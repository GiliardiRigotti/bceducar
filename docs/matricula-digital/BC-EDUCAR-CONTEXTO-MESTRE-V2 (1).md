> Arquivo recebido preservado como hist?rico. Vers?o vigente: [BC-EDUCAR-CONTEXTO-MESTRE.md](BC-EDUCAR-CONTEXTO-MESTRE.md).

# BC Educar --- Contexto Mestre Único

**Versão consolidada:** 05/10/2026\
**Status:** fonte normativa única do projeto\
**Escopo:** Pré-Matrícula Digital (PMD), etapa documental BC,
matrícula/enturmação i-Educar, comunicações, segurança, mapas,
homologação e pendências.

> ## REGRA DE PRECEDÊNCIA
>
> Este documento é a **fonte única de verdade funcional, arquitetural e
> operacional** do BC Educar para o Codex e para futuras manutenções.
>
> Ele substitui os documentos fragmentados por ondas, auditorias,
> relatórios de ajuste, validações, decisões e pendências produzidos até
> esta versão.
>
> Em caso de divergência entre documentação histórica e este arquivo,
> prevalece este documento.
>
> O código legado não deve ser considerado automaticamente correto
> quando contrariar as regras invariantes descritas aqui.
>
> Toda alteração futura relevante deve atualizar este arquivo, evitando
> recriar documentação paralela de contexto.

------------------------------------------------------------------------

# 1. Objetivo e arquitetura

O BC Educar integra:

1.  **Pré-Matrícula Digital --- PMD/Portabilis**, responsável pela
    inscrição e estados oficiais da pré-matrícula;
2.  **i-Educar**, responsável pela matrícula e enturmação nativas;
3.  **camada BC**, responsável por política documental, modalidade de
    entrega, recebimento, triagem, aprovação, auditoria, comunicações e
    integração do fluxo.

A extensão BC não deve substituir os serviços nativos de matrícula e
enturmação.

O objetivo é permitir:

``` text
pré-matrícula
→ deferimento escolar
→ liberação documental
→ escolha ONLINE/PRESENCIAL pelo responsável
→ entrega
→ triagem
→ aprovação documental
→ efetivação explícita
→ matrícula e enturmação nativas
```

com opção de documentação totalmente digital ou presencial.

------------------------------------------------------------------------

# 2. Regra invariante principal

A escolha entre documentação **ONLINE** e **PRESENCIAL nunca ocorre
durante a pré-matrícula**.

Enquanto o PMD estiver em `WAITING`:

-   `attendance_mode` permanece `null`;
-   não é permitida escolha de modalidade;
-   não é permitido upload documental pelo responsável;
-   não é permitido recebimento/análise como fase documental liberada;
-   vínculo BC existente não remove essa barreira;
-   operador escolar não pode escolher modalidade pelo responsável.

Somente após:

``` text
DEFERIMENTO DA PRÉ-MATRÍCULA
+
LIBERAÇÃO DOCUMENTAL
```

o responsável pode escolher a forma de entrega.

**Aprovação documental não cria matrícula.**

A matrícula somente existe após ação explícita de efetivação usando o
serviço oficial PMD/i-Educar.

------------------------------------------------------------------------

# 3. Terminologia obrigatória

## Pré-matrícula

Inscrição inicial realizada no PMD.

## Deferir pré-matrícula

Decisão escolar que permite a continuidade da inscrição.

Não significa matrícula.

## Liberar documentação

Abertura da fase documental.

Deferimento e liberação são eventos de domínio distintos, embora uma
única ação de interface possa coordená-los.

## Modalidade documental

Forma de entrega:

-   `ONLINE`
-   `IN_PERSON`

Não representa modalidade de ensino.

## Aprovar documento

Validar individualmente um item documental.

## Aprovar documentação

Confirmar que os documentos obrigatórios da solicitação foram atendidos.

Não cria matrícula.

## Efetivar matrícula

Ação explícita que chama o serviço nativo PMD/i-Educar para matrícula e
enturmação.

## Homologado

Somente utilizar essa expressão quando houver validação correspondente
no ambiente/instância e aceite aplicável. Testes locais não equivalem a
homologação municipal.

------------------------------------------------------------------------

# 4. Estados oficiais do PMD

Preservar:

  Código   Estado
  -------- -------------------
  1        `WAITING`
  2        `ACCEPTED`
  3        `REJECTED`
  4        `SUMMONED`
  5        `IN_CONFIRMATION`

Não criar novos estados PMD para representar documentação.

A documentação BC é uma dimensão independente.

------------------------------------------------------------------------

# 5. Matriz normativa de estados

  -------------------------------------------------------------------------------------------
  Etapa               PMD                 Documentação BC  Modalidade          Matrícula
  ------------------- ------------------- ---------------- ------------------- --------------
  Inscrição enviada   `WAITING`           não iniciada     `null`              não

  Deferida/liberada   `SUMMONED`          liberada         `null`              não

  Modalidade          `SUMMONED`          aguardando       ONLINE/PRESENCIAL   não
  escolhida                               entrega                              

  Entrega em          `SUMMONED`          parcial/em       escolhida           não
  andamento                               triagem                              

  Correção            `SUMMONED`          correção         escolhida           não
                                          pendente                             

  Documentação        `IN_CONFIRMATION`   `APROVADA`       escolhida           não
  aprovada                                                                     

  Matrícula efetivada `ACCEPTED`          `APROVADA`       escolhida           sim

  Pré-matrícula       `REJECTED`          encerrada/N.A.   ---                 não
  indeferida                                                                   
  -------------------------------------------------------------------------------------------

