# Histórico de alterações

## 05/10/2026 — Adoção do contexto mestre V2

- Fonte vigente consolidada em BC-EDUCAR-CONTEXTO-MESTRE.md; documentos anteriores identificados como históricos e preservados.
- Geocodificação bloqueia redirecionamentos, variantes do Nominatim público, URLs com credenciais/query/fragmento e protocolos inadequados; HTTPS obrigatório fora de local/testing.
- Compatibilidade mantida entre IN_PERSON no PMD e PRESENCIAL no BC. Sem migrations, exclusões ou conversão de dados históricos.
- Validação: GeocodingTest, 9 testes/37 asserções; Pint, 2 arquivos aprovados. Resultados anteriores permanecem checkpoints separados.
- Pendentes: infraestrutura e provedores institucionais, credenciais, política de retenção, homologação visual e aceite.

# Registro das ondas

## Onda 0

### Adicionado
- Mapa de modelos, migrations, GraphQL, frontend, segurança e riscos do fluxo original.

### Testes
- Suíte BC usada como linha de base.

### Compatibilidade
- Sem alteração funcional.

## Onda 1

### Adicionado
- Estrutura opcional de modalidade, estado documental e prazo do PMD; tipos documentais, vínculo por processo e histórico de arquivos.

### Migrations
- `database/migrations/pmd/2026_10_03_140002_add_pmd_document_foundation.php`, com rollback; o instalador executa após o pacote PMD.

### APIs / GraphQL
- Nenhuma nesta onda.

### Frontend
- Nenhum nesta onda.

### Compatibilidade
- Inscrições anteriores permanecem com os novos campos nulos; status originais não são alterados.

## Continuação das ondas 2 a 12

### Adicionado
- Configuração de documentos por processo, portal documental do responsável com código por e-mail, upload privado, entrega presencial parcial sem arquivo, filtros e indicadores escolares.
- Serviço Docker de vencimento documental e metadados SHA-256 dos arquivos.

### Alterado
- Novas vinculações PMD copiam a política documental e o prazo do processo; efetivações vinculadas usam o serviço oficial de matrícula PMD.
- Status e timeline do PMD sincronizados no deferimento; inscrição concorrente aberta segue a regra do processo.

### Migrations
- `2026_10_03_140003_add_bc_document_metadata.php` e `pmd/2026_10_03_140004_add_pmd_document_type_code.php`.

### Frontend / APIs
- `/matricula-digital` para responsável e `/bc/matriculas/configuracao` para administradores; fila e formulário presencial ampliados.

### Compatibilidade
- Dados e documentos BC existentes mantidos; novas exigências são copiadas somente para novas vinculações.

### Pendências
- Política institucional de retenção de documentos, SMTP real e homologação da Secretaria.

## Complementos das ondas 3, 4, 10, 11 e 12

- Portal do responsável disponível antes da vinculação à turma, com escolha de modalidade, envio privado, eventos e preservação dos arquivos e do prazo na importação escolar.
- Fila transacional de avisos de correção, prazo encerrado e matrícula efetivada, além de lembrete nas 24 horas anteriores ao prazo. Envios desativados até ativação do transporte de e-mail; a massa fictícia não gera mensagens.
- Filtros por processo PMD, série, turno e tipo de solicitação; indicadores seguem o recorte selecionado. Exportação CSV de totais por escola, série, turno, ano, modalidade e situação, com escopo escolar e proteção contra fórmulas em nomes do catálogo.
- Teste do percurso completo pela mutação pública oficial PMD, autenticação do responsável, upload, importação escolar, análise e matrícula/enturmação nativas.
- Migrations adicionais: `pmd/2026_10_03_140005_create_pmd_document_events.php` e `2026_10_03_140004_create_bc_guardian_notifications.php`.
- Continuação da implantação autorizada no ambiente local; homologação institucional permanece pendente.

## Revisão do plano mestre e correção da Onda 9 — 03/10/2026

- Adicionado `AUDITORIA-PLANO-MESTRE.md`, com evidências e limites por onda; etapas parciais não são apresentadas como integralmente concluídas.
- Corrigido indeferimento automático ao vencer documentação ou registrar não comparecimento: preservado status principal do PMD.
- Permitida decisão escolar explícita após vencimento, com motivo, escopo e auditoria do estado anterior.
- Permitida análise/aprovação posterior de documentação obrigatória completa entregue tempestivamente, mantendo bloqueio de recebimento tardio.
- Sem alteração de esquema nem reversão automática de rejeições históricas. Decisão: DEC-006.
- Validação: 34 testes, 416 asserções e estilo PHP aplicado aos arquivos alterados.

