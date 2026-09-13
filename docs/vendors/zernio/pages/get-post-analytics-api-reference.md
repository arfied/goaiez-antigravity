# Get post analytics API Reference

Returns analytics for posts. With postId, returns a single post. Without it, returns a paginated list with overview stats.
Accepts both Zernio Post IDs and External Post IDs (auto-resolved). fromDate defaults to 90 days ago if omitted, max range 366 days.
Single post lookups may return 202 (sync pending) or 424 (all platforms failed). For follower stats, use /v1/accounts/follower-stats.

LinkedIn personal accounts: Analytics are only available for posts published through Zernio. LinkedIn's API only returns metrics for posts authored by the authenticated user. Organization/company page analytics work for all posts.


## GET /v1/analytics

**Get post analytics**

Returns analytics for posts. With postId, returns a single post. Without it, returns a paginated list with overview stats.
Accepts both Zernio Post IDs and External Post IDs (auto-resolved). fromDate defaults to 90 days ago if omitted, max range 366 days.
Single post lookups may return 202 (sync pending) or 424 (all platforms failed). For follower stats, use /v1/accounts/follower-stats.

LinkedIn personal accounts: Analytics are only available for posts published through Zernio. LinkedIn's API only returns metrics for posts authored by the authenticated user. Organization/company page analytics work for all posts.


### Parameters

- **postId** (optional) in query: Returns analytics for a single post. Accepts both Zernio Post IDs and External Post IDs. Zernio IDs are auto-resolved to External Post analytics.
- **platform** (optional) in query: Filter by platform (default "all")
- **profileId** (optional) in query: Filter by profile ID (default "all")
- **accountId** (optional) in query: Filter by account ID
- **source** (optional) in query: Filter by post source: late (posted via Zernio API), external (synced from platform), all (default)
- **fromDate** (optional) in query: Inclusive lower bound (YYYY-MM-DD). Defaults to 90 days ago if omitted. Max range is 366 days.
- **toDate** (optional) in query: Inclusive upper bound (YYYY-MM-DD). Defaults to today if omitted.
- **limit** (optional) in query: Page size (default 50)
- **page** (optional) in query: Page number (default 1)
- **sortBy** (optional) in query: Sort by date, engagement, or a specific metric. Platform-specific metrics (follows, reposts, reels_skip_rate, ig_reels_*, completion_rate, profile_views) sort a null value as 0.
- **order** (optional) in query: Sort order

### Responses

#### 200: Analytics result

**Response Body:**

*One of the following:*
- `AnalyticsSinglePostResponse`
- `AnalyticsListResponse`

#### 202: Analytics are being synced from the platform (single post lookup only). The response body matches AnalyticsSinglePostResponse with syncStatus "pending" and a message.

**Response Body:**

- **postId** `string`: No description
- **latePostId** `string,null`: Original Zernio post ID if scheduled via Zernio
- **status** `string`: Overall post status. "partial" when some platforms published and others failed. - one of: published, failed, partial
- **content** `string`: No description
- **scheduledFor** `string` (date-time): No description
- **publishedAt** `string,null` (date-time): No description
- **analytics**: `PostAnalytics` - See schema definition
- **platformAnalytics** `array[PlatformAnalytics]`: 
- **platform** `string`: No description
- **platformPostUrl** `string,null` (uri): No description
- **isExternal** `boolean`: No description
- **syncStatus** `string`: Overall sync state across all platforms - one of: synced, pending, partial, unavailable
- **message** `string,null`: Human-readable status message for pending, partial, or failed states
- **thumbnailUrl** `string,null` (uri): No description
- **mediaType** `string,null`: No description - one of: image, video, carousel, text
- **mediaItems** `array[object]`: All media items for this post. Carousel posts contain one entry per slide.
  - **type** `string`: No description - one of: image, video
  - **url** `string,null` (uri): 'Direct URL to the media file. Null when the platform withholds it: check mediaStatus before downloading. Instagram omits the video file for Reels it flags as containing copyrighted material (its docs name audio as the usual cause), so type stays "video" while the file is permanently unreachable.'
  - **thumbnail** `string,null` (uri): Thumbnail URL (same as url for images). Still present when url is null.
  - **altText** `string`: Accessibility alt text set on the media, when present.
  - **mediaStatus** `string`: unavailable means the media file could not be retrieved (url is null or, for LinkedIn videos, a cover image standing in for the file). available or absent means the file is available at url (older synced items omit the field). - one of: available, unavailable
  - **unavailableReason** `string`: Why the file is missing. platform_withheld means the platform declined to return it and retrying will not help. - one of: platform_withheld