------------------------------------------------------------------------

# 6. Pré-matrícula pública

O formulário público PMD não deve solicitar ONLINE/PRESENCIAL.

Ao criar a inscrição:

``` text
PMD = WAITING
attendance_mode = null
matrícula = inexistente
```

Pode conter:

-   escola principal;
-   escolas alternativas, quando configuradas;
-   aluno;
-   responsável;
-   endereço;
-   dados definidos pelo processo PMD.

A modalidade documental não participa dessa decisão.

------------------------------------------------------------------------

# 7. Deferimento e liberação documental

Ao deferir uma pré-matrícula, o sistema deve:

1.  validar escopo da escola e operador;
2.  validar turma/contexto compatível quando aplicável;
3.  mudar `WAITING → SUMMONED`;
4.  registrar o deferimento;
5.  liberar a fase documental quando o processo estiver habilitado;
6.  criar/vincular a solicitação BC conforme arquitetura atual;
7.  copiar a política documental vigente para a solicitação;
8.  definir/copiar prazo;
9.  registrar evento de liberação;
10. gerar comunicação aplicável;
11. manter `attendance_mode = null`;
12. não criar matrícula.

Deferimento deve ser idempotente.

`REJECTED` não deve ser reaberto por chamadas da etapa documental.

------------------------------------------------------------------------

# 8. Ativação do fluxo documental por processo

Existe controle:

``` text
document_workflow_enabled
```

## Semântica

### Processos existentes anteriores à migration

``` text
document_workflow_enabled = null
```

`null` representa compatibilidade com o comportamento legado anterior à
decisão explícita.

### Novos processos

``` text
document_workflow_enabled = false
```

O administrador deve ativar explicitamente o fluxo.

### Processo ativado

``` text
document_workflow_enabled = true
```

Permite novas liberações documentais, respeitando todas as demais
regras.

### Processo suspenso

``` text
document_workflow_enabled = false
```

Suspende **novas liberações**.

Suspender:

-   não cria caminho de matrícula direta;
-   não remove barreiras documentais;
-   não encerra solicitações BC já iniciadas;
-   não reinicia prazo;
-   não converte inscrições.

Solicitações já vinculadas e fases anteriores ao vínculo que possuam
estado documental continuam seu fluxo e prazo.

Depois de uma decisão explícita, a interface não deve permitir retornar
ao `null` de compatibilidade.

A ativação exige pelo menos um tipo documental ativo.

Registrar:

-   último ator;
-   data;
-   log de configuração.

Operador escolar não pode alterar essa configuração.

A administração ainda precisa selecionar quais processos municipais
reais serão ativados.

------------------------------------------------------------------------

# 9. Modalidade de entrega

Depois da liberação, o responsável escolhe no portal:

``` text
ONLINE
```

ou:

``` text
IN_PERSON
```

Regras:

-   escolha exclusivamente pelo responsável;
-   auditada;
-   não cria matrícula;
-   não reinicia prazo;
-   não deve ser inferida;
-   não usar fallback ONLINE;
-   operador escolar não escolhe pelo responsável;
-   troca pode ocorrer enquanto permitida pelas regras atuais;
-   troca preserva histórico e documentos existentes.

------------------------------------------------------------------------

# 10. Autenticação do responsável

O acesso documental utiliza:

-   protocolo;
-   e-mail do responsável armazenado no PMD;
-   código de seis dígitos;
-   validade de dez minutos;
-   limite de tentativas;
-   sessão vinculada à inscrição confirmada.

Protocolo isolado não autoriza acesso.

A sessão de uma família não pode acessar arquivos ou solicitações de
outra.

Mecanismos fictícios são permitidos somente em ambientes controlados de
teste.

------------------------------------------------------------------------

# 11. Catálogo documental

## 11.1 Tipos originais

Os dez tipos canônicos originais foram preservados.

O enum continua válido para esses tipos.

## 11.2 Categorias adicionais

O sistema agora aceita categorias documentais adicionais configuráveis.

O código deve:

-   usar letras maiúsculas;
-   números;
-   `_`;
-   máximo de 100 caracteres;
-   ser único.

Exemplo:

``` text
GUARDA_JUDICIAL
AUTORIZACAO_ESPECIAL
DOCUMENTO_COMPLEMENTAR
```

`DocumentCodeCast` aceita:

-   tipos canônicos como enum;
-   códigos adicionais como `DocumentCode`;

e mantém o código como string na serialização dos modelos.

Não existe mais a regra de que o fluxo está tecnicamente limitado a dez
categorias.

## 11.3 Snapshot

Ao liberar a etapa documental, a política configurada é copiada para a
solicitação.

A solicitação guarda:

-   código;
-   nome histórico;
-   obrigatoriedade.

Renomear ou inativar o catálogo depois disso não altera solicitações já
abertas.

Processos legados sem política continuam com fallback dos tipos
originais quando aplicável.

## 11.4 Segurança de códigos

Somente tipos previstos na própria solicitação podem ser recebidos.

Códigos arbitrários devem ser recusados no serviço inclusive em chamadas
diretas.

Os formulários devem utilizar os tipos da solicitação, não aceitar
livremente qualquer código enviado pelo cliente.

## 11.5 Canais suportados

Categorias adicionais funcionam em:

