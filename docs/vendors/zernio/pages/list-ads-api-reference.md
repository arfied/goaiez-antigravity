# List ads API Reference

Returns a paginated list of ads with metrics computed over an optional date range.
Use source=all to include externally-synced ads from platform ad managers.
If no date range is provided, defaults to the last 90 days. Date range is capped at 730 days max.

To find the Zernio ad behind a comment you see in Meta Business Manager, filter by
platformAdId (the Meta ad ID), effectiveObjectStoryId (Facebook), or
effectiveInstagramMediaId (Instagram). Those are the post/media the ad's engagement
lives on, and are also returned on each ad's `creative` object. Then call
GET /v1/ads/{adId}/comments with the returned ad id.


## GET /v1/ads

**List ads**

Returns a paginated list of ads with metrics computed over an optional date range.
Use source=all to include externally-synced ads from platform ad managers.
If no date range is provided, defaults to the last 90 days. Date range is capped at 730 days max.

To find the Zernio ad behind a comment you see in Meta Business Manager, filter by
platformAdId (the Meta ad ID), effectiveObjectStoryId (Facebook), or
effectiveInstagramMediaId (Instagram). Those are the post/media the ad's engagement
lives on, and are also returned on each ad's `creative` object. Then call
GET /v1/ads/{adId}/comments with the returned ad id.


### Parameters

- **undefined** (optional): No description
- **limit** (optional) in query: No description
- **source** (optional) in query: all (default) = Zernio-created + platform-discovered ads. zernio = restrict to Zernio-created only.
- **status** (optional) in query: No description
- **platform** (optional) in query: No description
- **accountId** (optional) in query: Account ID
- **adAccountId** (optional) in query: Platform ad account ID (e.g. act_123 for Meta). Mirrors the same filter on /v1/ads/campaigns and /v1/ads/tree.
- **pageId** (optional) in query: Meta only: Facebook Page ID. Returns only ads whose creative is backed by this Page (a Meta ad account serves ads for every Page in the Business Manager). Matches each ad's `creative.pageId`; ads with no page signal (rare IG-only creatives) never match. Mirrors the same filter on /v1/ads/campaigns and /v1/ads/tree.
- **profileId** (optional) in query: Profile ID
- **campaignId** (optional) in query: Platform campaign ID (filter ads within a campaign)
- **adSetId** (optional) in query: Platform ad set ID (filter ads within an ad set, the /{adset_id}/ads read of an adset-centric dashboard).
- **platformAdId** (optional) in query: Meta ad ID. Returns the ad with this platform-side ad ID.
- **effectiveObjectStoryId** (optional) in query: Facebook `{pageId}_{postId}` of the post the ad's engagement lives on (Meta `effective_object_story_id`). Use to map a Business-Manager-visible post back to the Zernio ad.
- **effectiveInstagramMediaId** (optional) in query: Instagram media ID of the boosted post (Meta `effective_instagram_media_id`). Use to map a Business-Manager-visible IG post back to the Zernio ad.
- **fromDate** (optional) in query: Start of metrics date range (YYYY-MM-DD). Defaults to 90 days ago.
- **toDate** (optional) in query: End of metrics date range (YYYY-MM-DD). Defaults to today. Max 730-day range.

### Responses

#### 200: Paginated ads

**Response Body:**

- **ads** `array[Ad]`: 
- **pagination**: `Pagination` - See schema definition
- **backfillPending** `boolean`: Present and true while historical data is being backfilled.

#### 202: Historical data is incomplete and backfill remains pending.

**Response Body:**

- **ads** `array[Ad]`: 
- **pagination**: `Pagination` - See schema definition
- **backfillPending** `boolean`: Present and true while historical data is being backfilled.
- **backfillPending** (required) `boolean`: Always true on this response. Part of the requested range is still being backfilled; retry until the request returns 200.

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

#### 403: Ads access required. Legacy plans need the Ads add-on; included by default on usage-based plans.

---
