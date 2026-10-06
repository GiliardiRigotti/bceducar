> Resumo de checkpoint hist?rico. Requisitos e situa??o atual: [contexto mestre ?nico](docs/matricula-digital/BC-EDUCAR-CONTEXTO-MESTRE.md).

# Estado atual do BC Educar

**Data de referência: 05/10/2026**  
**Ambiente: desenvolvimento local**  
**Endereço principal: http://localhost:8080**

**Último ajuste:** etapas separadas, modalidade escolhida somente após deferimento, três etapas de acompanhamento e comunicação ampliada. Relatório: [Ajuste do fluxo](docs/matricula-digital/AJUSTE-FLUXO-2026-10-05.md).

## 1. Resumo

O sistema está em execução local, com os oito serviços Docker ativos (incluindo SMTP local de teste) na verificação desta documentação. O i-Educar mantém os cadastros, a matrícula e a enturmação oficiais. O módulo de Pré-Matrícula Digital (PMD) recebe as inscrições e mantém o acompanhamento do responsável. A extensão BC acrescenta configuração, entrega, análise e histórico documental, com integração ao fluxo oficial de matrícula.

O percurso principal já está implementado e possui validação automatizada: inscrição pública, escolha de escolas, liberação documental pela escola, entrega online ou presencial, análise, aprovação documental e efetivação de matrícula.

O projeto permanece em desenvolvimento e homologação local. A validação visual completa, o envio real de e-mails, a ativação dos processos reais pela administração e o aceite da Secretaria ainda estão pendentes.

## 2. Componentes e módulos

| Componente | Situação atual |
| --- | --- |
| i-Educar | Núcleo em execução; responsável pelos registros escolares oficiais. |
| Pré-Matrícula Digital | Backend e interface compilada instalados; integrado à fase documental BC. |
| Extensão documental BC | Portal do responsável, painel escolar, análise, prazos, auditoria e efetivação implementados localmente. |
| Transporte escolar | Pacote instalado, provider, migrações e menus integrados; cadastros e persistência de coordenadas possuem validação registrada. |
| Mapas | Leaflet integrado aos fluxos PMD e aos pontos de transporte. |
| Biblioteca, Relatórios e Educacenso | Há fontes obtidos no workspace; esta documentação não certifica ativação ou homologação completa desses módulos. |

Base técnica registrada no projeto: i-Educar 2.12, Laravel 13, PHP 8.5, PostgreSQL 18, Redis 8 e Nginx. O frontend PMD está compilado e publicado no diretório público; não exige Vite permanentemente em execução.

## 3. Fluxo funcional atual

1. **Inscrição:** o responsável preenche o formulário público do PMD, informa aluno, responsável e endereço e escolhe a escola principal.
2. **Alternativas:** quando o processo permite, pode indicar até duas escolas alternativas distintas e compatíveis com série e turno. As alternativas permanecem na lista de espera.
3. **Análise inicial escolar:** a inscrição aguarda decisão da escola. Enquanto estiver em espera, não pode escolher a modalidade documental nem enviar documentos.
4. **Liberação documental:** a escola utiliza a ação de deferimento com turma compatível. Essa ação libera a documentação e mantém a inscrição no estado oficial de convocação, sem criar matrícula.
5. **Acesso do responsável:** protocolo, e-mail cadastrado e código de acesso autorizam a consulta de uma inscrição.
6. **Entrega:** o responsável escolhe envio online ou entrega presencial. Os dois canais usam o mesmo prazo.
7. **Conferência:** o operador recebe documentos, analisa cada item e pode aprovar, rejeitar ou solicitar correção com motivo.
8. **Aprovação documental:** exige os documentos obrigatórios recebidos tempestivamente e aprovados.
9. **Efetivação:** uma ação posterior cria a matrícula e a enturmação nativas e sincroniza o PMD.

**Deferir a pré-matrícula, aprovar os documentos e efetivar a matrícula são ações distintas.** A liberação inicial ou a aprovação documental isolada não cria matrícula.

Não há alocação automática entre as preferências de escola. Inscrições alternativas exigem decisão escolar explícita para liberar documentação.

## 4. Funcionalidades implementadas

### Configuração documental

- Catálogo extensível: dez tipos canônicos preservados e categorias adicionais com códigos únicos.
- Associação de documentos a processos, com indicação de obrigatoriedade e prazo.
- Administração global ou institucional, conforme o escopo autorizado.
- A configuração e o prazo são copiados para a solicitação na vinculação BC; mudanças posteriores não alteram automaticamente solicitações já vinculadas.
- Categorias adicionais podem ser cadastradas na configuração e usadas nos dois canais, mantendo nome e exigência por solicitação.

