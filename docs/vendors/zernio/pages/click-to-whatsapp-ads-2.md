# Click-to-WhatsApp Ads

Capture the click id Meta attaches to a Click-to-WhatsApp conversation and send conversion events back to Meta so they attribute to the ad.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page a purchase or lead that happened inside a WhatsApp conversation is attributed to the Click-to-WhatsApp (CTWA) ad that started it. You need a connected WhatsApp account whose token carries `whatsapp_business_manage_events`, and a CTWA ad. A CTWA ad is a Meta ad that opens a WhatsApp conversation with your business instead of a website; create one with [`POST /v1/ads/ctwa`](/platforms/meta-ads/ctwa#click-to-whatsapp-ads). This page covers the WhatsApp side: the click id Meta attaches and the conversion events you send back.

## Step 1: The click id is captured for you

When someone reaches your number from a CTWA ad, Meta attaches a `referral` object with `ctwa_clid`, its click id for WhatsApp, to the first inbound message of the conversation. Zernio's webhook handler stores it on the [conversation](/messages/list-inbox-conversations) under `metadata`:

```json
{
  "metadata": {
    "ctwa_clid": "AbCdEfGhIjKlMn0pQrStUvWxYz",
    "ctwa_captured_at": "2027-01-01T08:18:44.991Z",
    "ctwa_source_id": "120000000000000000",
    "ctwa_source_url": "https://fb.me/...",
    "ctwa_headline": "Chat with us on WhatsApp",
    "ctwa_source_type": "ad"
  }
}
```

Capture is one-shot: once `ctwa_clid` is set, later messages from the same person never overwrite it, because Meta emits `referral` only on the first message after a click.

## Step 2: Provision the dataset [#conversions-api-for-business-messaging]

Conversion events that happen inside the WhatsApp conversation go back to Meta with `action_source = business_messaging`, so they attribute to the CTWA ad. This is separate from Meta Ads' [Conversions API](/platforms/meta-ads/capi) for web pixel events.

Meta needs a dataset linked to the WABA before it accepts events. Call `POST /v1/whatsapp/dataset` with `accountId`; Zernio creates the dataset and stores its id on the account as `metadata.metaCapiDatasetId`. A WABA owns at most one CTWA dataset, so the call is idempotent: a second call returns the same id with `created: false`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const accountId = '66b2e19d8c3f5a7e9d0b1c2d';

const { data: dataset } = await zernio.whatsapp.createWhatsAppDataset({
  body: { accountId }
});

console.log(dataset.datasetId, dataset.created);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()
account_id = "66b2e19d8c3f5a7e9d0b1c2d"

dataset = client.whatsapp.create_whats_app_dataset(account_id=account_id)

print(dataset["datasetId"], dataset["created"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/whatsapp/dataset" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"accountId": "66b2e19d8c3f5a7e9d0b1c2d"}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "datasetId": "987654321098765",
  "created": true
}
```

`GET /v1/whatsapp/dataset?accountId=...` returns the stored id without calling Meta (`datasetId: null` until provisioned), which is how to show whether tracking is set up on an account.

<Callout type="warn">
If Meta rejects the call with `(#100) Invalid parameter`, the usual cause is a WABA that has not finished business verification; complete it in Meta Business Manager and try again. The endpoint returns `422` with Meta's raw error, so other causes (region restrictions, app-level disablement) are visible. A token minted through Embedded Signup already carries `whatsapp_business_manage_events`; a System User token you mint for the [credentials flow](/platforms/whatsapp/connection#connect-with-credentials-headless) must include it yourself.
</Callout>

To use a dataset you already own instead, for example in another Business Manager, skip the provisioning call and set `metadata.metaCapiDatasetId` on the account. The WhatsApp account's token must then be able to reach that dataset: a WABA's System User token is scoped to the WABA's own Business Manager and cannot post to a pixel owned by a different Business (Meta returns code 100). Share the dataset with the WhatsApp app's Business in Meta Business Manager, or use one already in the same Business.

## Step 3: Send a conversion event

Call `POST /v1/whatsapp/conversions` with `accountId`, `eventName`, a stable `eventId` and the `conversationId`. Zernio replays the captured `ctwa_clid` on the event, forwards the WABA id as the per-channel attribution identifier, and hashes `email` and `externalId` before they leave.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: conversion } = await zernio.whatsapp.sendWhatsAppConversion({
  body: {
    accountId,
    conversationId: '66c3d2ae7b4f6c8d0e1f2a3b',
    eventName: 'LeadSubmitted',
    eventId: 'lead_abc_123',
    value: 49.00,
    currency: 'USD'
  }
});

console.log(conversion.eventsReceived, conversion.eventsFailed);
```
</Tab>
<Tab value="Python">
```python
conversion = client.whatsapp.send_whats_app_conversion(
    account_id=account_id,
    conversation_id="66c3d2ae7b4f6c8d0e1f2a3b",
    event_name="LeadSubmitted",
    event_id="lead_abc_123",
    value=49.00,
    currency="USD",
)

print(conversion["eventsReceived"], conversion["eventsFailed"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/whatsapp/conversions" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b",
    "eventName": "LeadSubmitted",
    "eventId": "lead_abc_123",
    "value": 49.00,
    "currency": "USD"
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "platform": "metaads",
  "eventsReceived": 1,
  "eventsFailed": 0,
  "failures": [],
  "traceId": "AbCdEfGhIjKlMn0pQrStUv"
}
```

A `200` means the request reached Meta, not that Meta accepted the event: check `eventsFailed` and `failures[]`. `traceId` is Meta's `fbtrace_id` for Events Manager. Reuse the same `eventId` to suppress duplicates; Meta dedupes it against pixel events with the same id.

Identify the conversation either way:

- `conversationId`, preferred: the conversation `_id` from [List conversations](/messages/list-inbox-conversations).
- `phoneE164`, fallback: digits only, no `+`. Zernio picks the most recent CTWA-attributed conversation for that phone on the account.

### Supported event names

Meta's allowlist for `business_messaging` events is narrower than the pixel Conversions API:

| Event | Use for |
|---|---|
| `LeadSubmitted` | Form filled, contact details captured, lead qualified |
| `Purchase` | Customer completed a purchase (payment confirmed, invoice issued) |
| `AddToCart` | Customer added an item to the cart in the conversation |
| `InitiateCheckout` | Customer started a checkout flow |
| `ViewContent` | Customer viewed a specific product or service |

`Lead`, the standard pixel name, is not accepted on `business_messaging` events; use `LeadSubmitted`. `CompleteRegistration`, `Subscribe`, `Schedule`, `Contact`, `StartTrial`, `AddPaymentInfo`, `Search` and `SubmitApplication` are rejected too. Zernio verified the list live against Graph API v25.0 and enforces it at the request boundary, so an unsupported name is a `400` before anything reaches Meta.

### Attribution window and test mode

Meta's attribution window is 7 days from the click. An event whose `ctwa_clid` was captured more than 7 days ago still posts but does not attribute; `eventTime` defaults to the time of the request. Pass `testCode: "TEST12345"` at the request root to route events to the Test Events tab in Meta Events Manager without touching production data.

## Step 4: Check recent activity

Call `GET /v1/whatsapp/conversions` with `accountId` to list the most recent events sent, for a "Conversions" panel in your own dashboard or for alerting on attribution sends.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: recent } = await zernio.whatsapp.listWhatsAppConversions({
  query: { accountId, limit: 50 }
});

for (const e of recent.events) {
  console.log(e.timestamp, e.eventName, e.conversationId, e.eventsReceived, e.eventsFailed);
}
```
</Tab>
<Tab value="Python">
```python
recent = client.whatsapp.list_whats_app_conversions(account_id=account_id, limit=50)

for e in recent["events"]:
    print(e["timestamp"], e["eventName"], e["conversationId"], e["eventsReceived"], e["eventsFailed"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/whatsapp/conversions?accountId=66b2e19d8c3f5a7e9d0b1c2d&limit=50" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "events": [
    {
      "timestamp": "2027-01-01T09:15:02Z",
      "eventName": "LeadSubmitted",
      "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b",
      "eventsReceived": 1,
      "eventsFailed": 0,
      "traceId": "AbCdEfGhIjKlMn0pQrStUv",
      "durationMs": 412
    }
  ]
}
```

The feed comes from delivery logs, not an event store, so it covers about 30 days of log retention. For a long-lived audit trail, persist each call to `POST /v1/whatsapp/conversions` on your side.

## If it fails

A `422` from Step 3 means there is nothing to attribute:

```json
{
  "error": "The resolved conversation has no captured ctwa_clid"
}
```

Three causes. The conversation did not start from a CTWA ad; or it predates Zernio's capture; or Meta omitted `ctwa_clid` from the referral, which it does on a minority of referrals on any number, most often WhatsApp Status placements. The third one recovers on its own: when Meta's automatic event identification later detects a lead or purchase in the thread, [`whatsapp.automatic_event`](/webhooks/whatsapp#whatsappautomatic_event) carries the clid, Zernio writes it back onto the conversation and this call starts working for that thread. Send the event only for conversations whose `metadata.ctwa_clid` is set, and re-read the conversation after that webhook rather than giving up on the first `422`. The other `422` is a missing `metaCapiDatasetId` on the account; run Step 2. A `404` means the `conversationId` does not exist. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Click-to-WhatsApp ads](/platforms/meta-ads/ctwa#click-to-whatsapp-ads): create the ad with `POST /v1/ads/ctwa`.
- [Conversions API](/platforms/meta-ads/capi): web pixel events for the rest of your funnel.
- [WhatsApp webhooks](/webhooks/whatsapp): `whatsapp.automatic_event`, Meta's own lead and purchase detection in CTWA conversations.
- [Conversions API reference](/whatsapp/send-whatsapp-conversion): every field, including `contentIds`, `email` and `externalId`.
- [Connection & Setup](/platforms/whatsapp/connection): the scope a System User token needs.

---
