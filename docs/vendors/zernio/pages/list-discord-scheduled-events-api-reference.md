# List Discord scheduled events API Reference

Return all scheduled events in the guild. Events are distinct from
messages: they appear in the server's Events panel and Discord
auto-notifies interested members ahead of start time.

Pass `withUserCount=true` to include `user_count` (number of members
who RSVP'd) on each event. Useful for surfacing engagement.


## GET /v1/discord/guilds/{guildId}/events

**List Discord scheduled events**

Return all scheduled events in the guild. Events are distinct from
messages: they appear in the server's Events panel and Discord
auto-notifies interested members ahead of start time.

Pass `withUserCount=true` to include `user_count` (number of members
who RSVP'd) on each event. Useful for surfacing engagement.


### Parameters

- **guildId** (required) in path: No description
- **accountId** (required) in query: No description
- **withUserCount** (optional) in query: Include user_count on each event.

### Responses

#### 200: List of scheduled events.

**Response Body:**

- **data** `array[DiscordScheduledEvent]`: 

#### 400: Invalid params.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Discord account not found or not in this guild.

#### 502: Bot lacks access to the guild's events.

---

## POST /v1/discord/guilds/{guildId}/events

**Create a Discord scheduled event**

Create a guild scheduled event. Three event types, selected via the
discriminator on `entity.type`:

  - `external`: off-platform (Zoom, in-person, livestream). Requires
    both `location` and `endsAt`. Most common type for scheduler
    integrations.
  - `voice`: hosted in a Discord voice channel. Requires `channelId`.
  - `stage`: hosted in a Discord stage channel. Requires `channelId`.

Bot needs MANAGE_EVENTS in the guild. Existing installs (pre-events
PR) need a re-invite OR a server admin manually granting the
permission. See route header for details.


### Parameters

- **guildId** (required) in path: No description

### Request Body

- **accountId** (required) `string`: No description
- **name** (required) `string`: No description
- **description** `string`: No description
- **startsAt** (required) `string`: ISO 8601 start time. Must be in the future.
- **entity**: Platform-specific settings (see schema definitions below)
- **imageDataUri** `string`: Optional cover image as a base64 data URI.

### Responses

#### 200: Event created.

**Response Body:**

- **data**: `DiscordScheduledEvent` - See schema definition

#### 400: Validation error (missing required fields for the chosen entity type, malformed snowflake, past startsAt, etc.).

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Discord account not found.

#### 502: Bot lacks MANAGE_EVENTS in the guild.

---
