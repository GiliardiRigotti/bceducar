# Matriz de mapeamento cadastral do i-Educar

Data: 05/10/2026. Levantamento feito no código instalado, antes de alterações do formulário.

## Entidades e serviços encontrados

- Declarações: PMD `Person` em `people`, `PersonAddress` em `person_addresses`, campos adicionais por inscrição. Não são ficha oficial.
- Pessoa oficial: `LegacyPerson` (`cadastro.pessoa`) e `LegacyIndividual` (`cadastro.fisica`).
- Aluno oficial: `LegacyStudent` (`pmieducar.aluno`), ligado pela pessoa.
- Documentos cadastrais: `LegacyDocument` (`cadastro.documento`).
- Endereços: `Place` e `PersonHasPlace`; telefone: `LegacyPhone`.
- Serviço nativo PMD: `Services/EnrollmentService.php`, com `FindOrCreatePerson`; matrícula e enturmação usam entidades nativas e regras de transferência/remanejamento.
- Localização de pessoas atual: CPF, RG, certidão, nome/data. A última alternativa não é prova de identidade e não deve autorizar vínculo de responsável.
- O serviço atual não atualiza automaticamente pessoa encontrada e não consolida todos os campos adicionais. Portanto a consolidação ampliada exige comparação e autorização explícita.

## Campos

Obrigatoriedade abaixo distingue campo nativo disponível de obrigatoriedade do processo. Comprovantes dependem da configuração documental, não de exigência fixa inventada.

| Campo exibido | Origem PMD/BC | Entidade i-Educar | Campo/serviço nativo | Obrigatório | Editável responsável | Exige comprovante | Validação escolar | Regra de atualização |
|---|---|---|---|---|---|---|---|---|
| Nome | people.name | LegacyPerson | nome / FindOrCreatePerson | Sim | Declaração | Conforme processo | Identidade | Comparar existente; consolidar na efetivação |
| Nome social | Campo adicional a mapear | LegacyIndividual | nome_social | Não | Declaração | Conforme processo | Conferência | Sem sobrescrita silenciosa |
| CPF | people.cpf | LegacyIndividual | cpf | Conforme processo | Declaração | Conforme processo | Identidade e duplicidade | Normalizar; não autoriza vínculo sozinho |
| Nascimento | people.date_of_birth | LegacyIndividual | data_nasc | Conforme processo | Declaração | Conforme processo | Identidade | Comparar antes de atualizar |
| Sexo | people.gender | LegacyIndividual | sexo | Conforme processo | Declaração | Conforme processo | Conferência | PMD 1=F, 2=M; ausência preservada |
| Nacionalidade | Campo adicional a mapear | LegacyIndividual | nacionalidade / idpais_estrangeiro | Conforme regra nativa | Declaração | Conforme processo | Conferência | Usar catálogo nativo |
| Naturalidade | people.place_of_birth | LegacyIndividual | idmun_nascimento | Conforme processo | Declaração | Conforme processo | Conferência | Cidade nativa; UF derivada |
| Raça/cor | Campo adicional a mapear | LegacyIndividual | races / cadastro.fisica_raca | Conforme processo | Declaração | Conforme processo | Conferência | Usar catálogo nativo |
| NIS | Campo adicional a mapear | LegacyIndividual | nis_pis_pasep | Não | Declaração | Conforme processo | Conferência | Não expor em logs técnicos |
| RG | people.rg | LegacyDocument | rg, idorg_exp_rg, sigla_uf_exp_rg, data_exp_rg | Conforme processo | Declaração | Conforme processo | Conferência | Não confundir arquivo e dado cadastral |
| Certidão | people.birth_certificate | LegacyDocument | certidao_nascimento; campos de termo/livro/folha | Conforme processo | Declaração | Conforme processo | Conferência | Comparar documento e declaração |
| Filiação | responsible + relation_type_id; outros campos adicionais | LegacyIndividual | idpes_mae, idpes_pai, nome_mae, nome_pai | Conforme processo | Declaração | Conforme processo | Vínculo | Não presumir estrutura familiar |
| Responsável legal | responsible_id / people | LegacyIndividual + LegacyStudent | idpes_responsavel, tipo_responsavel | Sim | Declaração | Conforme processo | Vínculo legal | Conta autenticada não prova todos os vínculos |
| E-mail | people.email | LegacyPerson | email | Responsável: sim | Com verificação | Não | Conferência | Conta e contato cadastral distintos |
| Telefone/celular | people.phone / mobile | LegacyPhone | ddd, fone, tipo | Conforme processo | Declaração | Não | Conferência | Tipos nativos 1 fixo, 2 celular, 3 alternativo |
| CEP/logradouro/número/complemento/bairro | person_addresses | Place | postal_code, address, number, complement, neighborhood | Conforme processo | Declaração | Conforme processo | Endereço | Sem exigir confirmação GPS |
| Município/UF | person_addresses.city | City + Place | city_id / state | Conforme processo | Declaração | Conforme processo | Endereço | Resolver município e UF sem ambiguidade |
| Zona residencial | Campo adicional a mapear | LegacyIndividual | zona_localizacao_censo | Conforme regra nativa | Declaração | Conforme processo | Endereço | Catálogo nativo |
| Coordenadas | person_addresses.latitude/longitude | Place | latitude/longitude | Não | Endereço suficiente | Não | Opcional | Nunca inventar posição |
| Ano/série/turno/escolas | preregistrations / process | LegacyRegistration + LegacyEnrollment | EnrollmentService / Classroom | Sim | Antes do envio | Não | Oferta e vaga | Preservar PMD e máximo de duas escolas |
| Deficiência e necessidades | Campo adicional a mapear | LegacyIndividual | deficiency / cadastro.fisica_deficiencia | Só quando necessário | Declaração restrita | Conforme processo | Acesso restrito | Sem coleta genérica adicional |
| Contato de emergência | Não demonstrado no PMD | Não definido neste levantamento | Não implementar campo sem destino validado | Não | Não definido | Não | Pendente | Não criar entidade oficial paralela |

