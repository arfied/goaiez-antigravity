# Create standalone ad API Reference

Create a paid ad with custom creative across Meta, Google Ads, Pinterest, TikTok, X, LinkedIn, and OpenAI Ads (ChatGPT Ads).

Google Performance Max: set `campaignType: "pmax"` and supply `assetGroup` with
text, images by role, business name and finalUrl. Creates a daily budget, PAUSED
campaign and asset group atomically. `validateOnly: true` validates the complete
request with Google without creating or persisting resources. Read assets with
`GET /v1/ads/campaigns/{campaignId}/asset-groups`. The logo is required; video is
optional via `assetGroup.youtubeVideoId`. Brand guidelines are disabled at creation.
All supplied asset links are validated together against Google's minimum asset requirements.
PMax rejects ACTIVE creation, portfolio bidding, bid caps, legacy creative fields
and attach shapes. Geo and language targeting are supported; omitted geo targets
all locations. PMax does not require top-level goal, headline, body or linkUrl.
Supported bidding: omitted or LOWEST_COST_WITHOUT_CAP for Maximize Conversions,
COST_CAP plus bidAmount for target CPA, LOWEST_COST_WITH_MIN_ROAS plus
roasAverageFloor for Maximize Conversion Value with target ROAS.

Other mutually-exclusive request shapes are selected by the body:

- Legacy single-creative shape (all platforms, the default).
- Meta-only multi-creative shape via the creatives array: one ad set with N ads sharing budget and targeting.
- Attach shape via adSetId: adds one new ad to an existing ad set, inheriting its budget, targeting, and schedule (Meta, Google Ads, TikTok, and LinkedIn). On LinkedIn adSetId is the existing Campaign id, and the budget, schedule, targeting and bidding fields must be omitted.

Meta accepts `creativeFeatures` on the single and attach shapes and as defaults for
`creatives[]`; an item replaces the whole feature map. `promotion` is not supported on any
shape and any object is rejected with 400.
Reusing `existingCreativeId` uses the existing creative settings instead of new settings.
Requested settings are persisted for lists, exports, and default ad-detail reads.

Per-platform required fields, budget minimums, and video-ad rules are documented on each property below.

LinkedIn creates a Single Image or Single Video Ad backed by a Direct Sponsored Content "dark post" authored by a Company Page (see `organizationId`). Supported goals are engagement, traffic, awareness, and video_views (video ads use the `video` field; video_views requires a video), and traffic ads require `linkUrl`.

**Idempotency:** this endpoint is not idempotent at the platform level (a blind retry creates a second campaign/ad set/ad). Send an `Idempotency-Key` header to make retries safe: the first request with a given key creates the ad and we store the response; a retry with the same key replays that exact response (with `Idempotent-Replayed: true`) instead of creating duplicates. Reusing a key with a different body returns 422; a key whose first request is still in flight returns 409 (retry after a short backoff). Keys are scoped to your credential and expire after 24h.


## POST /v1/ads/create

**Create standalone ad**

Create a paid ad with custom creative across Meta, Google Ads, Pinterest, TikTok, X, LinkedIn, and OpenAI Ads (ChatGPT Ads).

Google Performance Max: set `campaignType: "pmax"` and supply `assetGroup` with
text, images by role, business name and finalUrl. Creates a daily budget, PAUSED
campaign and asset group atomically. `validateOnly: true` validates the complete
request with Google without creating or persisting resources. Read assets with
`GET /v1/ads/campaigns/{campaignId}/asset-groups`. The logo is required; video is
optional via `assetGroup.youtubeVideoId`. Brand guidelines are disabled at creation.
All supplied asset links are validated together against Google's minimum asset requirements.
PMax rejects ACTIVE creation, portfolio bidding, bid caps, legacy creative fields
and attach shapes. Geo and language targeting are supported; omitted geo targets
all locations. PMax does not require top-level goal, headline, body or linkUrl.
Supported bidding: omitted or LOWEST_COST_WITHOUT_CAP for Maximize Conversions,
COST_CAP plus bidAmount for target CPA, LOWEST_COST_WITH_MIN_ROAS plus
roasAverageFloor for Maximize Conversion Value with target ROAS.

Other mutually-exclusive request shapes are selected by the body:

- Legacy single-creative shape (all platforms, the default).
- Meta-only multi-creative shape via the creatives array: one ad set with N ads sharing budget and targeting.
- Attach shape via adSetId: adds one new ad to an existing ad set, inheriting its budget, targeting, and schedule (Meta, Google Ads, TikTok, and LinkedIn). On LinkedIn adSetId is the existing Campaign id, and the budget, schedule, targeting and bidding fields must be omitted.

Meta accepts `creativeFeatures` on the single and attach shapes and as defaults for
`creatives[]`; an item replaces the whole feature map. `promotion` is not supported on any
shape and any object is rejected with 400.
Reusing `existingCreativeId` uses the existing creative settings instead of new settings.
Requested settings are persisted for lists, exports, and default ad-detail reads.

Per-platform required fields, budget minimums, and video-ad rules are documented on each property below.

LinkedIn creates a Single Image or Single Video Ad backed by a Direct Sponsored Content "dark post" authored by a Company Page (see `organizationId`). Supported goals are engagement, traffic, awareness, and video_views (video ads use the `video` field; video_views requires a video), and traffic ads require `linkUrl`.

**Idempotency:** this endpoint is not idempotent at the platform level (a blind retry creates a second campaign/ad set/ad). Send an `Idempotency-Key` header to make retries safe: the first request with a given key creates the ad and we store the response; a retry with the same key replays that exact response (with `Idempotent-Replayed: true`) instead of creating duplicates. Reusing a key with a different body returns 422; a key whose first request is still in flight returns 409 (retry after a short backoff). Keys are scoped to your credential and expire after 24h.


### Parameters

- **undefined** (optional): No description

### Request Body

