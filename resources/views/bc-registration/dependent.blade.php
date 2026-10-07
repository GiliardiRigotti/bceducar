<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="{{ asset('favicon.ico') }}"><title>{{ $item ? 'Editar dependente' : 'Novo dependente' }} · Matrícula Digital</title>
<link rel="stylesheet" href="{{ asset('vendor/bc-registration/workflow.css') }}?v=20261006-1"></head>
<body class="bc-guardian">@include('bc-registration.guardian-header')<main class="bc-guardian-main">
<a href="{{ route('bc-guardian.profile') }}">← Meu perfil</a>
<section class="bc-hero"><span class="bc-eyebrow">Meus dependentes</span><h1>{{ $item ? 'Editar dados do dependente' : 'Adicionar dependente' }}</h1><p>Guarde os dados declarados do dependente no seu perfil. Esse cadastro não realiza uma pré-matrícula.</p></section>
@if($errors->any())<div class="notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="card"><h2>Identificação e contatos</h2>
<form class="bc-data-form" method="post" action="{{ $item ? route('bc-guardian.dependents.update', $item->id) : route('bc-guardian.dependents.store') }}">@csrf
@foreach(\App\EnrollmentRequests\DeclaredStudentData::FIELDS as $field => $label)
<label>{{ $label }} @if($field !== 'name')<small>Opcional</small>@endif
@if($field === 'gender')<select name="gender"><option value="">Não informado</option><option value="1" @selected(old('gender', $item?->gender) == 1)>Feminino</option><option value="2" @selected(old('gender', $item?->gender) == 2)>Masculino</option></select>
@else<input name="{{ $field }}" type="{{ $field === 'date_of_birth' ? 'date' : (in_array($field, ['phone', 'mobile']) ? 'tel' : 'text') }}" value="{{ old($field, $item?->{$field}) }}" @required($field === 'name') @if($field === 'date_of_birth') max="{{ now()->format('Y-m-d') }}" @endif maxlength="{{ ['cpf' => 14, 'rg' => 50, 'phone' => 30, 'mobile' => 30][$field] ?? 255 }}">@endif
</label>
@endforeach
@if($item)<label>Motivo da atualização<textarea name="reason" rows="2" maxlength="1000" required>{{ old('reason') }}</textarea></label>@endif
<p>As inscrições já enviadas mantêm seus dados e decisões. Para corrigir uma inscrição, abra o acompanhamento e sua ficha cadastral.</p>
<div class="bc-review-actions"><button>Salvar dependente</button><a class="bc-button bc-button-secondary" href="{{ route('bc-guardian.profile') }}">Cancelar</a></div>
</form></section>
</main></body></html>
