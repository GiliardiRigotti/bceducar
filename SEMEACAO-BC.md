# Massa de testes BC Educar e integração PMD

## Executar

Com os serviços Docker ativos e as migrações aplicadas:

```powershell
docker compose exec php php artisan db:seed
docker compose exec php php artisan db:seed --class=BalnearioCamboriuDemoSeeder
```

Para instalar o PMD em outro checkout, execute `scripts/install-pmd.ps1`. O script fixa a revisão do pacote, ajusta Frontier para a versão do host, migra primeiro todos os diretórios do núcleo e depois o pacote, configura Balneário Camboriú, publica o frontend e valida o schema GraphQL. Não executa o instalador destrutivo `prematricula:install`.

O CommonMark do host foi atualizado para 2.10.3, sem desabilitar a proteção de avisos de segurança do Composer. Referência: [release oficial](https://commonmark.thephpleague.com/releases/).

## Acessos locais

- Portal PMD: http://localhost:8080/pre-matricula-digital

- Documentos do responsável: http://localhost:8080/matricula-digital (exemplo fictício local: `/matricula-digital/demo`)
- Inscrições administrativas PMD: http://localhost:8080/pre-matricula-digital/inscricoes
- Documentação e auditoria: http://localhost:8080/bc/matriculas
- Inscrições PMD ainda sem vínculo documental: http://localhost:8080/bc/matriculas/pmd

Faça login no i-Educar antes de abrir a área administrativa. O atalho de login automático do PMD foi substituído pela verificação da sessão, preservando o escopo do operador.

| Login de teste | Escopo |
| --- | --- |
| `admin.seduc` | Toda a instituição BC |
| `op.medici` | CEM Presidente Médici |
| `op.ivosilvei` | CEM Governador Ivo Silveira |
| `op.duas` | Médici e Ivo Silveira |

Existe um operador para cada uma das 15 unidades. O limite nativo de login é de 12 caracteres; os nomes sugeridos `operador.*` foram adaptados para `op.*`. A senha exclusivamente de teste é `Teste@2026`, configurável por `BC_DEMO_PASSWORD` antes da primeira execução. A reexecução preserva contas e senhas existentes; não funciona como troca de senha.

## Conteúdo

O seeder independente cria 1 instituição, 15 escolas, 2 cursos, 15 etapas, 186 turmas, 100 responsáveis, 150 alunos, 17 usuários e 200 solicitações. Inclui turmas lotadas, com uma vaga, próximas da capacidade, com muitas vagas e vazias; documentos pendentes, enviados, em análise, aprovados, rejeitados, com correção solicitada e substituídos; expiração, não comparecimento, transferência de ingresso e rematrícula com registros anteriores; 5 alunos com vínculo nativo de necessidade específica fictícia.

As 200 solicitações são vinculadas a inscrições no PMD oficial; 15 já possuem matrícula e enturmação nativas, nos dois canais. Documentos corrigidos possuem versão anterior preservada e histórico até a aprovação. Todos os eventos são distribuídos no período de 60 dias anterior a `BC_DEMO_REFERENCE_DATE` (padrão `2026-10-03`). Datas de prazo incluem hoje, amanhã, futuro, ontem e sete dias atrás. Os números do relatório são consultados no banco.

Alunos e responsáveis usam nomes explicitamente fictícios, emails `@teste.bc.local`, endereços `Rua Teste BC ...`, sem CPF, CNS ou telefones reais. PMD mantém seus próprios snapshots de entrada, ligados às pessoas legadas por `external_person_id`, sem criar outra estrutura de matrícula definitiva. As coordenadas das escolas são de demonstração, não um levantamento das unidades reais.

Os PDFs mock ficam em `storage/app/testing/registration-documents`, no disco privado `registration-documents`. Arquivos e versões somente podem ser baixados por uma sessão autorizada para a escola. Nenhum documento pessoal real é utilizado.

## Fluxo integrado

O processo demo não solicita escolha online/presencial no formulário público. O campo histórico é ocultado, sem apagar respostas anteriores. A modalidade fica vazia até escolha exclusiva do responsável após deferimento. Na área `/bc/matriculas/pmd`, o operador inicia a fase documental de uma pré-matrícula autorizada para deferimento e seleciona turma compatível com escola, série, turno e ano. A escolha registrada pelo responsável é preservada; o prazo é de sete dias em ambos os canais. A operação é idempotente e reutiliza aluno/pessoa nativos.

Na tela documental é possível receber arquivos, registrar entrega presencial, analisar, solicitar correção, substituir mantendo o histórico, aprovar, indeferir/cancelar e efetivar. A aprovação documental sozinha não cria matrícula. A efetivação utiliza modelos nativos em transação, bloqueia concorrência por aluno e turma, verifica capacidade e impede duplicação. A mutação de aprovação do PMD também exige a fase documental aprovada; inscrições sem esse vínculo precisam iniciar a documentação primeiro.

Os estados finais são sincronizados com o PMD. A Fila Única não foi reinventada: cenários de origem usam referências mock `TEST-FU-*`; entradas de lista de espera continuam no PMD. Transferências com matrícula ativa no mesmo ano são encaminhadas ao fluxo nativo de transferência, sem criar uma segunda matrícula silenciosamente.

```powershell
docker compose exec php php artisan bc:expire-registration-requests
```

O comando registra expiração/não comparecimento uma única vez. No Docker local, o serviço `bc-deadlines` o executa a cada cinco minutos; em outros ambientes, programe o comando equivalente.

Avisos ao responsável de correção, lembrete nas 24 horas antes do prazo, prazo encerrado e matrícula efetivada usam uma fila transacional. Os comandos `bc:queue-deadline-reminders` e `bc:send-guardian-notices` são executados pelo serviço `bc-deadlines` a cada cinco minutos. Configure e valide o SMTP antes de ativar `BC_GUARDIAN_NOTIFICATIONS_ENABLED=true`. Dados fictícios da semeação não disparam e-mails.

O portal público de inscrição continua sendo o PMD oficial. Com uma inscrição aberta, o responsável pode acessar `/matricula-digital` usando protocolo, e-mail cadastrado e código de acesso, mesmo antes de a escola vincular a turma. Ali escolhe a forma de entrega e pode enviar os documentos configurados para o processo. Na importação escolar, os arquivos, seus hashes e o prazo são preservados. Em `APP_ENV=local`, `/matricula-digital/demo` abre somente uma solicitação fictícia da massa de testes. O e-mail real precisa de transporte configurado fora do ambiente local.

## Proteção e reexecução

Execução permitida somente em `local`, `testing`, `staging` ou `homologation`; produção é bloqueada mesmo com `--force`. A criação é transacional, com trava de execução e manifesto `bc_demo_entities` identificado por `BC_DEMO_2026`. Reexecutar valida/reutiliza a massa existente, sem limpar tabelas nem duplicar escolas, alunos ou matrículas. Dados anteriores não são apagados. O `DemoSeeder.php` original permanece intacto.

## Verificação

Use banco separado `testing`, migre primeiro o núcleo e depois o PMD, execute o seeder básico e o BC. Não execute `migrate:fresh` no banco do sistema local.

```powershell
docker compose exec php vendor/bin/pest tests/Feature/BalnearioCamboriuDemoTest.php
docker compose exec php php artisan lighthouse:validate-schema
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify-bc-http.ps1
```

A suíte valida volumes/idempotência, bloqueio em produção, isolamento de lista/detalhe/download/escrita, preservação da sessão PMD, documentos obrigatórios, substituição e auditoria, efetivação idempotente, sincronização PMD, lotação, prazo nos dois canais e importação sem duplicar pessoas/alunos.

Validação local em 03/10/2026 após a revisão do plano: 34 testes aprovados, com 416 assertions. O schema GraphQL já havia sido validado e não foi alterado nesta revisão. A checagem HTTP anterior confirmou `/matricula-digital` e o exemplo fictício local. Um teste adicional percorre da inscrição pública oficial PMD à matrícula e enturmação nativas. O painel permite exportar o resumo CSV com os filtros e o escopo escolar do operador. Os testes de prazo confirmam que vencimento não indefere automaticamente e que análise escolar posterior preserva o recebimento tempestivo. A interação visual no navegador não foi validada, pois a automação do navegador ficou indisponível neste ambiente.

Os mapas usam Leaflet e tiles configuráveis, sem chave Google. A localização residencial pode ser confirmada manualmente; busca automática exige uma instância própria/municipal de geocoding. Veja [a migração de mapas](docs/mapas/MIGRACAO-MAPAS.md). Froala continua dependendo de chave própria quando utilizado, seguida de rebuild/publicação do frontend. O município usa o código IBGE `4202008` ([IBGE](https://geoftp.ibge.gov.br/cartas_e_mapas/mapas_municipais/colecao_de_mapas_municipais/2020/SC/balneario_camboriu/4202008_MM.pdf)).

Detalhes do fluxo vigente: [Ajuste de fluxo](docs/matricula-digital/AJUSTE-FLUXO-2026-10-05.md).
