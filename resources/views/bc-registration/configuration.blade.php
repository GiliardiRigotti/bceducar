@extends('bc-registration.layout')

@section('content')
<h1>Configuração documental</h1>
<p>Defina os documentos exigidos e o prazo de cada processo de pré-matrícula.</p>

<div class="card">
    <h2>Processos autorizados</h2>
    @forelse($processes as $item)
        <a href="{{ route('bc-registration.configuration', ['process' => $item->id]) }}">{{ $item->name }}</a>@unless($loop->last) · @endunless
    @empty
        <p>Seu perfil não administra nenhum processo com escolas vinculadas.</p>
    @endforelse
</div>

@if($process)
<div class="card">
    <h2>{{ $process->name }}</h2>
    <form method="post" action="{{ route('bc-registration.configuration.processes.update', $process->id) }}">
        @csrf
        <label>Novas liberações documentais
            <select name="document_workflow_enabled">
                @if($process->document_workflow_enabled === null)<option value="legacy" selected>Manter compatibilidade do processo legado</option>@endif
                <option value="1" @selected($process->document_workflow_enabled === true || $process->document_workflow_enabled === 1)>Ativar documentação</option>
                <option value="0" @selected($process->document_workflow_enabled === false || $process->document_workflow_enabled === 0)>Suspender novas liberações</option>
            </select>
        </label>
        <p><small>Processos novos exigem ativação. Solicitações documentais já abertas continuam seu fluxo; esta decisão não efetiva matrículas nem altera prazos existentes.</small></p>
        @if($process->documentation_configured_at)<p><small>Última configuração: {{ $process->documentation_configured_at }} · usuário #{{ $process->documentation_configured_by }}</small></p>@endif
        <label>Prazo total de entrega (dias após o deferimento)
            <input type="number" name="documentation_delivery_days" min="1" max="365" value="{{ old('documentation_delivery_days', $process->documentation_delivery_days) }}">
        </label>
        <label>Prazo de reentrega após recusa ou correção (dias)
            <input type="number" name="documentation_retry_days" min="1" max="365" required value="{{ old('documentation_retry_days', $process->documentation_retry_days ?? 3) }}">
        </label>
        <label>Prazo da conferência física após integração (dias)
            <input type="number" name="physical_delivery_days" min="1" max="365" required value="{{ old('physical_delivery_days', $process->physical_delivery_days ?? 7) }}">
        </label>
        <label>Prazo para regularizar divergência física (dias)
            <input type="number" name="physical_retry_days" min="1" max="365" required value="{{ old('physical_retry_days', $process->physical_retry_days ?? 3) }}">
        </label>

        <p><small>Dias corridos, até 23h59 do último dia, iguais para online e presencial. Reentrega conta da análise do operador e vale somente para o documento recusado. Padrão de reentrega: 3 dias. Prazos já concedidos não são alterados ao salvar os ajustes.</small></p>
        <label>Data fixa de entrega (alternativa ao prazo total em dias)
            <input type="datetime-local" name="documentation_deadline" value="{{ old('documentation_deadline', $process->documentation_deadline ? \Illuminate\Support\Carbon::parse($process->documentation_deadline)->format('Y-m-d\TH:i') : '') }}">
        </label>
        <p><small>Se preencher o prazo total em dias, a data fixa será desconsiderada. Sem dias nem data fixa, permanece o padrão de 7 dias para novas liberações. Alterações não modificam solicitações já liberadas.</small></p>
        <table>
            <thead><tr><th>Documento</th><th>Descrição</th><th>Solicitar</th><th>Obrigatório</th></tr></thead>
            <tbody>
            @foreach($types as $type)
                <tr>
                    <td>{{ $type->name }} @unless($type->active)<small>(inativo)</small>@endunless</td>
                    <td>{{ $type->description }}</td>
                    <td>@if($type->active && $type->code)<input type="checkbox" name="documents[{{ $type->id }}][enabled]" value="1" @checked($policy->has($type->id))>@endif</td>
                    <td>@if($type->active && $type->code)<input type="checkbox" name="documents[{{ $type->id }}][required]" value="1" @checked((bool) $policy->get($type->id))>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <button type="submit">Salvar exigências do processo</button>
    </form>
</div>
@endif

<div class="card">
    <h2>Cadastrar tipo documental</h2>
    <form method="post" action="{{ route('bc-registration.configuration.types.create') }}">
        @csrf
        <label>Nome <input name="name" maxlength="150" required></label>
        <label>Descrição <input name="description" maxlength="1000"></label>
        <label>Código <input name="code" maxlength="100" pattern="[A-Z][A-Z0-9_]*" placeholder="EX.: DECLARACAO_MEDICA" required></label>
        <small>Use letras maiúsculas, números e sublinhado. O código é único e permanece estável no histórico.</small>
        <button type="submit">Cadastrar</button>
    </form>
</div>

@if(auth()->user()->isAdmin())
<div class="card">
    <h2>Editar catálogo compartilhado</h2>
    @foreach($types as $type)
        <form method="post" action="{{ route('bc-registration.configuration.types.update', $type->id) }}">
            @csrf
            <label>Nome <input name="name" value="{{ $type->name }}" maxlength="150" required></label>
            <label>Descrição <input name="description" value="{{ $type->description }}" maxlength="1000"></label>
            <label>Situação <select name="active"><option value="1" @selected($type->active)>Ativo</option><option value="0" @selected(!$type->active)>Inativo</option></select></label>
            <button type="submit">Atualizar</button>
        </form>
    @endforeach
</div>
@endif
@endsection
