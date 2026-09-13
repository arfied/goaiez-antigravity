# Related Schema Definitions

## ProfilesListResponse

### Properties

- **profiles** `array`: No description
- **total** `integer`: Total matching profiles across all pages. Present only when limit or skip was passed.
- **skip** `integer`: Offset applied. Present only when limit or skip was passed.
- **limit** `integer`: Echo of the limit query param. Present only when it was passed.

## Profile

### Properties

- **_id** `string`: No description
- **userId** `string`: No description
- **name** `string`: No description
- **description** `string`: No description
- **color** `string`: No description
- **isDefault** `boolean`: No description
- **isOverLimit** `boolean`: Only present when includeOverLimit=true. Indicates if this profile exceeds the plan limit.
- **createdAt** `string`: No description

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

## ProfileCreateResponse

### Properties

- **message** `string`: No description
- **profile**: No description

---
