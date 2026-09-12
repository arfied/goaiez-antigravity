# Send the user's browser to connect["authUrl"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/connect/discord?profileId=66a1f0c2a4b9d3e8f1a2b3c4" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "authUrl": "https://discord.com/oauth2/authorize?client_id=...",
  "state": "..."
}
```

After the user authorizes, bind the channel that receives posts:

```bash
curl -X POST https://zernio.com/api/v1/connect/discord \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4",
    "guildId": "1098765432109876543",
    "channelId": "1234567890123456789"
  }'
```

Response (`200`):

```json
{
  "message": "Discord channel connected successfully",
  "account": {
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platform": "discord",
    "displayName": "Acme HQ - #announcements",
    "channelId": "1234567890123456789",
    "guildId": "1098765432109876543",
    "isActive": true
  }
}
```

`account.accountId` is the `accountId` for every call below. One connected account serves one channel: repeat `POST /v1/connect/discord` with another `channelId` to add another. `channelId` in `platformSpecificData` decides which channel receives each message.

### OAuth scopes

Posting runs through the bot rather than a user token, so the consent screen asks for 2 scopes:

| Scope | What it enables |
|-------|-----------------|
| `bot` | Install the Zernio bot into the server; all posting happens through the bot |
| `guilds` | List the servers the user manages, to pick where to post |

## Publish

A plain post becomes a text message in the channel named by `channelId`. Fields in `platformSpecificData` add embeds and polls, target forum channels, open a thread under the message and change the name it is posted under.

### Message

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Release 2.3 is live. Changelog in the thread below.',
    platforms: [
      {
        platform: 'discord',
        accountId: '66b2e19d8c3f5a7e9d0b1c2d',
        platformSpecificData: { channelId: '1234567890123456789' }
      }
    ],
    publishNow: true
  }
});

console.log(published.post.platforms[0].platformPostUrl);
```
</Tab>
<Tab value="Python">
```python
published = client.posts.create_post(
    content="Release 2.3 is live. Changelog in the thread below.",
    platforms=[
        {
            "platform": "discord",
            "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
            "platformSpecificData": {"channelId": "1234567890123456789"}
        }
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
    "content": "Release 2.3 is live. Changelog in the thread below.",
    "platforms": [
      {
        "platform": "discord",
        "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
        "platformSpecificData": {"channelId": "1234567890123456789"}
      }
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
        "platform": "discord",
        "status": "published",
        "platformPostUrl": "https://discord.com/channels/1098765432109876543/1234567890123456789/4455667788990011223"
      }
    ]
  }
}
```

Every sample below changes only the `platforms` entry of this request.

### Embeds

`embeds` sends rich cards alongside or instead of `content`. A message takes up to 10 embeds with 6,000 characters combined across every embed field; `color` is a decimal integer, so convert hex first.

```json
{
  "platform": "discord",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "channelId": "1234567890123456789",
    "embeds": [{
      "title": "v2.3.0 release notes",
      "description": "Dark mode, new API endpoints, faster uploads.",
      "color": 5814783,
      "url": "https://example.com/changelog",
      "footer": { "text": "Shipped today" },
      "fields": [
        { "name": "New features", "value": "3", "inline": true },
        { "name": "Bug fixes", "value": "12", "inline": true }
      ]
    }]
  }
}
```

### Polls

`poll` creates a native Discord poll, the same one the Discord client makes. A poll is a standalone message: Discord does not allow media attachments in the same message.

```json
{
  "platform": "discord",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "channelId": "1234567890123456789",
    "poll": {
      "question": { "text": "Ship it now or wait until Monday?" },
      "answers": [
        { "poll_media": { "text": "Ship now" } },
        { "poll_media": { "text": "Wait for Monday" } }
      ],
      "duration": 24,
      "allow_multiselect": false
    }
  }
}
```

| Poll property | Value |
|---------------|-------|
| Max answers | 10 |
| Duration | 1 to 768 hours (32 days) |
| Default duration | 24 hours |
| Multi-select | Optional (default `false`) |

### Forum posts

A forum channel (type 15) needs `forumThreadName`, which becomes the thread title; `content` is the starter message. `forumAppliedTags` takes up to 5 snowflake ids of the forum's existing tags. There is no discovery endpoint for tags: read the ids from the forum channel's settings in Discord.

```json
{
  "platform": "discord",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "channelId": "1122334455667788990",
    "forumThreadName": "Community call, 15 January",
    "forumAppliedTags": ["7788990011223344556", "8899001122334455667"]
  }
}
```

