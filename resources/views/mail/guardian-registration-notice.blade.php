Protocolo: {{ $protocol }}.

@if($kind === 'PHYSICAL_REMINDER')
O prazo de apresentação ou regularização física termina em {{ $deadline }}. Consulte o acompanhamento e apresente à escola os documentos indicados.
@elseif($kind === 'PHYSICAL_EXPIRED')
O prazo de apresentação ou regularização física terminou em {{ $deadline }}. Procure a escola para orientação e eventual reabertura motivada. Este aviso não cancela automaticamente sua solicitação.
@elseif($kind === 'PHYSICAL_REQUIRED')
Matrícula em confirmação. Sua documentação digital foi aprovada. Apresente os documentos físicos na unidade escolar para concluir a matrícula.
@if($deadline)Prazo: {{ $deadline }}.@endif
@elseif($kind === 'PREREGISTRATION_REGISTERED')
Sua pré-matrícula {{ $protocol }} foi registrada e aguarda análise da escola. A etapa documental será liberada após o deferimento.
@elseif($kind === 'MODE_SELECTED')
A forma de entrega documental da inscrição {{ $protocol }} foi escolhida. O prazo permanece o mesmo.
@elseif($kind === 'DOCUMENT_RECEIVED')
Um documento da inscrição {{ $protocol }} foi recebido para análise. O recebimento não significa aprovação documental ou matrícula.
@elseif($kind === 'DOCUMENTATION_APPROVED')
A documentação da inscrição {{ $protocol }} foi aprovada. A matrícula ainda não existe e aguarda efetivação pela unidade escolar.
@elseif($kind === 'DOCUMENTS_OPEN')
Sua pré-matrícula {{ $protocol }} foi deferida e está aguardando documentação. A matrícula ainda não foi efetivada.
@if($openedAt)Documentação liberada em {{ $openedAt }}.@endif
Envie os documentos online ou entregue-os presencialmente na escola até {{ $deadline }}. O prazo é o mesmo nas duas modalidades.
Acesse o acompanhamento para escolher a forma de entrega dos documentos: {{ url('/matricula-digital') }}
@elseif($kind === 'CORRECTION')
Foi solicitada uma correção nos documentos da inscrição {{ $protocol }}.
@if($deadline)Reentregue o documento corrigido até {{ $deadline }}. Esse prazo se aplica ao documento indicado no acompanhamento.@endif
@elseif($kind === 'EXPIRED')
O prazo de entrega documental da inscrição {{ $protocol }} terminou.
@elseif($kind === 'REMINDER')
O prazo de entrega documental da inscrição {{ $protocol }} termina em {{ $deadline }}.
@else
A matrícula vinculada à inscrição {{ $protocol }} foi efetivada.
@endif

Consulte os detalhes em {{ url('/matricula-digital') }} usando seu protocolo, e-mail cadastrado e código de acesso.

Esta mensagem não contém documentos pessoais. Em caso de dúvida, procure a escola.
