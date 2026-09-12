# Get ad details API Reference

Returns an ad with its creative, targeting, status, and performance metrics.
Google Search ads include current creative.headlines, creative.descriptions and creative.finalUrls,
preserving pinnedField. Top-level cachedAt and stale report cache freshness. Google mutations invalidate this read.
RSA enrichment requires a stored advertisingChannelType of SEARCH. Ads with an unknown or other channel
return their stored details without a Google read. If RSA enrichment fails, the stored ad is returned
with HTTP 200 and without cache metadata.

The `{adId}` path segment accepts any identifier dialect Zernio indexes for the ad:
- the Zernio internal `_id` (24-char hex)
- Meta's numeric `platformAdId` (the value shipped in `comment.received` webhooks as `comment.ad.id`)
- the creative's `effective_object_story_id` (`{pageId}_{postId}` shape, Facebook side)
- the creative's `effective_instagram_media_id` (Instagram side)

Any of the four resolve to the same ad. Caller doesn't need a translation step.
`creative.creativeFeatures` holds the stored requested settings, which do not confirm
platform application.


## GET /v1/ads/{adId}

**Get ad details**

Returns an ad with its creative, targeting, status, and performance metrics.
Google Search ads include current creative.headlines, creative.descriptions and creative.finalUrls,
preserving pinnedField. Top-level cachedAt and stale report cache freshness. Google mutations invalidate this read.
RSA enrichment requires a stored advertisingChannelType of SEARCH. Ads with an unknown or other channel
return their stored details without a Google read. If RSA enrichment fails, the stored ad is returned
with HTTP 200 and without cache metadata.

The `{adId}` path segment accepts any identifier dialect Zernio indexes for the ad:
- the Zernio internal `_id` (24-char hex)
- Meta's numeric `platformAdId` (the value shipped in `comment.received` webhooks as `comment.ad.id`)
- the creative's `effective_object_story_id` (`{pageId}_{postId}` shape, Facebook side)
- the creative's `effective_instagram_media_id` (Instagram side)

Any of the four resolve to the same ad. Caller doesn't need a translation step.
`creative.creativeFeatures` holds the stored requested settings, which do not confirm
platform application.


### Parameters

- **adId** (required) in path: Zernio `_id` (hex), Meta `platformAdId` (numeric), or one of the creative's effective story/media IDs. See description for details.


### Responses

#### 200: Ad details

**Response Body:**

- **ad**: `Ad` - See schema definition
- **cachedAt** `string,null` (date-time): Google RSA details cache timestamp.
- **stale** `boolean`: Whether Google RSA details use the last successful cached response.

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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## PUT /v1/ads/{adId}

**Update ad**

Patch one or more fields on an ad. Status, budget, targeting, and creative changes
are propagated to the platform.

Per-platform support:
- **Meta** (Facebook + Instagram): all fields supported.
- **TikTok**: status, budget, targeting (via `/v2/adgroup/update/`), and creative
  (via `/v2/ad/update/` patch-style: `headline` is ignored, `body` becomes `ad_text`).
- **Google**: status, budget, KEYWORD edits via `targeting.keywords` /
  `targeting.negativeKeywords`, DEVICE bid adjustments via `targeting.devices`,
  LOCATION edits via `targeting.locations` (or the equivalent top-level
  `targeting.countries` / `regions` / `cities` / `zips` / `metros`), and LANGUAGE
  edits via `targeting.languages`.
  Each list you send becomes the FULL new set of its kind (criteria not in the
  list are removed, except devices, which Google cannot remove and which are
  switched off with a bid modifier of 0 instead); a kind left out is untouched.
  Any other `targeting` field
  returns 400: Google cannot mutate it post-create without recreating
  the campaign. Creative edits are dispatched on the ad's `advertisingChannelType`,
  and every supported field replaces a whole set; a field you omit is preserved.
  - **Search**: top-level `headlines`, `descriptions` and `finalUrls`. Use 3-15 headlines
    (1-30 characters) and 2-4 descriptions (1-90 characters). Omit an asset to remove it;
    omit pinnedField on an included asset to unpin it. Updates do not pad or truncate text.
    The legacy creative fields remain unsupported.
  - **Display**: top-level `headlines` (1-5, no pinnedField, display ads have no pinned
    positions), `descriptions` (1-5) and `finalUrls`, plus `creative.longHeadline`,
    `creative.businessName`, `creative.imageUrl` (the landscape marketing image) and
    `creative.squareImageUrl`. Each image URL is uploaded as a new Google asset and the ad
    is pointed at it; Google assets are immutable, so the previous asset stays in the
    account's asset library.
  - **Performance Max**: top-level `assetGroup`, which swaps asset roles on the ad's asset
    group. The other creative fields return 422 for this channel, and `assetGroup` returns
    422 on any other channel.
