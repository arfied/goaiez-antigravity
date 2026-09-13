# Create a standalone campaign API Reference

Creates a campaign WITHOUT its first ad set / ad, on the platform of the given
`accountId`. Ad sets join it later via `existingCampaignId` on the create endpoints.
Platform notes: on Meta a budget here is campaign-level (CBO) by definition; omit it
for ABO (each ad set carries its own budget), and `specialAdCategories` is Meta-only
(400 elsewhere); `bidStrategy` is Meta and Google (400 elsewhere), and Google also
accepts `portfolioBidStrategyId` instead. Google, X and OpenAI require a budget
(422 without one; OpenAI accepts only `budgetType: lifetime`, Google only
`budgetType: daily`). LinkedIn creates the
campaign GROUP (our campaign level) and rejects a budget, which lives on the
campaign (ad set) level there; it comes back `status: DRAFT`. TikTok campaigns are
created without a status and report `ENABLE`. Created `PAUSED` unless
`status: ACTIVE` where the platform supports it.

**Idempotency:** send an `Idempotency-Key` header to make retries safe.

## GET /v1/ads/campaigns

**List campaigns**

Returns campaigns as virtual aggregations over ad documents grouped by platform campaign ID.
Metrics (spend, impressions, clicks, etc.) are summed across all ads in each campaign.
Campaign status is derived from child ad statuses (active > pending_review > paused > error > completed > cancelled > rejected).
Google campaign budgets include amountMicros, explicitlyShared, resourceName and
deliveryMethod after the next successful sync. This endpoint does not fetch Google live.


### Parameters

- **includeEmpty** (optional) in query: Meta only. Campaign reads aggregate over ad documents, so a campaign with ZERO ads is normally invisible here, the state the two-step create (campaign, then ads via `existingCampaignId`) leaves behind whenever Meta rejects the ad step. Set true to list those too, with `adCount: 0` and zeroed metrics. Requires `accountId` and `adAccountId`, since an empty campaign has no ad row to resolve a token or ad account from.
- **undefined** (optional): No description
- **limit** (optional) in query: No description
- **source** (optional) in query: `all` (default) returns both Zernio-created ads and those discovered from the platform's ad manager. Matches the web UI's default view. Pass `zernio` to restrict to isExternal=false only. Status is NOT filtered by default; use the `status` param for that.
- **platform** (optional) in query: No description
- **status** (optional) in query: Filter by derived campaign status (post-aggregation)
- **adAccountId** (optional) in query: Platform ad account ID (e.g. act_123 for Meta)
- **pageId** (optional) in query: Meta only: Facebook Page ID. Campaigns have no Page of their own, so this keeps campaigns having at least one ad backed by this Page, with adCount and metrics computed over those ads only. Mirrors the same filter on /v1/ads and /v1/ads/tree.
- **accountId** (optional) in query: Account ID
- **profileId** (optional) in query: Profile ID
- **fromDate** (optional) in query: Start of metrics date range (YYYY-MM-DD, inclusive). Defaults to 90 days ago when both date params are omitted.
- **toDate** (optional) in query: End of metrics date range (YYYY-MM-DD, inclusive). Defaults to today. Max 730-day range.
- **hasDelivery** (optional) in query: Return only campaigns that delivered between `fromDate` and `toDate`: spend above zero, or impressions served at zero spend. Unlike `status`, which reads a campaign's CURRENT state, this filters on what happened inside the window. Filters the campaign set itself, so `pagination.total` counts only matching campaigns. Mirrors the same filter on /v1/ads/tree.
- **minSpend** (optional) in query: Return only campaigns whose spend between `fromDate` and `toDate` reaches this amount, in each campaign's OWN currency (the `currency` field on the campaign). Implies `hasDelivery`; `minSpend=0` applies no filter. Mirrors the same filter on /v1/ads/tree.

### Responses

#### 200: Paginated campaigns

**Response Body:**

- **campaigns** `array[AdCampaign]`: 
- **pagination**: `Pagination` - See schema definition

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

---

## POST /v1/ads/campaigns

**Create a standalone campaign**

Creates a campaign WITHOUT its first ad set / ad, on the platform of the given
`accountId`. Ad sets join it later via `existingCampaignId` on the create endpoints.
Platform notes: on Meta a budget here is campaign-level (CBO) by definition; omit it
for ABO (each ad set carries its own budget), and `specialAdCategories` is Meta-only
(400 elsewhere); `bidStrategy` is Meta and Google (400 elsewhere), and Google also
accepts `portfolioBidStrategyId` instead. Google, X and OpenAI require a budget
(422 without one; OpenAI accepts only `budgetType: lifetime`, Google only
`budgetType: daily`). LinkedIn creates the
campaign GROUP (our campaign level) and rejects a budget, which lives on the
campaign (ad set) level there; it comes back `status: DRAFT`. TikTok campaigns are
created without a status and report `ENABLE`. Created `PAUSED` unless
`status: ACTIVE` where the platform supports it.

