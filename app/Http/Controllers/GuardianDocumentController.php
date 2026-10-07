<?php

namespace App\Http\Controllers;

use App\EnrollmentRequests\AttendanceMode;
use App\EnrollmentRequests\DeclaredStudentData;
use App\EnrollmentRequests\DocumentUploadPolicy;
use App\EnrollmentRequests\GuardianCommunications;
use App\EnrollmentRequests\GuardianDependents;
use App\EnrollmentRequests\GuardianProfiles;
use App\EnrollmentRequests\PmdGuardianDocuments;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\Mail\GuardianAccessCode;
use App\Models\RegistrationDocument;
use App\Models\RegistrationRequest;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GuardianDocumentController extends Controller
{
    private function pmdId(Request $request): int
    {
        $pmdId = $request->session()->get('bc_guardian_pmd_id');
        abort_unless(is_int($pmdId), 403, 'Confirme seu acesso antes de abrir os documentos.');

        if ($profileId = $request->session()->get('bc_guardian_profile_id')) {
            app(GuardianProfiles::class)->authorize((int) $profileId, $pmdId);
        }

        return $pmdId;
    }

    private function application(Request $request): RegistrationRequest
    {
        return RegistrationRequest::query()->where('pmd_preregistration_id', $this->pmdId($request))
            ->with(['student.person', 'school', 'documents', 'events'])->firstOrFail();
    }

    public function show(Request $request): View
    {
        $pmd = $request->session()->has('bc_guardian_pmd_id')
            ? PreRegistration::query()->with(['student', 'school', 'process'])->findOrFail($this->pmdId($request)) : null;
        $application = $pmd ? RegistrationRequest::query()->where('pmd_preregistration_id', $pmd->id)
            ->with(['student.person', 'school', 'documents', 'events'])->first() : null;
        $types = $pmd && !$application ? DB::table('process_document_types as policy')
            ->join('preregistration_document_types as type', 'type.id', '=', 'policy.document_type_id')
            ->where('policy.process_id', $pmd->process_id)->where('type.active', true)
            ->whereNotNull('type.code')->orderBy('type.name')
            ->get(['type.id', 'type.code', 'type.name', 'policy.required']) : collect();
        $documents = $pmd && !$application ? DB::table('preregistration_documents')
            ->where('preregistration_id', $pmd->id)->orderBy('id')->get() : collect();
        $events = $pmd && !$application ? DB::table('preregistration_document_events')
            ->where('preregistration_id', $pmd->id)->orderBy('id')->get() : collect();

        $documentationOpen = $pmd && in_array($pmd->status, [
            PreRegistration::STATUS_SUMMONED, PreRegistration::STATUS_IN_CONFIRMATION, PreRegistration::STATUS_ACCEPTED,
        ]) && ($application || !$pmd->isWaitingList())
            && ($application || !in_array($pmd->process->document_workflow_enabled, [false, 0, '0'], true) || $pmd->documentation_status);

        return view('bc-registration.guardian', compact('pmd', 'application', 'types', 'documents', 'events', 'documentationOpen'));
    }

    public function requestCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['protocol' => ['nullable', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255']]);
        $key = 'bc-guardian-start:' . hash('sha256', $request->ip() . '|' . Str::lower($data['protocol'] ?? $data['email']));
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['protocol' => 'Aguarde alguns minutos antes de tentar novamente.']);
        }
        RateLimiter::hit($key, 600);
        $email = Str::lower(trim($data['email']));
        $profile = DB::table('bc_guardian_profiles')->where('email', $email)->first();
        $protocol = trim($data['protocol'] ?? '');
        $pmd = $protocol !== '' ? PreRegistration::query()->with('responsible')
            ->where('preregistrations.protocol', $protocol)->first()
            : ($profile ? app(GuardianProfiles::class)->applications($profile->id)->first() : null);
        if ($pmd && $pmd->responsible && (($protocol === '' && $profile)
            || Str::lower(trim((string) $pmd->responsible->email)) === $email)) {
            $code = (string) random_int(100000, 999999);
            $challenge = (string) Str::uuid();
            Cache::put('bc-guardian:' . $challenge, [
                'pmd_id' => $pmd->id, 'email' => $email,
                'profile_id' => $protocol === '' ? $profile->id : null, 'hash' => hash_hmac('sha256', $code, config('app.key')),
            ], now()->addMinutes(10));
            $request->session()->put('bc_guardian_challenge', $challenge);
            try {
                Mail::to($email)->send(new GuardianAccessCode($code, $pmd->protocol));
            } catch (\Throwable $error) {
                Cache::forget('bc-guardian:' . $challenge);
                $request->session()->forget('bc_guardian_challenge');
                Log::warning('Falha ao enviar código de acesso documental', ['exception' => $error::class]);
            }
        }

        return back()->with('status', 'Se o protocolo e o e-mail corresponderem à inscrição, enviaremos um código de acesso.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $challenge = $request->session()->get('bc_guardian_challenge');
        abort_unless(is_string($challenge) && Str::isUuid($challenge), 403);
        $key = 'bc-guardian-verify:' . $challenge;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Código inválido ou expirado. Solicite outro código.']);
        }
        RateLimiter::hit($key, 600);
        $stored = Cache::get('bc-guardian:' . $challenge);
        if (!$stored || !hash_equals($stored['hash'], hash_hmac('sha256', $data['code'], config('app.key')))) {
            return back()->withErrors(['code' => 'Código inválido ou expirado.']);
        }
        Cache::forget('bc-guardian:' . $challenge);
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->forget('bc_guardian_challenge');
        $pmd = PreRegistration::query()->with('responsible')->findOrFail($stored['pmd_id']);
        if (!empty($stored['profile_id'])) {
            app(GuardianProfiles::class)->authorize((int) $stored['profile_id'], (int) $stored['pmd_id']);
            $profileId = (int) $stored['profile_id'];
            app(GuardianProfiles::class)->audit($profileId, 'AUTHENTICATED', (int) $stored['pmd_id']);
        } else {
            abort_if(isset($stored['email']) && Str::lower(trim((string) $pmd->responsible->email)) !== $stored['email'], 403);
            $profileId = app(GuardianProfiles::class)->claim((int) $stored['pmd_id']);
        }
        $request->session()->put('bc_guardian_profile_id', $profileId);
        $request->session()->put('bc_guardian_pmd_id', (int) $stored['pmd_id']);

        return redirect()->route('bc-guardian.show');
    }

    public function profile(Request $request): View
    {
        $profileId = $request->session()->get('bc_guardian_profile_id');
        abort_unless(is_int($profileId), 403);
        $profile = DB::table('bc_guardian_profiles')->where('id', $profileId)->first();
        abort_unless($profile, 403);
        $applications = app(GuardianProfiles::class)->applications($profileId);
        $requests = RegistrationRequest::query()->whereIn('pmd_preregistration_id', $applications->pluck('id'))
            ->get()->keyBy('pmd_preregistration_id');

        $bindings = DB::table('bc_guardian_profile_applications')->where('profile_id', $profileId)->get()->keyBy('pmd_id');
        foreach ($applications as $pmd) {
            if (!$bindings->get($pmd->id)?->dependent_id) {
                app(GuardianDependents::class)->ensure($profileId, $pmd->id);
            }
        }
        $dependents = app(GuardianDependents::class)->all($profileId);
        $bindings = DB::table('bc_guardian_profile_applications')->where('profile_id', $profileId)->get()->keyBy('pmd_id');
        $unreadCount = app(GuardianCommunications::class)->unreadCount($profileId);

        return view('bc-registration.profile', compact('profile', 'applications', 'requests', 'dependents', 'bindings', 'unreadCount'));
    }

    public function selectApplication(Request $request, int $pmd): RedirectResponse
    {
        $profileId = $request->session()->get('bc_guardian_profile_id');
        abort_unless(is_int($profileId), 403);
        app(GuardianProfiles::class)->authorize($profileId, $pmd);
        $request->session()->put('bc_guardian_pmd_id', $pmd);

        return redirect()->route('bc-guardian.show');
    }

    public function data(Request $request): View
    {
        $profileId = $request->session()->get('bc_guardian_profile_id');
        abort_unless(is_int($profileId), 403);
        $pmdId = $this->pmdId($request);
        app(GuardianProfiles::class)->authorize($profileId, $pmdId);
        $pmd = PreRegistration::query()->with(['student.addresses', 'responsible.addresses'])->findOrFail($pmdId);
        $review = app(DeclaredStudentData::class)->reviewFor($pmdId);

        return view('bc-registration.declared-data', compact('pmd', 'review'));
    }

    public function updateData(Request $request): RedirectResponse
    {
        $profileId = $request->session()->get('bc_guardian_profile_id');
        abort_unless(is_int($profileId), 403);
        $pmdId = $this->pmdId($request);
        $data = $request->validate([
            'student' => ['required', 'array:name,cpf,date_of_birth,gender,rg,birth_certificate,phone,mobile'],
            'student.name' => ['required', 'string', 'max:255'],
            'student.cpf' => ['nullable', 'regex:/^(?:[0-9]{11}|[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2})$/'],
            'student.date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'student.gender' => ['nullable', 'integer', Rule::in([1, 2])],
            'student.rg' => ['nullable', 'string', 'max:50'],
            'student.birth_certificate' => ['nullable', 'string', 'max:255'],
            'student.phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9()+ \-]+$/'],
            'student.mobile' => ['nullable', 'string', 'max:30', 'regex:/^[0-9()+ \-]+$/'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        app(DeclaredStudentData::class)->update($profileId, $pmdId, $data['student'], $data['reason']);

        return back()->with('status', 'Dados declarados atualizados e enviados para conferência da escola.');
    }

    public function demo(Request $request): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);
        $examples = RegistrationRequest::query()->where('seed_source', BalnearioCamboriuDemoSeeder::SOURCE)
            ->whereNotNull('pmd_preregistration_id')->whereHas('preregistration');
        $application = (clone $examples)->where('document_deadline', '>=', now())
            ->where('status', 'AGUARDANDO_DOCUMENTOS')->orderBy('id')->first()
            ?? (clone $examples)->orderByDesc('document_deadline')->orderBy('id')->first();
        if (!$application) {
            return redirect()->route('bc-guardian.show')->withErrors([
                'demo' => 'Não há inscrição fictícia disponível neste ambiente. Prepare a massa de demonstração local.',
            ]);
        }
        $profileId = app(GuardianProfiles::class)->claim((int) $application->pmd_preregistration_id);
        $request->session()->regenerate();
        $request->session()->forget('bc_guardian_challenge');
        $request->session()->put('bc_guardian_profile_id', $profileId);
        $request->session()->put('bc_guardian_pmd_id', (int) $application->pmd_preregistration_id);

        return redirect()->route('bc-guardian.show');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['bc_guardian_pmd_id', 'bc_guardian_profile_id', 'bc_guardian_challenge']);
        $request->session()->regenerate();

        return redirect()->route('bc-guardian.show');
    }

    public function mode(Request $request, RegistrationWorkflow $workflow, PmdGuardianDocuments $early): RedirectResponse
    {
        $pmdId = $this->pmdId($request);
        $application = RegistrationRequest::query()->where('pmd_preregistration_id', $pmdId)->first();
        $data = $request->validate(['attendance_mode' => ['required', Rule::enum(AttendanceMode::class)]]);
        $mode = AttendanceMode::from($data['attendance_mode']);
        if ($application) {
            $workflow->changeGuardianMode($application, $pmdId, $mode);
        } else {
            $early->choose($pmdId, $mode);
        }

        return back()->with('status', 'Forma de entrega atualizada. O prazo continua o mesmo.');
    }

    public function upload(Request $request, RegistrationWorkflow $workflow, PmdGuardianDocuments $early): RedirectResponse
    {
        $pmdId = $this->pmdId($request);
        $application = RegistrationRequest::query()->where('pmd_preregistration_id', $pmdId)->first();
        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:100', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'document' => DocumentUploadPolicy::rules(),
            'replaces_id' => ['nullable', 'integer'],
        ]);
        $previous = $application && !empty($data['replaces_id']) ? $application->documents()->findOrFail($data['replaces_id']) : null;
        $file = $request->file('document');
        $path = $file->store($application ? 'registration-documents/' . $application->id : 'preregistration-documents/' . $pmdId,
            'registration-documents');
        try {
            $metadata = ['original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath())];
            if ($application) {
                $workflow->receiveFromGuardian($application, $pmdId,
                    $data['document_type'], $path, $previous, $metadata);
            } else {
                $early->upload($pmdId, $data['document_type'], $path, $metadata);
            }
        } catch (\Throwable $error) {
            Storage::disk('registration-documents')->delete($path);
            throw $error;
        }

        return back()->with('status', 'Documento enviado para análise da escola.');
    }

    public function downloadEarly(Request $request, int $document)
    {
        $file = DB::table('preregistration_documents')->where('id', $document)
            ->where('preregistration_id', $this->pmdId($request))->first();
        abort_unless($file && Storage::disk('registration-documents')->exists($file->file_path), 404);

        return Storage::disk('registration-documents')->download($file->file_path);
    }

    public function download(Request $request, RegistrationDocument $document)
    {
        $application = $this->application($request);
        abort_unless($document->registration_request_id === $application->id, 403);
        abort_unless($document->path && Storage::disk('registration-documents')->exists($document->path), 404);

        return Storage::disk('registration-documents')->download($document->path);
    }
}
