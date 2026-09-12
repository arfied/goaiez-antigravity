# Templates

Create WhatsApp message templates, read Meta's review verdict, and import pre-approved templates from Meta's library.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you have a template Meta has approved, ready for `POST /v1/inbox/conversations` and [broadcasts](/platforms/whatsapp/broadcasts). You need a connected WhatsApp account and its `accountId`. A template is the only message that can open a conversation, or continue one more than 24 hours after the customer's last message, and Meta reviews every custom template before it can be sent.

## Step 1: Create a template

Call `POST /v1/whatsapp/templates` with `name` (lowercase letters, numbers and underscores, starting with a letter), `category`, `language` and `components`. Placeholders in the body are positional, `{{1}}`, `{{2}}` and so on, and each needs an `example` value for Meta's reviewers; pass `parameter_format: "NAMED"` to use `{{customer_name}}` instead.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const accountId = '66b2e19d8c3f5a7e9d0b1c2d';

const { data: created } = await zernio.whatsapp.createWhatsAppTemplate({
  body: {
    accountId,
    name: 'order_confirmation',
    category: 'UTILITY',
    language: 'en',
    components: [{
      type: 'body',
      text: 'Hi {{1}}, your order {{2}} has been confirmed.',
      example: { body_text: [['Ana', 'ORD-12345']] }
    }]
  }
});

console.log(created.template.status);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()
account_id = "66b2e19d8c3f5a7e9d0b1c2d"

created = client.whatsapp.create_whats_app_template(
    account_id=account_id,
    name="order_confirmation",
    category="UTILITY",
    language="en",
    components=[{
        "type": "body",
        "text": "Hi {{1}}, your order {{2}} has been confirmed.",
        "example": {"body_text": [["Ana", "ORD-12345"]]}
    }]
)

print(created["template"]["status"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/whatsapp/templates \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "name": "order_confirmation",
    "category": "UTILITY",
    "language": "en",
    "components": [{
      "type": "body",
      "text": "Hi {{1}}, your order {{2}} has been confirmed.",
      "example": {"body_text": [["Ana", "ORD-12345"]]}
    }]
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "template": {
    "id": "1234567890123456",
    "name": "order_confirmation",
    "status": "PENDING",
    "category": "UTILITY",
    "language": "en"
  }
}
```

`category` is `UTILITY`, `MARKETING` or `AUTHENTICATION`, and it decides Meta's delivery fee ([WhatsApp rates](/pricing/whatsapp)) and the [delivery window](#delivery-window) the template can carry. Header, footer, buttons, carousel and limited-time-offer components follow Meta's shapes; [Create template](/whatsapp/create-whatsapp-template) lists them.

## Step 2: Wait for Meta's verdict

Review takes up to 24 hours. Subscribe to [`whatsapp.template.status_updated`](/webhooks/whatsapp#whatsapptemplatestatus_updated): it fires whenever Meta finishes reviewing or re-reviewing any template on the WABA, with the new `status` and Meta's `reason` (`"NONE"` on approval, an explanation on rejection). Polling the list works too, but the event arrives first.

`status` is Meta's verdict, forwarded verbatim. Only `APPROVED` can be sent:

| `status` | Meaning |
|---|---|
| `PENDING` | Under Meta review. |
| `APPROVED` | Live and sendable. |
| `REJECTED` | Meta declined it. Fix the content and resubmit, or appeal the decision. |
| `IN_APPEAL` | A rejection appeal is being re-reviewed. |
| `PAUSED` | Meta paused delivery, typically after negative recipient feedback. The template cannot be sent while paused. |
| `DISABLED` | Disabled by Meta. The template can no longer be sent. |
| `PENDING_DELETION` | Delete requested. The template is removed after a 24-hour grace period. |

Polling reads the same verdict: call `GET /v1/whatsapp/templates` with `accountId`. Templates are read live from Meta, one entry per name and language, so a template that exists in 3 languages appears 3 times with its own Meta `id` each. Filter with `name`, `language` or `status`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: listed } = await zernio.whatsapp.getWhatsAppTemplates({
  query: { accountId, status: 'APPROVED' }
});

console.log(listed.templates.map(t => `${t.name} (${t.language})`));
```
</Tab>
<Tab value="Python">
```python
listed = client.whatsapp.get_whats_app_templates(
    account_id=account_id,
    status="APPROVED",
)

print([f"{t['name']} ({t['language']})" for t in listed["templates"]])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/whatsapp/templates?accountId=66b2e19d8c3f5a7e9d0b1c2d&status=APPROVED" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "templates": [
    {
      "id": "1234567890123456",
      "name": "order_confirmation",
      "status": "APPROVED",
      "category": "UTILITY",
      "language": "en",
      "components": [
        { "type": "BODY", "text": "Hi {{1}}, your order {{2}} has been confirmed." }
      ]
    }
  ]
}
```

## Step 3: Send the template

An `APPROVED` template opens a conversation with `POST /v1/inbox/conversations`: the phone goes in `participantId` (digits, country code included, no `+`), and `templateParams` is one flat array in the order the variables appear across the template, text-header variables first, then body variables, then dynamic URL buttons.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: opened } = await zernio.messages.createInboxConversation({
  body: {
    accountId,
    participantId: '13105551234',
    templateName: 'order_confirmation',
    templateLanguage: 'en',
    templateParams: ['Ana', 'ORD-12345']
  }
});

