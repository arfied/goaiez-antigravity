# Slack

Publish messages and thread replies to a Slack channel with the Zernio API, with file uploads, a per-message bot identity, and an inbox that receives DMs and mentions in realtime.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Publish messages and thread replies to a Slack channel with `POST /v1/posts` and `platform: "slack"`. The same account receives DMs and bot mentions in the inbox, and can start a DM with any member of its workspace.

## Quick reference

| Property | Value |
|----------|-------|
| Content limit | 40,000 characters (Slack truncates silently beyond this) |
| Edit limit | 4,000 characters (longer posts cannot be edited) |
| Media per post | Up to 10 files |
| Formatting | Slack `mrkdwn` |
| Post types | Channel messages, thread replies |
| Scheduling | Yes (Zernio-side; Slack's native scheduler is not used) |
| Threads | Yes (`threadTs`) |
| Link unfurling | Configurable per post |
| Per-message identity | Yes (display name and avatar override) |
| Inbox (DMs) | Yes |
| Start a conversation | Yes (DM any member of the workspace) |
| Inbox (channel messages) | Mentions and threads only, not all chatter |
| Contacts and built-in automations | DMs only |
| Analytics | No (Slack has no public message analytics API) |

## Before you start

Slack requires the Zernio bot app installed in the workspace. You do not create your own Slack app, host a bot or manage tokens: the user installs Zernio's app through OAuth and you post through the API.

One connected account is one channel. To publish to several channels, connect each one as its own account. Public channels are joined automatically on connect. A private channel needs a member to run `/invite @Zernio` in it first, because the bot cannot join a private channel on its own, and until then it does not appear in the channel list.

Some workspaces require admin approval for new apps, so Slack may show a "request approval" screen, and a workspace can be configured to allow only apps listed in the Slack Marketplace. Zernio's app is distributed but not yet listed, so Slack says it "has not been reviewed by Slack" during install. That is expected.

## Connect

Call `GET /v1/connect/slack` with `profileId` on [List Slack channels](/connect/list-slack-channels). With no `pendingDataToken` or `accountId` it returns the `authUrl` and `state` that start OAuth, like `GET /v1/connect/{platform}` does for other platforms. Slack installs the bot into a workspace, not a channel, so the flow ends with a channel choice:

1. Send the user to `authUrl`; they authorize the workspace on Slack's consent screen.
2. Zernio lists the workspace's channels and the user picks one.
3. `POST /v1/connect/slack` saves the channel as an account.

In headless mode (`headless=true`) the post-OAuth redirect carries a `pendingDataToken` and `connect_token`. List the channels yourself:

```bash
curl "https://zernio.com/api/v1/connect/slack?profileId=66a1f0c2a4b9d3e8f1a2b3c4&pendingDataToken=$PENDING_DATA_TOKEN" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "team": { "id": "T0123456789", "name": "Example Co", "icon": "https://..." },
  "channels": [
    { "id": "C0123456789", "name": "marketing", "isPrivate": false, "isMember": true },
    { "id": "G0123456789", "name": "launch-room", "isPrivate": true, "isMember": false }
  ]
}
```

Channels are read live from Slack, public and private, archived excluded, up to 2,000. `isMember` says whether the bot is already in the channel. Then finalize with [Connect Slack channel](/connect/connect-slack-channel):

```bash
curl -X POST https://zernio.com/api/v1/connect/slack \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4",
    "channelId": "C0123456789",
    "pendingDataToken": "'"$PENDING_DATA_TOKEN"'"
  }'
```

Response (`200`):

```json
{
  "message": "Slack channel connected successfully",
  "account": {
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platform": "slack",
    "username": "marketing",
    "displayName": "marketing",
    "isActive": true,
    "channelId": "C0123456789",
    "teamId": "T0123456789",
    "teamName": "Example Co"
  }
}
```

`account.accountId` is the id every sample below uses. The channel is now an account in that profile, so it also shows up in `GET /v1/accounts?profileId=66a1f0c2a4b9d3e8f1a2b3c4`.

Adding another channel from the same workspace needs no second OAuth: call `GET /v1/connect/slack` with `profileId` and `accountId` of an existing Slack account to list the channels, then `POST` with `accountId` instead of `pendingDataToken`. The [connecting accounts guide](/guides/connecting-accounts) covers the flow and [scopes](/guides/connecting-accounts#scopes) in general.

### OAuth scopes

Publishing and the inbox run through a single bot installation:

| Scope | What it enables |
|-------|-----------------|
| `chat:write` | Post messages as the bot |
| `chat:write.public` | Post to public channels the bot has not joined |
| `chat:write.customize` | Per-message display name and avatar override |
| `channels:join` | Join public channels on connect |
| `files:write` | Upload media with a post |
| `channels:read`, `groups:read` | List channels so you can pick one |
| `team:read` | Read workspace name and icon |
| `channels:history`, `groups:history`, `im:history`, `mpim:history` | Receive inbound messages for the inbox |
| `im:read`, `mpim:read`, `im:write` | Direct message conversations |
| `users:read` | Resolve sender names on inbound messages |
| `files:read` | Inbound message attachments |

<Callout type="warn">
Workspaces connected before the inbox shipped must reconnect once. Existing bot tokens keep the scopes they were granted, so inbound messages fail with `missing_scope` until the workspace re-authorizes from the dashboard.
</Callout>

## Publish

A plain post becomes a message in the channel the account was connected to. Fields in `platformSpecificData` on the Slack entry turn it into a thread reply, control unfurling and override the bot's identity.

### Channel message

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Deploy finished :rocket:',
    platforms: [
      { platform: 'slack', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
    ],
    publishNow: true
  }
});

