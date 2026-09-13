# Delete a Discord scheduled event API Reference

Hard-delete an event. Use PATCH with `status: 'cancelled'` instead
if you want the event preserved in the guild's history.


## GET /v1/discord/guilds/{guildId}/events/{eventId}

**Get a Discord scheduled event**

### Parameters

- **guildId** (required) in path: No description
- **eventId** (required) in path: No description
- **accountId** (required) in query: No description

### Responses

#### 200: Event.

**Response Body:**

- **data**: `DiscordScheduledEvent` - See schema definition

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Event or Discord account not found.

---

## PATCH /v1/discord/guilds/{guildId}/events/{eventId}

**Update a Discord scheduled event**

Patch any subset of fields. Passing `status: 'cancelled'` is how you
cancel an event. Discord doesn't have a dedicated cancel endpoint,
it's a status transition.

Most status transitions Discord enforces (you can't go SCHEDULED →
COMPLETED directly). The common consumer case is SCHEDULED → CANCELED.


### Parameters

- **guildId** (required) in path: No description
- **eventId** (required) in path: No description

### Request Body

- **accountId** (required) `string`: No description
- **name** `string`: No description
- **description** `string`: No description
- **startsAt** `string`: No description
- **endsAt** `string`: No description
- **location** `string`: For external events.
- **status** `string`: Status transition. Most common: 'cancelled' to cancel an event. - one of: scheduled, active, completed, cancelled
- **imageDataUri** `string`: No description

### Responses

#### 200: Event updated.

**Response Body:**

- **data**: `DiscordScheduledEvent` - See schema definition

#### 400: Validation error, no updatable fields beyond accountId provided, or Discord rejected the update (invalid status transition).

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Discord refused the update (bot permissions).

#### 404: Event or Discord account not found.

#### 502: Discord was unreachable or returned an unclassified error.

---

## DELETE /v1/discord/guilds/{guildId}/events/{eventId}

**Delete a Discord scheduled event**

Hard-delete an event. Use PATCH with `status: 'cancelled'` instead
if you want the event preserved in the guild's history.


### Parameters

- **guildId** (required) in path: No description
- **eventId** (required) in path: No description
- **accountId** (required) in query: No description

### Responses

#### 200: Event deleted.

**Response Body:**

- **success** `boolean`: No description
- **deleted** `string`: The deleted event's snowflake.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Event or Discord account not found.

#### 502: Bot lacks MANAGE_EVENTS in the guild.

---
