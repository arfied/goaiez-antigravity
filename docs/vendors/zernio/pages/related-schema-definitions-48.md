# Related Schema Definitions

## BusinessCenter

TikTok Business Center entity. Returned by `GET /v1/ads/business-centers`. BCs are
TikTok's agency container: one BC owns N advertisers (ad accounts). Most solo
advertisers don't have one; the agency token uses BCs to roll up multi-client access.


### Properties

- **bcId** `string`: Business Center ID
- **name** `string`: Display name set by the BC owner
- **advertiserCount** `integer,null`: Number of advertisers reachable under this BC for the calling token.
`null` when the BC asset walk returned empty or failed (typical for
agency apps without full BC asset read scope), distinct from `0`,
which would imply the BC genuinely has no advertisers.


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
