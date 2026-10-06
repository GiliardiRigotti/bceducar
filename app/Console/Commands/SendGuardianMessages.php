<?php

namespace App\Console\Commands;

use App\EnrollmentRequests\GuardianMessageTransport;
use App\EnrollmentRequests\PhysicalNotice;
use App\Mail\GuardianRegistrationNotice;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SendGuardianMessages extends Command
{
    protected $signature = 'bc:send-guardian-messages';

    protected $description = 'Entrega avisos aos conectores SMS/WhatsApp configurados, com corte explícito de histórico';

    public function handle(GuardianMessageTransport $transport): int
    {
        if (!config('bc-messages.enabled')) {
            $this->info('Canais SMS/WhatsApp desativados.');

            return self::SUCCESS;
        }
        if (!config('bc-messages.start_at') || !strtotime(config('bc-messages.start_at'))) {
            $this->error('Defina BC_GUARDIAN_MESSAGES_START_AT antes de ativar os canais.');

            return self::FAILURE;
        }
        $sent = 0;
        foreach (config('bc-messages.channels') as $channel => $options) {
            if (!$options['enabled']) {
                continue;
            }
            $ids = DB::table('bc_guardian_notifications as notice')
                ->where('notice.created_at', '>=', Carbon::parse(config('bc-messages.start_at')))
                ->whereNotExists(function ($query) use ($channel) {
                    $query->selectRaw('1')->from('bc_guardian_message_deliveries as delivery')
                        ->whereColumn('delivery.notification_id', 'notice.id')->where('delivery.channel', $channel)
                        ->where(fn ($q) => $q->whereNotNull('delivery.sent_at')->orWhere('delivery.attempts', '>=', 5)
                            ->orWhere('delivery.next_attempt_at', '>', now()));
                })->orderBy('notice.id')->limit(100)->pluck('notice.id');
            foreach ($ids as $id) {
                DB::table('bc_guardian_message_deliveries')->insertOrIgnore([
                    'notification_id' => $id, 'channel' => $channel, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::transaction(function () use ($id, $channel, $transport, &$sent) {
                    $delivery = DB::table('bc_guardian_message_deliveries')->where('notification_id', $id)
                        ->where('channel', $channel)->lockForUpdate()->first();
                    if ($delivery->sent_at || $delivery->attempts >= 5 || ($delivery->next_attempt_at && Carbon::parse($delivery->next_attempt_at)->gt(now()))) {
                        return;
                    }
                    $notice = DB::table('bc_guardian_notifications')->find($id);
                    $early = $notice->pmd_document_event_id
                        ? DB::table('preregistration_document_events')->find($notice->pmd_document_event_id) : null;
                    $event = $notice->registration_event_id
                        ? DB::table('bc_registration_events')->find($notice->registration_event_id) : null;
                    $request = $event ? DB::table('bc_registration_requests')->find($event->registration_request_id) : null;
                    $pmdId = $early?->preregistration_id ?? $request?->pmd_preregistration_id;
                    $pmd = $pmdId ? PreRegistration::query()->with('responsible')->find($pmdId) : null;
                    $deadline = in_array($notice->kind, ['CORRECTION', 'PHYSICAL_REQUIRED', 'PHYSICAL_REMINDER', 'PHYSICAL_EXPIRED']) ? (json_decode($event?->metadata ?? '{}', true)['delivery_deadline'] ?? null) : ($request?->document_deadline ?? $pmd?->documentation_deadline);
                    $obsolete = !$pmd || ($notice->kind !== 'REGISTERED' && in_array($pmd->status, [PreRegistration::STATUS_ACCEPTED, PreRegistration::STATUS_REJECTED]));
                    if (in_array($notice->kind, ['DOCUMENTS_OPEN', 'REMINDER'])) {
                        $obsolete = $obsolete || !$deadline || Carbon::parse($deadline)->lte(now())
                            || in_array($request?->status, ['APROVADA', 'VAGA_CONFIRMADA', 'MATRICULA_EFETIVADA', 'PRAZO_EXPIRADO', 'INDEFERIDA', 'CANCELADA', 'NAO_COMPARECEU']);
                    }
                    $obsolete = $obsolete || PhysicalNotice::obsolete($notice->kind, $request, $event);
                    $recipient = $transport->recipient($pmd?->responsible?->mobile ?: $pmd?->responsible?->phone);
                    if ($obsolete || !$recipient) {
                        DB::table('bc_guardian_message_deliveries')->where('id', $delivery->id)->update([
                            'attempts' => 5, 'last_error' => $obsolete ? 'Aviso desatualizado' : 'Telefone indisponível',
                            'updated_at' => now(),
                        ]);

                        return;
                    }
                    try {
                        $mail = new GuardianRegistrationNotice($notice->kind, $pmd->protocol,
                            $deadline ? Carbon::parse($deadline)->format('d/m/Y H:i') : null);
                        $body = view('mail.guardian-registration-notice', ['kind' => $mail->kind,
                            'protocol' => $mail->protocol, 'deadline' => $mail->deadline, 'openedAt' => null])->render();
                        $transport->send($channel, $recipient, ['subject' => $mail->envelope()->subject, 'message' => trim($body)],
                            'bc-message-'.$id.'-'.$channel);
                        DB::table('bc_guardian_message_deliveries')->where('id', $delivery->id)->update([
                            'sent_at' => now(), 'attempts' => $delivery->attempts + 1, 'next_attempt_at' => null,
                            'last_error' => null, 'updated_at' => now(),
                        ]);
                        $sent++;
                    } catch (\Throwable $error) {
                        DB::table('bc_guardian_message_deliveries')->where('id', $delivery->id)->update([
                            'attempts' => $delivery->attempts + 1, 'next_attempt_at' => now()->addMinutes(5 * (2 ** $delivery->attempts)),
                            'last_error' => $error::class, 'updated_at' => now(),
                        ]);
                    }
                });
            }
        }
        $this->info('Mensagens aceitas pelos conectores: '.$sent);

        return self::SUCCESS;
    }
}
