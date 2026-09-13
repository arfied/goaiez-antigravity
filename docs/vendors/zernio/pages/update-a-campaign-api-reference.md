# Update a campaign API Reference

Campaign-level edits. Send at least one of `budget`, `bidStrategy`,
`portfolioBidStrategyId`, `name` or `platformSpecificData`. An unsupported
field is always an error, never a silent drop.

| Body field | Meta | Google | Others |
|---|---|---|---|
| `bidStrategy` | Yes | Yes | 501 |
| `bidAmount`, `roasAverageFloor` | 400 (ad-set level) | Yes | 400 |
| `portfolioBidStrategyId` | 400 | Yes | 400 |
| `budget` (CBO; ABO returns 409) | Yes | Daily only | 501 |
| `name` | Yes | 501 | 501 |
| `platformSpecificData.spendCap` | Yes | 400 | 400 |
| `accountId` (empty campaigns) | Yes | - | - |

Meta budget edits check the live campaign budget, so an older local ABO stamp
cannot block a CBO campaign. A successful edit repairs local ad budget fields.
A live ABO campaign still returns 409 with the ad-set budget endpoint.

On Google: `LOWEST_COST_WITHOUT_CAP` = Maximize Conversions, `COST_CAP` +
`bidAmount` = Target CPA, `LOWEST_COST_WITH_MIN_ROAS` + `roasAverageFloor` =
Target ROAS, `LOWEST_COST_WITH_BID_CAP` + `bidAmount` = Maximize Clicks with a
CPC ceiling; `portfolioBidStrategyId` attaches a portfolio strategy instead
(exclusive with `bidStrategy`). Setting the standard triplet on a campaign that
is currently on a PORTFOLIO strategy is rejected: detach it in Google Ads
first, since it is shared across campaigns.

Google budget updates read the current budget before mutation. Shared budgets return
409 unless allowSharedBudgetUpdate=true is explicitly supplied, because the change
affects every campaign using that budget. Unknown sharing state also returns 409.

`accountId` forwards the update straight to Meta for a campaign with zero ads,
which would otherwise 404; the response then carries `updated: 0`.


## GET /v1/ads/campaigns/{campaignId}

**Get live campaign details**

Reads one campaign live from Meta, returned verbatim, so a caller that knows a
campaign id no longer has to page `GET /v1/ads/campaigns` to find it. The default
projection covers name, status, objective, buying type, bid strategy, budgets,
spend cap, schedule and `issues_info`. `fields` is a raw-passthrough override;
unknown fields return Meta's 400 verbatim. A campaign the resolved connection
cannot see comes back as Meta's own 400, not a 404.

### Parameters

- **campaignId** (required) in path: Meta campaign id (platformCampaignId).
- **accountId** (required) in query: Zernio SocialAccount id (posting or ads variant) used to resolve the Meta token.
- **fields** (optional) in query: Comma-separated Graph field override. Supports nested {} projections and Graph field modifiers.

### Responses

#### 200: The campaign as returned by Meta

**Response Body:**

- **campaign** `object`: Raw Meta campaign; keys are the requested Graph fields.

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

## PUT /v1/ads/campaigns/{campaignId}

**Update a campaign**

Campaign-level edits. Send at least one of `budget`, `bidStrategy`,
`portfolioBidStrategyId`, `name` or `platformSpecificData`. An unsupported
field is always an error, never a silent drop.

| Body field | Meta | Google | Others |
|---|---|---|---|
| `bidStrategy` | Yes | Yes | 501 |
| `bidAmount`, `roasAverageFloor` | 400 (ad-set level) | Yes | 400 |
| `portfolioBidStrategyId` | 400 | Yes | 400 |
| `budget` (CBO; ABO returns 409) | Yes | Daily only | 501 |
| `name` | Yes | 501 | 501 |
| `platformSpecificData.spendCap` | Yes | 400 | 400 |
| `accountId` (empty campaigns) | Yes | - | - |

Meta budget edits check the live campaign budget, so an older local ABO stamp
cannot block a CBO campaign. A successful edit repairs local ad budget fields.
A live ABO campaign still returns 409 with the ad-set budget endpoint.

On Google: `LOWEST_COST_WITHOUT_CAP` = Maximize Conversions, `COST_CAP` +
`bidAmount` = Target CPA, `LOWEST_COST_WITH_MIN_ROAS` + `roasAverageFloor` =
Target ROAS, `LOWEST_COST_WITH_BID_CAP` + `bidAmount` = Maximize Clicks with a
CPC ceiling; `portfolioBidStrategyId` attaches a portfolio strategy instead
(exclusive with `bidStrategy`). Setting the standard triplet on a campaign that
is currently on a PORTFOLIO strategy is rejected: detach it in Google Ads
first, since it is shared across campaigns.

