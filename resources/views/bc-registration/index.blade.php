@extends('bc-registration.layout')
@section('content')
@php
$activeFilterCount = collect(request()->only(['status', 'attendance_mode', 'school_id', 'process_id', 'grade_id', 'period_id', 'deadline', 'school_year', 'kind']))->filter(fn ($value) => filled($value))->count();
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
@endphp
<div class="bc-dashboard-heading"><div><h1>{{ $enrollments ? "Matrículas efetivadas" : "Triagem Documental" }}</h1>
<p>{{ $enrollments ? 'Consulte os alunos matriculados e suas unidades escolares.' : 'Confira os documentos e conclua a matrícula dos alunos.' }}</p></div>
<a class="bc-button bc-button-secondary" href="{{ route('bc-registration.export', array_merge(['only_enrolled' => $enrollments ? 1 : 0], request()->only(['status', 'attendance_mode', 'school_id', 'process_id', 'grade_id', 'period_id', 'deadline', 'school_year', 'kind']))) }}">Exportar resumo CSV</a></div>
<div class="metrics bc-main-metrics">
    <div class="metric"><span>Solicitações encontradas</span><strong>{{ $counts->sum() }}</strong><small>De {{ $scopeTotal }} no seu escopo</small></div>
    @if(!$enrollments)
    <div class="metric"><span>Em acompanhamento</span><strong>{{ $pendingTotal }}</strong><small>Entrega, pendências ou análise</small></div>
    <div class="metric"><span>Prontas para matrícula</span><strong>{{ $readyTotal }}</strong><small>Documentação aprovada ou vaga confirmada</small></div>
    @endif
    <div class="metric"><span>Matrículas efetivadas</span><strong>{{ $counts->get('MATRICULA_EFETIVADA', 0) }}</strong><small>Alunos matriculados no i-Educar</small></div>
</div>
@if(!$enrollments && ($dueToday > 0 || $overdue > 0))
<p class="bc-deadline-notice"><strong>Prazos que precisam de atenção</strong><span>{{ $dueToday }} vencem hoje · {{ $overdue }} vencidos em aberto</span></p>
@endif
<div class="bc-panel-toolbar" role="group" aria-label="Opções do painel">
<button type="button" id="bc-indicators-button" aria-expanded="false" aria-controls="bc-indicators-panel">Detalhamento dos indicadores</button>
<button type="button" id="bc-filters-button" aria-expanded="{{ $activeFilterCount ? 'true' : 'false' }}" aria-controls="bc-filters-panel">Filtros @if($activeFilterCount)<span>({{ $activeFilterCount }})</span>@endif</button>
<button type="button" id="bc-schools-button" aria-expanded="false" aria-controls="bc-schools-panel">Resumo por escola</button>
</div>
<section id="bc-indicators-panel" class="card bc-indicator-details" aria-labelledby="bc-indicators-button" hidden>
<div class="bc-indicator-grid bc-summary-content">
<section><h2>Situação das solicitações</h2><dl class="bc-stat-list">@foreach($counts as $status => $total)<div><dt>{{ $statusLabels[$status] ?? ucfirst(mb_strtolower(str_replace('_', ' ', $status))) }}</dt><dd>{{ $total }}</dd></div>@endforeach</dl></section>
<section><h2>Forma de entrega</h2><dl class="bc-stat-list"><div><dt>Online</dt><dd>{{ $modeCounts->get('ONLINE', 0) }}</dd></div><div><dt>Presencial</dt><dd>{{ $modeCounts->get('PRESENCIAL', 0) }}</dd></div><div><dt>Aguardando escolha</dt><dd>{{ $modeCounts->get('', 0) }}</dd></div></dl>
<h2 class="bc-deadline-heading">Prazos em aberto</h2><dl class="bc-stat-list"><div><dt>Vencem hoje</dt><dd>{{ $dueToday }}</dd></div><div><dt>Vencidos</dt><dd>{{ $overdue }}</dd></div></dl></section>
</div></section>
<section id="bc-filters-panel" class="card bc-filter-panel" aria-labelledby="bc-filters-button" @if(!$activeFilterCount) hidden @endif><div class="bc-summary-content"><form method="get">
    <label>Situação <select name="status"><option value="">Todas</option>@foreach(\App\EnrollmentRequests\RequestStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $statusLabels[$status->value] ?? $status->value }}</option>@endforeach</select></label>
    <label>Atendimento <select name="attendance_mode"><option value="">Todos</option><option value="UNSELECTED" @selected(request('attendance_mode') === 'UNSELECTED')>Aguardando escolha</option>@foreach(\App\EnrollmentRequests\AttendanceMode::cases() as $mode)<option value="{{ $mode->value }}" @selected(request('attendance_mode') === $mode->value)>{{ $mode->value }}</option>@endforeach</select></label>
    <label>Escola <select name="school_id"><option value="">Todas</option>@foreach($schools as $school)<option value="{{ $school->getKey() }}" @selected((string) request('school_id') === (string) $school->getKey())>{{ $school->name }}</option>@endforeach</select></label>
    <label>Processo PMD <select name="process_id"><option value="">Todos</option>@foreach($processes as $process)<option value="{{ $process->id }}" @selected((string) request('process_id') === (string) $process->id)>{{ $process->name }}</option>@endforeach</select></label>
    <label>Série <select name="grade_id"><option value="">Todas</option>@foreach($grades as $grade)<option value="{{ $grade->getKey() }}" @selected((string) request('grade_id') === (string) $grade->getKey())>{{ $grade->name }}</option>@endforeach</select></label>
    <label>Turno <select name="period_id"><option value="">Todos</option>@foreach($periods as $period)<option value="{{ $period->getKey() }}" @selected((string) request('period_id') === (string) $period->getKey())>{{ $period->name }}</option>@endforeach</select></label>
    <label>Prazo <select name="deadline"><option value="">Todos</option><option value="today" @selected(request('deadline') === 'today')>Vence hoje</option><option value="next3" @selected(request('deadline') === 'next3')>Próximos 3 dias</option><option value="next7" @selected(request('deadline') === 'next7')>Próximos 7 dias</option><option value="overdue" @selected(request('deadline') === 'overdue')>Vencido</option></select></label>
    <label>Tipo <select name="kind"><option value="">Todos</option>@foreach(['NOVA' => 'Nova matrícula', 'REMATRICULA' => 'Rematrícula', 'TRANSFERENCIA' => 'Transferência'] as $value => $label)<option value="{{ $value }}" @selected(request('kind') === $value)>{{ $label }}</option>@endforeach</select></label>
    <label>Ano <input type="number" name="school_year" min="2000" max="2100" value="{{ request('school_year') }}"></label>
    <button>Filtrar</button> <a href="{{ route($enrollments ? 'bc-registration.enrollments' : 'bc-registration.index') }}">Limpar filtros</a>
