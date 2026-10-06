@if($request->preregistration)
@php($dataReview = app(\App\EnrollmentRequests\DeclaredStudentData::class)->reviewFor($request->pmd_preregistration_id))
<section class="card"><h2>Dados declarados da pré-matrícula</h2><p>Confira os dados abaixo com os comprovantes. Confirmar a ficha não cria pessoa, aluno ou matrícula.</p>
<table><thead><tr><th>Campo</th><th>Aluno</th><th>Responsável</th></tr></thead><tbody>
@foreach(\App\EnrollmentRequests\DeclaredStudentData::FIELDS as $field => $label)
<tr><td>{{ $label }}</td><td>{{ $field === 'date_of_birth' ? $request->preregistration->student->{$field}?->format('d/m/Y') : ($field === 'gender' ? match((int) $request->preregistration->student->{$field}) { 1 => 'Feminino', 2 => 'Masculino', default => 'Não informado' } : $request->preregistration->student->{$field}) }}</td><td>{{ $field === 'date_of_birth' ? $request->preregistration->responsible->{$field}?->format('d/m/Y') : ($field === 'gender' ? match((int) $request->preregistration->responsible->{$field}) { 1 => 'Feminino', 2 => 'Masculino', default => 'Não informado' } : $request->preregistration->responsible->{$field}) }}</td></tr>
@endforeach
<tr><td>E-mail</td><td>{{ $request->preregistration->student->email }}</td><td>{{ $request->preregistration->responsible->email }}</td></tr>
</tbody></table>
@foreach($request->preregistration->student->addresses as $address)<p><strong>Endereço:</strong> {{ $address->address }}, {{ $address->number }} · {{ $address->complement }} · {{ $address->neighborhood }} · {{ $address->city }} · {{ $address->postal_code }}</p>@endforeach
<p>Situação cadastral: {{ match($dataReview?->status) { 'APPROVED' => 'Dados conferidos', 'CORRECTION' => 'Correção solicitada', default => 'Aguardando conferência' } }} @if($dataReview?->reason)<br>{{ $dataReview->reason }}@endif</p>
@if(in_array($request->preregistration->status, [4, 5]) && !$request->status->terminal() && !in_array($request->status, [\App\EnrollmentRequests\RequestStatus::Approved, \App\EnrollmentRequests\RequestStatus::VacancyConfirmed]))
<form method="post" action="{{ route('bc-registration.data.review', $request) }}">@csrf
<label>Justificativa ou orientação para correção <textarea name="reason" rows="2" maxlength="1000"></textarea></label>
@if(auth()->user()->isAdmin() || auth()->user()->isInstitutional())
<details><summary>Resolver identidade de cadastro existente</summary><p>Após conferir o cadastro nativo, informe o código de pessoa confirmado. Exige justificativa e fica registrado na auditoria. Os dados existentes serão preservados.</p><label>Código de pessoa do aluno no i-Educar <input name="native_student_person_id" type="number" min="1"></label><label>Código de pessoa do responsável no i-Educar <input name="native_guardian_person_id" type="number" min="1"></label></details>
@endif
<div class="bc-review-actions"><button name="decision" value="APPROVED">Dados conferem</button><button class="bc-button-warning" name="decision" value="CORRECTION">Dados divergentes — solicitar correção</button></div></form>
@endif
@if(in_array($request->status, [\App\EnrollmentRequests\RequestStatus::Approved, \App\EnrollmentRequests\RequestStatus::VacancyConfirmed]))
<details><summary>Solicitar correção cadastral antes da efetivação</summary><form method="post" action="{{ route('bc-registration.data.review', $request) }}">@csrf<input type="hidden" name="decision" value="CORRECTION"><p>A aprovação geral será reaberta para nova conferência. Os arquivos e os prazos documentais serão preservados.</p><label>Motivo e orientação para o responsável <textarea name="reason" required maxlength="1000"></textarea></label><button>Reabrir conferência e solicitar correção</button></form></details>
@if(auth()->user()->isAdmin() || auth()->user()->isInstitutional())
<details><summary>Resolver identidade antes da efetivação</summary><form method="post" action="{{ route('bc-registration.data.review', $request) }}">@csrf<input type="hidden" name="decision" value="APPROVED"><p>Use após conferir o cadastro nativo. Esta ação confirma a identidade, preservando os dados oficiais e a aprovação documental.</p><label>Código de pessoa do aluno <input type="number" min="1" name="native_student_person_id"></label><label>Código de pessoa do responsável <input type="number" min="1" name="native_guardian_person_id"></label><label>Justificativa da confirmação <textarea name="reason" required maxlength="1000"></textarea></label><button>Confirmar identidade verificada</button></form></details>
@endif
@endif
<details><summary>Histórico da ficha cadastral</summary>
@foreach(\Illuminate\Support\Facades\DB::table('bc_declared_data_events')->where('pmd_id', $request->pmd_preregistration_id)->orderByDesc('id')->get() as $dataEvent)
<div class="row"><strong>{{ $dataEvent->event }}</strong> · {{ \Illuminate\Support\Carbon::parse($dataEvent->created_at)->format('d/m/Y H:i') }} · {{ $dataEvent->actor_type }} {{ $dataEvent->actor_id }}<p>{{ $dataEvent->reason }}</p>
@foreach(json_decode($dataEvent->changes ?? '{}', true) as $field => $change)
@if(is_array($change))<p>{{ \App\EnrollmentRequests\DeclaredStudentData::FIELDS[$field] ?? $field }}: {{ $change['before'] ?? 'Não informado' }} → {{ $change['after'] ?? 'Não informado' }}</p>@else<p>{{ $field }}: {{ $change }}</p>@endif
@endforeach</div>
@endforeach</details></section>
@endif
