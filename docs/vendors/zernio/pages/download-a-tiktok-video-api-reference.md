# Download a TikTok video API Reference

Get a download URL or list available formats for a TikTok video. Requires Tools API access and uses the Tools API rate limit. Provider gateway failures and provider-side access blocks return 503; an unavailable video returns 404.

## GET /v1/tools/tiktok/download

**Download a TikTok video**

Get a download URL or list available formats for a TikTok video. Requires Tools API access and uses the Tools API rate limit. Provider gateway failures and provider-side access blocks return 503; an unavailable video returns 404.

### Parameters

- **url** (required) in query: TikTok video URL or numeric video ID.
- **action** (optional) in query: Return a download URL or the available formats.
- **formatId** (optional) in query: Format ID from the formats response. Omit to select the first available format.

### Responses

#### 200: Download URL or available formats.

**Response Body:**

- **success** (required) `boolean`: No description
- **title** (required) `string`: No description
- **format** `string`: Selected format label for action=download.
- **downloadUrl** `string`: Media download URL for action=download.
- **duration** `number`: Duration in seconds for action=formats.
- **cover** `string`: Thumbnail URL for action=formats.
- **formats** `array[object]`: Available formats for action=formats.
  - **id** `string`: No description
  - **label** `string`: No description
  - **ext** `string`: No description
  - **type** `string`: No description
  - **height** `number`: No description
  - **width** `number`: No description
  - **fps** `number`: No description
  - **hasAudio** `boolean`: No description
  - **hasVideo** `boolean`: No description

#### 400: Missing or invalid url, action, or formatId.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Tools API access is required.

#### 404: The video or a downloadable format was not found.

#### 429: The connected account's upstream platform quota is exhausted.

Reddit rate-limits per connected Reddit user (1000 requests per
10-minute window), and that budget is shared by every operation using
that account. Retry after the window resets rather than retrying
immediately; repeated calls while exhausted do not succeed and keep the
budget spent.


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

#### 502: The platform returned a server error.

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

#### 503: An upstream service or database is temporarily unavailable. Retry after the indicated delay. A timed-out write may have completed upstream; check its outcome before resubmitting.

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

---
