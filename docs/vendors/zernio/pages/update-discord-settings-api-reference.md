# Update Discord settings API Reference

Update Discord account settings. Supports two operations (can be combined):

1. **Webhook identity** - Set the default display name and avatar that appear as the message author on every post. These are account-level defaults; individual posts can override them via platformSpecificData.webhookUsername / webhookAvatarUrl.

2. **Switch channel** - Move the connection to a different channel in the same guild. A new webhook is automatically created in the target channel.


## GET /v1/accounts/{accountId}/discord-settings

**Get Discord account settings**

Returns the current Discord account settings including webhook identity (display name and avatar), connected channel, and guild information.

### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: Discord account settings

**Response Body:**

- **account** `object`: 
  - **_id** `string`: No description
  - **platform** `string`: No description (example: "discord")
  - **username** `string`: Channel name
  - **displayName** `string`: Guild - #channel display name
  - **profilePicture** `string`: Guild icon URL
  - **channelId** `string`: Connected channel snowflake ID
  - **channelName** `string`: Channel name
  - **channelType** `string`: Channel type (0 = text, 5 = announcement, 15 = forum)
  - **guildId** `string`: Guild (server) snowflake ID
  - **webhookUsername** `string,null`: Custom webhook display name (null = default "Zernio")
  - **webhookAvatarUrl** `string,null`: Custom webhook avatar URL (null = default bot avatar)

#### 400: Not a Discord account

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

---

## PATCH /v1/accounts/{accountId}/discord-settings

**Update Discord settings**

Update Discord account settings. Supports two operations (can be combined):

1. **Webhook identity** - Set the default display name and avatar that appear as the message author on every post. These are account-level defaults; individual posts can override them via platformSpecificData.webhookUsername / webhookAvatarUrl.

2. **Switch channel** - Move the connection to a different channel in the same guild. A new webhook is automatically created in the target channel.


### Parameters

- **accountId** (required) in path: No description

### Request Body

- **webhookUsername** `string`: Custom display name for the webhook (1-80 chars). Empty string resets to default ("Zernio"). Cannot contain "clyde" or "discord".
- **webhookAvatarUrl** `string`: Custom avatar URL. Empty string resets to default bot avatar.
- **channelId** `string`: Switch to a different channel in the same guild. Must be a text (0), announcement (5), or forum (15) channel.

### Responses

#### 200: Settings updated

**Response Body:**

- **message** `string`: No description (example: "Discord settings updated")
- **account** `object`: 
  - **_id** `string`: No description
  - **platform** `string`: No description
  - **username** `string`: No description
  - **displayName** `string`: No description
  - **profilePicture** `string`: No description
  - **channelId** `string`: No description
  - **channelName** `string`: No description
  - **channelType** `string`: No description
  - **guildId** `string`: No description
  - **webhookUsername** `string,null`: No description
  - **webhookAvatarUrl** `string,null`: No description

#### 400: Invalid request (no changes, invalid channel type, or bot cannot access channel)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Discord account not found

---

---
