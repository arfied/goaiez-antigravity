# Search Discord guild members API Reference

Search guild members whose username or nickname **starts with** the
query (Discord matches prefixes only, not substrings).

Cheaper than paginating the full member listing when you already know
who you are looking for.


## GET /v1/discord/guilds/{guildId}/members/search

**Search Discord guild members**

Search guild members whose username or nickname **starts with** the
query (Discord matches prefixes only, not substrings).

Cheaper than paginating the full member listing when you already know
who you are looking for.


### Parameters

- **guildId** (required) in path: No description
- **accountId** (required) in query: No description
- **query** (required) in query: Username or nickname prefix to match.
- **limit** (optional) in query: No description

### Responses

#### 200: Matching guild members.

**Response Body:**

- **data** `array[DiscordGuildMember]`: 

#### 400: Invalid query params.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Discord account not found or not in this guild.

---
