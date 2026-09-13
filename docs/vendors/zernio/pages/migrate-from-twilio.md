# Migrate from Twilio

Move WhatsApp, SMS, voice and phone numbers from Twilio to Zernio, with a mapping for every send call, webhook and registration that changes and a list of what stays on Twilio.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';
import { Step, Steps } from 'fumadocs-ui/components/steps';

When you finish this page your WhatsApp, SMS and voice traffic runs through Zernio, on numbers you ported or bought. You need your Twilio Account SID and auth token (to export data and wind down), a Zernio account and an [API key](https://zernio.com/dashboard/api-keys). Zernio replaces Twilio's messaging surfaces (WhatsApp Business API, SMS and MMS, voice calling, phone numbers); it does not replace TwiML voice apps, Verify's voice and email channels, or SendGrid, listed under [what does not map](#what-does-not-map).

## What changes

| What | Twilio | Zernio |
|------|--------|--------|
| Auth | HTTP Basic (Account SID + token, or API key) | `Authorization: Bearer KEY` |
| Wire format | `application/x-www-form-urlencoded` | JSON |
| Per-customer unit | Account and subaccounts | Team, split into profiles: a profile groups the numbers and accounts of one customer or brand |
| WhatsApp sender | WhatsApp Sender | Account (`accountId`), from `GET /v1/accounts?platform=whatsapp` |
| Phone number | `IncomingPhoneNumbers` (`PN...`) + Regulatory Bundles | `/v1/phone-numbers` purchase + KYC, or [port your numbers](#step-2-move-the-numbers). One number carries WhatsApp, SMS and voice together |
| WhatsApp send | `POST /2010-04-01/.../Messages.json` with `From=whatsapp:+E164` | `POST /v1/inbox/conversations` (then `/{conversationId}/messages`) |
| SMS send | The same `Messages.json` endpoint | `POST /v1/sms/messages` |
| One message | Message resource (`SM...`) | A message inside an inbox conversation (`GET /v1/inbox/conversations`) |
| WhatsApp templates | Content API (`ContentSid`, `HX...`) | `/v1/whatsapp/templates`, addressed by name. The approved templates stay on your WABA at Meta |
| Conversation automation | Studio flow | Workflow (`/v1/workflows`): trigger and node graph with AI nodes, conditions and handoff |
| Inbound webhooks | Form-encoded POST, reply with TwiML | JSON events, reply with any 2xx |
| Webhook signature | `X-Twilio-Signature` (HMAC-SHA1 of URL + sorted params) | `X-Zernio-Signature` (HMAC-SHA256 of the raw body) |

The send model is the biggest shift. Twilio is stateless: every send is `To` plus `From` on one endpoint. Zernio is conversation-centric for WhatsApp: a business-initiated template send creates or reuses a conversation, and free-form replies go into it. SMS keeps the familiar shape, `from` and `to` on `POST /v1/sms/messages`, and replies thread into the same inbox.

## Step 1: Create a profile

Call `POST /v1/profiles` with a `name`, once per Twilio subaccount (or once, if the app serves one customer):

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

`profile._id` is the `profileId` for everything below.

## Step 2: Move the numbers

<Tabs items={['Port your Twilio numbers', 'Buy new numbers', 'Move a WhatsApp sender']}>
<Tab value="Port your Twilio numbers">

The numbers your customers know can move with you.

<Steps>
### Check portability

Call `POST /v1/phone-numbers/port-in/check` with `phoneNumbers`; the check is free:

```bash
curl -X POST https://zernio.com/api/v1/phone-numbers/port-in/check \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"phoneNumbers": ["+13025551234"]}'
```

Response (`200`):

```json
{
  "results": [
    {
      "phoneNumber": "+13025551234",
      "portable": true,
      "fastPortable": true,
      "lineType": "landline",
      "countryCode": "US",
      "phoneNumberType": "local",
      "notPortableReason": null
    }
  ]
}
```

### Upload the paperwork

Call `POST /v1/phone-numbers/port-in/documents` once for the Letter of Authorization and once for a recent Twilio invoice; each call returns a `documentId`.

### Submit the port

Call `POST /v1/phone-numbers/port-in` with the numbers, the `endUser` and both document ids:

```bash
curl -X POST https://zernio.com/api/v1/phone-numbers/port-in \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "phoneNumbers": ["+13025551234"],
    "endUser": {
      "entityName": "Acme Corporation",
      "authPersonName": "Jane Smith",
      "streetAddress": "123 Market St",
      "locality": "San Francisco",
      "administrativeArea": "CA",
      "postalCode": "94103",
      "countryCode": "US"
    },
    "loaDocumentId": "66d4a1b2c3d4e5f6a7b8c9d0",
    "invoiceDocumentId": "66d4a1b2c3d4e5f6a7b8c9d1"
  }'
```

Response (`201`):

```json
{
  "id": "66d4a1b2c3d4e5f6a7b8c9d2",
  "status": "pending",
  "phoneNumbers": ["+13025551234"]
}
```

`GET /v1/phone-numbers/port-in` reports the status (`draft`, `pending`, `foc_confirmed`, `ported`, `exception`, `cancelled`). Ported numbers arrive voice-ready, and SMS-ready where supported ([porting](/platforms/phone-numbers/porting)).
</Steps>

</Tab>
<Tab value="Buy new numbers">

Call `POST /v1/phone-numbers/purchase` with `profileId` and `country`:

```bash
curl -X POST https://zernio.com/api/v1/phone-numbers/purchase \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"profileId": "66a1f0c2a4b9d3e8f1a2b3c4", "country": "US", "wantsSms": true}'
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

The number provisions in the same request, with no checkout redirect; with no payment method on file the call returns `402` and code `PAYMENT_REQUIRED`. `wantsSms: true` draws from SMS-capable inventory and `connectWhatsapp: false` leaves the number on calls and SMS ([provisioning](/platforms/phone-numbers/provisioning), [coverage and prices](/platforms/phone-numbers/availability)). Where Twilio has Regulatory Bundles, a regulated country answers `202` with `status: "kyc_required"` and a `kycUrl`, which you finish [over the API](/phone-numbers/get-phone-number-kyc-form) or hand to your customer as a white-label link ([KYC](/platforms/phone-numbers/kyc)).

</Tab>
<Tab value="Move a WhatsApp sender">

A WhatsApp sender's assets (the WABA, approved templates, display name, quality rating, messaging tier) live at Meta, not at Twilio, so reconnecting the same number through Zernio brings them along. Call `GET /v1/connect/whatsapp` with `profileId`:

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

Open `authUrl` and complete Meta's Embedded Signup with the Facebook login that owns the WABA (the one used for Twilio's "Continue with Facebook" flow). A WABA with several numbers gets a number selection step. With a Meta System User token, `POST /v1/connect/whatsapp/credentials` with `profileId`, `accessToken`, `wabaId` and `phoneNumberId` connects the number with no browser.

<Callout type="warn">
Message history does not transfer. Export what you need from Twilio (`GET /2010-04-01/.../Messages.json`) before winding down. Approved templates survive because they belong to the WABA, but you address them by name instead of `ContentSid`.
</Callout>

</Tab>
</Tabs>

To test before moving a number, the [sandbox](/platforms/whatsapp/sandbox) replaces Twilio's `join <code>` flow: `POST /v1/whatsapp/sandbox/sessions` with your phone, then reply to the verification message.

## Step 3: Get the account ids

Twilio addresses everything by phone number string. Zernio addresses WhatsApp by `accountId`; call `GET /v1/accounts?platform=whatsapp`:

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
      "username": "+1 555-765-4321",
      "displayName": "Acme Corp",
      "isActive": true
    }
  ]
}
```

Store the mapping from each `whatsapp:+E164` sender to its `_id`. SMS keeps raw E.164 numbers in `from` and `to`. To turn SMS on for a number you bought or ported, call `POST /v1/phone-numbers/{id}/sms` once (idempotent); US numbers also need [registration](#step-6-register-for-us-a2p).

## Step 4: Change the send calls

<Tabs items={['WhatsApp template', 'WhatsApp reply', 'SMS and MMS', 'Interactive']}>
<Tab value="WhatsApp template">

A business-initiated send (outside the 24-hour window) swaps `ContentSid` for the template name.

Twilio, form-encoded to `Messages.json`:

```
To=whatsapp:+15551234567
From=whatsapp:+15557654321
ContentSid=HX0123456789abcdef0123456789abcdef
ContentVariables={"1": "John", "2": "ORD-123"}
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
    "templateParams": ["John", "ORD-123"]
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

Store `conversationId` for replies. Sending to the same participant again reuses the conversation.

</Tab>
<Tab value="WhatsApp reply">

A free-form message inside the 24-hour window goes into the conversation.

Twilio, form-encoded to `Messages.json`:

```
To=whatsapp:+15551234567
From=whatsapp:+15557654321
Body=Your order shipped.
MediaUrl=https://cdn.example.com/receipt.png
```

Zernio:

```bash
curl -X POST https://zernio.com/api/v1/inbox/conversations/66c3d2ae7b4f6c8d0e1f2a3b/messages \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "message": "Your order shipped.",
    "attachmentUrl": "https://cdn.example.com/receipt.png",
    "attachmentType": "image"
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

Voice notes (`voiceNote: true`), locations, contact cards, reactions, quote replies (`replyTo`), read receipts and typing indicators are on the [inbox page](/platforms/whatsapp/inbox).

</Tab>
<Tab value="SMS and MMS">

Twilio, form-encoded to the same `Messages.json`:

```
To=+14155559876
From=+13025551234
Body=Your order shipped.
MediaUrl=https://cdn.example.com/receipt.png
```

Zernio:

```bash
curl -X POST https://zernio.com/api/v1/sms/messages \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: 4f9d2c9e-8a1b-4f30-9d2f-1c2b3a4d5e6f" \
  -d '{
    "from": "+13025551234",
    "to": "+14155559876",
    "text": "Your order shipped.",
    "mediaUrls": ["https://cdn.example.com/receipt.png"]
  }'
```

Response (`200`):

```json
{
  "id": "66c3d2ae7b4f6c8d0e1f2a3c",
  "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b",
  "status": "sent"
}
```

A message holds at most 10 segments (1,530 GSM-7 or 670 unicode characters), validated up front. `mediaUrls` (up to 10) makes it an MMS. The `Idempotency-Key` header makes retries safe ([idempotency](/guides/idempotency)). Twilio's `ScheduleType=fixed` with `SendAt` becomes `sendAt` on the same call, an ISO 8601 time with an offset that has to be in the future; the message is queued and `message.delivered` fires when it goes out. Replies thread into the same inbox conversation; STOP and START are handled for you, with the audit trail at `GET /v1/sms/opt-outs`; `GET /v1/sms/lookup?number=+1...` returns carrier and line type.

Traffic Twilio sent from an alphanumeric sender ID keeps that shape: register the name with `POST /v1/sms/sender-ids` and pass it as `from`. No number purchase, no carrier registration; 3 to 11 letters, digits and spaces, at least one letter. Sends are one-way, text-only, and cannot reach the US, Canada or Puerto Rico. [Sender IDs](/platforms/sms/sender-ids) has the daily cap and the country rules.

```bash
curl -X POST https://zernio.com/api/v1/sms/sender-ids \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "senderId": "ZERNIO" }'
```

Response (`200`):

```json
{
  "id": "66c3d2ae7b4f6c8d0e1f2a3d",
  "senderId": "ZERNIO",
  "isActive": true
}
```

</Tab>
<Tab value="Interactive">

Twilio expresses rich WhatsApp messages as Content API types; Zernio expresses them inline on the send:

| Twilio content type | Zernio equivalent |
|---------------------|-------------------|
| `twilio/text` | `message` |
| `twilio/media` | `attachmentUrl` + `attachmentType` |
| `twilio/quick-reply` | `buttons: [{type: "postback", title, payload}]` |
| `twilio/call-to-action` (URL) | `interactive: {type: "cta_url", ...}` |
| `twilio/list-picker` | `interactive: {type: "list", ...}` (session-only on both) |
| `twilio/card`, `twilio/carousel` | `interactive: {type: "carousel", ...}` (2 to 10 media or product cards) |
| `twilio/catalog` | `interactive: {type: "catalog_message"}`, plus `product` / `product_list` |
| `twilio/location` | `location: {latitude, longitude, name, address}` |
| `whatsapp/authentication` | `AUTHENTICATION` template with an `otp` button |
| `whatsapp/flows` | [Flows API](/platforms/whatsapp/flows) + `POST /v1/whatsapp/flows/send` |

Quick replies:

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "message": "Confirm your appointment?",
  "buttons": [
    {"type": "postback", "title": "Yes", "payload": "confirm_yes"},
    {"type": "postback", "title": "No", "payload": "confirm_no"}
  ]
}
```

A tap arrives on `message.received` as `metadata.interactiveType` and `metadata.interactiveId` (Twilio's `ButtonText` and `ButtonPayload`). Payload shapes: [commerce messages](/platforms/whatsapp/inbox#commerce-messages) and [inbox](/platforms/whatsapp/inbox).

</Tab>
</Tabs>

## Step 5: Change the webhooks

| | Twilio | Zernio |
|--|--------|--------|
| Payload | Form-encoded params (`From`, `Body`, `WaId`, ...) | JSON events with nested objects |
| Response | TwiML (`<Response/>`) | Any 2xx, body ignored |
| Registration | Per number or Messaging Service URL | `POST /v1/webhooks/settings`, team-wide and filtered by event |
| Signature | `X-Twilio-Signature`: HMAC-SHA1 over URL + sorted params, base64 | `X-Zernio-Signature`: HMAC-SHA256 over the raw JSON body, hex |
| Delivery status | `StatusCallback` per message | `message.sent`, `message.delivered`, `message.read`, `message.failed` events |
| Retries | 1 retry, only on connection timeout (by default) | 7 attempts over about 51 hours, then dead-letter |
| Dedup | `MessageSid` | `payload.id` (also the `X-Zernio-Event-Id` header) |

Call `POST /v1/webhooks/settings` once:

```bash
curl -X POST https://zernio.com/api/v1/webhooks/settings \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "messaging-events",
    "url": "https://yourapp.com/webhooks/zernio",
    "events": [
      "message.received", "message.sent", "message.delivered",
      "message.read", "message.failed", "conversation.started",
      "whatsapp.template.status_updated", "call.received", "call.ended"
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
    "name": "messaging-events",
    "url": "https://yourapp.com/webhooks/zernio",
    "events": ["message.received", "message.sent", "message.delivered", "message.read", "message.failed", "conversation.started", "whatsapp.template.status_updated", "call.received", "call.ended"],
    "isActive": true
  }
}
```

An inbound WhatsApp message maps field by field:

| Twilio param | Zernio (`message.received` payload) |
|--------------|-------------------------------------|
| `MessageSid` | `message.platformMessageId` (the wamid) |
| `From` (`whatsapp:+1...`) / `WaId` | `message.sender.id` / `message.sender.phoneNumber` |
| `ProfileName` | `message.sender.name` |
| `Body` | `message.text` |
| `MediaUrl0..N` | `message.attachments[].url` |
| `ButtonText` / `ButtonPayload` | `metadata.interactiveType` + `metadata.interactiveId` |
| `Latitude` / `Longitude` / `Address` / `Label` | `metadata.location` |
| `ReferralSourceId`, `ReferralBody`, ... | `metadata.referral` (CTWA, including `ctwa_clid`) |
| Cart orders (not available) | `metadata.order` |

Inbound SMS fires [`message.received`](/webhooks/inbox#messagereceived) with `platform: "sms"` (and [`conversation.started`](/webhooks/inbox#conversationstarted) the first time a thread appears); delivery states fire [`message.delivered`](/webhooks/inbox#messagedelivered) and [`message.failed`](/webhooks/inbox#messagefailed), the latter with the carrier's error code in `error`. The same data is readable with `GET /v1/inbox/conversations/{conversationId}/messages` (each message carries `deliveryStatus` and any `deliveryError`).

Twilio's URL-plus-sorted-params HMAC-SHA1 becomes a plain HMAC over the body:

```javascript
// Twilio (before): twilio.validateRequest(authToken, signature, url, params)

// Zernio (after):
const expected = crypto
  .createHmac('sha256', process.env.ZERNIO_WEBHOOK_SECRET)
  .update(rawBody)
  .digest('hex');
const valid = crypto.timingSafeEqual(
  Buffer.from(expected),
  Buffer.from(req.headers['x-zernio-signature'])
);
```

`GET /v1/webhooks/logs` keeps 30 days of deliveries and `POST /v1/webhooks/test` sends a test event ([webhooks](/webhooks)).

## Step 6: Register for US A2P

Brand and Campaign registrations made through Twilio belong to Twilio's CSP account at The Campaign Registry and do not transfer, so US long-code SMS needs a registration with Zernio (same registry, similar fields). Call `POST /v1/sms/registrations`:

```bash
curl -X POST https://zernio.com/api/v1/sms/registrations \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "registrationType": "standard_10dlc",
    "phoneNumbers": ["+13025551234"],
    "brand": {
      "entityType": "PRIVATE_PROFIT",
      "displayName": "Acme",
      "companyName": "Acme Corporation",
      "ein": "12-3456789",
      "country": "US",
      "vertical": "TECHNOLOGY",
      "street": "123 Market St", "city": "San Francisco",
      "state": "CA", "postalCode": "94103"
    },
    "campaign": {
      "usecase": "CUSTOMER_CARE",
      "description": "Order updates and customer support replies for Acme customers.",
      "messageFlow": "Customers opt in at checkout by checking the SMS updates box.",
      "sample1": "Your Acme order ORD-123 has shipped.",
      "helpMessage": "Acme support: reply with your question or email help@acme.com.",
      "optinKeywords": "START", "optinMessage": "You are subscribed to Acme updates. Reply STOP to opt out.",
      "optoutKeywords": "STOP", "optoutMessage": "You have been unsubscribed from Acme updates.",
      "helpKeywords": "HELP"
    }
  }'
