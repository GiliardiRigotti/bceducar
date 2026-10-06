# Política institucional de documentos — minuta para preenchimento

**Situação: não aprovada.** Esta minuta organiza decisões pendentes; não define prazo legal nem autoriza exclusões.

| Definição necessária | Decisão da Secretaria |
| --- | --- |
| Identificador e versão da política | A preencher |
| Autoridade que aprova e responsável operacional | A preencher |
| Finalidade por categoria documental | A preencher |
| Prazo e marco inicial de retenção por categoria | A preencher |
| Tratamento de documentos médicos/sensíveis | A preencher |
| Solicitações em andamento, recursos e bloqueios de descarte | A preencher |
| Tratamento de versões substituídas e histórico | A preencher |
| Tratamento de backups e cópias exportadas | A preencher |
| Atendimento a pedidos de titulares e canal de contato | A preencher |
| Método e autorização de descarte | A preencher |
| Registro de evidências e auditoria do descarte | A preencher |
| Revisão periódica e data da próxima revisão | A preencher |

## Ferramenta técnica disponível

```powershell
docker compose exec -T php php artisan bc:document-retention-report --days=180
```

180 é apenas um exemplo de simulação, não uma recomendação de prazo. O relatório é agregado por código documental, sem nomes/protocolos/caminhos. Identifica referências de arquivos associadas a solicitações encerradas cuja última atualização e criação do documento ultrapassam o prazo simulado. A existência física e eventuais impedimentos precisam ser conferidos na avaliação institucional.

Solicitações abertas e arquivos anteriores ao vínculo BC não são candidatos neste relatório. Versões substituídas de solicitações encerradas são incluídas para avaliação. Não há exclusão de arquivos ou registros, automática ou manual, nesse comando.

Após a política ser definida, os parâmetros BC_DOCUMENT_RETENTION_DAYS, BC_DOCUMENT_RETENTION_APPROVED e BC_DOCUMENT_RETENTION_POLICY_REFERENCE permitem documentar a configuração de um prazo uniforme para relatório. Eles não representam uma assinatura institucional nem habilitam descarte.

Se a política exigir prazos diferentes por categoria, marcos jurídicos específicos ou bloqueios de retenção, implementar essas regras somente depois de receber a decisão aprovada. O executor de descarte permanece pendente, pois os critérios e a autorização não foram fornecidos.
