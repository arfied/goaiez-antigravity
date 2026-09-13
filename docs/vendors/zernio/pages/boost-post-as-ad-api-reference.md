# Boost post as ad API Reference

Creates a paid ad from an existing published post, keeping the post's
engagement. By default it provisions the whole hierarchy (campaign, ad
set, ad).

**Attach shape (Meta).** Send `adSetId` to put the ad under an EXISTING
ad set instead, so that ad set keeps its learning phase. It then owns
`budget`, `schedule` and `targeting`, and sending any of those alongside
`adSetId` is a 400 rather than a silent drop. `budget` is required only
without `adSetId`.

`instagramAccountId`, `destinationType`, `whatsappPhoneNumber` and `adSetId`
are Meta-only and return 400 on other platforms.

**Messaging boosts (Meta).** Use `goal: engagement` with
`callToAction: WHATSAPP_MESSAGE`, `MESSAGE_PAGE`, or `INSTAGRAM_MESSAGE`.
The CTA implies WHATSAPP, MESSENGER, or INSTAGRAM_DIRECT respectively;
`destinationType` alone does not select a messaging CTA. Omit `linkUrl`
only for messaging CTAs. Plain link CTAs keep their goal and link behavior
when combined with an independent `destinationType`.
The campaign uses OUTCOME_ENGAGEMENT and the ad set uses CONVERSATIONS
with the promoted Page. Optional `whatsappPhoneNumber` selects a number
already paired with that Page. Conflicting CTA/destination, instant form,
goal, or optimizationGoal inputs return 400. Attach requires the target
ad set destination to match. Existing post references preserve social proof;
an Instagram reel rejected by Meta is not re-uploaded as a new post for
a messaging boost.

**Retries.** Boosts are NOT idempotent and can take minutes when Meta requires re-hosting an
Instagram video, so do not retry on client timeout. Send an
Idempotency-Key header to make retries safe: same key and body replays
the original 201, and distinct keys always create distinct ads.
Without the header, an identical request is treated as a retry: while
one is in flight it returns 409, and within 10 minutes of a completed
boost it returns the already-created ad instead of creating another.
To intentionally duplicate an ad, send distinct Idempotency-Keys (or
vary the body, e.g. the name).


## POST /v1/ads/boost

**Boost post as ad**

Creates a paid ad from an existing published post, keeping the post's
engagement. By default it provisions the whole hierarchy (campaign, ad
set, ad).

**Attach shape (Meta).** Send `adSetId` to put the ad under an EXISTING
ad set instead, so that ad set keeps its learning phase. It then owns
`budget`, `schedule` and `targeting`, and sending any of those alongside
`adSetId` is a 400 rather than a silent drop. `budget` is required only
without `adSetId`.

`instagramAccountId`, `destinationType`, `whatsappPhoneNumber` and `adSetId`
are Meta-only and return 400 on other platforms.

**Messaging boosts (Meta).** Use `goal: engagement` with
`callToAction: WHATSAPP_MESSAGE`, `MESSAGE_PAGE`, or `INSTAGRAM_MESSAGE`.
The CTA implies WHATSAPP, MESSENGER, or INSTAGRAM_DIRECT respectively;
`destinationType` alone does not select a messaging CTA. Omit `linkUrl`
only for messaging CTAs. Plain link CTAs keep their goal and link behavior
when combined with an independent `destinationType`.
The campaign uses OUTCOME_ENGAGEMENT and the ad set uses CONVERSATIONS
with the promoted Page. Optional `whatsappPhoneNumber` selects a number
already paired with that Page. Conflicting CTA/destination, instant form,
goal, or optimizationGoal inputs return 400. Attach requires the target
ad set destination to match. Existing post references preserve social proof;
an Instagram reel rejected by Meta is not re-uploaded as a new post for
a messaging boost.

**Retries.** Boosts are NOT idempotent and can take minutes when Meta requires re-hosting an
Instagram video, so do not retry on client timeout. Send an
Idempotency-Key header to make retries safe: same key and body replays
the original 201, and distinct keys always create distinct ads.
Without the header, an identical request is treated as a retry: while
one is in flight it returns 409, and within 10 minutes of a completed
boost it returns the already-created ad instead of creating another.
To intentionally duplicate an ad, send distinct Idempotency-Keys (or
vary the body, e.g. the name).


### Parameters

- **undefined** (optional): No description

### Request Body

