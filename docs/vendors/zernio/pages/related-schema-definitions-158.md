# Related Schema Definitions

## YouTubeDemographicsResponse

### Properties

- **success** `boolean`: No description
- **accountId** `string`: The Zernio SocialAccount ID
- **platform** `string`: No description
- **videoId** `string`: Present only when demographics are scoped to a single video
- **title** `string,null`: Video title (video mode only)
- **publishedAt** `string,null`: Video publish date (video mode only)
- **demographics** `object`: Object keyed by breakdown dimension (age, gender, country)
- **dateRange** `object`: 
  - **startDate** `string`: 
  - **endDate** `string`: 
- **provisionalSince** `string`: Present only when the range reaches into YouTube's ~3-day processing window: the first date whose numbers are provisional and may still be revised by YouTube.
- **note** `string`: No description

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
