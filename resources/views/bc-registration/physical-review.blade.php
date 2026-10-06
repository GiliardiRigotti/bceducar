<section class="card">
<h2>Conferência física</h2>
<p>{{ $request->documentationLabel() }}</p>
@if($request->physical_deadline?->lt(now()) && !$request->physical_confirmed_at)
<p role="status">O prazo inicial de apresentação terminou. Confira os prazos de regularização abaixo ou procure a escola. A solicitação não foi cancelada automaticamente.</p>
@endif
@if($request->physical_deadline)<p>Prazo para apresentação: <strong>{{ $request->physical_deadline->format('d/m/Y H:i') }}</strong></p>@endif
@if($request->integration_status === 'ERROR')
@php
$integrationFailure = $request->events->where('event', \App\EnrollmentRequests\EventType::IntegrationFailed)->sortByDesc('id')->first();
@endphp
<p role="alert">A aprovação digital foi preservada. A integração não foi concluída. Confira os vínculos cadastrais e use Reprocessar integração. Nenhuma matrícula definitiva foi criada.</p>
<p>{{ ($integrationFailure?->metadata['error_code'] ?? null) === 'IDENTITY_REVIEW_REQUIRED' ? 'Identidade pendente: utilize a conferência cadastral e confirme os vínculos nativos autorizados.' : 'Confira a configuração do processo, a unidade e os cadastros nativos.' }}</p>
@endif
@if($request->intermediate_registration_id && !$request->status->terminal())
@php
$physicalReviews = \Illuminate\Support\Facades\DB::table('bc_physical_reviews')->where('registration_request_id', $request->id)->get()->keyBy('subject');
$physicalSubjects = $request->documents->filter(fn ($doc) => $doc->status === \App\EnrollmentRequests\DocumentStatus::Approved)->mapWithKeys(fn ($doc) => ['document:'.$doc->id => $doc->documentName()])->put('cadastro', 'Ficha cadastral e identificação');
@endphp
<p>Pré-matrícula integrada ao i-Educar — aguardando conferência física. Vínculo nativo #{{ $request->intermediate_registration_id }}, situação Pré-matrícula (11).</p>
@foreach($physicalSubjects as $subject => $label)
@php
$physicalReview = $physicalReviews->get($subject);
@endphp
<div class="row"><h3>{{ $label }}</h3><p>{{ $physicalReview?->status === 'APPROVED' ? 'Conferido' : ($physicalReview ? 'Regularização pendente' : 'Aguardando apresentação') }}</p>
@if($physicalReview?->reason)<p>{{ $physicalReview->reason }}</p>@endif
@if($physicalReview?->deadline)<p>Regularizar até {{ \Illuminate\Support\Carbon::parse($physicalReview->deadline)->format('d/m/Y H:i') }}</p>@endif
<form class="bc-review-form" method="post" action="{{ route('bc-registration.physical.review', $request) }}">@csrf
<input type="hidden" name="subject" value="{{ $subject }}">
<label>Motivo da divergência e orientação para regularização <textarea name="reason" maxlength="1000" rows="2"></textarea></label>
<div class="bc-review-actions"><button name="decision" value="APPROVED">Original apresentado e conferido</button><button class="bc-button-warning" name="decision" value="PENDING">Registrar divergência</button></div>
</form></div>
@endforeach
@if(in_array($request->status, [\App\EnrollmentRequests\RequestStatus::Approved, \App\EnrollmentRequests\RequestStatus::VacancyConfirmed]))
<form method="post" action="{{ route('bc-registration.action', $request) }}">@csrf
<button name="action" value="finalize">Confirmar documentação física e matrícula</button>
</form>
@endif
@endif
</section>
