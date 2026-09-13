# List Discord guild members API Reference

Cursor-paginated list of guild members. Returns Discord's raw member
objects so callers can build community-ops automation (e.g. "add role
to all members joined in the last 7 days") on the actual platform shape.

Pagination: pass `after` = the last `user.id` from the previous page.
Omit on the first call. Response includes a `nextCursor` and `hasMore`
flag so callers don't need to know Discord's pagination shape.


## GET /v1/discord/guilds/{guildId}/members

**List Discord guild members**

Cursor-paginated list of guild members. Returns Discord's raw member
objects so callers can build community-ops automation (e.g. "add role
to all members joined in the last 7 days") on the actual platform shape.

Pagination: pass `after` = the last `user.id` from the previous page.
Omit on the first call. Response includes a `nextCursor` and `hasMore`
flag so callers don't need to know Discord's pagination shape.


### Parameters

- **guildId** (required) in path: No description
- **accountId** (required) in query: No description
- **limit** (optional) in query: Page size (1-1000).
- **after** (optional) in query: Snowflake of the last member from the previous page.

### Responses

#### 200: List of guild members.

**Response Body:**

- **data** `array[DiscordGuildMember]`: 
- **pagination** `object`: 
  - **nextCursor** `string,null`: Pass as `after` on the next call. Null when there are no more pages.
  - **hasMore** `boolean`: No description

#### 400: Invalid query params.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Discord denied access to the guild members (the bot is no longer in the guild).

#### 404: Discord account not found or not in this guild.

---
