<?php

namespace App\Http\Controllers;

use App\EnrollmentRequests\GuardianCommunications;
use App\EnrollmentRequests\GuardianDependents;
use App\EnrollmentRequests\GuardianProfiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GuardianProfileController extends Controller
{
    private function profile(Request $request): object
    {
        $id = $request->session()->get('bc_guardian_profile_id');
        abort_unless(is_int($id), 403);
        $profile = DB::table('bc_guardian_profiles')->where('id', $id)->first();
        abort_unless($profile, 403);

        return $profile;
    }

    public function dependent(Request $request, ?int $dependent = null): View
    {
        $profile = $this->profile($request);
        $item = $dependent ? app(GuardianDependents::class)->owned($profile->id, $dependent) : null;

        return view('bc-registration.dependent', compact('profile', 'item'));
    }

    public function saveDependent(Request $request, ?int $dependent = null): RedirectResponse
    {
        $profile = $this->profile($request);
        if ($dependent) {
            app(GuardianDependents::class)->owned($profile->id, $dependent);
        }
        $data = $request->validate(GuardianDependents::rules() + [
            'reason' => [$dependent ? 'required' : 'nullable', 'string', 'max:1000'],
        ]);
        $reason = $data['reason'] ?? null;
        unset($data['reason']);
        app(GuardianDependents::class)->save($profile->id, $data, $dependent, $reason);

        return redirect()->route('bc-guardian.profile')->with('status', 'Dados do dependente salvos no seu perfil.');
    }

    public function attachApplication(Request $request, int $pmd): RedirectResponse
    {
        $profile = $this->profile($request);
        app(GuardianProfiles::class)->authorize($profile->id, $pmd);
        $data = $request->validate(['dependent_id' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:1000']]);
        app(GuardianDependents::class)->attach($profile->id, $pmd, $data['dependent_id'], $data['reason']);

        return redirect()->route('bc-guardian.profile')->with('status', 'Inscrição organizada no dependente selecionado.');
    }

    public function communications(Request $request): View
    {
        $profile = $this->profile($request);
        $filters = $request->validate(['dependent' => ['nullable', 'integer', 'min:1'], 'pmd' => ['nullable', 'integer', 'min:1'],
            'unread' => ['nullable', 'boolean']]);
        $applications = app(GuardianProfiles::class)->applications($profile->id);
        $missing = DB::table('bc_guardian_profile_applications')->where('profile_id', $profile->id)
            ->whereNull('dependent_id')->pluck('pmd_id');
        foreach ($applications->whereIn('id', $missing) as $pmd) {
            app(GuardianDependents::class)->ensure($profile->id, $pmd->id);
        }
        $dependents = app(GuardianDependents::class)->all($profile->id);
        $notices = app(GuardianCommunications::class)->page($profile->id,
            isset($filters['dependent']) ? (int) $filters['dependent'] : null,
            isset($filters['pmd']) ? (int) $filters['pmd'] : null, (bool) ($filters['unread'] ?? false));

        return view('bc-registration.communications', compact('profile', 'applications', 'dependents', 'notices', 'filters'));
    }

    public function readNotice(Request $request, int $notice): RedirectResponse
    {
        $profile = $this->profile($request);
        app(GuardianCommunications::class)->markRead($profile->id, $notice);

        return back()->with('status', 'Comunicação marcada como lida.');
    }
}