## Continuação da Onda 4 — limite configurável

- Adicionados `bc-documents.php` e `DocumentUploadPolicy`: limite comum por configuração, padrão de 5 MB e validação antes de armazenar.
- Telas e rotas escolares e do responsável usam o mesmo limite, inclusive antes da vinculação BC.
- Corrigido o teto PHP local de 2 MB com configuração persistente Docker de 10 MB por arquivo e 12 MB por requisição. Serviços PHP e Nginx locais atualizados.
- Validação: 36 testes, 446 asserções, Pint PASS e portal local com HTTP 200. Sem migration nova. Decisão: DEC-007.

## Continuação da Onda 6 — estado documental agregado

- Adicionado `DocumentProgress` para recalcular o estado após recebimento e análise de documento.
- Entrega parcial permanece aguardando documentos; correção obrigatória permanece pendente mesmo após análise de outro item; correção opcional não impede aprovação dos obrigatórios.
- A análise individual não aprova automaticamente a solicitação; permanece necessária a ação geral autorizada. Documentos substituídos continuam no histórico e o prazo permanece intacto.
- Nenhuma migration nova; os registros anteriores não foram recalculados em massa.
- Checkpoint: 37 testes aprovados, 458 asserções, Pint PASS; pronto para próxima correção incremental local.

## Continuação da Onda 5 — progresso na fila

- Incluídas colunas de documentação obrigatória e data da solicitação no painel escolar.
- Recebimento tempestivo, aprovação e correção calculados com versões atuais; opcionais e versões substituídas excluídos do progresso obrigatório.
- Data da inscrição PMD quando vinculada; fallback para criação local BC. Sem migração nem alteração de documentos ou prazos.
- Validação: 38 testes aprovados, 477 asserções e Pint PASS. Próxima correção incremental local liberada.


## Mapas gratuitos e acesso do responsavel - 03/10/2026

- Substituidos Google Maps/Geocoding por Leaflet e servico de geocoding configuravel. Selecao e confirmacao manual do endereco funcionam sem chave Google.
- Preservados latitude/longitude, cadastros de pontos, rotas e sequencias de itinerario do transporte.
- Corrigido o link local Login Pais e Alunos, antes direcionado ao painel escolar, para /matricula-digital.
- Build publicado; 49 testes PHP (528 assertions) e 4 testes Vue aprovados. Sem referencias Google Maps nos assets publicados.
- Busca automatica residencial exige geocoder proprio/municipal; validacao visual e Network pendentes por indisponibilidade da ferramenta de navegador.
- Relatorio e configuracao: [Migracao dos mapas](../mapas/MIGRACAO-MAPAS.md).


## Correcao do envio apos endereco - 03/10/2026

- Reproduzida ausencia de envio com XForm, campos, regras e botao reais. Corrigida propriedade disable do XBtn em Prosseguir e Buscar CEP.
- Evento real encaminhado para validacao; lista de campos invalidos exibida junto ao formulario.
- Teste confirma payload de endereco com coordenadas e cobranca das validacoes existentes. Botao libera corretamente apos espera.
- Sete testes da interface aprovados e build de producao compilado.


## Deferimento da pré-matrícula e liberação documental - 04/10/2026

- Deferir no PMD passa a liberar documentação pela importação autorizada e compatível com a turma, sem efetivar matrícula. Lotes são transacionais. Efetivação continua exigindo aprovação documental no fluxo final.
- Estado oficial SUMMONED preservado; acompanhamento mostra Aguardando documentação. GraphQL expõe documentationStatus e documentationDeadline, usados pela consulta de protocolo para apresentar o prazo e o acesso aos documentos.
- Novo evento PREREGISTRATION_APPROVED enfileira aviso DOCUMENTS_OPEN uma vez. E-mail HTML e texto incluem liberação, prazo comum online/presencial, botão com acesso autenticado e esclarecimento de que a matrícula ainda não foi efetivada. Avisos desatualizados não são enviados.
- Inscrições alternativas permanecem em espera até deferimento explícito da escola com turma compatível. Depois podem acessar documentação.
- Endereço fixo na seleção de escola, pins azuis com nomes permanentes e ordenação por distância em linha reta, preservando filtros existentes de vagas/série/turno/raio.
- Verificação: 55 testes backend, 597 asserções; 12 testes frontend. Build concluído. E-mail real continua desativado no ambiente local e SMTP está sem credenciais; nenhum e-mail real foi enviado durante a verificação. Validação visual em navegador permanece pendente.


