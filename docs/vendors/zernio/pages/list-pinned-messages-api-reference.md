# List pinned messages API Reference

Returns the channel's pinned messages, sorted most-recently-pinned
first. Discord caps a channel at 50 pinned messages and returns the
full list unpaginated.

Bot needs READ_MESSAGE_HISTORY in the channel (granted by default
BOT_PERMISSIONS).


## GET /v1/discord/channels/{channelId}/pins

**List pinned messages**

Returns the channel's pinned messages, sorted most-recently-pinned
first. Discord caps a channel at 50 pinned messages and returns the
full list unpaginated.

Bot needs READ_MESSAGE_HISTORY in the channel (granted by default
BOT_PERMISSIONS).


### Parameters

- **channelId** (required) in path: Discord channel snowflake.
- **accountId** (required) in query: SocialAccount _id of any Discord account in the same guild.

### Responses

#### 200: Pinned messages.

**Response Body:**

- **data** `array[object]`: 
  - **id** `string`: No description
  - **channel_id** `string`: No description
  - **content** `string`: No description
  - **timestamp** `string` (date-time): No description
  - **author** `object`: No description
  - **attachments** `array[object]`: 
    Type: `object`
  - **embeds** `array[object]`: 
    Type: `object`

#### 400: Invalid channelId or accountId format.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Discord account not found or not accessible.

#### 502: Bot lacks access to the channel.

---

---
