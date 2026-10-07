> Documento histórico V2, preservado para consulta. A fonte normativa vigente é [BC-EDUCAR-CONTEXTO-MESTRE-V3.md](BC-EDUCAR-CONTEXTO-MESTRE-V3.md). As declarações de precedência abaixo pertencem à versão histórica.

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

No cadastro público, o endereço declarado pelo responsável é suficiente.
Não exigir confirmação de localização nem coordenadas para prosseguir.

O centro padrão do mapa nunca pode ser salvo automaticamente como
residência.

Coordenadas são metadados opcionais. O cadastro não solicita localização do dispositivo
e não substitui endereço declarado pelo centro do mapa.

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
-   transferência/remanejamento: integração técnica implementada; habilitação institucional e homologação pendentes;
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

## Continuação — diagnóstico operacional — 05/10/2026

Implementado o comando somente leitura `bc:check-readiness`, em `app/Console/Commands/CheckBcReadiness.php`. Ele verifica configuração de HTTPS da aplicação, SMTP/TLS, geocoder, tiles próprios, referência/prazo de retenção e canais SMS/WhatsApp com endpoint HTTPS e corte de data. Não revela endpoints, credenciais, tokens ou dados pessoais e não envia mensagens, consulta provedores ou altera banco/arquivos.

Execução local:

```sh
docker exec ieducar-php php artisan bc:check-readiness
```

O retorno 1 indica configuração pendente/incompleta; retorno 0 indica somente os requisitos de configuração verificados. Não certifica produção, entrega, identidade municipal do provedor, capacidade, CSP/CORS, política institucional ou aceite. Canais opcionais desativados são apresentados como DESATIVADO. A referência de retenção não autoriza descarte.

Resultado local: HTTPS da aplicação, SMTP institucional/TLS, geocoder, tiles próprios e política de retenção pendentes. SMS/WhatsApp desativados. Nenhuma configuração externa foi ativada.

Validação desta etapa: BcReadinessTest, 2 testes aprovados e 9 asserções; Pint aplicado aos 2 arquivos. Sem migration. Resultados não somados aos checkpoints anteriores. O arquivo canônico ausente foi restaurado a partir do V2 preservado.

Homologação visual e institucional continuam pendentes. O diagnóstico auxilia a configuração e não substitui o roteiro de aceite.


## Ajustes solicitados no cadastro e acompanhamento — 05/10/2026

Requisito atual aprovado pelo usuário: o endereço registrado é suficiente para o cadastro público. Este requisito substitui a antiga exigência de confirmação de localização descrita no histórico.

Implementado:
- Cadastro de endereço sem mapa de confirmação, solicitação de localização do dispositivo ou busca automática residencial. Campos de endereço continuam validados e a consulta de CEP mantém preenchimento manual em caso de falha.
- Coordenadas opcionais nos inputs e resultados GraphQL. Novos endereços sem coordenadas são armazenados com latitude/longitude nulas, sem atribuir o centro do mapa à residência.
- Até duas escolas por nova inscrição: principal e alternativa; terceira opção retirada da interface e recusada na API. Inscrições históricas e configurações dos processos não foram convertidas.
- Seleção mostra todas as unidades elegíveis com vagas na série/turno selecionados, com pins azuis e nomes; mapa ajusta o enquadramento às unidades. Na lista de espera, as alternativas respeitam a compatibilidade de série/turno. Rematrícula restrita à escola anterior mantém essa restrição.
- Sem coordenadas residenciais, as opções continuam disponíveis; não apresentar pin de residência ou distância calculada a partir de um centro fictício.
- Campo de e-mail obrigatório e validado para o responsável, inclusive quando o processo não o configurava. Não duplicar o campo existente. O e-mail é persistido no cadastro nativo PMD e novas inscrições sem contato válido são recusadas antes de criar registros.
- E-mails do fluxo de matrícula identificam o protocolo. Avisos textuais/HTML têm cabeçalho explícito; código de acesso também inclui protocolo e link de acompanhamento. O e-mail nativo de confirmação já apresenta os protocolos.
- Botões escolar BC e modal nativo PMD usam somente “Deferir pré-matrícula”. A ação mantém o fluxo documental após deferimento, sem criar matrícula ou escolher modalidade.

