@extends('bc-registration.layout')
@section('content')
<a href="{{ route('bc-registration.index') }}">← Triagem Documental</a><h1>{{ $request->protocol }} · {{ $request->studentName() }}</h1>
<div class="card"><p><strong>{{ $request->school->name }}</strong> · {{ $request->grade->name }} · {{ $request->school_year }}</p><p>Responsável: {{ $request->guardianName() }}</p><p>{{ $request->attendance_mode?->value ?? 'Aguardando escolha da modalidade' }} · {{ $request->documentationLabel() }} · {{ $request->kind }}</p><p>Prazo documental: <strong>{{ $request->document_deadline->format('d/m/Y H:i') }}</strong></p>@if($request->registration_id)<p>Matrícula nativa: {{ $request->registration_id }}</p>@endif</div>
<div class="card"><h2>Dados da pré-matrícula</h2>
<p>PRÉ-MATRÍCULA: <strong>{{ !$request->preregistration || in_array($request->preregistration->status, [4, 5, 2]) ? 'DEFERIDA' : 'NÃO DEFERIDA / ENCERRADA' }}</strong></p>
<p>Protocolo: {{ $request->source_reference ?? $request->protocol }} · Data: {{ $request->preregistration?->created_at?->format('d/m/Y H:i') ?? $request->created_at->format('d/m/Y H:i') }}</p>
<p>Série: {{ $request->grade->name }} · Turno: {{ $request->schoolClass?->period?->name ?? 'Não informado' }}</p>
</div>
@include('bc-registration.declared-review')
<h2>Etapa documental</h2>
<p>MODALIDADE: <strong>{{ $request->attendance_mode?->value ?? 'Aguardando escolha do responsável' }}</strong></p>

<h2>Documentos e versões</h2><div class="card bc-documents"><table><thead><tr><th>Tipo</th><th>Situação</th><th>Motivo</th><th>Análise</th></tr></thead><tbody>@foreach($request->documents as $document)<tr><td>{{ $document->documentName() }} @if($document->required)<small>(obrigatório)</small>@endif @if($document->path)<br><a href="{{ route('bc-registration.download', $document) }}">Baixar arquivo</a>@endif @if($document->replaces_id)<br><small>Substitui documento #{{ $document->replaces_id }}</small>@endif</td><td>{{ $document->status->value }}</td><td>{{ $document->reason }}@if($document->delivery_deadline)<p><small>Prazo deste documento: {{ $document->delivery_deadline->format('d/m/Y H:i') }}</small></p>@endif</td><td>@if(!$request->status->terminal() && !in_array($request->status, [\App\EnrollmentRequests\RequestStatus::Approved, \App\EnrollmentRequests\RequestStatus::VacancyConfirmed]) && $document->received_at && \App\EnrollmentRequests\DocumentDeadlines::timely($document, $request) && $request->attendance_mode && (!$request->preregistration || in_array($request->preregistration->status, [4, 5])) && in_array($document->status, [\App\EnrollmentRequests\DocumentStatus::Sent, \App\EnrollmentRequests\DocumentStatus::UnderReview]))<form class="bc-review-form" action="{{ route('bc-registration.review', $document) }}" method="post">@csrf<label>Motivo da recusa ou correção <select name="reason_option"><option value="">Selecione um motivo</option>@foreach(\App\EnrollmentRequests\DocumentReviewReasons::OPTIONS as $code => $label)<option value="{{ $code }}">{{ explode('.', $label)[0] }}</option>@endforeach</select></label><label>Detalhes para o responsável <textarea name="reason" rows="2" maxlength="1000" placeholder="Informe o que precisa ser corrigido. Obrigatório para Outro motivo."></textarea></label><small>Recusar este documento não indefere a inscrição. O responsável poderá substituí-lo no prazo de reentrega definido nos ajustes.</small><div class="bc-review-actions"><button name="status" value="APROVADO">Aprovar documento</button><button class="bc-button-secondary" name="status" value="EM_ANALISE">Em análise</button><button class="bc-button-warning" name="status" value="SOLICITAR_CORRECAO">Solicitar correção</button><button class="bc-button-danger" name="status" value="REJEITADO">Rejeitar documento</button></div></form>@elseif($document->status === \App\EnrollmentRequests\DocumentStatus::Pending)<small>Aguardando entrega do documento.</small>@else<small>{{ $document->status === \App\EnrollmentRequests\DocumentStatus::Approved ? 'Documento aprovado.' : 'Sem análise disponível neste estado.' }}</small>@endif</td></tr>@endforeach</tbody></table></div>
@if(!$request->status->terminal() && $request->attendance_mode && (!$request->preregistration || in_array($request->preregistration->status, [4, 5])))
@if($request->attendance_mode === \App\EnrollmentRequests\AttendanceMode::InPerson)
<div class="card"><h2>Registrar entrega presencial</h2><p>Marque os documentos apresentados. Os demais continuarão pendentes.</p>
<form action="{{ route('bc-registration.receive-in-person', $request) }}" method="post">@csrf
@foreach($request->documents as $document)
@if(in_array($document->status, [\App\EnrollmentRequests\DocumentStatus::Pending, \App\EnrollmentRequests\DocumentStatus::Rejected, \App\EnrollmentRequests\DocumentStatus::Correction]))
<label><input type="checkbox" name="types[]" value="{{ $document->document_type->value }}">{{ $document->documentName() }}</label>
@endif
@endforeach
<label>Observações <input name="note" maxlength="1000" aria-label="Observações da entrega"></label><button>Registrar entrega</button></form></div>
@endif
<div class="card"><h2>Receber ou substituir documento</h2><form action="{{ route('bc-registration.receive', $request) }}" method="post" enctype="multipart/form-data">@csrf<label>Tipo<select name="document_type">@foreach($request->documents->where("status", "!=", \App\EnrollmentRequests\DocumentStatus::Replaced)->unique(fn ($item) => $item->document_type->value) as $type)<option value="{{ $type->document_type->value }}">{{ $type->documentName() }}</option>@endforeach</select></label><label>Versão anterior<select name="replaces_id"><option value="">Primeiro recebimento</option>@foreach($request->documents as $document)@if(in_array($document->status, [\App\EnrollmentRequests\DocumentStatus::Rejected, \App\EnrollmentRequests\DocumentStatus::Correction]))<option value="{{ $document->id }}">#{{ $document->id }} {{ $document->document_type->value }}</option>@endif @endforeach</select></label><input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required aria-label="Arquivo"><button>Registrar recebimento</button></form><small>Até {{ \App\EnrollmentRequests\DocumentUploadPolicy::label() }}. O atendimento presencial mantém o mesmo prazo documental.</small></div>

