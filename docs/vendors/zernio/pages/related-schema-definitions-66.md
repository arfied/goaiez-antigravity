# Related Schema Definitions

## ValueRuleSet

A named set of bid-adjustment rules on an ad account. Attach it to an ad set with
`valueRuleSetId`. Limits: 6 sets per ad account, 10 rules per set, 4 criteria per rule.


### Properties

- **id** (required) `string`: Platform value rule set id.
- **name** (required) `string`: No description
- **rules** (required) `array`: Evaluated in order; the first matching rule wins.

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

## ValueRule

One bid-adjustment rule. Rules are evaluated in ARRAY ORDER and only the first matching
rule adjusts the bid for an overlapping audience, so the order is semantic.


### Properties

- **id** `string`: Platform rule id. Echo it on `PUT` to KEEP this rule, omit it to CREATE a new one.
A rule left out of the array entirely is DELETED.

- **name** (required) `string`: No description (max: 255)
- **adjustSign** (required) `string`: Direction of the adjustment. There is no signed value field. - one of: INCREASE, DECREASE
- **adjustValue** (required) `integer`: Unsigned percentage magnitude. `INCREASE` accepts 1-1000, `DECREASE` accepts 1-90.
0 is out of range on both.
 (min: 1) (max: 1000)
- **status** `string`: Meta returns `ACTIVE` here but documents no enum for the field. Treat it as a
passthrough: echo whatever the `GET` returned, and do not synthesize values.

- **criteria** (required) `array`: All criteria on a rule must match for the rule to fire.

---
