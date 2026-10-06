<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><link rel="icon" href="{{ asset('favicon.ico') }}?v=bc-20261005"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Matrícula Digital · Acompanhamento</title><link rel="stylesheet" href="{{ asset('vendor/bc-registration/workflow.css') . '?v=20261005-8' }}"></head>
<body class="bc-guardian"><header class="bc-guardian-header"><div class="bc-header-inner"><div class="bc-brand"><img src="{{ asset('img/brasao-balneario-camboriu.png') }}" alt="Brasão de Balneário Camboriú" width="38" height="44" style="object-fit:contain">i-Educar <small>Matrícula Digital</small></div><a class="bc-header-link" href="/pre-matricula-digital">Voltar à pré-matrícula</a></div></header><main class="bc-guardian-main">
@if(session("bc_guardian_profile_id"))<p><a class="bc-button" href="{{ route('bc-guardian.profile') }}">Meu perfil e dependentes</a> <a class="bc-button" href="{{ route('bc-guardian.data') }}">Ficha cadastral</a></p>@endif
@php
$documentApproved = $application && in_array($application->status, [\App\EnrollmentRequests\RequestStatus::Approved, \App\EnrollmentRequests\RequestStatus::VacancyConfirmed, \App\EnrollmentRequests\RequestStatus::Registered]) && $application->approved_at;
@endphp
<section class="bc-hero"><span class="bc-eyebrow">Matrícula Digital</span><h1>{{ $pmd ? 'Acompanhe sua matrícula' : 'Sua matrícula, passo a passo' }}</h1><p>{{ $pmd ? 'Consulte a situação da inscrição e acompanhe a entrega dos documentos.' : 'Acesse com seu protocolo e e-mail para acompanhar a inscrição e a documentação.' }}</p></section>
@include('bc-registration.guardian-progress')
@if($application?->workflow_version === 2)
@include('bc-registration.physical-guardian')
@endif
@if($pmd)
@php
$dataReview = app(\App\EnrollmentRequests\DeclaredStudentData::class)->reviewFor($pmd->id);
@endphp
@if($dataReview?->status === 'CORRECTION')<p class="notice" role="alert"><strong>Correção cadastral solicitada:</strong> {{ $dataReview->reason }} @if(session('bc_guardian_profile_id'))<a href="{{ route('bc-guardian.data') }}">Corrigir ficha cadastral</a>@endif</p>@endif
@endif
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

