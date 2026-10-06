# Implementação das pendências — 05/10/2026

## Resultado por onda

| Onda | Entrega técnica nesta etapa | Dependência restante |
| --- | --- | --- |
| 2 — Configuração | Categorias adicionais com códigos únicos, nomes históricos e suporte nos dois canais | Definir quais documentos reais serão exigidos por processo |
| 8 — Deferimento | Ativação explícita para processos novos e controle de novas liberações em legados | Administração selecionar os processos reais que serão ativados |
| 10 — Comunicação | Conectores HTTP SMS/WhatsApp, entregas e retries independentes; SMTP local revalidado | Ponte/provedor, credenciais, requisitos do canal e entrega externa |
| 12 — Homologação | Relatório de retenção sem exclusões, minuta e roteiro de quinze casos | Política aprovada, eventual executor de descarte conforme essa política, revisão visual e aceite |

Não houve ativação externa ou marcação de homologação. O usuário foi consultado sobre canais/provedores e política de retenção; essas informações não foram fornecidas nesta execução.

## Categorias documentais

O enum dos dez tipos originais foi preservado. DocumentCodeCast aceita os tipos canônicos como enum e códigos adicionais como DocumentCode, mantendo o código como string ao serializar modelos. Códigos cadastrados exigem letras maiúsculas, números e sublinhado, até cem caracteres, com unicidade.

A configuração agora recebe código livre validado. PmdIntake percorre a política configurada em vez de limitar-se ao enum. A solicitação guarda código, nome e obrigatoriedade; renomear ou inativar o catálogo não altera solicitações já abertas. O fallback dos processos legados sem política continua com os tipos originais.

Upload escolar e do responsável, recebimento presencial, correção, substituição e aprovação aceitam categorias adicionais. Somente tipos previstos na solicitação podem ser recebidos; códigos arbitrários são recusados no serviço, inclusive em chamadas diretas. Os formulários mostram os tipos da própria solicitação.

## Ativação e legado

- Processos existentes permanecem com document_workflow_enabled=null: compatibilidade com o comportamento anterior.
- Processos criados após a migration recebem false: o administrador precisa ativá-los antes de liberar novas fases documentais.
- true ativa novas liberações; false suspende novas liberações. Suspender não libera matrícula direta nem desativa as barreiras documentais.
- Solicitações BC já vinculadas e fases anteriores ao vínculo que já possuem estado documental continuam seu fluxo e prazo.
- Após uma decisão explícita, a tela não permite retornar ao modo null de compatibilidade.
- Ativação explícita pela configuração exige selecionar pelo menos um tipo ativo.
- Último ator e data são registrados no processo, com log de configuração. O operador escolar continua sem permissão para configurar.
- Nenhuma inscrição, rejeição histórica, matrícula ou prazo anterior foi convertido em massa.

Tela existente: /bc/matriculas/configuracao. Não há novos estados oficiais PMD nem mudanças no serviço nativo de matrícula.

## Comunicação

SendGuardianMessages usa a fila de avisos existente como origem e registra o estado de cada canal em bc_guardian_message_deliveries. A combinação notification_id/channel é única. Cada canal possui até cinco tentativas, espera progressiva e registro de aceitação pela ponte, sem alterar o estado de envio do e-mail.

Flags permanecem false. Um corte de data explícito é obrigatório para não reenviar o histórico. URLs fixas, HTTPS fora de local/testing, redirecionamentos recusados e chave de idempotência no cabeçalho/corpo. A ponte deve respeitar a chave; não houve comunicação com provedor real. Telefone inválido e avisos desatualizados não são enviados.

Comando bc:send-guardian-messages incluído no scheduler e no loop local bc-deadlines, recriado com overlay SMTP local preservado. A resposta HTTP 2xx significa aceitação da ponte, não confirmação de entrega ao telefone.

Contrato e operação: [OPERACAO-COMUNICACOES-EXTERNAS.md](OPERACAO-COMUNICACOES-EXTERNAS.md).

## Retenção e homologação

bc:document-retention-report --days=<prazo> produz somente contagens por categoria. Solicitações encerradas precisam ter atualização e documento anteriores ao corte. Solicitações abertas e arquivos anteriores ao vínculo são preservados. Não existe exclusão de arquivos ou registros.

Prazo institucional não foi inventado. A configuração permite registrar prazo/referência aprovados para o relatório; executor de descarte dependerá das regras de categoria, marcos, exceções, backups e autorização aprovadas.

[Minuta da política](POLITICA-DOCUMENTOS-MINUTA.md) e [roteiro de homologação](HOMOLOGACAO-ACEITE.md) preparados. O navegador voltou a falhar com helper_unknown_error: apply deny-read ACLs; nenhuma inspeção visual foi realizada.

## Migrations e arquivos

Migrations aditivas aplicadas em testing e local:

- 2026_10_05_190000_expand_document_catalog_and_activation: nome documental histórico, ativação por processo, ator/data da configuração. Compatibilidade para processos anteriores; default false somente para novas linhas.
- 2026_10_05_190001_create_guardian_message_deliveries: estado por canal, FK ao aviso e unicidade. Rollback recusa histórico de entregas; a anterior recusa categorias/decisões que precisariam ser preservadas.

Arquivos principais:

| Área | Arquivos |
| --- | --- |
| Categoria e persistência | app/EnrollmentRequests/{DocumentCode,DocumentCodeCast,PmdIntake,RegistrationWorkflow}.php; app/Models/RegistrationDocument.php |
| Portal e configuração | PmdGuardianDocuments; controllers GuardianDocumentController, BcRegistrationRequestController e PmdDocumentConfigurationController; views configuration, guardian e show |
| Canais | app/Console/Commands/SendGuardianMessages.php; app/EnrollmentRequests/GuardianMessageTransport.php; config/bc-messages.php |
| Retenção | app/Console/Commands/DocumentRetentionReport.php; config/bc-retention.php |
| Automação e ambiente | app/Console/Kernel.php; docker-compose.yml; .env.example; seeder PMD |
| Testes novos | ExtendedDocumentCatalogTest (6), GuardianMessageDeliveryTest (5), DocumentRetentionReportTest (2) |

Sem migrate:fresh, sem exclusão de dados ou arquivos e sem novo frontend Vue nesta etapa.

## Validação final

- **98 testes aprovados, um teste com aviso de depreciação legado, 941 asserções**, sem falhas, em dezessete suites no banco testing (151,91 s).
- Testes novos cobrem categoria online/presencial, correção, snapshot, código não solicitado, ativação, compatibilidade e default de processos; idempotência por canal, retry independente, corte, flags, telefone e redirecionamento; relatório sem exclusões.
- Aviso existente: criação de propriedade dinâmica clsBase::$titulo no transporte legado.
- SMTP local: nove tipos de aviso mais recuperação de falha, dez mensagens na execução final, sem duplicação e com rollback de fixtures.
- HTTP: portal, bundle/config PMD e quatro páginas principais respondem 200 para admin.seduc e op.medici.
- Lighthouse: schema válido. Pint --test aprovado nos 21 arquivos PHP da etapa.
- Logs: storage/logs/pending-final-regression.txt, pending-http-validation.txt e pending-smtp-validation.txt.

Essas evidências não representam entrega externa, política aprovada ou homologação municipal.
