# Delete a Discord channel message API Reference

Deletes a message from a channel, for moderation and cleanup. This cannot be undone.

Deleting a message the bot did not send requires the bot to hold the Manage Messages
permission, which the Zernio bot requests at install time. Deleting the bot's own
message needs no extra permission.

Ownership is verified by resolving the channel's guild and confirming the caller owns
a Discord account bound to it.


## DELETE /v1/discord/channels/{channelId}/messages/{messageId}

**Delete a Discord channel message**

Deletes a message from a channel, for moderation and cleanup. This cannot be undone.

Deleting a message the bot did not send requires the bot to hold the Manage Messages
permission, which the Zernio bot requests at install time. Deleting the bot's own
message needs no extra permission.

Ownership is verified by resolving the channel's guild and confirming the caller owns
a Discord account bound to it.


### Parameters

- **channelId** (required) in path: Discord channel snowflake ID
- **messageId** (required) in path: Discord message snowflake ID
- **accountId** (required) in query: SocialAccount _id of the Discord account bound to this channel's guild

### Responses

#### 200: Message deleted.

**Response Body:**

- **success** `boolean`: No description

#### 400: Invalid accountId, channelId, or messageId format.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Discord refused the action (bot lacks Manage Messages).

#### 404: Discord account not found, not accessible, or not bound to this channel's guild.

#### 502: Discord was unreachable or returned an unclassified error.

---

---
