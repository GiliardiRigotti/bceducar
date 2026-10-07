<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="{{ asset('favicon.ico') }}"><title>Comunicações · Matrícula Digital</title>
<link rel="stylesheet" href="{{ asset('vendor/bc-registration/workflow.css') }}?v=20261006-1"></head>
<body class="bc-guardian">@include('bc-registration.guardian-header')<main class="bc-guardian-main">
<section class="bc-hero"><span class="bc-eyebrow">Área do responsável</span><h1>Comunicações</h1><p>Avisos das inscrições que você confirmou, reunidos em um só lugar.</p></section>
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<details class="card bc-school-summary" @if(!empty($filters)) open @endif><summary>Filtrar comunicações<span class="bc-summary-toggle">Refinar resultados</span></summary><div class="bc-summary-content">
<form class="bc-data-form" method="get" action="{{ route('bc-guardian.communications') }}">
<label>Dependente<select name="dependent"><option value="">Todos os dependentes</option>@foreach($dependents as $dependent)<option value="{{ $dependent->id }}" @selected(($filters['dependent'] ?? null) == $dependent->id)>{{ $dependent->name }}</option>@endforeach</select></label>
<label>Inscrição<select name="pmd"><option value="">Todas as inscrições</option>@foreach($applications as $pmd)<option value="{{ $pmd->id }}" @selected(($filters['pmd'] ?? null) == $pmd->id)>{{ $pmd->protocol }} · {{ $pmd->student->name }}</option>@endforeach</select></label>
<label class="bc-checkbox-label"><input type="checkbox" name="unread" value="1" @checked($filters['unread'] ?? false)> Apenas não lidas</label>
<div class="bc-review-actions"><button>Aplicar filtros</button><a class="bc-button bc-button-secondary" href="{{ route('bc-guardian.communications') }}">Limpar</a></div>
</form></div></details>
<p class="bc-muted">{{ $notices->total() }} {{ $notices->total() === 1 ? 'comunicação encontrada' : 'comunicações encontradas' }}</p>
@forelse($notices as $notice)
@php
$metadata = json_decode($notice->registration_metadata ?? $notice->early_metadata ?? '{}', true) ?? [];
$deadline = $metadata['delivery_deadline'] ?? $metadata['deadline'] ?? (in_array($notice->kind, ['DOCUMENTS_OPEN', 'REMINDER']) ? ($notice->document_deadline ?? $notice->documentation_deadline) : null);
@endphp
<article class="card bc-communication" aria-labelledby="notice-{{ $notice->id }}">
<div class="bc-profile-row"><span class="bc-section-kicker">{{ $notice->dependent_name }}</span><span class="bc-status">{{ $notice->read_at ? 'Lida' : 'Não lida' }}</span></div>
<h2 id="notice-{{ $notice->id }}">{{ \App\EnrollmentRequests\GuardianCommunications::title($notice->kind) }}</h2>
<p><strong>Protocolo {{ $notice->protocol }}</strong> · {{ \Illuminate\Support\Carbon::parse($notice->created_at)->format('d/m/Y H:i') }}</p>
<p>{{ \App\EnrollmentRequests\GuardianCommunications::message($notice->kind) }}</p>
@if($notice->kind === 'CORRECTION' && !empty($metadata['reason']))<div class="bc-document-feedback"><strong>Orientação da escola</strong><p>{{ $metadata['reason'] }}</p></div>@endif
@if($deadline)<p>Prazo informado neste aviso: <strong>{{ \Illuminate\Support\Carbon::parse($deadline)->format('d/m/Y H:i') }}</strong></p>@endif
<small>{{ $notice->sent_at ? 'E-mail encaminhado em '.\Illuminate\Support\Carbon::parse($notice->sent_at)->format('d/m/Y H:i') : 'Aviso disponível no acompanhamento' }}. Consulte a inscrição para verificar a situação e os prazos atuais.</small>
<div class="bc-review-actions"><form method="post" action="{{ route('bc-guardian.select', $notice->pmd_id) }}">@csrf<button>Acompanhar inscrição</button></form>
@if(!$notice->read_at)<form method="post" action="{{ route('bc-guardian.communications.read', $notice->id) }}">@csrf<button class="bc-button-secondary">Marcar como lida</button></form>@endif</div>
</article>
@empty<section class="card"><h2>Nenhuma comunicação encontrada</h2><p>Os avisos das inscrições confirmadas aparecerão aqui. Confira os filtros ou volte ao seu perfil.</p><a class="bc-button bc-button-secondary" href="{{ route('bc-guardian.profile') }}">Meu perfil</a></section>@endforelse
@if($notices->hasPages())<nav class="bc-review-actions" aria-label="Paginação das comunicações">@if($notices->previousPageUrl())<a class="bc-button bc-button-secondary" href="{{ $notices->previousPageUrl() }}">Anteriores</a>@endif<span>Página {{ $notices->currentPage() }} de {{ $notices->lastPage() }}</span>@if($notices->nextPageUrl())<a class="bc-button bc-button-secondary" href="{{ $notices->nextPageUrl() }}">Próximas</a>@endif</nav>@endif
</main></body></html>
