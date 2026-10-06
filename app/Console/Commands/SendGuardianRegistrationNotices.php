<?php

namespace App\Console\Commands;

use App\EnrollmentRequests\PhysicalNotice;
use App\Mail\GuardianRegistrationNotice;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendGuardianRegistrationNotices extends Command
{
    protected $signature = 'bc:send-guardian-notices';

    protected $description = 'Envia avisos documentais pendentes ao e-mail cadastrado no PMD';

    public function handle(): int
    {
        if (!config('bc-notifications.enabled')) {
            $this->info('Avisos desativados. Configure BC_GUARDIAN_NOTIFICATIONS_ENABLED após validar o transporte de e-mail.');

            return self::SUCCESS;
        }

        $ids = DB::table('bc_guardian_notifications')->whereNull('sent_at')
            ->where('attempts', '<', 5)
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')->limit(100)->pluck('id');
        $sent = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$sent) {
                $notice = DB::table('bc_guardian_notifications')->where('id', $id)->lockForUpdate()->first();
                if (!$notice || $notice->sent_at || $notice->attempts >= 5
                    || ($notice->next_attempt_at && $notice->next_attempt_at > now())) {
                    return;
                }
                $event = DB::table('bc_registration_events')->where('id', $notice->registration_event_id)->first();
                $request = $event ? DB::table('bc_registration_requests')->where('id', $event->registration_request_id)->first() : null;
                $early = null;
                if ($notice->pmd_document_event_id) {
                    $event = DB::table('preregistration_document_events')->where('id', $notice->pmd_document_event_id)->first();
                    $early = $event ? PreRegistration::query()->with('responsible')->find($event->preregistration_id) : null;
                    $linked = $early ? DB::table('bc_registration_requests')->where('pmd_preregistration_id', $early->id)->first() : null;
                    $request = $linked ?: ($early ? (object) [
                        'pmd_preregistration_id' => $early->id, 'source_reference' => $early->protocol,
                        'protocol' => $early->protocol, 'status' => $early->status,
                        'document_deadline' => $early->documentation_deadline,
                    ] : null);
                }
                if (in_array($notice->kind, ['REMINDER', 'DOCUMENTS_OPEN']) && (!$request
                    || Carbon::parse($request->document_deadline)->lte(now())
                    || in_array($request->status, ['APROVADA', 'VAGA_CONFIRMADA', 'MATRICULA_EFETIVADA',
                        'NAO_COMPARECEU', 'PRAZO_EXPIRADO', 'INDEFERIDA', 'CANCELADA']))) {
                    DB::table('bc_guardian_notifications')->where('id', $id)->update([
                        'attempts' => 5, 'last_error' => 'Lembrete desatualizado', 'updated_at' => now(),
                    ]);

                    return;
                }
                $pmd = $early ?: ($request?->pmd_preregistration_id
                    ? PreRegistration::query()->with('responsible')->find($request->pmd_preregistration_id) : null);
                $email = $pmd?->responsible?->email;
                if (PhysicalNotice::obsolete($notice->kind, $request, $event)) {
                    DB::table('bc_guardian_notifications')->where('id', $id)->update(['attempts' => 5, 'last_error' => 'Aviso físico desatualizado', 'updated_at' => now()]);

                    return;
                }
                if ($pmd && $notice->kind !== 'REGISTERED'
                    && in_array($pmd->status, [PreRegistration::STATUS_ACCEPTED, PreRegistration::STATUS_REJECTED])) {
                    DB::table('bc_guardian_notifications')->where('id', $id)->update([
                        'attempts' => 5, 'last_error' => 'Aviso desatualizado', 'updated_at' => now(),
                    ]);

                    return;
                }
                if (!$request || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    DB::table('bc_guardian_notifications')->where('id', $id)->update([
                        'attempts' => 5, 'last_error' => 'Destinatário indisponível no PMD', 'updated_at' => now(),
                    ]);

                    return;
                }
                try {
                    Mail::to($email)->send(new GuardianRegistrationNotice($notice->kind,
                        $request->source_reference ?: $request->protocol,
                        in_array($notice->kind, ['CORRECTION', 'PHYSICAL_REQUIRED', 'PHYSICAL_REMINDER', 'PHYSICAL_EXPIRED']) ? (isset(json_decode($event->metadata ?? '{}', true)['delivery_deadline']) ? Carbon::parse(json_decode($event->metadata, true)['delivery_deadline'])->format('d/m/Y H:i') : null) : (in_array($notice->kind, ['REMINDER', 'DOCUMENTS_OPEN']) ? Carbon::parse($request->document_deadline)->format('d/m/Y H:i') : null),
                        $notice->kind === 'DOCUMENTS_OPEN' ? Carbon::parse($event->created_at)->format('d/m/Y H:i') : null));
                    DB::table('bc_guardian_notifications')->where('id', $id)->update([
                        'sent_at' => now(), 'attempts' => $notice->attempts + 1,
                        'last_error' => null, 'updated_at' => now(),
                    ]);
                    $sent++;
                } catch (\Throwable $error) {
                    DB::table('bc_guardian_notifications')->where('id', $id)->update([
                        'attempts' => $notice->attempts + 1,
                        'next_attempt_at' => now()->addMinutes(5 * (2 ** $notice->attempts)),
                        'last_error' => $error::class, 'updated_at' => now(),
                    ]);
                }
            });
        }
        $this->info("Avisos enviados: {$sent}");

        return self::SUCCESS;
    }
}
