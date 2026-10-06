<?php

namespace App\Console\Commands;

use App\EnrollmentRequests\RequestStatus;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HistoricalExpiryReview extends Command
{
    protected $signature = 'bc:historical-expiry-review
        {--process= : Filtrar por ID do processo}
        {--school= : Filtrar por ID da escola}
        {--json : Emitir somente relatório JSON agregado}';

    protected $description = 'Identifica rejeições com indícios de vencimento documental, sem restaurar inscrições';

    public function handle(): int
    {
        foreach (['process', 'school'] as $filter) {
            $value = $this->option($filter);
            if ($value !== null && (!ctype_digit((string) $value) || (int) $value < 1)) {
                $this->error('Filtros exigem IDs inteiros positivos.');

                return self::FAILURE;
            }
        }
        $query = DB::table('preregistrations as pmd')->where('pmd.status', PreRegistration::STATUS_REJECTED)
            ->where(function ($query) {
                $query->where('pmd.documentation_status', 'DEADLINE_EXPIRED')
                    ->orWhereExists(function ($bc) {
                        $bc->selectRaw('1')->from('bc_registration_requests as bc')
                            ->whereColumn('bc.pmd_preregistration_id', 'pmd.id')
                            ->whereIn('bc.status', [RequestStatus::Expired->value, RequestStatus::NoShow->value]);
                    })
                    ->orWhereExists(function ($audit) {
                        $audit->selectRaw('1')->from('preregistration_document_events as audit')
                            ->whereColumn('audit.preregistration_id', 'pmd.id')
                            ->where('audit.event', 'DOCUMENT_DEADLINE_EXPIRED')->where('audit.actor_type', 'SYSTEM');
                    });
            });
        foreach (['process' => 'process_id', 'school' => 'school_id'] as $filter => $column) {
            if ($this->option($filter) !== null) {
                $query->where('pmd.'.$column, (int) $this->option($filter));
            }
        }
        // EXISTS avoids counting versions, links or repeated audit events more than once.
        $groups = $query->select('pmd.process_id', 'pmd.school_id')->selectRaw('COUNT(*) AS candidates')
            ->groupBy('pmd.process_id', 'pmd.school_id')->orderBy('pmd.process_id')->orderBy('pmd.school_id')
            ->get()->map(fn ($row) => [
                'process_id' => (int) $row->process_id, 'school_id' => (int) $row->school_id,
                'candidates' => (int) $row->candidates,
            ])->all();
        $report = ['read_only' => true, 'total' => array_sum(array_column($groups, 'candidates')),
            'groups' => $groups, 'classification' => 'INDICATION_REQUIRES_MANUAL_REVIEW'];
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }
        $this->table(['Processo (ID)', 'Escola (ID)', 'Candidatos'], $groups);
        $this->info('Total para revisão: '.$report['total'].'.');
        $this->line('Indícios não comprovam a causa da rejeição. Revisar a decisão e a auditoria antes de qualquer ação.');
        $this->line('Nenhuma inscrição foi restaurada; nenhum estado, prazo, arquivo ou matrícula foi alterado.');

        return self::SUCCESS;
    }
}