- **creativeFeatures**: No description
- **postId** `string`: Zernio post ID (provide this or platformPostId)
- **platformPostId** `string`: Platform post ID (alternative to postId)
- **accountId** (required) `string`: Account ID
- **adAccountId** (required) `string`: Platform ad account ID
- **name** (required) `string`: No description
- **goal** (required) `string`: Available goals vary by platform. Meta (Facebook/Instagram) and TikTok support all 7. LinkedIn supports all except app_promotion. X supports engagement, traffic, awareness, video_views, app_promotion. Pinterest and Google Ads support only engagement, traffic, awareness, video_views. - one of: engagement, traffic, awareness, video_views, lead_generation, conversions, app_promotion
- **adSetId** `string`: Meta only. Attach the boosted post to this existing ad set instead of creating a campaign. The ad set then owns budget, schedule and targeting; sending those too is a 400.
- **budget** `object`: Required unless adSetId is set.
- **instagramAccountId** `string`: Meta only. Instagram identity the ad runs AS (creative.instagram_user_id), overriding the account linked to the Page. Live-verified against a Page-post creative.
- **destinationType** `string`: Meta only. Ad-set destination_type: where the click LANDS, as opposed to instagramAccountId which is who the ad runs as. Independent of plain link CTAs and their goal. A messaging callToAction selects its destination automatically; an explicit destinationType must then match. Lead ads use ON_AD. - one of: INSTAGRAM_PROFILE, WEBSITE, ON_AD, MESSENGER, WHATSAPP, INSTAGRAM_DIRECT
- **whatsappPhoneNumber** `string`: Meta WhatsApp only. E.164 number already paired with the Page. Omit to use the default pairing. Requires WHATSAPP_MESSAGE callToAction. Stored as creative.whatsappPhoneNumber on the ad.
- **currency** `string`: ISO 4217 currency code matching the ad account's currency. Meta only. Optional: Zernio resolves it from the ad account when omitted. The value selects the minor-unit exponent Zernio converts budget/bid amounts by before calling Meta (most currencies are cents; zero-decimal currencies like JPY/KRW are sent as-is).
- **schedule** `object`: No description
- **targeting** `object`: Same geo/demographic fields as the `TargetingSpec` used by /v1/ads/create.
Geo keys (`regions`/`cities`/`zips`/`metros`) resolve via
GET /v1/ads/targeting/search?dimension=geo. City radius and lat/lng
`customLocations` are Meta-only and preserve the boosted post's
social proof (the ad references the existing post).

- **rawTargeting** `object`: Meta only. A Meta-native targeting spec (e.g.
`{ "geo_locations": { "cities": [{ "key": "...", "radius": 15, "distance_unit": "kilometer" }] } }`).
Sent alone it is forwarded unchanged. Use for advanced fields the structured
object does not expose (flexible_spec, excluded audiences, business places,
user_os, wireless_carrier).

Can be combined with `targeting`: rawTargeting is the BASE layer and the
built camelCase spec is merged on top, key by key (camelCase wins on
collision). The merge goes one level deep inside `geo_locations` and
`excluded_geo_locations` (built sub-keys win; raw-only sub-keys such as
`location_types` survive). Array values (`flexible_spec`, ...) are replaced
as a whole key, never element-merged.

When `rawTargeting` is present the `advantage_audience: 0` default that
Zernio normally applies is no longer emitted, so it cannot clobber a
`targeting_automation` sent in the raw spec. Meta requires
`targeting_automation` on ad set creation, so include it in the raw spec,
or send `targeting.advantage_audience` (0 or 1), which is merged over raw
as `targeting_automation`.