### Portal do responsável

- Acesso com protocolo e e-mail cadastrado.
- Código de seis dígitos, com validade de dez minutos e limite de tentativas.
- Acompanhamento antes da liberação documental.
- Escolha online/presencial após liberação.
- Troca de modalidade dentro das condições permitidas, sem reiniciar o prazo.
- Envio de arquivos, consulta de situação, histórico e download autorizado.
- Preservação dos arquivos recebidos no PMD ao vinculá-los à análise escolar BC.

O acesso real por código depende de e-mail funcional. O exemplo local é restrito ao ambiente local e depende de existir uma solicitação fictícia elegível.

### Documentos e segurança

- PDF, JPG, JPEG e PNG.
- Limite padrão de 5 MB por arquivo, configurável por BC_DOCUMENT_MAX_UPLOAD_KB.
- Validação de MIME, extensão e tamanho.
- Armazenamento em disco privado, fora de links públicos diretos.
- Registro de nome original, MIME, tamanho, SHA-256 e histórico.
- Correções e substituições preservam versões anteriores.
- Downloads protegidos por sessão e autorização.
- Isolamento entre escolas e entre inscrições de responsáveis.

A validação de tipo/extensão não representa uma varredura antivírus ou a remoção de conteúdo ativo de todo PDF/imagem.

### Entrega presencial e análise escolar

- Recebimento de documentos físicos sem obrigatoriedade de digitalização.
- Entregas parciais, com data e operador registrados.
- Análise individual e decisão geral explícita.
- Documentos obrigatórios pendentes ou com correção impedem aprovação completa.
- Pendências de documentos opcionais não bloqueiam indevidamente os obrigatórios.
- Histórico de recebimentos, revisões, decisões e substituições.

### Painel e relatórios

- Fila por escopo escolar do operador.
- Filtros por escola, processo, série, turno, ano, modalidade, tipo, situação e prazo.
- Progresso dos documentos obrigatórios e data da solicitação.
- Indicadores e totais que acompanham os filtros.
- Exportação CSV de resumo agregado, respeitando os mesmos filtros e permissões.
- Proteção contra fórmulas em nomes exportados.
- Análises históricas mais detalhadas ainda pendentes.

### Matrícula e integração

- Uso do serviço oficial PMD para efetivações vinculadas.
- Verificação de escola, série, turno, ano, turma, capacidade e matrícula ativa.
- Transações e bloqueios para prevenir duplicidade.
- Atualização do acompanhamento e histórico PMD.
- Transferências com matrícula ativa encaminhadas ao fluxo nativo; regras municipais de remanejamento ainda precisam de homologação.

## 5. Prazos e automação

O prazo é de **entrega documental**. Não encerra automaticamente a análise da escola.

- Trocar modalidade ou reenviar documento não reinicia o prazo.
- Entrega completa e tempestiva continua disponível para análise após o vencimento.
- Documentação incompleta ou entregue tardiamente continua bloqueada para aprovação.
- Vencimento e não comparecimento não indeferem automaticamente a pré-matrícula.
- Indeferimento ou cancelamento exige decisão escolar explícita, motivo e auditoria.
- Reexecuções da rotina não duplicam o evento de vencimento.

### Complemento implementado em 05/10/2026

A rotina passou a registrar também vencimento de inscrições sem vínculo BC, desde que já estejam liberadas e com estado documental ativo. Preserva o status principal PMD e registra evento documental com autor SYSTEM.

Inscrições em espera, finalizadas, aprovadas, antigas sem estado documental e já vinculadas ficam fora dessa rotina adicional. Para inscrições já vinculadas, permanece o tratamento BC existente.

A importação escolar posterior ao prazo passou a aceitar documentação obrigatória completa entregue tempestivamente, preservando as datas originais. Novos envios tardios permanecem bloqueados.

O serviço bc-deadlines executa a cada cinco minutos:

- bc:expire-registration-requests
- bc:queue-deadline-reminders
- bc:send-guardian-notices

Extensão ou reabertura de prazo depende de regra institucional ainda não definida.

## 6. Comunicações

Estão implementados código de acesso e avisos enfileirados de:

- Liberação documental.
- Solicitação de correção.
- Lembrete nas 24 horas anteriores ao prazo.
- Encerramento do prazo.
- Matrícula efetivada.

