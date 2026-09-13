# Associate campaigns API Reference

Associate one or more campaigns with this conversion rule. Returns a
per-campaign success/failure result so callers can retry only the
rows that failed (e.g. wrong campaign type for the rule's objective).


## GET /v1/accounts/{accountId}/conversion-destinations/{destinationId}/associations

**List associated campaigns**

LinkedIn-only today. Returns the campaigns currently associated with
this conversion rule. Auto-association on rule creation
runs once at create time; campaigns created after the rule still need
explicit association.


### Parameters

- **accountId** (required) in path: No description
- **destinationId** (required) in path: No description
- **adAccountId** (required) in query: No description

### Responses

#### 200: Associations listed

**Response Body:**

- **platform** `string`: No description - one of: linkedinads
- **associations** `array[object]`: 
  - **campaignId** `string`: No description
  - **conversionId** `string`: No description
  - **associatedAt** `integer`: Epoch ms.

#### 400: Validation error.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads add-on or LinkedIn reconnect required.

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

#### 405: Platform does not support associations.

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

#### 429: LinkedIn rate limit hit. Retry with backoff.

---

## POST /v1/accounts/{accountId}/conversion-destinations/{destinationId}/associations

**Associate campaigns**

Associate one or more campaigns with this conversion rule. Returns a
per-campaign success/failure result so callers can retry only the
rows that failed (e.g. wrong campaign type for the rule's objective).


### Parameters

- **accountId** (required) in path: No description
- **destinationId** (required) in path: No description

### Request Body

- **adAccountId** (required) `string`: No description
- **campaignIds** (required) `array`: No description

### Responses

#### 200: Per-campaign batch result. Status is 200 even when some rows
failed. Inspect `failed[]` for details. Inputs that fail local
URN validation are bucketed into `failed` without ever hitting
LinkedIn.


**Response Body:**

- **platform** `string`: No description - one of: linkedinads
- **succeeded** `array[string]`: Numeric campaign IDs that were successfully associated.
- **failed** `array[object]`: 
  - **campaignId** `string`: No description
  - **reason** `string`: No description

#### 400: Invalid body.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads add-on or LinkedIn reconnect required.

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

#### 405: Platform does not support associations.

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

#### 429: LinkedIn rate limit hit. Retry with backoff.

---

## DELETE /v1/accounts/{accountId}/conversion-destinations/{destinationId}/associations

**Remove associated campaigns**

Remove one or more campaign associations from this conversion rule.
Pass `adAccountId` and `campaignIds` as query parameters
(`campaignIds` is comma-separated). The route also accepts a JSON
body with the same fields for clients that prefer DELETE-with-body,
but the documented surface is query-only because some SDK code
generators (e.g. Python) collapse query + body parameters with the
same name into a single kwarg.


### Parameters

- **accountId** (required) in path: No description
- **destinationId** (required) in path: No description
- **adAccountId** (required) in query: No description
- **campaignIds** (required) in query: Comma-separated list of campaign IDs.

### Responses

#### 200: Per-campaign batch result. Status is 200 even when some rows
failed. Inspect `failed[]` for details.


**Response Body:**

- **platform** `string`: No description - one of: linkedinads
- **succeeded** `array[string]`: Numeric campaign IDs that were successfully removed.
- **failed** `array[object]`: 
  - **campaignId** `string`: No description
  - **reason** `string`: No description

#### 400: Validation error: missing `adAccountId` or `campaignIds`,
campaignIds exceeds 100 entries per request, or `accountId` is not
a valid id.


#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads add-on or LinkedIn reconnect required.

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

#### 405: Platform does not support associations.

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

#### 429: LinkedIn rate limit hit. Retry with backoff.

---
