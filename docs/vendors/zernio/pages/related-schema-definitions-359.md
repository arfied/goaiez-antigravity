# Related Schema Definitions

## PostGetResponse

### Properties

- **post**: No description

## Post

### Properties

- **_id** `string`: No description
- **userId**: No description
- **title** `string`: Stored on the post for reference/display only. This field is NOT used as the video title when publishing. To set a YouTube video title, use platformSpecificData.title on the youtube platform target (falls back to the first line of content when omitted).
- **content** `string`: No description
- **mediaItems** `array`: No description
- **platforms** `array`: No description
- **scheduledFor** `string`: No description
- **timezone** `string`: No description
- **status** `string`: `cancelled` is set by DELETE /v1/posts/{postId}/unpublish once every platform entry has been removed from its platform (a post with published entries left becomes `partial`); cancelled posts can be edited and rescheduled like drafts. - one of: draft, scheduled, publishing, published, partial, failed, cancelled
- **tags** `array`: YouTube constraints: each tag max 100 chars, combined max 500 chars, duplicates removed.
- **hashtags** `array`: Stored for reference only. Hashtags are NOT automatically appended to the caption when publishing. Include hashtags directly in the content field (platforms like Instagram only support hashtags as caption text). For YouTube keywords, use the tags field instead.
- **mentions** `array`: Stored for reference only. This field does NOT automatically create @mentions when publishing. For LinkedIn @mentions, use the /v1/accounts/{accountId}/linkedin-mentions endpoint to resolve profile URLs to URNs, then embed the returned mentionFormat directly in the post content field.
- **visibility** `string`: No description - one of: public, private, unlisted
- **metadata** `object`: No description
- **recycling**: No description
- **recycledFromPostId** `string`: ID of the original post if this post was created via recycling
- **queuedFromProfile** `string`: Profile ID if the post was scheduled via the queue
- **queueId** `string`: Queue ID if the post was scheduled via a specific queue
- **createdAt** `string`: No description
- **updatedAt** `string`: No description

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

## TikTokPlatformData

Photo carousels up to 35 images. Video titles up to 2200 chars, photo titles truncated to 90 chars.
privacyLevel must match creator_info options. Both camelCase and snake_case accepted.

Creator Inbox (draft mode): Set draft: true to send content to the TikTok Creator Inbox
instead of publishing immediately. The creator receives an inbox notification and completes
the post using TikTok's editing flow. This maps to TikTok's post_mode: "MEDIA_UPLOAD" internally.

Important: The field publish_type is NOT supported. Use draft: true for Creator Inbox flow.

Photo drafts use the /v2/post/publish/content/init/ endpoint with post_mode: "MEDIA_UPLOAD".
Video drafts use the dedicated /v2/post/publish/inbox/video/init/ endpoint.

When draft: true, the video.upload scope is required. When draft is false or omitted
(direct post), the video.publish scope is required. For Creator Inbox, TikTok app version
must be 31.8 or higher.


### Properties

- **draft** `boolean`: When true, sends the post to the TikTok Creator Inbox as a draft instead of publishing
immediately. The creator receives an inbox notification to complete posting via TikTok's
editing flow. Maps to TikTok API post_mode: "MEDIA_UPLOAD" (photos) or the dedicated
inbox endpoint (videos). When false or omitted, publishes directly via post_mode: "DIRECT_POST".
Note: publish_type is not a supported field. Use this field instead.

- **privacyLevel** `string`: One of the values returned by the TikTok creator info API for the account. Accounts connected through the TikTok for Business app publish videos as public only: a non-public value on a video post is rejected at creation unless draft is true (photo posts keep every level).
- **allowComment** `boolean`: Allow comments on the post
- **allowDuet** `boolean`: Allow duets (required for video posts)
- **allowStitch** `boolean`: Allow stitches (required for video posts)
- **commercialContentType** `string`: Type of commercial content disclosure. Sufficient on its own: "brand_organic"
("Your Brand") implies isBrandOrganicPost and "brand_content" ("Branded Content",
paid partnership) implies brandPartnerPromote, so you don't need to send the
boolean flags separately. Branded content cannot be posted with privacyLevel
SELF_ONLY.
 - one of: none, brand_organic, brand_content
- **brandPartnerPromote** `boolean`: Whether the post promotes a brand partner (branded content / paid partnership).
Only needed to disclose BOTH types at once (set it alongside
commercialContentType "brand_organic"), or to override the value implied by
commercialContentType.

