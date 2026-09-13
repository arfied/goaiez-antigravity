# Migrate from Kapso

Move a WhatsApp integration from Kapso to Zernio, keeping the WABA, templates and numbers, with a mapping for every send call and webhook event that changes.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';
import { Step, Steps } from 'fumadocs-ui/components/steps';

When you finish this page your WhatsApp integration sends and receives through Zernio, on the same numbers and approved templates you had on Kapso. You need your Kapso project API key (to export conversations before cutover), a Zernio account and an [API key](https://zernio.com/dashboard/api-keys). Both products sit on Meta's WhatsApp Cloud API, so approved templates, the 24-hour service window, webhooks and WABA-level assets carry over; what changes is the request shape. Kapso exposes Meta's Cloud API envelope behind a proxy plus a separate platform API; Zernio has one flat, conversation-centric API. Most integrations move in half a day to a day.

## What changes

| What | Kapso | Zernio |
|------|-------|--------|
| Base URL | `api.kapso.ai/meta/whatsapp/v24.0` + `api.kapso.ai/platform/v1` | `zernio.com/api/v1` |
| Auth | `X-API-Key: KEY` | `Authorization: Bearer KEY` |
| Request shape | Meta Cloud API envelope (`messaging_product`, `type`) | Flat JSON fields (`message`, `attachmentUrl`, `template`) |
| Send target | `{phone_number_id}/messages` + `to` | `accountId` + `conversationId` |
| Business-initiated send | `POST .../messages` with `type: "template"` | `POST /v1/inbox/conversations` |
| Templates | `POST /{waba_id}/message_templates` | `POST /v1/whatsapp/templates` |
| Webhook signature | `X-Webhook-Signature` (HMAC-SHA256 hex) | `X-Zernio-Signature` (same algorithm) |
| Per-customer unit | Customer + setup link | Profile + connect URL |

The objects map one to one:

| Kapso | Zernio | Notes |
|-------|--------|-------|
| Project + API key | Team + API key | A Zernio key can be scoped to specific profiles |
| Customer | Profile | Groups the accounts of one customer or brand |
| WhatsApp phone number (`phone_number_id`) | Account (`accountId`) | Returned by `GET /v1/accounts` |
| Setup link | Connect URL (`GET /v1/connect/whatsapp`) | Both are Meta's hosted Embedded Signup |
| Conversation | Conversation | Zernio ids are 24-hex strings |
| Contact | Contact (`/v1/contacts`) | |

<Callout type="warn">
WABA assets travel with the number, message history does not. Approved templates, the display name, the quality rating and the messaging tier live on the WABA at Meta and come along when you reconnect the same number. Stored conversations and messages live in Kapso's database: export what you need with Kapso's `GET /platform/v1/whatsapp/messages` before you cancel.
</Callout>

## Step 1: Create a profile

Call `POST /v1/profiles` with a `name`, once per Kapso Customer (or once, for a single number):

```bash
curl -X POST https://zernio.com/api/v1/profiles \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name": "Acme Corporation"}'
```

Response (`201`):

```json
{
  "message": "Profile created successfully",
  "profile": {
    "_id": "66a1f0c2a4b9d3e8f1a2b3c4",
    "name": "Acme Corporation",
    "isDefault": false
  }
}
```

`profile._id` is the `profileId` for Step 2.

## Step 2: Move the numbers

Pick the path that matches how the number reached Kapso.

<Tabs items={['Reconnect your own number', 'Bring Meta credentials', 'Buy a number']}>
<Tab value="Reconnect your own number">

For numbers connected through a Kapso setup link or "Bring Your Own SIM" (the WABA belongs to your or your customer's Meta Business), call `GET /v1/connect/whatsapp` with `profileId`:

```bash
curl "https://zernio.com/api/v1/connect/whatsapp?profileId=66a1f0c2a4b9d3e8f1a2b3c4&redirect_url=https://yourapp.com/callback" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "authUrl": "https://www.facebook.com/v21.0/dialog/oauth?client_id=...",
  "state": "..."
}
```

Open `authUrl`, or send it to your customer the way you sent a Kapso setup link. It runs Meta's Embedded Signup with the Facebook login that owns the WABA; a WABA with several numbers gets a number selection step. The WABA stays the same, so templates, display name and quality rating come with it.

<Callout type="warn">
A coexistence number pairs with one provider at a time. Disconnect it from Kapso first (in the WhatsApp Business app: Settings, Account, Business Platform, Disconnect), then run the Zernio connect flow and scan its QR code with the same app ([coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence)).
</Callout>

</Tab>
<Tab value="Bring Meta credentials">

If you posted Meta credentials to Kapso's `POST /platform/v1/customers/{id}/whatsapp/phone_numbers`, the same values go to `POST /v1/connect/whatsapp/credentials`. Kapso's `whatsapp_phone_number` object:

```json
{
  "name": "Support Line",
  "kind": "production",
  "phone_number_id": "1234567890",
  "business_account_id": "98765432109",
  "access_token": "EAABsbCS...long-lived-token"
}
```

Zernio:

```bash
curl -X POST https://zernio.com/api/v1/connect/whatsapp/credentials \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4",
    "accessToken": "EAABsbCS...system-user-token",
    "wabaId": "98765432109",
    "phoneNumberId": "1234567890"
  }'
```

Response (`200`):

```json
{
  "message": "WhatsApp connected successfully",
  "account": {
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platform": "whatsapp",
    "username": "+1 555-123-4567",
    "displayName": "Acme Corp",
    "isActive": true,
    "qualityRating": "GREEN"
  }
}
```

The token must be a permanent Meta System User token with `whatsapp_business_management` and `whatsapp_business_messaging`. Zernio subscribes its webhooks on the WABA.

</Tab>
<Tab value="Buy a number">

A Kapso-managed number (Instant Setup) belongs to Kapso's pool, so buy a dedicated number with `POST /v1/phone-numbers/purchase`:

```bash
curl -X POST https://zernio.com/api/v1/phone-numbers/purchase \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"profileId": "66a1f0c2a4b9d3e8f1a2b3c4", "country": "US"}'
```

Response (`200`):

```json
{
  "phoneNumber": {
    "id": "66d4e5f6a7b8c9d0e1f2a3b4",
    "phoneNumber": "+14155550100",
    "status": "active",
    "country": "US",
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4"
  }
}
```

The number provisions in the same request and bills monthly on your usage-based invoice, with no checkout redirect; a team with no payment method on file gets `402` with code `PAYMENT_REQUIRED` instead. Numbers start at $3 per month; `GET /v1/phone-numbers/countries` lists the countries. Regulated countries answer `202` with `status: "kyc_required"`, which you complete [over the API](/platforms/phone-numbers/kyc) or hand to your customer as a white-label hosted KYC link. `whatsapp.number.activated` and `whatsapp.number.action_required` [events](/webhooks/phone-numbers) report provisioning, or poll `GET /v1/phone-numbers/{id}`.

</Tab>
</Tabs>

To test before moving a number, the [sandbox](/platforms/whatsapp/sandbox) activates your own phone with `POST /v1/whatsapp/sandbox/sessions`.

## Step 3: Get the account ids

Call `GET /v1/accounts?platform=whatsapp`. The `_id` replaces Kapso's `phone_number_id` in every call:

```bash
curl "https://zernio.com/api/v1/accounts?platform=whatsapp" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "accounts": [
    {
      "_id": "66b2e19d8c3f5a7e9d0b1c2d",
      "platform": "whatsapp",
      "username": "+1 555-123-4567",
      "displayName": "Acme Corp",
      "isActive": true,
      "metadata": {
        "wabaId": "98765432109",
        "phoneNumberId": "1234567890",
        "qualityRating": "GREEN",
        "messagingLimitTier": "TIER_1K"
      }
    }
  ]
}
```

Store the `phone_number_id` to `accountId` mapping. `qualityRating` and `messagingLimitTier` are the values Kapso's health endpoint showed; live WABA health is `GET /v1/whatsapp/number-info?accountId=...`.

## Step 4: Change the send calls

Kapso is recipient-centric: every send goes to `POST /{phone_number_id}/messages` with a `to` number. Zernio is conversation-centric:

- A business-initiated (template) message creates or reuses a conversation: `POST /v1/inbox/conversations` with `accountId` and `participantId` (the recipient's number).
- A free-form message inside the 24-hour window goes into that conversation: `POST /v1/inbox/conversations/{conversationId}/messages`.

The `conversationId` comes back from the create call, arrives on every webhook and is listed by `GET /v1/inbox/conversations`. It is stable per participant and account, so store it with the contact.

<Tabs items={['Template', 'Text', 'Media', 'Interactive', 'Location and reactions']}>
<Tab value="Template">

Kapso:

```json
{
  "messaging_product": "whatsapp",
  "to": "15551234567",
  "type": "template",
  "template": {
    "name": "order_update",
    "language": {"code": "en_US"},
    "components": [
      {"type": "body", "parameters": [{"type": "text", "text": "John"}]}
    ]
  }
}
```

Zernio:

```bash
curl -X POST https://zernio.com/api/v1/inbox/conversations \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "participantId": "15551234567",
    "templateName": "order_update",
    "templateLanguage": "en_US",
    "templateParams": ["John"]
  }'
```

Response (`201`):

```json
{
  "success": true,
  "data": {
    "messageId": "wamid.HBgLMTU1NTEyMzQ1NjcVAgARGBI...",
    "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b",
    "participantId": "15551234567"
  }
}
```

`templateParams` fills the body variables in order, for positional (`{{1}}`) and named (`{{name}}`) placeholders alike; a media header is filled from the approved template. To send a template into an existing conversation with full Meta-style `components` (for example a custom media header), post to the messages endpoint with a `template` object; the `components` array is forwarded to Meta verbatim, so Kapso payloads drop in unchanged ([templates](/platforms/whatsapp/templates)).

</Tab>
<Tab value="Text">

Kapso:

```json
{
  "messaging_product": "whatsapp",
  "to": "15551234567",
  "type": "text",
  "text": {"body": "Your order #12345 has shipped."}
}
```

Zernio:

```bash
curl -X POST https://zernio.com/api/v1/inbox/conversations/66c3d2ae7b4f6c8d0e1f2a3b/messages \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "message": "Your order #12345 has shipped."
  }'
```

Response (`200`):

```json
{
  "success": true,
  "data": {
    "messageId": "wamid.HBgLMTU1NTEyMzQ1NjcVAgARGBI...",
    "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b"
  }
}
```

To quote a message, pass `replyTo` with its `wamid`, which webhooks deliver as `message.platformMessageId`.

</Tab>
<Tab value="Media">

Kapso (a link or an uploaded media `id`):

```json
{
  "messaging_product": "whatsapp",
  "to": "15551234567",
  "type": "image",
  "image": {"link": "https://cdn.example.com/product.jpg"}
}
```

Zernio (a public URL):

```bash
curl -X POST https://zernio.com/api/v1/inbox/conversations/66c3d2ae7b4f6c8d0e1f2a3b/messages \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "attachmentUrl": "https://cdn.example.com/product.jpg",
    "attachmentType": "image"
  }'
```

Response (`200`):

```json
{
  "success": true,
  "data": {
    "messageId": "wamid.HBgLMTU1NTEyMzQ1NjcVAgARGBI...",
    "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b",
    "attachments": [
      {"type": "image", "url": "https://cdn.example.com/product.jpg"}
    ]
  }
}
```

`attachmentType` is `image`, `video`, `audio` or `file`; add `attachmentName` for documents and `voiceNote: true` for an `.ogg` OPUS voice note. There is no Meta media-id step: a file hosted nowhere goes as `multipart/form-data` (field `attachment`, up to 25 MB) or through `POST /v1/media/upload-direct`, which returns a URL valid for 7 days to pass as `attachmentUrl` ([inbox](/platforms/whatsapp/inbox)).

</Tab>
<Tab value="Interactive">

Reply buttons use Zernio's `buttons` shorthand instead of the `interactive` envelope.

Kapso:

```json
{
  "messaging_product": "whatsapp",
  "to": "15551234567",
  "type": "interactive",
  "interactive": {
    "type": "button",
    "body": {"text": "Confirm your appointment?"},
    "action": {
      "buttons": [
        {"type": "reply", "reply": {"id": "btn_yes", "title": "Yes"}},
        {"type": "reply", "reply": {"id": "btn_no", "title": "No"}}
      ]
    }
  }
}
```

Zernio:

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "message": "Confirm your appointment?",
  "buttons": [
    {"type": "postback", "title": "Yes", "payload": "btn_yes"},
    {"type": "postback", "title": "No", "payload": "btn_no"}
  ]
}
```

Lists, CTA URLs, flows, location requests and the commerce types keep Meta's `interactive` object verbatim: drop the outer envelope and add `accountId`:

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "interactive": {
    "type": "list",
    "body": {"text": "Pick an option"},
    "action": {
      "button": "View Options",
      "sections": [
        {"title": "Options", "rows": [{"id": "opt_1", "title": "Option 1"}]}
      ]
    }
  }
}
```

`interactive.type` accepts `list`, `cta_url`, `flow`, `location_request_message`, `voice_call`, `product`, `product_list`, `catalog_message` and `carousel` ([commerce messages](/platforms/whatsapp/inbox#commerce-messages)). Taps arrive on `message.received` as `metadata.interactiveType` (`button_reply` or `list_reply`) and `metadata.interactiveId`; submitted carts arrive as `metadata.order`.

</Tab>
<Tab value="Location and reactions">

A location pin keeps Meta's fields, with numbers instead of strings:

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "location": {
    "latitude": 37.7749,
    "longitude": -122.4194,
    "name": "San Francisco Office",
    "address": "123 Market St, San Francisco, CA 94103"
  }
}
```

Contact cards mirror Meta's shape in the `contacts` field. A reaction is its own endpoint, addressed by the `wamid` (the same id `replyTo` takes):

```bash
curl -X POST https://zernio.com/api/v1/inbox/conversations/66c3d2ae7b4f6c8d0e1f2a3b/messages/wamid.HBgLMTU1NTEyMzQ1NjcVAgARGBI.../reactions \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"accountId": "66b2e19d8c3f5a7e9d0b1c2d", "emoji": "👍"}'
```

Response (`200`):

```json
{
  "success": true,
  "messageId": "66c3d2ae7b4f6c8d0e1f2a3d",
  "platformMessageId": "wamid.HBgLMTU1NTEyMzQ1NjcVAgARGBI..."
}
```

A `200` means the platform accepted the request, not that the reaction was placed: WhatsApp never confirms what it acted on. `DELETE` on the same path, with `accountId` as a query parameter, removes it.

</Tab>
</Tabs>

Read receipts and typing indicators, which Kapso overloads onto the messages endpoint (`"status": "read"`, `typing_indicator`), have their own endpoints: `POST /v1/inbox/conversations/{conversationId}/read` sends the blue ticks and `POST /v1/inbox/conversations/{conversationId}/typing` shows "typing" for up to 25 seconds, both with `accountId` in the body. On a coexistence number the phone app owns read state, so `/read` sends no ticks there.

## Step 5: Change the webhooks

Kapso registers webhooks per phone number. A Zernio webhook endpoint is team-wide and filtered by event; message and template payloads carry an `account` object, so scope by `account.id` in your handler, while `whatsapp.number.*` events carry a `number` object with the `profileId`. Call `POST /v1/webhooks/settings`:

```bash
curl -X POST https://zernio.com/api/v1/webhooks/settings \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "whatsapp-events",
    "url": "https://yourapp.com/webhooks/zernio",
    "events": [
      "message.received", "message.sent", "message.delivered",
      "message.read", "message.failed", "reaction.received",
      "conversation.started", "whatsapp.template.status_updated"
    ],
    "secret": "'"$ZERNIO_WEBHOOK_SECRET"'"
  }'
```

Response (`200`):

```json
{
  "success": true,
  "webhook": {
    "_id": "507f1f77bcf86cd799439011",
    "name": "whatsapp-events",
    "url": "https://yourapp.com/webhooks/zernio",
    "events": ["message.received", "message.sent", "message.delivered", "message.read", "message.failed", "reaction.received", "conversation.started", "whatsapp.template.status_updated"],
    "isActive": true
  }
}
```

| Kapso event | Zernio event |
|-------------|--------------|
| `whatsapp.message.received` | [`message.received`](/webhooks/inbox#messagereceived) |
| `whatsapp.message.sent` | [`message.sent`](/webhooks/inbox#messagesent) |
| `whatsapp.message.delivered` | [`message.delivered`](/webhooks/inbox#messagedelivered) |
| `whatsapp.message.read` | [`message.read`](/webhooks/inbox#messageread) |
| `whatsapp.message.failed` | [`message.failed`](/webhooks/inbox#messagefailed) |
| Reaction messages | [`reaction.received`](/webhooks/inbox#reactionreceived) |
| `whatsapp.conversation.created` | [`conversation.started`](/webhooks/inbox#conversationstarted) |
| `whatsapp.conversation.ended` / `.inactive` | No equivalent; derive it from the conversation timestamps |
| `whatsapp.phone_number.created` | [`account.connected`](/webhooks/accounts#accountconnected), plus `whatsapp.number.*` for purchased numbers |
| `whatsapp.phone_number.deleted` | [`account.disconnected`](/webhooks/accounts#accountdisconnected) |
| Template status changes | [`whatsapp.template.status_updated`](/webhooks/whatsapp#whatsapptemplatestatus_updated) |

Payloads are Zernio-shaped, not Meta-shaped: the text is `message.text`, the wamid is `message.platformMessageId`, the sender is `message.sender` (with `phoneNumber` and `businessScopedUserId` as the durable identity) and the WhatsApp extras (button taps, flow responses, a CTWA `referral` with `ctwa_clid`) sit under `metadata`. Kapso retries 3 times (10 s, 40 s, 90 s) and can batch `whatsapp.message.received`; Zernio retries 7 times over about 51 hours and sends one event per request ([webhooks](/webhooks)).

The signature uses the same algorithm under a different header, so verification changes one line:

```javascript
const expected = crypto
  .createHmac('sha256', process.env.ZERNIO_WEBHOOK_SECRET)
  .update(rawBody)
  .digest('hex');

// Kapso:  req.headers['x-webhook-signature']
// Zernio: req.headers['x-zernio-signature']
const valid = crypto.timingSafeEqual(
  Buffer.from(expected),
  Buffer.from(req.headers['x-zernio-signature'])
);
```

## Step 6: Cut over

| Phase | Actions |
|-------|---------|
| Prep | Create profiles, ship the new calls and webhook handler behind a flag, test on the [sandbox](/platforms/whatsapp/sandbox) |
| Per number | Reconnect the number through Zernio, verify inbound events, stop Kapso sends for it |
| Cutoff | Export message history from Kapso, then cancel |

Meta's per-conversation charges continue either way, because they are billed to the WABA.

## If it fails

A `409` on the connect call is the failure a cutover actually hits: the number is still live somewhere. `code` says where:

```json
{
  "error": "This number is already connected on another profile. A number can only be live on one profile, so disconnect it there first.",
  "code": "WHATSAPP_NUMBER_ALREADY_CONNECTED"
}
```

| `code` | Meaning | Fix |
|---|---|---|
| `WHATSAPP_NUMBER_ALREADY_CONNECTED` | The number is live on another profile or team | Disconnect it there first, then retry |
| `ONE_WHATSAPP_PER_PROFILE` | The profile already holds a WhatsApp number | Connect this one to a new profile |

Kapso still holding the number shows up as the same `409` through Meta, so finish the Kapso-side disconnect before retrying ([connection and setup](/platforms/whatsapp/connection#if-it-fails)).

A `400` with `TEMPLATE_REQUIRED` on `POST /v1/inbox/conversations` means the recipient has not written in the last 24 hours, so a free-form message is not allowed; send an approved template instead. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [WhatsApp inbox](/platforms/whatsapp/inbox), [templates](/platforms/whatsapp/templates) and [connection](/platforms/whatsapp/connection)
- [Phone numbers](/platforms/phone-numbers) and [KYC](/platforms/phone-numbers/kyc)
- [Webhooks](/webhooks)
- [Rate limits](/guides/rate-limits)
- Support: support@zernio.com

---
