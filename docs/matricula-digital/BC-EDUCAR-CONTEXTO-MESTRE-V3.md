# BC Educar --- Contexto Mestre V3

**Versão consolidada:** 06/10/2026\
**Status:** fonte normativa única do projeto após aprovação desta
revisão\
**Escopo:** Pré-Matrícula Digital (PMD), etapa documental BC, matrícula
intermediária, enturmação antecipada para ocupação de vaga, conferência
física, efetivação da matrícula no i-Educar, perfil do responsável,
comunicações, segurança, mapas, homologação e pendências.

> ## REGRA DE PRECEDÊNCIA
>
> Este documento passa a ser a fonte única de verdade funcional,
> arquitetural e operacional do BC Educar.
>
> Em caso de divergência com documentos anteriores, prevalece este
> arquivo.
>
> A principal alteração da V3 é a separação explícita entre
> **enturmação** e **efetivação definitiva da matrícula**:
>
> -   a enturmação ocorre após a aprovação documental e a criação da
>     matrícula intermediária, para reservar a vaga e contabilizar
>     corretamente a ocupação da turma;
> -   a conferência física ocorre posteriormente;
> -   a matrícula somente é promovida à situação definitiva após a
>     conferência física satisfatória;
> -   não deve ocorrer uma segunda enturmação na efetivação: o vínculo
>     com a turma já existente é consolidado.

------------------------------------------------------------------------

# 1. Objetivo e arquitetura

O BC Educar integra três responsabilidades:

1.  **Pré-Matrícula Digital --- PMD/Portabilis**: inscrição, análise
    inicial e estados oficiais da pré-matrícula;
2.  **i-Educar**: pessoa, aluno, responsável, matrícula, turma,
    capacidade, enturmação e registros acadêmicos nativos;
3.  **Camada BC**: política documental, modalidade de entrega, ficha
    cadastral, recebimento, triagem, aprovação, matrícula intermediária,
    conferência física, regularização, comunicações, auditoria e
    orquestração do fluxo.

A camada BC não deve criar um mecanismo paralelo de matrícula ou de
turma. Deve utilizar os serviços e entidades nativos do i-Educar,
adicionando as barreiras de domínio necessárias ao fluxo municipal.

Fluxo normativo:

``` text
PRÉ-MATRÍCULA
→ ANÁLISE ESCOLAR
→ DEFERIMENTO
→ LIBERAÇÃO DOCUMENTAL
→ ESCOLHA ONLINE/PRESENCIAL PELO RESPONSÁVEL
→ FICHA + ENTREGA DOCUMENTAL
→ TRIAGEM
→ CORREÇÃO, SE NECESSÁRIA
→ APROVAÇÃO DOCUMENTAL/DIGITAL
→ CONSOLIDAÇÃO CADASTRAL
→ MATRÍCULA INTERMEDIÁRIA
→ ENTURMAÇÃO / RESERVA E CONTABILIZAÇÃO DA VAGA
→ CONFERÊNCIA FÍSICA DOS ORIGINAIS
→ REGULARIZAÇÃO, SE NECESSÁRIA
→ CONFIRMAÇÃO FÍSICA
→ EFETIVAÇÃO DEFINITIVA DA MATRÍCULA
→ PMD ACCEPTED
```

------------------------------------------------------------------------

# 2. Regras invariantes

## 2.1 Escolha documental

A escolha entre `ONLINE` e `IN_PERSON` nunca ocorre durante a
pré-matrícula.

Enquanto o PMD estiver em `WAITING`:

-   `attendance_mode = null`;
-   não existe fase documental liberada;
-   não há upload documental;
-   não há recebimento presencial;
-   não há matrícula;
-   não há enturmação;
-   operador escolar não escolhe modalidade pelo responsável.

A modalidade somente pode ser escolhida após o deferimento e a liberação
da etapa documental.

## 2.2 Aprovação documental não é matrícula definitiva

A aprovação dos documentos significa que a etapa documental
digital/presencial foi validada para permitir a integração com o
i-Educar.

Ela pode disparar:

-   consolidação segura de pessoa/aluno/responsável;
-   criação ou reutilização de matrícula intermediária;
-   vínculo do aluno à turma.

Ela **não** significa matrícula definitiva.

## 2.3 Enturmação não é efetivação

No BC Educar V3, a enturmação tem duas funções operacionais
indispensáveis:

