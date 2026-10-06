<?php

namespace Tests\Feature;

use App\EnrollmentRequests\GuardianMessageTransport;
use App\Models\BcDemoEntity;
use App\Models\RegistrationRequest;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GuardianMessageDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    private int $noticeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        Http::preventStrayRequests();
        if (!BcDemoEntity::where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
        $request = RegistrationRequest::whereNotNull('pmd_preregistration_id')->firstOrFail();
        $pmd = PreRegistration::with('responsible')->findOrFail($request->pmd_preregistration_id);
        $pmd->update(['status' => PreRegistration::STATUS_SUMMONED]);
        $pmd->responsible->update(['mobile' => '(47) 99999-1234']);
        $event = DB::table('bc_registration_events')->insertGetId([
            'registration_request_id' => $request->id, 'event' => 'DOCUMENTATION_APPROVED', 'actor_type' => 'TEST', 'created_at' => now(),
        ]);
        $this->noticeId = DB::table('bc_guardian_notifications')->insertGetId([
            'registration_event_id' => $event, 'kind' => 'DOCUMENTATION_APPROVED', 'created_at' => now(), 'updated_at' => now(),
        ]);
        config(['bc-messages.enabled' => true, 'bc-messages.start_at' => now()->subSecond()->toDateTimeString(),
            'bc-messages.channels.sms' => ['enabled' => true, 'url' => 'https://bridge.test/sms', 'token' => 'test-secret'],
            'bc-messages.channels.whatsapp' => ['enabled' => true, 'url' => 'https://bridge.test/whatsapp', 'token' => 'test-secret']]);
    }

    public function test_each_channel_uses_idempotency_and_reuses_safe_notice_text(): void
    {
        Http::fake(['bridge.test/*' => Http::response([], 202)]);
        $this->artisan('bc:send-guardian-messages')->assertSuccessful();
        $this->artisan('bc:send-guardian-messages')->assertSuccessful();
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->data()['recipient'] === '+5547999991234'
            && str_contains($request->data()['message'], 'A matrícula ainda não existe')
            && $request->hasHeader('Idempotency-Key', 'bc-message-'.$this->noticeId.'-sms')
            && $request->hasHeader('Authorization', 'Bearer test-secret'));
        $this->assertSame(2, DB::table('bc_guardian_message_deliveries')->where('notification_id', $this->noticeId)->whereNotNull('sent_at')->count());
        $this->assertNull(DB::table('bc_guardian_notifications')->find($this->noticeId)->sent_at);
    }

    public function test_failed_channel_retries_without_repeating_successful_channel(): void
    {
        Http::fake(['bridge.test/sms' => Http::sequence()->push([], 503)->push([], 202), 'bridge.test/whatsapp' => Http::response([], 202)]);
        $this->artisan('bc:send-guardian-messages')->assertSuccessful();
        $this->artisan('bc:send-guardian-messages')->assertSuccessful();
        Http::assertSentCount(2);
        $sms = DB::table('bc_guardian_message_deliveries')->where('channel', 'sms')->where('notification_id', $this->noticeId)->first();
        $this->assertSame(1, $sms->attempts);
        $this->assertNotNull($sms->next_attempt_at);
        DB::table('bc_guardian_message_deliveries')->where('id', $sms->id)->update(['next_attempt_at' => now()->subSecond()]);
        $this->artisan('bc:send-guardian-messages')->assertSuccessful();
        $this->assertDatabaseHas('bc_guardian_message_deliveries', ['id' => $sms->id, 'attempts' => 2, 'last_error' => null]);
    }

    public function test_disabled_channels_and_missing_cutoff_do_not_send(): void
    {
        config(['bc-messages.enabled' => false]);
        $this->artisan('bc:send-guardian-messages')->assertSuccessful();
        Http::assertNothingSent();
        config(['bc-messages.enabled' => true, 'bc-messages.start_at' => null]);
        $this->artisan('bc:send-guardian-messages')->assertFailed();
        Http::assertNothingSent();
    }

    public function test_historical_notices_before_cutoff_are_not_replayed(): void
    {
        DB::table('bc_guardian_notifications')->where('id', $this->noticeId)->update(['created_at' => now()->subDay()]);
        $this->artisan('bc:send-guardian-messages')->assertSuccessful();
        Http::assertNothingSent();
        $this->assertFalse(DB::table('bc_guardian_message_deliveries')->where('notification_id', $this->noticeId)->exists());
    }

    public function test_invalid_phone_is_not_sent_and_redirect_is_not_accepted(): void
    {
        $transport = app(GuardianMessageTransport::class);
        $this->assertNull($transport->recipient('ramal'));
        $this->assertSame('+5547999991234', $transport->recipient('(47) 99999-1234'));
        Http::fake(['bridge.test/*' => Http::response([], 302)]);
        $this->artisan('bc:send-guardian-messages')->assertSuccessful();
        $this->assertSame(0, DB::table('bc_guardian_message_deliveries')->whereNotNull('sent_at')->count());
        $this->assertSame(2, DB::table('bc_guardian_message_deliveries')->where('attempts', 1)->count());
    }
}