- **bidStrategy**: Deprecated: send it inside `platformSpecificData` instead (Meta today; TikTok's nested shape is planned). The flat field keeps working during the deprecation window; sending both shapes returns a 400.

Meta bid strategy applied to the ad set. On TikTok, mapped to
`bid_type` / `bid_price` / `deep_bid_type` automatically.

- **bidAmount** `number`: Deprecated: send it inside `platformSpecificData` instead (Meta today; TikTok's nested shape is planned). The flat field keeps working during the deprecation window; sending both shapes returns a 400.

Bid cap in WHOLE currency units (USD: 5 = $5.00; JPY: 100 = ¥100). Required when
`bidStrategy` is `LOWEST_COST_WITH_BID_CAP` or `COST_CAP`. Backward-compat: providing
`bidAmount` without `bidStrategy` is treated as `LOWEST_COST_WITH_BID_CAP`.

- **roasAverageFloor** `number`: Deprecated: send it inside `platformSpecificData` instead (Meta today; TikTok's nested shape is planned). The flat field keeps working during the deprecation window; sending both shapes returns a 400.

Minimum ROAS as a decimal multiplier (e.g. 2.0 = 2.0x ROAS). Required when
`bidStrategy` is `LOWEST_COST_WITH_MIN_ROAS`. Sent to Meta as
`bid_constraints.roas_average_floor` × 10000 (Meta uses fixed-point integers).

- **platformSpecificData**: Platform-specific settings (see schema definitions below)
- **tracking** `object`: Meta only. Tracking specs (pixel, URL tags).
- **specialAdCategories** `array`: Meta only. Required for housing, employment, credit, or political ads.
- **specialAdCategoryCountry** `array`: Meta (metaads) only. 2-letter ISO country codes the special ad category applies to. Requires specialAdCategories to be set (400 otherwise).
- **regionalRegulatedCategories** `array`: Meta only. Regional regulation categories required when the ad set targets certain countries (e.g. BRAZIL_REGULATION, SINGAPORE_UNIVERSAL, TAIWAN_UNIVERSAL, THAILAND_UNIVERSAL, AUSTRALIA_FINSERV, INDIA_FINSERV, TAIWAN_FINSERV). Forwarded to the ad set.
- **regionalRegulationIdentities** `object`: Meta only. Beneficiary/payer entity IDs for regionalRegulatedCategories. Values are numeric IDs from Meta verification. Keys vary by category (e.g. universal_beneficiary / universal_payer for BRAZIL_REGULATION and THAILAND_UNIVERSAL). If omitted, Meta uses Ads Manager defaults when configured.
- **linkUrl** `string`: Website URL for non-messaging CTA buttons. Send it with `callToAction`. Omit for messaging boosts.

**Meta**: adds a top-level `call_to_action` to the post-reference creative.
This is what gives a `traffic` boost a clickable destination without
replacing the creative and losing the post's social proof. Ignored when
`leadGenFormId` is set, which supplies its own destination. Live-verified
against a Page-post creative.

**TikTok**: maps to `landing_page_url` on the Spark Ad creative
(`AdcreateCreatives.landing_page_url`); Spark Ads have no clickable
destination without it.

Ignored on LinkedIn / Pinterest / X / Google, which infer the destination
from the boosted post.

- **callToAction** `string`: CTA button label. Non-messaging CTAs require `linkUrl`.
WHATSAPP_MESSAGE, MESSAGE_PAGE, and INSTAGRAM_MESSAGE do not
require a URL and reject linkUrl.

**Meta**: the CTA enum of POST /v1/ads/create plus
`VIEW_INSTAGRAM_PROFILE`, `WHATSAPP_MESSAGE`, `MESSAGE_PAGE`,
and `INSTAGRAM_MESSAGE`. VIEW_INSTAGRAM_PROFILE requires linkUrl;
the messaging CTAs select their destination automatically.

**TikTok**: pass-through to `call_to_action` on the Spark Ad creative; the
platform validates the value. See TikTok's "Enumeration - Call-to-Action".

- **sparkAuthCode** `string`: TikTok-only. Spark Code (creator's `auth_code`) authorizing cross-creator
Spark Ads: the advertiser can boost a video owned by a DIFFERENT TikTok
account. Without this, boosts are limited to videos owned by the same
account running the ads (same-BC creators only). The creator generates the
code in their TikTok app's Promote settings and shares it with the
advertiser. Maps to `auth_code` on the creative entry of /v2/ad/create/.

- **dsaBeneficiary** `string`: Legal entity that benefits from the ad. Required when targeting EU users
(EU DSA, Article 26). Optional if the ad account has a default beneficiary:
set it once via `PATCH /v1/ads/accounts` or in Meta Ads Manager, and Meta
fills it in whenever the field is omitted.

- **dsaPayor** `string`: Legal entity that pays for the ad. Can differ from `dsaBeneficiary`
(for example, an agency paying for a client's ads). Same rules as
`dsaBeneficiary`: required for EU targeting unless the ad account has
a default payor.

- **leadGenFormId** `string`: Lead Gen form ID to attach to the boosted ad's creative. REQUIRED when `goal` is `lead_generation`. On Meta this is the leadgen_forms ID (create one via POST /v1/ads/lead-forms). On LinkedIn this is the adForm ID (create one via POST /v1/ads/lead-forms with a LinkedIn account); the creative's `leadgenCallToAction.destination` is set to `urn:li:adForm:{id}`. Ignored for other goals.
- **status** `string`: Meta, TikTok, and LinkedIn. Publish state of the created entities. Omitted or ACTIVE publishes live (default); PAUSED creates them paused so you can review before they spend. On Meta a new campaign stays paused until explicitly activated; an attached ad is itself paused. On LinkedIn the whole campaign group, campaign, and creative hierarchy stays PAUSED (intendedStatus PAUSED on each). - one of: ACTIVE, PAUSED
- **optimizationGoal** `string`: Meta only. Explicit ad-set `optimization_goal` override. When omitted,
defaults to the value derived from `goal`. Messaging boosts always
use CONVERSATIONS and reject another optimizationGoal. Otherwise the value must be compatible
with the objective Meta derives from `goal`, not with the objective used
by `POST /v1/ads/create` for the same `goal` name: boost maps `goal:
"engagement"` to objective `OUTCOME_AWARENESS`, which accepts
`REACH`, `IMPRESSIONS`, `AD_RECALL_LIFT`, or THRUPLAY-class values, and
rejects `POST_ENGAGEMENT` (that value is only valid under
`OUTCOME_ENGAGEMENT`, which create uses for the same goal name).


### Responses

#### 201: Ad created

**Response Body:**

- **ad**: `Ad` - See schema definition
- **message** `string`: No description

#### 400: Missing required fields or invalid values

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

#### 409: The account may also be inactive or need reconnection (code ads_connection_required). Reconnect it and read GET /v1/accounts for its current ID before retrying.
An identical boost request is already in progress (with or without
an Idempotency-Key). Wait for it to finish instead of retrying.


#### 422: Platform ads connection required (TikTok Ads, X Ads), missing linked
account, or (for TikTok) the connected TikTok user is not authorized
as an Identity on the target advertiser. Returned with code
`ads_connection_required`; the message includes the actionable
"TikTok Ads Manager → Assets → Identity" remediation step.
Also returned as `idempotency_key_reused` when an Idempotency-Key
is reused with a different request body.


---
