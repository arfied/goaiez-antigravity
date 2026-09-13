# Worker, off the request path
def handle_inbox_event(event):
    customer = customer_by_account_id(event["account"]["id"])  # mapping stored at connect time

    match event["event"]:
        case "conversation.started":
            db.create_conversation(customer.id, event["conversation"])
        case "message.received":
            db.append_message(customer.id, event["message"], unread=1)
        case "message.sent" | "message.delivered" | "message.read":
            db.update_message_status(customer.id, event["message"])
        case "message.failed":
            db.mark_failed(customer.id, event["message"])
```
</Tab>
</Tabs>

<Callout type="info">
WhatsApp conversations run through the same inbox API with extra rules (the 24-hour customer service window, template messages). See [WhatsApp inbox](/platforms/whatsapp/inbox).
</Callout>

## Step 2: Send replies

Call `POST /v1/inbox/conversations/{conversationId}/messages` with `accountId` and `message`. Send an `Idempotency-Key` header, one UUID per reply, so a retry after a lost response replays the original `200` instead of sending the message twice; the [idempotency guide](/guides/idempotency) has the full rules. The Python client's `send_inbox_message` takes no idempotency key, so a retry from Python sends a second message. A [typing indicator](/messages/send-typing-indicator) while the customer composes and [mark read](/messages/mark-conversation-read) when they open the conversation make the embedded inbox feel native.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import { randomUUID } from 'crypto';
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: sent } = await zernio.messages.sendInboxMessage({
  path: { conversationId: '66c3d2ae7b4f6c8d0e1f2a3b' },
  headers: { 'Idempotency-Key': randomUUID() },
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    message: 'Yes, still available. Want me to hold it for you?',
  },
});

console.log(sent.data.messageId);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

sent = client.messages.send_inbox_message(
    conversation_id="66c3d2ae7b4f6c8d0e1f2a3b",
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    message="Yes, still available. Want me to hold it for you?",
)

print(sent["data"]["messageId"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/inbox/conversations/66c3d2ae7b4f6c8d0e1f2a3b/messages" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: 4b4986f4-77b5-4c22-a3a7-2c56f7a657e1" \
  -d '{ "accountId": "66b2e19d8c3f5a7e9d0b1c2d", "message": "Yes, still available. Want me to hold it for you?" }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "data": {
    "messageId": "aWdfZAG1faXRlbToxOklHTWVzc2FnZAUlEOjE3ODQx",
    "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b"
  }
}
```

The `200` means accepted. Delivery status arrives through the same webhook stream that writes your inbox:

<Mermaid
  chart={`sequenceDiagram
  participant UI as Customer UI
  participant BE as Your backend
  participant Z as Zernio
  UI->>BE: reply
  BE->>Z: POST /v1/inbox/conversations/{id}/messages
  Z-->>BE: 200, accepted, not yet delivered
  alt delivery succeeds
    Z-->>BE: webhook message.sent
    Z-->>BE: webhook message.delivered / message.read
    BE-->>UI: show delivered / read
  else delivery fails
    Z-->>BE: webhook message.failed (error details)
    BE-->>UI: show the error inline and offer a retry
  end`}
/>

Run a customer's burst of sends (a "reply to all" feature, an automation) through a queue per customer, because the rate limit applies to your key as a whole. On a `429`, wait for the `Retry-After` header before retrying, as the [analytics worker](/multi-tenant/analytics#step-2-run-a-sync-worker) does.

## Step 3: Backfill with the list endpoint

Call `GET /v1/inbox/conversations` with `profileId` when a customer's accounts connect, and page with `cursor`; then [messages](/messages/get-inbox-conversation-messages) per conversation. Run the same sweep on a schedule to catch anything a missed webhook left behind. The endpoint aggregates DMs across the customer's messaging accounts (Facebook, Instagram, X, Bluesky, Reddit, Telegram, WhatsApp, SMS, Slack).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: conversations } = await zernio.messages.listInboxConversations({
  query: {
    profileId: '66a1f0c2a4b9d3e8f1a2b3c4',
    status: 'active',
    limit: 50,
  },
});

console.log(conversations.pagination.nextCursor);
```
</Tab>
<Tab value="Python">
```python
conversations = client.messages.list_inbox_conversations(
    profile_id="66a1f0c2a4b9d3e8f1a2b3c4",
    status="active",
    limit=50,
)

print(conversations["pagination"]["nextCursor"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/inbox/conversations?profileId=66a1f0c2a4b9d3e8f1a2b3c4&status=active&limit=50" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "data": [
    {
      "id": "66c3d2ae7b4f6c8d0e1f2a3b",
      "platform": "instagram",
      "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
      "participantName": "Jane",
      "lastMessage": "Hi, is this still available?",
      "updatedTime": "2027-01-04T14:00:03Z"
    }
  ],
  "pagination": { "hasMore": true, "nextCursor": "eyJ1cGRhdGVkVGltZSI6..." },
  "meta": { "accountsQueried": 3, "accountsFailed": 0, "failedAccounts": [] }
}
```

Check `meta.accountsFailed` on every sweep. Accounts listed in `meta.failedAccounts` have token problems, which is your cue to run the [reconnect loop](/multi-tenant#step-5-monitor-account-health) for that customer.

<Callout type="warn">
Run the onboarding sweep twice for Instagram and Facebook. When one of these accounts connects, Zernio replays the DM history it already holds on Meta into the inbox, in the background, and that replay can finish after your sweep has walked the list. Replayed conversations keep their real `lastMessageAt`, so a 2-year-old conversation sorts 2 years back, on pages you already read, and the replay fires no webhooks. Re-run the sweep an hour later, or let the scheduled sweep pick it up.
</Callout>

To report inbox performance to a customer (volume, response time, heatmap, source breakdown), the [inbox analytics endpoints](/inbox-analytics/get-inbox-volume) take the same `profileId` filter.

## If it fails

A `400` from Step 2 with `code: "PLATFORM_LIMITATION"` means the platform does not offer what you sent, for example an attachment on a Bluesky or Reddit DM:

```json
{
  "error": "Attachments are not supported for bluesky conversations",
  "code": "PLATFORM_LIMITATION"
}
```

Send the message without the attachment.

A `500` means the platform rejected or failed the send, and Zernio never retries a send internally. It is ambiguous: the platform may already have accepted the message, and the `500` releases your `Idempotency-Key`, so a retry with the same key sends for real. Reconcile before you retry. List the conversation's [messages](/messages/get-inbox-conversation-messages) first and treat an empty result as inconclusive rather than as proof nothing was sent. The key covers the other case, where the send succeeded and the response never reached you: that retry replays the original `200` with `Idempotent-Replayed: true`.

## Related

- [Build a platform](/multi-tenant): the profile-per-customer model.
- [Analytics dashboards](/multi-tenant/analytics): the same architecture applied to metrics.
- [Inbox webhooks](/webhooks/inbox): full payload schemas for every message event.
- [WhatsApp inbox](/platforms/whatsapp/inbox): templates and the 24-hour window.

---
