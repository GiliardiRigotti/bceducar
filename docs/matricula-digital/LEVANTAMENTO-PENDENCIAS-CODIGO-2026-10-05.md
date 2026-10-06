# Levantamento de pendências — 05/10/2026

> Atualização em 06/10/2026: as cinco lacunas reproduzidas foram corrigidas, com regressões em tests/Feature/NativePhysicalProtectionTest.php. Monitoramento físico e prazo nos canais foram implementados. O pacote reproduzível está em patches/pmd. As demais evoluções e configurações continuam pendentes. O texto abaixo preserva as evidências do levantamento original.

## Resultado

O fluxo principal está implantado no Docker local, mas a implementação integral dos requisitos de perfil/ficha ainda não está concluída. A revisão encontrou lacunas nas integrações com caminhos nativos do i-Educar que devem ser corrigidas antes do uso operacional amplo da V2.

Este levantamento combina inspeção de código, documentos de requisitos, diagnóstico de configuração e cinco reproduções transacionais no banco `testing`. Não foram feitas correções no código de produção, alterações em dados reais ou comunicações externas nesta auditoria. A análise não certifica infraestrutura externa que não esteja representada neste repositório.

## O que já existe

- Pré-matrícula e deferimento no módulo PMD original; triagem documental posterior e restrita ao operador.
- Acompanhamento por protocolo/e-mail, verificação de acesso, perfil vinculado às inscrições verificadas e documentos privados.
- Aprovação/recusa individual de documentos, motivo de recusa, correções e prazos configuráveis de entrega digital/presencial.
- Ficha declarada parcial e conferência cadastral; integração com pessoa, aluno e responsável nativos com proteção contra associação ambígua.
- V2: aprovação digital, matrícula intermediária nativa em situação 11, conferência física individual e efetivação explícita com enturmação.
- Compatibilidade dos registros anteriores na versão 1; novas liberações usam versão 2, sem conversão massiva do histórico.
- Duas opções de escola, endereço informado sem confirmação de GPS, mapa das unidades disponíveis cadastradas, identidade municipal e ajustes de apresentação.
- Filas de e-mail com protocolo; infraestrutura de conectores SMS/WhatsApp existente, atualmente desativada.

## Prioridade 0 — integridade do fluxo

### 1. Impedir conclusão antecipada pelos serviços nativos

**Confirmado por duas reproduções.** `app/Services/EnrollmentService.php:187` permite enturmar o vínculo intermediário antes da conferência física. `app/Services/RegistrationService.php:86` permite promover a situação 11 para 3 sem essa conferência. A solicitação BC e o PMD continuam sem a conclusão correspondente.

O controlador nativo de enturmação usa o mesmo serviço. Trata-se de uma lacuna entre caminhos administrativos autorizados, não de acesso anônimo demonstrado.

**Falta implementar:** proteção compartilhada para vínculos intermediários associados à V2, aplicada nos caminhos nativos de promoção/enturmação. A confirmação física autorizada deve continuar funcionando de forma transacional; matrículas nativas sem vínculo BC e histórico precisam manter seu comportamento.

### 2. Sincronizar o indeferimento no PMD original

**Confirmado por reprodução.** `packages/portabilis/pre-matricula-digital/src/GraphQL/Mutations/RejectPreRegistrations.php` altera o PMD para rejeitado, mas mantém a solicitação BC aprovada e a matrícula intermediária 11 ativa. `app/Providers/BcPmdServiceProvider.php` integra o deferimento, sem integração equivalente para esse indeferimento.

**Falta implementar:** sincronização dos caminhos originais de rejeição com o encerramento BC e o cancelamento nativo do vínculo intermediário. Revisar também rejeições concorrentes em `app/EnrollmentRequests/PmdBridge.php`. Preservar pessoa/aluno e matrículas efetivas; não desfazer automaticamente uma matrícula oficial já concluída.

### 3. Tornar a implantação reproduzível fora deste computador

