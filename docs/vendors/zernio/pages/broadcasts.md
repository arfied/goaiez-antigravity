# Broadcasts

Send an approved WhatsApp template to many recipients with per-recipient variables, now or at a scheduled time, with delivery tracking per recipient.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page a template message has gone out to a list of phone numbers, each with its own variable values, and you can read who was reached. You need a connected WhatsApp account, its `accountId` and `profileId`, and an `APPROVED` template ([Templates](/platforms/whatsapp/templates)). Each recipient counts as one [outbound message](/pricing#outbound-messages); Meta's template fee is separate and goes to your WABA ([who bills what](/platforms/whatsapp/pricing)). Meta's messaging-limit tier caps how many of them actually deliver: a freshly connected number is on `TIER_250`, 250 unique contacts per day, and a larger list silently stops there. Read the number's current tier with [Number status](/platforms/whatsapp/phone-numbers#number-status).

## Step 1: Create the broadcast

Call `POST /v1/broadcasts` with `platform: "whatsapp"`, the account, and a `template` naming an approved template. The broadcast is created as a draft.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.broadcasts.createBroadcast({
  body: {
    profileId: '66a1f0c2a4b9d3e8f1a2b3c4',
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    platform: 'whatsapp',
    name: 'Order confirmations',
    template: {
      name: 'order_confirmation',
      language: 'en',
      components: [{
        type: 'body',
        parameters: [
          { type: 'text', text: '{{1}}' },
          { type: 'text', text: '{{2}}' }
        ]
      }],
      variableMapping: {
        '1': { field: 'name' },
        '2': { field: 'custom', customValue: 'VIP-2027' }
      }
    }
  }
});

const broadcastId = created.broadcast.id;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.broadcasts.create_broadcast(
    profile_id="66a1f0c2a4b9d3e8f1a2b3c4",
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    platform="whatsapp",
    name="Order confirmations",
    template={
        "name": "order_confirmation",
        "language": "en",
        "components": [{
            "type": "body",
            "parameters": [
                {"type": "text", "text": "{{1}}"},
                {"type": "text", "text": "{{2}}"}
            ]
        }],
        "variableMapping": {
            "1": {"field": "name"},
            "2": {"field": "custom", "customValue": "VIP-2027"}
        }
    }
)

broadcast_id = created["broadcast"]["id"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/broadcasts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4",
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platform": "whatsapp",
    "name": "Order confirmations",
    "template": {
      "name": "order_confirmation",
      "language": "en",
      "components": [{
        "type": "body",
        "parameters": [
          {"type": "text", "text": "{{1}}"},
          {"type": "text", "text": "{{2}}"}
        ]
      }],
      "variableMapping": {
        "1": { "field": "name" },
        "2": { "field": "custom", "customValue": "VIP-2027" }
      }
    }
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "broadcast": {
    "id": "66d4a1b2c3e4f5a6b7c8d9e2",
    "name": "Order confirmations",
    "platform": "whatsapp",
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "status": "draft",
    "createdAt": "2027-01-01T09:00:00Z"
  }
}
```

`broadcast.id` is the `broadcastId` for the next steps. A template with no variables needs only `name` and `language`.

### Template variables

A template body holds numbered placeholders, `{{1}}`, `{{2}}` and so on, fixed when the template was created (`Hi {{1}}, your order {{2}} has been confirmed.`). Broadcasts fill numbered placeholders only, not named ones.

`variableMapping` maps each position to a contact field or a fixed value, and Zernio resolves it per recipient at send time, so `{{1}}` becomes each contact's own name:

| `field` | Resolves to |
| --- | --- |
| `name` | The contact's name (falls back to "there" if unset) |
| `phone` | The recipient's phone number |
| `email` | The contact's email |
| `company` | The contact's company |
| `custom` | The literal `customValue` string, the same for every recipient |

<Callout type="warn">
The number of `variableMapping` entries must match the number of placeholders in the template body. A mismatch makes Meta reject the send with a parameter-count error (code 132000).
</Callout>

## Step 2: Add recipients

Call `POST /v1/broadcasts/{broadcastId}/recipients` with `phones` in E.164 format (contacts are created for numbers Zernio has not seen), `contactIds` for existing contacts, or `useSegment: true` to pull every contact matching the broadcast's `segmentFilters`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: recipients } = await zernio.broadcasts.addBroadcastRecipients({
  path: { broadcastId },
  body: { phones: ['+13105551234', '+442071234567'] }
});

console.log(recipients.added, recipients.skipped);
```
</Tab>
<Tab value="Python">
```python
recipients = client.broadcasts.add_broadcast_recipients(
    broadcast_id=broadcast_id,
    phones=["+13105551234", "+442071234567"],
)

print(recipients["added"], recipients["skipped"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/broadcasts/66d4a1b2c3e4f5a6b7c8d9e2/recipients" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"phones": ["+13105551234", "+442071234567"]}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "added": 2,
  "skipped": 0
}
```

`skipped` counts duplicates and contacts with no WhatsApp channel. To add existing contacts instead, send `{ "contactIds": ["66e5b2c3d4f5a6b7c8d9e0f1"] }` with Zernio contact ids, not phone numbers; to fill from a segment, send `{ "useSegment": true }`.

## Step 3: Send now or schedule

Call `POST /v1/broadcasts/{broadcastId}/send` to start delivery at once.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: sent } = await zernio.broadcasts.sendBroadcast({
  path: { broadcastId }
});

