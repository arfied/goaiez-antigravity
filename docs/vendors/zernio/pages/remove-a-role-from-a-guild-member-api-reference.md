# Remove a role from a guild member API Reference

Remove one role from one member. Idempotent: removing a role the
member doesn't have returns 204 no-op.

Same permission + hierarchy constraints as the PUT counterpart.


## PUT /v1/discord/guilds/{guildId}/members/{userId}/roles/{roleId}

**Assign a role to a guild member**

Assign one role to one member. Idempotent on Discord's side: re-running
on a member who already has the role is a 204 no-op.

Path shape mirrors Discord's own API (`PUT /guilds/{guild}/members/{user}/roles/{role}`)
for zero-translation mental mapping.

Bot needs MANAGE_ROLES permission in the guild AND its highest role
must be above the target role (Discord hierarchy rule). The
`@everyone` role (where roleId == guildId) cannot be assigned.


### Parameters

- **guildId** (required) in path: No description
- **userId** (required) in path: Discord user snowflake to assign the role to.
- **roleId** (required) in path: No description
- **accountId** (required) in query: No description

### Responses

#### 200: Role assigned (or already present, idempotent).

**Response Body:**

- **success** `boolean`: No description
- **operation** `string`: No description - one of: role_assigned
- **guildId** `string`: No description
- **userId** `string`: No description
- **roleId** `string`: No description

#### 400: Validation error (malformed snowflake) or @everyone manipulation attempt.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Discord refused the request: bot lacks MANAGE_ROLES, or target role is at or above the bot's highest role.

#### 404: Discord account not found or not in this guild.

#### 502: Discord was unreachable or returned an unclassified error.

---

## DELETE /v1/discord/guilds/{guildId}/members/{userId}/roles/{roleId}

**Remove a role from a guild member**

Remove one role from one member. Idempotent: removing a role the
member doesn't have returns 204 no-op.

Same permission + hierarchy constraints as the PUT counterpart.


### Parameters

- **guildId** (required) in path: No description
- **userId** (required) in path: No description
- **roleId** (required) in path: No description
- **accountId** (required) in query: No description

### Responses

#### 200: Role removed (or was already absent, idempotent).

**Response Body:**

- **success** `boolean`: No description
- **operation** `string`: No description - one of: role_removed
- **guildId** `string`: No description
- **userId** `string`: No description
- **roleId** `string`: No description

#### 400: Validation error.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Discord refused the request (permission or hierarchy issue).

#### 404: Discord account not found or not in this guild.

#### 502: Discord was unreachable or returned an unclassified error.

---

---