-   upload do responsável;
-   upload/canal escolar;
-   recebimento presencial;
-   correção;
-   substituição;
-   aprovação.

## 11.6 Dependência institucional

A tecnologia permite configurar categorias adicionais.

A Secretaria ainda deve definir quais documentos reais serão exigidos em
cada processo.

------------------------------------------------------------------------

# 12. Configuração documental

Tela:

``` text
/bc/matriculas/configuracao
```

Permite, conforme autorização:

-   catálogo documental;
-   códigos;
-   nomes;
-   obrigatoriedade;
-   ativação/inativação;
-   política por processo;
-   ativação do fluxo documental.

Somente perfis administrativos autorizados podem alterar configuração.

Operadores escolares não possuem essa permissão.

------------------------------------------------------------------------

# 13. Documentação ONLINE

Quando ONLINE:

1.  apresentar checklist da solicitação;
2.  exigir fase liberada;
3.  validar responsável;
4.  validar prazo;
5.  validar se código pertence à solicitação;
6.  validar extensão;
7.  validar MIME;
8.  validar tamanho;
9.  armazenar privadamente;
10. calcular SHA-256;
11. versionar;
12. auditar;
13. recalcular progresso;
14. emitir aviso sem duplicidade.

Versões substituídas permanecem auditáveis, mas não representam a versão
atual.

------------------------------------------------------------------------

# 14. Documentação PRESENCIAL

Quando PRESENCIAL:

-   responsável entrega fisicamente;
-   escola registra recebimento;
-   digitalização não é obrigatória;
-   entrega parcial é permitida;
-   mesmos documentos obrigatórios;
-   mesmo prazo;
-   registrar ator e data;
-   manter auditoria.

A ausência de arquivo digital não pode impedir aprovação de documento
presencial validamente recebido.

------------------------------------------------------------------------

# 15. Triagem documental

Tela:

``` text
/bc/matriculas
```

É separada da fila de pré-matrículas.

Deve apresentar, conforme aplicável:

-   protocolo;
-   aluno;
-   escola;
-   processo;
-   série;
-   turno;
-   solicitação;
-   deferimento;
-   modalidade;
-   progresso;
-   prazo;
-   situação;
-   ações autorizadas.

Filtros devem respeitar escopo escolar no backend.

------------------------------------------------------------------------

# 16. Análise individual

Documentos são analisados individualmente.

Pode haver:

-   aprovação;
-   correção;
-   estados documentais previstos pela implementação.

Correção/reprovação exige justificativa quando a regra de domínio
exigir.

Nova versão não elimina a auditabilidade das anteriores.

------------------------------------------------------------------------

# 17. Estado documental agregado

Não derivar o estado geral simplesmente da última ação.

Calcular com os documentos obrigatórios atuais da solicitação.

Regras:

1.  correção obrigatória tem prioridade;
2.  obrigatório não recebido mantém pendência;
3.  análise em andamento permanece representada;
4.  opcional não bloqueia obrigatórios;
5.  versões substituídas não contam como versão atual;
6.  todos os obrigatórios aplicáveis precisam estar válidos antes da
    aprovação geral.

Não aprovar automaticamente a documentação apenas porque o último
documento individual foi aprovado.

------------------------------------------------------------------------

# 18. Aprovação documental

Exigir:

-   pré-matrícula deferida;
-   fase documental liberada;
-   modalidade escolhida;
-   obrigatórios recebidos;
-   obrigatórios aprovados;
-   política de prazo atendida;
-   operador autorizado.

Resultado:

``` text
BC = APROVADA
PMD = IN_CONFIRMATION
matrícula = inexistente
```

A interface e comunicação devem deixar claro:

> Documentação aprovada não significa matrícula efetivada.

------------------------------------------------------------------------

# 19. Efetivação da matrícula

A efetivação é ação explícita.

Usar o serviço oficial PMD/i-Educar.

Não criar implementação paralela de matrícula/enturmação.

Validar:

-   documentação;
-   escola;
-   turma;
-   ano;
-   turno;
-   capacidade;
-   duplicidade;
-   matrícula ativa;
-   inscrições concorrentes conforme política homologada;
-   autorização.

Resultado:

``` text
PMD = ACCEPTED
BC = MATRICULA_EFETIVADA
matrícula nativa = criada
enturmação nativa = criada
```

A ação deve ser idempotente.

------------------------------------------------------------------------

# 20. Prazos

Trocar modalidade não reinicia prazo.

## Vencimento

O vencimento documental não rejeita automaticamente a pré-matrícula.

Resultado documental:

``` text
DEADLINE_EXPIRED
```

O estado PMD deve ser preservado.

Não fazer automaticamente:

``` text
PMD = REJECTED
```

Rejeição/cancelamento exige ação administrativa explícita e autorizada.

## Entrega tempestiva

Documentos entregues corretamente dentro do prazo podem ser
analisados/aprovados depois do vencimento.

O prazo não invalida retroativamente a entrega tempestiva.

Novos recebimentos após o prazo seguem a política de bloqueio vigente.

------------------------------------------------------------------------

# 21. Legado e dados históricos

Não converter dados históricos em massa.

Vínculos BC cujo PMD permaneça `WAITING` continuam bloqueados até
deferimento explícito.

Código deve aceitar:

``` text
attendance_mode = null
```

Inscrições rejeitadas pelo comportamento antigo de vencimento podem ser
revisadas administrativamente, mas não restauradas automaticamente.