Há controle para evitar duplicações e envio de avisos desatualizados. Dados fictícios da semeadura não disparam os avisos escolares reais.

**O transporte SMTP local foi validado com Mailpit; envio externo ainda precisa de SMTP institucional configurado e validado.** Os avisos dependem da ativação de BC_GUARDIAN_NOTIFICATIONS_ENABLED. Não houve validação de entrega real nesta etapa.

Os avisos de inscrição registrada, modalidade escolhida, documento recebido, aprovação documental e vencimento anterior ao vínculo estão implementados na mesma fila, com controle de duplicidade. A entrega real ainda depende de SMTP. SMS/WhatsApp possuem conectores HTTP genéricos preparados e desativados; ainda dependem de ponte compatível e validação externa.

## 7. Mapas e transporte

- Mapas com Leaflet e tiles configuráveis, sem chave Google nos fluxos migrados.
- Busca de CEP com preenchimento de endereço e alternativa manual.
- Localização residencial confirmada explicitamente no mapa ou por coordenadas.
- Escolas apresentadas com nomes, marcadores e distância aproximada em linha reta.
- Latitude/longitude de pontos de transporte e sequência de itinerários preservadas.
- Transporte oferece empresas, motoristas, pontos, rotas, veículos e usuários de transporte.
- Acesso ao transporte depende das permissões atribuídas ao tipo de usuário.

A busca automática residencial depende de um geocoder próprio/municipal configurado. Não há cálculo de trajeto pelas ruas, associação automática do aluno ao transporte ou definição automática de elegibilidade.

## 8. Acessos locais

| Área | Endereço |
| --- | --- |
| Sistema e login | http://localhost:8080 |
| Portal de Pré-Matrícula Digital | http://localhost:8080/pre-matricula-digital |
| Consulta e documentação do responsável | http://localhost:8080/matricula-digital |
| Exemplo fictício do responsável | http://localhost:8080/matricula-digital/demo |
| Inscrições administrativas PMD | http://localhost:8080/pre-matricula-digital/inscricoes |
| Fila e painel documental BC | http://localhost:8080/bc/matriculas |
| Pré-matrículas para análise/liberação | http://localhost:8080/bc/matriculas/pmd |
| Configuração documental | http://localhost:8080/bc/matriculas/configuracao |
| Transporte escolar | http://localhost:8080/intranet/educar_transporte_escolar_index.php |

Entre primeiro no i-Educar para acessar áreas administrativas.

Perfis fictícios documentados: admin.seduc (instituição), op.medici (CEM Presidente Médici), op.ivosilvei (CEM Governador Ivo Silveira) e op.duas (duas escolas). Há operadores por unidade na massa demonstrativa. As credenciais de teste estão em [SEMEACAO-BC.md](SEMEACAO-BC.md); senhas existentes são preservadas ao reexecutar o seeder.

## 9. Dados de demonstração

A documentação da semeadura registra:

| Entidade | Quantidade prevista |
| --- | ---: |
| Instituição | 1 |
| Escolas | 15 |
| Cursos | 2 |
| Etapas | 15 |
| Turmas | 186 |
| Responsáveis | 100 |
| Alunos | 150 |
| Usuários | 17 |
| Solicitações | 200 |

Inclui canais online/presencial, entrega parcial, correção, aprovação, vencimento, turmas com diferentes disponibilidades e matrículas nativas.

Os nomes, contatos e documentos são fictícios. Coordenadas escolares são de demonstração. As quantidades acima descrevem a massa documentada, não uma contagem atual do banco feita nesta entrega.

O seeder é idempotente, preserva dados anteriores e bloqueia uso em produção. Não se deve executar migrate:fresh no banco local com dados.

## 10. Ambiente e operação

Na verificação de 05/10/2026, os sete serviços estavam ativos:

| Serviço | Responsabilidade |
| --- | --- |
| php | Comandos Artisan, Composer e manutenção. |
| fpm | Execução das requisições PHP. |
| nginx | Atendimento HTTP local. |
| postgres | Banco de dados persistido em volume Docker. |
| redis | Cache e suporte às filas. |
| horizon | Processamento e supervisão de filas Laravel. |
| bc-deadlines | Rotinas documentais periódicas. |

Portas publicadas localmente: HTTP 8080, porta SSL 8443, PostgreSQL 5433 e Redis 6380, vinculadas a 127.0.0.1. A porta SSL publicada não significa que HTTPS foi homologado.

Comandos usuais, executados na raiz do projeto:

~~~powershell
docker compose up -d
docker compose ps
docker compose logs --tail=100 fpm nginx horizon bc-deadlines
~~~

Para parar preservando o volume do banco:

~~~powershell
docker compose stop
~~~

A migration 2026_10_05_180000_separate_document_choice_and_notice_origin foi aplicada no banco local e em testing: modalidade opcional até a escolha e origem BC/PMD dos avisos. O rollback protege dados incompatíveis. O código do projeto é montado nos containers locais.

## 11. Validações e evidências

### Validação técnica final de 05/10/2026

**Validação anterior de segurança: 46 testes aprovados, com 584 asserções**, no banco isolado testing. A regressão mais recente do ajuste de fluxo executou 78 testes aprovados e um teste com aviso de depreciação legado, totalizando 805 asserções. Frontend: 16 testes aprovados e build concluído.

Suítes executadas:

- PmdDocumentExpirationTest
- GuardianDocumentTest
- RegistrationDeadlinePolicyTest
- RegistrationEndToEndTest
- RegistrationDashboardTest
- BalnearioCamboriuDemoTest

Cobertura inclui inscrição pública até matrícula nativa, alternativas de escola, liberação documental, entrega e substituição, arquivos disfarçados, isolamento entre responsáveis/escolas, prazos, capacidade, duplicidade, painel e CSV. Pint --test passou nos seis arquivos PHP da etapa de segurança e prazos.

Também foram verificados em 05/10/2026:

- Schema GraphQL válido.
- Login respondendo HTTP 200.
- Portal PMD, bundle JavaScript e configuração municipal disponíveis.
- Acessos administrativos por admin.seduc e op.medici nas três páginas principais, com HTTP 200.

Há checkpoint anterior, de 04/10/2026, com 55 testes backend e 12 testes frontend aprovados e build compilado. Esse registro histórico tem escopo diferente; não deve ser somado à validação de 46 testes nem apresentado como reexecução de toda a suíte hoje.

### Limites da validação

A ferramenta de navegador não iniciou por erro do ambiente. Por isso, a aparência e as interações completas em desktop/celular não foram validadas visualmente nesta sessão. HTTP 200 e testes automatizados não substituem essa revisão.

Também não foram concluídos aceite da Secretaria, entrega SMTP real ou homologação de produção.

## 12. Pendências e próximas etapas

| Prioridade | Pendência |
| --- | --- |
| Integração entregue | Resumo documental no detalhe nativo PMD e navegação escolar à triagem, com API restrita. |
| Ativação entregue | Processos novos exigem ativação; legados mantêm compatibilidade até decisão administrativa. |
| Antes de uso real | Configurar SMTP e validar códigos de acesso e avisos de ponta a ponta. |
| Antes de produção | Revisão visual completa em desktop/celular e homologação com a Secretaria. |
| Antes de produção | Confirmar regras municipais de transferência, remanejamento, concorrência e reabertura de prazos. |
| Antes de produção | Definir finalidade, retenção, descarte e responsáveis pelo tratamento documental. |
| Antes de produção | Preparar e validar backup/restauração, infraestrutura e monitoramento. |
| Comunicação | Eventos completos nesta etapa; validar entrega real por SMTP. |
| Complemento | Validar ponte/provedor SMS/WhatsApp; análises históricas continuam como melhoria posterior. |
| Complemento | Configurar infraestrutura municipal de geocoding/tiles e chave Froala quando utilizada. |

Rejeições históricas causadas pelo comportamento antigo de vencimento não foram revertidas automaticamente: precisam de análise de eventos e decisão autorizada.

## 13. Documentação relacionada

- [Desenvolvimento no Windows](DEVELOPMENT-WINDOWS.md)
- [Instalação e integração PMD](INSTALL-PMD.md)
- [Transporte escolar](INSTALL-TRANSPORTE.md)
- [Massa fictícia e fluxo integrado](SEMEACAO-BC.md)
- [Pendências detalhadas](docs/matricula-digital/PENDENCIAS.md)
- [Histórico das implementações](docs/matricula-digital/CHANGELOG.md)
- [Auditoria do plano por ondas](docs/matricula-digital/AUDITORIA-PLANO-MESTRE.md)
- [Validação técnica e roteiro visual](docs/matricula-digital/VALIDACAO-2026-10-05.md)
- [Migração e configuração dos mapas](docs/mapas/MIGRACAO-MAPAS.md)

Esta documentação retrata o estado local conhecido em 05/10/2026 e distingue implementação, validação técnica e homologação operacional.

## Separação das etapas e validação atual

