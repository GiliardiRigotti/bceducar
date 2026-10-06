<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Correct only matching, active, un-enrolled V2 intermediate registrations.
        DB::statement(<<<'SQL'
            UPDATE pmieducar.matricula AS m
            SET turno_pre_matricula = t.turma_turno_id
            FROM bc_registration_requests AS r
            JOIN pmieducar.turma AS t ON t.cod_turma = r.school_class_id
            JOIN preregistrations AS p ON p.id = r.pmd_preregistration_id
            WHERE r.workflow_version = 2 AND r.intermediate_registration_id = m.cod_matricula
              AND r.integration_status = 'INTEGRATED' AND r.physical_confirmed_at IS NULL
              AND r.status NOT IN ('MATRICULA_EFETIVADA', 'INDEFERIDA', 'CANCELADA')
              AND m.ativo = 1 AND m.aprovado = 11 AND m.turno_pre_matricula IS NULL
              AND m.ref_cod_aluno = r.student_id AND m.ano = r.school_year
              AND m.ref_ref_cod_escola = r.school_id AND m.ref_ref_cod_serie = r.grade_id
              AND t.ref_ref_cod_escola = r.school_id AND t.ref_ref_cod_serie = r.grade_id
              AND t.ano = r.school_year AND t.ativo = 1 AND t.turma_turno_id = p.period_id
              AND p.school_id = r.school_id AND p.grade_id = r.grade_id
              AND NOT EXISTS (SELECT 1 FROM pmieducar.matricula_turma AS e
                              WHERE e.ref_cod_matricula = m.cod_matricula AND e.ativo = 1)
            SQL);
    }

    public function down(): void
    {
        // Preserve corrected native data; reverting code must not erase the school shift.
    }
};
