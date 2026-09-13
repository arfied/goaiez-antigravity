# Get YouTube demographics API Reference

Returns audience demographic insights for a YouTube channel, broken down by age, gender, and/or country.
Pass videoId to get the audience profile of a single video instead of the whole channel.
Age and gender values are viewer percentages (0-100). Country values are view counts.
Data is based on signed-in viewers only, with a 2-3 day delay. YouTube suppresses demographics
for videos with too few signed-in views, so low-traffic videos can return empty breakdowns.
Requires the Analytics add-on.


## GET /v1/analytics/youtube/demographics

**Get YouTube demographics**

Returns audience demographic insights for a YouTube channel, broken down by age, gender, and/or country.
Pass videoId to get the audience profile of a single video instead of the whole channel.
Age and gender values are viewer percentages (0-100). Country values are view counts.
Data is based on signed-in viewers only, with a 2-3 day delay. YouTube suppresses demographics
for videos with too few signed-in views, so low-traffic videos can return empty breakdowns.
Requires the Analytics add-on.


### Parameters

- **accountId** (required) in query: The Zernio SocialAccount ID for the YouTube account
- **videoId** (optional) in query: YouTube video ID. When provided, demographics are scoped to this single video
(must belong to the connected channel; otherwise 404 video_not_found).

- **breakdown** (optional) in query: Comma-separated list of demographic dimensions: age, gender, country.
Defaults to all three if omitted.

- **startDate** (optional) in query: Start date in YYYY-MM-DD format. Defaults to 90 days ago, or to the video's
publish date (lifetime) when videoId is provided.

- **endDate** (optional) in query: End date (YYYY-MM-DD). Defaults to 3 days ago, the newest fully finalized day
(YouTube finalizes analytics with a ~3-day delay). An explicit endDate is honored
up to today: days inside the delay window are provisional and may still be revised
by YouTube (see provisionalSince in the response).


### Responses

#### 200: Demographic insights data

**Response Body:**

- **success** `boolean`: No description (example: true)
- **accountId** `string`: The Zernio SocialAccount ID
- **platform** `string`: No description (example: "youtube")
- **videoId** `string`: Present only when demographics are scoped to a single video
- **title** `string,null`: Video title (video mode only)
- **publishedAt** `string,null` (date-time): Video publish date (video mode only)
- **demographics** `object`: Object keyed by breakdown dimension (age, gender, country)
- **dateRange** `object`: 
  - **startDate** `string`: No description (example: "2026-01-01")
  - **endDate** `string`: No description (example: "2026-03-31")
- **provisionalSince** `string` (date): Present only when the range reaches into YouTube's ~3-day processing window: the first date whose numbers are provisional and may still be revised by YouTube.
- **note** `string`: No description (example: "Age/gender values are viewer percentages (0-100). Country values are view counts. Data based on signed-in viewers only, with 2-3 day delay.")

#### 400: Bad request (invalid parameters or not a YouTube account)

**Response Body:**

- **error** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

**Response Body:**

- **error** `string`: No description (example: "Analytics add-on required")
- **code** `string`: No description (example: "analytics_addon_required")

#### 403: Access denied to this account

**Response Body:**

- **error** `string`: No description (example: "Access denied to this account")

#### 404: Account not found, or the video does not exist / does not belong to this YouTube channel

**Response Body:**

- **error** `string`: No description (example: "Account not found")

#### 412: YouTube Analytics scope not granted

**Response Body:**

- **success** `boolean`: No description (example: false)
- **error** `string`: No description
- **code** `string`: No description (example: "youtube_analytics_scope_missing")
- **scopeStatus** `object`: 
  - **hasAnalyticsScope** `boolean`: No description (example: false)
  - **requiresReauthorization** `boolean`: No description (example: true)
  - **reauthorizeUrl** `string`: No description

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