</form></div></section>
<section id="bc-schools-panel" class="card" aria-labelledby="bc-schools-button" hidden><div class="bc-summary-content"><table><thead><tr><th>Unidade</th><th>Solicitações após filtros</th></tr></thead><tbody>
    @forelse($schools->filter(fn ($school) => $schoolCounts->has($school->getKey())) as $school)
        <tr><td>{{ $school->name }}</td><td>{{ $schoolCounts->get($school->getKey()) }}</td></tr>
    @empty
        <tr><td colspan="2">Nenhuma solicitação neste recorte.</td></tr>
    @endforelse
</tbody></table></div></section>
<div class="card"><table><thead><tr><th>Protocolo</th><th>Aluno</th><th>Unidade / série / turno</th><th>Atendimento</th><th>Documentos obrigatórios</th><th>Situação</th><th>Solicitação</th><th>Data do deferimento</th><th>Prazo</th><th>Última atualização</th></tr></thead><tbody>
    @forelse($requests as $item)
        <tr><td><a href="{{ route('bc-registration.show', $item) }}">{{ $item->protocol }}</a></td><td>{{ $item->studentName() }}</td><td>{{ $item->school->name }}<br><small>{{ $item->grade->name }} · {{ $item->schoolClass?->period?->name ?? 'Turno não informado' }}</small></td><td>{{ $item->attendance_mode?->value ?? 'Aguardando escolha da modalidade' }}</td>
        <td>@if($item->required_total > 0){{ $item->required_received }}/{{ $item->required_total }} recebidos no prazo<br><small>{{ $item->required_approved }}/{{ $item->required_total }} aprovados</small>@if($item->required_corrections > 0)<br><small>{{ $item->required_corrections }} com correção pendente</small>@endif @else Sem obrigatórios @endif</td>
        <td>{{ $item->documentationLabel() }}</td><td>{{ ($item->requested_at ?? $item->created_at)?->format('d/m/Y H:i') }}</td><td>{{ $item->released_at?->format('d/m/Y H:i') ?? 'Não registrada no histórico anterior' }}</td><td>{{ $item->document_deadline->format('d/m/Y H:i') }}</td><td>{{ $item->updated_at?->format('d/m/Y H:i') }}</td></tr>
    @empty
        <tr><td colspan="10">Nenhuma solicitação neste recorte.</td></tr>
    @endforelse
</tbody></table>{{ $requests->links() }}</div>
<script>
document.querySelectorAll('.bc-panel-toolbar button[aria-controls]').forEach(function (button) {
    button.addEventListener('click', function () {
        const panel = document.getElementById(button.getAttribute('aria-controls'));
        if (!panel) return;
        const expanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!expanded));
        panel.hidden = expanded;
    });
});
</script>
@endsection