1.  reservar a vaga;
2.  fazer o aluno participar da contagem real de ocupação/capacidade da
    turma.

Por isso, a enturmação ocorre **antes da conferência física final**,
vinculada à matrícula intermediária.

A existência dessa enturmação não autoriza o sistema a apresentar o
aluno como matrícula definitiva.

## 2.4 Conferência física continua sendo barreira de efetivação

A matrícula intermediária enturmada não pode ser promovida à matrícula
definitiva enquanto houver:

-   original obrigatório não conferido;
-   divergência documental pendente;
-   divergência cadastral pendente;
-   regularização aberta;
-   impedimento de identidade;
-   requisito físico obrigatório não atendido.

## 2.5 Cancelamento devolve a vaga

Se uma matrícula intermediária for definitivamente cancelada ou
indeferida antes da efetivação:

-   cancelar o vínculo intermediário conforme serviço nativo;
-   remover/cancelar a enturmação intermediária correspondente;
-   devolver a vaga à capacidade disponível da turma;
-   preservar pessoa e aluno quando já legitimamente existentes;
-   preservar auditoria, documentos e histórico conforme política
    aplicável.

------------------------------------------------------------------------

# 3. Terminologia obrigatória

### Pré-matrícula

Inscrição inicial no PMD. Não é matrícula.

### Deferimento

Decisão escolar que autoriza a continuidade do fluxo. Não é matrícula.

### Liberação documental

Abertura da fase em que o responsável escolhe a forma de entrega e
cumpre as exigências documentais.

### Modalidade documental

Forma de entrega dos documentos: `ONLINE` ou `IN_PERSON`.

### Aprovação documental

Confirmação de que os requisitos documentais e cadastrais necessários à
integração foram atendidos. Não é matrícula definitiva.

### Matrícula intermediária

Vínculo nativo controlado no i-Educar, atualmente associado à situação
`PRE_REGISTRATION = 11`, criado/reutilizado depois da aprovação
documental. Pode possuir enturmação para reserva e contagem da vaga, mas
continua pendente de conferência física.

### Enturmação intermediária

Vínculo do aluno com a turma durante a matrícula intermediária. Ocupa
vaga e deve entrar nos cálculos de capacidade da turma. Não equivale à
matrícula definitiva.

### Conferência física

Validação presencial dos originais obrigatórios e dos dados cadastrais
definidos pelo processo.

### Efetivação definitiva

Promoção explícita da matrícula intermediária para a situação
oficial/ativa, somente depois da conferência física satisfatória.
Reutiliza a enturmação existente.

------------------------------------------------------------------------

# 4. Estados e matriz normativa

Os estados oficiais do PMD continuam preservados:

  Código   Estado
  -------- -------------------
  1        `WAITING`
  2        `ACCEPTED`
  3        `REJECTED`
  4        `SUMMONED`
  5        `IN_CONFIRMATION`

A documentação BC, a matrícula intermediária e a conferência física são
dimensões complementares.

  -----------------------------------------------------------------------------------------------------------------
  Etapa               PMD                    Documentação   Matrícula i-Educar Enturmação           Vaga
                                             BC                                                     contabilizada
  ------------------- ---------------------- -------------- ------------------ -------------------- ---------------
  Pré-matrícula       `WAITING`              não iniciada   inexistente        não                  não
  enviada

  Deferida/liberada   `SUMMONED`             liberada       inexistente        não                  não

  Modalidade          `SUMMONED`             aguardando     inexistente        não                  não
  escolhida                                  entrega

  Entrega/triagem     `SUMMONED`             parcial/em     inexistente        não                  não
                                             análise

  Correção            `SUMMONED`             correção       inexistente        não                  não
                                             pendente

  Documentação        `IN_CONFIRMATION` após aprovada       intermediária      **sim**              **sim**
  aprovada            integração                            (`11`)

  Conferência física  `IN_CONFIRMATION`      aprovada       intermediária      **sim**              **sim**
  pendente                                                  (`11`)

  Regularização       `IN_CONFIRMATION`      aprovada/com   intermediária      **sim**, enquanto    **sim**,
  física                                     pendência      (`11`)             válida               enquanto válida
                                             física

  Matrícula efetivada `ACCEPTED`             aprovada       definitiva/ativa   vínculo existente    sim
                                                                               consolidado

  Cancelamento antes  `REJECTED`/encerrada   encerrada      intermediária      removida/cancelada   não
  da efetivação       conforme origem                       cancelada
  -----------------------------------------------------------------------------------------------------------------

