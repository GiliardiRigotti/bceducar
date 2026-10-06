# Operação das comunicações externas

## Situação

SMTP local já foi validado com Mailpit. O envio institucional continua dependendo de host, porta, credenciais, remetente e validação de entrega. SMS/WhatsApp possuem conectores HTTP genéricos, não uma integração certificada com um provedor específico.

A Secretaria ou seu fornecedor deve disponibilizar uma ponte compatível com o contrato abaixo. Um endpoint de Twilio, Meta ou outro provedor não é automaticamente compatível com esse JSON. A ponte deve fazer a tradução, autenticação, uso de templates aprovados e demais requisitos do canal contratado.

Os canais permanecem desativados por padrão. Esta implementação não enviou mensagens reais a terceiros.

## Contrato da ponte

POST no endpoint fixo configurado, com Content-Type application/json.

Cabeçalhos:

- Authorization: Bearer <token>, quando configurado.
- Idempotency-Key: bc-message-<notification_id>-<channel>.

Corpo:

```json
{
  "channel": "sms",
  "recipient": "+5547999991234",
  "idempotency_key": "bc-message-123-sms",
  "subject": "Documentação aprovada: aguardando efetivação",
  "message": "Texto do aviso com protocolo e link para acompanhamento."
}
```

HTTP 2xx significa aceitação pela ponte, não confirmação de entrega ao aparelho. Qualquer outro status ou falha de conexão agenda nova tentativa. A ponte **deve respeitar a chave de idempotência**, inclusive quando uma tentativa tiver sido aceita e a resposta se perder; esse requisito evita duplicações após timeout. O BC registra até cinco tentativas com espera progressiva, independentes para cada canal.

Redirecionamentos não são seguidos. Apenas URLs HTTP/HTTPS são aceitas; HTTPS é obrigatório fora de local/testing. Mensagens reutilizam textos sem documentos anexos, nomes de estudantes ou motivos clínicos. Não expor tokens ou dados de destinatários em logs da ponte.

O telefone vem do responsável cadastrado, priorizando mobile e usando phone como alternativa. Formato internacional é recomendado; números nacionais de dez/onze dígitos recebem o código configurado (55 por padrão). Telefone inválido não é enviado.

Avisos de etapas já encerradas e lembretes vencidos são descartados. O limite de início evita reenviar todo o histórico ao ativar um novo canal.

## Configuração

Definir os valores reais em ambiente protegido; .env.example contém apenas placeholders.

```dotenv
BC_GUARDIAN_MESSAGES_ENABLED=true
BC_GUARDIAN_MESSAGES_START_AT=2026-10-05T12:00:00-03:00
BC_GUARDIAN_MESSAGES_COUNTRY_CODE=55
BC_SMS_ENABLED=true
BC_SMS_WEBHOOK_URL=https://ponte-da-secretaria.example/sms
BC_SMS_WEBHOOK_TOKEN=
BC_WHATSAPP_ENABLED=false
BC_WHATSAPP_WEBHOOK_URL=
BC_WHATSAPP_WEBHOOK_TOKEN=
```

Substituir a data por um corte explícito acordado para ativação. Os exemplos de URL não são endpoints operacionais. Não habilitar um canal sem ponte configurada e testada.

Comandos:

```powershell
docker compose exec -T php php artisan config:clear
docker compose exec -T php php artisan bc:send-guardian-messages
```

O scheduler e o loop bc-deadlines incluem o comando. A fila de e-mail continua independente; SMS ou WhatsApp não modifica sent_at do aviso de e-mail.

Para SMTP institucional, configurar MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION, MAIL_FROM_ADDRESS e MAIL_FROM_NAME. Habilitar BC_GUARDIAN_NOTIFICATIONS_ENABLED somente após validação. O overlay docker-compose.mail.yml captura mensagens localmente e deve ser removido da configuração dos containers que forem usar SMTP institucional.

## Validação antes da ativação

1. Definir canais, provedor e destinatário de teste autorizado.
2. Validar autenticação e HTTPS da ponte e idempotência após timeout.
3. Conferir recebimento dos nove tipos de aviso e links no ambiente correto.
4. Conferir aprovação documental sem afirmar matrícula efetivada.
5. Conferir falha, nova tentativa e independência entre os canais.
6. Confirmar requisitos de opt-in/templates com o fornecedor e a Secretaria.
7. Registrar evidências e aprovação da ativação; a implementação automatizada não comprova entrega externa.
