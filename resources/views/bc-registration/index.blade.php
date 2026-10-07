@extends('bc-registration.layout')
@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/bc-registration/dashboard.css') }}?v=20261006-1">
@endpush
@section('content')
@php
$filterKeys = ['status', 'attendance_mode', 'school_id', 'process_id', 'grade_id', 'period_id', 'deadline', 'school_year', 'kind'];
$activeFilters = collect(request()->only($filterKeys))->filter(fn ($value) => filled($value));
$activeFilterCount = $activeFilters->count();
$indexRoute = $enrollments ? 'bc-registration.enrollments' : 'bc-registration.index';
$pendingTotal = $counts->only(['INICIADA', 'AGUARDANDO_DOCUMENTOS', 'DOCUMENTOS_ENVIADOS', 'AGUARDANDO_ANALISE', 'EM_ANALISE', 'PENDENCIA_DOCUMENTAL', 'DOCUMENTOS_CORRIGIDOS', 'PRESENCIAL_AGENDADO', 'AGUARDANDO_COMPARECIMENTO'])->sum();
$readyTotal = $counts->only(['APROVADA', 'VAGA_CONFIRMADA'])->sum();
$statusLabels = [
    'INICIADA' => 'Iniciadas', 'AGUARDANDO_DOCUMENTOS' => 'Aguardando documentos',
    'DOCUMENTOS_ENVIADOS' => 'Documentos enviados', 'AGUARDANDO_ANALISE' => 'Aguardando análise',
    'EM_ANALISE' => 'Em análise', 'PENDENCIA_DOCUMENTAL' => 'Com pendências',
    'DOCUMENTOS_CORRIGIDOS' => 'Documentos corrigidos', 'APROVADA' => 'Documentação aprovada',
    'VAGA_CONFIRMADA' => 'Vaga confirmada', 'MATRICULA_EFETIVADA' => 'Matrículas efetivadas',
    'PRESENCIAL_AGENDADO' => 'Atendimento agendado', 'AGUARDANDO_COMPARECIMENTO' => 'Aguardando comparecimento',
    'NAO_COMPARECEU' => 'Não compareceram', 'PRAZO_EXPIRADO' => 'Prazo expirado',
    'INDEFERIDA' => 'Indeferidas', 'CANCELADA' => 'Canceladas',
];
$filterLabels = ['status' => 'Situação', 'attendance_mode' => 'Entrega', 'school_id' => 'Escola', 'process_id' => 'Processo', 'grade_id' => 'Série', 'period_id' => 'Turno', 'deadline' => 'Prazo', 'school_year' => 'Ano', 'kind' => 'Tipo'];
@endphp
<div class="bc-dashboard">
<header class="bc-dashboard-heading">
    <div><span class="bc-eyebrow">Gestão escolar</span><h1>{{ $enrollments ? 'Matrículas efetivadas' : 'Triagem Documental' }}</h1>
    <p>{{ $enrollments ? 'Consulte os alunos matriculados e suas unidades escolares.' : 'Acompanhe a documentação, confira os originais e conclua cada matrícula.' }}</p></div>
    <a class="bc-button bc-button-secondary bc-export" href="{{ route('bc-registration.export', array_merge(['only_enrolled' => $enrollments ? 1 : 0], request()->only($filterKeys))) }}"><span aria-hidden="true">↓</span> Exportar resumo CSV</a>
</header>
<section class="metrics bc-main-metrics" aria-label="Resumo das solicitações">
    <article class="metric"><span class="bc-metric-label"><span class="bc-metric-mark" aria-hidden="true">≡</span>Solicitações encontradas</span><strong>{{ $counts->sum() }}</strong><small>De {{ $scopeTotal }} no seu escopo</small></article>
    @if(!$enrollments)
    <article class="metric"><span class="bc-metric-label"><span class="bc-metric-mark bc-mark-amber" aria-hidden="true">◷</span>Em acompanhamento</span><strong>{{ $pendingTotal }}</strong><small>Entrega, pendências ou análise</small></article>
    <article class="metric"><span class="bc-metric-label"><span class="bc-metric-mark bc-mark-violet" aria-hidden="true">✓</span>Aguardando efetivação</span><strong>{{ $readyTotal }}</strong><small>Documentação aprovada; conferir originais</small></article>
    @endif
    <article class="metric"><span class="bc-metric-label"><span class="bc-metric-mark bc-mark-green" aria-hidden="true">✓</span>Matrículas efetivadas</span><strong>{{ $counts->get('MATRICULA_EFETIVADA', 0) }}</strong><small>Alunos matriculados no i-Educar</small></article>