@if(!$pmd)
<div class="bc-auth-grid"><div class="card"><span class="bc-section-kicker">Primeiro acesso</span><h2>Acesso do responsável</h2><p>No primeiro acesso ou para vincular outra inscrição, informe protocolo e e-mail cadastrado. Se já confirmou seu perfil, informe apenas o e-mail. Enviaremos um código de acesso.</p>
<form method="post" action="{{ route('bc-guardian.access') }}">@csrf
<label>Protocolo <input name="protocol" maxlength="100" autocomplete="off" placeholder="Informe seu protocolo" value="{{ old('protocol') }}"></label>
<label>E-mail cadastrado <input name="email" type="email" required maxlength="255" autocomplete="email" placeholder="seuemail@exemplo.com" value="{{ old('email') }}"></label>
<button>Enviar código</button></form></div>
<div class="card"><span class="bc-section-kicker">Confirmação de acesso</span><h2>Já recebeu o código?</h2><p>Digite os seis dígitos enviados ao seu e-mail para continuar.</p><form method="post" action="{{ route('bc-guardian.verify') }}">@csrf
<label>Código de seis dígitos <input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required></label><button>Confirmar acesso</button></form></div></div>
@if(app()->environment('local'))<p><a href="{{ route('bc-guardian.demo') }}">Abrir exemplo fictício de responsável (ambiente local)</a></p>@endif
@elseif(!$documentationOpen)
<div class="card"><h2>Pré-matrícula {{ $pmd->protocol }}</h2>
<p>Aguarde a análise da escola. A documentação será liberada somente após a aprovação da pré-matrícula.</p>
<form class="bc-signout" method="post" action="{{ route('bc-guardian.logout') }}">@csrf<button>Sair</button></form></div>
@elseif(!$application)
@php
$earlyDeadline = $pmd->documentation_deadline ?: ($pmd->process->documentation_deadline ?: now()->addDays(7)->endOfDay());
@endphp
<div class="card"><form class="bc-signout" method="post" action="{{ route('bc-guardian.logout') }}">@csrf<button>Sair do acesso documental</button></form>
<h2>Pré-inscrição {{ $pmd->protocol }}</h2><p>Aluno: {{ $pmd->student->name }}<br>Escola: {{ $pmd->school->name }}<br>
Prazo documental: <strong>{{ \Illuminate\Support\Carbon::parse($earlyDeadline)->format('d/m/Y H:i') }}</strong></p>
<p>A escola vinculará a inscrição à análise e à turma. Sua pré-matrícula foi deferida e a etapa documental está liberada. Os documentos serão preservados na conclusão da matrícula.</p></div>
@if(\Illuminate\Support\Carbon::parse($earlyDeadline)->lt(now()))
<p class="notice">O prazo de envio terminou. Os documentos recebidos no prazo continuam disponíveis para análise. Procure a escola para acompanhar a decisão.</p>
@endif
<div class="card" id="documentos"><h2>Como deseja entregar a documentação?</h2><p>O prazo é igual nas duas formas de entrega. Trocar a modalidade não o reinicia.</p>
@if(\Illuminate\Support\Carbon::parse($earlyDeadline)->gte(now()))
<form method="post" action="{{ route('bc-guardian.mode') }}">@csrf
<label><input type="radio" name="attendance_mode" value="ONLINE" @checked($pmd->document_submission_method === 'ONLINE')> Enviar documentos online</label>
<label><input type="radio" name="attendance_mode" value="PRESENCIAL" @checked($pmd->document_submission_method === 'IN_PERSON')> Entregar documentos na escola</label>
<button>Salvar forma de entrega</button></form>
@endif</div>
<div class="card"><h2>Documentos do processo</h2>
@forelse($types as $type)
@php
$latest = $documents->where('document_type_id', $type->id)->last();
@endphp
<div class="row"><strong>{{ $type->name }}</strong> @if($type->required)<small>obrigatório</small>@endif
<br>Estado: {{ $latest->status ?? 'AGUARDANDO_DOCUMENTO' }}
@if($pmd->document_submission_method === 'ONLINE' && \Illuminate\Support\Carbon::parse($earlyDeadline)->gte(now()) && (!$latest || $latest->status === 'REJECTED'))
<form method="post" action="{{ route('bc-guardian.upload') }}" enctype="multipart/form-data">@csrf
<input type="hidden" name="document_type" value="{{ $type->code }}">
<label>Arquivo PDF, JPG ou PNG (até {{ \App\EnrollmentRequests\DocumentUploadPolicy::label() }}) <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required></label><button>Enviar documento</button></form>
@endif</div>
@empty<p>A escola ainda não configurou documentos para este processo. Consulte a unidade escolar.</p>@endforelse
@if($pmd->document_submission_method === 'IN_PERSON' && \Illuminate\Support\Carbon::parse($earlyDeadline)->gte(now()))<p>Entregue os documentos na escola indicada até o prazo acima. A unidade registrará a entrega.</p>@endif
</div>
@if($documents->isNotEmpty())<div class="card"><h2>Arquivos e versões</h2>
@foreach($documents as $document)<div class="row">{{ $document->original_filename }} · {{ $document->status }} · {{ \Illuminate\Support\Carbon::parse($document->created_at)->format('d/m/Y H:i') }}<br><a href="{{ route('bc-guardian.download-early', $document->id) }}">Baixar arquivo</a></div>@endforeach
</div>@endif
<div class="card"><h2>Histórico</h2>@foreach($events as $event)<div class="row">{{ \Illuminate\Support\Carbon::parse($event->created_at)->format('d/m/Y H:i') }} · {{ str_replace('_', ' ', $event->event) }}</div>@endforeach</div>
@else
<div class="card"><form class="bc-signout" method="post" action="{{ route('bc-guardian.logout') }}">@csrf<button>Sair do acesso documental</button></form>
<h2>Acompanhamento {{ $application->protocol }}</h2>
@if($application->status === \App\EnrollmentRequests\RequestStatus::AwaitingDocuments)
@php
$releasedAt = $application->events->firstWhere('event', \App\EnrollmentRequests\EventType::PreRegistrationApproved)?->created_at;
@endphp
@if($releasedAt)<p>Documentação liberada em <strong>{{ $releasedAt->format('d/m/Y H:i') }}</strong>.</p>@endif
<p class="notice"><strong>Pré-matrícula deferida: aguardando documentação.</strong> Envie os documentos online ou leve-os à escola até {{ $application->document_deadline->format('d/m/Y H:i') }}. A matrícula será efetivada pela escola após a aprovação documental.</p>
<p><a href="#documentos" class="bc-button">Enviar documentos ou escolher entrega presencial</a></p>
@endif<p>Aluno: {{ $application->studentName() }}<br>Escola: {{ $application->school->name }}<br>
Prazo total para entrega: <strong>{{ $application->document_deadline->format('d/m/Y H:i') }}</strong><br>Situação: {{ $application->documentationLabel() }}</p><p>Quando houver recusa ou solicitação de correção, siga o prazo de reentrega de cada documento abaixo, inclusive para entrega presencial.</p></div>
<div class="card" id="documentos"><h2>Como deseja entregar a documentação?</h2><p>A escolha é apenas a forma de entrega. O prazo é o mesmo para as duas opções.</p>
@if(!$application->status->terminal() && !$documentApproved && $application->document_deadline->gte(now()))
<form method="post" action="{{ route('bc-guardian.mode') }}">@csrf
<label><input type="radio" name="attendance_mode" value="ONLINE" @checked($application->attendance_mode === \App\EnrollmentRequests\AttendanceMode::Online)> Enviar documentos online</label>
<label><input type="radio" name="attendance_mode" value="PRESENCIAL" @checked($application->attendance_mode === \App\EnrollmentRequests\AttendanceMode::InPerson)> Entregar documentos na escola</label>
<button>Salvar forma de entrega</button></form>
@endif</div>
<div class="card"><h2>Documentos</h2>
@if($application->attendance_mode === \App\EnrollmentRequests\AttendanceMode::InPerson && !$documentApproved && $application->document_deadline->gte(now()))
<p>Apresente os documentos necessários na unidade escolar até o prazo acima. A escola registrará a entrega e a análise neste sistema.</p>
@elseif($application->attendance_mode === \App\EnrollmentRequests\AttendanceMode::Online)
<p>Envie PDF, JPG ou PNG de até {{ \App\EnrollmentRequests\DocumentUploadPolicy::label() }} por documento. A escola analisará cada arquivo.</p>
@endif
@php
    $currentDocuments = $application->documents->where('status', '!=', \App\EnrollmentRequests\DocumentStatus::Replaced);
    $received = $currentDocuments->whereNotNull('received_at')->count();
    $approved = $currentDocuments->where('status', \App\EnrollmentRequests\DocumentStatus::Approved)->count();
    $corrections = $currentDocuments->filter(fn ($document) => in_array($document->status, [
        \App\EnrollmentRequests\DocumentStatus::Correction, \App\EnrollmentRequests\DocumentStatus::Rejected,
    ]))->count();