Não reverter decisões administrativas históricas via migration.

Processos legados com `document_workflow_enabled = null` permanecem em
compatibilidade até decisão administrativa explícita.

------------------------------------------------------------------------

# 22. Navegação

## Pré-matrículas

``` text
/bc/matriculas/pmd
```

Responsável por deferimento/decisão inicial.

Não escolher modalidade.

## Triagem

``` text
/bc/matriculas
```

Documentação, análise, presencial, correção, aprovação e efetivação.

## Efetivadas

``` text
/bc/matriculas/efetivadas
```

Lista concluídos e vínculo com registro nativo.

## Configuração

``` text
/bc/matriculas/configuracao
```

Política, catálogo e ativação.

## Responsável

``` text
/matricula-digital
```

Acompanhamento documental.

## PMD público

``` text
/pre-matricula-digital
```

Pré-matrícula e acompanhamento nativo.

------------------------------------------------------------------------

# 23. Stepper do responsável

Três macroetapas:

``` text
1. Pré-matrícula
2. Documentação
3. Matrícula
```

Mostrar estado real, como:

-   aguardando análise;
-   deferida;
-   documentação liberada;
-   escolher entrega;
-   aguardando documentos;
-   em análise;
-   correção necessária;
-   documentação aprovada;
-   aguardando efetivação;
-   matrícula efetivada.

Não apresentar como concluído algo que ainda depende da escola.

------------------------------------------------------------------------

# 24. Permissões

Backend é a fonte de autorização.

Ocultar botão não é segurança.

Escopo escolar deve proteger:

-   leitura;
-   deferimento;
-   recebimento;
-   upload escolar;
-   análise;
-   correção;
-   aprovação;
-   efetivação;
-   download;
-   exportação;
-   ações administrativas aplicáveis.

Operador de uma escola não pode atuar na solicitação de outra.

Configuração exige perfil administrativo autorizado.

------------------------------------------------------------------------

# 25. Auditoria

Registrar, conforme aplicável:

-   inscrição/solicitação;
-   evento;
-   data/hora;
-   ator;
-   escola;
-   estado anterior/novo;
-   motivo;
-   documento/versão;
-   alteração de configuração.

Eventos auditáveis incluem:

-   deferimento;
-   liberação;
-   escolha/troca de modalidade;
-   upload;
-   presencial;
-   substituição;
-   análise;
-   correção;
-   aprovação;
-   vencimento;
-   efetivação;
-   ativação/suspensão de processo.

------------------------------------------------------------------------

# 26. Comunicações --- arquitetura

A fila de avisos existente é a origem das comunicações.

Não criar filas funcionais paralelas sem necessidade arquitetural
explícita.

A origem do aviso deve ser exatamente uma:

-   evento BC; ou
-   evento PMD/documental suportado.

Avisos contemplados:

1.  inscrição registrada;
2.  documentação liberada;
3.  modalidade escolhida;
4.  documento recebido;
5.  correção;
6.  documentação aprovada;
7.  lembrete;
8.  prazo encerrado;
9.  matrícula efetivada.

Aprovação documental deve informar que a matrícula ainda depende da
escola.

------------------------------------------------------------------------

# 27. E-mail

SMTP foi validado localmente com Mailpit.

Cobertura local inclui:

-   nove tipos de aviso;
-   HTML;
-   texto;
-   protocolo/link;
-   retry;
-   backoff;
-   recuperação de falha;
-   ausência de duplicidade.

Isso não equivale a entrega externa ou homologação institucional.

Produção depende de:

-   SMTP institucional;
-   credenciais;
-   domínio;
-   autenticação;
-   TLS;
-   teste externo;
-   aceite.

------------------------------------------------------------------------

# 28. SMS e WhatsApp

A infraestrutura técnica para canais externos foi implementada.

`SendGuardianMessages` usa a fila de avisos existente e registra cada
canal em:

``` text
bc_guardian_message_deliveries
```

A combinação:

``` text
notification_id + channel
```

é única.

Cada canal possui:

-   estado independente;
-   até cinco tentativas;
-   espera progressiva;
-   registro de aceitação da ponte;
-   retry independente do e-mail.

## Segurança/contrato

-   flags permanecem `false` por padrão;
-   corte de data explícito é obrigatório;
-   não reenviar histórico;
-   URLs fixas/configuradas;
-   HTTPS obrigatório fora de local/testing;
-   redirecionamentos recusados;
-   chave de idempotência enviada no header/corpo;
-   ponte deve respeitar idempotência;
-   telefone inválido não é enviado;
-   aviso desatualizado não é enviado.

HTTP `2xx` significa:

> ponte aceitou a solicitação

e **não**:

> mensagem confirmadamente entregue ao telefone.

## Automação

Comando:

``` text
bc:send-guardian-messages
```

integrado ao scheduler e ao loop local aplicável.

## Dependências restantes

Não houve comunicação com provedor real.

Para ativar SMS/WhatsApp ainda são necessários:

-   definição da ponte/provedor;
-   credenciais;
-   requisitos do canal;
-   configuração institucional;
-   teste de entrega externa;
-   aceite.

Portanto:

``` text
INFRAESTRUTURA = IMPLEMENTADA
PROVEDOR/ENTREGA REAL = PENDENTE
```

------------------------------------------------------------------------

# 29. Dashboard e CSV

Filtros/indicadores podem incluir:

