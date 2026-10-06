# Integração documental no detalhe nativo PMD — 05/10/2026

## Diagnóstico e escopo

A triagem BC já apresentava documentos e o acompanhamento público já distinguia três etapas. O detalhe nativo da pré-matrícula ainda não mostrava o resumo documental nem oferecia navegação direta à triagem. A API nativa tinha status e prazo, mas não um resumo exclusivo para operadores.

Implementação incremental: acrescentar um campo GraphQL opcional e um componente de leitura no modal existente. Reutilizar RequestAccess, os documentos e a rota de detalhe BC. Preservar as mutações e o serviço nativo de matrícula. Processos sem vínculo BC mantêm o comportamento atual; nenhuma ativação documental retroativa é aplicada.

## Alterações

- Campo aditivo PreRegistration.documentarySummary: BcDocumentSummary, nullable, resolvido por app/EnrollmentRequests/PmdDocumentSummary.php.
- Exige usuário ativo da sessão web e autorização da escola. O token público PMD sozinho recebe null. Consulta de outra escola não retorna a pré-matrícula pelo escopo original e o resolver repete a autorização.
- Informa situação, modalidade ou espera de escolha, prazo, totais dos obrigatórios atuais, recebidos no prazo, aprovados com recebimento tempestivo, correções e matrícula efetivada.
- WAITING com vínculo BC continua em análise inicial e não oferece triagem, prazo ou contagens documentais.
- Não contém nome/caminho de arquivo, motivo de correção ou links de download; downloads privados continuam nas rotas autorizadas existentes.
- Modal nativo inclui DocumentarySummary.vue e consulta o novo campo; oferece acesso direto ao detalhe BC.
- A aprovação documental permanece distinta da efetivação. O resumo não executa mutações ou cria matrícula.
- Texto de seleção de turma deixa de sugerir matrícula imediata; situação ACCEPTED no modal é exibida como Matrícula efetivada.

## Arquivos

| Área | Arquivo |
| --- | --- |
| API e autorização | app/EnrollmentRequests/PmdDocumentSummary.php |
| Schema aditivo | packages/portabilis/pre-matricula-digital/graphql/preregistration.graphql |
| Contrato e consulta | packages/portabilis/pre-matricula-digital/resources/ts/modules/preregistration/types.ts; api/services/graphql/preregistration.ts |
| Interface | packages/portabilis/pre-matricula-digital/resources/ts/modules/preregistration/components/DocumentarySummary.vue e PreRegistrationModal.vue |
| Backend tests | tests/Feature/PmdDocumentSummaryTest.php |
| Frontend tests | packages/portabilis/pre-matricula-digital/tests/geo/documentary-summary.test.ts |

## Estados, rotas e compatibilidade

Nenhum status ou rota novos. Campo GraphQL aditivo em POST /graphql; navegação escolar existente /pre-matricula-digital/inscricoes → modal da pré-matrícula → /bc/matriculas/{id}. Nenhuma migration ou atualização em massa de registros.

documentarySummary pode ser null por ausência de autorização ou vínculo. O frontend antigo continua compatível, e o componente novo não aparece sem resumo. Publicação do novo frontend depende de schema atualizado e cache GraphQL limpo.

## Limites e próximo trabalho

A integração desta etapa cobre o detalhe escolar nativo. Não substitui o portal autenticado do responsável nem implementa upload no formulário público. Regras de ativação para processos legados sem configuração documental continuam pendentes de definição e validação institucional. Integrações que consomem o novo campo devem tratar null.

Validação visual continua pendente pela falha de inicialização das ferramentas (ACL do ambiente), registrada nas etapas anteriores. A conclusão técnica não representa homologação.

## Validação final

- Backend: **38 testes aprovados, 475 asserções** em cinco suites (57,21 s): PmdDocumentSummaryTest, RegistrationStageSeparationTest, RegistrationDashboardTest, RegistrationEndToEndTest e GuardianDocumentTest.
- A suite nova possui sete testes: consulta escolar autorizada, token público, outra escola/usuário inativo, vínculo WAITING, ausência de vínculo legado, versões substituídas/opcionais e recebimento tardio.
- Frontend: **21 testes aprovados** em sete arquivos, incluindo cinco cenários do resumo; build Vite concluído. Normalização do prazo nativo para leitura consistente pelo componente.
- Pint --test aprovado nos dois arquivos PHP; Lighthouse schema válido; cache GraphQL limpo.
- Frontend publicado com vendor:publish --tag=pmd --force. Portal e bundle local HTTP 200; consulta GraphQL local reconhece o contrato e não retorna resumo escolar ao token público.
- Logs: storage/logs/pmd-summary-regression.txt e storage/logs/pmd-summary-frontend.txt.
- Avisos de Sass, Browserslist e tamanho de chunk permanecem no build, sem falhas. Revisão visual não realizada; não declarar homologação.