@endphp
<p class="notice">{{ $received }} de {{ $currentDocuments->count() }} documentos recebidos · {{ $approved }} aprovados.
@if($corrections) {{ $corrections }} com correção solicitada. @endif</p>
@if(!$application->attendance_mode)<p>Escolha a forma de entrega para iniciar a documentação.</p>@endif
@foreach($application->documents->sortBy('id') as $document)
<div class="row"><strong>{{ $document->documentName() }}</strong> @if($document->required)<small>obrigatório</small>@endif
<br>Estado: {{ $document->status->value }} @if($document->reason)<div class="bc-document-feedback">@if($document->delivery_deadline)<p><strong>Reentregue até {{ $document->delivery_deadline->format('d/m/Y H:i') }}</strong></p>@endif<strong>O que precisa ser corrigido</strong><p>{{ $document->reason }}</p></div>@endif
@if($document->path)<br><a href="{{ route('bc-guardian.download', $document) }}">Baixar arquivo enviado</a>@endif
@if($application->attendance_mode === \App\EnrollmentRequests\AttendanceMode::Online && !$application->status->terminal() && !in_array($application->status, [\App\EnrollmentRequests\RequestStatus::Approved, \App\EnrollmentRequests\RequestStatus::VacancyConfirmed]) && \App\EnrollmentRequests\DocumentDeadlines::effective($document, $application)->gte(now()) && in_array($document->status, [\App\EnrollmentRequests\DocumentStatus::Pending, \App\EnrollmentRequests\DocumentStatus::Rejected, \App\EnrollmentRequests\DocumentStatus::Correction]))
<form method="post" action="{{ route('bc-guardian.upload') }}" enctype="multipart/form-data">@csrf
<input type="hidden" name="document_type" value="{{ $document->document_type->value }}">
@if($document->status !== \App\EnrollmentRequests\DocumentStatus::Pending)<input type="hidden" name="replaces_id" value="{{ $document->id }}">@endif
<label>{{ in_array($document->status, [\App\EnrollmentRequests\DocumentStatus::Rejected, \App\EnrollmentRequests\DocumentStatus::Correction]) ? 'Novo arquivo corrigido' : 'Arquivo' }} <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required></label><button>{{ in_array($document->status, [\App\EnrollmentRequests\DocumentStatus::Rejected, \App\EnrollmentRequests\DocumentStatus::Correction]) ? 'Enviar novo documento' : 'Enviar documento' }}</button></form>
@endif</div>
@endforeach</div>
<div class="card"><h2>Histórico</h2>
@foreach($application->events as $event)
<div class="row">{{ $event->created_at->format('d/m/Y H:i') }} · {{ $event->event->label() }}</div>
@endforeach
</div>
@endif
</main><footer class="bc-footer"><span>BC Educar · Matrícula Digital</span><a href="/pre-matricula-digital">Portal da pré-matrícula</a></footer></body></html>
