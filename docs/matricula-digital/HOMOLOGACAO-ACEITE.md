# Roteiro de homologação e aceite institucional

**Situação: pendente de execução visual e aceite.** Registrar ambiente, versão/commit, data, executor e evidências em cada linha. Dados fictícios para testes locais; homologação institucional em ambiente autorizado.

| Caso | Evidência automática | Resultado visual / responsável |
| --- | --- | --- |
| Formulário sem modalidade documental | RegistrationStageSeparationTest | Pendente |
| WAITING sem envio, mesmo com vínculo BC | RegistrationStageSeparationTest | Pendente |
| Deferimento sem matrícula | RegistrationStageSeparationTest | Pendente |
| Escolha exclusiva do responsável após deferir | RegistrationStageSeparationTest | Pendente |
| Online: upload e acesso privado | GuardianDocumentTest | Pendente |
| Presencial: recebimento sem arquivo obrigatório | ExtendedDocumentCatalogTest | Pendente |
| Categoria adicional configurável | ExtendedDocumentCatalogTest | Pendente |
| Correção e versão substituída preservadas | ExtendedDocumentCatalogTest | Pendente |
| Aprovação documental sem matrícula | RegistrationStageSeparationTest | Pendente |
| Efetivação única com serviço nativo e turma | RegistrationEndToEndTest | Pendente |
| Operador de outra escola bloqueado | PmdDocumentSummaryTest / testes BC | Pendente |
| Prazo comum e análise posterior de entrega tempestiva | RegistrationDeadlinePolicyTest | Pendente |
| Processo novo suspenso e legado compatível | ExtendedDocumentCatalogTest | Pendente |
| Três etapas no acompanhamento e resumo no detalhe PMD | Testes Vue / PmdDocumentSummaryTest | Pendente |
| SMTP, SMS/WhatsApp, idempotência e falhas | SMTP local / GuardianMessageDeliveryTest | Entrega externa pendente |

Repetir o formulário, acompanhamento e triagem em desktop e celular. Conferir legibilidade, foco/teclado, datas, estados vazios, arquivos inválidos e mensagens de erro. A ferramenta de navegador desta sessão voltou a falhar ao iniciar por ACL; não houve inspeção de telas renderizadas.

## Aceite

- Versão e ambiente: a preencher.
- Representante da Secretaria: a preencher.
- Política documental aprovada: a preencher.
- Provedores e evidências de comunicação externa: a preencher.
- Ressalvas, responsáveis e prazos: a preencher.
- Resultado: aprovado / aprovado com ressalvas / reprovado, a preencher.
- Data e assinatura/registro institucional: a preencher.

Não marcar como homologado por execução de testes automatizados.
