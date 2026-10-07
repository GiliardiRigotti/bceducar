<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="{{ asset('favicon.ico') }}"><title>Meu perfil · Matrícula Digital</title>
<link rel="stylesheet" href="{{ asset('vendor/bc-registration/workflow.css') }}?v=20261006-1"></head>
<body class="bc-guardian">@include('bc-registration.guardian-header')<main class="bc-guardian-main">
<section class="bc-hero"><span class="bc-eyebrow">Área do responsável</span><h1>Olá, {{ $profile->name }}</h1><p>Seus dependentes, inscrições e comunicações em um só lugar.</p></section>
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<nav class="bc-workflow-links" aria-label="Seu perfil"><a href="#dependentes">Meus dependentes</a><a href="{{ route('bc-guardian.communications') }}">Comunicações @if($unreadCount)<span class="bc-status">{{ $unreadCount }} não {{ $unreadCount === 1 ? 'lida' : 'lidas' }}</span>@endif</a><a href="#matriculas">Matrículas</a><a href="#dados">Meus dados</a></nav>
<section id="dependentes"><div class="bc-profile-row"><h2>Meus dependentes</h2><a class="bc-button" href="{{ route('bc-guardian.dependents.create') }}">Adicionar dependente</a></div>
<span id="inscricoes"></span>
@forelse($dependents as $dependent)
@php($items = $applications->filter(fn ($pmd) => ($bindings->get($pmd->id)?->dependent_id) == $dependent->id))
<article class="card" aria-labelledby="dependent-{{ $dependent->id }}">
<div class="bc-profile-row"><h3 id="dependent-{{ $dependent->id }}">{{ $dependent->name }}</h3><a href="{{ route('bc-guardian.dependents.edit', $dependent->id) }}">Editar dados</a></div>
<p class="bc-muted">Dados declarados do perfil @if($dependent->date_of_birth) · Nascimento: {{ \Illuminate\Support\Carbon::parse($dependent->date_of_birth)->format('d/m/Y') }} @endif</p>
@forelse($items as $pmd)
@php($application = $requests->get($pmd->id))
<div class="row"><div class="bc-profile-row"><strong>Protocolo {{ $pmd->protocol }}</strong><span class="bc-status">{{ $application?->documentationLabel() ?? 'Acompanhamento da pré-matrícula' }}</span></div>
<p>{{ $pmd->school->name }} · {{ $pmd->process->name }}<br>Estudante declarado na inscrição: {{ $pmd->student->name }}</p>
<div class="bc-review-actions"><form method="post" action="{{ route('bc-guardian.select', $pmd->id) }}">@csrf<button>Acompanhar inscrição</button></form><a class="bc-button bc-button-secondary" href="{{ route('bc-guardian.communications', ['pmd' => $pmd->id]) }}">Ver comunicações</a></div>
@if($dependents->count() > 1)<details class="bc-school-summary bc-dependent-organize"><summary>Organizar no perfil<span class="bc-summary-toggle">Escolher dependente</span></summary><div class="bc-summary-content">
<p>Se esta inscrição pertence a outro dependente do seu perfil, selecione abaixo. A ficha e a matrícula da inscrição serão preservadas.</p>
<form method="post" action="{{ route('bc-guardian.dependents.attach', $pmd->id) }}">@csrf
<label>Dependente<select name="dependent_id" required>@foreach($dependents as $option)<option value="{{ $option->id }}" @selected($option->id == $dependent->id)>{{ $option->name }}</option>@endforeach</select></label>
<label>Motivo da organização<textarea name="reason" rows="2" maxlength="1000" required></textarea></label><button>Organizar inscrição</button></form>
</div></details>@endif
</div>
@empty<p>Ainda não há inscrições confirmadas para este dependente.</p>@endforelse
</article>
@empty<section class="card"><h3>Adicione seu primeiro dependente</h3><p>Você também pode confirmar uma inscrição existente para trazer seus dados declarados ao perfil.</p></section>@endforelse
<a class="bc-button bc-button-secondary" href="/pre-matricula-digital">Nova pré-matrícula</a>
</section>
<section class="card" id="matriculas"><h2>Matrículas</h2>
@forelse($requests->whereNotNull('registration_id') as $application)<div class="row"><strong>Protocolo {{ $application->source_reference }}</strong><p>Matrícula i-Educar {{ $application->registration_id }}</p></div>@empty<p>Nenhuma matrícula efetivada nas inscrições confirmadas.</p>@endforelse</section>
<section class="card" id="dados"><h2>Meus dados</h2><p>{{ $profile->name }}<br>{{ $profile->email }}</p>
<details class="bc-school-summary"><summary>Confirmar outra inscrição<span class="bc-summary-toggle">Adicionar ao perfil</span></summary><div class="bc-summary-content">
<p>Informe o protocolo e o e-mail cadastrados na inscrição. Enviaremos um código para confirmar seu acesso.</p>
<form method="post" action="{{ route('bc-guardian.access') }}">@csrf<label>Protocolo da inscrição<input name="protocol" required maxlength="100"></label><label>E-mail cadastrado<input type="email" name="email" required maxlength="255" value="{{ $profile->email }}"></label><button>Enviar código de acesso</button></form>
@if(session('bc_guardian_challenge'))<form method="post" action="{{ route('bc-guardian.verify') }}">@csrf<label>Código<input name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"></label><button>Confirmar vínculo</button></form>@endif
</div></details></section>
<form method="post" action="{{ route('bc-guardian.logout') }}">@csrf<button class="bc-button-secondary">Sair</button></form>
</main></body></html>
