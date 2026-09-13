# Telegram

Publish text, photos, videos, documents and albums to Telegram channels and groups with the Zernio API, edit published messages, and run DMs and bot commands from the inbox.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Publish text, photos, videos, documents and albums to a Telegram channel or group with `POST /v1/posts` and `platform: "telegram"`. The same bot serves DMs and bot commands in the inbox.

## Quick reference

| Property | Value |
|----------|-------|
| Text limit | 4,096 characters (text messages) |
| Caption limit | 1,024 characters (media captions) |
| Images per album | 10 |
| Videos per album | 10 |
| Mixed media | Yes (images and videos in the same album) |
| Image formats | JPEG, PNG, GIF, WebP |
| Image max size | 10 MB (auto-compressed) |
| Video formats | MP4, MOV |
| Video max size | 50 MB (auto-compressed) |
| Scheduling | Yes |
| Editing published posts | Yes (text or caption, media cannot be changed) |
| Inbox (DMs) | Yes |
| Inbox (comments) | No |
| Analytics | No (Telegram limitation) |

## Before you start

Telegram requires Zernio's bot, `@ZernioScheduleBot`, to be an administrator of your channel or group with permission to post. There is no OAuth and there are no scopes: what the bot can do comes from the admin permissions you grant it. In a channel, posts appear as the channel's name and logo. In a group, posts appear as sent by "ZernioScheduleBot", and that cannot be changed.

<Callout type="warn">
Add `@ZernioScheduleBot` as an administrator with the Post Messages permission before you connect. A bot that is a plain member, or an admin without post permission, is the most common setup failure: the connect call returns `400` and every post fails with "Bot is not a member of the channel".
</Callout>

## Connect

Call `GET /v1/connect/telegram` with `profileId` to [generate an access code](/connect/get-telegram-connect-status), valid for 15 minutes. The user adds the bot to the channel or group and sends it the code; you poll until the connection completes. The [connecting accounts guide](/guides/connecting-accounts#telegram) covers where this fits next to the OAuth platforms.

### Access code flow

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: access } = await zernio.connect.telegram.getTelegramConnectStatus({
  query: { profileId: '66a1f0c2a4b9d3e8f1a2b3c4' }
});

console.log(access.code, access.botUsername);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

access = client.connect.get_telegram_connect_status(
    profile_id="66a1f0c2a4b9d3e8f1a2b3c4"
)

print(access["code"], access["botUsername"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/connect/telegram?profileId=66a1f0c2a4b9d3e8f1a2b3c4" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "code": "ZRN-ABC123",
  "expiresAt": "2027-01-01T12:15:00.000Z",
  "expiresIn": 900,
  "botUsername": "ZernioScheduleBot",
  "instructions": [
    "1. Add @ZernioScheduleBot as an administrator in your channel/group",
    "2. Open a private chat with @ZernioScheduleBot",
    "3. Send: ZRN-ABC123 @yourchannel (replace @yourchannel with your channel username)",
    "4. Wait for confirmation - the connection will appear in your dashboard",
    "Tip: If your channel has no public username, forward a message from it along with the code"
  ]
}
```

The user then does 3 things in Telegram:

1. Adds `@ZernioScheduleBot` to the channel as an administrator with the Post Messages permission, or to the group as an administrator.
2. Opens a private chat with [@ZernioScheduleBot](https://t.me/ZernioScheduleBot).
3. Sends the code with the channel username: `ZRN-ABC123 @yourchannel`. For a private channel without a username, they forward any message from the channel to the bot together with the code.

Poll `PATCH /v1/connect/telegram` with the code until `status` is `connected`:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: status } = await zernio.connect.telegram.completeTelegramConnect({
  query: { code: access.code }
});

console.log(status.status);
```
</Tab>
<Tab value="Python">
```python
status = client.connect.complete_telegram_connect(code=access["code"])

print(status["status"])
```
</Tab>
<Tab value="curl">
```bash
curl -X PATCH "https://zernio.com/api/v1/connect/telegram?code=ZRN-ABC123" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`) while the user has not sent the code yet:

```json
{
  "status": "pending",
  "expiresAt": "2027-01-01T12:15:00.000Z",
  "expiresIn": 542
}
```

Response (`200`) once the bot has received it:

```json
{
  "status": "connected",
  "chatId": "-1001234567890",
  "chatTitle": "My Channel",
  "chatType": "channel",
  "account": {
    "_id": "66b2e19d8c3f5a7e9d0b1c2d",
    "platform": "telegram",
    "username": "mychannel",
    "displayName": "My Channel"
  }
}
```

`account._id` is the `accountId` for every call below. A code that is not used within 15 minutes returns `status: "expired"`; generate a new one.

### Direct connection

If the bot is already an administrator and you know the chat id, skip the code and [connect directly](/connect/initiate-telegram-connect) with `POST /v1/connect/telegram`:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: connected } = await zernio.connect.telegram.initiateTelegramConnect({
  body: {
    profileId: '66a1f0c2a4b9d3e8f1a2b3c4',
    chatId: '-1001234567890'
  }
});

console.log(connected.account._id);
```
</Tab>
<Tab value="Python">
```python
connected = client.connect.initiate_telegram_connect(
    profile_id="66a1f0c2a4b9d3e8f1a2b3c4",
    chat_id="-1001234567890"
)

print(connected["account"]["_id"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/connect/telegram \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4",
    "chatId": "-1001234567890"
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "message": "Telegram channel connected successfully",
  "account": {
    "_id": "66b2e19d8c3f5a7e9d0b1c2d",
    "platform": "telegram",
    "username": "mychannel",
    "displayName": "My Channel",
    "isActive": true,
    "chatType": "channel"
  }
}
```

`chatId` is the username with an `@` prefix for a public channel (`@mychannel`) or the numeric id for a private channel or a group. To find a numeric id, forward a message from the channel to [@userinfobot](https://t.me/userinfobot), which replies with the id (private channel ids start with `-100`), or add that bot to the group for a moment and remove it once it has shown the group's negative id.

## Publish

A plain post becomes a text message. One media item becomes a photo, video or document message with `content` as its caption, and 2 to 10 items become an album with the caption on the first message. Text is parsed as HTML unless `parseMode` says otherwise.

### Text message

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: published } = await zernio.posts.createPost({
  body: {
    content: '<b>Release 2.3 is out.</b>\n\nRead the <a href="https://example.com/changelog">changelog</a>.',
    platforms: [
      {
        platform: 'telegram',
        accountId: '66b2e19d8c3f5a7e9d0b1c2d',
        platformSpecificData: { parseMode: 'HTML' }
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
    content='<b>Release 2.3 is out.</b>\n\nRead the <a href="https://example.com/changelog">changelog</a>.',
    platforms=[
        {
            "platform": "telegram",
            "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
            "platformSpecificData": {"parseMode": "HTML"}
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
    "content": "<b>Release 2.3 is out.</b>\n\nRead the <a href=\"https://example.com/changelog\">changelog</a>.",
    "platforms": [
      {
        "platform": "telegram",
        "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
        "platformSpecificData": {"parseMode": "HTML"}
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
        "platform": "telegram",
        "status": "published",
        "platformPostUrl": "https://t.me/mychannel/42"
      }
    ]
  }
}
```

Every sample below changes only the `mediaItems` or the `platforms` entry of this request.

### Photo message

A single image with `content` as the caption (1,024 characters):

```json
"mediaItems": [
  { "type": "image", "url": "https://cdn.example.com/launch.jpg" }
]
```

### Video message

A single video with `content` as the caption:

```json
"mediaItems": [
  { "type": "video", "url": "https://cdn.example.com/demo.mp4" }
]
```

### Document message

Any file type, sent as a document:

```json
"mediaItems": [
  { "type": "document", "url": "https://cdn.example.com/report.pdf" }
]
```

### Album

Up to 10 items, images and videos mixed, sent as one album:

```json
"mediaItems": [
  { "type": "image", "url": "https://cdn.example.com/photo1.jpg" },
  { "type": "image", "url": "https://cdn.example.com/photo2.jpg" },
  { "type": "video", "url": "https://cdn.example.com/clip.mp4" },
  { "type": "image", "url": "https://cdn.example.com/photo3.jpg" }
]
```

### Formatting

`parseMode` selects how Telegram reads `content`. HTML is the default:

```html
<b>bold</b>
<i>italic</i>
<u>underline</u>
<s>strikethrough</s>
<code>inline code</code>
<pre>code block</pre>
<a href="https://example.com">link</a>
```

`"Markdown"` supports `*bold*`, `_italic_`, `[link](https://example.com)` and backtick-wrapped inline code. `"MarkdownV2"` adds `__underline__`, `~strikethrough~` and `||spoiler||`, and needs a backslash before every one of these characters:

```text
_ * [ ] ( ) ~ ` > # + - = | { } . !
```

An unescaped character fails the post with "Can't parse entities".

### Silent and protected messages

`disableNotification` delivers the message without a notification sound, `protectContent` stops recipients from forwarding or saving it, and `disableWebPagePreview` suppresses the link preview:

```json
{
  "platform": "telegram",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "parseMode": "HTML",
    "disableWebPagePreview": true,
    "disableNotification": true,
    "protectContent": true
  }
}
```

### Channels and groups

| Destination | Shown as the author |
|-------------|---------------------|
| Channel | The channel's name and logo |
| Group | ZernioScheduleBot |

### Edit a published message

[Edit post](/posts/edit-post) replaces the text of a published Telegram message. Messages stay editable with no time window and no limit on the number of edits, and the message id does not change. What `content` replaces depends on the original post:

| Original post | What `content` replaces | Limit |
|---------------|-------------------------|-------|
| Text only | The message text | 4,096 characters |
| Single photo, video or document | The caption | 1,024 characters |
| Album | The caption on the album (its first message) | 1,024 characters |

The media itself cannot be swapped, added or removed, and an edit with unchanged content is rejected with `400`. Check which limit applies before sending: a 2,000-character edit fits a text message and is too long for a photo caption.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: edited } = await zernio.posts.editPost({
  path: { postId: published.post._id },
  body: {
    platform: 'telegram',
    content: '<b>Updated.</b> The webinar starts at 18:00 CET.'
  }
});

console.log(edited.id, edited.url);
```
</Tab>
<Tab value="Python">
```python
edited = client.posts.edit_post(
    post_id=published["post"]["_id"],
    platform="telegram",
    content="<b>Updated.</b> The webinar starts at 18:00 CET."
)

print(edited["id"], edited["url"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts/65f1c0a9e2b5af0012ab34cd/edit \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "platform": "telegram",
    "content": "<b>Updated.</b> The webinar starts at 18:00 CET."
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "id": "42",
  "url": "https://t.me/mychannel/42"
}
```