## Valida??o t?cnica local ? 05/10/2026

- 19 testes aprovados, 314 asser??es; schema GraphQL v?lido; checagem HTTP dos portais e dois perfis conclu?da.
- Corrigida depend?ncia de prazos da massa fict?cia em tr?s cen?rios e prepara??o expl?cita de inscri??o sem v?nculo no teste da fila. Altera??es restritas aos testes.
- Valida??o visual bloqueada pelo ambiente de navegador; roteiro e evid?ncias em [VALIDACAO-2026-10-05.md](VALIDACAO-2026-10-05.md).


## Seguranca e prazos anteriores ao vinculo - 05/10/2026

- Expiracao documental sem vinculo BC integrada ao comando e servico Docker existentes, com trava transacional, auditoria unica e preservacao do status principal PMD.
- Entrega obrigatoria completa e tempestiva permanece disponivel para analise e importacao escolar apos o prazo; recebimento tardio e alteracao de modalidade continuam bloqueados.
- Portal anterior ao vinculo informa prazo encerrado e oculta troca de modalidade fora do prazo.
- Upload exige extensao permitida alem de MIME/tamanho. Testes usam arquivos reais com MIME declarado falso para verificar conteudo PHP/SVG disfarcado e PNG com extensao PHP/TXT. Cobertura de acesso/substituicao entre responsaveis ampliada.
- Validacao final: 46 testes aprovados, 584 assercoes, no banco testing; Pint --test PASS nos seis arquivos PHP alterados. Sem migration nova, frontend Vue nao alterado.
- Servico bc-deadlines local ativo; codigo disponibilizado pelo volume do projeto. Revisao visual, SMTP e aceite institucional continuam pendentes.

## Ajuste de separação das etapas — 05/10/2026

Fluxo atual: pré-matrícula → deferimento escolar → liberação documental → escolha do responsável → entrega → triagem → aprovação documental → efetivação explícita pelo serviço nativo.

A modalidade fica vazia até a escolha do responsável após deferimento. O formulário público e o deferimento escolar não escolhem online/presencial. PMD WAITING bloqueia entrega e análise mesmo que exista vínculo BC. Aprovação documental não cria matrícula.

O painel separa Pré-matrículas, Triagem Documental, Matrículas efetivadas e Configuração. O acompanhamento possui três etapas dinâmicas. Auditoria e comunicação reutilizam a fila existente, inclusive para inscrição anterior ao vínculo BC.

Migration 2026_10_05_180000_separate_document_choice_and_notice_origin aplicada em testing e no banco local: modalidade nullable e origem única BC/PMD dos avisos. Sem exclusão de dados; rollback protegido.

Validação: 78 testes backend aprovados e um teste com aviso de depreciação legado (805 asserções), 16 testes frontend aprovados, build concluído, assets publicados e schema válido. Não equivale a homologação visual ou entrega real de e-mail.

Relatório completo, arquivos, rotas, cobertura e riscos: [AJUSTE-FLUXO-2026-10-05.md](AJUSTE-FLUXO-2026-10-05.md).
Aguardar validação do usuário antes de iniciar outra onda.

## Comunicação por SMTP local — 05/10/2026

- Overlay docker-compose.mail.yml com Mailpit v1.31.4 ativo em http://localhost:8025; aplicação local usa SMTP de captura.
- Script scripts/verify-bc-smtp.php validou nove tipos de aviso, versões HTML/texto, protocolo, link e ausência de duplicidade.
- Falha de conexão, backoff e recuperação validados; dez mensagens capturadas na execução final. Fixtures apenas em testing, com rollback.
- Sem migration ou alteração nas regras de matrícula; flag global de avisos não ativada automaticamente.
- Revisão visual segue bloqueada pela inicialização das ferramentas (ACL do ambiente). SMTP institucional e aceite permanecem pendentes.
- Evidências e reprodução: [COMUNICACAO-SMTP-LOCAL-2026-10-05.md](COMUNICACAO-SMTP-LOCAL-2026-10-05.md).

