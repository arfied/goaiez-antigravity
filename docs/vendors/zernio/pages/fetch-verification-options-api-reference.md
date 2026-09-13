# Fetch verification options API Reference

Reports the verification methods Google currently offers for the location. Non-mutating (nothing is sent to the business). `languageCode` is required; service-area ("CUSTOMER_LOCATION_ONLY") businesses also require `context.address`, otherwise Google returns 400.

## POST /v1/accounts/{accountId}/gmb-verifications/options

**Fetch verification options**

Reports the verification methods Google currently offers for the location. Non-mutating (nothing is sent to the business). `languageCode` is required; service-area ("CUSTOMER_LOCATION_ONLY") businesses also require `context.address`, otherwise Google returns 400.

### Parameters

- **accountId** (required) in path: The Zernio account ID (from /v1/accounts)
- **locationId** (optional) in query: Override which location to query. If omitted, uses the account's selected location.

### Request Body

- **languageCode** (required) `string`: No description
- **context** `object`: ServiceBusinessContext. Required for service-area businesses (must include the service address).

### Responses

#### 200: Verification options fetched

**Response Body:**

- **success** `boolean`: No description
- **accountId** `string`: No description
- **locationId** `string`: No description
- **options** `array[object]`: 
  - **verificationMethod** `string`: No description - one of: ADDRESS, EMAIL, PHONE_CALL, SMS, AUTO, VETTED_PARTNER
  - **phoneNumber** `string`: Present for PHONE_CALL / SMS.

#### 400: Invalid request (e.g. missing service business context, or missing languageCode)

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

#### 401: Unauthorized or token invalid

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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---
