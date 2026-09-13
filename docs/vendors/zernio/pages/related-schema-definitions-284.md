# Related Schema Definitions

## DiscordGuildMember

A Discord guild member, returned verbatim from Discord's API.

### Properties

- **user** `object`: 
  - **id** `string`: User snowflake
  - **username** `string`: 
  - **discriminator** `string`: 
  - **avatar** `string,null`: 
  - **global_name** `string,null`: User's display name (post-2023 Discord rebrand)
- **nick** `string,null`: Guild-specific nickname
- **roles** `array`: Snowflake IDs of roles assigned to this member
- **joined_at** `string`: No description
- **premium_since** `string,null`: When the user started boosting the server

---
