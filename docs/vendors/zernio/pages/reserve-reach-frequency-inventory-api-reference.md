# Reserve reach-frequency inventory API Reference

Locks the quoted price + inventory until the returned `expiresAt` and mints a NEW
prediction id. Pass that RESERVED id (not the original) as `rfPredictionId` on
POST /v1/ads/create. Release an unused reservation via DELETE.

## POST /v1/ads/rf-predictions/{predictionId}/reserve

**Reserve reach-frequency inventory**

Locks the quoted price + inventory until the returned `expiresAt` and mints a NEW
prediction id. Pass that RESERVED id (not the original) as `rfPredictionId` on
POST /v1/ads/create. Release an unused reservation via DELETE.

### Parameters

- **predictionId** (required) in path: No description

### Request Body

- **accountId** (required) `string`: No description
- **adAccountId** (required) `string`: No description

### Responses

#### 201: Reserved; `prediction.predictionId` is the new RESERVED id

**Response Body:**

- **adAccountId** `string`: No description
- **prediction**: `RfPrediction` - See schema definition

#### 400: Invalid input, or Meta rejected the reserve

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