O código está ativo pelo ambiente local, mas arquivos novos BC, incluindo a implementação de conferência física, sua migration e testes, não estão rastreados no Git principal na consulta realizada. O diretório de pacotes é ignorado em `.gitignore`; o pacote PMD tem alterações locais. `composer.json` usa repositório de caminho, e `scripts/install-pmd.ps1` não restaura todas essas adaptações a partir de uma instalação limpa.

**Falta preparar:** versionamento dos arquivos BC e uma estratégia versionada para o pacote alterado — fork, submódulo ou patches reproduzíveis — com revisão fixa. Validar instalação limpa, compilação dos recursos, migrations, backup e retorno de versão. A CI em `.github/workflows/tests.yml` existe, mas precisa reproduzir esse conjunto específico. Não houve commit ou publicação durante o levantamento.

## Prioridade 1 — regras e funcionalidades pendentes

| Pendência | Evidência no código | Implementação necessária |
|---|---|---|
| Correção cadastral após prazo de regularização | `app/EnrollmentRequests/DeclaredStudentData.php:106`; reprodução aceita alteração após vencimento em `bc_physical_reviews` | Validar o prazo aplicável no envio da ficha e permitir reabertura apenas conforme ação motivada do operador. |
| Acompanhamento automático dos prazos físicos | `app/Console/Commands/ExpireRegistrationRequests.php:21` e `QueueGuardianDeadlineReminders.php:26` consideram prazo documental, sem rotina equivalente de prazo físico | Expor vencimentos e regularizações na operação e comunicar o responsável. Definir tratamento do vencimento sem cancelamento automático indevido. Documentos conferidos dentro do prazo podem permitir conclusão posterior. |
| Turno da matrícula intermediária | `packages/portabilis/pre-matricula-digital/src/Services/EnrollmentService.php:110`; reprodução mostra `turno_pre_matricula` nulo | Gravar o turno nativo validado. A fila por turno em `ieducar/intranet/educar_matricula_cad.php:1012` filtra esse campo, excluindo o vínculo criado sem turno. Avaliar regularização dos vínculos existentes com escopo controlado. |
| Dependentes persistentes e reutilização em novo processo | `app/EnrollmentRequests/GuardianProfiles.php`; `resources/views/bc-registration/profile.blade.php` agrupa snapshots PMD e abre uma nova inscrição genérica | Cadastro independente de dependentes, revisão/reutilização de dados em outro processo e vínculo seguro. A correção que clona snapshots pode separar visualmente inscrições da mesma criança. |
| Perfil desde o primeiro envio e edição de contatos | Perfil é vinculado após acesso verificado em `GuardianDocumentController`; rotas de perfil não oferecem edição/reverificação dos contatos | Completar o perfil provisório previsto nos requisitos e edição de contatos com verificação. Não associar inscrições automaticamente apenas pela coincidência de e-mail/CPF. |
| Ficha cadastral completa | `DeclaredStudentData.php:16` limita a edição a oito campos; `declared-data.blade.php` mantém responsável/endereço como consulta | Completar campos adicionais previstos na matriz: filiação/responsáveis, endereço, nacionalidade/naturalidade, raça/cor, NIS, nome social, dados complementares de identidade/certidão e necessidades. Aplicar validações consistentes, inclusive CPF. |
| Comparação e atualização autorizada de cadastro oficial | `declared-review.blade.php` não apresenta comparação completa com o cadastro nativo; `NativeStudentConsolidation.php:30` retorna quando já existe `student_id` | Exibir declarado versus oficial, selecionar alterações autorizadas e registrar antes/depois. Correção após integração precisa de reconciliação explícita, preservando a proteção atual contra sobrescrita silenciosa. |

**Movimentos na V2:** `PhysicalConfirmation.php:200` bloqueia a confirmação quando já existe matrícula ativa no mesmo ano. Hoje esse caso exige tratamento pelo fluxo nativo. Transferência/remanejamento da versão 1 existe. Se a automação desses movimentos na V2 fizer parte do escopo, integrar os serviços nativos com testes; não criar matrícula duplicada para contornar o bloqueio.

## Prioridade 2 — comunicação, dados e experiência

