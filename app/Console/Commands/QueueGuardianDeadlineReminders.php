<?php

namespace App\Console\Commands;

use App\EnrollmentRequests\EventType;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\EnrollmentRequests\RequestStatus;
use App\Models\RegistrationRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueGuardianDeadlineReminders extends Command
{
    protected $signature = 'bc:queue-deadline-reminders';

    protected $description = 'Prepara um lembrete ao responsável nas 24 horas anteriores ao prazo documental';

    public function handle(RegistrationWorkflow $workflow): int
    {
        $statuses = [RequestStatus::Started, RequestStatus::AwaitingDocuments, RequestStatus::DocumentsSent,
            RequestStatus::AwaitingReview, RequestStatus::UnderReview, RequestStatus::Pending,
            RequestStatus::Corrected, RequestStatus::Scheduled, RequestStatus::AwaitingAttendance];
        $count = 0;
        RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->whereNull('seed_source')
            ->whereIn('status', array_map(fn ($status) => $status->value, $statuses))
            ->where('document_deadline', '>', now())->where('document_deadline', '<=', now()->addDay())
            ->chunkById(100, function ($requests) use ($workflow, $statuses, &$count) {
                foreach ($requests as $candidate) {
                    DB::transaction(function () use ($candidate, $workflow, $statuses, &$count) {
                        $request = RegistrationRequest::query()->lockForUpdate()->findOrFail($candidate->id);
                        if (!$request->pmd_preregistration_id || $request->seed_source
                            || !in_array($request->status, $statuses)
                            || $request->document_deadline->lte(now())
                            || $request->document_deadline->gt(now()->addDay())
                            || $request->events()->where('event', EventType::DeadlineReminder->value)->exists()) {
                            return;
                        }
                        $workflow->event($request, EventType::DeadlineReminder,
                            metadata: ['deadline' => $request->document_deadline->toIso8601String()]);
                        $count++;
                    });
                }
            });
        $this->info("Lembretes preparados: {$count}");

        return self::SUCCESS;
    }
}
