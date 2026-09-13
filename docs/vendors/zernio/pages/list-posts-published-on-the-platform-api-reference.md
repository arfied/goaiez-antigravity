# List posts published on the platform API Reference

Returns the 25 most recent posts that exist on the platform for a connected account, read
live from the platform API. This covers everything on the account, including posts that
were never created through Zernio.

Use it to obtain the platform's own post id, which the analytics endpoints take as input.
On YouTube the returned `id` is the video ID that `GET /v1/analytics/youtube/daily-views`,
`/video-retention` and `/demographics` expect as `videoId`, so this endpoint is what backs
a video picker in your own UI.

Not every field applies to every platform: `reactionCount` is Facebook and LinkedIn,
`shareCount` is platform dependent, `cid` is the Bluesky content id needed to reply, and
`subreddit` is Reddit only. Absent fields are omitted from the response.

The account's token is refreshed before the call when it has expired. When the refresh
cannot recover it, the response is a 401 with code `TOKEN_EXPIRED` and the account has to
be reconnected.


## GET /v1/accounts/{accountId}/posts

**List posts published on the platform**

Returns the 25 most recent posts that exist on the platform for a connected account, read
live from the platform API. This covers everything on the account, including posts that
were never created through Zernio.

Use it to obtain the platform's own post id, which the analytics endpoints take as input.
On YouTube the returned `id` is the video ID that `GET /v1/analytics/youtube/daily-views`,
`/video-retention` and `/demographics` expect as `videoId`, so this endpoint is what backs
a video picker in your own UI.

Not every field applies to every platform: `reactionCount` is Facebook and LinkedIn,
`shareCount` is platform dependent, `cid` is the Bluesky content id needed to reply, and
`subreddit` is Reddit only. Absent fields are omitted from the response.

The account's token is refreshed before the call when it has expired. When the refresh
cannot recover it, the response is a 401 with code `TOKEN_EXPIRED` and the account has to
be reconnected.


### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: Posts list

**Response Body:**

- **status** `string`: No description - one of: success
- **posts** `array[object]`: 
  - **id** `string`: The platform's own post id (the video ID on YouTube)
  - **platform** `string`: No description
  - **message** `string`: Caption or title, empty string when the post has no text
  - **createdTime** `string` (date-time): No description
  - **permalink** `string`: Public URL of the post on the platform
  - **picture** `string`: Thumbnail or media URL
  - **mediaType** `string`: No description
  - **commentCount** `integer`: No description
  - **likeCount** `integer`: No description
  - **reactionCount** `integer`: Facebook and LinkedIn only
  - **shareCount** `integer`: No description
  - **cid** `string`: Bluesky content id, required to reply to the post
  - **subreddit** `string`: Reddit only
- **lastUpdated** `string` (date-time): No description

#### 400: Invalid accountId, platform does not support posts listing, or the account has no access token

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: X analytics capability not enabled for this account (code X_ANALYTICS_NOT_ENABLED)

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

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
