# Create reach-frequency prediction API Reference

Creates an R&F prediction. This is a QUOTE, nothing is bought and no ad entities are created.
Provide a date range plus exactly one of `budgetAmount` (Meta predicts reach) or `reach`
(Meta predicts the budget). The response carries the estimate and its allowed bounds
(min/max budget and reach). Predictions expire on their own; to buy, reserve one via
POST /v1/ads/rf-predictions/{predictionId}/reserve and pass the RESERVED id to
POST /v1/ads/create with `buyingType: "RESERVED"`.

Reservation campaigns reject automatic placements. Top-level `placements` wins; when it is
omitted, `targeting.placements` is used; when neither is set, placements default to
Facebook feed (+ Instagram stream when a linked IG professional account resolves).
Instagram placements require that IG account.

## POST /v1/ads/rf-predictions

**Create reach-frequency prediction**

Creates an R&F prediction. This is a QUOTE, nothing is bought and no ad entities are created.
Provide a date range plus exactly one of `budgetAmount` (Meta predicts reach) or `reach`
(Meta predicts the budget). The response carries the estimate and its allowed bounds
(min/max budget and reach). Predictions expire on their own; to buy, reserve one via
POST /v1/ads/rf-predictions/{predictionId}/reserve and pass the RESERVED id to
POST /v1/ads/create with `buyingType: "RESERVED"`.

Reservation campaigns reject automatic placements. Top-level `placements` wins; when it is
omitted, `targeting.placements` is used; when neither is set, placements default to
Facebook feed (+ Instagram stream when a linked IG professional account resolves).
Instagram placements require that IG account.

### Request Body

- **accountId** (required) `string`: Zernio SocialAccount id (posting or ads variant).
- **adAccountId** (required) `string`: Platform ad account id (Meta act_<n>, Google customer id, LinkedIn account id, ...).
- **budgetAmount** `number`: Whole currency units. Exactly one of budgetAmount / reach.
- **reach** `integer`: Target unique reach. Exactly one of budgetAmount / reach.
- **startDate** (required) `string`: Campaign window start (must be in the future).
- **endDate** (required) `string`: No description
- **frequencyCap** `integer`: Max impressions per person over the window.
- **targeting** `object`: Canonical camelCase TargetingSpec (same shape as /v1/ads/create's `targeting`). Defaults to countries: [US].
- **placements** `object`: Meta placements object (same shape as /v1/ads/create's `placements`).

### Responses

#### 201: Prediction created (usually ready within seconds)

**Response Body:**

- **adAccountId** `string`: No description
- **currency** `string`: No description
- **prediction**: `RfPrediction` - See schema definition

#### 400: Invalid input, or Meta rejected the prediction; the message carries Meta's error

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

#### 422: No Facebook Page resolved for the account

#### 501: Only supported on Meta (facebook/instagram)

---