Validação desta atualização:
- Backend: 4 suítes, 32 testes aprovados, 354 asserções. Cadastro público sem coordenadas, consulta GraphQL com campos nulos, duas escolas, rejeição da terceira, e-mail válido, protocolo no código de acesso e percurso até matrícula nativa cobertos.
- Frontend: 10 arquivos de teste, 23 testes aprovados, incluindo escolas além do antigo raio e rematrícula restrita.
- Build de produção concluído e assets publicados no ambiente local.
- Schema GraphQL válido; formatação PHP aplicada.
- SMTP local: 9 tipos de aviso capturados com HTML/texto/protocolo/link; retry/backoff/recuperação validados, sem duplicidade (10 mensagens sintéticas no total). Transação do banco testing revertida.
- Nenhuma migration necessária: o banco já permite coordenadas nulas.
- HTTP: portal, bundle, configuração municipal e quatro páginas para administrador e operador escolar responderam 200. Pint aprovado para os 5 arquivos PHP alterados. Homologação visual não foi realizada nesta etapa.

## Continuação — revisão histórica e proposta de movimentação nativa — 05/10/2026

### Revisão histórica implementada, somente leitura

Comando: bc:historical-expiry-review [--process=ID] [--school=ID] [--json].

O relatório conta inscrições PMD rejeitadas com ao menos um indício documental: estado DEADLINE_EXPIRED, vínculo BC PRAZO_EXPIRADO/NAO_COMPARECEU ou evento sistêmico DOCUMENT_DEADLINE_EXPIRED. Agrupa IDs de processo/escola e contagens, sem nomes, e-mails, protocolos, motivos ou arquivos. Eventos repetidos não multiplicam a contagem. Os filtros exigem IDs positivos.

A classificação INDICATION_REQUIRES_MANUAL_REVIEW não confirma que a rejeição foi causada pelo comportamento antigo. Uma rejeição administrativa legítima também pode coexistir com prazo vencido. O relatório não restaura inscrições, não muda prazos, não executa descarte nem altera matrícula. A revisão das decisões e qualquer ação administrativa continuam dependentes de avaliação autorizada.

### Proposta histórica de transferência/remanejamento — autorizada e implementada

Problema: RegistrationWorkflow::finalize recusa toda matrícula ativa antes de chamar o serviço PMD, impedindo também movimentações previstas pelo serviço nativo quando allow_transfer_registration estiver habilitada.

Escopo da proposta, aplicado após autorização explícita do usuário:
1. Consultar e bloquear as matrículas ativas do aluno/ano sob o bloqueio do aluno e da turma já existente.
2. Preservar recusa por padrão. Delegar ao serviço nativo apenas para solicitação vinculada ao PMD, flag explicitamente habilitada, uma única matrícula em andamento e mesma série.
3. Manter autorização escolar, documentação aprovada, compatibilidade de turma/escola/ano/turno, capacidade e consistência de aluno retornado.
4. Executar transferência entre escolas/remanejamento entre turmas exclusivamente por EnrollmentService/RegistrationTransferService nativos, somente na efetivação explícita.
5. No remanejamento, conservar a matrícula reutilizada como ultima_matricula. Na transferência, manter a nova como última, ajustando demais somente após sucesso do serviço, na mesma transação.
6. Auditar ENROLLMENT/TRANSFER/RELOCATION e ID da matrícula anterior; preservar idempotência.
7. Antes de disponibilizar, testar em testing com rollback: flag desativada/habilitada, mesma turma, múltiplas matrículas, série divergente, escola não autorizada, capacidade, falha do serviço e repetição.

Arquivo afetado: app/EnrollmentRequests/RegistrationWorkflow.php, método finalize. Sem ativar flags, migrations, conversão histórica em massa ou configuração dos processos. Testes de movimentação executados; resultados na atualização abaixo.

A revisão automática rejeitou a escrita por risco de integridade em matrículas ativas e transferências, considerando que a instrução geral de continuar não autoriza especificamente essa alteração. Esse bloqueio foi resolvido pela autorização explícita posterior do usuário: “autorizado todas as proximas implementações necessarias”. A integração foi aplicada e testada.

Validação desta etapa: HistoricalExpiryReviewTest e BcReadinessTest, 4 testes aprovados, 27 asserções (15,67 s); Pint aprovado em 4 arquivos. Execução local do relatório: 24 candidatos em 7 grupos, sem alterações. Diagnóstico passa a tratar geocoder vazio como DESATIVADO e opcional, coerente com o cadastro por endereço; geocoder configurado inválido permanece erro. Sem migrations. Infraestrutura, comunicação, retenção institucional e aceite continuam pendentes.


## Transferência e remanejamento nativos implementados — 05/10/2026

Na efetivação explícita, solicitações vinculadas ao PMD podem usar a movimentação nativa quando allow_transfer_registration já estiver habilitada. Exige uma única matrícula ativa em andamento, mesma série e autorização do operador para a escola de origem e destino. As validações documentais, de turma, turno, ano e capacidade permanecem obrigatórias.