-   escola;
-   modalidade;
-   status;
-   ano;
-   vencimento;
-   processo;
-   série;
-   turno;
-   tipo de solicitação.

Respeitar escopo escolar.

CSV deve usar o mesmo recorte filtrado.

Não incluir PII desnecessária, especialmente:

-   nome do aluno quando exportação for agregada;
-   dados do responsável;
-   documentos.

Manter proteção contra fórmulas quando aplicável.

Análises históricas avançadas continuam evolução futura.

------------------------------------------------------------------------

# 30. Segurança dos documentos

Armazenamento privado.

Nunca expor caminho público direto.

Validar:

-   código documental solicitado;
-   extensão;
-   MIME;
-   tamanho;
-   hash SHA-256;
-   autorização;
-   versão;
-   auditoria.

Cobrir testes com MIME/extensão disfarçados.

Cobrir acesso cruzado:

-   entre responsáveis;
-   entre escolas.

------------------------------------------------------------------------

# 31. Retenção documental

Existe comando:

``` text
bc:document-retention-report --days=<prazo>
```

Ele produz **somente contagens por categoria**.

Não exclui:

-   arquivo;
-   registro;
-   documento;
-   solicitação.

Para entrar no relatório:

-   solicitação precisa estar encerrada;
-   atualização da solicitação precisa ser anterior ao corte;
-   documento precisa ser anterior ao corte.

Solicitações abertas são preservadas.

Arquivos anteriores ao vínculo também são preservados conforme regra
implementada.

## Regra crítica

Nenhum prazo institucional foi inventado pelo sistema.

A configuração pode registrar:

-   prazo aprovado;
-   referência da política aprovada;

para fins de relatório.

**Não implementar executor de descarte antes de existir política
institucional aprovada.**

Uma política definitiva precisa estabelecer, no mínimo:

-   categorias;
-   marcos de contagem;
-   prazos;
-   exceções;
-   documentos sensíveis;
-   documentos médicos;
-   backups;
-   auditoria;
-   autorização;
-   descarte;
-   responsabilização.

O relatório técnico não autoriza exclusão.

------------------------------------------------------------------------

# 32. LGPD e governança documental

A segurança técnica não substitui decisão institucional.

Antes de descarte/produção definitiva, a Secretaria deve aprovar:

-   finalidade;
-   retenção;
-   descarte;
-   acesso;
-   tratamento de dados sensíveis;
-   exceções;
-   responsabilidade;
-   procedimentos de backup;
-   política de documentos médicos.

Até essa decisão:

``` text
RELATÓRIO = permitido
EXCLUSÃO AUTOMÁTICA = não permitida
```

------------------------------------------------------------------------

# 33. Mapas

Fluxos migrados utilizam:

``` text
Leaflet 1.9.4
```

Tiles padrão:

``` text
OpenStreetMap
```

Sem API Google Maps nos fluxos migrados de:

-   pré-matrícula;
-   consulta de escolas;
-   edição;
-   pontos de transporte.

------------------------------------------------------------------------

# 34. Configuração dos mapas

``` dotenv
MAP_TILE_URL=https://tile.openstreetmap.org/{z}/{x}/{y}.png
MAP_ATTRIBUTION="&copy; OpenStreetMap contributors"
MAP_MAX_ZOOM=19
GEOCODING_URL=
GEOCODING_USER_AGENT=BC-Educar/1.0
```

`MapConfig` centraliza provider, atribuição, zoom e centro.

Troca de tiles não deve exigir reescrita do fluxo de matrícula.

------------------------------------------------------------------------

# 35. Endereço e geolocalização

ViaCEP permanece para CEP.

Falha de ViaCEP deve permitir preenchimento manual.

A residência precisa de confirmação explícita quando o fluxo exigir
coordenadas.

O centro padrão do mapa nunca pode ser salvo automaticamente como
residência.

Alteração de coordenadas invalida confirmação anterior quando aplicável.

Campos visíveis de latitude/longitude foram retirados do formulário; o
contrato interno continua preservado.

------------------------------------------------------------------------

# 36. Geocoding

Busca residencial automática somente por infraestrutura:

-   própria; ou
-   municipal;

compatível com a API adotada.

**Não enviar endereço residencial ao Nominatim público.**

`GEOCODING_URL` vazio significa sem geocoder automático.

Localização manual continua disponível.

Proteções implementadas incluem:

-   POST;
-   CSRF;
-   cache;
-   rate limiting;
-   timeout;
-   debounce;
-   erro genérico;
-   não registrar endereço/resposta sensível em logs.

------------------------------------------------------------------------

# 37. Geolocalização do dispositivo

Somente solicitar localização após ação explícita, como:

``` text
Estou neste endereço: usar minha localização
```

Sem permissão/resposta:

-   não inventar localização;
-   permitir mapa/manual.

------------------------------------------------------------------------

# 38. Transporte escolar

Preservar contratos do módulo.

Não alterar automaticamente:

-   vagas;
-   matrícula;
-   rotas;
-   veículos;
-   motoristas;
-   elegibilidade;
-   sequência de itinerário.

Latitude/longitude permanecem compatíveis.

Distância aproximada em linha reta não deve ser apresentada como trajeto
viário ou direito ao transporte.

------------------------------------------------------------------------

# 39. Infraestrutura geográfica de produção

Tiles públicos OSM não representam infraestrutura municipal ilimitada.

Antes de alto volume, definir:

-   tiles adequados;
-   geocoding próprio/municipal;
-   HTTPS;
-   CSP;
-   CORS;
-   capacidade;
-   monitoramento aplicável.

Busca residencial automática depende dessa infraestrutura.

------------------------------------------------------------------------

# 40. Decisões arquiteturais consolidadas

## DEC-001 --- Evolução por migrations aditivas

Não reescrever migrations históricas do PMD.

## DEC-002 --- Snapshot documental

Política é copiada ao iniciar fase BC.

## DEC-003 --- Responsável autenticado

Protocolo + e-mail + código temporário.

## DEC-004 --- Matrícula nativa

Efetivação pelo serviço oficial PMD/i-Educar.

## DEC-005 --- Presencial sem digitalização obrigatória

Recebimento físico pode existir sem arquivo.

## DEC-006 --- Vencimento independente da decisão PMD

Não rejeitar automaticamente.

## DEC-007 --- Política coerente de upload

Canais devem obedecer limites compatíveis.

## DEC-008 --- Progresso pelos obrigatórios atuais

Não usar simplesmente último evento.

## DEC-009 --- Modalidade após deferimento

`attendance_mode = null` antes da escolha; `WAITING` é barreira backend;
aprovação documental não matricula; origem única de avisos.

## DEC-010 --- Catálogo extensível

Dez tipos canônicos permanecem, mas códigos adicionais configuráveis são
aceitos e congelados por solicitação.

## DEC-011 --- Ativação explícita por processo

Novos processos começam desativados; legado `null` preserva
compatibilidade; administração decide ativação.

## DEC-012 --- Comunicação multicanal desacoplada

E-mail, SMS e WhatsApp possuem estados/tentativas independentes; fila de
aviso permanece origem comum.

## DEC-013 --- Retenção sem exclusão automática

Relatório técnico pode existir sem política; descarte somente após
decisão institucional aprovada.

------------------------------------------------------------------------

# 41. Migrations relevantes

Entre as migrations consolidadas:

``` text
database/migrations/pmd/2026_10_03_140002_add_pmd_document_foundation.php
database/migrations/pmd/2026_10_03_140004_add_pmd_document_type_code.php
2026_10_05_180000_separate_document_choice_and_notice_origin
2026_10_05_190000_expand_document_catalog_and_activation
2026_10_05_190001_create_guardian_message_deliveries
```

## `180000`

-   `attendance_mode` nullable;
-   origem BC ou PMD para aviso;
-   exatamente uma origem;
-   sem exclusão de dados;
-   rollback protegido.

## `190000`

-   nome documental histórico;
-   catálogo extensível;
-   ativação por processo;
-   ator/data de configuração;
-   compatibilidade para processos anteriores;
-   novos processos desativados por padrão.

## `190001`

-   estado de entrega por canal;
-   FK ao aviso;
-   unicidade;
-   rollback protegido quando houver histórico que precise ser
    preservado.

Não executar `migrate:fresh` em ambiente relevante.

------------------------------------------------------------------------

# 42. Componentes técnicos relevantes

## Categoria/persistência

``` text
app/EnrollmentRequests/DocumentCode
app/EnrollmentRequests/DocumentCodeCast
app/EnrollmentRequests/PmdIntake
app/EnrollmentRequests/RegistrationWorkflow
app/Models/RegistrationDocument.php
```

## Portal/configuração

``` text
PmdGuardianDocuments
GuardianDocumentController
BcRegistrationRequestController
PmdDocumentConfigurationController
views de configuration, guardian e show
```

## Comunicação

``` text
app/Console/Commands/SendGuardianMessages.php
app/EnrollmentRequests/GuardianMessageTransport.php
config/bc-messages.php
```

## Retenção

``` text
app/Console/Commands/DocumentRetentionReport.php
config/bc-retention.php
```

## Automação

``` text
app/Console/Kernel.php
docker-compose.yml
.env.example
seeder PMD
```

------------------------------------------------------------------------

# 43. Endpoints relevantes

``` text
POST /graphql
POST /bc/matriculas/pmd/{id}
POST /matricula-digital/modalidade
POST /matricula-digital/documentos
POST /bc/matriculas/documentos/{id}/analise
POST /bc/matriculas/{id}/acao
POST /bc/matriculas/{id}/entrega-presencial
```

Regra:

> toda restrição crítica deve ser aplicada no backend, mesmo que a UI
> também bloqueie a ação.

------------------------------------------------------------------------

# 44. Validação técnica consolidada

## Checkpoint mais recente --- implementação das pendências

Resultado:

-   **98 testes aprovados**;
-   **1 teste com aviso de depreciação legado**;
-   **941 asserções**;
-   **17 suites**;
-   banco isolado `testing`;
-   nenhuma falha;
-   tempo registrado: 151,91 s.

Testes novos:

``` text
ExtendedDocumentCatalogTest — 6
GuardianMessageDeliveryTest — 5
DocumentRetentionReportTest — 2
```

Cobertura nova inclui:

-   categoria adicional online;
-   categoria adicional presencial;
-   correção;
-   snapshot;
-   rejeição de código não solicitado;
-   ativação;
-   compatibilidade legado;
-   default de novos processos;
-   idempotência por canal;
-   retry independente;
-   corte de data;
-   flags;
-   telefone;
-   redirecionamento;
-   relatório sem exclusão.

## Qualidade