## Ajuste de separação das etapas — 05/10/2026

Fluxo atual: pré-matrícula → deferimento escolar → liberação documental → escolha do responsável → entrega → triagem → aprovação documental → efetivação explícita pelo serviço nativo.

A modalidade fica vazia até a escolha do responsável após deferimento. O formulário público e o deferimento escolar não escolhem online/presencial. PMD WAITING bloqueia entrega e análise mesmo que exista vínculo BC. Aprovação documental não cria matrícula.

O painel separa Pré-matrículas, Triagem Documental, Matrículas efetivadas e Configuração. O acompanhamento possui três etapas dinâmicas. Auditoria e comunicação reutilizam a fila existente, inclusive para inscrição anterior ao vínculo BC.

Migration 2026_10_05_180000_separate_document_choice_and_notice_origin aplicada em testing e no banco local: modalidade nullable e origem única BC/PMD dos avisos. Sem exclusão de dados; rollback protegido.

Validação: 78 testes backend aprovados e um teste com aviso de depreciação legado (805 asserções), 16 testes frontend aprovados, build concluído, assets publicados e schema válido. Não equivale a homologação visual ou entrega real de e-mail.

Relatório completo, arquivos, rotas, cobertura e riscos: [AJUSTE-FLUXO-2026-10-05.md](docs/matricula-digital/AJUSTE-FLUXO-2026-10-05.md).
Aguardar validação do usuário antes de iniciar outra onda.


## Comunicação por SMTP local — 05/10/2026

- Overlay docker-compose.mail.yml com Mailpit v1.31.4 ativo em http://localhost:8025; aplicação local usa SMTP de captura.
- Script scripts/verify-bc-smtp.php validou nove tipos de aviso, versões HTML/texto, protocolo, link e ausência de duplicidade.
- Falha de conexão, backoff e recuperação validados; dez mensagens capturadas na execução final. Fixtures apenas em testing, com rollback.
- Sem migration ou alteração nas regras de matrícula; flag global de avisos não ativada automaticamente.
- Revisão visual segue bloqueada pela inicialização das ferramentas (ACL do ambiente). SMTP institucional e aceite permanecem pendentes.
- Evidências e reprodução: [COMUNICACAO-SMTP-LOCAL-2026-10-05.md](docs/matricula-digital/COMUNICACAO-SMTP-LOCAL-2026-10-05.md).


## Integração do detalhe nativo PMD — 05/10/2026

O detalhe da pré-matrícula passa a mostrar situação documental, modalidade pendente/escolhida, prazo, obrigatórios recebidos no prazo, aprovados e correções, com link à triagem autorizada. O campo GraphQL documentarySummary é opcional e exclusivo para sessão web escolar autorizada; token público, outra escola e usuário inativo não recebem dados. WAITING com vínculo BC permanece bloqueado. Versões substituídas, opcionais e recebimentos tardios não contam como progresso válido.

Sem migration ou novos estados. Aprovação documental continua sem matrícula; nenhum registro legado foi convertido. Processos sem vínculo não exibem o componente.

Relatório: [INTEGRACAO-PMD-DOCUMENTAL-2026-10-05.md](docs/matricula-digital/INTEGRACAO-PMD-DOCUMENTAL-2026-10-05.md).

Validação mais recente da integração nativa: **38 testes backend aprovados (475 asserções)** e **21 testes frontend aprovados**, build publicado e schema GraphQL válido. Esse recorte não deve ser somado às execuções anteriores, pois inclui regressão repetida.


## Pendências implementadas — 05/10/2026

- Categorias documentais adicionais em configuração, importação, upload, presencial, análise e versões; nome/obrigatoriedade preservados por solicitação.
- Processos novos exigem ativação; legados mantêm compatibilidade até decisão explícita. Suspensão afeta novas liberações, preservando fases abertas.
- Conectores HTTP SMS/WhatsApp preparados, desativados e com corte explícito, idempotência e retry por canal. Requerem ponte compatível e validação externa.
- Relatório de retenção agregado sem exclusões; minuta e roteiro de aceite preparados. Política e revisão visual permanecem pendentes.
- Duas migrations aditivas aplicadas em local e testing; 98 testes aprovados e um aviso de depreciação legado, 941 asserções, sem falhas. SMTP local e HTTP revalidados.
- Relatório: [IMPLEMENTACAO-PENDENCIAS-2026-10-05.md](docs/matricula-digital/IMPLEMENTACAO-PENDENCIAS-2026-10-05.md).
