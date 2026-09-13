# Related Schema Definitions

## GoogleBusinessReview

A Google Business Profile review, as returned by every gmb-reviews read endpoint.

### Properties

- **id** `string`: Review ID
- **name** `string`: Full resource name
- **reviewer** `object`: 
  - **displayName** `string`: 
  - **profilePhotoUrl** `string,null`: 
  - **isAnonymous** `boolean`: 
- **rating** `integer`: Numeric star rating (0 when Google sends no rating) (min: 0) (max: 5)
- **starRating** `string`: Google's string rating - one of: ONE, TWO, THREE, FOUR, FIVE
- **comment** `string`: Review text
- **createTime** `string`: No description
- **updateTime** `string`: No description
- **reviewReply** `object,null`: No description
- **photoCount** `integer`: Number of photos attached to the review (photos only, videos are not counted)
- **photos** `array`: Photos attached to the review by the reviewer

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
