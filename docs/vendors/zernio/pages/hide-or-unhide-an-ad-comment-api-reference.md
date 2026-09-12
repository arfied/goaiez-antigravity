# Hide or unhide an ad comment API Reference

Hide or restore a TikTok ad comment. Send hidden=true to hide it or hidden=false to make it public again. Identity and video item ID are not required; no identity lookup is performed.

Requires Ads access. The ad is resolved within the caller's accessible profiles.
Before moderation, Zernio verifies that the comment belongs to this ad using
TikTok's ad-group comment listing. The default search window is the last 30 days.
Use since/until for older comments, with at most 30 days between the dates.
Lookups scan at most 2,000 ad-group comments; narrow the date window if exceeded.
Meta returns 501 feature_not_available with guidance to use the existing inbox
comment endpoints and the account/post IDs from GET /v1/ads/{adId}/comments.


## POST /v1/ads/{adId}/comments/{commentId}/hide

**Hide or unhide an ad comment**

Hide or restore a TikTok ad comment. Send hidden=true to hide it or hidden=false to make it public again. Identity and video item ID are not required; no identity lookup is performed.

Requires Ads access. The ad is resolved within the caller's accessible profiles.
Before moderation, Zernio verifies that the comment belongs to this ad using
TikTok's ad-group comment listing. The default search window is the last 30 days.
Use since/until for older comments, with at most 30 days between the dates.
Lookups scan at most 2,000 ad-group comments; narrow the date window if exceeded.
Meta returns 501 feature_not_available with guidance to use the existing inbox
comment endpoints and the account/post IDs from GET /v1/ads/{adId}/comments.


### Parameters

- **adId** (required) in path: Internal Zernio ad ID or indexed platform ad ID.
- **commentId** (required) in path: TikTok comment ID from the ad comment listing.
- **since** (optional) in query: Start date of the comment lookup window. Defaults to 30 days before until.
- **until** (optional) in query: End date of the comment lookup window. Defaults to today in UTC.

### Request Body

- **hidden** (required) `boolean`: True to hide the comment; false to restore it.

### Responses

#### 200: Comment action completed.

**Response Body:**

- **status** (required) `string`: No description - one of: success
- **commentId** (required) `string`: ID of the created reply or moderated comment.
- **hidden** `boolean`: The requested visibility state.

#### 400: Invalid request

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

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access or the required TikTok comment capability is unavailable.

#### 404: Ad is inaccessible or the comment was not found on this ad in the selected date window.

#### 422: TikTok Ads connection is unavailable.

#### 501: Moderation on this route supports TikTok. Use the inbox comment routes for Meta.

#### 502: TikTok rejected the request or was unavailable. Inspect platformError for its code and message.

---