console.log(published.post.platforms[0].platformPostUrl);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

published = client.posts.create_post(
    content="Deploy finished :rocket:",
    platforms=[
        {"platform": "slack", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
    ],
    publish_now=True
)

print(published["post"]["platforms"][0]["platformPostUrl"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Deploy finished :rocket:",
    "platforms": [
      {"platform": "slack", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
    ],
    "publishNow": true
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "published",
    "platforms": [
      {
        "platform": "slack",
        "status": "published",
        "platformPostUrl": "https://example-co.slack.com/archives/C0123456789/p1785320613863000"
      }
    ]
  }
}
```

Every sample below changes only the `mediaItems` or the `platforms` entry of this request.

<Callout type="warn">
There is no `channelId` field on a post. The channel is whichever one the account was connected to; passing `channelId` in `platformSpecificData` returns a `400`. Connect the channel you want and target its `accountId`.
</Callout>

### Thread reply

`threadTs` posts the message inside an existing thread. Pass the parent message's timestamp:

```json
{
  "platform": "slack",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "threadTs": "1785320613.863000" }
}
```

### Files

Up to 10 files per post, uploaded through Slack's file API with the text as the caption:

```json
"mediaItems": [
  { "type": "image", "url": "https://cdn.example.com/dashboard.png" },
  { "type": "image", "url": "https://cdn.example.com/error-rate.png" }
]
```

### Message identity

`username` and `iconUrl` override the bot's display name and avatar for one message. The override is cosmetic: Slack still shows an APP badge, and the app's identity in the sidebar stays "Zernio". Set a default identity for every post from the channel with [Slack account settings](/accounts/get-slack-settings); the per-post fields still win.

```json
{
  "platform": "slack",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "unfurlLinks": false,
    "username": "Status Bot",
    "iconUrl": "https://example.com/status.png"
  }
}
```

### Edit and delete

[Edit post](/posts/edit-post) replaces the message text up to 4,000 characters; media cannot be swapped. Longer messages cannot be edited, because Slack's edit limit is stricter than its posting limit. There is no time window unless workspace admins restrict message editing. Unpublishing deletes the Slack message; workspace retention or compliance settings can block the deletion, which is an admin setting, not a Zernio error. A media post occasionally cannot be edited or deleted when Slack returned no message reference at publish time.

## Platform fields

All fields go in `platformSpecificData` on the Slack entry.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `threadTs` | string | | Post as a reply inside an existing thread. The parent message's timestamp, for example `"1503435956.000247"`. |
| `unfurlLinks` | boolean | `true` | Expand links in the text into preview cards. |
| `unfurlMedia` | boolean | `true` | Expand media links into inline previews. |
| `username` | string | (account default) | Override the bot's display name for this message. |
| `iconUrl` | string | (account default) | Override the bot's avatar for this message. |

## Media requirements

Up to 10 files per post, uploaded through Slack's file API with the text as the caption. Slack is the one platform Zernio neither compresses nor transcodes for, and it applies no format allowlist here: the bytes behind your URL reach Slack as they are, whatever the type. The per-file ceiling is therefore Slack's own, and the workspace's upload policy and, on Slack's free plan, its storage cap decide whether an upload succeeds; a file Slack refuses fails the post with Slack's message. Zernio names the upload from the media item, `type: "video"` as `.mp4` and `type: "gif"` as `.gif`, and otherwise from `filename`. Host files on a public URL or through the [media endpoint](/guides/media-uploads), which caps a single file at 5 GB.

## Analytics

Slack has no public message analytics API, so `GET /v1/analytics?platform=slack` reports zero for every Slack post. Reactions and thread replies reach you through the [inbox](#inbox) instead.

## Inbox

Slack supports DMs and channel mentions in the [inbox](/messages/list-inbox-conversations), delivered in realtime through Slack's Events API. Zernio ingests:

- DMs to the Zernio bot
- Channel messages that @-mention the bot
- Replies in threads the bot is already part of, including under a post Zernio published
- The bot's own messages
- Emoji reactions added to or removed from a message, as [`reaction.received`](/webhooks/inbox#reactionreceived)

Ordinary channel conversation is deliberately not ingested. The inbox is not a mirror of the workspace, so day-to-day chatter in a connected channel is never stored.

Every incoming message fires the `message.received` event. Its `metadata` carries:

| Field | Meaning |
|-------|---------|
| `threadTs` | Present when the message is a thread reply |
| `mentionsBot` | `true` when the message @-mentions the Zernio bot |

That pair covers the usual agent rule: answer when mentioned in a channel, always answer DMs, and reply inside the thread. Reply from the inbox or with [Send inbox message](/messages/send-inbox-message) on `POST /v1/inbox/conversations/{conversationId}/messages`; the message goes out as the bot, and `replyTo` threads the reply. `POST /v1/inbox/conversations/{conversationId}/messages/{messageId}/reactions` reacts to a message; the emoji has to have a Slack name, such as `:thumbsup:`, and an unnamed character returns a `400`.

Constraints:

- No history backfill. The inbox starts at connect time, a Slack restriction for apps not listed in the Marketplace.
- Messages from other bots appear for context but never trigger automations or webhook events (loop guard).
- Attachments appear as links that open in Slack.
- Contacts and the built-in automations (sequences, workflows) are DM-only. Someone who DMs the bot becomes a contact; people talking in a connected channel do not, otherwise every colleague in the channel would be added as a contact. Automate channels with your own agent reacting to `message.received`.

### Start a conversation

You do not have to wait for someone to message the bot first. Look up a workspace member with [List Slack members](/slack/list-slack-members), then open the DM by passing their member id as `participantId` to [Create inbox conversation](/messages/create-inbox-conversation):

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: members } = await zernio.slack.listSlackMembers({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  query: { query: 'maria' }
});

const { data: conversation } = await zernio.messages.createInboxConversation({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    participantId: members.members[0].id,
    message: 'Hi Maria, the campaign is live.'
  }
});

console.log(conversation.data.conversationId);
```
</Tab>
<Tab value="Python">
```python
members = client.slack.list_slack_members(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    query="maria"
)

conversation = client.messages.create_inbox_conversation(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    participant_id=members["members"][0]["id"],
    message="Hi Maria, the campaign is live."
)

print(conversation["data"]["conversationId"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/slack-members?query=maria" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"

curl -X POST https://zernio.com/api/v1/inbox/conversations \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "participantId": "U01ABCDEF",
    "message": "Hi Maria, the campaign is live."
  }'
```
</Tab>
</Tabs>

