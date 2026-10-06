@php
    $enrolled = $application?->status === \App\EnrollmentRequests\RequestStatus::Registered && $application?->registration_id;
    $documentApproved = $application && in_array($application->status, [
        \App\EnrollmentRequests\RequestStatus::Approved, \App\EnrollmentRequests\RequestStatus::VacancyConfirmed,
        \App\EnrollmentRequests\RequestStatus::Registered,
    ]) && $application->approved_at;
@endphp
<div class="guardian-progress" aria-label="Pré-matrícula → Documentação → Matrícula">
    <div class="guardian-step {{ $documentationOpen ? 'done' : 'active' }}" data-stage="preregistration">
        Pré-matrícula<br><small>{{ $documentationOpen ? 'Pré-matrícula deferida' : ($pmd ? 'Pré-matrícula em análise' : 'Consulte sua inscrição') }}</small>
    </div>
    <div class="guardian-step {{ $documentApproved ? 'done' : ($documentationOpen ? 'active' : '') }}" data-stage="documentation">
        Documentação<br><small>{{ $documentApproved ? 'Documentação aprovada' : ($documentationOpen ? 'Entrega e análise documental' : 'Disponível após o deferimento') }}</small>
    </div>
    @if($application?->workflow_version === 2)
    <div class="guardian-step {{ $enrolled ? 'done' : ($application->intermediate_registration_id ? 'active' : '') }}" data-stage="physical">
        Conferência física<br><small>{{ $enrolled ? 'Documentação física confirmada' : ($application->intermediate_registration_id ? 'Matrícula em confirmação' : 'Após aprovação e integração') }}</small>
    </div>
    @endif
    <div class="guardian-step {{ $enrolled ? 'done' : ($documentApproved ? 'active' : '') }}" data-stage="enrollment">
        Matrícula<br><small>{{ $enrolled ? 'Matrícula efetivada' : ($documentApproved ? ($application?->workflow_version === 2 ? 'Conferência física e confirmação' : 'Aguardando efetivação pela unidade escolar') : 'Aguardando etapas anteriores') }}</small>
    </div>
</div>
