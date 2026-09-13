# List active Instagram stories API Reference

Returns the IG Business/Creator account's currently-active stories.
Meta keeps stories live for 24h; expired stories are not returned.

Limitations propagated from Meta (these are NOT bugs):
- 24h window only
- Live videos excluded
- Reshared stories not returned
- `mediaUrl` may be null if Meta flagged the story for copyright
- `caption`, `likeCount`, `commentsCount` do not apply to story media


## GET /v1/accounts/{accountId}/instagram/stories

**List active Instagram stories**

Returns the IG Business/Creator account's currently-active stories.
Meta keeps stories live for 24h; expired stories are not returned.

Limitations propagated from Meta (these are NOT bugs):
- 24h window only
- Live videos excluded
- Reshared stories not returned
- `mediaUrl` may be null if Meta flagged the story for copyright
- `caption`, `likeCount`, `commentsCount` do not apply to story media


### Parameters

- **accountId** (required) in path: The Instagram account ID

### Responses

#### 200: Active stories

**Response Body:**

- **data** (required) `array[object]`: 
  - **id** (required) `string`: Instagram media ID of the story.
  - **mediaType** `string,null`: IMAGE / VIDEO / CAROUSEL_ALBUM
  - **mediaProductType** `string,null`: Always 'STORY' for this endpoint.
  - **mediaUrl** `string,null`: Direct media URL. Null if Meta flagged the story for copyright. URL expires when the story expires.
  - **permalink** `string,null`: Public Instagram permalink to the story (only viewable while live).
  - **thumbnailUrl** `string,null`: Thumbnail URL for video stories.
  - **timestamp** `string,null` (date-time): When the story was posted.

#### 400: Invalid request.

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

---
