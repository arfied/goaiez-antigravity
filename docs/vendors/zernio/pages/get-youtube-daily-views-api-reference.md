# Get YouTube daily views API Reference

Returns daily view counts for a YouTube video including views, watch time, and subscriber changes.
Requires yt-analytics.readonly scope (re-authorization may be needed). YouTube finalizes analytics
with a ~3-day delay; by default only finalized days are returned, and an explicit endDate can reach
into the delay window (see the endDate parameter). Max 90 days, defaults to last 30 days.


## GET /v1/analytics/youtube/daily-views

**Get YouTube daily views**

Returns daily view counts for a YouTube video including views, watch time, and subscriber changes.
Requires yt-analytics.readonly scope (re-authorization may be needed). YouTube finalizes analytics
with a ~3-day delay; by default only finalized days are returned, and an explicit endDate can reach
into the delay window (see the endDate parameter). Max 90 days, defaults to last 30 days.


### Parameters

- **videoId** (required) in query: The YouTube video ID (e.g., "dQw4w9WgXcQ")
- **accountId** (required) in query: The Zernio account ID for the YouTube account
- **startDate** (optional) in query: Start date (YYYY-MM-DD). Defaults to 30 days ago.
- **endDate** (optional) in query: End date (YYYY-MM-DD). Defaults to 3 days ago, the newest fully finalized day
(YouTube finalizes analytics with a ~3-day delay). An explicit endDate is honored
up to today: days inside the delay window are provisional and may still be revised
by YouTube (see provisionalSince in the response), and days YouTube has not
processed yet are omitted from dailyViews.


### Responses

#### 200: Daily views breakdown

**Response Body:**

- **success** `boolean`: No description (example: true)
- **videoId** `string`: The YouTube video ID
- **durationSeconds** `integer,null`: Video length in seconds (from YouTube contentDetails.duration)
- **dateRange** `object`: 
  - **startDate** `string` (date): No description
  - **endDate** `string` (date): No description
- **provisionalSince** `string` (date): Present only when the range reaches into YouTube's ~3-day processing window: the first date whose numbers are provisional and may still be revised by YouTube.
- **totalViews** `integer`: Sum of views across all days in the range
- **dailyViews** `array[object]`: 
  - **date** `string` (date): No description
  - **views** `integer`: No description
  - **estimatedMinutesWatched** `number`: No description
  - **averageViewDuration** `number`: Average view duration in seconds
  - **averageViewPercentage** `number`: Average percentage of the video watched per view. Can exceed 100 on Shorts (looping rewatches), so do not clamp it client-side.
  - **subscribersGained** `integer`: No description
  - **subscribersLost** `integer`: No description
  - **likes** `integer`: No description
  - **comments** `integer`: No description
  - **shares** `integer`: No description
- **lastSyncedAt** `string,null` (date-time): When the data was last synced from YouTube
- **scopeStatus** `object`: 
  - **hasAnalyticsScope** `boolean`: No description

#### 400: Bad request (missing or invalid parameters)

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

#### 412: Missing YouTube Analytics scope

**Response Body:**

- **success** `boolean`: No description (example: false)
- **error** `string`: No description (example: "To access daily video analytics, please reconnect your YouTube account to grant the required permissions.")
- **code** `string`: No description (example: "youtube_analytics_scope_missing")
- **scopeStatus** `object`: 
  - **hasAnalyticsScope** `boolean`: No description (example: false)
  - **requiresReauthorization** `boolean`: No description (example: true)
  - **reauthorizeUrl** `string` (uri): URL to redirect user for reauthorization

#### 500: Internal server error

**Response Body:**

- **success** `boolean`: No description (example: false)
- **error** `string`: No description

---
