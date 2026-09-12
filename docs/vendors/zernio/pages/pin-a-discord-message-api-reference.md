# Pin a Discord message API Reference

Pin a specific message in a channel. Path shape mirrors Discord's own
API (`PUT /channels/{cid}/pins/{mid}`).

Idempotent: re-pinning an already-pinned message is a 204 no-op.

Constraints:
  - Bot needs MANAGE_MESSAGES in the channel.
  - 50-pin cap per channel: hitting it returns 400 (Discord-side).
    Caller should unpin one first.


## PUT /v1/discord/channels/{channelId}/pins/{messageId}

**Pin a Discord message**

Pin a specific message in a channel. Path shape mirrors Discord's own
API (`PUT /channels/{cid}/pins/{mid}`).

Idempotent: re-pinning an already-pinned message is a 204 no-op.

Constraints:
  - Bot needs MANAGE_MESSAGES in the channel.
  - 50-pin cap per channel: hitting it returns 400 (Discord-side).
    Caller should unpin one first.


### Parameters

- **channelId** (required) in path: No description
- **messageId** (required) in path: No description
- **accountId** (required) in query: No description

### Responses

#### 200: Message pinned (or was already pinned, idempotent).

**Response Body:**

- **success** `boolean`: No description
- **operation** `string`: No description - one of: message_pinned
- **channelId** `string`: No description
- **messageId** `string`: No description

#### 400: Validation error or pin cap (50) reached.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Discord account not found.

#### 502: Bot lacks MANAGE_MESSAGES in the channel.

---

## DELETE /v1/discord/channels/{channelId}/pins/{messageId}

**Unpin a Discord message**

Unpin a message. Same MANAGE_MESSAGES permission requirement as pin.
Idempotent: unpinning a non-pinned message is a 204 no-op.


### Parameters

- **channelId** (required) in path: No description
- **messageId** (required) in path: No description
- **accountId** (required) in query: No description

### Responses

#### 200: Message unpinned (or was not pinned, idempotent).

**Response Body:**

- **success** `boolean`: No description
- **operation** `string`: No description - one of: message_unpinned
- **channelId** `string`: No description
- **messageId** `string`: No description

#### 400: Validation error.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Discord account not found.

#### 502: Bot lacks MANAGE_MESSAGES in the channel.

---

---
