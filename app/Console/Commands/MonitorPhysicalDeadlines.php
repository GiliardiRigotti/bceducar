<?php

namespace App\Console\Commands;

use App\EnrollmentRequests\EventType;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\Models\RegistrationRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MonitorPhysicalDeadlines extends Command
{
    protected $signature = 'bc:monitor-physical-deadlines';

    protected $description = 'Registra vencimentos e lembretes físicos sem cancelar matrículas automaticamente';

    public function handle(RegistrationWorkflow $workflow): int
    {
        $count = 0;
        RegistrationRequest::query()->where('workflow_version', 2)->where('integration_status', 'INTEGRATED')
            ->whereNull('physical_confirmed_at')->whereNull('seed_source')
            ->whereNotIn('status', ['MATRICULA_EFETIVADA', 'INDEFERIDA', 'CANCELADA'])
            ->chunkById(100, function ($requests) use ($workflow, &$count) {
                foreach ($requests as $candidate) {
                    DB::transaction(function () use ($candidate, $workflow, &$count) {
                        $request = RegistrationRequest::query()->lockForUpdate()->findOrFail($candidate->id);
                        if ($request->physical_confirmed_at || $request->status->terminal()) {
                            return;
                        }
                        $reviews = DB::table('bc_physical_reviews')->where('registration_request_id', $request->id)->get()->keyBy('subject');
                        $subjects = $request->documents()->where('required', true)->where('status', '!=', 'SUBSTITUIDO')
                            ->pluck('id')->map(fn ($id) => 'document:'.$id)->push('cadastro');
                        $subjects = $subjects->merge($reviews->where('status', 'PENDING')->keys())->unique();
                        foreach ($subjects as $subject) {
                            $review = $reviews->get($subject);
                            if ($review?->status === 'APPROVED') {
                                continue;
                            }
                            $deadline = $review?->status === 'PENDING' ? $review->deadline : $request->physical_deadline;
                            if (!$deadline || Carbon::parse($deadline)->gt(now()->addDay())) {
                                continue;
                            }
                            $deadline = Carbon::parse($deadline);
                            $event = $deadline->lt(now()) ? EventType::PhysicalExpired : EventType::PhysicalReminder;
                            $key = $subject.'|'.$deadline->toIso8601String();
                            if ($request->events()->where('event', $event->value)->where('metadata->deadline_key', $key)->exists()) {
                                continue;
                            }
                            $workflow->event($request, $event, metadata: ['subject' => $subject,
                                'deadline_key' => $key, 'delivery_deadline' => $deadline->toIso8601String()]);
                            $count++;
                        }
                    });
                }
            });
        $this->info('Avisos físicos preparados: '.$count);

        return self::SUCCESS;
    }
}
