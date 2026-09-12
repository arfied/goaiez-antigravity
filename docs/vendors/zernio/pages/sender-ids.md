# Sender IDs

Send one-way international SMS from a brand name like ZERNIO instead of a phone number, with POST /v1/sms/sender-ids and the regular send endpoint.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';
import { PlatformCapabilities } from '@/components/platform-capabilities';

When you finish this page your messages arrive from your brand name (`ZERNIO`) instead of a phone number, the way banks and airlines text. You need usage-based billing with a payment method on file. There is no number to buy, no monthly fee and no US carrier registration: create the name once with `POST /v1/sms/sender-ids`, then pass it as `from` when sending.

## Quick reference

<PlatformCapabilities rows={[
  { property: 'Format', value: '3 to 11 characters: letters, digits, spaces, at least one letter' },
  { property: 'Direction', value: 'One-way; recipients cannot reply' },
  { property: 'Destinations', value: 'International only; never US, Canada or Puerto Rico. Send from a phone number for those.' },
  { property: 'Content', value: 'Text only, no MMS' },
  { property: 'Cost', value: 'Same per-segment rate as regular SMS; the name itself is free' },
  { property: 'Daily limit', value: '500 messages per day per team by default, raisable on request' },
  { property: 'US registration', value: 'Not required; 10DLC does not apply' },
]} />

## Step 1: Create a sender ID

Call `POST /v1/sms/sender-ids` with `senderId`. The name is usable at once.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: sender } = await zernio.sms.createSmsSenderId({
  body: { senderId: 'ZERNIO' }
});
console.log(sender.id, sender.isActive);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

sender = client.sms.create_sms_sender_id(sender_id="ZERNIO")
print(sender["id"], sender["isActive"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/sms/sender-ids" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"senderId": "ZERNIO"}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "id": "67b8c9d0e1f2a3b4c5d6e7f8",
  "senderId": "ZERNIO",
  "isActive": true
}
```

Names are vetted at creation. Names that impersonate well-known brands or institutions (payment providers, delivery carriers, banks, government) are rejected with `422`. Names are not exclusive: the same sender ID can be registered by any number of teams, and delivery receipts and billing always track the team that sent each message. A team can hold up to 1,000 active sender IDs (`403` with `code: "sender_id_limit_reached"` beyond that; contact support to raise it).

`GET /v1/sms/sender-ids` lists your names with the daily budget; `DELETE /v1/sms/sender-ids/{id}` deactivates one, and creating the same name again restores it.

## Step 2: Send from it

Pass the sender ID as `from` on the regular [send endpoint](/platforms/sms/sending); everything else is unchanged.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: sent } = await zernio.sms.sendSms({
  body: {
    from: 'ZERNIO',
    to: '+34612345678',
    text: 'Your verification code is 482913',
  }
});
console.log(sent.id, sent.status);
```
</Tab>
<Tab value="Python">
```python
sent = client.sms.send_sms(
    from_="ZERNIO",
    to="+34612345678",
    text="Your verification code is 482913",
)
print(sent["id"], sent["status"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/sms/messages" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"from": "ZERNIO", "to": "+34612345678", "text": "Your verification code is 482913"}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "id": "67c9d0e1f2a3b4c5d6e7f8ab",
  "conversationId": "66c3d2ae7b4f6c8d0e1f2a3c",
  "status": "sent"
}
```

Delivery works like any SMS: track it with the [`message.delivered` and `message.failed`](/webhooks/inbox) webhooks, and each segment bills at the destination country's [SMS rate](/pricing/sms).

## Country behavior

Most of Europe and many other markets display alphanumeric senders as they are. Some countries substitute a numeric sender to guarantee delivery, and a few (India and the UAE, for example) require the name to be pre-registered with local carriers before it displays; contact support if you need a registration-required destination.

## Daily limits

Each team has a daily sender-ID message budget, shared across all its names and reset at midnight UTC:

| Level | Messages per day |
|---|---|
| 1 (default) | 500 |
| 2 | 2,000 |
| 3 | 10,000 |
| 4 | 25,000 |

Request a higher level with `POST /v1/sms/sender-ids/limit-request` and `requestedCap` plus `reason` (your use case and audience); requests are reviewed within a business day, one at a time. `GET /v1/sms/sender-ids` reports the current `budget` (`cap`, `usedToday`, `level` and any `pendingRequest`).

## If it fails

A `403` with `code: "alpha_destination_not_supported"` means `to` is in the US, Canada or Puerto Rico; the message is refused before it reaches the carrier:

```json
{
  "error": "Alphanumeric sender IDs cannot deliver to +13105551234",
  "type": "permission_error",
  "code": "alpha_destination_not_supported"
}
```

Send to those destinations from a [phone number](/platforms/phone-numbers). A `403` with `code: "mms_not_supported"` means `mediaUrls` was attached; a `429` with `code: "alpha_daily_limit_reached"` means today's budget is spent, and a `402` means no payment method is on file. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Sending](/platforms/sms/sending): the send endpoint, opt-outs and lookups.
- [Phone numbers](/platforms/phone-numbers): what to send from for 2-way conversations or US recipients.
- [Create an alphanumeric sender ID](/sms/create-sms-sender-id) and [Request a higher sender ID daily limit](/sms/request-sms-sender-id-limit-increase): every field.
- [SMS rates](/pricing/sms): per-segment prices by destination.

---
