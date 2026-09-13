# Related Schema Definitions

## AdKeyword

### Properties

- **id** `string`: No description
- **accountId** `string`: Account ID owning the sync
- **profileId** `string`: No description
- **platform** `string`: No description - one of: google
- **adAccountId** `string`: Google customer ID
- **campaignId** `string`: No description
- **campaignName** `string,null`: No description
- **campaignStatus** `string,null`: No description
- **adSetId** `string`: Google ad group ID
- **adSetName** `string,null`: No description
- **adSetStatus** `string,null`: No description
- **keyword** `string`: No description
- **matchType** `string`: No description - one of: exact, phrase, broad, unknown
- **status** `string`: No description - one of: active, paused
- **negative** `boolean`: No description
- **qualityScore** `integer,null`: Google Quality Score, 1-10. Null when unrated.
- **syncedAt** `string,null`: No description
- **metrics** `object,null`: Trailing 30-day window. Null on rows synced before the metrics columns existed (re-synced on the keyword's next weekly sweep).

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
