# Render pre-create ad previews API Reference

Renders how a creative would look per placement BEFORE any ad exists, via Meta's
`/generatepreviews`. Provide exactly one creative source: `existingCreativeId` or `creativeSpec`.
Each preview is an HTML `<iframe>` snippet embeddable directly. Unknown `formats` values
return Meta's 400 verbatim.


## POST /v1/ads/preview

**Render pre-create ad previews**

Renders how a creative would look per placement BEFORE any ad exists, via Meta's
`/generatepreviews`. Provide exactly one creative source: `existingCreativeId` or `creativeSpec`.
Each preview is an HTML `<iframe>` snippet embeddable directly. Unknown `formats` values
return Meta's 400 verbatim.


### Request Body

- **accountId** (required) `string`: Zernio SocialAccount id used to resolve the Meta token.
- **adAccountId** (required) `string`: Platform ad account id (Meta act_<n>, Google customer id, LinkedIn account id, ...).
- **formats** `array`: Meta ad_format values, one preview per format. Defaults to [DESKTOP_FEED_STANDARD].
- **existingCreativeId** `string`: Preview an existing ad-account creative by id. Mutually exclusive with creativeSpec.
- **creativeSpec** `object`: Raw Meta creative spec forwarded verbatim to /generatepreviews. Mutually exclusive with existingCreativeId.

### Responses

#### 200: Rendered previews

**Response Body:**

- **previews** `array[object]`: 
  - **format** `string`: No description
  - **html** `string,null`: Meta's <iframe> snippet; null when Meta returned no preview for the format.

#### 400: Invalid input, or Meta rejected the creative spec / ad_format; the message carries Meta's error

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: The account or requested resource was not found or is not accessible. An account ID may have been disconnected and removed. Read GET /v1/accounts for current account IDs.

**Response Body:**

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

#### 409: The account exists but is inactive or needs reconnection. Reconnect it, then read GET /v1/accounts for its current account ID before retrying. Code: ads_connection_required.

**Response Body:**

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

#### 429: Meta rate limit reached

#### 501: Only supported on Meta (facebook/instagram)

---