------------------------------------------------------------------------

# 5. Pré-matrícula pública

O formulário público deve continuar utilizando o fluxo do PMD.

Pode conter:

-   aluno;
-   responsável;
-   endereço;
-   série/ano;
-   turno;
-   escola principal;
-   escola alternativa, quando configurada;
-   demais dados definidos pelo processo.

Não deve conter a escolha `ONLINE/PRESENCIAL`.

Resultado inicial:

``` text
PMD = WAITING
attendance_mode = null
matrícula = inexistente
enturmação = inexistente
```

------------------------------------------------------------------------

# 6. Deferimento e liberação documental

Ao deferir:

1.  validar escola, processo, série, turno e escopo do operador;
2.  alterar `WAITING → SUMMONED`;
3.  registrar o deferimento;
4.  liberar a fase documental quando habilitada;
5.  criar/vincular a solicitação BC;
6.  copiar a política documental vigente;
7.  copiar os prazos aplicáveis;
8.  registrar auditoria;
9.  emitir comunicação;
10. manter `attendance_mode = null`;
11. não criar matrícula;
12. não enturmar.

O responsável escolhe a modalidade somente após receber a liberação.

------------------------------------------------------------------------

# 7. Documentação ONLINE

Após escolher `ONLINE`, o responsável pode:

-   revisar/completar a ficha cadastral disponível;
-   enviar os documentos exigidos;
-   acompanhar o progresso;
-   substituir documentos quando houver correção;
-   receber orientações e avisos.

Arquivos devem permanecer privados e autorizados por vínculo.

A aprovação individual de todos os documentos não deve finalizar
automaticamente a etapa geral: a ação de aprovação documental continua
explícita e auditável.

------------------------------------------------------------------------

# 8. Documentação PRESENCIAL

Após escolher `IN_PERSON`:

-   o responsável entrega os documentos na unidade;
-   a escola registra o recebimento;
-   digitalização não é requisito obrigatório, salvo política
    específica;
-   entrega parcial pode ser registrada;
-   aplicam-se os mesmos documentos obrigatórios e prazos definidos pelo
    processo;
-   ator, data e decisão devem ser auditados.

A ausência de arquivo digital não pode impedir a validação de documento
presencial legitimamente recebido.

------------------------------------------------------------------------

# 9. Triagem e aprovação documental

A triagem deve permanecer separada da fila inicial de pré-matrículas.

A escola analisa:

-   ficha cadastral;
-   documentos obrigatórios;
-   versões atuais;
-   prazo;
-   correções;
-   identidade e consistência necessárias.

Somente após todos os requisitos aplicáveis:

``` text
DOCUMENTAÇÃO = APROVADA
```

A integração com o i-Educar é então executada.

------------------------------------------------------------------------

# 10. Consolidação cadastral

Antes da matrícula intermediária:

-   localizar/reutilizar pessoa, aluno e responsável de forma segura;
-   não vincular automaticamente pessoas apenas por nome, CPF ou data;
-   tratar ambiguidades pela escola;
-   comparar cadastros existentes antes de atualizá-los;
-   não sobrescrever silenciosamente dados oficiais;
-   manter auditoria das alterações autorizadas.

Os snapshots declarados pelo responsável não substituem automaticamente
o cadastro oficial.

------------------------------------------------------------------------

# 11. Matrícula intermediária e enturmação antecipada

Após a aprovação documental e a integração cadastral bem-sucedida:

1.  criar ou reutilizar a matrícula intermediária nativa;
2.  manter `intermediate_registration_id` separado da matrícula
    definitiva;
3.  validar escola, série, turno, turma e ano;
4.  validar capacidade;
5.  **enturmar o aluno na turma selecionada**;
6.  fazer essa enturmação participar da ocupação/capacidade;
7.  registrar que o vínculo está pendente de conferência física;
8.  impedir que relatórios e portal apresentem esse vínculo como
    matrícula definitiva.

A operação deve ser transacional.

Se não houver vaga no momento da criação do vínculo, a integração não
pode concluir silenciosamente.

------------------------------------------------------------------------

# 12. Regra de capacidade e contagem de alunos