Response (`200`) from the member lookup:

```json
{
  "members": [
    { "id": "U01ABCDEF", "name": "Maria Lopez", "username": "maria", "picture": "https://..." }
  ]
}
```

Response (`201`) from the conversation call:

```json
{
  "success": true,
  "data": {
    "messageId": "1785320613.863000",
    "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b",
    "participantId": "U01ABCDEF",
    "participantName": "Maria Lopez"
  }
}
```

Slack addresses people by opaque id, never by name. Bots, deactivated members and Slackbot are left out of the lookup because they cannot hold a DM conversation. The DM comes from the workspace's connected bot, so it reaches members of that workspace only, and the reply lands in the same conversation.

## What you cannot do

Slack's bot API does not expose:

- Messaging people outside the connected workspace
- Posting to several channels from one connected account (connect each channel separately)
- Choosing the channel per post (`channelId` in `platformSpecificData` returns a `400`)
- Engagement metrics
- Message history from before the connection
- Joining a private channel without being invited

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| Bot is not a member of this channel | The bot was removed, or the channel is private | Run `/invite @Zernio` in the channel. |
| Channel was not found or the bot lost access | The channel was deleted or the bot lost visibility | Reconnect, or connect another channel. |
| A workspace setting prevents posting | A Slack admin restricted posting in that channel | Only a Slack admin can lift it. |
| File uploads are disabled or too large | The workspace's upload policy or its free-plan storage cap | Check the workspace's upload policy and storage. |
| Workspace access was revoked | The app was uninstalled from Slack | Reconnect from the dashboard. |
| `missing_scope` on inbound messages | The workspace was connected before the inbox shipped | Reconnect once. |

A `publishNow: true` post that Slack rejects returns `207` with `post.status: "failed"` and the message in `platforms[].errorMessage`:

```json
{
  "message": "Post created but publishing failed",
  "error": "All platforms failed",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "failed",
    "platforms": [
      {
        "platform": "slack",
        "status": "failed",
        "errorMessage": "Bot is not a member of this channel"
      }
    ]
  }
}
```

`207` is a 2xx status, so `fetch(...).ok` is `true`; branch on the status code and on `post.status`. [Error handling](/guides/error-handling) covers the envelope.

Disconnecting one channel account does not revoke the workspace token, so sibling channel accounts keep working. Uninstalling the Zernio app from Slack disconnects every channel account of that workspace in Zernio automatically.

## Related

- [Connecting accounts](/guides/connecting-accounts): the OAuth flow in general.
- [Create post](/posts/create-post) and [Edit post](/posts/edit-post): every field of the request.
- [Slack account settings](/accounts/get-slack-settings): the default message identity for a channel.
- [Messages](/messages/list-inbox-conversations): the inbox API.
- [Account health](/accounts/get-all-accounts-health): verify the connection.
- [Pricing](/pricing): what the inbox and outbound messages cost.

---
