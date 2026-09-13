# Related Schema Definitions

## RfPrediction

A Meta Reach & Frequency prediction. Money values in whole units of the ad account currency.

### Properties

- **predictionId** `string`: No description
- **status** `string`: ready | pending | failed:<meta code>
- **budget** `number,null`: Quoted (or provided) lifetime budget for the window.
- **reach** `integer,null`: Predicted (or requested) unique reach.
- **impressions** `integer,null`: No description
- **minBudget** `number,null`: Meta's allowed lower bound for this spec.
- **maxBudget** `number,null`: No description
- **minReach** `integer,null`: No description
- **maxReach** `integer,null`: No description
- **frequencyCap** `integer,null`: No description
- **startTime** `integer,null`: Unix seconds; the reserved window the R&F ad set will run on.
- **stopTime** `integer,null`: No description
- **expiresAt** `string,null`: When the reservation's locked price expires (set after reserving).

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
