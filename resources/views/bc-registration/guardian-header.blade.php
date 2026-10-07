<header class="bc-guardian-header"><div class="bc-header-inner">
<div class="bc-brand"><img src="{{ asset('img/brasao-balneario-camboriu.png') }}" alt="Brasão de Balneário Camboriú" width="38" height="44" style="object-fit:contain">i-Educar <small>Matrícula Digital</small></div>
<nav class="bc-workflow-nav" aria-label="Área do responsável">
<a href="{{ route('bc-guardian.profile') }}" @if(request()->routeIs('bc-guardian.profile', 'bc-guardian.dependents.*')) aria-current="page" @endif>Meu perfil</a>
<a href="{{ route('bc-guardian.communications') }}" @if(request()->routeIs('bc-guardian.communications')) aria-current="page" @endif>Comunicações</a>
<a href="{{ route('bc-guardian.show') }}">Documentação</a>
</nav></div></header>