Google budget updates read the current budget before mutation. Shared budgets return
409 unless allowSharedBudgetUpdate=true is explicitly supplied, because the change
affects every campaign using that budget. Unknown sharing state also returns 409.

`accountId` forwards the update straight to Meta for a campaign with zero ads,
which would otherwise 404; the response then carries `updated: 0`.


### Parameters

- **campaignId** (required) in path: Platform campaign ID

### Request Body

- **platform** (required) `string`: Required: platform campaign IDs are not globally unique. - one of: facebook, instagram, google
- **accountId** `string`: **Meta only.** Zernio SocialAccount id owning the ad account. Needed only for an EMPTY campaign (zero ads); ignored otherwise.
- **bidStrategy**: **Meta + Google.** On Meta, the campaign default that ad sets inherit unless they override it. On Google, the campaign's own bidding strategy. On Google: LOWEST_COST_WITHOUT_CAP = Maximize Conversions, COST_CAP + bidAmount = Target CPA, LOWEST_COST_WITH_MIN_ROAS + roasAverageFloor = Target ROAS, LOWEST_COST_WITH_BID_CAP + bidAmount = Maximize Clicks with a CPC ceiling; portfolioBidStrategyId attaches a portfolio strategy instead.
- **bidAmount** `number`: **Google only.** Whole currency units (USD: 12 = $12.00). Max CPC for LOWEST_COST_WITH_BID_CAP, CPA target for COST_CAP; required for both.
- **roasAverageFloor** `number`: **Google only.** Decimal ROAS multiplier (2.0 = 2.0x), required for LOWEST_COST_WITH_MIN_ROAS.
- **portfolioBidStrategyId** `string`: **Google only.** Attach an existing portfolio bid strategy (numeric id from GET /v1/ads/bid-strategies) instead of setting bidStrategy. Exclusive with bidStrategy.
- **allowSharedBudgetUpdate** `boolean`: Google only. Explicitly allow changing a shared campaign budget, affecting every campaign that uses it. Does not bypass an unknown sharing state.
- **budget** `object`: Meta CBO or Google daily campaign budget, in whole currency units.
- **name** `string`: **Meta only.** Rename the campaign.
- **platformSpecificData** `object`: **Meta only.** Platform implied by the `platform` body param, same convention as POST /v1/ads/create.

### Responses

#### 200: Campaign updated

**Response Body:**

- **updated** `integer`: Local Ad documents mirrored. 0 on the empty-campaign path.
- **budget**: `AdCampaignBudget` - See schema definition
- **budgetLevel** `string`: No description - one of: campaign
- **bidStrategy**: `BidStrategy` - See schema definition
- **bidAmount** `number`: No description
- **roasAverageFloor** `number`: No description
- **portfolioBidStrategyId** `string`: Google only. Echoed back, but NOT mirrored onto local Ad documents (no column for it yet).
- **platformSpecificData** `object`: No description

#### 400: Invalid input, or a field the resolved platform does not support at the campaign level (see the support table)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Returned with code `ads_allowance_exceeded` when the team has no payment method on file and has reached the 500 free live ads: add a card to resume.

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

#### 409: Meta campaign is ABO, or the Google budget is shared without allowSharedBudgetUpdate=true, or sharing state cannot be verified. The account may also be inactive or need reconnection (code ads_connection_required). Reconnect it and read GET /v1/accounts for its current ID before retrying.

#### 501: Operation not supported on this platform

---

## DELETE /v1/ads/campaigns/{campaignId}

**Delete a campaign**

Deletes the whole campaign on the platform, cascading to its ad sets
and ads. Locally, all Ad documents for this campaign are marked
`status: cancelled`.

**Empty campaigns.** A campaign with zero ads has no local Ad documents
to resolve, so it is invisible to `/v1/ads/tree` and this endpoint would
404. That state is produced by the two-step create flow (campaign, then
ads via `existingCampaignId`) whenever Meta rejects the ad step. To
delete such a shell, send `accountId` in the body: we skip the local
lookup entirely and forward the delete to Meta. `accountId` is ignored
when the campaign does have ads.


### Parameters

- **campaignId** (required) in path: Platform campaign ID

### Request Body

- **platform** (required) `string`: No description - one of: facebook, instagram, google
- **accountId** `string`: Zernio SocialAccount id owning the ad account. Required only to delete an EMPTY campaign (zero ads), which has no local Ad documents to resolve a token from.

### Responses

#### 200: Campaign deleted

**Response Body:**

- **deleted** `boolean`: No description
- **adCount** `integer`: Number of local Ad docs marked cancelled

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

#### 501: Operation not supported on this platform

---
