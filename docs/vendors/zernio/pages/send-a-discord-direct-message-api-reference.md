# Send a Discord Direct Message API Reference

Send a 1:1 Direct Message from the bot to a Discord user (by snowflake ID).
Supports the same payload shape as channel posts: content, embeds, media
attachments, and TTS.

Constraints (Discord platform limits):
  - The bot can only DM users it shares at least one guild with.
  - If the recipient has DMs disabled for non-friends, Discord returns 403
    (surfaces as a 502 platform error).
  - `content` capped at 2,000 chars.
  - At least one of `content`, `embeds`, or `attachments` is required.
  - The recipient must be identified by Discord snowflake ID (not username).

This is a dedicated endpoint rather than a `POST /v1/posts` variant because
DMs are 1:1 operational messages (onboarding, billing reminders, support
pings) with a different lifecycle than scheduled channel posts. DMs are
not persisted to `Post` / `ExternalPost` and are always sent immediately.


## POST /v1/discord/dms

**Send a Discord Direct Message**

Send a 1:1 Direct Message from the bot to a Discord user (by snowflake ID).
Supports the same payload shape as channel posts: content, embeds, media
attachments, and TTS.

Constraints (Discord platform limits):
  - The bot can only DM users it shares at least one guild with.
  - If the recipient has DMs disabled for non-friends, Discord returns 403
    (surfaces as a 502 platform error).
  - `content` capped at 2,000 chars.
  - At least one of `content`, `embeds`, or `attachments` is required.
  - The recipient must be identified by Discord snowflake ID (not username).

This is a dedicated endpoint rather than a `POST /v1/posts` variant because
DMs are 1:1 operational messages (onboarding, billing reminders, support
pings) with a different lifecycle than scheduled channel posts. DMs are
not persisted to `Post` / `ExternalPost` and are always sent immediately.


### Request Body

- **accountId** (required) `string`: SocialAccount _id of the connected Discord account the bot speaks as. Caller must own the account (directly or via team membership).
- **userId** (required) `string`: Discord snowflake ID of the recipient (15-21 digits).
- **content** `string`: Message text, up to 2,000 characters.
- **embeds** `array`: Up to 10 Discord embeds. Same shape as channel-post embeds (title, description, color, fields, etc.). See DiscordPlatformData.embeds for the embed object schema.
- **attachments** `array`: Up to 10 media attachments. Each is `{ type: image|video|gif|document, url, filename?, mimeType?, size? }`.
- **tts** `boolean`: Send as text-to-speech message.

### Responses

#### 200: DM sent successfully.

**Response Body:**

- **messageId** `string`: Discord message snowflake ID
- **channelId** `string`: DM channel snowflake (Discord auto-creates one per recipient pair)
- **url** `string`: Direct link to the message, using Discord's @me path for DMs
- **timestamp** `string` (date-time): No description
- **recipient** `object`: 
  - **userId** `string`: No description
  - **platform** `string`: No description (example: "discord")
- **account** `object`: 
  - **id** `string`: No description
  - **username** `string`: No description
  - **displayName** `string`: No description

#### 400: Validation error (missing required fields, content > 2000 chars, malformed snowflake, or all of content/embeds/attachments missing).

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Discord refused the message (most commonly: bot doesn't share a guild with the recipient, OR the recipient has DMs disabled). Error body contains Discord's response.

#### 404: Discord account not found or not accessible to this user.

#### 502: Discord was unreachable or returned an unclassified error.

---

---