A capacidade da turma deve considerar:

``` text
matrículas definitivas ativas
+
matrículas intermediárias enturmadas e ainda válidas
```

Portanto:

``` text
vaga disponível =
capacidade da turma
- vínculos válidos contabilizados
```

Uma matrícula intermediária cancelada não pode continuar ocupando vaga.

O sistema deve impedir dupla contabilização quando o vínculo
intermediário for promovido para definitivo.

------------------------------------------------------------------------

# 13. Conferência física

Após a matrícula intermediária e a enturmação, o responsável apresenta
os originais exigidos.

A escola realiza:

-   conferência individual dos documentos;
-   conferência da identificação;
-   conferência da ficha/dados cadastrais;
-   registro de operador;
-   data;
-   decisão;
-   motivo de divergência;
-   prazo de regularização, quando aplicável.

Se estiver correto:

``` text
CONFERÊNCIA FÍSICA = APROVADA
```

Se houver divergência:

``` text
MATRÍCULA = INTERMEDIÁRIA
ENTURMAÇÃO = MANTIDA enquanto o processo estiver válido
EFETIVAÇÃO = BLOQUEADA
REGULARIZAÇÃO = ABERTA
```

------------------------------------------------------------------------

# 14. Regularização

A divergência física não deve apagar automaticamente:

-   pessoa;
-   aluno;
-   documentos enviados;
-   histórico;
-   matrícula intermediária;
-   enturmação.

O responsável recebe orientação e prazo.

Quando a divergência cadastral exigir alteração da ficha:

-   reabrir a correção de forma auditada;
-   exigir nova validação necessária;
-   manter a barreira de efetivação.

Ao vencer definitivamente o prazo conforme decisão/regra institucional,
a escola poderá encerrar o processo. Nesse caso, a vaga deve ser
liberada pela remoção/cancelamento da enturmação intermediária.

Não cancelar automaticamente apenas pela passagem do prazo se a política
exigir decisão escolar.

------------------------------------------------------------------------

# 15. Efetivação definitiva

Somente permitir quando:

-   documentação estiver aprovada;
-   matrícula intermediária existir;
-   enturmação intermediária estiver válida;
-   originais obrigatórios estiverem conferidos;
-   ficha/identidade estiverem confirmadas;
-   não houver regularização pendente;
-   escola/turma/turno/ano continuarem compatíveis;
-   operador estiver autorizado.

A ação explícita de efetivação deve:

1.  promover a matrícula intermediária para situação definitiva/ativa
    usando serviço nativo;
2.  **preservar/consolidar a enturmação já existente**;
3.  não consumir uma segunda vaga;
4.  registrar `registration_id` definitivo;
5.  concluir o PMD como `ACCEPTED`;
6.  registrar auditoria;
7.  emitir comunicação de matrícula efetivada.

------------------------------------------------------------------------

# 16. Cancelamento e indeferimento

Se ocorrer antes da matrícula intermediária, encerrar o fluxo sem criar
matrícula/enturmação.

Se ocorrer depois da matrícula intermediária e antes da efetivação:

1.  cancelar o vínculo intermediário conforme regra nativa;
2.  cancelar/remover a enturmação associada;
3.  devolver a vaga à turma;
4.  sincronizar o PMD/BC;
5.  preservar pessoa/aluno legítimos;
6.  preservar histórico e auditoria.

Matrícula definitiva já concluída não deve ser desfeita automaticamente
por um indeferimento tardio do fluxo documental.

------------------------------------------------------------------------

# 17. Perfil do responsável e dependentes

Dependentes persistentes podem existir no perfil sem criar
automaticamente:

-   pessoa oficial;
-   aluno oficial;
-   pré-matrícula;
-   matrícula;
-   enturmação.

Inscrições verificadas podem ser organizadas no perfil com controles de
vínculo e auditoria.

Não usar coincidência simples de nome/CPF como prova suficiente de
vínculo.

------------------------------------------------------------------------

# 18. Comunicações

As mensagens devem distinguir claramente:

-   pré-matrícula recebida;
-   pré-matrícula deferida;
-   documentação liberada;
-   escolha/entrega;
-   correção documental;
-   documentação aprovada;
-   matrícula intermediária criada / vaga reservada, quando a
    comunicação institucional desejar expor esse estágio;
