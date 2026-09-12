# Crosspost Discord message API Reference

Publishes a message from an announcement channel so it propagates to every server
following that channel.

The source channel must be an announcement channel. Calling this on a regular text
channel returns a 400 before Discord is contacted, because Discord's own error for
this case is opaque.


## POST /v1/discord/channels/{channelId}/messages/{messageId}/crosspost

**Crosspost Discord message**

Publishes a message from an announcement channel so it propagates to every server
following that channel.

The source channel must be an announcement channel. Calling this on a regular text
channel returns a 400 before Discord is contacted, because Discord's own error for
this case is opaque.


### Parameters

- **channelId** (required) in path: Discord announcement channel snowflake ID
- **messageId** (required) in path: Discord message snowflake ID
- **accountId** (required) in query: SocialAccount _id of the Discord account bound to this channel's guild

### Responses

#### 200: Message crossposted.

**Response Body:**

- **data** `object`: The crossposted Discord message object.

#### 400: Invalid ids, or the channel is not an announcement channel.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Discord refused the action (bot lacks the required permission).

#### 404: Discord account not found, not accessible, or not bound to this channel's guild.

#### 502: Discord was unreachable or returned an unclassified error.

---

---
