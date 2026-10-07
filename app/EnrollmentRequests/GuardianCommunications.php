<?php

namespace App\EnrollmentRequests;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class GuardianCommunications
{
    private function query(int $profileId): Builder
    {
        return DB::table('bc_guardian_notifications as notice')
            ->leftJoin('bc_registration_events as event', 'event.id', '=', 'notice.registration_event_id')
            ->leftJoin('bc_registration_requests as request', 'request.id', '=', 'event.registration_request_id')
            ->leftJoin('preregistration_document_events as early', 'early.id', '=', 'notice.pmd_document_event_id')
            ->join('bc_guardian_profile_applications as link', function ($join) {
                $join->on('link.pmd_id', '=', DB::raw('COALESCE(request.pmd_preregistration_id, early.preregistration_id)'));
            })->join('preregistrations as pmd', 'pmd.id', '=', 'link.pmd_id')
            ->join('people as student', 'student.id', '=', 'pmd.student_id')
            ->leftJoin('bc_guardian_dependents as dependent', 'dependent.id', '=', 'link.dependent_id')
            ->leftJoin('bc_guardian_notice_reads as reading', function ($join) use ($profileId) {
                $join->on('reading.notification_id', '=', 'notice.id')->where('reading.profile_id', $profileId);
            })->where('link.profile_id', $profileId);
    }

    public function unreadCount(int $profileId): int
    {
        return $this->query($profileId)->whereNull('reading.read_at')->count();
    }

    public function page(int $profileId, ?int $dependentId, ?int $pmdId, bool $unread = false)
    {
        if ($dependentId) {
            app(GuardianDependents::class)->owned($profileId, $dependentId);
        }
        if ($pmdId) {
            app(GuardianProfiles::class)->authorize($profileId, $pmdId);
        }

        return $this->query($profileId)->when($dependentId, fn ($query) => $query->where('link.dependent_id', $dependentId))
            ->when($pmdId, fn ($query) => $query->where('link.pmd_id', $pmdId))
            ->when($unread, fn ($query) => $query->whereNull('reading.read_at'))
            ->orderByDesc('notice.id')->select(['notice.id', 'notice.kind', 'notice.created_at', 'notice.sent_at', 'notice.attempts',
                'pmd.protocol', 'link.pmd_id', 'link.dependent_id', 'reading.read_at',
                'event.metadata as registration_metadata', 'early.metadata as early_metadata',
                'request.document_deadline', 'pmd.documentation_deadline'])
            ->selectRaw('COALESCE(dependent.name, student.name) AS dependent_name')->paginate(20)->withQueryString();
    }

    public function markRead(int $profileId, int $id): void
    {
        DB::transaction(function () use ($profileId, $id) {
            abort_unless($this->query($profileId)->where('notice.id', $id)->exists(), 403);
            DB::table('bc_guardian_notice_reads')->insertOrIgnore([
                'profile_id' => $profileId, 'notification_id' => $id, 'read_at' => now(),
            ]);
        });
    }

    public static function title(string $kind): string
    {
        return match ($kind) {
            'PREREGISTRATION_REGISTERED' => 'Pré-matrícula registrada',
            'DOCUMENTS_OPEN' => 'Documentação liberada',
            'MODE_SELECTED' => 'Forma de entrega escolhida',
            'DOCUMENT_RECEIVED' => 'Documento recebido para análise',
            'DOCUMENTATION_APPROVED' => 'Documentação aprovada',
            'PHYSICAL_REQUIRED' => 'Apresente os documentos físicos',
            'CORRECTION' => 'Correção solicitada pela escola',
            'EXPIRED' => 'Prazo documental encerrado',
            'REMINDER' => 'Lembrete de prazo documental',
            'PHYSICAL_REMINDER' => 'Lembrete de apresentação ou regularização física',
            'PHYSICAL_EXPIRED' => 'Prazo físico vencido: procure a escola',
            'REGISTERED' => 'Matrícula efetivada',
            default => 'Atualização da sua inscrição',
        };
    }

    public static function message(string $kind): string
    {
        return match ($kind) {
            'PREREGISTRATION_REGISTERED' => 'Sua inscrição foi recebida e aguarda análise da escola.',
            'DOCUMENTS_OPEN' => 'Sua pré-matrícula foi deferida. Escolha a forma de entrega e apresente os documentos no prazo.',
            'MODE_SELECTED' => 'A forma de entrega foi registrada. O prazo documental permanece o mesmo.',
            'DOCUMENT_RECEIVED' => 'Um documento foi recebido. Acompanhe a decisão individual da escola.',
            'DOCUMENTATION_APPROVED' => 'Os documentos foram aprovados. Aguarde a efetivação pela unidade escolar.',
            'PHYSICAL_REQUIRED' => 'A documentação digital foi aprovada. Apresente os originais na escola para concluir a matrícula.',
            'CORRECTION' => 'Consulte o documento ou a ficha indicada no acompanhamento e regularize a pendência.',
            'EXPIRED' => 'O prazo documental terminou. Procure a escola para orientação.',
            'REMINDER' => 'O prazo para entrega dos documentos está próximo do fim.',
            'PHYSICAL_REMINDER' => 'Apresente ou regularize os documentos indicados na escola dentro do prazo.',
            'PHYSICAL_EXPIRED' => 'O prazo físico terminou. Procure a escola para orientação sobre a regularização.',
            'REGISTERED' => 'Sua matrícula foi efetivada no i-Educar.',
            default => 'Consulte o acompanhamento para conhecer a situação da inscrição.',
        };
    }
}
