# Start a verification API Reference

Starts a verification for the location. This is a mutating action: depending on `method`, Google mails a postcard, places a call, or sends an SMS/email to the business. Submit the resulting code with POST /gmb-verifications/{verificationId}/complete. Use POST /gmb-verifications/options first to discover which methods are eligible.

## GET /v1/accounts/{accountId}/gmb-verifications

**Get verification state**

Returns the location's Voice of Merchant state plus its verification history. `voiceOfMerchantState.hasVoiceOfMerchant` tells you whether the listing is verified and published; when it is false, `verify` reports whether a verification is already pending. Each entry in `verifications` has a `state` of PENDING, COMPLETED, or FAILED.

### Parameters

- **accountId** (required) in path: The Zernio account ID (from /v1/accounts)
- **locationId** (optional) in query: Override which location to query. If omitted, uses the account's selected location. Use GET /gmb-locations to list valid IDs.

### Responses

#### 200: Verification state fetched successfully

**Response Body:**

- **success** `boolean`: No description
- **accountId** `string`: No description
- **locationId** `string`: No description
- **voiceOfMerchantState** `object`: Raw Voice of Merchant state from Google.
  - **hasVoiceOfMerchant** `boolean`: True when the listing is verified and published (eligible to surface reviews, edits, etc.).
  - **hasBusinessAuthority** `boolean`: True when the authenticated user has owner/manager authority over the listing.
  - **verify** `object`: Present when verification is the path to Voice of Merchant.
    - **hasPendingVerification** `boolean`: True when a verification is already in progress.
- **verifications** `array[object]`: Verification history, newest first. Empty when none exist.
  - **name** `string`: Resource name, e.g. "locations/123/verifications/0T1776879124712". The last segment is the verificationId.
  - **method** `string`: Method used (omitted on some entries). - one of: ADDRESS, EMAIL, PHONE_CALL, SMS, AUTO, VETTED_PARTNER
  - **state** `string`: No description - one of: PENDING, COMPLETED, FAILED
  - **createTime** `string` (date-time): No description

#### 400: Not a Google Business Profile account or missing location

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

## POST /v1/accounts/{accountId}/gmb-verifications

**Start a verification**

Starts a verification for the location. This is a mutating action: depending on `method`, Google mails a postcard, places a call, or sends an SMS/email to the business. Submit the resulting code with POST /gmb-verifications/{verificationId}/complete. Use POST /gmb-verifications/options first to discover which methods are eligible.

### Parameters

- **accountId** (required) in path: The Zernio account ID (from /v1/accounts)
- **locationId** (optional) in query: Override which location to target. If omitted, uses the account's selected location.

### Request Body

- **method** (required) `string`: The verification method. Selects which method-specific field below is required. - one of: ADDRESS, EMAIL, PHONE_CALL, SMS, AUTO, VETTED_PARTNER
- **languageCode** `string`: No description
- **phoneNumber** `string`: For PHONE_CALL / SMS.
- **emailAddress** `string`: For EMAIL.
- **mailerContact** `object`: For ADDRESS (postcard) verification.
- **context** `object`: ServiceBusinessContext (e.g. service address). Required for service-area businesses.

### Responses

#### 200: Verification started

**Response Body:**

- **success** `boolean`: No description
- **accountId** `string`: No description
- **locationId** `string`: No description
- **verification** `object`: 
  - **name** `string`: No description
  - **method** `string`: No description - one of: ADDRESS, EMAIL, PHONE_CALL, SMS, AUTO, VETTED_PARTNER
  - **state** `string`: No description - one of: PENDING, COMPLETED, FAILED
  - **createTime** `string` (date-time): No description

#### 400: Invalid request (e.g. wrong field for the chosen method, or Google rejected it)

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
