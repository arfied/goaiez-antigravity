# Related Schema Definitions

## DiscordRole

A Discord guild role, returned verbatim from Discord's API.

### Properties

- **id** `string`: Role snowflake ID
- **name** `string`: No description
- **color** `integer`: Decimal color (0 = no color). Convert to hex via .toString(16).
- **position** `integer`: Position in role hierarchy (higher = more authority)
- **permissions** `string`: Permissions bitfield as a stringified integer
- **managed** `boolean`: True for integration-managed roles (bot roles)
- **mentionable** `boolean`: No description
- **hoist** `boolean`: True if role is displayed separately in member list

---