Transferência chama o serviço nativo, encerra a matrícula anterior e cria a nova; remanejamento reutiliza a matrícula existente e movimenta a enturmação. ultima_matricula é ajustada somente após sucesso. A transação reverte integralmente falhas, inclusive cancelamento intermediário da enturmação. Repetir a efetivação retorna a matrícula existente. Auditoria registra ENROLLMENT, TRANSFER ou RELOCATION e a matrícula anterior.

A configuração municipal não foi ativada automaticamente. Solicitações BC sem vínculo PMD, série divergente, matrículas concorrentes, turma lotada ou escola de origem não autorizada continuam recusadas. Não há migration ou conversão histórica.

Validação específica: NativeEnrollmentMovementTest, 8 testes aprovados, 59 asserções; Pint aprovado em 2 arquivos. Testes executados em banco testing, com rollback. Homologação operacional e habilitação municipal permanecem pendentes, assim como SMTP institucional, infraestrutura HTTPS/tiles e política aprovada de retenção.

Regressão final desta implementação: 8 suítes, 37 testes aprovados e 360 asserções (42,60 s), cobrindo movimentação nativa, separação de etapas, cadastro até matrícula, prazos, catálogo documental, compatibilidade histórica e diagnóstico. Resultados deste checkpoint não somados aos anteriores.


## Atualização visual da Matrícula Digital — 05/10/2026

