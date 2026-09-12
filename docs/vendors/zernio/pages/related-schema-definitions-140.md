# Related Schema Definitions

## ApiKey

### Properties

- **id** `string`: No description
- **name** `string`: No description
- **keyPreview** `string`: No description
- **expiresAt** `string`: No description
- **createdAt** `string`: No description
- **key** `string`: Returned only once, on creation
- **scope** `string`: 'full' grants access to all profiles, 'profiles' restricts to specific profiles - one of: full, profiles (default: full)
- **profileIds** `array`: Profiles this key can access (populated with name and color). Only present when scope is 'profiles'.
- **permission** `string`: 'read-write' allows all operations, 'read' restricts to GET requests only - one of: read-write, read (default: read-write)
- **disabledResourceGroups** `array`: Resource groups this key can NOT access (opt-out denylist). Absent or empty means legacy full access. A key with any group disabled is a restricted key (zrk_ prefix) and can never manage API keys, invites, or member identity. Each operation's group is published as x-resource-group. With 'messages' disabled, the key cannot read or send direct messages through any API surface, and it cannot create or edit a webhook subscription broader than itself: it cannot subscribe to, test-fire, redeliver, or read delivery logs for message events. Subscriptions created earlier, from the dashboard, or with a full-access key keep delivering whatever their own `disabledResourceGroups` allows, so restricting an existing integration end to end means restricting the subscription too. OAuth connector tokens (AI assistants and MCP clients) resolve against the same registry, but their groups are not settable yet: treat an authorized connector as full access.

---
