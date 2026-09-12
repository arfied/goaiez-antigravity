# Related Schema Definitions

## CtwaSingleResponse

Response returned by `POST /v1/ads/ctwa` when the request used the
single-creative shape (top-level headline / body / imageUrl|video).
`adType` is the union discriminator.


### Properties

- **adType** (required) `string`: No description - one of: single
- **ad** (required) `object`: The persisted Ad document.
- **message** (required) `string`: No description

## CtwaMultiResponse

Response returned by `POST /v1/ads/ctwa` when the request used the
multi-creative shape (`creatives[]`). N persisted Ad documents share
the returned `platformCampaignId` and `platformAdSetId`. `adType` is
the union discriminator.


### Properties

- **adType** (required) `string`: No description - one of: multi
- **ads** (required) `array`: The persisted Ad documents (one per creative), all sharing the same
`platformCampaignId` and `platformAdSetId`.

- **platformCampaignId** (required) `string`: No description
- **platformAdSetId** (required) `string`: No description
- **message** (required) `string`: No description

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
