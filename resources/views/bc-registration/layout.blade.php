<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><link rel="icon" href="{{ asset('favicon.ico') }}?v=bc-20261005"><meta name="viewport" content="width=device-width, initial-scale=1"><title>BC Educar · Matrículas</title>
<link rel="stylesheet" href="{{ asset('vendor/bc-registration/workflow.css') . '?v=20261005-8' }}">@stack('styles')</head><body class="bc-workflow"><header class="bc-workflow-header"><div class="bc-header-inner"><div class="bc-brand"><img src="{{ asset('img/brasao-balneario-camboriu.png') }}" alt="Brasão de Balneário Camboriú" width="38" height="44" style="object-fit:contain">i-Educar <small>BC Educar · Matrículas</small></div><nav class="bc-workflow-nav" aria-label="Navegação principal"><a href="{{ route('bc-registration.index') }}" @if(request()->routeIs('bc-registration.index')) aria-current="page" @endif>Triagem Documental</a><a href="/pre-matricula-digital">Pré-Matrícula Digital</a><a href="/intranet/educar_index.php">Início do i-Educar</a></nav></div></header>
<main class="bc-workflow-main"><nav class="bc-workflow-links" aria-label="Etapas administrativas">
<a href="/pre-matricula-digital/inscricoes">Pré-matrículas</a>
<a href="{{ route('bc-registration.index') }}" @if(request()->routeIs('bc-registration.index')) aria-current="page" @endif>Triagem Documental</a>
<a href="{{ route('bc-registration.enrollments') }}" @if(request()->routeIs('bc-registration.enrollments')) aria-current="page" @endif>Matrículas</a>
@if(auth()->user()?->isAdmin() || auth()->user()?->isInstitutional()) <a href="{{ route('bc-registration.configuration') }}" @if(request()->routeIs('bc-registration.configuration')) aria-current="page" @endif>Configuração</a> @endif
</nav>@if(session('status'))<p class="alert bc-alert bc-alert-success">{{ session('status') }}</p>@endif
@if($errors->any())<div class="alert bc-alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@yield('content')</main><footer class="bc-footer"><span>BC Educar · Matrícula Digital</span><span>Secretaria de Educação · Balneário Camboriú</span></footer></body></html>