If the post went to several Telegram channels or groups, pass `accountId` to pick which copy to edit. Without it, the first `telegram` entry on the post is edited.

## Platform fields

All fields go in `platformSpecificData` on the Telegram entry.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `parseMode` | `"HTML"`, `"Markdown"`, `"MarkdownV2"` | `"HTML"` | How Telegram parses `content`. See [Formatting](#formatting). |
| `disableWebPagePreview` | boolean | `false` | No link preview for URLs in the message. |
| `disableNotification` | boolean | `false` | Deliver silently: recipients get the message without a notification sound. |
| `protectContent` | boolean | `false` | Recipients cannot forward or save the message. |

## Media requirements

Files above these limits are compressed automatically: images above 10 MB and videos above 50 MB, which are Telegram's own Bot API caps. Compression has a ceiling of its own: a video over 200 MB is passed through untouched, and Telegram then refuses it.

### Images

| Property | Requirement |
|----------|-------------|
| Max per album | 10 |
| Formats | JPEG, PNG, GIF, WebP |
| Max file size | 10 MB (auto-compressed) |
| Max resolution | Width plus height must total 10,000 px or less; a larger image is downscaled |

### Videos

| Property | Requirement |
|----------|-------------|
| Max per album | 10 |
| Formats | MP4, MOV |
| Max file size | 50 MB (auto-compressed) |
| Max duration | No limit |
| Codec | H.264 recommended |

Media URLs must be direct HTTPS URLs that are publicly accessible with no redirects; Telegram rejects HTTP URLs and redirecting links with "Wrong file identifier/HTTP URL specified". Upload files through the [media endpoint](/guides/media-uploads) to get a URL that qualifies.

## Analytics

Telegram's Bot API exposes no analytics, so there are none for Telegram accounts. View counts for channel posts are visible in the Telegram app, and channels with 500 or more subscribers get Telegram's own channel statistics there.

## Inbox

Telegram supports DMs with attachments, inline and reply keyboards, and a bot command menu. There are no comments.

### Direct messages

| Feature | Supported |
|---------|-----------|
| List conversations | <Yes /> |
| Fetch messages | <Yes /> |
| Send text messages | <Yes /> |
| Send attachments | <Yes /> (images, videos, documents) |
| Edit messages | <Yes /> (text and inline keyboard) |
| Inline keyboards | <Yes /> (buttons with callback data or URLs) |
| Reply keyboards | <Yes /> (one-time custom keyboards) |
| Reply to a message | <Yes /> (`replyTo` with the message id) |
| Archive and unarchive | <Yes /> |

Attachments:

| Type | Max size |
|------|----------|
| Images | 10 MB |
| Videos | 50 MB |
| Documents | 50 MB |

When a user taps an inline keyboard button, the callback data arrives on the incoming message in `metadata.callbackData`. The [Messages API](/messages/list-inbox-conversations) has every endpoint.

### Bot commands

Bot commands are the entries in the "/" menu users see when they chat with the bot. Manage them with `GET`, `PUT` and `DELETE /v1/accounts/{accountId}/telegram-commands` ([Account settings](/account-settings/get-telegram-commands)): up to 100 commands, each a lowercase name of at most 32 characters (`a-z`, `0-9`, `_`, no leading slash) with a description of at most 256 characters.

### Webhooks

| Event | When it fires |
|-------|---------------|
| `message.received` | New incoming message to the bot |
| `message.sent` | Outgoing message is sent |
| `message.edited` | The user edits a sent message; also fires for `edited_channel_post` in channels where the bot is an administrator |
| `reaction.received` | A participant adds or removes an emoji reaction. The bot must be an administrator of the chat; reactions in private chats are not delivered |

Messages are stored locally as webhooks arrive; the [webhooks page](/webhooks) has the payloads. Telegram's Bot API exposes no deletion or read-receipt events for regular bot chats. Delivery and read tracking exist only in the separate Telegram Business integration, which Zernio does not use.

## What you cannot do

Telegram's Bot API does not expose:

- Polls or quizzes
- Telegram's own scheduled messages (schedule with `scheduledFor` instead)
- Replacing the media on a published post (only the text or caption can be edited)
- Managing channel administrators
- Message analytics
- Pinning messages
- Channel invite links

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| "Bot is not a member of the channel" | `@ZernioScheduleBot` is not in the channel or group, or is not an administrator | Add the bot as an administrator and grant the Post Messages permission. |
| "Message is too long" | Text over 4,096 characters, or a caption over 1,024 | Shorten the content or split it into several messages. |
| "Wrong file identifier/HTTP URL specified" | The media URL is not reachable, uses HTTP, or redirects | Use a direct, public HTTPS URL with no redirects. |
| "Can't parse entities" | Broken HTML or Markdown, or an unescaped MarkdownV2 character | Close every tag in HTML mode; escape the reserved characters in MarkdownV2. |
| Media not displayed | An unsupported format, or a video over 200 MB, which is too large for Zernio to compress under Telegram's 50 MB cap | Check the format; compress a video over 200 MB before you send it. |
| "Access code expired" | The code was not used within 15 minutes | Generate a new code with `GET /v1/connect/telegram`. |

A `publishNow: true` post that Telegram rejects returns `207` with `post.status: "failed"` and the message in `platforms[].errorMessage`:

```json
{
  "message": "Post created but publishing failed",
  "error": "All platforms failed",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "failed",
    "platforms": [
      {
        "platform": "telegram",
        "status": "failed",
        "errorMessage": "Bot is not a member of the channel"
      }
    ]
  }
}
```

`207` is a 2xx status, so `fetch(...).ok` is `true`; branch on the status code and on `post.status`. Make the bot an administrator with post permission, then retry. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts#telegram): the access code flow next to the OAuth platforms.
- [Create post](/posts/create-post): every field of the request.
- [Edit post](/posts/edit-post): edit the text or caption of a published message.
- [Media uploads](/guides/media-uploads): upload images and videos instead of hosting them.
- [Messages](/messages/list-inbox-conversations) and [Account settings](/account-settings/get-telegram-commands): the inbox API and bot commands.
- [Pricing](/pricing): what the inbox and outbound messages cost.

---
