<?php

// Execute only in the isolated testing database. All database changes are rolled back.
use App\Models\RegistrationRequest;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (!$app->environment('testing') || DB::connection()->getDatabaseName() !== 'testing') {
    throw new RuntimeException('Run with APP_ENV=testing and DB_DATABASE=testing.');
}

config([
    'bc-notifications.enabled' => true,
    'mail.default' => 'smtp',
    'mail.mailers.smtp.host' => 'mailpit',
    'mail.mailers.smtp.port' => 1025,
    'mail.mailers.smtp.encryption' => null,
    'mail.mailers.smtp.username' => null,
    'mail.mailers.smtp.password' => null,
    'mail.mailers.smtp.timeout' => 10,
    'mail.from.address' => 'no-reply@bceducar.test',
    'mail.from.name' => 'BC Educar - teste SMTP',
]);

$recipient = 'smtp-'.bin2hex(random_bytes(6)).'@example.test';
$api = 'http://mailpit:8025/api/v1';
$search = ['query' => 'to:'.$recipient];
$check = function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
DB::beginTransaction();
try {
    // Isolate the sender's batch without changing the persistent testing queue.
    DB::table('bc_guardian_notifications')->delete();
    $request = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->firstOrFail();
    $pmd = PreRegistration::query()->withoutGlobalScopes()->with('responsible')->findOrFail($request->pmd_preregistration_id);
    $pmd->responsible->update(['email' => $recipient]);
    DB::table('preregistrations')->where('id', $pmd->id)->update(['status' => PreRegistration::STATUS_SUMMONED]);
    DB::table('bc_registration_requests')->where('id', $request->id)->update([
        'status' => 'AGUARDANDO_DOCUMENTOS', 'document_deadline' => now()->addDays(7),
        'source_reference' => 'SMTP-TESTE', 'protocol' => 'SMTP-TESTE',
    ]);
    $kinds = ['PREREGISTRATION_REGISTERED', 'DOCUMENTS_OPEN', 'MODE_SELECTED',
        'DOCUMENT_RECEIVED', 'CORRECTION', 'DOCUMENTATION_APPROVED', 'REMINDER', 'EXPIRED', 'REGISTERED'];
    foreach ($kinds as $kind) {
        $origin = ['registration_event_id' => null, 'pmd_document_event_id' => null];
        if ($kind === 'PREREGISTRATION_REGISTERED') {
            $origin['pmd_document_event_id'] = DB::table('preregistration_document_events')->insertGetId([
                'preregistration_id' => $pmd->id, 'event' => $kind,
                'actor_type' => 'SMTP_TEST', 'metadata' => '{}', 'created_at' => now(),
            ]);
        } else {
            $origin['registration_event_id'] = DB::table('bc_registration_events')->insertGetId([
                'registration_request_id' => $request->id, 'event' => $kind,
                'actor_type' => 'SMTP_TEST', 'metadata' => '{}', 'created_at' => now(),
            ]);
        }
        DB::table('bc_guardian_notifications')->insert($origin + [
            'kind' => $kind, 'attempts' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $check(Artisan::call('bc:send-guardian-notices') === 0, 'Sender command failed.');
    $check(DB::table('bc_guardian_notifications')->whereNotNull('sent_at')->count() === 9, 'Not all nine notices were delivered.');
    $messages = Http::timeout(10)->get($api.'/search', $search)->throw()->json();
    $check($messages['messages_count'] === 9, 'Mailpit did not capture exactly nine messages.');
    $subjects = [];
    foreach ($messages['messages'] as $message) {
        $detail = Http::timeout(10)->get($api.'/message/'.$message['ID'])->throw()->json();
        $check(!empty($detail['HTML']) && !empty($detail['Text']), 'Missing HTML or text body.');
        $check(str_contains($detail['Text'], 'SMTP-TESTE'), 'Missing protocol in notice.');
        $check(str_contains($detail['Text'], '/matricula-digital'), 'Missing guardian link.');
        $subjects[] = $detail['Subject'];
        if (str_contains($detail['Subject'], 'aguardando efetiva')) {
            $check(str_contains($detail['Text'], 'A matrícula ainda não existe'), 'Approval message incorrectly implies enrollment.');
        }
    }
    $check(count(array_unique($subjects)) === 9, 'Distinct notice subjects expected.');
    Artisan::call('bc:send-guardian-notices');
    $again = Http::timeout(10)->get($api.'/search', $search)->throw()->json();
    $check($again['messages_count'] === 9, 'Repeated sender duplicated mail.');
    $eventId = DB::table('bc_registration_events')->insertGetId([
        'registration_request_id' => $request->id, 'event' => 'SMTP_RETRY_TEST',
        'actor_type' => 'SMTP_TEST', 'metadata' => '{}', 'created_at' => now(),
    ]);
    $retryId = DB::table('bc_guardian_notifications')->insertGetId([
        'registration_event_id' => $eventId, 'kind' => 'DOCUMENT_RECEIVED',
        'attempts' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    config(['mail.mailers.smtp.port' => 1026, 'mail.mailers.smtp.timeout' => 2]);
    app('mail.manager')->purge('smtp');
    Artisan::call('bc:send-guardian-notices');
    $failed = DB::table('bc_guardian_notifications')->find($retryId);
    $check($failed->attempts === 1 && !$failed->sent_at && $failed->last_error && $failed->next_attempt_at,
        'Connection failure did not schedule a retry.');
    Artisan::call('bc:send-guardian-notices');
    $check(DB::table('bc_guardian_notifications')->find($retryId)->attempts === 1,
        'Sender ignored retry backoff.');
    config(['mail.mailers.smtp.port' => 1025, 'mail.mailers.smtp.timeout' => 10]);
    app('mail.manager')->purge('smtp');
    DB::table('bc_guardian_notifications')->where('id', $retryId)->update(['next_attempt_at' => now()->subMinute()]);
    Artisan::call('bc:send-guardian-notices');
    $recovered = DB::table('bc_guardian_notifications')->find($retryId);
    $check($recovered->attempts === 2 && $recovered->sent_at && !$recovered->last_error,
        'Retry did not recover after SMTP connectivity returned.');
    Artisan::call('bc:send-guardian-notices');
    $final = Http::timeout(10)->get($api.'/search', $search)->throw()->json();
    $check($final['messages_count'] === 10, 'Retry recovery duplicated or lost a notice.');
    echo "PASS: 9 notice kinds captured via SMTP, HTML/text/protocol/link verified, no duplicates.\n";
    echo "PASS: connection failure, backoff and recovery verified; 10 messages total.\n";
    echo 'Mailbox filter: '.$recipient."\n";
    foreach ($subjects as $subject) {
        echo '- '.$subject."\n";
    }
} finally {
    DB::rollBack();
    echo "Testing database transaction rolled back.\n";
}
