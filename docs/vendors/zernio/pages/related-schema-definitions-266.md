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

## ConversionDestination

A discoverable conversion destination on an ad platform: a Meta pixel,
Google conversion action, or LinkedIn conversion rule. Returned by
`listConversionDestinations`, `getConversionDestination`,
`createConversionDestination`, and `updateConversionDestination`.


### Properties

- **id** (required) `string`: Platform-native identifier. Pass back as `destinationId` on event
send and as the path segment on CRUD endpoints.

- **name** (required) `string`: No description
- **type** `string`: Present when the platform locks the event type/category to the
destination (Google conversion actions, LinkedIn conversion rules).
Absent for Meta pixels (which accept any event name per request).

- **status** `string`: For LinkedIn, `inactive` means the rule is soft-deleted (`enabled: false`).
 - one of: active, inactive
- **adAccountId** `string`: Set by adapters whose destinations are scoped to a specific ad
account (LinkedIn). Pass back on subsequent CRUD calls to
identify the parent ad account.

---
