# Create a Discord guild role API Reference

Creates a new role in the guild.

Requires the bot to hold the Manage Roles permission. Guilds that added the Zernio bot
before role management shipped must re-invite it, because Discord applies the
permission set at invite time.

Discord's role hierarchy applies: the bot cannot create a role positioned at or above
its own highest role, and cannot grant permissions it does not itself hold. Either
attempt returns a 403 carrying Discord's own error.


## GET /v1/discord/guilds/{guildId}/roles

**List Discord guild roles**

Returns all roles in a Discord guild. Useful for building role-mention
pickers, role-permission UIs, or finding the role ID before calling
the role-assign endpoint.

Roles are returned unordered. Sort client-side by `position` if you
need Discord's UI ordering.

Caller must pass `accountId` of a Discord SocialAccount bound to this
guild (route verifies team access + guild match).


### Parameters

- **guildId** (required) in path: Discord guild snowflake ID
- **accountId** (required) in query: SocialAccount _id of the Discord account bound to this guild

### Responses

#### 200: List of guild roles.

**Response Body:**

- **data** `array[object]`: 
  - **id** `string`: Role snowflake ID
  - **name** `string`: No description
  - **color** `integer`: Decimal color (0 = no color). Convert to hex via .toString(16).
  - **position** `integer`: Position in role hierarchy (higher = more authority)
  - **permissions** `string`: Permissions bitfield as a stringified integer
  - **managed** `boolean`: True for integration-managed roles (bot roles)
  - **mentionable** `boolean`: No description
  - **hoist** `boolean`: True if role is displayed separately in member list

#### 400: Invalid accountId or guildId format.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Discord refused the request (bot lacks View Channels permission in the guild).

#### 404: Discord account not found, not accessible, or not bound to this guild.

#### 502: Discord was unreachable or returned an unclassified error.

---

## POST /v1/discord/guilds/{guildId}/roles

**Create a Discord guild role**

Creates a new role in the guild.

Requires the bot to hold the Manage Roles permission. Guilds that added the Zernio bot
before role management shipped must re-invite it, because Discord applies the
permission set at invite time.

Discord's role hierarchy applies: the bot cannot create a role positioned at or above
its own highest role, and cannot grant permissions it does not itself hold. Either
attempt returns a 403 carrying Discord's own error.


### Parameters

- **guildId** (required) in path: Discord guild snowflake ID
- **accountId** (required) in query: SocialAccount _id of the Discord account bound to this guild

### Request Body

- **name** (required) `string`: No description
- **color** `integer`: Decimal color (0 = no color). 0xFF0000 red is 16711680.
- **hoist** `boolean`: Display members with this role separately in the member list
- **mentionable** `boolean`: Allow anyone to @mention this role
- **permissions** `string`: Permissions bitfield as a stringified integer

### Responses

#### 201: Role created.

**Response Body:**

- **data**: `DiscordRole` - See schema definition

#### 400: Invalid accountId, guildId, or role body.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Discord refused the action (bot lacks Manage Roles, or the new role would sit at or above the bot's highest role).

#### 404: Discord account not found, not accessible, or not bound to this guild.

#### 502: Discord was unreachable or returned an unclassified error.

---