@endif
@if(!$request->status->terminal())
<section class="card bc-decision"><h2>{{ $request->workflow_version === 2 ? 'Aprovação digital da documentação' : 'Aprovação da documentação e matrícula' }}</h2>
@if($request->workflow_version === 2)
<p>A aprovação digital integra a pré-matrícula ao i-Educar sem enturmar. Confira os originais e a ficha cadastral abaixo para concluir a matrícula.</p>
@else
<p>Primeiro aprove os documentos obrigatórios acima. A aprovação documental não cria matrícula. Após a aprovação, use Efetivar matrícula para matricular e enturmar o aluno no i-Educar.</p>
@endif
@if(!$request->attendance_mode)<p class="bc-alert bc-alert-info">Aguardando a escolha da forma de entrega pelo responsável. A aprovação ficará disponível após essa escolha e o deferimento da pré-matrícula.</p>@endif
<form action="{{ route('bc-registration.action', $request) }}" method="post">@csrf
<label>Motivo da recusa <input name="reason" maxlength="1000" placeholder="Obrigatório para recusar ou cancelar"></label>
<div class="bc-review-actions">
<button name="action" value="approve" @disabled(!$request->attendance_mode || ($request->preregistration && !in_array($request->preregistration->status, [4, 5])))>{{ $request->workflow_version === 2 && $request->integration_status === 'ERROR' ? 'Reprocessar integração' : 'Aprovar documentação' }}</button>
@if($request->workflow_version !== 2 && in_array($request->status, [\App\EnrollmentRequests\RequestStatus::Approved, \App\EnrollmentRequests\RequestStatus::VacancyConfirmed]))
<button name="action" value="finalize">{{ $request->workflow_version === 2 ? 'Confirmar documentação física e matrícula' : 'Efetivar matrícula' }}</button>
@endif
<button class="bc-button-danger" name="action" value="reject">Recusar documentação</button>
<button class="bc-button-secondary" name="action" value="cancel">Cancelar solicitação</button>
</div></form></section>
@if($request->workflow_version === 2)
@include('bc-registration.physical-review')
@endif
@endif
<h2>Histórico de auditoria</h2><div class="card"><table><thead><tr><th>Data</th><th>Evento</th><th>Autor</th><th>Documento</th></tr></thead><tbody>@foreach($request->events as $event)<tr><td>{{ $event->created_at->format('d/m/Y H:i') }}</td><td>{{ $event->event->value }}</td><td>{{ $event->actor_type }} {{ $event->actor_id }}</td><td>{{ $event->document_id }}</td></tr>@endforeach</tbody></table></div>
@if(in_array($request->status, [\App\EnrollmentRequests\RequestStatus::Expired, \App\EnrollmentRequests\RequestStatus::NoShow]))
<div class="card"><h2>Decisão após o prazo</h2><p>O prazo documental terminou. O vencimento, por si só, não indefere a pré-matrícula. Registre a decisão da escola e seu motivo.</p>
<form action="{{ route('bc-registration.action', $request) }}" method="post">@csrf
<select name="action" aria-label="Decisão após o prazo"><option value="reject">Indeferir</option><option value="cancel">Cancelar</option></select>
<input name="reason" required maxlength="1000" placeholder="Motivo da decisão" aria-label="Motivo da decisão após o prazo"><button>Registrar decisão</button></form></div>
@endif
@endsection