</section>
@if(!$enrollments && ($dueToday > 0 || $overdue > 0))
<div class="bc-deadline-notice"><div><strong>Prazos que precisam de atenção</strong><span>{{ $dueToday }} vencem hoje · {{ $overdue }} vencidos em aberto</span></div><a href="{{ route($indexRoute, array_merge(request()->except(['page']), ['deadline' => $overdue > 0 ? 'overdue' : 'today'])) }}">Consultar prazos <span aria-hidden="true">→</span></a></div>
@endif
<div class="bc-panel-toolbar" role="group" aria-label="Opções do painel">
    <button type="button" id="bc-filters-button" aria-expanded="{{ $activeFilterCount ? 'true' : 'false' }}" aria-controls="bc-filters-panel">Filtros <span class="bc-tool-count">{{ $activeFilterCount }}</span></button>
    <button type="button" id="bc-indicators-button" aria-expanded="false" aria-controls="bc-indicators-panel">Detalhamento dos indicadores</button>
    <button type="button" id="bc-schools-button" aria-expanded="false" aria-controls="bc-schools-panel">Resumo por escola</button>
</div>
<section id="bc-filters-panel" class="card bc-filter-panel" aria-labelledby="bc-filters-button" @if(!$activeFilterCount) hidden @endif>
    <div class="bc-panel-heading"><h2>Encontre as solicitações</h2><p>Combine filtros para organizar sua fila de trabalho.</p></div>
    <form method="get" class="bc-dashboard-filters">
        <label>Situação <select name="status"><option value="">Todas as situações</option>@foreach(\App\EnrollmentRequests\RequestStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $statusLabels[$status->value] ?? $status->value }}</option>@endforeach</select></label>
        <label>Atendimento <select name="attendance_mode"><option value="">Todas as formas de entrega</option><option value="UNSELECTED" @selected(request('attendance_mode') === 'UNSELECTED')>Aguardando escolha</option>@foreach(\App\EnrollmentRequests\AttendanceMode::cases() as $mode)<option value="{{ $mode->value }}" @selected(request('attendance_mode') === $mode->value)>{{ $mode->value === 'ONLINE' ? 'Online' : 'Presencial' }}</option>@endforeach</select></label>
        <label>Escola <select name="school_id"><option value="">Todas as escolas</option>@foreach($schools as $school)<option value="{{ $school->getKey() }}" @selected((string) request('school_id') === (string) $school->getKey())>{{ $school->name }}</option>@endforeach</select></label>
        <label>Processo PMD <select name="process_id"><option value="">Todos os processos</option>@foreach($processes as $process)<option value="{{ $process->id }}" @selected((string) request('process_id') === (string) $process->id)>{{ $process->name }}</option>@endforeach</select></label>
        <label>Série <select name="grade_id"><option value="">Todas as séries</option>@foreach($grades as $grade)<option value="{{ $grade->getKey() }}" @selected((string) request('grade_id') === (string) $grade->getKey())>{{ $grade->name }}</option>@endforeach</select></label>
        <label>Turno <select name="period_id"><option value="">Todos os turnos</option>@foreach($periods as $period)<option value="{{ $period->getKey() }}" @selected((string) request('period_id') === (string) $period->getKey())>{{ $period->name }}</option>@endforeach</select></label>
        <label>Prazo <select name="deadline"><option value="">Todos os prazos</option><option value="today" @selected(request('deadline') === 'today')>Vence hoje</option><option value="next3" @selected(request('deadline') === 'next3')>Próximos 3 dias</option><option value="next7" @selected(request('deadline') === 'next7')>Próximos 7 dias</option><option value="overdue" @selected(request('deadline') === 'overdue')>Vencido</option></select></label>
        <label>Tipo <select name="kind"><option value="">Todos os tipos</option>@foreach(['NOVA' => 'Nova matrícula', 'REMATRICULA' => 'Rematrícula', 'TRANSFERENCIA' => 'Transferência'] as $value => $label)<option value="{{ $value }}" @selected(request('kind') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label>Ano letivo <input type="number" name="school_year" min="2000" max="2100" placeholder="Ex.: {{ now()->year }}" value="{{ request('school_year') }}"></label>
        <div class="bc-filter-actions"><button type="submit">Aplicar filtros</button><a href="{{ route($indexRoute) }}">Limpar filtros</a></div>
    </form>
</section>
@if($activeFilterCount)
<nav class="bc-filter-chips" aria-label="Filtros aplicados">
    <span>Filtros aplicados</span>
    @foreach($activeFilters as $key => $value)
    @php
    $display = match ($key) {
        'status' => $statusLabels[$value] ?? $value,
        'school_id' => $schools->first(fn ($school) => (string) $school->getKey() === (string) $value)?->name ?? $value,
        'grade_id' => $grades->first(fn ($grade) => (string) $grade->getKey() === (string) $value)?->name ?? $value,
        'period_id' => $periods->first(fn ($period) => (string) $period->getKey() === (string) $value)?->name ?? $value,
        'process_id' => $processes->first(fn ($process) => (string) $process->id === (string) $value)?->name ?? $value,
        'attendance_mode' => ['ONLINE' => 'Online', 'PRESENCIAL' => 'Presencial', 'UNSELECTED' => 'Aguardando escolha'][$value] ?? $value,
        'deadline' => ['today' => 'Vence hoje', 'next3' => 'Próximos 3 dias', 'next7' => 'Próximos 7 dias', 'overdue' => 'Vencido'][$value] ?? $value,
        'kind' => ['NOVA' => 'Nova matrícula', 'REMATRICULA' => 'Rematrícula', 'TRANSFERENCIA' => 'Transferência'][$value] ?? $value,
        default => $value,
    };
    @endphp
    <a href="{{ route($indexRoute, request()->except([$key, 'page'])) }}" aria-label="Remover filtro {{ $filterLabels[$key] }}: {{ $display }}">{{ $filterLabels[$key] }}: {{ $display }} <span aria-hidden="true">×</span></a>
    @endforeach
</nav>
@endif
<section id="bc-indicators-panel" class="card bc-indicator-details" aria-labelledby="bc-indicators-button" hidden>
    <div class="bc-indicator-grid"><section><h2>Situação das solicitações</h2><dl class="bc-stat-list">@foreach($counts as $status => $total)<div><dt>{{ $statusLabels[$status] ?? ucfirst(mb_strtolower(str_replace('_', ' ', $status))) }}</dt><dd>{{ $total }}</dd></div>@endforeach</dl></section>
    <section><h2>Forma de entrega</h2><dl class="bc-stat-list"><div><dt>Online</dt><dd>{{ $modeCounts->get('ONLINE', 0) }}</dd></div><div><dt>Presencial</dt><dd>{{ $modeCounts->get('PRESENCIAL', 0) }}</dd></div><div><dt>Aguardando escolha</dt><dd>{{ $modeCounts->get('', 0) }}</dd></div></dl><h2 class="bc-deadline-heading">Prazos em aberto</h2><dl class="bc-stat-list"><div><dt>Vencem hoje</dt><dd>{{ $dueToday }}</dd></div><div><dt>Vencidos</dt><dd>{{ $overdue }}</dd></div></dl></section></div>
</section>
<section id="bc-schools-panel" class="card" aria-labelledby="bc-schools-button" hidden>
    <h2>Resumo por escola</h2><table><thead><tr><th scope="col">Unidade</th><th scope="col">Solicitações após filtros</th></tr></thead><tbody>
    @forelse($schools->filter(fn ($school) => $schoolCounts->has($school->getKey())) as $school)<tr><td>{{ $school->name }}</td><td>{{ $schoolCounts->get($school->getKey()) }}</td></tr>@empty<tr><td colspan="2">Nenhuma solicitação neste recorte.</td></tr>@endforelse
    </tbody></table>
</section>
<section class="card bc-queue" aria-labelledby="bc-queue-title">
    <header class="bc-queue-heading"><div><h2 id="bc-queue-title">{{ $enrollments ? 'Matrículas da sua unidade' : 'Solicitações para acompanhamento' }}</h2><p>{{ $requests->total() }} {{ $requests->total() === 1 ? 'registro encontrado' : 'registros encontrados' }}{{ $activeFilterCount ? ' com os filtros aplicados' : ' no seu escopo' }}</p></div><span class="bc-queue-order">Mais recentes primeiro</span></header>
    @if($requests->count())
    <div class="bc-table-scroll" tabindex="0" role="region" aria-label="Lista de solicitações">
    <table class="bc-queue-table"><caption class="bc-sr-only">Solicitações documentais, progresso, situação e prazos</caption><thead><tr><th scope="col">Aluno / protocolo</th><th scope="col">Unidade / série / turno</th><th scope="col">Documentos obrigatórios</th><th scope="col">Situação</th><th scope="col">Prazo / histórico</th><th scope="col"><span class="bc-sr-only">Ações</span></th></tr></thead><tbody>
    @foreach($requests as $item)
    @php
    $tone = match ($item->status->value) {
        'MATRICULA_EFETIVADA' => 'success',
        'APROVADA', 'VAGA_CONFIRMADA' => 'info',
        'PENDENCIA_DOCUMENTAL', 'PRAZO_EXPIRADO', 'NAO_COMPARECEU' => 'warning',
        'CANCELADA', 'INDEFERIDA' => 'neutral',
        default => 'default',
    };
    $deliveryOpen = !in_array($item->status->value, ['MATRICULA_EFETIVADA', 'APROVADA', 'VAGA_CONFIRMADA', 'PRAZO_EXPIRADO', 'NAO_COMPARECEU', 'INDEFERIDA', 'CANCELADA']);
    $late = $deliveryOpen && $item->document_deadline?->isPast();
    $todayDeadline = $deliveryOpen && $item->document_deadline?->isToday();
    @endphp
    <tr>
        <td data-label="Aluno"><a class="bc-student-link" href="{{ route('bc-registration.show', $item) }}">{{ $item->studentName() }}</a><span class="bc-row-secondary bc-protocol">{{ $item->protocol }}</span><span class="bc-delivery">{{ $item->attendance_mode ? ($item->attendance_mode->value === 'ONLINE' ? 'Entrega online' : 'Entrega presencial') : 'Aguardando escolha da modalidade' }}</span></td>
        <td data-label="Unidade"><span class="bc-school-name">{{ $item->school->name }}</span><span class="bc-row-secondary">{{ $item->grade->name }} · {{ $item->schoolClass?->period?->name ?? 'Turno não informado' }}</span></td>
        <td data-label="Documentos">@if($item->required_total > 0)<span class="bc-document-count">{{ $item->required_approved }}<small>/{{ $item->required_total }} aprovados</small></span><progress class="bc-document-progress" max="{{ $item->required_total }}" value="{{ $item->required_approved }}" aria-label="{{ $item->required_approved }} de {{ $item->required_total }} documentos obrigatórios aprovados"></progress><span class="bc-row-secondary">{{ $item->required_received }}/{{ $item->required_total }} recebidos no prazo</span>@if($item->required_corrections > 0)<span class="bc-correction-count">{{ $item->required_corrections }} com correção pendente</span>@endif @else <span class="bc-row-secondary">Sem obrigatórios</span> @endif</td>
        <td data-label="Situação"><span class="bc-state bc-state-{{ $tone }}">{{ $item->documentationLabel() }}</span></td>
        <td data-label="Prazo"><span class="bc-row-date {{ $late ? 'bc-date-late' : '' }}">{{ $item->document_deadline?->format('d/m/Y') ?? 'Não informado' }}</span><span class="bc-row-secondary">{{ $item->document_deadline?->format('H:i') }}</span>@if($late || $todayDeadline)<span class="bc-deadline-tag">{{ $late ? 'Prazo vencido' : 'Vence hoje' }}</span>@endif
        <details class="bc-row-history"><summary>Ver histórico</summary><dl><div><dt>Solicitação</dt><dd>{{ ($item->requested_at ?? $item->created_at)?->format('d/m/Y H:i') }}</dd></div><div><dt>Data do deferimento</dt><dd>{{ $item->released_at?->format('d/m/Y H:i') ?? 'Não registrada no histórico anterior' }}</dd></div><div><dt>Última atualização</dt><dd>{{ $item->updated_at?->format('d/m/Y H:i') }}</dd></div></dl></details></td>
        <td class="bc-row-action"><a class="bc-open-request" href="{{ route('bc-registration.show', $item) }}" aria-label="Abrir solicitação {{ $item->protocol }} de {{ $item->studentName() }}">Abrir <span aria-hidden="true">→</span></a></td>
    </tr>
    @endforeach
    </tbody></table></div>
    <div class="bc-queue-footer"><span>Exibindo {{ $requests->firstItem() }}–{{ $requests->lastItem() }} de {{ $requests->total() }}</span>{{ $requests->links('bc-registration.pagination') }}</div>
    @else
    <div class="bc-queue-empty"><span class="bc-empty-mark" aria-hidden="true">≡</span><h3>Nenhuma solicitação neste recorte.</h3><p>{{ $activeFilterCount ? 'Tente remover um filtro ou ampliar os critérios da consulta.' : 'As solicitações aparecerão aqui após a liberação da etapa documental.' }}</p>@if($activeFilterCount)<a class="bc-button bc-button-secondary" href="{{ route($indexRoute) }}">Limpar filtros</a>@endif</div>
    @endif
</section>
</div>
<script>
document.querySelectorAll('.bc-dashboard .bc-panel-toolbar button[aria-controls]').forEach(function (button) {
    button.addEventListener('click', function () {
        const panel = document.getElementById(button.getAttribute('aria-controls'));
        if (!panel) return;
        const expanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!expanded));
        panel.hidden = expanded;
    });
});
</script>
<noscript><style>.bc-dashboard #bc-filters-panel[hidden],.bc-dashboard #bc-indicators-panel[hidden],.bc-dashboard #bc-schools-panel[hidden]{display:block!important}.bc-dashboard .bc-panel-toolbar{display:none}</style></noscript>
@endsection
