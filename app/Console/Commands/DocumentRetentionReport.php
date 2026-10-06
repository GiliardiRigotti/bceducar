<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DocumentRetentionReport extends Command
{
    protected $signature = 'bc:document-retention-report {--days= : Simular prazo em dias, sem alterar dados}';

    protected $description = 'Relatório agregado de arquivos de solicitações encerradas, sem excluir documentos';

    public function handle(): int
    {
        $days = $this->option('days') ?? config('bc-retention.days');
        if (!ctype_digit((string) $days) || (int) $days < 1 || (int) $days > 36500) {
            $this->error('Informe --days com prazo positivo ou configure o prazo da política institucional.');

            return self::FAILURE;
        }
        $approved = !$this->option('days') && config('bc-retention.approved') && config('bc-retention.reference');
        $this->info($approved ? 'Relatório da política configurada; nenhum descarte será executado.' : 'Simulação; não representa aprovação da política nem autorização de descarte.');
        $rows = DB::table('bc_registration_documents as document')
            ->join('bc_registration_requests as request', 'request.id', '=', 'document.registration_request_id')
            ->whereNotNull('document.path')
            ->whereIn('request.status', ['MATRICULA_EFETIVADA', 'PRAZO_EXPIRADO', 'INDEFERIDA', 'CANCELADA', 'NAO_COMPARECEU'])
            ->where('request.updated_at', '<=', now()->subDays((int) $days))
            ->where('document.created_at', '<=', now()->subDays((int) $days))
            ->select('document.document_type')->selectRaw('count(*) as total')
            ->groupBy('document.document_type')->orderBy('document.document_type')->get();
        $this->table(['Categoria', 'Arquivos para avaliação'], $rows->map(fn ($row) => [$row->document_type, $row->total])->all());
        $this->info('Total para avaliação: '.$rows->sum('total').'.');
        $this->info('Solicitações abertas e arquivos anteriores ao vínculo são preservados. Nenhum arquivo ou registro foi removido.');

        return self::SUCCESS;
    }
}