- **mediaProductType** `string`: Instagram only: the platform media product type (e.g. FEED, REELS, STORY, AD). Absent when the platform did not report it.
- **isAiGenerated** `boolean`: Instagram only: whether Instagram labeled the media as AI-generated. Absent when the platform did not report it.
- **isSharedToFeed** `boolean`: Instagram reels only: whether the reel is also shared to the main feed. Absent when the platform did not report it.
- **mediaAudioType** `string`: Instagram only: audio type of the media (MUSIC or ORIGINAL_SOUND). Absent when the platform did not report it.

#### 400: Validation error

**Response Body:**

- **error** `string`: No description (example: "Invalid query parameters")
- **details** `object`: Detailed validation errors

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

**Response Body:**

- **error** `string`: No description (example: "Analytics add-on required")
- **code** `string`: No description (example: "analytics_addon_required")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 424: Post failed to publish on all platforms. Analytics are unavailable. (single post lookup only)

**Response Body:**

- **postId** `string`: No description
- **latePostId** `string,null`: Original Zernio post ID if scheduled via Zernio
- **status** `string`: Overall post status. "partial" when some platforms published and others failed. - one of: published, failed, partial
- **content** `string`: No description
- **scheduledFor** `string` (date-time): No description
- **publishedAt** `string,null` (date-time): No description
- **analytics**: `PostAnalytics` - See schema definition
- **platformAnalytics** `array[PlatformAnalytics]`: 
- **platform** `string`: No description
- **platformPostUrl** `string,null` (uri): No description
- **isExternal** `boolean`: No description
- **syncStatus** `string`: Overall sync state across all platforms - one of: synced, pending, partial, unavailable
- **message** `string,null`: Human-readable status message for pending, partial, or failed states
- **thumbnailUrl** `string,null` (uri): No description
- **mediaType** `string,null`: No description - one of: image, video, carousel, text
- **mediaItems** `array[object]`: All media items for this post. Carousel posts contain one entry per slide.
  - **type** `string`: No description - one of: image, video
  - **url** `string,null` (uri): 'Direct URL to the media file. Null when the platform withholds it: check mediaStatus before downloading. Instagram omits the video file for Reels it flags as containing copyrighted material (its docs name audio as the usual cause), so type stays "video" while the file is permanently unreachable.'
  - **thumbnail** `string,null` (uri): Thumbnail URL (same as url for images). Still present when url is null.
  - **altText** `string`: Accessibility alt text set on the media, when present.
  - **mediaStatus** `string`: unavailable means the media file could not be retrieved (url is null or, for LinkedIn videos, a cover image standing in for the file). available or absent means the file is available at url (older synced items omit the field). - one of: available, unavailable
  - **unavailableReason** `string`: Why the file is missing. platform_withheld means the platform declined to return it and retrying will not help. - one of: platform_withheld
- **mediaProductType** `string`: Instagram only: the platform media product type (e.g. FEED, REELS, STORY, AD). Absent when the platform did not report it.
- **isAiGenerated** `boolean`: Instagram only: whether Instagram labeled the media as AI-generated. Absent when the platform did not report it.
- **isSharedToFeed** `boolean`: Instagram reels only: whether the reel is also shared to the main feed. Absent when the platform did not report it.
- **mediaAudioType** `string`: Instagram only: audio type of the media (MUSIC or ORIGINAL_SOUND). Absent when the platform did not report it.

#### 500: Internal server error

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
