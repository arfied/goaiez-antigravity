# Related Schema Definitions

## AnalyticsSinglePostResponse

### Properties

- **postId** `string`: No description
- **latePostId** `string,null`: Original Zernio post ID if scheduled via Zernio
- **status** `string`: Overall post status. "partial" when some platforms published and others failed. - one of: published, failed, partial
- **content** `string`: No description
- **scheduledFor** `string`: No description
- **publishedAt** `string,null`: No description
- **analytics**: No description
- **platformAnalytics** `array`: No description
- **platform** `string`: No description
- **platformPostUrl** `string,null`: No description
- **isExternal** `boolean`: No description
- **syncStatus** `string`: Overall sync state across all platforms - one of: synced, pending, partial, unavailable
- **message** `string,null`: Human-readable status message for pending, partial, or failed states
- **thumbnailUrl** `string,null`: No description
- **mediaType** `string,null`: No description - one of: image, video, carousel, text
- **mediaItems** `array`: All media items for this post. Carousel posts contain one entry per slide.
- **mediaProductType** `string`: Instagram only: the platform media product type (e.g. FEED, REELS, STORY, AD). Absent when the platform did not report it.
- **isAiGenerated** `boolean`: Instagram only: whether Instagram labeled the media as AI-generated. Absent when the platform did not report it.
- **isSharedToFeed** `boolean`: Instagram reels only: whether the reel is also shared to the main feed. Absent when the platform did not report it.
- **mediaAudioType** `string`: Instagram only: audio type of the media (MUSIC or ORIGINAL_SOUND). Absent when the platform did not report it.

## AnalyticsListResponse

### Properties

- **overview**: No description
- **posts** `array`: No description
- **pagination**: No description
- **accounts** `array`: Connected accounts (followerCount and followersLastUpdated only included if user has analytics add-on)
- **hasAnalyticsAccess** `boolean`: Whether user has analytics add-on access

## PostAnalytics

### Properties

- **impressions** `integer`: No description
- **reach** `integer`: No description
- **likes** `integer`: No description
- **comments** `integer`: No description
- **shares** `integer`: No description
- **saves** `integer`: Number of saves/bookmarks (Instagram, Pinterest, X)
- **clicks** `integer`: No description
- **views** `integer`: No description
- **follows** `integer,null`: Instagram feed posts and stories only: organic accounts that started following from this post. Null on Instagram Reels and non-Reels video, where Meta does not expose this metric for the media. 0 for other platforms.
- **igReelsAvgWatchTime** `integer`: Instagram Reels only: average watch time per play, in milliseconds. 0 for non-Reels media and other platforms.
- **igReelsVideoViewTotalTime** `integer`: Instagram Reels only: total watch time including replays, in milliseconds. 0 for non-Reels media and other platforms.
- **reelsSkipRate** `number`: Instagram Reels only: percentage (0-100) of initial views that skipped the reel within its first 3 seconds, as reported by Meta. Meta labels the metric estimated and in development, so it can move between syncs. 0 for non-Reels media and other platforms. When a post is published to several accounts, the aggregate is weighted by views.
- **completionRate** `number`: TikTok accounts connected through the TikTok for Business app only: share of viewers who watched the video to the end, 0 to 1, as TikTok reports it (T+24-48h, only for posts active in the last 7 days). 0 for other platforms. When a post is published to several accounts, the aggregate is weighted by views.
- **profileViews** `integer`: TikTok accounts connected through the TikTok for Business app only: profile views from users who reached the profile through this post (T+24-48h). 0 for other platforms.
- **reposts** `integer`: Instagram accounts connected with Facebook Login only: reposts of the media by other users, minus deleted reposts, on feed posts, reels and stories. Meta does not expose this metric for accounts connected with Instagram Login, so those always report 0. 0 for other platforms, including Threads, where reposts are counted in shares instead.
- **videoDurationSeconds** `integer,null`: Video length in seconds. Currently Instagram Reels only; combine with igReelsAvgWatchTime (ms) to estimate retention. Null when unknown (other platforms, non-video media, or when Instagram does not expose the media URL, e.g. reels with copyrighted audio).
- **engagementRate** `number`: Percentage, rounded to 2 decimals: (likes + comments + shares + saves) / (impressions or reach or views) * 100. Clicks and follows are never counted. The denominator is the FIRST of impressions, reach, views that is non-zero, so it is not the same basis on every post: a post with impressions divides by impressions, one without falls back to reach, then to views. If you need a single consistent basis (e.g. interactions / reach), compute it from the raw fields above. The engagementRate on the LinkedIn account endpoints is a different formula.
- **lastUpdated** `string`: No description

## PlatformAnalytics

### Properties

- **platform** `string`: No description
- **status** `string`: No description - one of: published, failed
- **platformPostId** `string,null`: The native post ID on the platform (e.g. Instagram media ID, tweet ID)
- **accountId** `string`: No description
- **accountUsername** `string,null`: No description
- **analytics**: No description
- **syncStatus** `string`: Sync state of analytics for this platform - one of: synced, pending, unavailable
- **platformPostUrl** `string,null`: No description
- **errorMessage** `string,null`: Error details when status is failed

## ErrorResponse

Canonical error envelope. `error` is the human-readable message; `type`,
`code`, `param`, `platform`, and `platformError` are top-level siblings
for programmatic handling. For upstream platform failures (`type:
platform_error`), `platformError` carries the provider's raw payload
verbatim (for Meta: `error_subcode`, `error_user_title`, `error_user_msg`).


### Properties

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
