<?php

namespace App\EnrollmentRequests;

use App\Rules\Cpf;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GuardianDependents
{
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', new Cpf],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'gender' => ['nullable', 'integer', Rule::in([1, 2])],
            'rg' => ['nullable', 'string', 'max:50'],
            'birth_certificate' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9()+ \-]+$/'],
            'mobile' => ['nullable', 'string', 'max:30', 'regex:/^[0-9()+ \-]+$/'],
        ];
    }

    public function all(int $profileId)
    {
        return DB::table('bc_guardian_dependents')->where('profile_id', $profileId)->orderBy('name')->orderBy('id')->get();
    }

    public function owned(int $profileId, int $id): object
    {
        $dependent = DB::table('bc_guardian_dependents')->where('profile_id', $profileId)->where('id', $id)->first();
        abort_unless($dependent, 403);

        return $dependent;
    }

    public function save(int $profileId, array $data, ?int $id = null, ?string $reason = null): int
    {
        $data = Validator::make($data, self::rules())->validate();

        return DB::transaction(function () use ($profileId, $data, $id, $reason) {
            DB::table('bc_guardian_profiles')->where('id', $profileId)->lockForUpdate()->firstOrFail();
            $before = $id ? $this->owned($profileId, $id) : null;
            $changes = [];
            foreach ($data as $field => $value) {
                if ((string) ($before?->{$field} ?? '') !== (string) ($value ?? '')) {
                    $changes[$field] = ['before' => $before?->{$field}, 'after' => $value];
                }
            }
            if (!$before) {
                $id = DB::table('bc_guardian_dependents')->insertGetId($data + [
                    'profile_id' => $profileId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } elseif ($changes) {
                DB::table('bc_guardian_dependents')->where('id', $id)->update($data + ['updated_at' => now()]);
            } else {
                return $id;
            }
            $this->audit($profileId, $id, $before ? 'DEPENDENT_UPDATED' : 'DEPENDENT_CREATED', $changes, reason: $reason);

            return $id;
        });
    }

    public function ensure(int $profileId, int $pmdId): int
    {
        return DB::transaction(function () use ($profileId, $pmdId) {
            DB::table('bc_guardian_profiles')->where('id', $profileId)->lockForUpdate()->firstOrFail();
            app(GuardianProfiles::class)->authorize($profileId, $pmdId);
            $link = DB::table('bc_guardian_profile_applications')->where('profile_id', $profileId)->where('pmd_id', $pmdId)->firstOrFail();
            if ($link->dependent_id) {
                return (int) $link->dependent_id;
            }
            $pmd = PreRegistration::query()->with('student')->findOrFail($pmdId);
            // Reuse only a verified application sharing the same PMD snapshot, never CPF/name matching.
            $id = DB::table('bc_guardian_profile_applications as link')->join('preregistrations as pmd', 'pmd.id', '=', 'link.pmd_id')
                ->where('link.profile_id', $profileId)->where('pmd.student_id', $pmd->student_id)
                ->whereNotNull('link.dependent_id')->value('link.dependent_id');
            if (!$id) {
                $data = [];
                foreach (DeclaredStudentData::FIELDS as $field => $label) {
                    $data[$field] = $field === 'date_of_birth' ? $pmd->student->date_of_birth?->format('Y-m-d') : $pmd->student->{$field};
                }
                // Historical declared data can be incomplete; preserve it rather than imposing new intake rules.
                $id = DB::table('bc_guardian_dependents')->insertGetId($data + [
                    'profile_id' => $profileId, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->audit($profileId, $id, 'DEPENDENT_FROM_VERIFIED_APPLICATION', [], $pmdId);
            }
            DB::table('bc_guardian_profile_applications')->where('id', $link->id)->update(['dependent_id' => $id]);

            return (int) $id;
        });
    }

    public function attach(int $profileId, int $pmdId, int $id, string $reason): void
    {
        DB::transaction(function () use ($profileId, $pmdId, $id, $reason) {
            DB::table('bc_guardian_profiles')->where('id', $profileId)->lockForUpdate()->firstOrFail();
            app(GuardianProfiles::class)->authorize($profileId, $pmdId);
            $this->owned($profileId, $id);
            $link = DB::table('bc_guardian_profile_applications')->where('profile_id', $profileId)->where('pmd_id', $pmdId)->firstOrFail();
            if ((int) $link->dependent_id === $id) {
                return;
            }
            DB::table('bc_guardian_profile_applications')->where('id', $link->id)->update(['dependent_id' => $id]);
            $this->audit($profileId, $id, 'VERIFIED_APPLICATION_ORGANIZED', ['dependent_id' => [
                'before' => $link->dependent_id, 'after' => $id,
            ]], $pmdId, $reason);
        });
    }

    private function audit(int $profileId, int $id, string $event, array $changes, ?int $pmdId = null, ?string $reason = null): void
    {
        DB::table('bc_guardian_dependent_events')->insert([
            'profile_id' => $profileId, 'dependent_id' => $id, 'pmd_id' => $pmdId,
            'event' => $event, 'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'reason' => $reason, 'created_at' => now(),
        ]);
    }
}