console.log(sent.status, sent.sent, sent.failed);
```
</Tab>
<Tab value="Python">
```python
sent = client.broadcasts.send_broadcast(broadcast_id=broadcast_id)

print(sent["status"], sent["sent"], sent["failed"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/broadcasts/66d4a1b2c3e4f5a6b7c8d9e2/send" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), after the first batch:

```json
{
  "success": true,
  "status": "sending",
  "sent": 2,
  "failed": 0,
  "recipientCount": 2
}
```

To send later, call `POST /v1/broadcasts/{broadcastId}/schedule` with the scheduled time instead:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: scheduled } = await zernio.broadcasts.scheduleBroadcast({
  path: { broadcastId },
  body: { scheduledAt: '2027-01-01T12:00:00Z' }
});

console.log(scheduled.broadcast.status);
```
</Tab>
<Tab value="Python">
```python
scheduled = client.broadcasts.schedule_broadcast(
    broadcast_id=broadcast_id,
    scheduled_at="2027-01-01T12:00:00Z",
)

print(scheduled["broadcast"]["status"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/broadcasts/66d4a1b2c3e4f5a6b7c8d9e2/schedule" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"scheduledAt": "2027-01-01T12:00:00Z"}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "broadcast": {
    "id": "66d4a1b2c3e4f5a6b7c8d9e2",
    "status": "scheduled",
    "scheduledAt": "2027-01-01T12:00:00Z"
  }
}
```

Delivery is tracked per recipient as sent, delivered and read. Read it with [List broadcast recipients](/broadcasts/list-broadcast-recipients), or receive it as it happens through the `message.delivered` and `message.read` events ([WhatsApp inbox webhooks](/platforms/whatsapp/inbox#webhooks)).

## If it fails

A `400` from the send or schedule call means the broadcast has no recipients:

```json
{
  "error": "Broadcast has no recipients"
}
```

Add them with Step 2. The other `400` on those calls reads `Cannot send broadcast in sending status` and names the status it refused; check `status` with [Get broadcast](/broadcasts/get-broadcast) before retrying. A per-recipient failure surfaces in `failed` and on the recipient list, not as an error on the call; the common cause is a template whose placeholder count does not match `variableMapping` (Meta code 132000). Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Templates](/platforms/whatsapp/templates): create the template and watch for Meta's approval.
- [Contacts](/platforms/whatsapp/contacts): import contacts with tags so `useSegment` can target them.
- [Broadcasts API](/broadcasts/create-broadcast): every field, plus cancel and list.
- [Messages pricing](/pricing/messages): how broadcast recipients meter.
- [WhatsApp inbox](/platforms/whatsapp/inbox): send a template into a single conversation instead.

---