console.log(opened.data.conversationId);
```
</Tab>
<Tab value="Python">
```python
opened = client.messages.create_inbox_conversation(
    account_id=account_id,
    participant_id="13105551234",
    template_name="order_confirmation",
    template_language="en",
    template_params=["Ana", "ORD-12345"],
)

print(opened["data"]["conversationId"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/inbox/conversations" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "participantId": "13105551234",
    "templateName": "order_confirmation",
    "templateLanguage": "en",
    "templateParams": ["Ana", "ORD-12345"]
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "success": true,
  "data": {
    "messageId": "wamid.HBgLMTMxMDU1NTEyMzQVAgARGBI5QTNEMEY3RjQ4RjE2QjA3QzYA",
    "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b",
    "participantId": "13105551234"
  }
}
```

`templateLanguage` must be the exact code the template was created with: `en` and `en_US` are different templates. Inside a conversation that is already open, send the same template through [`POST /v1/inbox/conversations/{conversationId}/messages`](/platforms/whatsapp/inbox#send-a-template); to map the variables per recipient across a list, use a [broadcast](/platforms/whatsapp/broadcasts#template-variables).

## Delivery window

`message_send_ttl_seconds` is how long Meta keeps trying to deliver a message built from this template. A message not delivered inside the window is dropped rather than queued further, which is what you want for a one-time passcode and not what you want for a promotion. Send it on create alongside `category`, and read it back afterwards.

| `category` | Range | Meta's default when you send nothing |
|---|---|---|
| `AUTHENTICATION` | 30 to 900 seconds | 600 seconds |
| `UTILITY` | 30 to 43200 seconds (12 hours) | 30 days |
| `MARKETING` | 43200 to 2592000 seconds (30 days) | 30 days |

`-1` is accepted on create only, where it keeps the 30-day default on `AUTHENTICATION` and `UTILITY`. The create call echoes the value you sent; the list and get calls return `message_send_ttl_seconds` only while a custom window is set, and leave it out while the category default applies. Meta clears the window when it recategorises a template, so read it back rather than assuming the value you sent survived.

Change it later with either update endpoint, `PATCH /v1/whatsapp/templates/{templateName}` or `PATCH /v1/whatsapp/templates/id/{templateId}`, each of which takes `components`, `message_send_ttl_seconds` or both. The difference matters: a component update sends the variant back to Meta for review and returns `status: "PENDING"`, while a window-only update leaves an `APPROVED` variant approved. `-1` is not accepted here, because Meta reads it as an empty edit; send a value in range. A value outside the category's range is a `400` with `param: message_send_ttl_seconds`.

```bash
curl -X PATCH "https://zernio.com/api/v1/whatsapp/templates/order_confirmation" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "language": "en",
    "message_send_ttl_seconds": 3600
  }'
