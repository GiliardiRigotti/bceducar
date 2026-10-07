<?php

namespace App\Console\Commands;

use App\Models\LegacyRegistration;
use App\Models\RegistrationRequest;
use App\Models\RegistrationStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReportIntermediateEnrollments extends Command
{
    protected $signature = 'bc:report-intermediate-enrollments';

    protected $description = 'Levantamento somente leitura de matrículas 11 sem enturmação ativa (compatibilidade V2/V3)';

    public function handle(): int
    {
        $rows = DB::transaction(function () {
            return LegacyRegistration::query()->where('aprovado', RegistrationStatus::PRE_REGISTRATION)
                ->where('ativo', 1)->whereDoesntHave('activeEnrollments')->get()->map(function ($native) {
                    $request = RegistrationRequest::query()->where('intermediate_registration_id', $native->getKey())->first();
                    $class = $request?->schoolClass;

                    return [
                        'matricula' => $native->getKey(), 'aluno' => $native->ref_cod_aluno,
                        'escola' => $native->ref_ref_cod_escola, 'serie' => $native->ref_ref_cod_serie,
                        'ano' => $native->ano, 'turno' => $native->turno_pre_matricula,
                        'solicitacao' => $request?->id, 'turma_pretendida' => $class?->getKey(),
                        'vagas_disponiveis' => $class?->vacancies,
                        'estado_documental' => $request?->status->value,
                        'integracao' => $request?->integration_status,
                        'conferencia_fisica' => $request?->physical_confirmed_at?->toIso8601String(),
                        'revisoes_fisicas' => $request ? DB::table('bc_physical_reviews')
                            ->where('registration_request_id', $request->id)->get(['subject', 'status', 'deadline']) : [],
                        'possibilidade_segura' => $request && $class && $class->vacancies > 0
                            ? 'Reprocessamento explícito sujeito às validações documentais, cadastrais e nativas em transação.'
                            : 'Bloqueada: revisar vínculo BC, turma e disponibilidade.',
                    ];
                })->values();
        });
        $this->line(json_encode(['quantidade' => $rows->count(), 'registros' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
