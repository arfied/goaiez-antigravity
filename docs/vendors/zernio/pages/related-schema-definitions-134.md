# Related Schema Definitions

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

## AdBudget

Budget amount in the ad account's native currency (see the campaign's `currency` field for the code).

### Properties

- **amount** (required) `number`: No description
- **type** (required) `string`: No description - one of: daily, lifetime

## BidStrategy

Meta bid strategy. Same enum applies at campaign and ad-set level; ad-set value (when set)
overrides campaign-level. Cross-field rules:
  - `LOWEST_COST_WITHOUT_CAP` (default): auto-bid, forbids `bidAmount` and `roasAverageFloor`.
  - `LOWEST_COST_WITH_BID_CAP` / `COST_CAP`: require `bidAmount` (whole currency units).
  - `LOWEST_COST_WITH_MIN_ROAS`: requires `roasAverageFloor` (decimal multiplier, 2.0 = 2.0x).
Source: facebook-business-sdk-codegen api_specs/specs/enum_types.json (`AdSet_bid_strategy`,
`Campaign_bid_strategy`).

---
