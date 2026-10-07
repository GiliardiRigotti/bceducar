# Adequação V3 — análise e validação de 06/10/2026

Fonte normativa: [BC-EDUCAR-CONTEXTO-MESTRE-V3.md](BC-EDUCAR-CONTEXTO-MESTRE-V3.md).

## Análise apresentada antes da implementação

1. **Regra V3:** aprovação documental e consolidação cadastral autorizam matrícula 11 e enturmação antecipada. Conferência física autoriza exclusivamente a promoção definitiva.
2. **Estado anterior:** `PhysicalConfirmation::integrate` criava situação 11 sem turma; `NativePhysicalGuard::assertConfirmed` bloqueava `EnrollmentService::enroll`; `confirm` promovia e enturmava; encerramento recusava vínculo enturmado.
3. **Arquivos:** PhysicalConfirmation, NativePhysicalGuard, RegistrationWorkflow, PmdRejectionSync, EnrollmentService, RegistrationService, LegacySchoolClass, RegistrationRequest, controlador BC e entradas legadas de matrícula/enturmação. Documentação e testes correspondentes.
4. **Métodos/serviços:** integrate, nativeRegistration, reserve, confirm, close, sync de rejeição, enroll, updateStatus, getActiveEnrollments, AvailableTimeService, edita/cadastra legados.
5. **Testes:** PhysicalConfirmationTest, NativePhysicalProtectionTest, RegistrationEndToEndTest, AvailableTimeServiceTest; regressões de separação documental, prazos, painel, dados declarados e movimentos nativos.
6. **Alterações:** enturmação nativa na integração; capacidade validada sob lock da turma; promoção preserva vínculo; cancelamento usa cancelRegistration; nomenclatura de vaga reservada; levantamento V2 somente leitura.
7. **Riscos:** concorrência, dupla contagem, vínculos alterados, bypass legado e consultas acadêmicas que devem continuar distinguindo reserva de matrícula definitiva.
8. **Compatibilidade V2:** manter workflow_version=2 como discriminador técnico da arquitetura documental existente. A versão normativa é V3. Sem conversão de dados em massa. Reprocessamento explícito da aprovação integra registros antigos sem turma, com as mesmas validações e transação.

## IMPLEMENTADO / CORRIGIDO

- Situação 11 enturmada após aprovação documental; `intermediate_registration_id` separado de `registration_id`.
- Uso de serviços nativos PMD para pré-matrícula e EnrollmentService para enturmação; sem entidades paralelas.
- Bloqueio da turma e matrícula no serviço de enturmação; capacidade BC respeitada mesmo quando a instituição permite exceder vagas no fluxo geral.
- Reprocessamento reutiliza vínculo existente; divergência não remove a reserva. Nomenclatura: “Vaga reservada — aguardando conferência física”; registros V2 sem vínculo mostram “enturmação pendente”.
- Efetivação exige enturmação válida, aprovação física de documentos/ficha e ausência de pendências. Preserva ID do vínculo, sem segundo enroll, incluindo turma exatamente na capacidade.
- Cancelamento/indeferimento desativa vínculos via RegistrationService; preserva aluno, pessoa, documentos e eventos. Repetição de encerramento não duplica auditoria.
- Caminhos legados: promoção passa pela guarda física; criação de vínculo BC delega ao serviço nativo protegido. A barreira cobre também situações acadêmicas definitivas além de cursando/aprovado/reprovado.
- Situação 11 incluída na relação de matrícula carregada pela listagem nativa de enturmações, evitando aluno sem identificação.
- Validação nativa de horários considera matrícula 11 ativa enturmada; cancelamento deixa de gerar conflito.

## MANTIDO

- WAITING sem modalidade/documentos/matrícula; deferimento separado da aprovação documental.
- Conferência física obrigatória e PMD ACCEPTED somente na efetivação.
- Consolidação segura, autorização, regras de calendário e INEP na efetivação.
- Matrícula definitiva não pode ser cancelada pela rejeição documental tardia.
- Consultas acadêmicas e filtros de notas/censo não foram globalmente ampliados.

## Capacidade real

`LegacySchoolClass::getTotalEnrolled` já contabiliza enturmações ativas sem dependência, sem excluir situação 11. `vacancies = max_aluno - getTotalEnrolled`.

A view PMD `public.classrooms` usa a mesma contagem em `available_vacancies`. `available` desconta adicionalmente pré-matrículas em estados 1/4, mas não IN_CONFIRMATION=5. Logo a reserva V3 é descontada uma vez. Nenhuma alteração SQL global foi necessária.

As verificações de integração usam PostgreSQL `testing`: contagem sobe 1; vagas nativas e PMD caem 1; divergência mantém vagas; cancelamento devolve 1; promoção mantém o ID e a ocupação.

## MIGRAÇÕES / levantamento V2

Comando: `docker compose exec -T php php artisan bc:report-intermediate-enrollments --no-ansi`.

Resultado no banco local em 06/10/2026: **quantidade 0**, registros `[]`. Consulta inclui todas as matrículas 11 ativas sem enturmação ativa, inclusive sem vínculo BC. Nenhum dado foi alterado. O comando informa matrícula, aluno, escola, série, ano, turno, turma pretendida, vagas, estado documental/integracional, conferência e necessidade de revisão.

Não há nova migration nem backfill automático. O reprocessamento explícito é o mecanismo idempotente para registros V2 compatíveis; falhas de capacidade ou validação deixam o vínculo preservado e impedem efetivação. Migração histórica de turno de 05/10 permanece restrita à sua regra V2, testada com fixture sem enturmação.

## PENDÊNCIAS / RISCOS RESIDUAIS

- Levantamento executado no ambiente local; repetir o comando no banco institucional antes de qualquer regularização de dados.
- Homologação institucional e visual desktop/mobile continuam pendências do contexto mestre.
- Filtros acadêmicos em EnrollmentStatusFilter continuam excluindo situação 11 deliberadamente; reserva não equivale a aluno definitivo para notas/censo.
- Escritas SQL externas e caminhos legados de outros alunos não usam necessariamente o lock do serviço. Não foi executado ensaio concorrente com múltiplas sessões.
- `git diff --check` global aponta espaços em linhas do patch PMD que já estava modificado antes desta tarefa; esse patch foi preservado.

## TESTES EXECUTADOS / RESULTADOS

Rodada ampliada inicial: 111 testes aprovados, 1 falha (1022 asserções). A falha revelou uma fixture nativa de lotação que alterava max_aluno apenas em memória; o teste agora persiste a capacidade porque o serviço relê a linha bloqueada do banco. Correção confirmada na rodada final: **65 testes aprovados, 445 asserções, 147,52 segundos**. Abrange PhysicalConfirmationTest, NativePhysicalProtectionTest, Feature/Services/EnrollmentServiceTest e Unit/Services/SchoolClass/AvailableTimeServiceTest.


- A rodada ampliada também aprovou RegistrationEndToEndTest, RegistrationStageSeparationTest, RegistrationDeadlinePolicyTest, RegistrationDashboardTest, DeclaredStudentDataTest, NativeEnrollmentMovementTest e LegacySchoolClassTest.
- Pint --test: **18 arquivos aprovados**. Diff específico de código e documentação V3: sem erros de whitespace.
- Os testes de capacidade usam as tabelas nativas e a view classrooms reais do PostgreSQL testing; não simulam a disponibilidade da turma.- Checagem final após as últimas validações da guarda: **5 testes aprovados, 98 asserções, 29,01 segundos** (reserva antecipada, duplicação, promoção proibida e rejeição PMD devolvendo vaga).
