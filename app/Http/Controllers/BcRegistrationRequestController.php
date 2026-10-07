<?php

namespace App\Http\Controllers;

use App\EnrollmentRequests\DeclaredStudentData;
use App\EnrollmentRequests\DocumentReviewReasons;
use App\EnrollmentRequests\DocumentStatus;
use App\EnrollmentRequests\DocumentUploadPolicy;
use App\EnrollmentRequests\PhysicalConfirmation;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\EnrollmentRequests\RequestAccess;
use App\EnrollmentRequests\RequestStatus;
use App\Models\LegacyGrade;
use App\Models\LegacyPeriod;
use App\Models\LegacySchoolClass;
use App\Models\RegistrationDocument;
use App\Models\RegistrationRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BcRegistrationRequestController extends Controller
{
    public function index(Request $http)
    {
        $base = RegistrationRequest::query()->visibleTo($http->user())->documentaryStage();
        $enrollments = $http->routeIs('bc-registration.enrollments');
        if ($enrollments) {
            $base->whereNotNull('registration_id');
        }
        $scopeTotal = (clone $base)->count();
        $schools = app(RequestAccess::class)->schools($http->user())->with(['person', 'organization'])->orderBy('cod_escola')->get();
        $grades = LegacyGrade::query()->whereIn('cod_serie', (clone $base)->select('grade_id'))->orderBy('nm_serie')->get();
        $periods = LegacyPeriod::query()->whereIn('id', LegacySchoolClass::query()
            ->whereIn('cod_turma', (clone $base)->select('school_class_id'))->select('turma_turno_id'))
            ->orderBy('nome')->get();
        $processes = DB::table('processes')->whereIn('id', DB::table('preregistrations')
            ->whereIn('id', (clone $base)->whereNotNull('pmd_preregistration_id')->select('pmd_preregistration_id'))
            ->select('process_id'))->orderBy('name')->get(['id', 'name']);
        $query = $this->filteredQuery($http);
        $counts = (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $modeCounts = (clone $query)->selectRaw('attendance_mode, count(*) as total')
            ->groupBy('attendance_mode')->pluck('total', 'attendance_mode');
        $dueToday = (clone $query)->whereDate('document_deadline', today())->count();
        $overdue = (clone $query)->where('document_deadline', '<', now())
            ->whereNotIn('status', [RequestStatus::Registered->value, RequestStatus::Approved->value,
                RequestStatus::VacancyConfirmed->value, RequestStatus::Expired->value, RequestStatus::NoShow->value,
                RequestStatus::Rejected->value, RequestStatus::Cancelled->value])->count();
        $schoolCounts = (clone $query)->selectRaw('school_id, count(*) as total')
            ->groupBy('school_id')->pluck('total', 'school_id');
        $requests = $query->select('bc_registration_requests.*')->addSelect([
            'released_at' => DB::table('bc_registration_events')->selectRaw('MIN(created_at)')
                ->whereColumn('registration_request_id', 'bc_registration_requests.id')
                ->where('event', 'PREREGISTRATION_APPROVED'),
            'requested_at' => DB::table('preregistrations')->select('created_at')
                ->whereColumn('preregistrations.id', 'bc_registration_requests.pmd_preregistration_id')->limit(1),
        ])->with(['student.person', 'preregistration.student', 'preregistration.responsible', 'school', 'grade', 'schoolClass.period'])->withCount([
            'documents as required_total' => fn ($documents) => $documents->where('required', true)
                ->where('status', '!=', DocumentStatus::Replaced->value),
            'documents as required_received' => fn ($documents) => $documents->where('required', true)
                ->where('status', '!=', DocumentStatus::Replaced->value)->whereNotNull('received_at')
                ->whereRaw('received_at <= COALESCE(bc_registration_documents.delivery_deadline, bc_registration_requests.document_deadline)'),
            'documents as required_approved' => fn ($documents) => $documents->where('required', true)
                ->where('status', DocumentStatus::Approved->value)->whereNotNull('received_at')
                ->whereRaw('received_at <= COALESCE(bc_registration_documents.delivery_deadline, bc_registration_requests.document_deadline)'),
            'documents as required_corrections' => fn ($documents) => $documents->where('required', true)
                ->whereIn('status', [DocumentStatus::Rejected->value, DocumentStatus::Correction->value]),
        ])->orderByDesc('updated_at')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('bc-registration.index', compact('requests', 'counts', 'modeCounts', 'dueToday', 'overdue',
            'schools', 'grades', 'periods', 'processes', 'scopeTotal', 'schoolCounts', 'enrollments'));
    }

    private function filteredQuery(Request $http): Builder
    {
        $http->validate([
            'status' => ['nullable', Rule::enum(RequestStatus::class)],
            'only_enrolled' => ['nullable', 'boolean'],
            'attendance_mode' => ['nullable', Rule::in(['ONLINE', 'PRESENCIAL', 'UNSELECTED'])],
            'kind' => ['nullable', Rule::in(['NOVA', 'REMATRICULA', 'TRANSFERENCIA'])],
            'school_id' => ['nullable', 'integer', 'min:1'],
            'grade_id' => ['nullable', 'integer', 'min:1'],
            'period_id' => ['nullable', 'integer', 'min:1'],
            'process_id' => ['nullable', 'integer', 'min:1'],
            'school_year' => ['nullable', 'integer', 'between:2000,2100'],
            'deadline' => ['nullable', Rule::in(['today', 'next3', 'next7', 'overdue'])],
        ]);
        $query = RegistrationRequest::query()->visibleTo($http->user())->documentaryStage();
        if ($http->routeIs('bc-registration.enrollments') || $http->boolean('only_enrolled')) {
            $query->whereNotNull('registration_id');
        }
        foreach (['status', 'school_id', 'attendance_mode', 'kind'] as $filter) {
            if ($http->filled($filter)) {
                if ($filter === 'attendance_mode' && $http->input($filter) === 'UNSELECTED') {
                    $query->whereNull('attendance_mode');
                } else {
                    $query->where($filter, $http->input($filter));
                }
            }
        }
        if ($http->filled('deadline')) {
            match ($http->input('deadline')) {
                'today' => $query->whereDate('document_deadline', today()),
                'next3' => $query->whereBetween('document_deadline', [now(), now()->addDays(3)]),
                'next7' => $query->whereBetween('document_deadline', [now(), now()->addDays(7)]),
                'overdue' => $query->where('document_deadline', '<', now()),
                default => null,
            };
        }
        if ($http->filled('school_year')) {
            $query->where('school_year', $http->integer('school_year'));
        }
        if ($http->filled('grade_id')) {
            $query->where('grade_id', $http->integer('grade_id'));
        }
        if ($http->filled('period_id')) {
            $query->whereIn('school_class_id', LegacySchoolClass::query()
                ->where('turma_turno_id', $http->integer('period_id'))->select('cod_turma'));
        }
        if ($http->filled('process_id')) {
            $query->whereIn('pmd_preregistration_id', DB::table('preregistrations')
                ->where('process_id', $http->integer('process_id'))->select('id'));
        }

        return $query;
    }

    public function export(Request $http)
    {
        $columns = ['bc_registration_requests.school_id', 'bc_registration_requests.grade_id',
            'bc_registration_requests.school_year', 'bc_registration_requests.attendance_mode',
            'bc_registration_requests.status', 'report_class.turma_turno_id'];
        $query = $this->filteredQuery($http)
            ->join('pmieducar.turma as report_class', 'report_class.cod_turma', '=', 'bc_registration_requests.school_class_id')
            ->select($columns)->selectRaw('count(*) as total')->groupBy($columns)
            ->with(['school', 'grade']);
        foreach ($columns as $column) {
            $query->orderBy($column);
        }
        $periods = LegacyPeriod::query()->pluck('nome', 'id');

        return response()->streamDownload(function () use ($query, $periods) {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Escola', 'Série', 'Turno', 'Ano letivo', 'Atendimento', 'Situação', 'Total'], ';', '"', '');
            foreach ($query->lazy(250) as $row) {
                $cells = [$row->school?->name ?? '', $row->grade?->name ?? '',
                    $periods->get($row->turma_turno_id, 'Não informado'), $row->school_year,
                    $row->attendance_mode?->value ?? 'AGUARDANDO_ESCOLHA', $row->status->value, (int) $row->total];
                // Prevent names in the catalog from being interpreted as spreadsheet formulas.
                $cells = array_map(fn ($cell) => is_string($cell) && preg_match('/^[\s]*[=+@-]/u', $cell)
                    ? "'" . $cell : $cell, $cells);
                fputcsv($stream, $cells, ';', '"', '');
            }
            fclose($stream);
        }, 'matriculas-resumo-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function show(Request $http, RegistrationRequest $registrationRequest, RequestAccess $access)
    {
        $access->authorize($http->user(), $registrationRequest);
        $registrationRequest->load(['student.person', 'guardian.person', 'school', 'grade', 'documents', 'events', 'preregistration', 'schoolClass.period']);

        return view('bc-registration.show', ['request' => $registrationRequest]);
    }

    public function action(Request $http, RegistrationRequest $registrationRequest, RegistrationWorkflow $workflow, RequestAccess $access)
    {
        $access->authorize($http->user(), $registrationRequest);
        $data = $http->validate(['action' => ['required', Rule::in(['approve', 'finalize', 'reject', 'cancel'])],
            'reason' => ['required_if:action,reject,cancel', 'nullable', 'string', 'max:1000']]);
        match ($data['action']) {
            'approve' => $workflow->approve($registrationRequest, $http->user()),
            'finalize' => $workflow->finalize($registrationRequest, $http->user()),
            'reject' => $workflow->close($registrationRequest, $http->user(), false, $data['reason']),
            'cancel' => $workflow->close($registrationRequest, $http->user(), true, $data['reason']),
        };

        return back()->with('status', $data['action'] === 'approve'
            ? ($registrationRequest->fresh()->workflow_version === 2
                ? ($registrationRequest->fresh()->integration_status === 'INTEGRATED' ? 'Aprovação digital concluída. '.$registrationRequest->fresh()->documentationLabel().'.' : 'Aprovação digital mantida. Integração pendente: confira o cadastro e reprocessse a aprovação.')
                : 'Documentação aprovada. A matrícula aguarda efetivação explícita pela escola.')
            : 'Ação registrada com sucesso.');
    }

    public function reviewPhysical(Request $http, RegistrationRequest $registrationRequest)
    {
        app(RequestAccess::class)->authorize($http->user(), $registrationRequest);
        $data = $http->validate([
            'subject' => ['required', 'string', 'max:100'],
            'decision' => ['required', Rule::in(['APPROVED', 'PENDING'])],
            'reason' => ['nullable', 'required_if:decision,PENDING', 'string', 'max:1000'],
        ]);
        app(PhysicalConfirmation::class)->review($registrationRequest, $http->user(),
            $data['subject'], $data['decision'] === 'APPROVED', $data['reason'] ?? null);

        return back()->with('status', 'Conferência física registrada.');
    }

    public function reviewData(Request $http, RegistrationRequest $registrationRequest, RequestAccess $access)
    {
        $access->authorize($http->user(), $registrationRequest);
        $data = $http->validate([
            'decision' => ['required', Rule::in(['APPROVED', 'CORRECTION'])],
            'reason' => ['required_if:decision,CORRECTION', 'nullable', 'string', 'max:1000'],
            'native_student_person_id' => ['nullable', 'integer', 'min:1'],
            'native_guardian_person_id' => ['nullable', 'integer', 'min:1'],
        ]);
        app(DeclaredStudentData::class)->review($registrationRequest, $http->user(),
            $data['decision'] === 'APPROVED', $data['reason'] ?? null, $data);

        return back()->with('status', 'Conferência cadastral registrada.');
    }

    public function receive(Request $http, RegistrationRequest $registrationRequest, RegistrationWorkflow $workflow, RequestAccess $access)
    {
        $access->authorize($http->user(), $registrationRequest);
        $data = $http->validate(['document_type' => ['required', 'string', 'max:100', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'document' => DocumentUploadPolicy::rules(), 'replaces_id' => ['nullable', 'integer']]);
        $previous = !empty($data['replaces_id']) ? $registrationRequest->documents()->findOrFail($data['replaces_id']) : null;
        $path = $http->file('document')->store('registration-documents/' . $registrationRequest->id, 'registration-documents');
        try {
            $file = $http->file('document');
            $workflow->receive($registrationRequest, $http->user(), $data['document_type'], $path, $previous, [
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
            ]);
        } catch (\Throwable $error) {
            Storage::disk('registration-documents')->delete($path);
            throw $error;
        }

        return back()->with('status', 'Documento recebido. O prazo documental foi preservado.');
    }

    public function review(Request $http, RegistrationDocument $document, RegistrationWorkflow $workflow)
    {
        $data = $http->validate([
            'status' => ['required', Rule::enum(DocumentStatus::class)],
            'reason_option' => ['nullable', Rule::in(array_keys(DocumentReviewReasons::OPTIONS))],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $status = DocumentStatus::from($data['status']);
        $reason = in_array($status, [DocumentStatus::Rejected, DocumentStatus::Correction])
            ? DocumentReviewReasons::describe($data['reason_option'] ?? null, $data['reason'] ?? null) : null;
        $workflow->review($document, $http->user(), $status, $reason);

        return back()->with('status', 'Análise registrada.');
    }

    public function receiveInPerson(Request $http, RegistrationRequest $registrationRequest, RegistrationWorkflow $workflow)
    {
        $data = $http->validate([
            'types' => ['required', 'array', 'min:1'],
            'types.*' => ['required', 'string', 'max:100', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $workflow->receiveInPerson($registrationRequest, $http->user(),
            $data['types'], $data['note'] ?? null);

        return back()->with('status', 'Entrega presencial registrada. Pendências permanecem visíveis.');
    }

    public function download(Request $http, RegistrationDocument $document, RequestAccess $access)
    {
        $access->authorize($http->user(), $document->request);
        abort_unless($document->path && Storage::disk('registration-documents')->exists($document->path), 404);

        return Storage::disk('registration-documents')->download($document->path);
    }
}
