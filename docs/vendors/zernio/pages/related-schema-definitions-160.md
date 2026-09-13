# Related Schema Definitions

## ExternalPostSummary

A post synced from a platform (published directly on the platform, not
through Zernio). Returned by GET /v1/posts?source=external and
POST /v1/posts/sync-external. Analytics are exposed separately via
GET /v1/analytics?source=external.


### Properties

- **platform** `string`: Platform the post belongs to (e.g. instagram, youtube, tiktok)
- **platformPostId** `string`: The platform's own post/media/video id
- **platformPostUrl** `string`: Canonical URL (permalink) of the post on the platform
- **content** `string`: Post caption / text
- **publishedAt** `string`: When the post was published on the platform
- **mediaType** `string`: Media type (e.g. image, video, carousel)
- **thumbnailUrl** `string`: Thumbnail URL
- **mediaItems** `array`: Per-item media (for carousels / multi-media posts)
- **mediaProductType** `string`: Instagram only: the platform media product type (e.g. FEED, REELS, STORY, AD). Absent when the platform did not report it.
- **isAiGenerated** `boolean`: Instagram only: whether Instagram labeled the media as AI-generated. Absent when the platform did not report it.
- **isSharedToFeed** `boolean`: Instagram reels only: whether the reel is also shared to the main feed. Absent when the platform did not report it.
- **mediaAudioType** `string`: Instagram only: audio type of the media (MUSIC or ORIGINAL_SOUND). Absent when the platform did not report it.
- **analytics** `object`: Engagement + insights for the post. `likes` and `comments` are
available immediately after an on-demand sync (they come from the
platform listing). `reach`, `impressions`, `views` depend on the
platform's insights, which carry their own delay (e.g. ~24h on
Instagram) and read 0 until the platform makes them available.

  - **likes** `integer`: 
  - **comments** `integer`: 
  - **shares** `integer`: 
  - **saves** `integer`: 
  - **sends** `integer`: 
  - **clicks** `integer`: 
  - **views** `integer`: 
  - **reach** `integer`: 
  - **impressions** `integer`: 
  - **engagementRate** `number`: Percentage, rounded to 2 decimals. Same definition as PostAnalytics.engagementRate: (likes + comments + shares + saves) / (impressions or reach or views) * 100, where the denominator is the first of the three that is non-zero. Clicks and follows are never counted.
  - **lastUpdated** `string`: When these metrics were last refreshed

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
