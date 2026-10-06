<?php

namespace App\EnrollmentRequests;

use App\Models\RegistrationDocument;
use App\Models\RegistrationRequest;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentDeadlines
{
    public static function initial(PreRegistration $pmd): Carbon
    {
        if ($pmd->documentation_deadline && $pmd->documentation_status) {
            return Carbon::parse($pmd->documentation_deadline);
        }
        if ($pmd->process->documentation_delivery_days) {
            return now()->addDays((int) $pmd->process->documentation_delivery_days)->endOfDay();
        }

        return Carbon::parse($pmd->documentation_deadline ?? $pmd->process->documentation_deadline ?? now()->addDays(7)->endOfDay());
    }

    public static function effective(RegistrationDocument $document, RegistrationRequest $request): Carbon
    {
        return $document->delivery_deadline ? Carbon::parse($document->delivery_deadline) : $request->document_deadline;
    }

    public static function timely(RegistrationDocument $document, RegistrationRequest $request): bool
    {
        return $document->received_at && $document->received_at->lte(self::effective($document, $request));
    }

    public static function retry(RegistrationRequest $request): Carbon
    {
        $processId = $request->preregistration?->process_id;
        $days = $processId ? DB::table('processes')->where('id', $processId)->value('documentation_retry_days') : 3;

        return now()->addDays((int) ($days ?? 3))->endOfDay();
    }
}
