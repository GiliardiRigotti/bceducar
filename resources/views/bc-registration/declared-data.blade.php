<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ficha cadastral · Matrícula Digital</title><link rel="stylesheet" href="{{ asset('vendor/bc-registration/workflow.css') }}?v=20261006-1"></head><body class="bc-guardian">@include('bc-registration.guardian-header')<main class="bc-guardian-main">
<a href="{{ route('bc-guardian.profile') }}">← Meu perfil</a>
<section class="bc-hero"><h1>Dados declarados da pré-matrícula</h1><p>Protocolo {{ $pmd->protocol }}. A escola confere os dados declarados antes da integração ao i-Educar. A matrícula depende da conclusão das etapas de documentação.</p></section>
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if($review?->status === 'CORRECTION')<p class="notice" role="alert"><strong>A escola solicitou correção:</strong> {{ $review->reason }}</p>@endif
<section class="card"><h2>Identificação e contatos do aluno</h2>
<form class="bc-data-form" method="post" action="{{ route('bc-guardian.data.update') }}">@csrf
@foreach(\App\EnrollmentRequests\DeclaredStudentData::FIELDS as $field => $label)
<label>{{ $label }}
@if($field === 'gender')<select name="student[gender]"><option value="">Não informado</option><option value="1" @selected(old('student.gender', $pmd->student->gender) == 1)>Feminino</option><option value="2" @selected(old('student.gender', $pmd->student->gender) == 2)>Masculino</option></select>
@else<input name="student[{{ $field }}]" type="{{ $field === 'date_of_birth' ? 'date' : 'text' }}" value="{{ old('student.'.$field, $field === 'date_of_birth' ? $pmd->student->date_of_birth?->format('Y-m-d') : $pmd->student->{$field}) }}" maxlength="255" @required(in_array($field, ['name', 'date_of_birth']))>
@endif</label>
@endforeach
<label>Motivo da atualização <textarea name="reason" required maxlength="1000" rows="2">{{ old('reason') }}</textarea></label>
@if($pmd->status === 1 || $review?->status === 'CORRECTION')<button>Salvar dados para conferência</button>@else<p>Após o deferimento, a alteração depende de uma solicitação de correção cadastral pela escola.</p>@endif
</form></section>
<section class="card"><h2>Responsável e endereço declarados</h2><p>{{ $pmd->responsible->name }}<br>{{ $pmd->responsible->email }}</p>@foreach($pmd->student->addresses as $address)<p>{{ $address->address }}, {{ $address->number }} · {{ $address->complement }}<br>{{ $address->neighborhood }} · {{ $address->city }} · CEP {{ $address->postal_code }}</p>@endforeach</section>
</main></body></html>
