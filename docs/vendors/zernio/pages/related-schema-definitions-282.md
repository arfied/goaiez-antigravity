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

## ErrorResponse

Canonical error envelope. `error` is the human-readable message; `type`,
`code`, `param`, `platform`, and `platformError` are top-level siblings
for programmatic handling. For upstream platform failures (`type:
platform_error`), `platformError` carries the provider's raw payload
verbatim (for Meta: `error_subcode`, `error_user_title`, `error_user_msg`).


### Properties

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

---
