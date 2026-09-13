# Delete a Discord guild role API Reference

Permanently deletes a role from the guild and removes it from every member.
This cannot be undone.

Requires the bot to hold Manage Roles, and the target role must sit below the bot's
highest role.


## PATCH /v1/discord/guilds/{guildId}/roles/{roleId}

**Edit a Discord guild role**

Updates a role's name, color, hoist, mentionable flag, or permission bitfield.
At least one field must be supplied. Omitted fields are left unchanged.

Requires the bot to hold Manage Roles, and the target role must sit below the bot's
highest role. See the create-role operation for the re-invite requirement.


### Parameters

- **guildId** (required) in path: Discord guild snowflake ID
- **roleId** (required) in path: Discord role snowflake ID
- **accountId** (required) in query: SocialAccount _id of the Discord account bound to this guild

### Request Body

- **name** `string`: No description
- **color** `integer`: No description
- **hoist** `boolean`: No description
- **mentionable** `boolean`: No description
- **permissions** `string`: Permissions bitfield as a stringified integer

### Responses

#### 200: Role updated.

**Response Body:**

- **data**: `DiscordRole` - See schema definition

#### 400: Invalid ids, or no fields supplied to edit.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Discord refused the action (bot lacks Manage Roles, or the target role sits at or above the bot's highest role).

#### 404: Discord account not found, not accessible, or not bound to this guild.

#### 502: Discord was unreachable or returned an unclassified error.

---

## DELETE /v1/discord/guilds/{guildId}/roles/{roleId}

**Delete a Discord guild role**

Permanently deletes a role from the guild and removes it from every member.
This cannot be undone.

Requires the bot to hold Manage Roles, and the target role must sit below the bot's
highest role.


### Parameters

- **guildId** (required) in path: Discord guild snowflake ID
- **roleId** (required) in path: Discord role snowflake ID
- **accountId** (required) in query: SocialAccount _id of the Discord account bound to this guild

### Responses

#### 200: Role deleted.

**Response Body:**

- **success** `boolean`: No description

#### 400: Invalid accountId, guildId, or roleId format.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Discord refused the action (bot lacks Manage Roles, or the target role sits at or above the bot's highest role).

#### 404: Discord account not found, not accessible, or not bound to this guild.

#### 502: Discord was unreachable or returned an unclassified error.

---