```

Response (`200`):

```json
{
  "registrationId": "66d4a1b2c3d4e5f6a7b8c9d3",
  "status": "pending",
  "awaitingOtp": false
}
```

- `sole_prop_10dlc` (no EIN) returns `awaitingOtp: true` and texts an OTP for `POST /v1/sms/registrations/{id}/verify-otp`; `toll_free` covers toll-free verification.
- Poll `GET /v1/sms/registrations/{id}` from `pending` to `approved`, and appeal a rejection with `POST /v1/sms/registrations/{id}/appeal`.
- `POST /v1/sms/registrations/share` mints a single-use link so a customer fills in their own legal details, and `POST /v1/phone-numbers/{id}/sms/reuse-registration` puts more numbers on an approved campaign ([carrier registration](/platforms/sms/registration)).

<Callout type="warn">
A US number delivers SMS only once its registration is approved. Start the Zernio registration while Twilio still carries the traffic, and switch sending after `status: "approved"`.
</Callout>

## Step 7: Configure voice

Twilio's voice model is programmable: your server returns TwiML per call. Zernio's is configuration plus API calls, which covers the common business-phone patterns without an app server:

- Inbound: `POST /v1/phone-numbers/{id}/voice` sets forwarding (`tel:`, `sip:`, or `wss://` for an AI voice agent), voicemail, business hours, an IVR menu of up to 12 keys, recording and transcription ([setup](/platforms/voice/setup)).
- Outbound: `POST /v1/voice/calls`, with transfer, cost preview and a WebRTC softphone token ([outbound](/platforms/voice/outbound), [browser calling](/platforms/voice/browser-calling)).
- History: `GET /v1/calls` unifies phone and [WhatsApp](/platforms/whatsapp/calling) calls with per-call cost, transcript and recording ([history](/platforms/voice/history)), and `call.received`, `call.ended` and `call.failed` report them live ([call webhooks](/webhooks/calls)).