**Idempotency:** send an `Idempotency-Key` header to make retries safe.

### Parameters

- **Idempotency-Key** (optional) in header: Optional client-generated unique key (e.g. a UUID) that makes retries safe. Same key + same body replays the original response; same key + different body → 422; key still processing → 409. Only 2xx responses are stored, so a request that failed with a 4xx can be retried with a corrected body under the SAME key.

### Request Body

- **accountId** (required) `string`: Zernio SocialAccount id (posting or ads variant); its platform decides where the campaign is created.
- **adAccountId** (required) `string`: Platform ad account id (Meta act_<n>, Google customer id, LinkedIn account id, ...).
- **name** (required) `string`: No description
- **goal** (required) `string`: Mapped to the ODAX objective (same mapping as POST /v1/ads/create). - one of: engagement, traffic, awareness, video_views, lead_generation, lead_conversion, job_applicants, conversions, app_promotion, catalog_sales, page_likes
- **isSkadnetworkAttribution** `boolean`: Meta app promotion only. Immutable campaign flag. Set true for iOS 14+ SKAdNetwork campaigns and supply promotedObject.applicationId plus promotedObject.objectStoreUrl. The campaign receives promotedObject only when this flag is true. Cannot be changed on an existing campaign.
- **promotedObject**: No description
- **buyingType** `string`: Meta only. Defaults to AUCTION and is explicitly sent on new campaigns, including validateOnly. SKAdNetwork app promotion requires AUCTION. - one of: AUCTION, RESERVED
- **validateOnly** `boolean`: Meta only. Runs campaign validation without creating or persisting a campaign; Idempotency-Key storage is bypassed. Returns HTTP 200 with validateOnly true and status VALIDATED.
- **specialAdCategories** `array`: No description
- **budgetAmount** `number`: Campaign-level (CBO) budget in WHOLE currency units (USD: 50 = $50.00), NOT cents. Meta's own Marketing API takes this same number in minor units, so it is an easy and expensive mix-up. Requires budgetType.
- **budgetType** `string`: No description - one of: daily, lifetime
- **status** `string`: No description - one of: ACTIVE, PAUSED
- **bidStrategy** `string`: Campaign bid strategy. Meta stores `bid_strategy` alongside the budget, so this REQUIRES `budgetAmount` + `budgetType` on the same request; sending it without a campaign budget is a 400. A campaign carrying a strategy without its `bid_amount` makes every ad set created under it fail with an error that names the ad set (code 100, subcode 1815857), so the bad state is rejected up front rather than accepted. To bid at ad-set level on Meta, set the strategy there instead. On Google: LOWEST_COST_WITHOUT_CAP = Maximize Conversions, COST_CAP + bidAmount = Target CPA, LOWEST_COST_WITH_MIN_ROAS + roasAverageFloor = Target ROAS, LOWEST_COST_WITH_BID_CAP + bidAmount = Maximize Clicks with a CPC ceiling; portfolioBidStrategyId attaches a portfolio strategy instead. - one of: LOWEST_COST_WITHOUT_CAP, LOWEST_COST_WITH_BID_CAP, COST_CAP, LOWEST_COST_WITH_MIN_ROAS
- **bidAmount** `number`: Whole currency units (USD: 5 = $5.00). Required for LOWEST_COST_WITH_BID_CAP and COST_CAP; ignored otherwise. On Meta, validated here but NOT stored: the campaign object has no bid_amount field, only bid_strategy lives on it, and the amount takes effect once an ad set joins this campaign (existingCampaignId on POST /v1/ads/create) and supplies its own bidAmount there. On Google, stored directly on the campaign's bidding strategy.
- **roasAverageFloor** `number`: Decimal ROAS multiplier (2.0 = 2.0x). Required for LOWEST_COST_WITH_MIN_ROAS.
- **portfolioBidStrategyId** `string`: Google only. Attach an existing portfolio bid strategy (numeric id from GET /v1/ads/bid-strategies) to the new campaign instead of a standard one. Exclusive with bidStrategy.

### Responses

#### 200: Campaign validation passed without creating a campaign.

**Response Body:**

- **validateOnly** `boolean`: Always true.
- **adAccountId** `string`: No description
- **campaignId** `string`: Empty because no campaign was created.
- **objective** `string`: No description
- **status** `string`: No description

#### 201: Campaign created

**Response Body:**

- **adAccountId** `string`: No description
- **campaignId** `string`: Platform id of the new campaign
- **objective** `string`: Resolved ODAX objective (e.g. OUTCOME_SALES).
- **status** `string`: No description - one of: ACTIVE, PAUSED

#### 400: Invalid input, or Meta rejected the create

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

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

#### 501: Only supported on Meta (facebook/instagram)

---
