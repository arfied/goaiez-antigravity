# Complete a verification API Reference

Completes a PENDING verification by submitting the PIN/code Google sent the business (postcard code, SMS PIN, etc.). On success the verification moves to COMPLETED.

## POST /v1/accounts/{accountId}/gmb-verifications/{verificationId}/complete

**Complete a verification**

Completes a PENDING verification by submitting the PIN/code Google sent the business (postcard code, SMS PIN, etc.). On success the verification moves to COMPLETED.

### Parameters

- **accountId** (required) in path: The Zernio account ID (from /v1/accounts)
- **verificationId** (required) in path: The last segment of a verification `name` from GET /gmb-verifications.
- **locationId** (optional) in query: Override which location to target. If omitted, uses the account's selected location.

### Request Body

- **pin** (required) `string`: The code Google sent to the business.

### Responses

#### 200: Verification completed

**Response Body:**

- **success** `boolean`: No description
- **accountId** `string`: No description
- **locationId** `string`: No description
- **verification** `object`: 
  - **name** `string`: No description
  - **method** `string`: No description - one of: ADDRESS, EMAIL, PHONE_CALL, SMS, AUTO, VETTED_PARTNER
  - **state** `string`: No description - one of: PENDING, COMPLETED, FAILED
  - **createTime** `string` (date-time): No description

#### 400: Invalid request (e.g. wrong PIN or verification not pending)

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