<Callout type="warn">
TwiML apps do not port. Dynamic call flows beyond a keypress menu, conferencing, SIP trunking and call queues have no Zernio equivalent. Keep that voice on Twilio and migrate messaging, or simplify to the configuration model above.
</Callout>

## What does not map

Everything above has a Zernio equivalent. These do not; keep them on Twilio or plan around them:

- Programmable voice at TwiML depth (dynamic call flows, conferencing, SIP trunking, queues).
- Verify's voice and email channels, Video, SendGrid email, RCS. Zernio has managed OTP over SMS: [Send a verification code](/verify/create-verification) delivers it and stores only its hash, and [Check a verification code](/verify/check-verification) verifies what the user typed.
- Messaging Services sender pools (sticky sender, geomatch, short codes). Alphanumeric sender IDs do map: see [Step 4](#step-4-change-the-send-calls).
- Link shortening with click tracking.
- Serverless Functions; your webhook handlers run on your own infrastructure.

## Step 8: Cut over

| Phase | Actions |
|-------|---------|
| Prep | Create profiles, build against the [sandbox](/platforms/whatsapp/sandbox), start US A2P registration early |
| WhatsApp | Reconnect senders through Zernio one number at a time: reconnect, verify webhooks, stop Twilio sends for it |
| SMS | Once registration is `approved`, port numbers in (or switch to new ones) and flip to `POST /v1/sms/messages` |
| Voice | Configure forwarding and IVR per number, or keep complex voice on Twilio |
| Cutoff | Export message history from Twilio, then release the Twilio numbers you ported away |

Meta's per-template conversation charges continue either way, because they are billed to the WABA, and template approvals survive the move for the same reason.

## If it fails

A `400` with `TEMPLATE_REQUIRED` on `POST /v1/inbox/conversations` is Twilio's error 63016: the recipient has not written in the last 24 hours, so business-initiated WhatsApp needs an approved template. Other first-day errors:

| Symptom | Twilio equivalent | Fix |
|---------|-------------------|-----|
| `401 Unauthorized` | Basic auth credentials | `Authorization: Bearer $ZERNIO_API_KEY` |
| Form-encoded body rejected | Twilio wire format | Send JSON with `Content-Type: application/json` |
| SMS blocked for a US number | Error 30034 (unregistered 10DLC) | Complete `/v1/sms/registrations` and wait for `approved` |
| Recipient gets nothing after STOP | Error 21610 | The recipient must text START; check `GET /v1/sms/opt-outs` |
| Sandbox cannot message a phone | Error 63015 (phone has not joined) | Activate the phone with `POST /v1/whatsapp/sandbox/sessions` and the verification reply |
| Template send ignores variables | `ContentVariables` JSON string | `templateParams`, an array in placeholder order |
| Webhook signature mismatch | URL and params HMAC-SHA1 | HMAC-SHA256 of the raw body against `X-Zernio-Signature` |

Every error uses the envelope in [error handling](/guides/error-handling).

The two sends in Node.js:

```javascript
const ZERNIO = 'https://zernio.com/api/v1';
const headers = {
  'Authorization': `Bearer ${process.env.ZERNIO_API_KEY}`,
  'Content-Type': 'application/json',
};

// Twilio: client.messages.create({ to: 'whatsapp:+1...', contentSid, contentVariables })
const sendWhatsAppTemplate = async ({ accountId, to, templateName, templateParams = [] }) => {
  const res = await fetch(`${ZERNIO}/inbox/conversations`, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      accountId,
      participantId: to,          // digits with country code, no whatsapp: prefix
      templateName,               // template name instead of ContentSid
      templateLanguage: 'en_US',
      templateParams,
    }),
  });
  const { data } = await res.json();
  return data; // { messageId, conversationId, ... }
};

// Twilio: client.messages.create({ to, from, body })
const sendSms = async ({ from, to, text }) => {
  const res = await fetch(`${ZERNIO}/sms/messages`, {
    method: 'POST',
    headers: { ...headers, 'Idempotency-Key': crypto.randomUUID() },
    body: JSON.stringify({ from, to, text }),
  });
  return res.json(); // { id, conversationId, status: "sent" }
};
```

## Related

- [WhatsApp](/platforms/whatsapp) and [phone numbers](/platforms/phone-numbers)
- [Webhooks](/webhooks)
- [Rate limits](/guides/rate-limits) and [idempotency](/guides/idempotency)
- [Workflows](/workflows)
- Support: support@zernio.com

---
