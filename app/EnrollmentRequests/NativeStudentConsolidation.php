<?php

namespace App\EnrollmentRequests;

use App\Models\City;
use App\Models\LegacyIndividual;
use App\Models\LegacyPerson;
use App\Models\LegacyPhone;
use App\Models\PersonHasPlace;
use App\Models\Place;
use App\Models\RegistrationRequest;
use App\User;
use iEducar\Packages\PreMatricula\Models\Person;
use iEducar\Packages\PreMatricula\Models\PersonAddress;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use iEducar\Packages\PreMatricula\Services\Concerns\FindOrCreatePerson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NativeStudentConsolidation
{
    use FindOrCreatePerson {
        getOrCreatePerson as private nativePerson;
    }

    private array $resolved = [];

    public function consolidate(RegistrationRequest $request, User $actor): void
    {
        if (!$request->pmd_preregistration_id || $request->student_id) {
            return;
        }
        app(RequestAccess::class)->authorize($actor, $request);
        if (!in_array($request->status, [RequestStatus::Approved, RequestStatus::VacancyConfirmed]) || !$request->approved_at) {
            throw ValidationException::withMessages(['declared_data' => 'A consolidação exige documentação aprovada e efetivação explícita.']);
        }
        app(DeclaredStudentData::class)->assertApproved($request);
        DB::table('preregistrations')->where('id', $request->pmd_preregistration_id)->lockForUpdate()->firstOrFail();
        $pmd = PreRegistration::query()->with(['student.addresses', 'responsible.addresses'])
            ->findOrFail($request->pmd_preregistration_id);
        $review = app(DeclaredStudentData::class)->reviewFor($pmd->id);
        if ($review?->status === 'APPROVED') {
            foreach (['student_id' => 'native_student_person_id', 'responsible_id' => 'native_guardian_person_id'] as $field => $nativeField) {
                if ($review->{$nativeField}) {
                    $this->resolved[$pmd->{$field}] = LegacyPerson::query()->findOrFail($review->{$nativeField});
                }
            }
        }
        // Resolve identity before native creation; names alone never authorize merging.
        $guardian = $this->getOrCreateResponsiblePerson($pmd);
        $this->resolved[$pmd->responsible_id] = $guardian;
        $person = $this->getOrCreatePerson($pmd);
        $student = $this->getOrCreateStudent($person, $pmd);
        foreach ([[$person, $pmd->student], [$guardian, $pmd->responsible]] as [$native, $declared]) {
            if ($native->wasRecentlyCreated) {
                $native->update(['idpes_cad' => $actor->getKey()]);
                $native->individual->update(['idpes_cad' => $actor->getKey()]);
                $this->savePhone($native, $declared->mobile, LegacyPhone::TYPE_MOBILE);
            }
        }
        $relation = match ($pmd->relation_type_id) {
            PreRegistration::RELATION_MOTHER => 'idpes_mae',
            PreRegistration::RELATION_FATHER => 'idpes_pai',
            default => 'idpes_responsavel',
        };
        $previousGuardian = $person->individual->{$relation};
        if ($previousGuardian && (int) $previousGuardian !== (int) $guardian->getKey()) {
            throw ValidationException::withMessages(['identity' => 'O vínculo de filiação/responsável diverge do cadastro oficial. Confira o cadastro nativo antes de efetivar; o vínculo existente foi preservado.']);
        }
        if (!$previousGuardian) {
            $person->individual->update([$relation => $guardian->getKey()]);
        }
        if ($student->wasRecentlyCreated) {
            $student->update(['ref_usuario_cad' => $actor->getKey()]);
        }
        $pmd->student->update(['external_person_id' => $person->getKey()]);
        $pmd->responsible->update(['external_person_id' => $guardian->getKey()]);
        $pmd->external_person_id = $person->getKey();
        $pmd->saveOrFail();
        $request->update(['student_id' => $student->getKey(), 'guardian_id' => $guardian->getKey()]);
        $request->unsetRelation('student');
        $request->unsetRelation('guardian');
        app(RegistrationWorkflow::class)->event($request, EventType::DataConsolidated, $actor,
            metadata: ['student_id' => $student->getKey(), 'person_id' => $person->getKey(), 'guardian_id' => $guardian->getKey(),
                'guardian_link' => ['field' => $relation, 'before' => $previousGuardian, 'after' => $guardian->getKey()],
                'origin' => $request->workflow_version === 2 ? 'DIGITAL_APPROVAL' : 'EXPLICIT_FINALIZATION']);
    }

    public function getOrCreatePerson(PreRegistration $preregistration)
    {
        $existing = $this->resolve($preregistration->student);
        // An external ID inferred during public intake is not sufficient evidence.
        $preregistration->external_person_id = $existing?->getKey();

        return $this->nativePerson($preregistration);
    }

    private function findPerson($data)
    {
        return $this->resolve($data);
    }

    private function findPersonStudent($data)
    {
        return $this->resolve($data);
    }

    private function resolve(Person $snapshot): ?LegacyPerson
    {
        if (isset($this->resolved[$snapshot->id])) {
            return $this->resolved[$snapshot->id];
        }
        $cpf = preg_replace('/\D/', '', (string) $snapshot->cpf);
        $identity = $cpf ?: Str::slug($snapshot->name) . '|' . $snapshot->date_of_birth?->format('Y-m-d');
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['bc-person:' . $identity]);
        if ($cpf) {
            $matches = LegacyIndividual::query()->whereRaw("lpad(cpf::text, 11, '0') = ?", [$cpf])
                ->with('person')->lockForUpdate()->get();
            if ($matches->count() > 1) {
                $this->uncertain();
            }
            if ($individual = $matches->first()) {
                if (Str::slug($individual->person->nome) !== Str::slug($snapshot->name)
                    || ($snapshot->date_of_birth && $individual->data_nasc
                        && !$individual->data_nasc->isSameDay($snapshot->date_of_birth))) {
                    $this->uncertain();
                }

                return $this->resolved[$snapshot->id] = $individual->person;
            }
        }
        $weak = LegacyIndividual::query()->whereHas('person', fn ($query) => $query->where('slug', Str::slug($snapshot->name, ' ')));
        if ($snapshot->date_of_birth) {
            $weak->whereDate('data_nasc', $snapshot->date_of_birth);
        }
        if ($weak->exists() || $snapshot->external_person_id) {
            $this->uncertain();
        }

        return null;
    }

    private function uncertain(): never
    {
        throw ValidationException::withMessages(['identity' => 'Possível cadastro existente ou divergência de identidade. A escola deve conferir o cadastro nativo antes de efetivar; nenhum cadastro foi sobrescrito.']);
    }

    private function createPhone(LegacyPerson $person, $phone)
    {
        $this->savePhone($person, $phone, LegacyPhone::TYPE_LANDLINE);
    }

    private function savePhone(LegacyPerson $person, ?string $phone, int $type): void
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (!$digits) {
            return;
        }
        if (!in_array(strlen($digits), [10, 11])) {
            throw ValidationException::withMessages(['phone' => 'Confira o telefone declarado, incluindo DDD, antes de efetivar.']);
        }
        LegacyPhone::query()->create(['idpes' => $person->getKey(), 'tipo' => $type,
            'ddd' => substr($digits, 0, 2), 'fone' => substr($digits, 2)]);
    }

    private function createAddress(LegacyPerson $person, ?PersonAddress $address)
    {
        if (!$address) {
            return;
        }
        $cities = City::query()->whereRaw('lower(name) = lower(?)', [$address->city])->get();
        if ($cities->count() !== 1) {
            throw ValidationException::withMessages(['address' => 'Confira o município do endereço declarado; não foi possível identificar uma cidade única.']);
        }
        $place = Place::query()->create([
            'city_id' => $cities->first()->getKey(), 'address' => $address->address,
            'number' => $address->number ?: null, 'complement' => $address->complement,
            'neighborhood' => $address->neighborhood, 'postal_code' => idFederal2int($address->postal_code),
            'latitude' => $address->latitude, 'longitude' => $address->longitude,
        ]);
        PersonHasPlace::query()->create(['person_id' => $person->getKey(), 'type' => 1, 'place_id' => $place->getKey()]);
    }
}
