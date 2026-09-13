# Chat SDK

Build one chatbot that answers Instagram, Facebook, Telegram, WhatsApp, X, Bluesky and Reddit conversations through the Zernio adapter for Vercel's Chat SDK.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';
import { Step, Steps } from 'fumadocs-ui/components/steps';

When you finish this page a [Chat SDK](https://chat-sdk.dev) bot answers messages from Instagram, Facebook, Telegram, WhatsApp, X (platform value `twitter`), Bluesky and Reddit through one adapter and one webhook endpoint. You need an [API key](https://zernio.com/dashboard/api-keys) with read-write permission, a [connected account](/guides/connecting-accounts) and Node.js 20+. Zernio holds the platform app registrations and tokens, so the bot never talks to Meta, X, Reddit or Telegram directly.

`@zernio/chat-sdk-adapter` is the official Zernio adapter, [listed on chat-sdk.dev](https://chat-sdk.dev/adapters/zernio). Every account includes the inbox; messages the bot sends are metered after the first 10,000 each month ([pricing](/pricing#outbound-messages)). On a legacy AppSumo plan the inbox stays off until support enables it.

## Step 1: Install and configure

<Steps>

<Step>
### Install the adapter

```bash
npm install @zernio/chat-sdk-adapter chat @chat-adapter/state-memory
```

`@chat-adapter/state-memory` keeps state in memory. In production use a persistent state adapter such as `@chat-adapter/state-redis` or `@chat-adapter/state-pg` ([state adapters](https://chat-sdk.dev/docs/state)).
</Step>

<Step>
### Set the environment variables

```bash
ZERNIO_API_KEY=$ZERNIO_API_KEY
ZERNIO_WEBHOOK_SECRET=$ZERNIO_WEBHOOK_SECRET
```

`ZERNIO_WEBHOOK_SECRET` is the secret you set on the webhook endpoint in Step 3; the adapter verifies every delivery's signature with it.
</Step>

</Steps>

## Step 2: Create the bot and its route

<Steps>

<Step>
### Create the bot

```typescript title="lib/bot.ts"
import { Chat } from "chat";
import { createZernioAdapter } from "@zernio/chat-sdk-adapter";
import { createMemoryState } from "@chat-adapter/state-memory";

export const bot = new Chat({
  userName: "pizza-bot",
  adapters: {
    zernio: createZernioAdapter(),
  },
  state: createMemoryState(),
});

// /.*/ matches every message
bot.onNewMessage(/.*/, async (thread, message) => {
  const platform = (message.raw as any).platform;
  await thread.post(`Hello from ${platform}`);
});
```
</Step>

<Step>
### Add the route that receives events

<Tabs items={['Next.js App Router', 'Express']}>
<Tab value="Next.js App Router">
```typescript title="app/api/chat-webhook/route.ts"
import { bot } from "@/lib/bot";

export async function POST(request: Request) {
  return bot.webhooks.zernio(request);
}
```
</Tab>
<Tab value="Express">
```typescript title="server.ts"
import express from "express";
import { bot } from "./lib/bot";

const app = express();

app.post("/api/chat-webhook", async (req, res) => {
  const response = await bot.webhooks.zernio(req);
  res.status(response.status).send(await response.text());
});
```
</Tab>
</Tabs>
</Step>

</Steps>

## Step 3: Subscribe Zernio to your route

Create the endpoint in the [webhooks dashboard](https://zernio.com/dashboard/webhooks), or call `POST /v1/webhooks/settings` with `name`, `url`, `events` and the same `secret` you put in `ZERNIO_WEBHOOK_SECRET`. Subscribe to `message.received` and `comment.received`; add `reaction.received` if the bot handles reactions, which route to `bot.onReaction`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.webhooks.createWebhookSettings({
  body: {
    name: 'Chat SDK bot',
    url: 'https://your-app.com/api/chat-webhook',
    events: ['message.received', 'comment.received', 'reaction.received'],
    secret: process.env.ZERNIO_WEBHOOK_SECRET
  }
});

console.log(created.webhook._id);
```
</Tab>
<Tab value="Python">
```python
import os
from zernio import Zernio

client = Zernio()

created = client.webhooks.create_webhook_settings(
    name="Chat SDK bot",
    url="https://your-app.com/api/chat-webhook",
    events=["message.received", "comment.received", "reaction.received"],
    secret=os.environ["ZERNIO_WEBHOOK_SECRET"],
)

print(created["webhook"]["_id"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/webhooks/settings \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Chat SDK bot",
    "url": "https://your-app.com/api/chat-webhook",
    "events": ["message.received", "comment.received", "reaction.received"],
    "secret": "'"$ZERNIO_WEBHOOK_SECRET"'"
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "webhook": {
    "_id": "66d9f1a2b3c4d5e6f7a8b9c0",
    "name": "Chat SDK bot",
    "url": "https://your-app.com/api/chat-webhook",
    "events": ["message.received", "comment.received", "reaction.received"],
    "isActive": true,
    "failureCount": 0
  }
}
```

Send the connected account a DM and the bot replies. The adapter reads its settings from the environment; to pass them explicitly:

```typescript
const adapter = createZernioAdapter({
  apiKey: process.env.ZERNIO_API_KEY,
  webhookSecret: process.env.ZERNIO_WEBHOOK_SECRET,
  baseUrl: "https://zernio.com/api",  // default
  botName: "My Bot",                   // default: "Zernio Bot"
});
```

| Environment variable | Config key | Required | Description |
|---|---|---|---|
| `ZERNIO_API_KEY` | `apiKey` | Yes | API key used to send messages |
| `ZERNIO_WEBHOOK_SECRET` | `webhookSecret` | Recommended | HMAC-SHA256 secret for webhook verification |
| `ZERNIO_API_BASE_URL` | `baseUrl` | No | Override the API base URL |
| `ZERNIO_BOT_NAME` | `botName` | No | Bot display name |

## Step 4: Read platform data and reactions

Every message carries the raw Zernio payload in `message.raw`:

```typescript
bot.onNewMessage(/.*/, async (thread, message) => {
  const raw = message.raw as any;

  console.log(raw.platform); // "instagram" | "facebook" | "telegram" | ...

  if (raw.sender.instagramProfile) {
    console.log(raw.sender.instagramProfile.followerCount);
    console.log(raw.sender.instagramProfile.isVerified);
  }

  if (raw.sender.phoneNumber) {
    console.log(raw.sender.phoneNumber); // WhatsApp
  }

  for (const att of raw.attachments) {
    console.log(att.type, att.url);
  }
});
```

Reactions (WhatsApp, Telegram, Slack, Instagram, Facebook Messenger) arrive as their own event and route to `onReaction`, never to `onNewMessage`, so a 👍 is never handled as an inbound message:

```typescript
bot.onReaction(async (event) => {
  // event.emoji     the normalized emoji (event.rawEmoji is the platform value)
  // event.added     true when added, false when removed
  // event.messageId the message that was reacted to
  // event.thread    the thread where it happened
  if (event.added) {
    await event.thread.post(`Thanks for the ${event.emoji}`);
  }
});
```

## What the adapter supports

| Feature | Supported | Notes |
|---|---|---|
| Send messages | Yes | Text on every platform |
| Rich messages (cards) | Yes | Buttons and templates on Facebook, Instagram, Telegram, WhatsApp |
| Edit messages | Partial | Telegram only |
| Delete messages | Partial | Telegram, X (full); Bluesky, Reddit (own messages only) |
| Send reactions | Partial | WhatsApp, Telegram, Slack, Instagram, Facebook Messenger |
| Receive reactions (`onReaction`) | Partial | WhatsApp, Telegram, Slack, Instagram, Facebook Messenger, through the `reaction.received` event |
| Typing indicators | Partial | Facebook Messenger, Instagram, Telegram, WhatsApp |
| AI streaming | Partial | Live post and edit on Telegram; one full reply on platforms without message editing (WhatsApp, Instagram, Facebook, X, Bluesky, Reddit) |
| File attachments | Yes | Through the media upload endpoint |
| Fetch messages | Yes | Full conversation history, with `limit`, `cursor` and `direction` |
| Fetch thread info | Yes | Participant details, platform, status |
| Webhook verification | Yes | HMAC-SHA256 |
| Comment webhooks | Yes | `comment.received` routed through handlers |
| Reaction webhooks | Yes | `reaction.received` routed to `onReaction` |

For calls the Chat SDK does not cover, the package exports a client for the Zernio API:

```typescript
import { ZernioApiClient } from "@zernio/chat-sdk-adapter";

const client = new ZernioApiClient(process.env.ZERNIO_API_KEY, "https://zernio.com/api");

const { data, pagination } = await client.listConversations({
  platform: "instagram",
  status: "active",
  limit: 20,
});

await client.sendMessage("66c3d2ae7b4f6c8d0e1f2a3b", {
  accountId: "66b2e19d8c3f5a7e9d0b1c2d",
  message: "Here is the menu",
  attachmentUrl: "https://cdn.example.com/menu.jpg",
  attachmentType: "image",
});
```

## If it fails

A `401` with the body `Invalid signature` from the webhook route means the signature check failed: the `secret` on the webhook endpoint and `ZERNIO_WEBHOOK_SECRET` differ, or the endpoint has no secret. Set the same value in both places and redeliver the event from the [webhooks dashboard](https://zernio.com/dashboard/webhooks).

## Related

- [GitHub repository](https://github.com/zernio-dev/chat-sdk-adapter) and the [npm package](https://www.npmjs.com/package/@zernio/chat-sdk-adapter)
- [Chat SDK documentation](https://chat-sdk.dev)
- [Webhooks](/webhooks)
- [Inbox API](/messages/list-inbox-conversations)

---