### Threads

`threadFromMessage` opens a thread under the published message. To open one later, or a standalone thread with no message under it, call `POST /v1/discord/channels/{channelId}/threads` with `name` and an optional `messageId` ([Create thread](/discord/create-discord-thread)). It returns `403` when the bot lacks Create Public Threads. `autoArchiveDuration` is 60, 1440 (1 day), 4320 (3 days) or 10080 (7 days) minutes, and `rateLimitPerUser` sets slow mode in seconds, 0 to 21600.

```json
{
  "platform": "discord",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "channelId": "1234567890123456789",
    "threadFromMessage": {
      "name": "Discussion",
      "autoArchiveDuration": 1440,
      "rateLimitPerUser": 0
    }
  }
}
```

### Announcement crosspost

`crosspost: true` publishes the message to every server that follows an announcement channel (type 5). On a regular text channel it does nothing. To crosspost a message you already published, call `POST /v1/discord/channels/{channelId}/messages/{messageId}/crosspost` ([Crosspost message](/discord/crosspost-discord-message)); it returns `400` when the channel is not an announcement channel.

```json
{
  "platform": "discord",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "channelId": "1011121314151617181",
    "crosspost": true
  }
}
```

### Webhook identity

Messages post as "Zernio" with the bot's avatar unless you change the name and avatar. An account-level default applies to every post from the account; set it with `PATCH /v1/accounts/{accountId}/discord-settings` ([Update Discord settings](/discord/update-discord-settings)):

```bash
curl -X PATCH https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/discord-settings \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "webhookUsername": "My Brand",
    "webhookAvatarUrl": "https://cdn.example.com/logo.png"
  }'
```

Response (`200`):

```json
{
  "message": "Discord settings updated",
  "account": {
    "_id": "66b2e19d8c3f5a7e9d0b1c2d",
    "channelId": "1234567890123456789",
    "webhookUsername": "My Brand",
    "webhookAvatarUrl": "https://cdn.example.com/logo.png"
  }
}
```

A per-post override in `platformSpecificData` wins over the account default for that post only:

```json
{
  "platform": "discord",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "channelId": "1234567890123456789",
    "webhookUsername": "Special announcement",
    "webhookAvatarUrl": "https://cdn.example.com/special-logo.png"
  }
}
```

A webhook username is 1 to 80 characters and cannot contain "clyde" or "discord". An empty string resets to the default ("Zernio").

### Edit and delete

[Edit post](/posts/edit-post) (`POST /v1/posts/{postId}/edit`) replaces the text of a published message with no time limit; the message id does not change. `DELETE /v1/posts/{postId}` deletes a draft or cancels a scheduled post. To delete a message that is already published, call `DELETE /v1/discord/channels/{channelId}/messages/{messageId}` with `accountId`.

## Platform fields

