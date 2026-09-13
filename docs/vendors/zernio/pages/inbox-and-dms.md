# Inbox and DMs

Build one inbox per customer, written by webhooks and read from your database, with replies and backfills through the inbox API.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page each customer reads and answers their DMs from Instagram, Facebook, X and the other messaging platforms inside your product. You need the account map from the [core model](/multi-tenant) and one webhook endpoint. Webhooks write your inbox database; API calls are for sending and backfill. Conversations belong to accounts, and accounts live in the customer's profile, so `profileId` scopes an inbox to one customer.

<Mermaid
  chart={`flowchart LR
  subgraph zernio ["Zernio"]
    API["Inbox API"]
    EV["Webhooks"]
  end
  subgraph app ["Your app"]
    BF["Backfill job
once, at onboarding"]
    HOOK["Webhook endpoint"]
    DB[("Your inbox DB")]
    UI["Customer inbox UI"]
  end
  API -->|"conversation history"| BF
  EV -->|"message.received ..."| HOOK
  BF -->|"writes"| DB
  HOOK -->|"writes"| DB
  DB -->|"reads"| UI
  UI -.->|"never: poll for new messages"| API
  linkStyle 5 stroke:#f43f5e,color:#f43f5e,stroke-dasharray:6 4;`}
/>

Polling each of 500 customers' conversations every 30 seconds is 1,000 requests per minute of mostly empty responses, above the 600 per minute [rate limit](/guides/rate-limits), with up to 30 seconds of latency. Webhook-first costs about 0 standing requests and delivers on push.

## Step 1: Write your inbox from webhooks

Subscribe once to the [inbox events](/webhooks/inbox) and handle each one:

| Event | Fires when | What to update |
|---|---|---|
| [`conversation.started`](/webhooks/inbox#conversationstarted) | First message of a new conversation | Create the conversation |
| [`message.received`](/webhooks/inbox#messagereceived) | An incoming DM arrives | Append the message, bump the unread count |
| [`message.sent`](/webhooks/inbox#messagesent) | A message goes out from the connected account | Append or confirm the message |
| [`message.delivered`](/webhooks/inbox#messagedelivered) / [`message.read`](/webhooks/inbox#messageread) | The platform reports receipts | Mark the message row delivered or read |
| [`message.failed`](/webhooks/inbox#messagefailed) | A send is rejected | Show the error inline so the customer can retry |

Inbox payloads carry the account, not the profile. A `message.received` delivery:

```json
{
  "id": "evt_5f8e2a1c",
  "event": "message.received",
  "message": {
    "id": "66d4a1b2c3e4f5a6b7c8d9e1",
    "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b",
    "platform": "instagram",
    "direction": "incoming",
    "text": "Hi, is this still available?",
    "sender": { "id": "17841400000000000", "name": "Jane", "username": "jane_doe" }
  },
  "conversation": { "id": "66c3d2ae7b4f6c8d0e1f2a3b", "status": "active" },
  "account": { "id": "66b2e19d8c3f5a7e9d0b1c2d", "accountId": "66b2e19d8c3f5a7e9d0b1c2d", "profileId": "66a1f0c2a4b9d3e8f1a2b3c4", "platform": "instagram" },
  "timestamp": "2027-01-04T14:00:03Z"
}
```

Look up `account.id` in the account map you stored when the account was [connected](/multi-tenant#step-2-connect-their-accounts). One endpoint serves every customer. Delivery is at least once, so dedupe on the event `id`, and acknowledge within 5 seconds by enqueueing the event and processing it on a worker; a slower handler triggers redeliveries. `X-Zernio-Signature` is the hex HMAC-SHA256 of the raw body keyed by your endpoint's secret, so compare it before you parse; the [webhooks overview](/webhooks) covers the secret and the retry schedule.

<Tabs items={['Node.js', 'Python']}>
<Tab value="Node.js">
```typescript
import crypto from 'crypto';

// Your single webhook endpoint, shared by every customer
export const POST = async (req: Request) => {
  const secret = process.env.ZERNIO_WEBHOOK_SECRET;
  if (!secret) return new Response('No secret provided.', { status: 401 });

  const rawBody = await req.text();
  const computedSignature = crypto.createHmac('sha256', secret).update(rawBody).digest('hex');

  if (req.headers.get('X-Zernio-Signature') !== computedSignature) {
    return new Response('Invalid signature', { status: 400 });
  }

  const event = JSON.parse(rawBody);

  if (await alreadyProcessed(event.id)) return Response.json({ ok: true });

  await queue.enqueue(event);
  return Response.json({ ok: true });
};

// Worker, off the request path
async function handleInboxEvent(event) {
  const customer = await customerByAccountId(event.account.id); // mapping stored at connect time

  switch (event.event) {
    case 'conversation.started':
      return db.createConversation(customer.id, event.conversation);
    case 'message.received':
      return db.appendMessage(customer.id, event.message, { unread: +1 });
    case 'message.sent':
    case 'message.delivered':
    case 'message.read':
      return db.updateMessageStatus(customer.id, event.message);
    case 'message.failed':
      return db.markFailed(customer.id, event.message);
  }
}
```
</Tab>
<Tab value="Python">
```python
import hashlib
import hmac
import json
import os