-   Lighthouse: schema válido;
-   `Pint --test`: 21 arquivos PHP aprovados;
-   HTTP: portal, bundle/config PMD e quatro páginas principais
    retornaram 200 nos perfis testados;
-   SMTP local novamente validado.

## Logs registrados

``` text
storage/logs/pending-final-regression.txt
storage/logs/pending-http-validation.txt
storage/logs/pending-smtp-validation.txt
```

## Aviso legado conhecido

``` text
clsBase::$titulo
```

criação de propriedade dinâmica no transporte legado.

Não foi introduzido por esta etapa.

------------------------------------------------------------------------

# 45. Checkpoints históricos relevantes

Antes da implementação das pendências, houve regressão do ajuste
estrutural com:

-   78 testes backend;
-   805 asserções;
-   16 testes frontend;
-   build Vite;
-   schema válido;
-   HTTP 200;
-   migration aplicada em testing/local.

Também houve recorte específico com:

-   19 testes;
-   314 asserções;
-   fluxo, prazo, upload, dashboard e escopo.

Esses números são checkpoints de momentos/recortes diferentes.

**Não somar as contagens.**

O checkpoint consolidado mais recente é:

``` text
98 testes / 941 asserções
```

para a regressão backend registrada na implementação das pendências.

------------------------------------------------------------------------

# 46. Cobertura funcional confirmada

A cobertura automatizada acumulada contempla, entre outros:

-   inscrição sem modalidade;
-   `WAITING` bloqueado;
-   vínculo BC não burlando `WAITING`;
-   deferimento sem matrícula;
-   liberação;
-   modalidade pelo responsável;
-   online;
-   presencial;
-   categorias canônicas;
-   categorias adicionais;
-   código arbitrário recusado;
-   snapshot;
-   upload privado;
-   MIME/extensão;
-   limites;
-   acesso cruzado;
-   entrega parcial;
-   correção;
-   versão;
-   aprovação sem matrícula;
-   matrícula nativa;
-   enturmação;
-   idempotência;
-   duplicidade;
-   capacidade;
-   prazo;
-   vencimento sem rejeição;
-   análise após prazo de entrega tempestiva;
-   ativação de processos;
-   legado;
-   filtros/CSV;
-   escopo;
-   e-mail;
-   comunicação multicanal em nível de ponte;
-   retries independentes;
-   retenção sem exclusão;
-   mapas/geolocalização nos testes existentes.

------------------------------------------------------------------------

# 47. Homologação visual obrigatória

Ainda não foi concluída.

A ferramenta de navegador falhou repetidamente com erro de ACL no
ambiente de validação.

Isso significa que testes automatizados **não substituem inspeção
visual**.

Executar manualmente:

1.  pré-matrícula pública;
2.  CEP/endereço;
3.  mapa;
4.  escola principal/alternativas;
5.  protocolo;
6.  espera antes do deferimento;
7.  deferimento;
8.  turma;
9.  prazo;
10. liberação;
11. autenticação do responsável;
12. escolha ONLINE;
13. upload;
14. erros;
15. correção;
16. reenvio;
17. versões;
18. troca de modalidade;
19. PRESENCIAL;
20. entrega parcial;
21. entrega completa;
22. aprovação incompleta bloqueada;
23. documentação aprovada;
24. comprovar ausência de matrícula;
25. efetivar;
26. conferir matrícula;
27. conferir enturmação;
28. turma lotada;
29. duplicidade;
30. vencimento;
31. ausência de rejeição automática;
32. operador de outra escola;
33. desktop;
34. mobile;
35. mensagens/stepper;
36. download autorizado;
37. SMTP institucional;
38. canais externos quando configurados.

------------------------------------------------------------------------

# 48. Estado atual por área

  Área                                 Estado
  ------------------------------------ ---------------------------------------------
  Fluxo principal                      IMPLEMENTADO / TESTADO LOCALMENTE
  Pré-matrícula sem modalidade         IMPLEMENTADO
  Deferimento separado da matrícula    IMPLEMENTADO
  Liberação documental                 IMPLEMENTADA
  Modalidade após deferimento          IMPLEMENTADA
  ONLINE                               IMPLEMENTADO
  PRESENCIAL                           IMPLEMENTADO
  Triagem                              IMPLEMENTADA
  Categorias originais                 IMPLEMENTADAS
  Categorias adicionais                IMPLEMENTADAS
  Snapshot documental                  IMPLEMENTADO
  Ativação por processo                IMPLEMENTADA
  Aprovação documental                 IMPLEMENTADA
  Matrícula nativa explícita           IMPLEMENTADA
  Vencimento sem rejeição automática   IMPLEMENTADO
  Arquivos privados                    IMPLEMENTADO
  Auditoria                            IMPLEMENTADA
  Dashboard/CSV                        IMPLEMENTADO
  E-mail/fila                          IMPLEMENTADO LOCALMENTE
  SMTP institucional                   PENDENTE
  Infraestrutura SMS/WhatsApp          IMPLEMENTADA
  Provedor/ponte real SMS/WhatsApp     PENDENTE
  Entrega externa SMS/WhatsApp         NÃO HOMOLOGADA
  Relatório de retenção                IMPLEMENTADO
  Exclusão automática                  NÃO IMPLEMENTAR SEM POLÍTICA
  Política institucional de retenção   PENDENTE
  Leaflet/OSM                          IMPLEMENTADO LOCALMENTE
  Geocoder residencial                 DEPENDE DE INFRAESTRUTURA MUNICIPAL/PRÓPRIA
  Revisão visual                       PENDENTE
  Aceite municipal                     PENDENTE

