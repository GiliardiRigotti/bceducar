<?php

namespace App\EnrollmentRequests;

use App\Models\BcDemoEntity;
use Illuminate\Support\Facades\DB;

class PmdDocumentAudit
{
    public function record(int $pmdId, string $event, array $metadata = [], string $actor = 'GUARDIAN'): int
    {
        $id = DB::table('preregistration_document_events')->insertGetId([
            'preregistration_id' => $pmdId, 'event' => $event, 'actor_type' => $actor,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
        $kind = match ($event) {
            'PREREGISTRATION_REGISTERED' => 'PREREGISTRATION_REGISTERED',
            'DOCUMENT_MODE_SELECTED', 'DOCUMENT_MODE_CHANGED' => 'MODE_SELECTED',
            'DOCUMENT_UPLOADED', 'DOCUMENT_REPLACED' => 'DOCUMENT_RECEIVED',
            'DOCUMENT_DEADLINE_EXPIRED' => 'EXPIRED',
            default => null,
        };
        $pmd = DB::table('preregistrations')->where('id', $pmdId)->first();
        $demo = $pmd && BcDemoEntity::query()->where('key', 'pmd.process')->where('legacy_id', $pmd->process_id)->exists();
        if ($kind && !$demo) {
            DB::table('bc_guardian_notifications')->insertOrIgnore([
                'pmd_document_event_id' => $id, 'kind' => $kind, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $id;
    }
}
