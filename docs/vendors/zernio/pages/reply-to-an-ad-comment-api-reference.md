# Reply to an ad comment API Reference

Reply to a first-level TikTok ad comment. Requires a TT_USER or CUSTOMIZED_USER identity with comment-management permission. Replies to replies are rejected. The response commentId identifies the new reply. This operation is not idempotent; do not blindly retry an uncertain response.

Unknown identity and video item fields are resolved only when needed for this
action, then persisted for reuse. Comment-specific fields take precedence.
If TikTok no longer returns the ad needed to resolve identity, 404 ad_not_found
directs you to check deletion or archival in TikTok Ads Manager. Listing can
still succeed. Unsupported or unavailable identity returns 403 feature_not_available.
Denied access to ad details returns 403 insufficient_permissions with reconnect
guidance and the upstream platformError.

Requires Ads access. The ad is resolved within the caller's accessible profiles.
Before moderation, Zernio verifies that the comment belongs to this ad using
TikTok's ad-group comment listing. The default search window is the last 30 days.
Use since/until for older comments, with at most 30 days between the dates.
Lookups scan at most 2,000 ad-group comments; narrow the date window if exceeded.
Meta returns 501 feature_not_available with guidance to use the existing inbox
comment endpoints and the account/post IDs from GET /v1/ads/{adId}/comments.


## POST /v1/ads/{adId}/comments/{commentId}/reply

**Reply to an ad comment**

Reply to a first-level TikTok ad comment. Requires a TT_USER or CUSTOMIZED_USER identity with comment-management permission. Replies to replies are rejected. The response commentId identifies the new reply. This operation is not idempotent; do not blindly retry an uncertain response.

Unknown identity and video item fields are resolved only when needed for this
action, then persisted for reuse. Comment-specific fields take precedence.
If TikTok no longer returns the ad needed to resolve identity, 404 ad_not_found
directs you to check deletion or archival in TikTok Ads Manager. Listing can
still succeed. Unsupported or unavailable identity returns 403 feature_not_available.
Denied access to ad details returns 403 insufficient_permissions with reconnect
guidance and the upstream platformError.

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

- **text** (required) `string`: Non-empty reply text.

### Responses

#### 200: Comment action completed.

**Response Body:**

- **status** (required) `string`: No description - one of: success
- **commentId** (required) `string`: ID of the created reply or moderated comment.

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

#### 403: Ads access or supported identity is unavailable (feature_not_available), or TikTok denies ad-detail access or comment-management permission (insufficient_permissions). Grant permission and reconnect the TikTok Ads account before retrying.

#### 404: Ad is inaccessible or unavailable on TikTok for identity resolution (ad_not_found), or the comment was not found on this ad in the selected date window (resource_not_found).

#### 422: TikTok Ads connection is unavailable.

#### 501: Moderation on this route supports TikTok. Use the inbox comment routes for Meta.

#### 502: TikTok rejected the request or was unavailable. Inspect platformError for its code and message.

---
