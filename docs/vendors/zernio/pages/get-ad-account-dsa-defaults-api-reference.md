# Get ad account DSA defaults API Reference

Returns the default DSA beneficiary and payor currently set on a Meta ad account,
whether they were set via `PATCH /v1/ads/accounts` or in Meta Ads Manager. Fields
are omitted when no default is configured. Meta accounts only.


## GET /v1/ads/dsa-defaults

**Get ad account DSA defaults**

Returns the default DSA beneficiary and payor currently set on a Meta ad account,
whether they were set via `PATCH /v1/ads/accounts` or in Meta Ads Manager. Fields
are omitted when no default is configured. Meta accounts only.


### Parameters

- **accountId** (required) in query: Account ID (metaads, or a facebook/instagram posting account)
- **adAccountId** (required) in query: Meta ad account ID (act_...)

### Responses

#### 200: Current DSA defaults (empty object when none are set)

**Response Body:**

- **adAccountId** `string`: No description
- **dsaDefaults** `object`: 
  - **beneficiary** `string`: No description
  - **payor** `string`: No description

#### 400: Non-Meta adAccountId

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

---
