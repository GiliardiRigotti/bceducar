> Documento histórico. A fonte atual é [BC-EDUCAR-CONTEXTO-MESTRE-V3.md](BC-EDUCAR-CONTEXTO-MESTRE-V3.md).

# Matrícula digital e presencial

O i-Educar mantém a matrícula e a enturmação oficiais. O PMD mantém a inscrição e o contato com o responsável. A documentação é uma dimensão independente: modalidade `ONLINE` ou `IN_PERSON`, estado documental e prazo comum. O plano recebido em `PLANO-MESTRE-MATRICULA-DIGITAL-POR-ONDAS.md` orienta as ondas; este diretório registra o que foi efetivamente entregue neste checkout.

O fluxo BC usa `bc_registration_requests` e `bc_registration_documents` para a fase documental escolar. Há configuração por processo, acesso do responsável por código enviado ao e-mail cadastrado e upload privado inclusive antes da vinculação escolar, armazenado no PMD e preservado na importação. O painel e o relatório CSV usam os filtros e o escopo de escolas do operador. Os avisos e lembretes por e-mail dependem da ativação do transporte configurado. O percurso público PMD até a matrícula nativa é validado em teste com dados fictícios.

A implementação continua no ambiente local, conforme a escolha do usuário. O aceite da Secretaria, o transporte real de e-mail e a política institucional de retenção de documentos permanecem pendentes para uso fora da demonstração. Dados reais e tabelas legadas não devem ser apagados.

## Ajuste de separação das etapas — 05/10/2026

Fluxo atual: pré-matrícula → deferimento escolar → liberação documental → escolha do responsável → entrega → triagem → aprovação documental → efetivação explícita pelo serviço nativo.

A modalidade fica vazia até a escolha do responsável após deferimento. O formulário público e o deferimento escolar não escolhem online/presencial. PMD WAITING bloqueia entrega e análise mesmo que exista vínculo BC. Aprovação documental não cria matrícula.

O painel separa Pré-matrículas, Triagem Documental, Matrículas efetivadas e Configuração. O acompanhamento possui três etapas dinâmicas. Auditoria e comunicação reutilizam a fila existente, inclusive para inscrição anterior ao vínculo BC.

Migration 2026_10_05_180000_separate_document_choice_and_notice_origin aplicada em testing e no banco local: modalidade nullable e origem única BC/PMD dos avisos. Sem exclusão de dados; rollback protegido.

Validação: 78 testes backend aprovados e um teste com aviso de depreciação legado (805 asserções), 16 testes frontend aprovados, build concluído, assets publicados e schema válido. Não equivale a homologação visual ou entrega real de e-mail.

Relatório completo, arquivos, rotas, cobertura e riscos: [AJUSTE-FLUXO-2026-10-05.md](AJUSTE-FLUXO-2026-10-05.md).
Aguardar validação do usuário antes de iniciar outra onda.

## Integração do detalhe nativo PMD — 05/10/2026

O detalhe da pré-matrícula passa a mostrar situação documental, modalidade pendente/escolhida, prazo, obrigatórios recebidos no prazo, aprovados e correções, com link à triagem autorizada. O campo GraphQL documentarySummary é opcional e exclusivo para sessão web escolar autorizada; token público, outra escola e usuário inativo não recebem dados. WAITING com vínculo BC permanece bloqueado. Versões substituídas, opcionais e recebimentos tardios não contam como progresso válido.

Sem migration ou novos estados. Aprovação documental continua sem matrícula; nenhum registro legado foi convertido. Processos sem vínculo não exibem o componente.

Relatório: [INTEGRACAO-PMD-DOCUMENTAL-2026-10-05.md](INTEGRACAO-PMD-DOCUMENTAL-2026-10-05.md).

## Pendências implementadas — 05/10/2026

- Categorias documentais adicionais em configuração, importação, upload, presencial, análise e versões; nome/obrigatoriedade preservados por solicitação.
- Processos novos exigem ativação; legados mantêm compatibilidade até decisão explícita. Suspensão afeta novas liberações, preservando fases abertas.
- Conectores HTTP SMS/WhatsApp preparados, desativados e com corte explícito, idempotência e retry por canal. Requerem ponte compatível e validação externa.
- Relatório de retenção agregado sem exclusões; minuta e roteiro de aceite preparados. Política e revisão visual permanecem pendentes.
- Duas migrations aditivas aplicadas em local e testing; 98 testes aprovados e um aviso de depreciação legado, 941 asserções, sem falhas. SMTP local e HTTP revalidados.
- Relatório: [IMPLEMENTACAO-PENDENCIAS-2026-10-05.md](IMPLEMENTACAO-PENDENCIAS-2026-10-05.md).
