# Get a Discord guild member API Reference

Fetch a single guild member by Discord user id.

Cheaper than paginating the full member listing when you already know
who you are looking for.


## GET /v1/discord/guilds/{guildId}/members/{userId}

**Get a Discord guild member**

Fetch a single guild member by Discord user id.

Cheaper than paginating the full member listing when you already know
who you are looking for.


### Parameters

- **guildId** (required) in path: No description
- **userId** (required) in path: Discord user snowflake.
- **accountId** (required) in query: No description

### Responses

#### 200: The guild member.

**Response Body:**

- **data**: `DiscordGuildMember` - See schema definition

#### 400: Invalid request

**Response Body:**

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Discord account not found, or the user is not a member of this guild.

---