- **LinkedIn**: status, budget, targeting (countries or regions, excludedLocations (countries),
  the B2B facets, and audience segments; applied to the LinkedIn Campaign via
  PARTIAL_UPDATE, and REPLACES the campaign's entire targetingCriteria, not a merge),
  and creative (uploads new media, creates a replacement inline creative on the same
  campaign, pauses the old one).
- **Pinterest / X / OpenAI Ads**: status + budget only. Sending
  `targeting` or `creative` returns 501 with code `unsupported_platform_operation`.
  OpenAI Ads budget is lifetime-only (see `budget.type` below).

**Google location and language replacement:** locations, languages and devices are
campaign-level criteria on Google, so these edits apply to every ad group and ad in
the ad's campaign. Send the complete list you want to keep. Zernio diffs it against
the campaign's live criteria and sends the removes and the creates in ONE
`googleAds:mutate`, so the campaign is never left with a half-applied set; criteria
already in the list keep their criterion ID and history. Excluded (negative)
locations are left untouched. Two cases are refused rather than applied: an empty
location list returns 400 (a Google campaign with no location criteria targets every
country, which is never what "remove my locations" means, so omit the field instead),
and radius targeting (`customLocations`) returns 422 because it is a separate Google
criterion type that this replacement neither creates nor removes. Send either
`targeting.locations` or the top-level geo fields, not both: mixing them returns 400.

**Google keyword replacement:** These edits affect the ad's entire ad group,
including sibling ads. Positive (`targeting.keywords`) and negative
(`targeting.negativeKeywords`) sets are independent: omit a field to leave
that set unchanged, or send `[]` to remove every keyword of that kind.

Zernio compares each supplied set with Google's live criteria by
case-insensitive keyword text and match type. A matching criterion is left
untouched, retaining its criterion ID, enabled/paused status, keyword-level
bid overrides, labels, and criterion-associated history/statistics. Zernio
does not reset its quality score; Google continues to calculate scores and
statistics normally. Text comparison does not trim whitespace.

A bare string or an object without `matchType` means `broad`, not the
existing criterion's match type. For example, resending an existing
`{ "text": "plumber", "matchType": "exact" }` preserves it; sending
`"plumber"` instead removes that EXACT criterion and requests a BROAD one.
Changing text or match type removes criteria no longer requested and
creates any missing criteria. New criteria get new IDs and do not inherit
removed criteria's bid overrides, labels, or history. Historical reporting
for a removed criterion is not transferred to its replacement.

To add keywords without replacing a set, use
[POST /v1/ads/keywords](https://docs.zernio.com/ad-campaigns/add-ad-keywords).
Use `PATCH /v1/ads/keywords/{keywordId}` to pause/enable one keyword, or
`DELETE /v1/ads/keywords/{keywordId}` to remove it.


### Parameters

- **adId** (required) in path: No description

### Request Body

- **headlines** `array`: Google Search and Display only. Replaces the complete headline list. Search takes 3-15, Display 1-5 and rejects pinnedField; the count is checked once the ad's channel is known. No padding or truncation on update.
- **descriptions** `array`: Google Search and Display only. Replaces the complete description list. Search takes 2-4, Display 1-5 and rejects pinnedField. No padding or truncation on update.
- **finalUrls** `array`: Google Search and Display only. Replaces final URLs. Omitted lists stay unchanged. For Performance Max use assetGroup.finalUrl.
- **assetGroup**: Google Performance Max only. Replaces whole asset roles on the ad's asset group. Returns 422 on any other platform or channel.
- **status** `string`: No description - one of: active, paused
- **budget** `object`: No description
- **targeting** `object`: Meta + TikTok (demographics/interests), Google (keyword and device
bid adjustment edits only), and LinkedIn (countries or regions required).
Pinterest / X return 501.

- **creative** `object`: Replace or patch the ad's creative. Meta, TikTok, and LinkedIn.

- **Meta**: patch-style. Pass any subset: fields you omit are preserved from the
  live creative, including media (`image_hash`/`video_id` are reused, no re-upload)
  and `url_tags`. Sending the full set (`headline`, `body`, `callToAction`,
  `linkUrl`, `imageUrl`) rebuilds the creative from scratch instead. Partial
  patching reads the live `object_story_spec`, which Meta strips on SHARE /
  page-post / dark / asset_feed creatives. Those return 422 asking for the full
  set. A `videoUrl`/`videoId` on an image creative is a type change and also
  needs the full set. `existingCreativeId` repoints the ad at a creative from
  GET /v1/ads/creatives and ignores every other field. Meta creatives are
  immutable, so any change creates a new creative and repoints the ad; the old
  creative is retained on the ad account for historical reporting.
  `creativeFeatures` is Meta-only. Omitted settings are preserved from the
  live creative, including full rebuilds. A supplied creativeFeatures map
  overrides individual existing keys.
- **TikTok**: patch-style. Pass any subset; `headline` is ignored (TikTok creatives
  have no headline slot). `body` becomes the in-feed `ad_text`; `linkUrl` becomes
  `landing_page_url`; `videoUrl` triggers a fresh upload. `description`, `videoId`
  and `existingCreativeId` are Meta-only and return 400.
- **LinkedIn**: requires new media (image via `imageUrl` or video via `videoUrl`);
  a text-only creative update returns 400. Uploads the media, creates a new inline
  media creative on the same campaign, and pauses the old creative (best-effort).
  The old creative is retained for historical reporting. `videoId` and
  `existingCreativeId` are Meta-only and return 400.

- **name** `string`: Rename the ad. Now propagated to Meta (POST /{ad-id}); non-Meta platforms return 501.

### Responses

#### 200: Ad updated

**Response Body:**

- **ad**: `Ad` - See schema definition
- **message** `string`: No description

#### 400: Invalid status transition, budget below minimum, a LinkedIn creative update without imageUrl or videoUrl, a LinkedIn targeting update without countries or regions, or a Google targeting update that is unsupported, empty, mixes locations with the top-level geo fields, or names an unknown country or language code

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Returned with code `ads_allowance_exceeded` when the team has no payment method on file and has reached the 500 free live ads: add a card to resume.

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 422: The ad has no campaign or ad group on the platform yet, the Google targeting edit asks for something that is create-only (`locations.customLocations`), or a creative field the ad's channel cannot carry: assetGroup on a non-Performance-Max ad, a Google Display field on a Search ad, a pinnedField on a Display headline, or any Google-only field on another platform. A Google creative edit that cannot reach Google at all (the ad has no `platformAdId`, or its ad account cannot be loaded) also returns 422 rather than a 200 that changed nothing.

#### 429: Meta admits one write per 30 seconds to a metered object, ad creatives above all. Zernio waits out two of those windows and replays the call before surfacing this, so it only appears when the object is being edited faster than that. Retry in 30 seconds.

#### 501: targeting or creative not supported on the platform (supported on Meta, TikTok, and LinkedIn)

#### 502: Meta accepted the request then failed to produce the media (upload session, chunk transfer, processing timeout, or a response with no image hash). Inspect `platformError.reason`.

---

## DELETE /v1/ads/{adId}

**Cancel an ad**

Cancels the ad on the platform and marks it as cancelled in the database. The ad is preserved for history. OpenAI Ads has no delete API; the ad is archived instead (a terminal state, the closest equivalent).

### Parameters

- **adId** (required) in path: No description

### Responses

#### 200: Ad cancelled

**Response Body:**

- **message** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---
