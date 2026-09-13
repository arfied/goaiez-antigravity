# Related Schema Definitions

## PortfolioBidStrategy

A Google Ads portfolio bid strategy: a named bidding strategy shared across campaigns, with its R.130 report metrics over the queried date range.

### Properties

- **id** `string`: Numeric bid strategy id; pass as portfolioBidStrategyId or in the {strategyId} path.
- **name** `string`: No description
- **type** `string`: No description - one of: TARGET_CPA, TARGET_ROAS, MAXIMIZE_CONVERSIONS, MAXIMIZE_CONVERSION_VALUE
- **status** `string`: ENABLED or REMOVED.
- **campaignCount** `integer`: Number of campaigns currently attached.
- **clicks** `integer`: No description
- **cost** `number`: Cost in the account's currency units (converted from micros).
- **costPerConversion** `number`: Cost per conversion in the account's currency units.
- **impressions** `integer`: No description
- **averageCpc** `number`: Average CPC in the account's currency units.
- **conversions** `number`: No description
- **targetCpa** `number,null`: Current target, in the account's currency units. Null for a ROAS-family type (TARGET_ROAS, MAXIMIZE_CONVERSION_VALUE), or a Maximize type with no target set. Pre-fills the edit form's target field.
- **targetRoas** `number,null`: Current target as a decimal multiplier (2.0 = 2.0x). Null for a CPA-family type (TARGET_CPA, MAXIMIZE_CONVERSIONS), or a Maximize type with no target set.

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
