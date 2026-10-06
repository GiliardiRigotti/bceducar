<?php

namespace App\Console\Commands;

use App\EnrollmentRequests\PmdDocumentExpiration;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\Models\RegistrationRequest;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireRegistrationRequests extends Command
{
    protected $signature = 'bc:expire-registration-requests';

    protected $description = 'Registra expiração documental e não comparecimento em ambos os canais';

    public function handle(RegistrationWorkflow $workflow, PmdDocumentExpiration $early): int
    {
        $count = 0;
        RegistrationRequest::query()->where('document_deadline', '<', now())->chunkById(100, function ($requests) use ($workflow, &$count) {
            foreach ($requests as $request) {
                $count += (int) $workflow->expire($request);
            }
        });
        $this->info("Solicitações expiradas: {$count}");

        $earlyCount = 0;
        DB::table('preregistrations as pmd')->join('processes', 'processes.id', '=', 'pmd.process_id')
            ->whereIn('pmd.status', [PreRegistration::STATUS_SUMMONED, PreRegistration::STATUS_IN_CONFIRMATION])
            ->whereIn('pmd.documentation_status', ['AWAITING_DOCUMENTS', 'AWAITING_REVIEW', 'UNDER_REVIEW', 'CORRECTION_REQUIRED'])
            ->whereRaw('COALESCE(pmd.documentation_deadline, processes.documentation_deadline) < ?', [now()])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('bc_registration_requests')
                ->whereColumn('pmd_preregistration_id', 'pmd.id'))
            ->select('pmd.id')->chunkById(100, function ($registrations) use ($early, &$earlyCount) {
                foreach ($registrations as $registration) {
                    $earlyCount += (int) $early->expire($registration->id);
                }
            }, 'pmd.id', 'id');
        $this->info("Pré-matrículas sem vínculo expiradas: {$earlyCount}");

        return self::SUCCESS;
    }
}
