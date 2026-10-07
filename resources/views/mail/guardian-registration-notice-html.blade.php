<!doctype html><html lang="pt-BR"><body style="font:16px Arial,sans-serif;color:#20324a">
<p><strong>Protocolo: {{ $protocol }}</strong></p>
@if($kind === 'DOCUMENTS_OPEN')
<h1>Pré-matrícula deferida</h1>
<p>Sua pré-matrícula <strong>{{ $protocol }}</strong> está <strong>aguardando documentação</strong>. A matrícula ainda não foi efetivada.</p>
@if($openedAt)<p>Documentação liberada em {{ $openedAt }}.</p>@endif
<p>Convidamos você a iniciar a matrícula online ou escolher a entrega presencial na escola <strong>até {{ $deadline }}</strong>. O prazo é igual nas duas formas de entrega.</p>
<p>Na opção online, o próximo passo é enviar os documentos da matrícula pelo acompanhamento. Na opção presencial, apresente os documentos na unidade escolar. A conclusão depende da análise e das confirmações da escola.</p>
@else
<p>@include('mail.guardian-registration-notice')</p>
@endif
<p><a href="{{ url('/matricula-digital') }}" style="display:inline-block;background:#175e95;color:#fff;padding:14px 22px;text-decoration:none;border-radius:5px">Acompanhar pré-matrícula, documentação e matrícula</a></p>
<p>Acesse com seu protocolo, e-mail cadastrado e código de confirmação.</p>
</body></html>
