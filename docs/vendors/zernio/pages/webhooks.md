# Webhooks

Create a webhook endpoint, receive your first event, and verify, deduplicate and retry deliveries the way Zernio sends them.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Zernio POSTs an event to your webhook endpoint when something happens: a post publishes, a DM arrives, an account disconnects, a number activates. Enable it with `POST /v1/webhooks/settings`. The secret Zernio signs deliveries with is the `secret` you send in that call; Zernio never generates one for you, and the same endpoints are editable in the [webhooks dashboard](https://zernio.com/dashboard/webhooks).

## First event

Call `POST /v1/webhooks/settings` with `name`, `url`, `events` and a `secret`. You can create up to 50 endpoints per user.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.webhooks.createWebhookSettings({
  body: {
    name: 'Production',
    url: 'https://example.com/webhooks/zernio',
    secret: process.env.ZERNIO_WEBHOOK_SECRET,
    events: ['post.published', 'post.failed', 'message.received']
  }
});

const webhookId = created.webhook._id;
```
</Tab>
<Tab value="Python">
```python
import os
from zernio import Zernio

client = Zernio()

created = client.webhooks.create_webhook_settings(
    name="Production",
    url="https://example.com/webhooks/zernio",
    secret=os.environ["ZERNIO_WEBHOOK_SECRET"],
    events=["post.published", "post.failed", "message.received"],
)

webhook_id = created["webhook"]["_id"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/webhooks/settings \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Production",
    "url": "https://example.com/webhooks/zernio",
    "secret": "'"$ZERNIO_WEBHOOK_SECRET"'",
    "events": ["post.published", "post.failed", "message.received"]
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "webhook": {
    "_id": "507f1f77bcf86cd799439011",
    "name": "Production",
    "url": "https://example.com/webhooks/zernio",
    "events": ["post.published", "post.failed", "message.received"],
    "isActive": true,
    "failureCount": 0
  }
}
```

`webhook._id` is the `webhookId` for the test call. Call `POST /v1/webhooks/test` to send a `webhook.test` event to the endpoint right away:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: tested } = await zernio.webhooks.testWebhook({
  body: { webhookId }
});

console.log(tested.message);
```
</Tab>
<Tab value="Python">
```python
tested = client.webhooks.test_webhook(webhook_id=webhook_id)

print(tested["message"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/webhooks/test \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"webhookId": "507f1f77bcf86cd799439011"}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "message": "Test webhook sent successfully"
}
```

Your endpoint receives one `POST` with these headers and body:

```http
POST /webhooks/zernio HTTP/1.1
Content-Type: application/json
User-Agent: Zernio-Webhooks/1.0
X-Zernio-Event: webhook.test
X-Zernio-Event-Id: 3f0c1c2e-6c4a-4d3e-9b1f-2a7d8e9f0a1b
X-Zernio-Signature: 5d41402abc4b2a76b9719d911017c592e4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9
```

```json
{
  "id": "3f0c1c2e-6c4a-4d3e-9b1f-2a7d8e9f0a1b",
  "event": "webhook.test",
  "message": "This is a test webhook from Zernio",
  "timestamp": "2027-01-01T17:00:00Z"
}
```

Return any `2xx` within 5 seconds. `webhook.test` reaches the endpoint whatever its `events` list says, so it works before you subscribe to anything real. A `500` from the test call means your endpoint did not answer `2xx`:

```json
{
  "success": false,
  "message": "Test webhook failed"
}
```

Check the endpoint's URL, then read the attempt in [webhook logs](/webhooks/get-webhook-logs) or the [dashboard](https://zernio.com/dashboard/webhooks).

## Events

Every event is one `event` name in the `events` array. Each area page documents the payload of every event it covers.

| Area | Events |
| --- | --- |
| [Posts](/webhooks/posts) | [`post.scheduled`](/webhooks/posts#postscheduled), [`post.platform.published`](/webhooks/posts#postplatformpublished), [`post.platform.failed`](/webhooks/posts#postplatformfailed), [`post.published`](/webhooks/posts#postpublished), [`post.partial`](/webhooks/posts#postpartial), [`post.failed`](/webhooks/posts#postfailed), [`post.tiktok.url_resolved`](/webhooks/posts#posttiktokurl_resolved), [`post.platform.deleted`](/webhooks/posts#postplatformdeleted), [`post.cancelled`](/webhooks/posts#postcancelled), [`post.recycled`](/webhooks/posts#postrecycled), [`post.external.created`](/webhooks/posts#postexternalcreated), [`post.external.updated`](/webhooks/posts#postexternalupdated), [`post.external.deleted`](/webhooks/posts#postexternaldeleted) |
| [Inbox](/webhooks/inbox) | [`message.received`](/webhooks/inbox#messagereceived), [`message.sent`](/webhooks/inbox#messagesent), [`conversation.started`](/webhooks/inbox#conversationstarted), [`conversation.control_changed`](/webhooks/inbox#conversationcontrol_changed), [`message.edited`](/webhooks/inbox#messageedited), [`message.deleted`](/webhooks/inbox#messagedeleted), [`message.delivered`](/webhooks/inbox#messagedelivered), [`message.read`](/webhooks/inbox#messageread), [`message.failed`](/webhooks/inbox#messagefailed), [`reaction.received`](/webhooks/inbox#reactionreceived), [`referral.received`](/webhooks/inbox#referralreceived), [`comment.received`](/webhooks/inbox#commentreceived), [`review.new`](/webhooks/inbox#reviewnew), [`review.updated`](/webhooks/inbox#reviewupdated) |
| [Accounts](/webhooks/accounts) | [`account.connected`](/webhooks/accounts#accountconnected), [`account.disconnected`](/webhooks/accounts#accountdisconnected) |
| [Analytics](/webhooks/analytics) | [`analytics.synced`](/webhooks/analytics#analyticssynced) |
| [Ads](/webhooks/ads) | [`account.ads.initial_sync_completed`](/webhooks/ads#accountadsinitial_sync_completed), [`lead.received`](/webhooks/ads#leadreceived), [`ad.status_changed`](/webhooks/ads#adstatus_changed) |
| [Calls](/webhooks/calls) | [`call.received`](/webhooks/calls#callreceived), [`call.ended`](/webhooks/calls#callended), [`call.failed`](/webhooks/calls#callfailed), [`call.permission_request`](/webhooks/calls#callpermission_request) |
| [WhatsApp](/webhooks/whatsapp) | [`whatsapp.template.status_updated`](/webhooks/whatsapp#whatsapptemplatestatus_updated), [`whatsapp.template.category_updated`](/webhooks/whatsapp#whatsapptemplatecategory_updated), [`whatsapp.account.name_status_updated`](/webhooks/whatsapp#whatsappaccountname_status_updated), [`whatsapp.automatic_event`](/webhooks/whatsapp#whatsappautomatic_event) |
| [Phone numbers](/webhooks/phone-numbers) | [`whatsapp.number.kyc_submitted`](/webhooks/phone-numbers#whatsappnumberkyc_submitted), [`whatsapp.number.activated`](/webhooks/phone-numbers#whatsappnumberactivated), [`whatsapp.number.declined`](/webhooks/phone-numbers#whatsappnumberdeclined), [`whatsapp.number.action_required`](/webhooks/phone-numbers#whatsappnumberaction_required), [`whatsapp.number.verification_required`](/webhooks/phone-numbers#whatsappnumberverification_required), [`whatsapp.number.suspended`](/webhooks/phone-numbers#whatsappnumbersuspended), [`whatsapp.number.reactivated`](/webhooks/phone-numbers#whatsappnumberreactivated), [`whatsapp.number.released`](/webhooks/phone-numbers#whatsappnumberreleased), [`phone_number.stock_available`](/webhooks/phone-numbers#phone_numberstock_available) |
| [Verify](/verify/create-verification) | [`verification.approved`](#verification-events), [`verification.failed`](#verification-events) |

Subscribe an endpoint only to the events it handles. Manage endpoints with [List webhooks](/webhooks/get-webhook-settings), [Update webhook settings](/webhooks/update-webhook-settings) and [Delete webhook settings](/webhooks/delete-webhook-settings).

### Verification events

The two managed-OTP events have no area page: they belong to a verification, not to a connected account. `verification.approved` fires when the recipient submits the right code to [Check a verification code](/verify/check-verification), `verification.failed` when the attempts run out, with `reason: "max_attempts_reached"`. Both carry `verification.verificationId`, `verification.channel` (`sms`) and `verification.to`. Send the code with [Send a verification code](/verify/create-verification).

**Payload for `verification.approved`:**

- **id** `string`: No description
- **event** `string`: No description - one of: verification.approved
- **timestamp** `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.
- **verification** `object`: 
  - **verificationId** `string`: No description
  - **channel** `string`: No description - one of: sms
  - **to** `string`: No description

**Payload for `verification.failed`:**

- **id** `string`: No description
- **event** `string`: No description - one of: verification.failed
- **timestamp** `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.
- **verification** `object`: 
  - **verificationId** `string`: No description
  - **channel** `string`: No description - one of: sms
  - **to** `string`: No description
- **reason** `string`: No description - one of: max_attempts_reached

## How it behaves

### Delivery retries

Zernio delivers an event up to 7 times, counting the first attempt. A delivery succeeds when your endpoint returns a `2xx` within 5 seconds. Any other outcome (a non-`2xx` status, a timeout, a connection error) schedules the next attempt on an exponential backoff capped at 24 hours, measured from the moment the previous attempt finished:

| Attempt | Delay before this attempt | Cumulative time since the first attempt |
| --- | --- | --- |
| 1 | immediate | 0 |
| 2 | 10s | ~10s |
| 3 | 1m 40s | ~1m 50s |
| 4 | 16m 40s | ~18m 30s |
| 5 | 2h 46m 40s | ~3h 5m |
| 6 | 24h (capped) | ~27h 5m |
| 7 | 24h (capped) | ~51h 5m |

After the 7th failure Zernio moves the event to a dead-letter queue and stops retrying it. Every attempt is visible in [webhook logs](/webhooks/get-webhook-logs) and the [dashboard](https://zernio.com/dashboard/webhooks); `attemptNumber` says which try produced each entry, and [Redeliver](/webhooks/redeliver-webhook-event) sends a dead-lettered event again.

To stay under the 5-second limit, persist the event and return `2xx`, then process it on a background worker.

Zernio disables an endpoint only when it has had no successful delivery for 3 days and has either reached 20 consecutive terminal failures (events that exhausted every attempt) or been failing continuously for 3 days. One successful delivery inside that window keeps it enabled whatever the count. Zernio emails the owner; re-enable it with `isActive: true` on [Update webhook settings](/webhooks/update-webhook-settings). You can also pause or remove an endpoint yourself at any time.

### At-least-once delivery

Zernio delivers every event at least once. The same event can arrive twice when a previous attempt's response was lost or your endpoint took longer than 5 seconds to answer, so your handler must be idempotent.

Every payload carries a stable event id, repeated as a header:

- `payload.id`, the canonical event id (UUID).
- `X-Zernio-Event-Id`, the same value as a header.
- `X-Late-Event-Id`, the legacy alias, kept for backward compatibility.

Use it as your deduplication key: insert the id into a unique-indexed table or cache before processing, and skip the payload when the insert conflicts.

### Signatures

Zernio signs every delivery when the endpoint has a `secret`. `X-Zernio-Signature` is the lowercase hex HMAC-SHA256 of the raw request body keyed by that secret; `X-Late-Signature` is the legacy alias with the same value. Read the raw body, compute the HMAC and compare:

<Tabs items={['Node.js', 'Python']}>
<Tab value="Node.js">
```typescript
import crypto from "crypto";

export const POST = async (req: Request) => {
  const webhookSignature = req.headers.get("X-Zernio-Signature");
  if (!webhookSignature) {
    return new Response("No signature provided.", { status: 401 });
  }

  const secret = process.env.ZERNIO_WEBHOOK_SECRET;
  if (!secret) {
    return new Response("No secret provided.", { status: 401 });
  }

  const rawBody = await req.text();

  const computedSignature = crypto
    .createHmac("sha256", secret)
    .update(rawBody)
    .digest("hex");

  if (webhookSignature !== computedSignature) {
    return new Response("Invalid signature", { status: 400 });
  }

  const payload = JSON.parse(rawBody);
  // Handle the webhook event
  // ...
};
```
</Tab>
<Tab value="Python">
```python
import hashlib
import hmac
import json
import os

from fastapi import FastAPI, Request, Response

app = FastAPI()

@app.post("/webhooks/zernio")
async def handle_webhook(request: Request):
    signature = request.headers.get("X-Zernio-Signature")
    if not signature:
        return Response("No signature provided.", status_code=401)

    secret = os.environ.get("ZERNIO_WEBHOOK_SECRET")
    if not secret:
        return Response("No secret provided.", status_code=401)

    raw_body = await request.body()
    computed = hmac.new(secret.encode(), raw_body, hashlib.sha256).hexdigest()

    if not hmac.compare_digest(computed, signature):
        return Response("Invalid signature", status_code=400)

    payload = json.loads(raw_body)
    # Handle the webhook event
    return Response(status_code=200)
```
</Tab>
</Tabs>

A mismatch means the request did not come from Zernio or the body changed in transit. Do not process it.

### The test event

Zernio sends `webhook.test` to an endpoint when you call [Test webhook](/webhooks/test-webhook) or press test in the [dashboard](https://zernio.com/dashboard/webhooks), whatever events the endpoint subscribes to. It is sent once, synchronously, and is never retried.

**Payload for `webhook.test`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: webhook.test
- **message** (required) `string`: Human-readable test message
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this test event (set once when the payload is built). Test fires are sent synchronously as a single attempt; a later redelivery of this event keeps the original value.

## Related

- [Create webhook settings](/webhooks/create-webhook-settings): every field, including `customHeaders` and `disabledResourceGroups`.
- [Webhook logs](/webhooks/get-webhook-logs): every attempt with its status code and response body.
- [Post webhooks](/webhooks/posts): the events behind a scheduled post.
- [Inbox webhooks](/webhooks/inbox): `message.received` and what it carries.
- [Multi-tenant](/multi-tenant): route events to the right customer by `account.profileId`.

---
