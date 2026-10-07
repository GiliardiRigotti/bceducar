<?php

namespace App\EnrollmentRequests;

use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuardianProfiles
{
    public function claim(int $pmdId): int
    {
        return DB::transaction(function () use ($pmdId) {
            $pmd = PreRegistration::query()->with('responsible')->findOrFail($pmdId);
            $email = Str::lower(trim((string) $pmd->responsible->email));
            abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL), 403);
            $profileCreated = DB::table('bc_guardian_profiles')->insertOrIgnore([
                'email' => $email, 'name' => $pmd->responsible->name,
                'verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $profile = DB::table('bc_guardian_profiles')->where('email', $email)->lockForUpdate()->first();
            if ($profileCreated) {
                $this->audit($profile->id, 'PROFILE_CREATED');
            }
            $created = DB::table('bc_guardian_profile_applications')->insertOrIgnore([
                'profile_id' => $profile->id, 'pmd_id' => $pmdId, 'verified_at' => now(),
            ]);
            app(GuardianDependents::class)->ensure((int) $profile->id, $pmdId);
            $this->audit($profile->id, $created ? 'APPLICATION_LINK_VERIFIED' : 'AUTHENTICATED', $pmdId);

            return (int) $profile->id;
        });
    }

    public function applications(int $profileId)
    {
        return PreRegistration::query()->with(['student', 'school', 'process'])
            ->whereIn('preregistrations.id', DB::table('bc_guardian_profile_applications')
                ->where('profile_id', $profileId)->select('pmd_id'))
            ->orderByDesc('preregistrations.id')->get();
    }

    public function authorize(int $profileId, int $pmdId): void
    {
        abort_unless(DB::table('bc_guardian_profile_applications')
            ->where('profile_id', $profileId)->where('pmd_id', $pmdId)->exists(), 403);
    }

    public function audit(int $profileId, string $event, ?int $pmdId = null): void
    {
        DB::table('bc_guardian_profile_events')->insert([
            'profile_id' => $profileId, 'event' => $event, 'pmd_id' => $pmdId, 'created_at' => now(),
        ]);
    }
}