All fields go in `platformSpecificData` on the Discord entry. `channelId` is required.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `channelId` | string | | Snowflake id of the target channel. Required. |
| `embeds` | Array\<object\> | | Up to 10 Discord embed objects, 6,000 characters combined. See [Embeds](#embeds). |
| `poll` | object | | Native poll (question, answers, duration). Cannot be combined with media. See [Polls](#polls). |
| `crosspost` | boolean | `false` | Publish to every follower of an announcement channel. No-op on text channels. |
| `forumThreadName` | string | | Thread title for a forum starter message. Required on forum channels. |
| `forumAppliedTags` | Array\<string\> | | Tag snowflake ids for a forum post, at most 5. |
| `threadFromMessage` | \{name, autoArchiveDuration?, rateLimitPerUser?\} | | Open a thread under the published message. See [Threads](#threads). |
| `tts` | boolean | `false` | Text-to-speech message: Discord reads it aloud in the channel. |
| `webhookUsername` | string | account default | Display name for this post only, 1 to 80 characters. |
| `webhookAvatarUrl` | string | account default | Avatar URL for this post only. |

## Media requirements

A message takes up to 10 attachments of 25 MB each.

| Type | Formats | Max size |
|------|---------|----------|
| Images | JPEG, PNG, GIF, WebP | 25 MB |
| Videos | MP4 | 25 MB |
| Documents | Any file | 25 MB |

Media URLs must be publicly reachable HTTPS URLs; upload files through the [media endpoint](/guides/media-uploads) to get one. A poll cannot carry attachments.

## Analytics

Discord's Bot API exposes no message metrics, so there are no analytics for Discord accounts.

## Inbox

Discord supports outbound DMs from the bot and nothing inbound: replies do not arrive, because reading DMs needs a Gateway connection, and there are no comments. Channels, roles, members, pins and scheduled events are in [Server management](#server-management).

### Direct messages

`POST /v1/discord/dms` ([Send Discord DM](/discord/send-discord-direct-message)) sends a 1:1 message from the bot to a user: onboarding, billing reminders, password resets, support pings. Each DM counts as one [outbound message](/pricing#outbound-messages).

```bash
curl -X POST https://zernio.com/api/v1/discord/dms \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "userId": "2233445566778899001",
    "content": "Welcome to Acme. Reply STOP to opt out."
  }'
```

Response (`200`):

```json
{
  "messageId": "4455667788990011223",
  "channelId": "5566778899001122334",
  "url": "https://discord.com/channels/@me/5566778899001122334/4455667788990011223",
  "timestamp": "2027-01-01T12:00:00.000Z",
  "recipient": { "userId": "2233445566778899001", "platform": "discord" }
}
```

The body has the same shape as a channel post and also accepts `embeds`, `attachments` and `tts`; at least one of `content`, `embeds` or `attachments` is required. The bot can only DM users who share at least one server with it, and `userId` is the recipient's snowflake id, not a username: resolve a username with `GET /v1/discord/guilds/{guildId}/members/search`, which prefix-matches usernames and nicknames. A recipient who has DMs turned off for non-friends makes Discord return `403`, which surfaces as a `502` platform error.

## Server management

The bot administers the server it is installed in: channels, roles, members, pinned messages and scheduled events. Every call takes `accountId` as a query parameter and the server's `guildId` or `channelId` in the path.

### Channels

List the channels in the connected server with `GET /v1/accounts/{accountId}/discord-channels` ([List Discord channels](/discord/get-discord-channels)):

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: channels } = await zernio.discord.getDiscordChannels({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

console.log(channels.channels);
```
</Tab>
<Tab value="Python">
```python
channels = client.discord.get_discord_channels(
    account_id="66b2e19d8c3f5a7e9d0b1c2d"
)

print(channels["channels"])
```
</Tab>
<Tab value="curl">
```bash
curl https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/discord-channels \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "channels": [
    { "id": "1234567890123456789", "name": "announcements", "type": 5 },
    { "id": "1122334455667788990", "name": "community", "type": 15 }
  ]
}
```

To move the account to another channel in the same server, send `channelId` to the same `PATCH /v1/accounts/{accountId}/discord-settings` call as [Webhook identity](#webhook-identity); the response carries the account with its new `channelId` and `channelName`. Only text (0), announcement (5) and forum (15) channels are accepted, and Zernio creates a new webhook in the target channel.

### Roles and members

Nine endpoints cover roles and members: list, create, edit and delete roles; list, search and get members; assign and remove a role per member. The paths mirror Discord's own API.

List every role in a server:

```bash
curl "https://zernio.com/api/v1/discord/guilds/1098765432109876543/roles?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "data": [
    {
      "id": "3344556677889900112",
      "name": "Members",
      "color": 3447003,
      "position": 3,
      "permissions": "1071698660929",
      "managed": false,
      "mentionable": true,
      "hoist": false
    }
  ]
}
```

`color` is a decimal value (`0` means no color), `permissions` a bitfield as a string, and `position` the place in the hierarchy, where higher means more authority. `managed` is `true` for roles an integration owns, which no one can assign.

List members, cursor-paginated, 100 per page by default and at most 1,000:

```bash
curl "https://zernio.com/api/v1/discord/guilds/1098765432109876543/members?accountId=66b2e19d8c3f5a7e9d0b1c2d&limit=100" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "data": [
    {
      "user": { "id": "2233445566778899001", "username": "sam", "global_name": "Sam" },
      "nick": null,
      "roles": ["3344556677889900112"],
      "joined_at": "2026-01-15T10:30:00.000Z"
    }
  ],
  "pagination": { "nextCursor": "2233445566778899001", "hasMore": true }
}
```

Pass `nextCursor` as `after` on the next call until `hasMore` is `false`. This endpoint needs the Server Members Intent on Zernio's Discord app, which is on for new bot installs; an empty array with no error means the intent is missing.

Assign a role to a member:

```bash
curl -X PUT "https://zernio.com/api/v1/discord/guilds/1098765432109876543/members/2233445566778899001/roles/3344556677889900112?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "success": true,
  "operation": "role_assigned",
  "guildId": "1098765432109876543",
  "userId": "2233445566778899001",
  "roleId": "3344556677889900112"
}
```

`DELETE` on the same path removes the role and answers with `operation: "role_removed"`. Both are idempotent, so a role that is already assigned or already absent still returns `200`.

The bot's highest role must sit above the role it assigns: a bot demoted in the server's role hierarchy loses the ability to assign higher roles even with Manage Roles. The `@everyone` role, whose `roleId` equals the `guildId`, cannot be assigned or removed.

Create a role with `POST /v1/discord/guilds/{guildId}/roles` ([Create role](/discord/create-discord-guild-role)). `name` is the only required field; `color` is decimal (`16711680` is red), `permissions` a bitfield as a string, and `hoist` and `mentionable` are booleans:

```bash
curl -X POST "https://zernio.com/api/v1/discord/guilds/1098765432109876543/roles?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name": "Beta testers", "color": 3447003, "mentionable": true}'
```

Response (`201`):

```json
{
  "data": {
    "id": "3344556677889900112",
    "name": "Beta testers",
    "color": 3447003,
    "position": 3,
    "permissions": "1071698660929",
    "managed": false,
    "mentionable": true,
    "hoist": false
  }
}
```

`PATCH /v1/discord/guilds/{guildId}/roles/{roleId}` ([Edit role](/discord/edit-discord-guild-role)) changes any of those fields, at least one per call, and leaves the rest alone. `DELETE` on the same path ([Delete role](/discord/delete-discord-guild-role)) removes the role from the server and from every member, answers `{ "success": true }`, and cannot be undone. All three need Manage Roles on the bot and a target role below the bot's highest role; a server that added the bot before role management shipped must re-invite it, because Discord fixes the permission set at invite time.

Read one member with `GET /v1/discord/guilds/{guildId}/members/{userId}` ([Get member](/discord/get-discord-guild-member)) and prefix-match usernames and nicknames with `GET /v1/discord/guilds/{guildId}/members/search` ([Search members](/discord/search-discord-guild-members)).

### Pinned messages

Pin messages for community operations: the announcement of the week, rules updates, or freshly published posts. List the current pins, most recent first:

```bash
curl "https://zernio.com/api/v1/discord/channels/1234567890123456789/pins?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`), each entry a raw Discord message:

