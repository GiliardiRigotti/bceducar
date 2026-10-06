<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Meu perfil · Matrícula Digital</title><link rel="stylesheet" href="{{ asset('vendor/bc-registration/workflow.css') }}?v=20261005-9"></head>
<body class="bc-guardian"><main class="bc-guardian-main">
<section class="bc-hero"><span class="bc-eyebrow">Área do responsável</span><h1>Olá, {{ $profile->name }}</h1><p>Seu perfil reúne as inscrições que você confirmou com código de acesso.</p></section>
@if(session('status'))<p class="notice">{{ session('status') }}</p>@endif
@if($errors->any())<div class="notice">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<nav class="card bc-review-actions" aria-label="Área do responsável"><a href="#dependentes">Meus dependentes</a><a href="/pre-matricula-digital">Nova pré-matrícula</a><a href="#inscricoes">Minhas pré-matrículas</a><a href="{{ route('bc-guardian.show') }}">Documentação</a><a href="#matriculas">Matrículas</a><a href="#dados">Meus dados</a></nav>
<span id="inscricoes"></span><section id="dependentes"><h2>Meus dependentes</h2>
@foreach($applications->groupBy('student_id') as $items)
<div class="card"><h3>{{ $items->first()->student->name }}</h3><p>Dados declarados da pré-matrícula</p>
@foreach($items as $pmd)
@php($application = $requests->get($pmd->id))
<div class="row"><strong>Protocolo {{ $pmd->protocol }}</strong><p>{{ $pmd->school->name }} · {{ $pmd->process->name }}<br>{{ $application?->documentationLabel() ?? 'Acompanhamento da pré-matrícula' }}</p>
<form method="post" action="{{ route('bc-guardian.select', $pmd->id) }}">@csrf<button>Acompanhar inscrição</button></form></div>
@endforeach</div>
@endforeach</section>
<section class="card" id="matriculas"><h2>Matrículas</h2>
@forelse($requests->whereNotNull('registration_id') as $application)<p>Protocolo {{ $application->source_reference }} · Matrícula i-Educar {{ $application->registration_id }}</p>@empty<p>Nenhuma matrícula efetivada nas inscrições confirmadas.</p>@endforelse</section>
<section class="card" id="dados"><h2>Meus dados</h2><p>{{ $profile->name }}<br>{{ $profile->email }}</p><p>Para confirmar outra inscrição, acesse com o protocolo e o e-mail cadastrado.</p><form method="post" action="{{ route('bc-guardian.access') }}">@csrf<label>Protocolo da inscrição <input name="protocol" required maxlength="100"></label><label>E-mail cadastrado <input type="email" name="email" required maxlength="255" value="{{ $profile->email }}"></label><button>Enviar código para vincular inscrição</button></form>
@if(session('bc_guardian_challenge'))<form method="post" action="{{ route('bc-guardian.verify') }}">@csrf<label>Código <input name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6"></label><button>Confirmar vínculo</button></form>@endif</section>
<form method="post" action="{{ route('bc-guardian.logout') }}">@csrf<button>Sair</button></form>
</main></body></html>
