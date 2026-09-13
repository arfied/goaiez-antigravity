# Sending

Send an SMS or MMS from an SMS-enabled number with POST /v1/sms/messages, schedule it, read opt-outs and look up a number's line type.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you have sent an SMS and an MMS from your number and know how replies and delivery reach you. You need an [SMS-enabled number](/platforms/sms/registration#enable-sms-on-the-number-first) (approved for US numbers) and usage-based billing. `from` also accepts a branded [sender ID](/platforms/sms/sender-ids) such as `ZERNIO` for one-way international sends, with no number and no US registration.

## Step 1: Send an SMS

Call `POST /v1/sms/messages` with `from`, `to` and `text`. Both numbers are normalized to E.164, so `from` matches however you format it, and replies thread into the same [inbox](/messages/list-inbox-conversations) conversation. `text` is at most 10 segments (1,530 GSM-7 or 670 unicode characters).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: sent } = await zernio.sms.sendSms({
  body: {
    from: '+14155550100',
    to: '+13105551234',
    text: 'Acme: your order #1234 has shipped. Reply STOP to opt out.',
  }
});
console.log(sent.id, sent.status);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

sent = client.sms.send_sms(
    from_="+14155550100",
    to="+13105551234",
    text="Acme: your order #1234 has shipped. Reply STOP to opt out.",
)
print(sent["id"], sent["status"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/sms/messages" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"from": "+14155550100", "to": "+13105551234", "text": "Acme: your order #1234 has shipped. Reply STOP to opt out."}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "id": "67c9d0e1f2a3b4c5d6e7f8a9",
  "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b",
  "status": "sent"
}
```

Delivery and replies arrive as webhooks, not by polling: this message's outcome fires [`message.delivered`](/webhooks/inbox#messagedelivered) or [`message.failed`](/webhooks/inbox#messagefailed) (with the carrier's error code), an inbound reply fires [`message.received`](/webhooks/inbox#messagereceived) with `platform: "sms"`, and the first message of a new thread also fires `conversation.started`. Send an `Idempotency-Key` header so a retry replays the original response instead of sending twice ([idempotency](/guides/idempotency)).

A US number must have an approved [carrier registration](/platforms/sms/registration) before you can send. Until the registration reaches `approved` the number stays inactive, and `from` matches active numbers only, so the send comes back as a `404`.

## Step 2: Send an MMS

Add `mediaUrls` (public URLs, up to 10) to attach media. Pass `text` too for a caption, or omit it to send media only.

```bash
curl -X POST "https://zernio.com/api/v1/sms/messages" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "from": "+14155550100",
    "to": "+13105551234",
    "text": "Here is your receipt",
    "mediaUrls": ["https://acme.example.com/receipts/1234.png"]
  }'
```

Response (`200`):

```json
{
  "id": "67c9d0e1f2a3b4c5d6e7f8aa",
  "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b",
  "status": "sent"
}
```

To send later, add `sendAt` as an ISO 8601 time with offset, for example `"sendAt": "2027-01-01T12:00:00Z"`; it must be in the future.

## Opt-outs

Recipients who reply STOP are opted out by the carrier, and further sends to them are refused with `409`, never silently dropped. Only the recipient can re-subscribe, by replying START. Call `GET /v1/sms/opt-outs` (or `format=csv`) to keep your own suppression list in sync. `limit` defaults to 500 and tops out at 5,000: the list is truncated to it, most recent first, and there is no cursor, so raise `limit` rather than paging.

```bash
curl "https://zernio.com/api/v1/sms/opt-outs" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "optOuts": [
    { "phoneNumber": "+13105559999", "optedOutAt": "2027-01-02T09:30:00Z", "keyword": "STOP", "from": "+14155550100" }
  ],
  "count": 1
}
```

## Look up a number

Call `GET /v1/sms/lookup` with `number` for its carrier and line type (`mobile`, `landline`, `voip`, `toll-free`, `unknown`) plus `smsReachable`. Each lookup is billed by the carrier-data provider, so call it when you validate an opt-in list, not on every send.

```bash
curl "https://zernio.com/api/v1/sms/lookup?number=%2B13105551234" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "phoneNumber": "+13105551234",
  "carrierName": "T-Mobile USA",
  "lineType": "mobile",
  "smsReachable": true
}
```

## If it fails

A `409` means the recipient replied STOP, or the same `Idempotency-Key` is still in flight:

```json
{
  "error": "Recipient +13105559999 has opted out of SMS from this number",
  "type": "invalid_request_error"
}
```

Drop the recipient from your list; they come back only by replying START. A `404` means no active SMS-enabled number matches `from`, which covers both a number you do not own and a US number whose registration has not been approved; a `422` means the `Idempotency-Key` was reused with a different body. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Carrier registration](/platforms/sms/registration): the US step before sending.
- [Sender IDs](/platforms/sms/sender-ids): send from a brand name internationally.
- [Send an SMS/MMS](/sms/send-sms): every field of the request.
- [Inbox webhooks](/webhooks/inbox): `message.received`, `message.delivered` and `message.failed` payloads.
- [SMS rates](/pricing/sms): per-segment prices by destination.

---
