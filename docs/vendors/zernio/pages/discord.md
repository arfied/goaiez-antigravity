# Discord

Publish messages, embeds, polls, forum posts and threads to Discord channels with the Zernio API, send DMs, manage roles and pins, and create scheduled events, with no bot hosting.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Publish messages, embeds, polls, forum posts and threads to a Discord channel with `POST /v1/posts` and `platform: "discord"`. The same bot sends DMs, lists members, assigns roles, pins messages and creates scheduled events, all over REST.

## Quick reference

| Property | Value |
|----------|-------|
| Content limit | 2,000 characters (message content) |
| Embeds per message | 10 (max 6,000 characters total) |
| Images per message | Up to 10 attachments |
| Image formats | JPEG, PNG, GIF, WebP |
| Image max size | 25 MB |
| Video formats | MP4 |
| Video max size | 25 MB |
| Post types | Messages, Embeds, Polls, Forum Posts, Threads |
| Scheduling | Yes |
| Direct messages | Outbound only |
| Role management | Yes (list, create, edit, delete, assign, remove) |
| Member listing | Yes (list with cursor pagination, search, get one) |
| Pinned messages | Yes (list, pin, unpin) |
| Scheduled events | Yes (create, list, update, cancel, delete) |
| Inbox (DM replies) | No (Discord requires a Gateway connection) |
| Inbox (comments) | No |
| Analytics | No (Discord Bot API limit) |

## Before you start

Discord requires Zernio's bot in your server. You do not create a Discord application, request privileged intents or host a gateway connection: Zernio runs one central bot, the user installs it into their server through OAuth, and you POST to the REST API. `MESSAGE_CONTENT` and the other privileged intents are handled on Zernio's bot, and you never open a WebSocket.

The bot asks for these permissions at install time: Send Messages, Embed Links, Attach Files, Send Messages in Threads, Create Public Threads, Manage Messages (pin and unpin), Manage Roles (role assignment) and Manage Events (scheduled events).

<Callout type="warn">
Servers connected before Manage Roles and Manage Events were added to the install do not have them, so the role and event endpoints return `502` there. Ask a server admin to re-invite the bot (the new permissions come with the invite) or to grant the missing permission on the bot's role under Server Settings, Roles.
</Callout>

## Connect

Call `GET /v1/connect/discord` with `profileId` on [Get OAuth connect URL](/connect/get-connect-url). The user authorizes the bot to join their server, and you then bind a channel with `POST /v1/connect/discord` ([Connect Discord channel](/connect/connect-discord-channel)). The [connecting accounts guide](/guides/connecting-accounts) covers the OAuth flow and [scopes](/guides/connecting-accounts#scopes) in general.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: connect } = await zernio.connect.getConnectUrl({
  path: { platform: 'discord' },
  query: { profileId: '66a1f0c2a4b9d3e8f1a2b3c4' }
});
// Send the user's browser to connect.authUrl
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

connect = client.connect.get_connect_url(
    platform="discord",
    profile_id="66a1f0c2a4b9d3e8f1a2b3c4"
)