Aplicado às telas do responsável e da administração escolar: paleta azul alinhada ao PMD (#0072ff/#003473), cabeçalho leve, navegação com etapa administrativa ativa, cartões e campos arredondados, indicadores reorganizados e três etapas de acompanhamento com estados distintos. O acesso por protocolo/e-mail e código recebeu blocos separados; a escolha de entrega destaca a opção selecionada. Layout adapta colunas e etapas em telas pequenas, mantém tabelas com rolagem dentro do cartão e oferece foco visível e respeito à preferência por movimento reduzido. Sem dependências externas novas.

Estilos com versão para atualização de cache; removido CSS inline conflitante do layout administrativo. Formulários, rotas, permissões, prazos e regras de efetivação preservados. Também atualizado teste com expectativa antiga do botão de deferimento para o texto solicitado anteriormente.

Validação: 24 testes do acesso documental e separação de etapas aprovados; 6 testes do painel aprovados após corrigir a expectativa antiga. Templates Blade compilados e HTTP 200 no acesso do responsável/estilos, portal, configuração e quatro páginas para administrador e operador. Conferência visual desktop/mobile permanece pendente: a ferramenta de navegador falhou na inicialização com helper_unknown_error: apply deny-read ACLs. Não afirmar homologação visual com base em HTTP ou testes.


## Correção do erro ao designar turma — 05/10/2026

Causa identificada no fluxo acceptPreRegistrations/PmdIntake: CPF formatado era gravado diretamente em cadastro.fisica.cpf (numérico), provocando SQLSTATE 22P02 e Internal server error ao deferir uma inscrição sem vínculo prévio com pessoa nativa. A importação agora normaliza o CPF para somente dígitos tanto na busca quanto na criação de aluno/responsável; ausência de CPF permanece nula. O cadastro PMD mantém sua apresentação original. Não há migration nem alteração em massa de dados. Deferimento continua liberando documentação sem criar matrícula.

Incluídos testes de regressão para pessoas novas com CPF formatado iniciado em zero e sem CPF, verificando estado documental, ausência de matrícula e repetição idempotente. Formatação PHP aplicada. Testes executados em testing com rollback.


## Triagem exclusiva do operador e ações documentais explícitas — 05/10/2026

Retirados da triagem a orientação de acesso por protocolo/e-mail e o link de navegação para o responsável. Todas as rotas administrativas bc/matriculas exigem autenticação i-Educar, usuário/servidor ativos e perfil administrativo, institucional ou escolar; o escopo de escolas continua aplicado. A sessão documental do responsável não concede acesso administrativo.

Cada documento recebido no prazo e elegível para análise apresenta botões Aprovar documento, Em análise, Solicitar correção e Rejeitar documento. Pendentes informam que aguardam entrega. Aprovar documentação e Efetivar matrícula também são ações explícitas e aparecem nas etapas correspondentes. O backend mantém validações, motivo obrigatório para pendências e separação entre aprovação documental e matrícula.

Validação: 9 testes do painel aprovados (155 asserções), incluindo bloqueio da sessão do responsável, operador inativo, aprovação individual e exibição da efetivação; 12 testes do acesso documental também aprovados. Pint aprovado em 3 arquivos; templates compilados, cache de views limpo e estilos versionados. Sem migrations.


## Pré-matrícula exclusivamente na interface original — 05/10/2026

Correção de escopo solicitada pelo usuário: a pré-matrícula e seu deferimento pertencem à interface original do módulo PMD. Removidos o template administrativo adicional intake.blade.php, os métodos de página/deferimento do controlador BC e o endpoint POST /bc/matriculas/pmd/{pmd}. O endereço GET antigo /bc/matriculas/pmd mantém somente redirecionamento para /pre-matricula-digital/inscricoes. A navegação aponta diretamente para a lista original.

A integração interna PmdIntake continua sendo chamada pela ação original do módulo para liberar a etapa documental; não representa uma página alternativa. A triagem administrativa permanece exclusiva do operador para análise documental posterior, com efetivação explícita após aprovação. Nenhuma inscrição, documento, histórico ou matrícula foi removido. Este requisito substitui as descrições históricas da página administrativa adicional de pré-matrículas. Testes e script HTTP ajustados ao fluxo original.


## Aprovação final efetiva a matrícula — 05/10/2026

Requisito posterior do usuário: a aprovação final da documentação deve tornar o aluno matriculado no i-Educar. A ação administrativa approve agora executa approveAndEnroll em transação única: valida/aprova documentação e efetiva matrícula/enturmação pelos serviços existentes. Falha de vaga, turma ou outra validação reverte também a aprovação; repetição não duplica matrícula. Aprovar um documento individual continua sendo apenas a conferência desse documento.

Na tela original de detalhe da triagem, bloco em destaque acima da lista apresenta Aprovar documentação e matricular e Recusar documentação (motivo obrigatório). Aprovação exige deferimento original, escolha de entrega e documentos obrigatórios aprovados/recebidos no prazo; sem escolha o botão fica visível e desabilitado com orientação. A lista de documentos adapta as ações para telas pequenas. A pré-matrícula continua exclusivamente no módulo original; não foi criada nova página.

Este comportamento substitui a exigência histórica de dois cliques separados na interface escolar para aprovação documental e efetivação. Permanecem eventos distintos de aprovação/matrícula na mesma transação. Validação: 11 testes do painel aprovados, 168 asserções, incluindo matrícula e enturmação nativas, idempotência, rollback por turma lotada e bloqueio do responsável; Pint aprovado em 3 arquivos. Cache Blade limpo, CSS versionado, sem migrations. Homologação visual permanece pendente.


## Resumo documental somente após deferimento — 05/10/2026

O resumo Documentação foi retirado do início do modal original de pré-matrícula. Aparece abaixo dos dados apenas na consulta SHOW após liberação documental; não aparece com inscrição WAITING nem durante ações ACCEPT, REJECT ou SYNC, mesmo que um resumo histórico indique liberação. Antes do deferimento, o componente não renderiza bloco documental, prazo ou contagens. Mensagem pós-deferimento atualizada para a aprovação final com matrícula no i-Educar. Nenhuma página nova ou alteração de regras/prazos. Teste cobre ocultação durante análise inicial com resumo antigo liberado, além da ausência de liberação.


## Acesso à triagem no menu original PMD — 05/10/2026

Adicionado Triagem de documentos após Inscrições no menu interno desktop e mobile do módulo original, com destino /bc/matriculas e indicação de etapa posterior ao deferimento. O link usa navegação direta para a área administrativa e só aparece com sessão autenticada; não foi incluído no menu público de responsáveis. As permissões de operador e escopo escolar continuam verificadas no backend. Sem nova página de pré-matrícula.


## Identidade municipal em todo o sistema — 05/10/2026

Brasão local de Balneário Camboriú aplicado ao login, layouts autenticados i-Educar (base/default), cabeçalhos PMD e área documental (operador/responsável), configuração padrão PMD e favicon. A URL legada intranet/imagens/brasao-republica.png agora entrega o mesmo brasão municipal convertido para PNG, preservando transparência; imagens municipais possuem nomes sem caracteres especiais. Arquivos oficiais locais reutilizados, sem geração de novo brasão.

Relatórios: logo padrão atualizado para brasao-balneario-camboriu.png; fábrica PHPJasper resolve esse nome pelo arquivo versionável public/img, evitando depender do diretório ReportLogos ignorado pelo Git. Também disponibilizado no diretório local de logos. Servidor remoto de relatórios, quando utilizado, ainda precisa dispor do arquivo municipal; nenhuma publicação em servidor externo ou geração completa de relatório foi realizada nesta etapa.

Build PMD concluído e assets publicados localmente. Cache de configuração/views limpo; Pint aplicado em 3 arquivos PHP. HTTP das imagens PNG, endereço antigo, favicon, login e portal documental verificados. Homologação visual completa permanece pendente.


## Resumo por escola recolhível — 05/10/2026

Resumo por escola no painel de triagem/matrículas convertido em sanfona nativa details/summary, fechada por padrão. O controle Ver resumo/Ocultar resumo abre e fecha a tabela com mouse ou teclado, mantendo filtros, contagens e escopo escolar existentes. CSS versionado e cache Blade limpo; sem JavaScript adicional ou alteração de dados.


## Painel documental com menos informação simultânea — 05/10/2026

Reduzidos os cartões visíveis para quatro na triagem (solicitações, acompanhamento, prontas para matrícula, efetivadas) e dois em matrículas. Total no escopo vira contexto do primeiro cartão. Situações, modalidades e prazos detalhados ficam em sanfona fechada por padrão, com rótulos legíveis. Filtros recolhíveis, abertos automaticamente quando ativos; exportação em ação única junto ao título. Prazos urgentes permanecem visíveis em aviso compacto quando existem. Resumo por escola mantém sanfona. Sem alterar consultas, dados, filtros, CSV ou permissões.

Validação: 4 testes aprovados, 121 asserções (contagem documental, filtros/escopo, CSV e proteção da exportação). CSS versionado, cache de views limpo. Conferência visual no navegador permanece pendente por indisponibilidade anterior da ferramenta.


## Controles do painel em uma única linha — 05/10/2026

Detalhamento dos indicadores, Filtros e Resumo por escola passam a ser três botões lado a lado. Cada um abre/recolhe seu painel abaixo da linha e sinaliza estado com aria-expanded; filtros ativos começam abertos. Em telas estreitas, a linha permite rolagem horizontal sem empilhar os controles. Esta atualização substitui as três sanfonas com cabeçalhos em linhas separadas. Dados, filtros e permissões preservados; CSS versionado e cache de views limpo.


## Conferência individual e orientação de correção — 05/10/2026

Aprovação da documentação e matrícula posicionada abaixo da análise individual e do recebimento de documentos, antes da auditoria. Cada documento elegível tem ações individuais de aprovar, em análise, solicitar correção e rejeitar. A recusa de um arquivo não indefere toda a inscrição nem altera documentos já aprovados.

Operador pode selecionar arquivo ilegível, documento incompleto, tipo incorreto, documento de outra pessoa, documento fora da validade, dados divergentes ou outro motivo, e complementar detalhes. Opções conhecidas produzem orientação de reenvio; Outro motivo exige descrição. API valida opção e limite total de 1000 caracteres; integrações que já enviam motivo livre continuam aceitas. Motivo armazenado no documento/auditoria, exibido ao responsável em destaque. Correção ou recusa disponibiliza envio de novo documento dentro do prazo; versão anterior e motivo preservados. Recusa individual agora também cria aviso CORRECTION na fila existente, com protocolo e link de acompanhamento; não houve envio externo real.

Validação: 30 testes distintos aprovados nas suítes de painel, documentos do responsável e avisos (incluindo reexecução do novo cenário após ajuste da fixture). Teste completo verifica ordem das seções, motivo preset+detalhes, recusa individual, aviso, exibição ao responsável, reenvio com versão e preservação de outros aprovados. Opção desconhecida e Outro motivo sem descrição recusados. Pint aplicado em 4 arquivos, cache Blade limpo, estilos versionados. Sem migrations. Este posicionamento substitui a descrição histórica do bloco de aprovação acima da lista.


## Prazos de entrega e reentrega configuráveis — 05/10/2026

Em Triagem documental → Configuração, o administrador/operador institucional autorizado define por processo o prazo total de entrega em dias após o deferimento e o prazo de reentrega após recusa ou solicitação de correção. Ambos aceitam de 1 a 365 dias corridos e terminam às 23h59 do último dia, igualmente para atendimento online e presencial. O prazo em dias prevalece sobre a data fixa alternativa. Sem configuração do prazo inicial, mantém-se o padrão de sete dias; o padrão de reentrega é três dias.

A liberação registra o prazo total na solicitação. Mudança posterior na configuração não altera prazos já concedidos. Cada recusa/correção registra um prazo próprio para aquele documento, contado da análise, e sua versão substituta herda esse prazo. Assim é possível corrigir um documento após o vencimento total, sem estender a entrega dos outros. Não é possível substituir documentos depois do prazo individual. Aprovações anteriores e histórico de versões são preservados.

O responsável vê motivo e data limite de reentrega por documento. O aviso de correção contém protocolo, link de acompanhamento e o prazo individual registrado no evento. Expiração automática respeita reentrega obrigatória ainda aberta; recebimentos tempestivos pelo prazo individual continuam elegíveis para análise e aprovação. Após aprovação de todos os obrigatórios, a ação final efetiva matrícula no i-Educar.

Migration 2026_10_05_220000 aplicada no ambiente local e no banco testing: configurações documentation_delivery_days/documentation_retry_days em processes e delivery_deadline no documento BC. Não há alteração retroativa dos prazos nem ativação automática de processos.

Validação desta atualização: 56 testes distintos aprovados entre as suítes de configuração, separação de etapas, painel, documentos do responsável, e-mail e canais de mensagem. Inclui reentrega após prazo total em ambas as modalidades, herança do prazo na nova versão, bloqueio após prazo individual, expiração, preservação do prazo inicial e matrícula nativa após correção. Avisos validados com transporte falso, sem envio externo real. Pint aplicado aos arquivos PHP alterados e cache de views limpo.


## Endereços e geografia das escolas — 05/10/2026

Corrigidas as 15 unidades existentes: endereços principais, bairros, cidade e pontos distintos, substituindo a coordenada única do demo. i-Educar e PMD usam os mesmos pontos nativos; seeder cria endereços do catálogo revisado. Comando bc:sync-school-locations oferece prévia e aplicação transacional com backup. Mapas de consulta e edição enquadram marcadores. 2 testes PHP/111 asserções e 24 testes frontend aprovados; build publicado. NEI Pioneiros: endereço atualizado, confirmação da entrada pendente. Divergências do Tomaz Garcia e Cristo Luz documentadas. Fontes, coordenadas e limitações em [ESCOLAS-ENDERECOS-MAPAS.md](ESCOLAS-ENDERECOS-MAPAS.md).


## 05/10/2026 — Perfil do responsável: base persistente e aprovação separada

Referência: BC-EDUCAR-PERFIL-RESPONSAVEL-FICHA-CADASTRAL.md.

Implementado:
- Matriz técnica de campos, entidades e serviços em MATRIZ-MAPEAMENTO-CADASTRAL-IEDUCAR.md antes de alterar o formulário.
- Perfil persistente criado após confirmação de protocolo/e-mail por OTP; vínculos individuais confirmados e auditados.
- Recuperação sem senha por e-mail para perfis previamente confirmados. Somente inscrições previamente vinculadas aparecem; não há vinculação automática por e-mail/CPF/nome.
- Página /matricula-digital/perfil com dependentes das inscrições, escolha da inscrição, consulta de matrículas efetivadas e saída.
- Aprovação documental separada da efetivação explícita: o endpoint approve não cria matrícula; finalize permanece transacional e idempotente. Esta regra substitui o botão combinado descrito anteriormente.

Pendências do documento, ainda não concluídas:
- Perfil provisório desde o primeiro envio; edição de meus dados e gestão segura de outros responsáveis.
- Cadastro declarativo de dependentes, revisão/reutilização entre processos e formulário cadastral ampliado.
- Conferência de dados versus comprovantes, comparação com cadastro oficial e correções auditadas por campo.
- Transferir criação de pessoa/aluno do PmdIntake para a efetivação. Atualmente o deferimento ainda cria pessoa/aluno, sem matrícula; não considerar esse requisito atendido.
- Central de comunicações, linha do tempo ampliada e histórico/retenção do perfil.
- Validação visual em desktop/celular.

Não foi criada ficha oficial paralela. E-mails de teste foram interceptados com Mail::fake; nenhum envio externo foi necessário.


## 05/10/2026 — Continuação: consolidação na efetivação e conferência cadastral

Esta atualização substitui a pendência anterior sobre criação antecipada de pessoa/aluno.

- Novos deferimentos PMD criam somente solicitação documental, com referências nativas de aluno/responsável opcionais. Nenhuma pessoa, aluno ou matrícula é criada no deferimento ou na aprovação geral.
- NativeStudentConsolidation reutiliza FindOrCreatePerson do PMD para criar os cadastros nativos somente dentro da transação de Efetivar matrícula. Endereços, RG/certidão e telefones são consolidados para novas pessoas; a matrícula/enturmação continua no serviço original.
- Identidade por CPF normalizado exige correspondência de nome/nascimento e unicidade. Nome/nascimento ou external_person_id inferido no cadastro público não autorizam associação automática. Divergências são bloqueadas.
- Resolução manual de identidade: administrador/institucional confirma código nativo com justificativa. Institucional depende de aluno vinculado a escola/solicitação de seu escopo, e responsável vinculado a esse aluno. Não permite pesquisa livre por responsáveis.
- Cadastros existentes são preservados. Não há sobrescrita automática de nome, documentos, contatos/endereço ou filiação. Divergência de vínculo oficial bloqueia efetivação. A associação de filiação ausente fica auditada.
- Ficha em /matricula-digital/ficha, mediante perfil confirmado, edita nome, CPF, nascimento, sexo, RG, certidão, telefone/celular do aluno. Exibe responsável/e-mail/endereço já declarados. Ainda não é o formulário ampliado completo.
- Após deferimento, atualização exige solicitação de correção da escola; antes dele, o responsável pode revisar. Alteração cria snapshot PMD próprio para preservar inscrições que compartilham a pessoa e volta a PENDING.
- Novas solicitações exigem Dados conferem antes de aprovação geral/finalização. Solicitações históricas sem revisão permanecem compatíveis; abrir uma revisão passa a exigir sua conclusão.
- Operador registra Dados conferem ou Dados divergentes com motivo, e consulta histórico de campos antes/depois, ator, data e motivo na própria triagem. Auditoria sensível permanece em tabela restrita, sem logs técnicos gerais.
- Antes da matrícula, o operador pode reabrir a conferência cadastral com motivo. Retira a aprovação geral, preserva arquivos/aprovações individuais e prazos; não reabre matrícula já efetivada.
- Falha de vaga/identidade/serviço nativo desfaz a transação inteira, incluindo pessoas/aluno/eventos. Repetir efetivação retorna a matrícula existente.
- Migrations 231000 e 232000 aplicadas nos bancos local e testing.

Continuam pendentes: cadastro de dependentes independente de inscrição; reutilização/revisão entre processos; perfil provisório desde primeiro envio e edição de contatos do responsável com verificação; formulário completo de campos adicionais/filiação/endereço; comparação e atualização autorizada campo a campo de cadastros oficiais existentes; central de comunicações/linha do tempo ampliada; retenção/histórico completo do perfil e validação visual em desktop/celular. A implementação integral do documento ainda não foi declarada concluída.

Validação desta continuação: 88 testes distintos passaram (fluxo ponta a ponta, ficha, perfis, documentos, matrícula/transferência, prazos, resumos e notificações). Pint validou os 13 arquivos PHP alterados; portal público respondeu HTTP 200. A reabertura cadastral e a resolução após falha de identidade foram verificadas adicionalmente nos testes da ficha. Testes HTTP renderizaram as telas novas; inspeção visual em navegador ainda pendente.


## Perfil/Ficha V2 — aprovação digital e conferência física (05/10/2026)

Implementado o delta autorizado da V2. Novas liberações documentais recebem workflow_version=2. A migração preserva registros existentes como versão 1, sem converter matrículas históricas.

- Aprovação digital valida dados e documentos, é persistida e tenta a integração em transação separada. Falhas preservam a aprovação, registram categoria/classe técnica sem SQL, documentos ou dados pessoais, e oferecem reprocessamento pelo operador autorizado. O PMD só passa a IN_CONFIRMATION após integração concluída.
- Consolidação reutiliza pessoa/aluno/responsável com resolução segura de identidade. A criação intermediária reutiliza o serviço PMD com PRE_REGISTRATION=11; não cria enturmação. intermediate_registration_id é separado de registration_id definitivo, evitando apresentar pré-matrículas como matrículas concluídas nos relatórios/perfil.
- Conferência física individual por documento, mais identificação/ficha, registra operador, data, decisão, motivo e prazo. Divergência bloqueia a confirmação, mantém o vínculo 11 e aparece ao responsável com orientação de regularização. Divergência cadastral reabre a correção da ficha e exige nova aprovação digital, preservando aluno/vínculo intermediário.
- Configuração do processo permite prazo físico inicial e prazo de regularização, de 1 a 365 dias (padrões iniciais 7 e 3). Prazo inicial é copiado na integração e não reinicia por mudança de configuração ou reprocessamento. Conferência após vencimento exige regularização motivada; documentos já conferidos no prazo continuam elegíveis para conclusão posterior.
- Após todos os originais obrigatórios e cadastro conferidos, ação explícita da escola promove a matrícula existente pelo RegistrationService::updateStatus para ONGOING, enturma pelo EnrollmentService::enroll e conclui o PMD em ACCEPTED. Transação e bloqueios protegem idempotência; incompatibilidade, pendência ou turma cheia mantêm a situação intermediária.
- Cancelamento/indeferimento da solicitação intermediária usa cancelRegistration nativo, sem apagar pessoa/aluno. Vínculo nativo 11 existente compatível e ainda não associado pode ser reutilizado; ambiguidade ou vínculo de outra solicitação impede associação automática.
- Aviso de apresentação física usa fila existente e contém protocolo/prazo/acesso; acompanhamento possui etapa física e histórico com rótulos em português. Nenhum SMTP real foi ativado.
- Matrícula ativa no mesmo ano continua exigindo tratamento de movimento no fluxo nativo. A confirmação V2 não transfere nem remaneja automaticamente uma matrícula oficial preexistente; solicitações históricas mantêm seus serviços e testes de transferência/remanejamento.

A alteração nativa localizada é packages/portabilis/pre-matricula-digital/src/Services/EnrollmentService.php: preRegister reutiliza createRegistration com situação explícita, sem chamar createEnrollment. Essa adaptação deve acompanhar atualizações do pacote.

Limites de aceite: a revisão visual desktop/mobile foi tentada, mas o navegador de automação não iniciou por erro do sandbox Windows (apply deny-read ACLs). Testes HTTP verificam renderização das páginas; revisão visual permanece pendente. Pendências anteriores do perfil/ficha (dependente reutilizável em novo processo, ficha completa e revalidação de contatos) permanecem registradas; este delta não declara essas partes concluídas.


Validação final do delta V2: **77 testes aprovados, 897 assertions**, cobrindo PhysicalConfirmation (9), RegistrationDashboard (15), RegistrationStageSeparation/compatibilidade versão 1 (15), DeclaredStudentData (4), GuardianDocument (12), GuardianProfile (2), PmdDocumentConfiguration (4), NativeEnrollmentMovement/legado (8), GuardianRegistrationNotice (5), RegistrationEndToEnd (3). Pint --test aprovado em 18 arquivos PHP; migração 233000 aplicada no banco local e no banco testing; portal /matricula-digital respondeu HTTP 200. Não foi efetuada confirmação de matrícula real nem envio de e-mail real durante a verificação.


## Implantação local da V2 — 05/10/2026

Implantação solicitada pelo usuário e aplicada ao Docker local em http://localhost:8080. Banco verificado com migrate --force, sem migrações pendentes. Caches de configuração, rotas e views limpos; PHP-FPM, Nginx, Horizon e rotina documental reiniciados para carregar a versão atual. Configurações e dados existentes preservados.

Verificação após reinício: /matricula-digital e /pre-matricula-digital responderam HTTP 200; /login respondeu HTTP 200; /bc/matriculas sem autenticação redirecionou para /login, que respondeu HTTP 200. Serviços ativos. A evidência automatizada da implementação permanece em 77 testes/897 assertions; não houve mudança de código nesta implantação.


## Levantamento de pendências no código — 05/10/2026

Auditoria registrada em [LEVANTAMENTO-PENDENCIAS-CODIGO-2026-10-05.md](LEVANTAMENTO-PENDENCIAS-CODIGO-2026-10-05.md). Cinco diagnósticos transacionais no banco testing (26 assertions) reproduziram: enturmação nativa antecipada, promoção nativa antecipada, indeferimento original sem sincronização BC/cancelamento intermediário, correção cadastral após prazo físico de regularização e ausência de turno na matrícula intermediária. Esses diagnósticos comprovam lacunas; não são testes de aceite aprovando as regras. Nenhuma correção de produção ou alteração de dados reais foi feita nesta auditoria.

O relatório também distingue funcionalidades incompletas de perfil/ficha, preparação de produção, catálogo municipal, validação visual e necessidade de versionamento reproduzível das adaptações do pacote. A implantação local e as migrations aplicadas permanecem confirmadas; o fluxo V2 ainda exige as correções prioritárias identificadas antes do uso operacional amplo.


## Correções do levantamento e preparação de versionamento — 06/10/2026

Implementadas as cinco lacunas reproduzidas no levantamento: bloqueio de promoção/enturmação nativa antes da conferência física; sincronização do indeferimento original e em lote com encerramento BC/cancelamento intermediário; bloqueio de correção cadastral após prazo de regularização, com reabertura motivada pelo operador; turno validado no vínculo intermediário e backfill restrito a vínculos V2 compatíveis.

O monitor bc:monitor-physical-deadlines prepara lembretes e avisos de vencimento por item/prazo, com idempotência e descarte de avisos desatualizados. Não cancela solicitações automaticamente. E-mail e conectores SMS/WhatsApp usam o prazo físico correto. Falha nativa de enturmação desfaz promoção e confirmação física na mesma transação.

Validação: 82 testes/927 assertions na regressão e 21 testes/180 assertions no fluxo físico (nove casos repetidos da regressão), totalizando 94 casos distintos. A migration 234000 foi aplicada em testing; a consulta local posterior informou Nothing to migrate. A reconstrução das adaptações PMD foi verificada em checkout limpo local da revisão fixa e a reaplicação foi idempotente.

O conjunto patches/pmd e scripts/apply-pmd-customizations.py preserva as adaptações do pacote antes ignoradas pelo Git principal; instalador integrado. Permanecem evoluções de perfil/dependentes/ficha completa, homologação visual e configuração institucional/produção registradas no levantamento. Esta correção não declara essas funcionalidades concluídas.

Nesta preparação para commit, não foi confirmado novo reinício dos serviços locais após as últimas correções; a mudança da rotina Docker precisa de recriação do serviço bc-deadlines para carregar o novo comando. Não houve publicação em ambiente externo.
