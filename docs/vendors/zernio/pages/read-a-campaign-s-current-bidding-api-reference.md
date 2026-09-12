# Read a campaign's current bidding API Reference

Read of the campaign's bidding strategy on Google, cached for the quota window, for
pre-filling the bid strategy block before a PUT to /v1/ads/campaigns/{campaignId}.
Google Ads only; `platform` is required and rejected when it is anything else, since
a `campaignId` is not globally unique. The response carries `cachedAt` and `stale`,
set when a quota-exhausted call falls back to the last-good copy instead of a live
read.

Maps Google's bidding strategy onto the same triplet PUT accepts: `LOWEST_COST_WITHOUT_CAP`
(Maximize Conversions, no target), `COST_CAP` + `bidAmount` (Target CPA), `LOWEST_COST_WITH_MIN_ROAS`
+ `roasAverageFloor` (Target ROAS), `LOWEST_COST_WITH_BID_CAP` + `bidAmount` (Maximize Clicks with
a CPC ceiling). A campaign on a portfolio strategy returns `portfolio` (id + name) and
`bidSpec.portfolioBidStrategyId` instead of the triplet. Anything else (Manual CPC, Target
Impression Share, ...) returns `bidSpec: null`; show `biddingStrategyType` as-is.


## GET /v1/ads/campaigns/{campaignId}/bidding

**Read a campaign's current bidding**

Read of the campaign's bidding strategy on Google, cached for the quota window, for
pre-filling the bid strategy block before a PUT to /v1/ads/campaigns/{campaignId}.
Google Ads only; `platform` is required and rejected when it is anything else, since
a `campaignId` is not globally unique. The response carries `cachedAt` and `stale`,
set when a quota-exhausted call falls back to the last-good copy instead of a live
read.

Maps Google's bidding strategy onto the same triplet PUT accepts: `LOWEST_COST_WITHOUT_CAP`
(Maximize Conversions, no target), `COST_CAP` + `bidAmount` (Target CPA), `LOWEST_COST_WITH_MIN_ROAS`
+ `roasAverageFloor` (Target ROAS), `LOWEST_COST_WITH_BID_CAP` + `bidAmount` (Maximize Clicks with
a CPC ceiling). A campaign on a portfolio strategy returns `portfolio` (id + name) and
`bidSpec.portfolioBidStrategyId` instead of the triplet. Anything else (Manual CPC, Target
Impression Share, ...) returns `bidSpec: null`; show `biddingStrategyType` as-is.


### Parameters

- **campaignId** (required) in path: Numeric Google platform campaign id.
- **accountId** (required) in query: Zernio Google Ads SocialAccount id: resolves the customer id + refresh token.
- **platform** (required) in query: Required: campaign IDs are not globally unique. Only "google" is supported today.
- **customerId** (optional) in query: Numeric Google Ads customer id (no dashes). Required when the connection has multiple Google Ads accounts; optional (and inferred) when it has only one.

### Responses

#### 200: Campaign bidding

**Response Body:**

- **campaignId** `string`: No description
- **channel** `string`: campaign.advertising_channel_type. COST_CAP's underlying Google field differs by channel; see bidStrategy on PUT. - one of: SEARCH, DISPLAY
- **biddingStrategyType** `string`: Google's raw enum: MAXIMIZE_CONVERSIONS, TARGET_CPA, MAXIMIZE_CONVERSION_VALUE, TARGET_ROAS, TARGET_SPEND, MANUAL_CPC, TARGET_IMPRESSION_SHARE, or another Google adds later.
- **bidSpec** `object,null`: Null when the campaign is on a strategy PUT does not model (Manual CPC, Target Impression Share, ...); show biddingStrategyType instead in that case.
- **portfolio** `object,null`: Set only when the campaign is on a portfolio bid strategy (campaign.bidding_strategy); null otherwise.
- **cachedAt** `string,null` (date-time): When this data was fetched from Google. Null when it was never served from cache.
- **stale** `boolean`: True when Google's daily API quota was exhausted and this is the last successful fetch, not a live read.

#### 400: Invalid input (accountId, customerId, or a non-numeric campaignId), or a platform other than "google"

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

#### 501: Not a Google Ads account: the connection behind accountId resolves to another platform.

---
