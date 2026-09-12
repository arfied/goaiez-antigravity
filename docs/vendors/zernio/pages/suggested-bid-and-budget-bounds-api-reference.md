# Suggested bid and budget bounds API Reference

LinkedIn-only. Returns the suggested bid and bid limits for a targeting
spec, plus the daily-budget bounds LinkedIn will accept. Use it before
creating a campaign to pick a bid inside the allowed range and warn the
user if their daily budget is below the minimum. Wraps LinkedIn's
`adBudgetPricing` finder.

Non-LinkedIn accounts return `available: false` so clients can hide the
pricing UI without treating it as a failure.


## POST /v1/ads/targeting/bid-pricing

**Suggested bid and budget bounds**

LinkedIn-only. Returns the suggested bid and bid limits for a targeting
spec, plus the daily-budget bounds LinkedIn will accept. Use it before
creating a campaign to pick a bid inside the allowed range and warn the
user if their daily budget is below the minimum. Wraps LinkedIn's
`adBudgetPricing` finder.

Non-LinkedIn accounts return `available: false` so clients can hide the
pricing UI without treating it as a failure.


### Request Body

- **accountId** (required) `string`: Zernio account ID (LinkedIn).
- **adAccountId** (required) `string`: LinkedIn ad account ID (numeric).
- **spec** (required): Same targeting spec used by POST /v1/ads/create.
- **campaignType** `string`: Defaults to SPONSORED_UPDATES. - one of: TEXT_AD, SPONSORED_UPDATES, SPONSORED_INMAILS
- **bidType** `string`: Defaults to CPM. - one of: CPM, CPC, CPV
- **matchType** `string`: Defaults to EXACT. - one of: EXACT, AUDIENCE_EXPANDED
- **currency** `string`: ISO 4217, defaults to USD.
- **objectiveType** `string`: LinkedIn objectiveType, e.g. WEBSITE_VISIT, LEAD_GENERATION, VIDEO_VIEW.
- **optimizationTargetType** `string`: LinkedIn optimizationTargetType, e.g. MAX_CLICK, MAX_IMPRESSION.
- **dailyBudget** `number`: Optional daily budget in whole account-currency units. LinkedIn refines the suggested bid to this budget.

### Responses

#### 200: Pricing insights

**Response Body:**

- **available** (required) `boolean`: No description
- **pricing** `object,null`: LinkedIn's adBudgetPricing element. Null when LinkedIn has no data for the combination.

#### 400: Invalid targeting or unsupported objective/optimization/bid combination.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required.

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