## Diferenças encontradas e ordem de implementação

1. A sessão atual autoriza um protocolo. Persistir conta e vínculos comprovados por protocolo + OTP; recuperação por e-mail deve recuperar somente vínculos previamente comprovados.
2. Atualmente PmdIntake cria pessoa/aluno no deferimento. Necessário tornar referências nativas opcionais e preparar apresentação/filtros para dados declarados antes de mover criação à efetivação.
3. A aprovação geral atualmente chama approveAndEnroll. Separar aprovação e efetivação explícita conforme documento de perfil.
4. Não vincular pessoas pelo nome/CPF/data de nascimento do formulário. Casos incertos exigem resolução escolar.
5. Não sobrescrever cadastros existentes sem comparação, decisão autorizada e auditoria restrita.
6. Formulário ampliado, conferência cadastral, reutilização e consolidação ainda exigem implementação e testes próprios. Esta matriz não declara essas funcionalidades concluídas.

Fontes locais: app/Models/Legacy{Person,Individual,Student,Document,Phone}.php; packages/portabilis/pre-matricula-digital/src/Models/{Person,PersonAddress}.php; src/Services/Concerns/FindOrCreatePerson.php; src/Services/EnrollmentService.php; app/EnrollmentRequests/{PmdIntake,RegistrationWorkflow}.php.


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


## Delta V2 — integração e conferência física (05/10/2026)

Inspeção local confirmou RegistrationStatus::PRE_REGISTRATION = 11, PMD IN_CONFIRMATION = 5, RegistrationService::updateStatus e EnrollmentService::enroll. A criação intermediária reutiliza createRegistration do serviço PMD, com situação explícita 11 e sem enturmação. Promoção usa updateStatus; enturmação usa serviço nativo. Identidades passam pela consolidação e conferência já existentes, sem sobrescrever cadastros oficiais.

Migração preserva solicitações existentes como workflow_version=1. Novas liberações documentais usam versão 2; intermediate_registration_id não é registration_id definitivo. Aprovação digital é persistida antes da tentativa de integração; falhas ficam auditadas por classe técnica, sem dados pessoais, e podem ser reprocessadas. Conferência física individual e pendências cadastrais bloqueiam a confirmação até regularização. Prazos físicos são definidos no processo e copiados para a solicitação; alterações de configuração não reiniciam prazos existentes.


Estado final V2: erro de integração mantém PMD SUMMONED; sucesso sincroniza IN_CONFIRMATION. Matrícula intermediária nativa existente só é vinculada quando única, compatível, sem enturmação e sem outro vínculo BC. Dados oficiais, autoria e observação existentes são preservados. O operador registra divergência por documento ou ficha, e a confirmação física exige todos os obrigatórios e cadastro conferidos, com ausência de pendências. Cadastro divergente reabre a ficha e a aprovação digital. Situação 11 só é promovida via RegistrationService após validações e enturmação nativa dentro da mesma transação. Matrícula ativa no mesmo ano exige tratamento nativo separado, preservando o fluxo histórico de movimentos.