```

Response (`200`):

```json
{
  "success": true,
  "template": {
    "id": "1234567890123456",
    "name": "order_confirmation",
    "language": "en",
    "status": "APPROVED"
  }
}
```

Meta stores one template per name and language, so `language` in the body says which variant you mean; as a query parameter on this call it is a `400`. A name that exists in several languages with no `language` returns `409` with code `ambiguous_template` and `details.languages`. Meta allows an approved template to be edited once per 24 hours and up to 10 times per 30 days, and only from `APPROVED`, `REJECTED` or `PAUSED`.

## Import from the template library

Meta's [Template Library](https://business.facebook.com/latest/whatsapp_manager/template_library) holds pre-approved templates that skip the review wait. Pass `library_template_name` instead of `components`. A library template with URL or PHONE_NUMBER buttons must be created with a matching `library_template_button_inputs` array, one entry per button in order, or Meta rejects it with "give the same number of button inputs to match the library buttons". Look the template up first with `GET /v1/whatsapp/template-library` to see its buttons and the languages it comes in.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: library } = await zernio.whatsapp.getWhatsAppLibraryTemplate({
  query: { accountId, name: 'account_creation_confirmation_3', language: 'en_US' }
});

const { data: imported } = await zernio.whatsapp.createWhatsAppTemplate({
  body: {
    accountId,
    name: 'account_creation_confirmation_3',
    category: 'UTILITY',
    language: library.template.language,
    library_template_name: 'account_creation_confirmation_3',
    library_template_button_inputs: [
      { type: 'URL', url: { base_url: 'https://your-site.com/account', url_suffix_example: 'https://your-site.com/account' } }
    ]
  }
});

console.log(imported.template.status);
```
</Tab>
<Tab value="Python">
```python
library = client.whatsapp.get_whats_app_library_template(
    account_id=account_id,
    name="account_creation_confirmation_3",
    language="en_US",
)

imported = client.whatsapp.create_whats_app_template(
    account_id=account_id,
    name="account_creation_confirmation_3",
    category="UTILITY",
    language=library["template"]["language"],
    library_template_name="account_creation_confirmation_3",
    library_template_button_inputs=[
        {"type": "URL", "url": {"base_url": "https://your-site.com/account", "url_suffix_example": "https://your-site.com/account"}}
    ],
)

print(imported["template"]["status"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/whatsapp/template-library?accountId=66b2e19d8c3f5a7e9d0b1c2d&name=account_creation_confirmation_3&language=en_US" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"

curl -X POST https://zernio.com/api/v1/whatsapp/templates \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "name": "account_creation_confirmation_3",
    "category": "UTILITY",
    "language": "en_US",
    "library_template_name": "account_creation_confirmation_3",
    "library_template_button_inputs": [
      { "type": "URL", "url": { "base_url": "https://your-site.com/account", "url_suffix_example": "https://your-site.com/account" } }
    ]
  }'
```
</Tab>
</Tabs>

Response (`200`), the lookup:

```json
{
  "template": {
    "name": "account_creation_confirmation_3",
    "language": "en_US",
    "category": "UTILITY",
    "body": "Your account has been created. ...",
    "body_params": [],
    "availableLanguages": ["en_US", "es", "pt_BR"],
    "buttons": [
      { "type": "URL", "text": "View account" }
    ]
  }
}
```

Response (`200`), the import, approved at once with no review wait:

```json
{
  "success": true,
  "template": {
    "id": "1234567890123457",
    "name": "account_creation_confirmation_3",
    "status": "APPROVED",
    "category": "UTILITY",
    "language": "en_US"
  }
}
```

When the language you asked for is not offered, the lookup returns the first available variant and names it in `language`, which is why the sample reads the language back before creating.

## If it fails

A `400` on a library import means the template has URL or PHONE_NUMBER buttons and `library_template_button_inputs` does not match them one for one:

```json
{
  "error": "Please give the same number of button inputs to match the library buttons."
}
```

Read `buttons` from the lookup above and send one input per button, in order. A custom template returns `400` for a name outside Meta's format, a missing field or a `category` outside the 3 values. A template that comes back `REJECTED` is not an API error: read `reason` on the [`whatsapp.template.status_updated`](/webhooks/whatsapp#whatsapptemplatestatus_updated) event, fix the content and create it again. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Broadcasts](/platforms/whatsapp/broadcasts): send an approved template to many recipients.
- [WhatsApp inbox](/platforms/whatsapp/inbox): send a template into one conversation.
- [Templates API](/whatsapp/get-whatsapp-templates): get, update and delete by name or Meta id.
- [WhatsApp webhooks](/webhooks/whatsapp): review verdicts and category changes.
- [WhatsApp rates](/pricing/whatsapp): what Meta charges per delivered template.

---