------------------------------------------------------------------------

# 49. Pendências reais

## P0 --- produção/homologação

-   revisão visual desktop;
-   revisão visual mobile;
-   percurso integral em homologação;
-   SMTP institucional;
-   entrega externa de e-mail;
-   regras municipais reais;
-   aceite da Secretaria;
-   selecionar processos reais a ativar;
-   definir documentos reais por processo.

## P1 --- institucional/técnico

-   política aprovada de retenção/descarte;
-   eventual executor de descarte somente após política;
-   transferência/remanejamento;
-   inscrições concorrentes;
-   revisão administrativa de rejeições históricas afetadas pelo
    comportamento antigo;
-   integração/navegação documental nativa PMD quando necessária;
-   infraestrutura de tiles/geocoding;
-   HTTPS/CSP/CORS em homologação;
-   provedor/ponte/credenciais para SMS/WhatsApp;
-   requisitos institucionais dos canais externos.

## P2 --- evolução

-   análises históricas avançadas;
-   routing/mapa de itinerário caso solicitado;
-   demais melhorias não essenciais ao fluxo atual.

------------------------------------------------------------------------

# 50. Regras de segurança para futuras alterações

Não:

-   pedir modalidade na pré-matrícula;
-   escolher modalidade no deferimento;
-   usar fallback ONLINE;
-   liberar documentação em `WAITING`;
-   analisar documentação em `WAITING`;
-   matricular ao aprovar documentos;
-   rejeitar PMD automaticamente por prazo documental;
-   criar estado PMD desnecessário;
-   aceitar código documental que não pertença à solicitação;
-   alterar retroativamente snapshot documental;
-   reabrir legado em massa;
-   expor arquivo publicamente;
-   confiar somente no frontend;
-   habilitar canais externos sem configuração;
-   reenviar histórico sem corte;
-   considerar HTTP 2xx da ponte como entrega ao telefone;
-   excluir documento por relatório de retenção;
-   inventar prazo LGPD;
-   enviar endereço residencial ao Nominatim público;
-   depender de tiles OSM públicos como infraestrutura ilimitada;
-   executar `migrate:fresh`;
-   alterar migration histórica PMD sem decisão arquitetural;
-   substituir serviço nativo de matrícula.

------------------------------------------------------------------------

# 51. Regras para o Codex

Antes de qualquer alteração:

1.  ler este documento integralmente;
2.  identificar a seção afetada;
3.  inspecionar a implementação atual;
4.  preservar invariantes;
5.  preservar compatibilidade;
6.  não recuperar regra superada de documentos antigos;
7.  criar/ajustar testes;
8.  executar regressão adequada;
9.  registrar migration apenas quando necessária e aditiva;
10. não apagar dados;
11. diferenciar claramente:

-   implementado;
-   testado;
-   homologado;
-   ativado em produção.

Ao finalizar uma mudança:

1.  atualizar este documento;
2.  atualizar `CHANGELOG.md`;
3.  registrar testes/evidências;
4.  listar pendências externas;
5.  não declarar homologação sem evidência.

------------------------------------------------------------------------

# 52. Critério de aceite funcional

Demonstrar:

``` text
pré-matrícula
→ deferimento
→ liberação
→ modalidade pelo responsável
→ entrega
→ triagem
→ aprovação SEM matrícula
→ efetivação explícita
→ matrícula nativa
→ enturmação nativa
```

com:

-   autorização;
-   escopo escolar;
-   auditoria;
-   prazo;
-   documentos configurados;
-   categorias adicionais quando aplicável;
-   comunicação;
-   idempotência;
-   armazenamento privado;
-   compatibilidade PMD/i-Educar.

------------------------------------------------------------------------

# 53. Critério de produção

Além do aceite funcional:

-   homologação visual;
-   ambiente de homologação;
-   SMTP institucional;
-   entrega externa;
-   processos reais ativados;
-   documentos reais configurados;
-   regras municipais;
-   política LGPD;
-   infraestrutura geográfica;
-   canais externos homologados se forem ativados;
-   aceite institucional.

------------------------------------------------------------------------

# 54. Política de documentação do repositório

Este arquivo deve permanecer como:

``` text
BC-EDUCAR-CONTEXTO-MESTRE.md
```

e ser a fonte única de contexto.

Pode existir:

``` text
CHANGELOG.md
```

exclusivamente como histórico cronológico de alterações.

Não recriar:

``` text
ONDA-X.md
PENDENCIAS-X.md
AUDITORIA-X.md
CONTEXTO-MESTRE-2.md
AJUSTE-FLUXO-X.md
VALIDACAO-X.md
```

como novas fontes paralelas de verdade.

Resultados temporários de execução podem ficar em logs/commits e ser
resumidos aqui.

------------------------------------------------------------------------

# 55. Nota final

O estado técnico mais recente demonstra implementação e testes locais
substanciais, mas **não representa ativação externa, política
institucional aprovada ou homologação municipal**.

Qualquer funcionalidade que dependa de:

-   decisão da Secretaria;
-   provedor externo;
-   credencial;
-   política LGPD;
-   infraestrutura municipal;
-   aceite visual/operacional;

deve permanecer desativada ou tratada como pendente até que essas
informações sejam formalmente definidas.

**Este documento deve ser atualizado antes de qualquer documento
histórico ser usado para orientar novas implementações.**