```json
{
  "data": [
    {
      "id": "4455667788990011223",
      "channel_id": "1234567890123456789",
      "content": "Read the rules before posting.",
      "timestamp": "2027-01-01T12:00:00.000Z",
      "attachments": [],
      "embeds": []
    }
  ]
}
```

Pin a message with `PUT` on the same path plus its id:

```bash
curl -X PUT "https://zernio.com/api/v1/discord/channels/1234567890123456789/pins/4455667788990011223?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "success": true,
  "operation": "message_pinned",
  "channelId": "1234567890123456789",
  "messageId": "4455667788990011223"
}
```

`DELETE` on that path unpins and answers with `operation: "message_unpinned"`. Both are idempotent. A channel holds at most 50 pins, and pinning a 51st returns `400`; unpin one first.

### Scheduled events

Scheduled events are separate from messages: they appear in the server's Events panel, and Discord notifies interested members before the start time. They fit AMAs, tournaments, office hours and livestreams. `entity.type` picks one of 3 kinds.

An external event (Zoom, in person, a livestream elsewhere) requires both `location` and `endsAt`:

```bash
curl -X POST https://zernio.com/api/v1/discord/guilds/1098765432109876543/events \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "name": "Weekly AMA",
    "description": "Bring your questions about the roadmap.",
    "startsAt": "2027-01-01T18:00:00Z",
    "entity": {
      "type": "external",
      "location": "https://zoom.us/j/123456789",
      "endsAt": "2027-01-01T19:00:00Z"
    }
  }'
```

Response (`200`):

```json
{
  "data": {
    "id": "5566778899001122334",
    "guild_id": "1098765432109876543",
    "name": "Weekly AMA",
    "scheduled_start_time": "2027-01-01T18:00:00.000Z",
    "scheduled_end_time": "2027-01-01T19:00:00.000Z",
    "status": 1,
    "entity_type": 3,
    "entity_metadata": { "location": "https://zoom.us/j/123456789" }
  }
}
```

