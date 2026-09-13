# Create a Discord public thread API Reference

Creates a public thread in a channel. Pass `messageId` to start the thread from an
existing message, or omit it to create a standalone thread.

Threads created here are always public. Requires the bot to hold Create Public
Threads, which the Zernio bot requests at install time.


## POST /v1/discord/channels/{channelId}/threads

**Create a Discord public thread**

Creates a public thread in a channel. Pass `messageId` to start the thread from an
existing message, or omit it to create a standalone thread.

Threads created here are always public. Requires the bot to hold Create Public
Threads, which the Zernio bot requests at install time.


### Parameters

- **channelId** (required) in path: Discord channel snowflake ID
- **accountId** (required) in query: SocialAccount _id of the Discord account bound to this channel's guild

### Request Body

- **name** (required) `string`: Thread name
- **messageId** `string`: Optional message snowflake to start the thread from. Omit for a standalone thread.
- **autoArchiveDuration** `integer`: Minutes of inactivity before the thread auto-archives. Discord accepts only these four values. - one of: 60, 1440, 4320, 10080

### Responses

#### 200: Thread created.

**Response Body:**

- **data** `object`: 
  - **id** `string`: Thread snowflake ID
  - **name** `string`: No description

#### 400: Invalid accountId, channelId, messageId, name, or autoArchiveDuration.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Discord refused the action (bot lacks Create Public Threads).

#### 404: Discord account not found, not accessible, or not bound to this channel's guild.

#### 502: Discord was unreachable or returned an unclassified error.

---

---