- **accountId** (required) `string`: No description
- **adAccountId** (required) `string`: No description
- **name** (required) `string`: No description
- **campaignName** `string`: Meta only. Exact campaign name. Overrides the default `<name> - Campaign`.
- **adSetName** `string`: Meta only. Exact ad set name. Overrides the default `<name> - Ad Set`. (For per-ad names on the multi-creative shape, set `name` on each `creatives[]` entry.)
- **adName** `string`: Meta only. Exact ad name (the single-creative ad object's name). Overrides the default, which is `name`. (For per-ad names on the multi-creative shape, set `name` on each `creatives[]` entry instead.)
- **tracking**: No description
- **goal** `string`: Required on legacy and multi-creative shapes; the attach shape inherits it from the ad set. Available goals vary by platform.

**Meta**
- `conversions`: OUTCOME_SALES. Requires `promotedObject.pixelId` and `promotedObject.customEventType` with a commerce event such as PURCHASE or START_TRIAL, or `promotedObject.customConversionId` to optimise against a Custom Conversion, or `customEventType: OTHER` + `customEventStr` to optimise against a pixel custom event.
- `lead_conversion`: OUTCOME_LEADS optimizing website pixel leads. Same pixel and event fields, but with a leads-class event such as LEAD, SUBMIT_APPLICATION, SCHEDULE or CONTACT (or `promotedObject.customConversionId` to optimise against a Custom Conversion instead). Meta gates conversion events by objective, so leads-class events are rejected under `conversions`.
- `lead_generation`: OUTCOME_LEADS with instant forms. Requires `leadGenFormId`. `promotedObject.pageId` is optional and auto-filled from the connected Page.
- `app_promotion`: requires `promotedObject.applicationId` and `promotedObject.objectStoreUrl`.
- `catalog_sales`: Advantage+ catalog ads, for example vehicle inventory. Requires `promotedObject.productSetId`, `promotedObject.pixelId` and `promotedObject.customEventType`. Builds a catalog TEMPLATE creative from the copy fields, which may carry template tags like {{product.name}} or {{vehicle.make}}. No imageUrl or video is sent; Meta renders the visuals per catalog item. Discover catalogs via GET /v1/ads/catalogs and product sets via GET /v1/ads/catalogs/{catalogId}/product-sets. Single shape only, no creatives[], adSetId, dynamicCreative or placementAssets.
- `page_likes`: Page Likes conversion location under OUTCOME_ENGAGEMENT (destination_type ON_PAGE, optimization PAGE_LIKES). `promotedObject.pageId` is optional and auto-filled from the connected Page. The creative CTA is fixed to LIKE_PAGE targeting that Page; headline / body / linkUrl / callToAction / imageUrl / video are all optional (Meta derives the link and the Like button from the Page).

**TikTok**
- `conversions`: website-conversion ad group. Requires `promotedObject.pixelId`, your TikTok Pixel ID. Accepts an optional `promotedObject.customEventType` with a TikTok optimization_event code your pixel tracks (newer pixels use e.g. SHOPPING for purchase events; legacy pixels use ON_WEB_ORDER, INITIATE_ORDER, ON_WEB_REGISTER or FORM). To inherit pixel and event from an existing ad group, pass `adSetId` instead.

**LinkedIn**
- `engagement`, `traffic`, `awareness` and `video_views` create standalone Direct Sponsored Content ads. `traffic` requires `linkUrl`; `video_views` requires `video`.
- `lead_generation`: requires `leadGenFormId` (an adForm ID from POST /v1/ads/lead-forms). The campaign objective is set to MAX_LEAD and the creative's `leadgenCallToAction` destination is set to `urn:li:adForm:{id}`.
- `job_applicants` requires a `platformSpecificData.jobs` creative.
- For `conversions` on LinkedIn, or to promote an existing post, use POST /v1/ads/boost.

**OpenAI Ads**
- Only `traffic`, `awareness`, and `conversions` are supported (other goals return 400). Maps to OpenAI's `bidding_type` (clicks, impressions, conversions respectively). `conversions` requires an active conversion event setting on the account; create a tracking tag with `defaultEventType` via the tracking-tags API (`POST /v1/accounts/{accountId}/tracking-tags`), or configure a conversion event in OpenAI Ads Manager, or the request returns 422.
 - one of: engagement, traffic, awareness, video_views, lead_generation, lead_conversion, conversions, app_promotion, catalog_sales, page_likes, job_applicants
- **optimizationGoal** `string`: Meta only. Explicit ad-set `optimization_goal` (e.g. `LANDING_PAGE_VIEWS`, `LINK_CLICKS`, `REACH`, `IMPRESSIONS`, `OFFSITE_CONVERSIONS`, `THRUPLAY`, `LEAD_GENERATION`). Overrides the default derived from `goal` (e.g. `traffic` defaults to `LINK_CLICKS`). Forwarded verbatim to Meta, which validates compatibility with the campaign objective and rejects incompatible combinations.
- **billingEvent** `string`: Meta only. Explicit ad-set `billing_event`. Defaults to `IMPRESSIONS`. Forwarded verbatim to Meta, which validates compatibility with the optimization goal.
- **buyingType** `string`: Meta only. Defaults to AUCTION and is explicitly sent on new campaigns, including validateOnly. Reusing existingCampaignId does not change the campaign. RESERVED = Reach & Frequency: requires `rfPredictionId` (a RESERVED prediction from /v1/ads/rf-predictions + /reserve). Budget, schedule and pricing come from the reservation, so budgetAmount/budgetType are not required and bid fields are ignored. Only the plain single-ad shape (no creatives[], adSetId, existingCampaignId or dynamicCreative). - one of: AUCTION, RESERVED
- **rfPredictionId** `string`: Meta only. The RESERVED prediction id the R&F ad set runs on (reserving mints a new id, so pass that one). Requires buyingType RESERVED.
- **promotion**: Not supported. Meta validates creative_sourcing_spec.promotion_metadata_spec on the create call and then discards it, so a Promotion set through the Marketing API never reaches the creative. Any object is rejected with 400 invalid_field_value. Send null or omit the field, and set the Promotion on the ad in Ads Manager. Verified on 2026-09-11 across Graph v19.0 to v25.0 and every write path.
- **creativeFeatures**: Meta only. Applied to each new creative, including standalone and attach shapes. With creatives[], these are defaults; an item replaces the whole feature map, including an empty map. auto_promotion_tag is an Advantage+ enhancement, not the Ads Manager Promotion setting.
- **multiAdvertiser** `string`: Meta only. Multi-advertiser ads: whether Meta may show this ad alongside other advertisers' in one unit. Meta auto-enrols since Aug 2024, so send OPT_OUT to leave. It is a top-level creative field, NOT a `creativeFeatures` key, and Meta rejects it there. - one of: OPT_IN, OPT_OUT
- **validateOnly** `boolean`: Google Performance Max validates the complete atomic campaign and asset group with no resource creation or local persistence. Google validation still downloads image URLs and consumes quota. On Meta, validates the complete inline campaign, ad set, creative and ad with execution_options validate_only. Nothing is uploaded or created, and validation bypasses Idempotency-Key storage. Supports a single image, all-image placementAssets with per-rule copy, existing video.id or existingCreativeId; other media pools, new video uploads, creatives[], adSetId and RESERVED buying return 400. Placement validation uses existing Instagram identities only. Existing campaign or creative nodes are marked skipped. Success returns 200 with per-node results; Meta rejection returns an error.
- **budgetAmount** `number`: Budget in WHOLE currency units (USD: 50 = $50.00), NOT cents. Meta's own Marketing API takes this same number in minor units, so it is an easy and expensive mix-up. Required on legacy, multi-creative and Performance Max shapes. Inherited on attach. OpenAI Ads requires a $1 minimum (its budget is lifetime-only, see budgetType).
- **budgetType** `string`: Required on legacy, multi-creative and Performance Max shapes. Inherited on attach. OpenAI Ads accepts lifetime only (no daily-budget concept on the platform); sending daily returns 422. OpenAI Ads lifetime budgets require `endDate` to give the lifetime cap a spend window. - one of: daily, lifetime
- **status** `string`: Google Performance Max accepts PAUSED only and always creates a paused campaign. Google Search and Display, Meta, TikTok, and LinkedIn: publish state of the created entities. Omitted or ACTIVE publishes live (default, back-compat); PAUSED creates them paused so you can review before they spend. On Meta the pause is held on the campaign this call creates, leaving the ad set and ad switched on, so a single PUT /v1/ads/campaigns/{campaignId}/status with `active` brings the whole thing live. It is held at every level instead when the pause cannot rely on the campaign: `existingCampaignId` (that campaign may be running and is never touched) or `campaignStatus: ACTIVE`. Google Search and Display follow the same rule, and because Google keeps an independent switch at campaign, ad group and ad level, a PAUSED create leaves the campaign it creates PAUSED at Google. On TikTok the whole campaign > ad group > ad hierarchy stays paused. On LinkedIn the whole campaign group, campaign, and creative hierarchy stays PAUSED (intendedStatus PAUSED on each). - one of: ACTIVE, PAUSED
- **campaignStatus** `string`: Meta and Google. Overrides `status` for the campaign level alone, so you can create a live campaign whose ad set and ad stay paused, or the reverse. Omitted, it follows `status`. - one of: ACTIVE, PAUSED
- **budgetLevel** `string`: Meta only. Where the budget lives, which selects the Meta budget model:
  - `adset` (default): ABO (Ad-set Budget Optimization). The budget is set on the
    ad set. This is the back-compatible behaviour; omit this field to keep it.
  - `campaign`: CBO (Campaign Budget Optimization / Advantage Campaign Budget). The
    budget AND `bidStrategy` are set on the CAMPAIGN, and Meta distributes spend
    across ad sets automatically.
The returned ad stores the applied `budgetLevel` and budget in `campaignBudget`
for CBO or `adSetBudget` for ABO. Edit CBO budgets with
`PUT /v1/ads/campaigns/{campaignId}` and ABO budgets with
`PUT /v1/ads/ad-sets/{adSetId}`.
Meta requires the budget at exactly one level, never both. Non-Meta platforms ignore
this field. Ignored on the attach shape (`adSetId`), which inherits the existing budget.
 - one of: adset, campaign
- **currency** `string`: ISO 4217 currency code matching the ad account's currency (e.g. `USD`). Meta only. Optional: Zernio resolves it from the ad account when omitted. The value selects the minor-unit exponent Zernio converts budget/bid amounts by before calling Meta (most currencies are cents; zero-decimal currencies like JPY/KRW are sent as-is).
- **headline** `string`: Required for Meta, Google, Pinterest, LinkedIn, and OpenAI Ads on legacy + attach shapes (skip for multi-creative; use `creatives[].headline`). Ignored for TikTok and X. Max: Meta=255, Google=30, Pinterest=100, LinkedIn=400, OpenAI=50 (min 3). On LinkedIn this is the ad's headline (the bold text on the creative); for traffic ads it's the link card title. On OpenAI Ads this is the chat card's title.
- **longHeadline** `string`: Google Display only. Defaults to `headline` if omitted. On LinkedIn, reused as the optional secondary description text on traffic (link) ads; omitted if not provided.
- **body** `string`: Required on legacy + attach shapes. For X this is the tweet text (max 280 chars including a ~24-char URL when `linkUrl` is set). On LinkedIn this is the post commentary (the intro text shown above the ad). On OpenAI Ads this is the chat card's body text. Max: Google=90, Pinterest=500, OpenAI=100.
- **description** `string`: Meta only (facebook/instagram). Link description: the secondary text shown below the headline (Meta's link_data.description; on video creatives mapped to video_data.link_description). When omitted, Meta auto-pulls the destination URL's OpenGraph description. Applies on legacy, attach, and placementAssets shapes; for multi-creative use creatives[].description (this field is the shared fallback). For multi-text variations use `descriptions` (array) instead.
- **bodies** `array`: Meta only. Multiple Text Options (Advantage+ Flexible Format): supply 1-5 primary-text
variations and Meta optimises delivery across them, WITHOUT enabling full Dynamic Creative
(`dynamicCreative`). Uses `optimization_type: DEGREES_OF_FREEDOM` on the asset feed, so
multiple ads per ad set are allowed (unlike `dynamicCreative` which is limited to one).
Requires `imageUrl` or `video`, `linkUrl`, and `callToAction`. When set, the top-level
`body` field is used as the `object_story_spec.link_data.message` (the preview text) and
`headlines` must also be present. On a video creative the copy lands in
`video_data.message` / `video_data.title` instead of `link_data`. Mutually exclusive
with `dynamicCreative`, `placementAssets`, `carouselCards`, and `creatives[]`. For placement-specific copy, use the singular `placementAssets.rules[].body` and `headline` fields instead.

- **headlines** `array`: Meta only. Headline variations for Multiple Text Options. Must be sent alongside `bodies`.
The top-level `headline` field is used as the `object_story_spec.link_data.name`
(`video_data.title` on a video creative).

- **descriptions** `array`: Meta only. Optional description variations for Multiple Text Options. Sent alongside `bodies` and `headlines`.
- **callToAction** `string`: Required on legacy + attach shapes for Meta. Honoured on TikTok (passes through to the Spark Ad creative's `call_to_action`) and on LinkedIn (the CTA button on the ad; defaults to LEARN_MORE when `linkUrl` is set). LinkedIn accepts: LEARN_MORE, SIGN_UP, DOWNLOAD, SUBSCRIBE, REGISTER, JOIN, ATTEND, REQUEST_DEMO, VIEW_QUOTE, APPLY, SEE_MORE, SHOP_NOW, BUY_NOW. Ignored by Google, Pinterest, and X. - one of: LEARN_MORE, SHOP_NOW, SIGN_UP, BOOK_TRAVEL, CONTACT_US, DOWNLOAD, GET_OFFER, GET_QUOTE, SUBSCRIBE, WATCH_MORE, ADD_TO_CART, APPLY_NOW, BOOK_NOW, BUY_TICKETS, DONATE, DONATE_NOW, GET_DIRECTIONS, GET_SHOWTIMES, LISTEN_NOW, ORDER_NOW, PLAY_GAME, REQUEST_TIME, SEE_MENU, START_ORDER, INSTALL_MOBILE_APP, USE_APP, REGISTER, JOIN, ATTEND, REQUEST_DEMO, VIEW_QUOTE, APPLY, SEE_MORE, BUY_NOW
- **linkUrl** `string`: Required on legacy + attach shapes (skip for multi-creative). On LinkedIn it's the ad's destination URL; required for `traffic` ads, optional for `engagement` / `awareness`. NOT required when `goal` is `lead_generation` (the ad opens a Lead Gen form instead of a destination). On LinkedIn, `imageUrl` + `linkUrl` publishes an ARTICLE-content creative; this is LinkedIn's article ad format, with the image as thumbnail and `longHeadline` as description. Required for OpenAI Ads (the chat card's target_url).
- **leadGenFormId** `string`: Lead Gen form ID to attach to the ad's creative. REQUIRED when `goal` is `lead_generation`. Create one via POST /v1/ads/lead-forms. On Meta (facebook/instagram) this is the leadgen_forms ID; the ad set's promoted_object.page_id + LEAD_GENERATION optimization + destination_type ON_AD are derived automatically from the goal. On LinkedIn this is the adForm ID; the creative's `leadgenCallToAction.destination` is set to `urn:li:adForm:{id}` and the campaign objective is set to MAX_LEAD. Forms must be owned by the sponsoredAccount (not the organization) for the URN to resolve. Also required on every Meta ATTACH (`adSetId`) call that targets a lead ad set (the form attaches per-ad; Meta rejects a formless ad in a lead ad set). `placementAssets`, `dynamicCreative` and `carouselCards` (Meta multi-card Instant-Form lead ad; `linkUrl` and per-card `linkUrl` are optional and forwarded as real destinations when sent, falling back to Meta's lead-form link when omitted) ARE supported on Meta instant-form lead ads.
- **imageUrl** `string`: Image creative for Meta/Google/Pinterest/LinkedIn on legacy + attach shapes (mutually exclusive with `video`). Required for LinkedIn ads unless `video` is set. Not required for Google Search campaigns. For TikTok, this field carries the VIDEO URL (the TikTok ads endpoint is video-only; the field retains the `imageUrl` name for cross-platform consistency). Ignored for X. For Google Display, treated as the landscape image (alias of `images.landscape`); supply `images.square` alongside or the request is rejected. For LinkedIn the image is uploaded to LinkedIn under the authoring Company Page (see `organizationId`); recommended ratio 1.91:1 (e.g. 1200×627). Required for OpenAI Ads (uploaded as the chat card's image; OpenAI has no video ad format).
- **images** `object`: Google Display (Responsive Display Ads) only. Google RDA requires both a landscape (1.91:1) and a square (1:1) marketing image; sending only one is rejected upstream as 'Too few.' (NOT_ENOUGH_*_MARKETING_IMAGE_ASSET). Supply both URLs here. Either this field or the legacy `imageUrl` can provide the landscape, but `square` has no legacy counterpart so it must be set here for Display.
- **video** `object`: Meta (facebook, instagram) and LinkedIn. Creates a single VIDEO ad. Mutually exclusive with `imageUrl`. Supply `url` to upload a file, or `id` to reuse a video already on the ad account (list them with GET /v1/ads/videos). Works on the single-ad and attach (`adSetId`) shapes; for Meta multi-creative, set `video` per entry inside `creatives[]` instead. For LinkedIn the video is uploaded to LinkedIn under the authoring Company Page (see `organizationId`) and the campaign format is set to SINGLE_VIDEO; LinkedIn ignores `thumbnailUrl` (it auto-generates the poster frame). Supply MP4 H.264/AAC, 3s-30min, 75KB-500MB.
- **creatives** `array`: Meta-only. When present, switches to the multi-creative shape:
creates 1 campaign + 1 ad set + N ads (one per entry here).
Top-level `headline` / `body` / `imageUrl` / `linkUrl` /
`callToAction` are ignored in this mode. Mutually exclusive with `adSetId`.

- **adSetId** `string`: When present, switches to the attach shape: adds
one new ad to this existing ad set without creating a new
campaign. Budget, targeting, goal, schedule, AND bid strategy
are inherited from the ad set on Meta, and passing `bidStrategy`
in attach mode returns 400. To change an existing ad set's
bid, use `PUT /v1/ads/ad-sets/{adSetId}`. Mutually exclusive
with `creatives[]`. `dynamicCreative` returns 400 in attach mode: create
a new dynamic ad set by omitting `adSetId` instead.

The attached ad takes the full single-creative surface:
`headline`/`body`/`description`/`callToAction` plus either
`imageUrl`/`video` OR `placementAssets` (its own per-placement
Feed/Story assets) OR `translations`/`defaultLocale` (its own
per-locale asset feed, Meta only), and `leadGenFormId` when
the target is a lead ad set (the parent must be ON_AD, true for ad sets
created via goal `lead_generation`; Meta rejects a formless ad
there, so pass the form on EVERY attached ad). This is the way
to build N full ads sharing one ad set: create the first ad
via the normal shape, then attach the rest one call each.

Supported on Meta (facebook, instagram), Google Ads, TikTok,
and LinkedIn. On TikTok the `adSetId` is the ad group ID; the
new ad inherits the ad group's bid + budget + targeting.
On LinkedIn the `adSetId` is the LinkedIn Campaign ID
(numeric); we attach a new Creative to that Campaign, so
the Campaign's `platformSpecificData` bidding, targeting,
budget and schedule are inherited (passing those fields
returns 400).

On Google Ads the `adSetId` is the AD GROUP id. `goal` is
still REQUIRED even though budget and targeting are
inherited from the ad group. Send `campaignType: "search"`
to attach into a Search ad group, including one created by
`POST /v1/ads/ad-sets` (always SEARCH_STANDARD): without it
the request is treated as Display and requires
`images.landscape` + `images.square` + `businessName`, and
the resulting display creative does not match a Search ad
group.
`budgetAmount`/`budgetType` and bidding fields
(`bidStrategy`, `bidAmount`, `portfolioBidStrategyId`)
return 400 on this shape; the ad group already owns them.

- **existingCampaignId** `string`: Meta, Google Ads, and LinkedIn. On Meta: add the new ad
set under this EXISTING campaign instead of creating a new
one (multi-ad-set audience testing). The new ad set's
budget is matched to the campaign's mode automatically:
for a CBO campaign (campaign-level budget) omit
`budgetAmount`/`budgetType`, since the campaign owns the
budget; for an ABO campaign pass them (they go on the new
ad set). On LinkedIn: create a new Campaign (and its
Creative) under this EXISTING CampaignGroup. On Google
Ads: create a new ad group under this EXISTING campaign;
the new ad group inherits the campaign's budget, so omit
`budgetAmount`/`budgetType` (and any bidding field), or
the request returns 400. On failure only the entities we
authored are cleaned up; the pre-existing parent is left
untouched and is never (re)activated. Mutually exclusive
with `adSetId` and `creatives[]`.

- **existingCreativeId** `string`: Meta only. Reuse an EXISTING ad creative by id instead of
building a new one from the copy/media fields (which are then
ignored). Combine with `existingCampaignId` to build a
multi-ad-set campaign that shares one creative. Mutually
exclusive with `creatives[]`, `dynamicCreative`, and
`placementAssets`. The creative id used is returned as
`creativeId` on the create response.

- **businessName** `string`: Google Display only
- **boardId** `string`: Pinterest only. Board ID (auto-creates if not provided).
- **organizationId** `string`: LinkedIn only. The Company Page that authors the Direct Sponsored Content ("dark") post backing the ad. Accepts a numeric organization ID or a full `urn:li:organization:N` URN. Required unless the resolved `accountId` is a connected LinkedIn Company-Page account (defaults to that page) or the LinkedIn ad account is org-owned (defaults to the account's owning organization). The authenticated member must be an ADMINISTRATOR or DIRECT_SPONSORED_CONTENT_POSTER of this page (and the page must be associated with the ad account), or LinkedIn returns 403. Ignored by every other platform.
- **targeting**: Nested targeting object, the same TargetingSpec shape as `POST /v1/ads/boost`,
`POST /v1/ads/targeting/reach-estimate`, and `saved_targeting` audiences. Merged
UNDER the flat inline targeting fields below: `savedTargetingId` < `targeting` <
flat fields (a flat field present on the body replaces the nested value entirely).
Both forms are equivalent; use whichever your integration already builds.

- **countries** `array`: ISO 3166-1 alpha-2 country codes (e.g. ['NL']). Defaults to ['US'] when no other geo targeting (flat or nested `targeting`) is provided. (LinkedIn and OpenAI Ads currently honour country-level targeting only; any other targeting field returns 400 for OpenAI Ads.)
- **cities** `array`: City-level geo targeting (Meta and TikTok). Each city is targeted by the platform's opaque `key` (the city ID) which can be looked up via `GET /v1/ads/targeting/search?dimension=geo&q=<name>&countryCode=<ISO>`. Optional `radius` + `distance_unit` (Meta only) extend the targeting beyond the city limits (e.g. radius 25 km around the city center). Both must be set together, or both omitted (Meta defaults to ~16 km when omitted).

On Meta, cannot overlap with the same country in `countries` (Meta returns a "locations overlap" error). Either drop the country or scope it to a different country. On TikTok, keys are numeric location ids and can be sent without `countries`.

- **regions** `array`: Region-level (state/province) geo targeting (Meta and TikTok). Each region is targeted by the platform's opaque `key` (the region ID) which can be looked up via `GET /v1/ads/targeting/search?dimension=geo&q=<name>&countryCode=<ISO>`.

- **ageMin** `integer`: No description
- **ageMax** `integer`: No description
- **interests** `array`: Interest objects from /v1/ads/interests. Each must include id and name.
- **zips** `array`: Postal/ZIP geo targeting. `key` is the platform's postal location ID from /v1/ads/targeting/search?dimension=geo&geoType=zip. Supported on Meta, Google, TikTok, Pinterest, X.
- **metros** `array`: DMA / metro-area geo targeting (Meta and TikTok). `key` is the platform's metro ID from /v1/ads/targeting/search?dimension=geo&geoType=metro (TikTok metros appear as type `metro`, e.g. the New York DMA).
- **customLocations** `array`: Point-radius (lat/lng) geo targeting. Meta only (custom_locations). Rejected on platforms without radius support.
- **behaviors** `array`: Behaviour entities from /v1/ads/targeting/search?dimension=behavior. Supported on Meta and TikTok. Each must include id.
- **workPositions** `array`: Meta only. Job title entities from /v1/ads/targeting/search?dimension=workPosition. Each must include id. Rejected on other platforms (use LinkedIn's `jobTitles` there).
- **workEmployers** `array`: Meta only. Employer entities from /v1/ads/targeting/search?dimension=workEmployer. Each must include id.
- **workIndustries** `array`: Meta only. Work-industry entities from /v1/ads/targeting/search?dimension=workIndustry. Each must include id. Rejected on other platforms (use LinkedIn's `industries` there).
- **incomeTier** `string`: Normalized household-income tier. Meta and TikTok express all four; Google maps only
`top_10`; rejected on LinkedIn, X, and Pinterest. On Meta, income targeting is incompatible
with housing/employment/credit `specialAdCategories`.
 - one of: top_5, top_10, top_10_25, top_25_50
- **languages** `array`: e.g. ["en","es"]. Google: campaign language targeting (language_constant) using Google's language codes (ISO 639-1, plus variants such as `zh_CN`); unknown codes return 400. On Meta, a bare code targets all regional variants ("en" = all English), or use a region-qualified code for a specific one ("en_GB", "pt_BR", "zh_TW"); unknown codes are rejected. Other ad platforms use their own language-code systems.
- **placements** `object`: Meta only. Manual ad placements. Omit for automatic placements (Meta's default,
recommended for most cases, since Meta optimises delivery across all eligible surfaces).
When set, restricts delivery to the chosen surfaces, mapped onto the ad set's
`targeting.{publisher_platforms, facebook_positions, instagram_positions,
messenger_positions, audience_network_positions, threads_positions,
whatsapp_positions, device_platforms}`. Enum membership is validated here; Meta
additionally enforces co-selection rules (e.g. some positions require their parent
publisher platform) and returns an actionable error which we surface. Non-Meta
platforms reject this field.

- **savedTargetingId** `string`: ID of a `saved_targeting` audience (created via POST /v1/ads/audiences). When set, its stored
TargetingSpec is expanded as the base targeting; inline fields on this body merge on top. Lets you
reuse a named targeting preset without re-sending every field.

- **rawTargeting** `object`: Meta only. A raw Meta-native targeting spec (snake_case: `geo_locations`, `age_min`,
`excluded_custom_audiences`, `flexible_spec`, `targeting_automation`, `user_os`,
`wireless_carrier`, business places, etc.), exactly the shape `GET /v1/ads/{adId}` returns for
external ads. Sent alone it reaches the ad set VERBATIM (the clone-a-campaign's-targeting-exactly
path). Meta validates and surfaces any errors.

Can be combined with the camelCase targeting fields (countries/regions/cities/interests/ageMin/...,
`targeting`, `savedTargetingId`, `audienceId`): rawTargeting is the BASE layer and the built
camelCase spec is merged on top, key by key, with the camelCase side winning on collision (the
camelCase precedence chain stays `savedTargetingId` < `targeting` < flat fields). The merge goes
one level deep inside `geo_locations` and `excluded_geo_locations`: built sub-keys win, raw-only
sub-keys such as `location_types` survive alongside built `countries`. Array values
(`flexible_spec`, ...) are replaced as a WHOLE key when the camelCase spec builds them, never
element-merged. When rawTargeting is present the defaults the camelCase builder normally injects
(US geo, `targeting_automation.advantage_audience: 0`) are suppressed, so raw's values are not
clobbered. Include `targeting_automation` in the raw spec (or send `advantageAudience`) as Meta
requires it on create. If cloning an EU campaign, also pass `dsaBeneficiary` / `dsaPayor` (those
are separate fields, not part of targeting).

- **specialAdCategories** `array`: Meta only. Declares the ad's special category, required for housing, employment, credit, or
political/social-issue ads (Meta enforces restricted targeting for these). Note: setting a special
category disables income/zip targeting on Meta.

- **specialAdCategoryCountry** `array`: Meta (metaads) only. 2-letter ISO country codes the special ad category applies to. Requires
specialAdCategories to be set (400 otherwise). Ignored when joining an existing campaign via
existingCampaignId (the existing campaign's category/country already governs it).

- **regionalRegulatedCategories** `array`: Meta only. Regional regulation categories required when the ad set targets certain countries.
Known values: BRAZIL_REGULATION, SINGAPORE_UNIVERSAL, TAIWAN_UNIVERSAL, THAILAND_UNIVERSAL,
AUSTRALIA_FINSERV, INDIA_FINSERV, TAIWAN_FINSERV. Meta rejects the ad set without this when
the targeting geo includes the corresponding country.

- **regionalRegulationIdentities** `object`: Meta only. Beneficiary/payer entity IDs for regionalRegulatedCategories. Values are
numeric IDs from Meta verification. Keys vary by category (e.g. universal_beneficiary /
universal_payer for BRAZIL_REGULATION and THAILAND_UNIVERSAL). If omitted, Meta uses
Ads Manager defaults when configured.

- **endDate** `string`: Required for lifetime budgets
- **startDate** `string`: Meta only. Ad-set start time (ISO 8601, e.g. "2026-06-10T09:00:00Z"), mapped to the
ad set's `start_time`. When omitted the ad starts delivering immediately. For lifetime
budgets Meta also requires `endDate`. (Same `schedule.startDate` semantics already
available on `POST /v1/ads/boost`.)

- **instagramAccountId** `string`: Meta only. Override the Instagram account the ad is delivered as. Pass an Instagram
Business Account ID (e.g. 17841...), mapped to the creative's `instagram_user_id`.
When omitted we use the Instagram actor Meta already runs the Page's other ads as,
falling back to the Page's page-backed Instagram account. Useful when a Page has more
than one eligible IG account.

- **dynamicCreative** `object`: Meta only. Dynamic Creative: supply a POOL of assets and Meta auto-combines and
optimises them into the best-performing variations within a single ad (mapped to the
creative's `asset_feed_spec`). When set, the top-level single-creative fields
(`imageUrl`, `headline`, `body`, `linkUrl`, `callToAction`) are ignored. Mutually
exclusive with the `creatives[]` multi-creative shape. Exactly ONE of `imageUrls` /
`videoUrls` is required (Meta allows one ad format per asset feed; sending both →
400). Limits remain 10 images or videos and 5 bodies, titles or descriptions.
The ad set is created with `is_dynamic_creative: true`. Combining this field
with `adSetId` returns 400: omit `adSetId` to create a new dynamic ad set.
Multiple headlines go in `titles`; multiple primary texts go in `bodies`.

- **carouselCards** `array`: Meta only. Hand-built carousel: 2-10 authored cards in DETERMINISTIC order, mapped to
the creative's `link_data.child_attachments`. Unlike `dynamicCreative`,
you control the card order and per-card copy/link. Requires top-level `body`
and `callToAction`; `linkUrl` is also required UNLESS `leadGenFormId` is set. Those
become the ad's own Destination and button (`link_data.link` / `link_data.call_to_action`),
and double as the per-card fallback when a card omits its own.
Mutually exclusive with `imageUrl`/`video`, `creatives[]`, `dynamicCreative`,
`placementAssets`, `existingCreativeId`, `adSetId` and goal
`catalog_sales`. Combines with `leadGenFormId` to build a carousel Instant-Form
lead ad: `linkUrl` and per-card `linkUrl` become OPTIONAL and, when sent, are
forwarded as the real card and top-level destinations; when omitted, the
destination falls back to Meta's lead-form link.

- **defaultLocale** `string`: Meta only. Language the top-level copy is written in (e.g. `en`, `pt_BR`), used by the `translations` default rule. Defaults to `en`. Meta rejects a language asset feed whose default rule carries no locales of its own. Must NOT also appear as an entry in `translations`.
- **translations** `array`: Meta only. Multi-language ads (Dynamic Language Optimization): ONE ad carrying
per-locale copy and, optionally, per-locale media: the "Languages" toggle in Ads
Manager. Keeps social proof (likes/comments/shares) on a SINGLE post instead of
splitting it across one ad per language.

The ad's top-level copy is the DEFAULT shown to every locale you do NOT list,
and it counts as one of the language variants.

IMPORTANT, and the opposite of what you might expect: text does NOT inherit.
Every entry must carry its own `headline`, `body` AND `description`, and all of
them must be DISTINCT from each other and from the ad's top-level copy. Meta
deduplicates identical strings inside the asset feed, so two locales sharing a
string collapse into one asset and the create fails with a misleading "Too few
... texts provided in asset creation" (subcode 1885817) that names a field which
is actually present. We validate this before calling Meta and return a 400
naming the offending locale and field. `description` is therefore effectively
required on the ad whenever `translations` is present, even though it is
optional otherwise.

Do NOT list `defaultLocale` inside `translations`: Meta rejects the duplicate
with "The language asset feed includes an unsupported targeting field"
(subcode 1885985).

Media DOES inherit and is uploaded once when shared, and `linkUrl` inherits
too: each locale may name its own landing page and unlisted locales fall back
to the ad's top-level `linkUrl`. Meta enforces
Dynamic Creative image dimensions on language feeds, so an `imageUrl` that
works on a normal ad may be rejected with "The following images have invalid
dimensions for Dynamic Creative" (subcode 1885558). Video is not affected.

Mutually exclusive with `dynamicCreative`, `placementAssets`, `carouselCards`,
`existingCreativeId` and `creatives[]`. Meta allows one `asset_feed_spec` shape per creative.

- **placementAssets** `object`: Meta only. Placement asset customization: pin a SPECIFIC asset (image OR video) to
each placement group on a SINGLE ad (e.g. a 9:16 on Stories/Reels and a 4:5 on Feed).
The same thing Meta Ads Manager produces with "different creative per placement",
mapped to the creative's `asset_feed_spec` + `asset_customization_rules`. Deterministic
pinning, NOT the auto-optimizing pool of `dynamicCreative` (mutually exclusive). Works
on the legacy single shape AND the attach shape (`adSetId` + placementAssets adds one
placement-customized ad to an existing ad set, the way to build N per-placement ads
sharing one ad set: create the first normally, attach the rest). Cannot be combined
with `creatives[]` or top-level `bodies`/`headlines`/`descriptions` arrays. Each rule
can override `headline`, `body` and `description` with one string per field. Omitted
fields and unmatched placements use the top-level copy; `linkUrl` and `callToAction`
remain shared. Zernio emits labelled text with `optimization_type: PLACEMENT`.
Multiple text options rotating within a placement are not supported by this input. Each rule's `placements` accepts the same fields as the top-level
`placements` object; Meta enforces co-selection rules and returns an actionable error.

Meta controls text rendering by placement and format. Validation accepts these fields
but does not prove that every field appears in delivery. Preview the ad; put copy that
must always be visible into the image or video itself.

`validateOnly: true` supports all-image placementAssets without uploading or creating
anything. Video placement validation remains unsupported because it requires uploads.

A block is all-image OR all-video, never mixed (Meta's asset_feed_spec carries one ad
format). Image mode: `defaultImageUrl` + `rules[].imageUrl`. Video mode:
`defaultVideoUrl` + `rules[].videoUrl` (optional `thumbnailUrl`/`defaultThumbnailUrl`
posters; Meta auto-generates when omitted). Exactly one catch-all default is required.

- **audienceId** `string`: Custom audience ID for targeting
- **campaignType** `string`: Google only. Performance Max requires assetGroup and is always created PAUSED. - one of: display, search, pmax
- **assetGroup**: No description
- **keywords** `array`: Google Search only. Keywords on the new ad group; entries are strings (BROAD) or { text, matchType }. Editable later via PUT /v1/ads/{adId} targeting.keywords.
- **negativeKeywords** `array`: Google Search only; other platforms return 400. Ad-group-level negative keywords on the new ad group. Editable later via PUT /v1/ads/{adId} targeting.negativeKeywords.
- **campaignNegativeKeywords** `array`: Google Search only; other platforms return 400. Campaign-level negative keywords (campaign_criterion.negative), created alongside the ad group. Editable later via PUT /v1/ads/campaigns/{campaignId}/negative-keywords.
- **additionalHeadlines** `array`: Google Search RSA only. Extra text assets as strings or objects with text and optional pinnedField. Existing string input remains supported. The effective create lists, including primary text and deduplication, must contain 3-15 headlines and 2-4 descriptions; excess entries return 400.
- **additionalDescriptions** `array`: Google Search RSA only. Extra text assets as strings or objects with text and optional pinnedField. Existing string input remains supported. The effective create lists, including primary text and deduplication, must contain 3-15 headlines and 2-4 descriptions; excess entries return 400.
- **sitelinks** `array`: Google Search only. Sitelink assets to create and attach at the campaign level.
Each entry becomes an Asset (with sitelink_asset + Asset.final_urls) plus a
CampaignAsset link (field_type SITELINK). Approval is async: Google reviews
assets after creation; poll asset.policy_summary later to read the verdict.
Google requires at least two sitelinks to surface them on an ad; four or more
is Google's own recommendation for maximum visibility. The response's
creative.sitelinks[] echoes each input plus its Google resourceName.

- **callouts** `array`: Google Search only. Short callout texts (max 25 chars each) that appear as
non-clickable annotations under the ad, e.g. "Free shipping", "24/7 support".
Each becomes one Asset (`callout_asset`) plus a CampaignAsset link with
field_type CALLOUT. Response's creative.callouts[] echoes each input plus
its Google resourceName.

- **structuredSnippets** `array`: Google Search only. Structured snippets: one header from Google's
predefined list plus 3-10 values (max 25 chars each). Each becomes one
Asset (`structured_snippet_asset`) plus a CampaignAsset link with
field_type STRUCTURED_SNIPPET.

- **advantageAudience** `integer`: Meta only. Controls the Advantage audience feature (targeting_automation). 0 = disabled (default), 1 = enabled. Meta Marketing API requires this field on all ad set creation requests. - one of: 0, 1
- **attributionSpec** `array`: Meta only. Conversion attribution window for the ad set, mapping 1:1 to Meta's
ad-set `attribution_spec`. Only honored for conversion goals (`conversions`,
`lead_generation`, `app_promotion`); ignored for awareness/traffic/engagement.
Omit to use Meta's default (`7-day click` + `1-day view`). Meta enforces the
valid combinations: `VIEW_THROUGH` only allows `windowDays: 1` (7d/28d view
windows were removed Jan 2026); `ENGAGED_VIDEO_VIEW` only `1` and only alongside
`VIEW_THROUGH: 1`; `CLICK_THROUGH: 28` only on certain objectives. Invalid combos
surface as a Meta 400.
Example: `[{ "eventType": "CLICK_THROUGH", "windowDays": 7 }, { "eventType": "VIEW_THROUGH", "windowDays": 1 }]`

- **gender** `string`: Restrict the audience by gender. 'male' targets men only, 'female' targets women only, 'all' (default) targets everyone. Applied on Meta, TikTok and Pinterest. Ignored on Google, LinkedIn and X. - one of: all, male, female
- **bidStrategy**: Deprecated: send it inside `platformSpecificData` instead (Meta today; TikTok's nested shape is planned). The flat field keeps working during the deprecation window; sending both shapes returns a 400.

Meta bid strategy applied to the ad set.

OpenAI Ads: required on every ad group via this flat field, the only channel it supports (`platformSpecificData` is Meta/LinkedIn-only and returns 400 for OpenAI). No auto-bid option exists; send `LOWEST_COST_WITH_BID_CAP` or `COST_CAP` together with `bidAmount`, omitting it returns 400.

Google (not deprecated there, this shared flat field is Google's only shape): applied to the campaign this call creates. On Google: LOWEST_COST_WITHOUT_CAP = Maximize Conversions, COST_CAP + bidAmount = Target CPA, LOWEST_COST_WITH_MIN_ROAS + roasAverageFloor = Target ROAS, LOWEST_COST_WITH_BID_CAP + bidAmount = Maximize Clicks with a CPC ceiling; portfolioBidStrategyId attaches a portfolio strategy instead. Omitted, the campaign falls back to a goal-based default.

- **bidAmount** `number`: Deprecated: send it inside `platformSpecificData` instead (Meta today; TikTok's nested shape is planned). The flat field keeps working during the deprecation window; sending both shapes returns a 400.

Bid cap in WHOLE currency units (USD: 5 = $5.00; JPY: 100 = ¥100). Required when
`bidStrategy` is `LOWEST_COST_WITH_BID_CAP` or `COST_CAP`. Meta only: sending
`bidAmount` WITHOUT `bidStrategy` requires `existingCampaignId` (400 otherwise),
and sets the new ad set's cap under the joined campaign's COST_CAP /
LOWEST_COST_WITH_BID_CAP parent. The strategy itself is inherited from the
campaign. Restating bidStrategy here is accepted but has no effect on the ad set.

Rejected with 400 in `adSetId` attach mode: that shape inherits its cap from
the platform. Use `PUT /v1/ads/ad-sets/{adSetId}` there instead.

- **roasAverageFloor** `number`: Deprecated: send it inside `platformSpecificData` instead (Meta today; TikTok's nested shape is planned). The flat field keeps working during the deprecation window; sending both shapes returns a 400.

Minimum ROAS as a decimal multiplier (e.g. 2.0 = 2.0x ROAS). Required when
`bidStrategy` is `LOWEST_COST_WITH_MIN_ROAS`. Sending it without `bidStrategy`
is a 400. Sent to Meta as
`bid_constraints.roas_average_floor` × 10000. Known gap: a CBO campaign's
ROAS floor lives on the campaign only (set via `POST /v1/ads/campaigns`);
there is no supported way to set it while joining a CBO campaign here.

- **portfolioBidStrategyId** `string`: Google Search and Display only. Performance Max rejects portfolio bidding. Attach an existing portfolio bid strategy (numeric id from GET /v1/ads/bid-strategies) to the new campaign instead of a standard one. Exclusive with bidStrategy.
- **valueRuleSetId** `string`: Meta only (facebook, instagram; other platforms return 400). Value rule set
to attach to the new ad set, from `/v1/ads/value-rule-sets`. Attachment is
driven by this id, so `valueRulesApplied` is optional alongside it.

Rejected with 400 in `adSetId` attach mode: that shape inherits the existing
ad set's attachment, so the field would be silently ignored. Use
`PUT /v1/ads/ad-sets/{adSetId}` there instead.

Ignored (stripped before the ad-set create) when `buyingType` is `RESERVED`:
value rules only apply to auction ad sets on `LOWEST_COST_WITHOUT_CAP` or
`COST_CAP`, and a Reach & Frequency reservation has no auction bid strategy.

Read back with `GET /v1/ads/ad-sets/{adSetId}?fields=value_rule_set_id`; the
attachment is not mirrored onto Zernio's ad documents.

- **valueRulesApplied** `boolean`: Meta only (facebook, instagram; other platforms return 400). Optional when
attaching, and requires `valueRuleSetId`. `false` is REJECTED here with 400:
a newly created ad set has nothing to detach, so detaching lives on
`PUT /v1/ads/ad-sets/{adSetId}`.

- **platformSpecificData**: Platform-specific settings (see schema definitions below)
- **dsaBeneficiary** `string`: Legal entity that benefits from the ad. Required when targeting EU users
(EU DSA, Article 26). Optional if the ad account has a default beneficiary:
set it once via `PATCH /v1/ads/accounts` or in Meta Ads Manager, and Meta
fills it in whenever the field is omitted.

- **dsaPayor** `string`: Legal entity that pays for the ad. Can differ from `dsaBeneficiary`
(for example, an agency paying for a client's ads). Same rules as
`dsaBeneficiary`: required for EU targeting unless the ad account has
a default payor.

- **brandIdentity** `object`: TikTok only. Synthetic Brand Identity used when the ad
attributes to a CUSTOMIZED_USER (instead of a real TT_USER
@username). Required on the FIRST CUSTOMIZED_USER ad on a
`tiktokads` SocialAccount with no cached identity; omit on
subsequent ads (the identity is cached on the account after
first creation). Non-TikTok platforms ignore this field.

Alternative: configure once via `PATCH /v1/connect/tiktok-ads`,
then create ads without this field.

- **identityType** `string`: TikTok only. Forces the identity attribution on the ad:

  - `TT_USER`: the posting account's open_id (real @username
    branding). Requires a connected TikTok posting account
    on the same profile.
  - `CUSTOMIZED_USER`: synthetic Brand Identity (display
    name + avatar). Requires a configured Brand Identity
    (cached on the `tiktokads` SocialAccount via
    `PATCH /v1/connect/tiktok-ads`) or an inline
    `brandIdentity` to create one on the fly.

When omitted, defaults to `TT_USER` if a posting account is
connected on this profile, else `CUSTOMIZED_USER`. Spark
Ads (`POST /v1/ads/boost`) always use `TT_USER` regardless
of this field, because TikTok requires the original organic
post's author identity for Spark.
 - one of: TT_USER, CUSTOMIZED_USER
- **smartPlus** `boolean`: TikTok only. Creates the ad as a TikTok Upgraded Smart+
campaign: TikTok automates targeting, bidding and delivery. Supports goals
`conversions` (Smart+ Web Conversions), `lead_generation` (Smart+ Lead
Generation with a website form on `linkUrl`; TikTok Instant Forms not supported)
and `app_promotion` (Smart+ App installs; the ad's destination is the app store,
so `linkUrl` is not used). The web goals require `promotedObject.pixelId` AND
`promotedObject.customEventType`; `app_promotion` requires
`promotedObject.applicationId` instead.
Targeting works like on any TikTok ad (defaults to `countries: ["US"]` when
omitted); TikTok automates delivery within it.
The budget lives on the Smart+ campaign (Campaign Budget Optimization); a `lifetime`
budget additionally requires `endDate`. Cannot be combined with `adSetId`.

- **userOs** `array`: Meta only. Operating systems and version ranges, such as iOS_ver_14.0_and_above or Android. Emitted as user_os. May also be supplied inside targeting.
- **userDevice** `array`: Meta only. Device models such as iPhone. Emitted as user_device. May also be supplied inside targeting.
- **isSkadnetworkAttribution** `boolean`: Meta app promotion only. Immutable campaign flag. Set true for iOS 14+ SKAdNetwork campaigns and supply promotedObject.applicationId plus promotedObject.objectStoreUrl. The campaign receives promotedObject only when this flag is true. Cannot be changed on an existing campaign.
- **campaignAttribution** `string`: Meta ad-set attribution. Required as SKADNETWORK for iOS 14+ app promotion or a SKAdNetwork campaign. Requires AUCTION buying. Standalone Meta ad-set creation is not supported; use this field on /v1/ads/create. - one of: AEM, SKADNETWORK
- **promotedObject**: No description

### Responses

#### 200: validateOnly dry-run passed, nothing was created

**Response Body:**

- **validateOnly** `boolean`: Always true in a validate-only response.
- **results** `array[object]`: 
  - **node** `string`: No description - one of: campaign, adSet, creative, ad, performanceMaxCampaign
  - **status** `string`: No description - one of: validated, skipped
  - **reason** `string`: Why the node could not be validated (on skipped), or what the dry run could not check and what the request would do as sent (on validated). A Performance Max validation with no location targeting reports here that the campaign would run worldwide.
- **message** `string`: No description

#### 201: Ad(s) created

**Response Body:**

*One of the following:*
  - **ad**: `Ad` - See schema definition
  - **message** `string`: No description
  - **ads** `array[Ad]`: 
  - **platformCampaignId** `string`: No description
  - **platformAdSetId** `string`: No description
  - **message** `string`: No description

#### 400: Missing required fields, invalid values, non-Meta platform used with creatives[] / adSetId, or a Meta validateOnly validation failure (verbatim)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required. Legacy plans need the Ads add-on; included by default on usage-based plans. Also returned with code `ads_allowance_exceeded` when the team has no payment method on file and has reached the 500 free live ads: add a card to resume.

#### 404: The account or requested resource was not found or is not accessible. An account ID may have been disconnected and removed. Read GET /v1/accounts for current account IDs.

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

#### 409: The account exists but is inactive or needs reconnection. Reconnect it, then read GET /v1/accounts for its current account ID before retrying. Code: ads_connection_required.

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

#### 422: Platform ads connection required (TikTok Ads, X Ads) or missing linked account

#### 501: The requested option is not supported on this platform: `validateOnly` outside Meta,
or a shape the adapter does not implement. Carries code `feature_not_available`.


#### 502: The platform rejected the request, or failed to produce media the ad
needs (e.g. Meta generated no poster for an uploaded video when no
`video.thumbnailUrl` was supplied). Inspect `platformError` for the
upstream payload. Failures we raise carry a `reason`; a payload
forwarded verbatim from Meta may not. On the `creatives[]` shape a
missing poster also carries `creativeIndex` and `videoUrl` to
identify the entry. An upstream 4xx status is forwarded instead
of 502.


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