- **isBrandOrganicPost** `boolean`: Whether the post promotes the creator's own brand (brand organic). Only needed
to disclose BOTH types at once (set it alongside commercialContentType
"brand_content"), or to override the value implied by commercialContentType.

- **contentPreviewConfirmed** `boolean`: User has confirmed they previewed the content
- **expressConsentGiven** `boolean`: User has given express consent for posting
- **mediaType** `string`: Optional override. Defaults based on provided media items. - one of: video, photo
- **videoCoverTimestampMs** `integer`: Optional for video posts. Timestamp in milliseconds to select which frame to use as thumbnail (defaults to 1000ms/1 second). Ignored when videoCoverImageUrl is provided. (min: 0)
- **videoCoverImageUrl** `string`: Optional for video posts. URL of a custom thumbnail image (JPG, PNG, or WebP, max 20MB). Any downloadable URL works: we rehost it ourselves. The image is stitched as a single frame at the start of the video to serve as the cover. Accounts connected through the TikTok for Business app hand it to TikTok as the cover instead, with no stitching, falling back to videoCoverTimestampMs without it. Overrides videoCoverTimestampMs when provided.
- **photoCoverIndex** `integer`: Optional for photo carousels. Index of image to use as cover, 0-based (defaults to 0/first image). (min: 0)
- **autoAddMusic** `boolean`: When true, TikTok may add recommended music (photos only)
- **videoMadeWithAi** `boolean`: Set true to disclose AI-generated content. Accounts connected through the TikTok for Business app carry the disclosure on video posts only: the business photo endpoint has no AI disclosure field, so true on a direct photo post is rejected at creation rather than published undisclosed. Send draft true to publish such a photo post and set the disclosure in the TikTok app.
- **description** `string`: Optional long-form caption for photo posts (max 4000 chars). Recommended when content exceeds 90 chars, as photo titles are auto-truncated. Falls back to the post content when omitted. (max: 4000)

## FacebookSettings

Facebook options that must be nested under platformSpecificData.facebookSettings, or sent at the request root as facebookSettings. The remaining Facebook options sit directly on platformSpecificData, see FacebookPlatformData.


### Properties

- **draft** `boolean`: When true, creates the post as a draft in Facebook Publishing Tools instead of publishing immediately. Supported for feed posts (text, link, image, video) and reels. Not supported for stories. Drafts expire after ~30 days. (default: false)
- **carouselCards** `array`: Renders the post as a multi-link carousel (organic Page post). When set, mediaItems must be provided with the same length and all items must be images (no videos). Each cards[i] adds the click-through link and headline for the image at mediaItems[i]. Mutually exclusive with contentType=story|reel. Facebook display truncates name at ~35 chars and description at ~30 chars; longer strings are accepted but get truncated on render.

- **carouselLink** `string`: Optional top-level "See more" destination shown on the carousel end card. Defaults to the first card's link when omitted. Only used together with carouselCards.

- **textFormatPresetId** `string`: Facebook-defined preset ID that renders the post as large text on a colored background (Graph `text_format_preset_id`). Supply the raw numeric ID from Meta; we do not publish a catalog of presets and Facebook may change the available set. Pages only (ignored on personal profiles and groups) and text-only feed posts only: the request is rejected with 400 when mediaItems or carouselCards are present, when contentType is story or reel, or when content is empty. An attachment makes Facebook drop the background silently, so those are rejected up front. Length is NOT rejected: Facebook's composer stops offering a background at around 130 characters, but Meta documents no API limit, so longer content publishes and returns a warning instead. A URL detected in the content is NOT attached as a link preview while a preset is set, because a link attachment also makes Facebook drop the background.


## PostUpdateResponse

### Properties

- **message** `string`: No description
- **post**: No description
- **warnings** `array`: No description

## PostPublishIncompleteResponse

Body of the 207 returned by createPost and updatePost when the post was saved but the inline publish did not fully succeed. Read `post.status` to tell the three outcomes apart.

### Properties

- **post**: No description
- **message** `string`: Human-readable summary of the publish outcome.
- **error** `string`: Present when no platform published. Absent on a partial success. Informational only; the per-platform detail is in `platformResults` and in `post.platforms[]`.
- **platformResults** `array`: Per-platform outcome of the publish attempt. Omitted when the attempt aborted before producing per-platform results (for example the post was already being processed); read `post.platforms[]` in that case.
- **warnings** `array`: Advisory notices about the post that was still created. Absent when there are none.

## PostDeleteResponse

### Properties

- **message** `string`: No description

---