## Integração do detalhe nativo PMD — 05/10/2026

O detalhe da pré-matrícula passa a mostrar situação documental, modalidade pendente/escolhida, prazo, obrigatórios recebidos no prazo, aprovados e correções, com link à triagem autorizada. O campo GraphQL documentarySummary é opcional e exclusivo para sessão web escolar autorizada; token público, outra escola e usuário inativo não recebem dados. WAITING com vínculo BC permanece bloqueado. Versões substituídas, opcionais e recebimentos tardios não contam como progresso válido.

Sem migration ou novos estados. Aprovação documental continua sem matrícula; nenhum registro legado foi convertido. Processos sem vínculo não exibem o componente.

Relatório: [INTEGRACAO-PMD-DOCUMENTAL-2026-10-05.md](INTEGRACAO-PMD-DOCUMENTAL-2026-10-05.md).

Validação desta integração: 38 testes backend, 475 asserções; 21 testes frontend e build aprovados. Sem migration. Revisão visual e ativação dos processos legados permanecem pendentes.

## Pendências implementadas — 05/10/2026

- Categorias documentais adicionais em configuração, importação, upload, presencial, análise e versões; nome/obrigatoriedade preservados por solicitação.
- Processos novos exigem ativação; legados mantêm compatibilidade até decisão explícita. Suspensão afeta novas liberações, preservando fases abertas.
- Conectores HTTP SMS/WhatsApp preparados, desativados e com corte explícito, idempotência e retry por canal. Requerem ponte compatível e validação externa.
- Relatório de retenção agregado sem exclusões; minuta e roteiro de aceite preparados. Política e revisão visual permanecem pendentes.
- Duas migrations aditivas aplicadas em local e testing; 98 testes aprovados e um aviso de depreciação legado, 941 asserções, sem falhas. SMTP local e HTTP revalidados.
- Relatório: [IMPLEMENTACAO-PENDENCIAS-2026-10-05.md](IMPLEMENTACAO-PENDENCIAS-2026-10-05.md).

Verificação HTTP desta atualização concluída: portal público, bundle, configuração municipal e quatro páginas para admin.seduc e op.medici responderam HTTP 200. Esta checagem não substitui homologação visual.

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

## 05/10/2026 — Simplificação do cadastro público

- Endereço suficiente para cadastro; confirmação de localização removida e coordenadas opcionais no GraphQL.
- Terceira escola retirada; novas inscrições limitadas a duas opções sem converter histórico ou alterar processos.
- Mapas mostram todas as unidades elegíveis e ajustam enquadramento, preservando rematrícula restrita.
- E-mail do responsável obrigatório, sem duplicação de campo e persistido no PMD.
- Protocolo explícito nos avisos e no código de acesso.
- Botões BC e PMD renomeados para “Deferir pré-matrícula”.
- Validação: backend 32 testes/354 asserções; frontend 23 testes; build/publicação e schema válidos; SMTP local 9 tipos e recuperação sem duplicidade. Sem migrations. Homologação visual pendente.

- Verificação final: telas HTTP 200 para administrador e operador; Pint aprovado em 5 arquivos.

## 05/10/2026 — Identificação para revisão histórica

- Implementado bc:historical-expiry-review: relatório agregado somente leitura, filtros por processo/escola e saída JSON.
- Não restaura inscrições nem confirma automaticamente a causa da rejeição.
- Proposta de movimentação nativa no contexto mestre pendente de autorização após rejeição da revisão automática; RegistrationWorkflow permanece intacto.
- Geocoder vazio tratado como opcional/desativado no diagnóstico.
- Validação: 4 testes/27 asserções; Pint em 4 arquivos. Relatório local com 24 candidatos, somente leitura. Sem migrations.


## 05/10/2026 — Movimentação nativa autorizada e implementada

- Após autorização explícita, efetivação BC integra transferência/remanejamento pelos serviços nativos PMD, apenas com configuração já habilitada e vínculo PMD.
- Exige matrícula única em andamento, mesma série, capacidade e autorização para origem/destino; preserva idempotência e rollback integral.
- Mantém a matrícula reutilizada como última no remanejamento; audita tipo de movimento e matrícula anterior.
- 8 testes específicos aprovados, 59 asserções; Pint aprovado em 2 arquivos. Sem migration ou ativação de configuração municipal.

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