-   necessidade de apresentação dos originais;
-   divergência/regularização;
-   conferência física concluída;
-   matrícula efetivada;
-   cancelamento.

Nunca comunicar "matrícula efetivada" na criação da matrícula
intermediária.

------------------------------------------------------------------------

# 19. Painéis e nomenclatura

O painel escolar deve distinguir visualmente:

1.  **Pré-matrículas**
2.  **Triagem documental**
3.  **Aguardando conferência física**
4.  **Regularização**
5.  **Matrículas efetivadas**
6.  **Configuração**

A matrícula intermediária enturmada deve aparecer como algo equivalente
a:

> **Vaga reservada --- aguardando conferência física**

e nunca simplesmente como:

> Matrícula efetivada.

------------------------------------------------------------------------

# 20. Proteções obrigatórias no código

A regra antiga "bloquear enturmação antes da conferência física" deve
ser removida/substituída.

A proteção correta é:

``` text
ANTES DA CONFERÊNCIA FÍSICA:
✓ matrícula intermediária permitida
✓ enturmação intermediária permitida
✓ vaga contabilizada
✗ promoção para matrícula definitiva proibida
```

Também deve existir proteção para:

-   impedir matrícula intermediária sem documentação aprovada;
-   impedir enturmação sem matrícula intermediária válida;
-   impedir segunda enturmação na efetivação;
-   impedir dupla contagem de vaga;
-   liberar vaga ao cancelar vínculo intermediário;
-   impedir promoção definitiva com conferência pendente;
-   manter transação consistente em falhas;
-   manter idempotência.

------------------------------------------------------------------------

# 21. Compatibilidade

Registros históricos da versão anterior não devem ser convertidos em
massa.

Novas liberações devem seguir o fluxo V3.

Registros V2 em andamento precisam ser tratados por estratégia explícita
de compatibilidade/migração, especialmente quando possuírem matrícula
intermediária ainda não enturmada.

Não executar migração destrutiva sem relatório prévio dos registros
afetados.

------------------------------------------------------------------------

# 22. Pendências funcionais ainda não declaradas concluídas

Continuam como evoluções separadas, salvo implementação posterior
comprovada:

-   reutilização completa de dependentes em nova inscrição;
-   ficha cadastral integral com todos os campos mapeados;
-   edição/verificação completa dos contatos do responsável;
-   comparação e atualização autorizada campo a campo do cadastro
    oficial;
-   linha do tempo integral;
-   validação visual desktop/mobile;
-   configuração de provedores externos;
-   política institucional definitiva de retenção;
-   homologação e aceite institucional.

Essas pendências não alteram o fluxo normativo de matrícula definido
neste documento.

------------------------------------------------------------------------

# 23. Critérios mínimos de teste da V3

Devem existir testes que comprovem pelo menos:

1.  `WAITING` não permite modalidade;
2.  deferimento não cria matrícula;
3.  aprovação documental cria/reutiliza matrícula intermediária;
4.  matrícula intermediária pode ser enturmada antes da conferência
    física;
5.  essa enturmação reduz a disponibilidade da turma;
6.  tentativa de nova matrícula respeita a vaga já reservada;
7.  matrícula intermediária não aparece como definitiva;
8.  conferência física pendente bloqueia promoção definitiva;
9.  divergência mantém vínculo intermediário conforme regra de prazo;
10. cancelamento remove a enturmação e devolve a vaga;
11. regularização aprovada permite efetivação;
12. efetivação promove a matrícula sem criar segunda enturmação;
13. não há dupla contagem de aluno/vaga;
14. PMD somente chega a `ACCEPTED` na efetivação;
15. falhas transacionais não deixam matrícula, enturmação e estados
    BC/PMD divergentes.

------------------------------------------------------------------------

# 24. Regra final

A regra de negócio do BC Educar passa a ser:

> **A aprovação documental autoriza a criação da matrícula intermediária
> e a enturmação do aluno para reserva e contabilização da vaga. A
> enturmação antecede a conferência física, mas não representa matrícula
> definitiva. A efetivação somente ocorre após a conferência física
> satisfatória dos originais e consolida o vínculo já existente com a
> turma. Se o processo for cancelado antes da efetivação, a enturmação
> intermediária deve ser desfeita e a vaga devolvida.**

Esta regra prevalece sobre qualquer documento anterior que determine que
a enturmação somente pode ocorrer depois da conferência física.
