# Get YouTube video retention curve API Reference

Returns the audience retention curve for a single YouTube video, plus the video's
duration for rendering the curve on a time axis. The curve has up to 100 points
(elapsedVideoTimeRatio 0.01-1.0) aggregated over the whole date range; YouTube does
not support per-day retention breakdowns.

audienceWatchRatio is the absolute share of viewers watching at that point in the
video and can exceed 1 (rewinds and looping, common on Shorts). relativeRetentionPerformance
compares against videos of similar length (0 = worst, 0.5 = median, 1 = best).
YouTube returns an empty curve for videos with very few views or before analytics
processing completes (2-3 day delay).

Requires yt-analytics.readonly scope (re-authorization may be needed).


## GET /v1/analytics/youtube/video-retention

**Get YouTube video retention curve**

Returns the audience retention curve for a single YouTube video, plus the video's
duration for rendering the curve on a time axis. The curve has up to 100 points
(elapsedVideoTimeRatio 0.01-1.0) aggregated over the whole date range; YouTube does
not support per-day retention breakdowns.

audienceWatchRatio is the absolute share of viewers watching at that point in the
video and can exceed 1 (rewinds and looping, common on Shorts). relativeRetentionPerformance
compares against videos of similar length (0 = worst, 0.5 = median, 1 = best).
YouTube returns an empty curve for videos with very few views or before analytics
processing completes (2-3 day delay).

Requires yt-analytics.readonly scope (re-authorization may be needed).


### Parameters

- **videoId** (required) in query: The YouTube video ID (e.g., "dQw4w9WgXcQ")
- **accountId** (required) in query: The Zernio account ID for the YouTube account
- **startDate** (optional) in query: Start date (YYYY-MM-DD). Defaults to the video's publish date (lifetime curve).
- **endDate** (optional) in query: End date (YYYY-MM-DD). Defaults to 3 days ago, the newest fully finalized day
(YouTube finalizes analytics with a ~3-day delay). An explicit endDate is honored
up to today: days inside the delay window are provisional and may still be revised
by YouTube (see provisionalSince in the response).


### Responses

#### 200: Audience retention curve

**Response Body:**

- **success** `boolean`: No description (example: true)
- **accountId** `string`: The Zernio account ID for the YouTube account
- **videoId** `string`: The YouTube video ID
- **title** `string,null`: Video title
- **publishedAt** `string,null` (date-time): When the video was published on YouTube
- **durationSeconds** `integer,null`: Video length in seconds (from YouTube contentDetails.duration)
- **dateRange** `object`: 
  - **startDate** `string` (date): No description
  - **endDate** `string` (date): No description
- **provisionalSince** `string` (date): Present only when the range reaches into YouTube's ~3-day processing window: the first date whose numbers are provisional and may still be revised by YouTube.
- **retentionCurve** `array[object]`: Up to 100 points covering the video timeline, aggregated over the date range. Can be empty when YouTube has no retention data for the video in the given range.
  - **elapsedVideoTimeRatio** `number`: Position in the video as a ratio (0.01-1.0, exclusive end of each interval)
  - **audienceWatchRatio** `number`: Absolute share of viewers watching at this point. Can exceed 1 (rewinds/looping, common on Shorts).
  - **relativeRetentionPerformance** `number`: Retention vs videos of similar length (0 = worst, 0.5 = median, 1 = best)
  - **startedWatching** `integer`: Viewers who started watching in this segment. 0 when YouTube has no segment-level data for the video.
  - **stoppedWatching** `integer`: Viewers who stopped watching in this segment. 0 when YouTube has no segment-level data for the video.
  - **totalSegmentImpressions** `integer`: Total views of this segment, including rewatches
- **note** `string`: Present only when the curve is empty, explaining why
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

#### 404: Video not found, or it does not belong to this YouTube channel

**Response Body:**

- **error** `string`: No description (example: "Video not found on this YouTube channel")
- **type** `string`: No description (example: "not_found")
- **code** `string`: No description (example: "video_not_found")
- **param** `string`: No description (example: "videoId")

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
