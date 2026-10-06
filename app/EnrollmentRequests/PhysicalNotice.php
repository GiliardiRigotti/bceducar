<?php

namespace App\EnrollmentRequests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PhysicalNotice
{
    public static function obsolete(string $kind, ?object $request, ?object $event): bool
    {
        if (!in_array($kind, ['PHYSICAL_REMINDER', 'PHYSICAL_EXPIRED'])) {
            return false;
        }
        if (!$request || $request->physical_confirmed_at || in_array($request->status, ['MATRICULA_EFETIVADA', 'INDEFERIDA', 'CANCELADA'])) {
            return true;
        }
        $metadata = json_decode($event?->metadata ?? '{}', true);
        $review = DB::table('bc_physical_reviews')->where('registration_request_id', $request->id)
            ->where('subject', $metadata['subject'] ?? '')->first();
        $deadline = $review?->status === 'PENDING' ? $review->deadline : $request->physical_deadline;

        return $review?->status === 'APPROVED' || !$deadline
            || Carbon::parse($deadline)->toIso8601String() !== ($metadata['delivery_deadline'] ?? null)
            || ($kind === 'PHYSICAL_REMINDER' && Carbon::parse($deadline)->lt(now()));
    }
}