A voice channel event takes `channelId` instead, and `endsAt` is optional; a stage channel event has the same shape with `"type": "stage"` and a stage channel id:

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "name": "Game night",
  "startsAt": "2027-01-08T20:00:00Z",
  "entity": { "type": "voice", "channelId": "6677889900112233445" }
}
```

Four more calls read and change an event. Each takes `accountId`, as a query parameter on the reads and the delete and in the body on the `PATCH`. The reads and the `PATCH` return the same `data` object as the create call:

| Call | What it does |
|------|--------------|
| `GET /v1/discord/guilds/{guildId}/events` | Every event in the server. Add `withUserCount=true` for the RSVP count in `user_count`. |
| `GET /v1/discord/guilds/{guildId}/events/{eventId}` | One event. |
| `PATCH /v1/discord/guilds/{guildId}/events/{eventId}` | Update any subset of the fields, for example `{ "name": "Updated title" }`. |
| `DELETE /v1/discord/guilds/{guildId}/events/{eventId}` | Delete it. Answers `{ "success": true, "deleted": "5566778899001122334" }` and keeps no record. |

Discord has no cancel endpoint, so cancelling is the `PATCH` with `status: "cancelled"`, which keeps the event in the server's history as `status: 4`. Delete only when no record should remain.

| Event property | Value |
|----------------|-------|
| Name | Max 100 characters |
| Description | Max 1,000 characters |
| Privacy level | Always `GUILD_ONLY` (Discord deprecated public events) |
| External events | Require `location` (max 100 characters) and `endsAt` |
| Voice and stage events | Require `channelId`; `endsAt` optional |
| Cover image | Optional base64 data URI (PNG, JPEG, GIF) |
| Start time | Must be in the future |

## What you cannot do

Discord's Bot API, as Zernio runs it, does not expose:

- Reading DM replies or any inbound message (needs a Gateway connection)
- Comments
- Analytics
- A poll and media attachments in the same message
- More than 50 pinned messages per channel
- DMs to users who share no server with the bot, or who have DMs turned off for non-friends
- Assigning or removing the `@everyone` role
- Tag discovery for forum channels (read the ids in Discord's channel settings)

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| `502` from a role or event endpoint | The server was connected before Manage Roles and Manage Events were part of the install | Re-invite the bot, or grant the permission on the bot's role in Server Settings. |
| `403` from `POST /v1/discord/dms` | The recipient shares no server with the bot, or has DMs turned off for non-friends | Only DM members of a server the bot is in; the recipient's privacy setting cannot be overridden. |
| `400` from `PUT .../pins/{messageId}` | The channel already has 50 pinned messages | Unpin one first. |
| `400` "no changes, invalid channel type, or bot cannot access channel" from `PATCH .../discord-settings` | `channelId` is not a text, announcement or forum channel, or the bot cannot see it | Pick a channel of type 0, 5 or 15 that the bot has access to. |
| Empty `data` from `GET .../members` with no error | The Server Members Intent is missing on the bot install | Re-invite the bot; new installs have the intent on. |
| Webhook username rejected | The name is empty, over 80 characters, or contains "clyde" or "discord" | Use 1 to 80 characters without those words. |

A `publishNow: true` post that Discord rejects returns `207` with `post.status: "failed"` and Discord's message in `platforms[].errorMessage`:

```json
{
  "message": "Post created but publishing failed",
  "error": "All platforms failed",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "failed",
    "platforms": [
      {
        "platform": "discord",
        "status": "failed",
        "errorMessage": "Missing Access"
      }
    ]
  }
}
```

`207` is a 2xx status, so `fetch(...).ok` is `true`; branch on the status code and on `post.status`. "Missing Access" means the bot cannot see `channelId`: check it with [List Discord channels](/discord/get-discord-channels) and grant the bot access to the channel. [Error handling](/guides/error-handling) covers the envelope, and Discord's own rate limits are handled for you: Zernio backs off on `429` and honours `X-RateLimit-Reset-After`, so you never write retry logic ([rate limits](/guides/rate-limits)).

## Related

- [Connecting accounts](/guides/connecting-accounts): the OAuth flow and bot installation.
- [Create post](/posts/create-post): every field of the request.
- [Discord settings](/discord/update-discord-settings) and [Discord channels](/discord/get-discord-channels): webhook identity and channel switching.
- [Send Discord DM](/discord/send-discord-direct-message): 1:1 messages from the bot.
- [Pricing](/pricing): what outbound DMs cost.

---