- **Central de comunicações e histórico ampliado:** faltam a central por responsável/dependente e a composição do histórico completo PMD + documentação + matrícula. As telas atuais exibem históricos parciais de suas etapas.
- **Prazo físico em SMS/WhatsApp:** `app/Console/Commands/SendGuardianMessages.php:61` escolhe prazo de correção ou prazo documental, sem tratar `PHYSICAL_REQUIRED` com o prazo físico da metadata. Corrigir antes de ativar esses canais. A implementação atual registra aceitação HTTP pelo conector, não confirmação de entrega ao destinatário.
- **Catálogo municipal completo:** `resources/data/bc-school-locations.json` e `ESCOLAS-ENDERECOS-MAPAS.md` documentam um escopo de 15 unidades existentes, não toda a rede. Completar catálogo oficial e configuração de oferta/vagas antes de ampliar a operação. A coordenada da entrada do NEI Pioneiros ainda precisa de validação; não inventar número/CEP.
- **Validação visual e acessibilidade:** revisão em desktop/celular e aceite dos operadores/responsáveis permanecem pendentes. Testes HTTP de renderização não substituem essa revisão. A tentativa anterior de navegador automatizado foi bloqueada por erro de ACL do sandbox Windows.

## Preparação para produção

`php artisan bc:check-readiness` encontrou:

| Item | Estado local verificado |
|---|---|
| HTTPS da aplicação | Pendente |
| SMTP com TLS | Pendente |
| Tiles próprios com HTTPS | Pendente |
| Referência à política de retenção aprovada | Pendente |
| Geocoder automático opcional | Desativado |
| SMS / WhatsApp | Desativados |

SMTP e canais opcionais precisam de configuração e homologação de entrega, não de reimplementação integral. O verificador examina configuração, sem comprovar entrega ou capacidade operacional.

A minuta de retenção e o relatório de documentos existem, mas o relatório apenas contabiliza dados. Falta aprovar a política institucional e, se previsto nela, implementar execução controlada de retenção, incluindo arquivos privados e histórico. Não foi encontrada automação completa de backup/restauração de banco e documentos no escopo verificado; validar também o que existir na infraestrutura externa.

As migrations locais estão aplicadas, sem migration pendente. A implantação confirmada é `http://localhost:8080`; não há evidência de implantação externa homologada neste levantamento.

## Evidências e limites dos testes

O delta V2 teve validação anterior de **77 testes / 897 assertions**. Nesta auditoria foi criado `scripts/audits/WorkflowGapAuditTest.php`, fora da suíte normal, com **cinco diagnósticos / 26 assertions** que reproduzem as lacunas descritas. Seu resultado aprovado significa que o problema foi reproduzido; não significa aceite dessas regras.

```powershell
docker exec ieducar-php php vendor/pestphp/pest/bin/pest tests/Feature/NativePhysicalProtectionTest.php --compact
```

O diagnóstico reutiliza fixture que exige banco `testing`, transações e e-mails simulados. Não modifica dados reais. As reproduções foram convertidas em testes de comportamento esperado na suíte normal; o script diagnóstico original foi removido. O comando acima executa agora os testes de regressão.

Ainda faltam testes de aceite nos caminhos nativos, prazo físico/regularização, ficha completa, dependentes reutilizáveis e comunicação física nos canais opcionais. Validar concorrência real e carga; repetição sequencial/idempotência não comprova comportamento de múltiplos operadores simultâneos.

## Ordem recomendada

1. Proteger os serviços nativos e sincronizar o indeferimento original.
2. Corrigir prazo de regularização, acompanhamento físico e turno do vínculo intermediário.
3. Garantir versionamento e instalação limpa reproduzível.
4. Completar dependentes, perfil, ficha e atualização autorizada do cadastro oficial.
5. Ampliar comunicações/catálogo, revisar telas e homologar operação.
6. Preparar produção com HTTPS, entrega de mensagens, retenção e restauração validada.

Não é adequado declarar um percentual ou número fechado de ondas sem dimensionar essas correções e o escopo de implantação municipal.
