# Set IG ice breakers API Reference

Set ice breakers for an Instagram account. Max 4 ice breakers, question max 80 chars.

## GET /v1/accounts/{accountId}/instagram-ice-breakers

**Get IG ice breakers**

Get the ice breaker configuration for an Instagram account.

### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: Ice breaker configuration

**Response Body:**

- **data** `array[object]`: 
  Type: `object`

#### 400: Not an Instagram account

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

## PUT /v1/accounts/{accountId}/instagram-ice-breakers

**Set IG ice breakers**

Set ice breakers for an Instagram account. Max 4 ice breakers, question max 80 chars.

### Parameters

- **accountId** (required) in path: No description

### Request Body

- **ice_breakers** (required) `array`: No description

### Responses

#### 200: Ice breakers set successfully

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

## DELETE /v1/accounts/{accountId}/instagram-ice-breakers

**Delete IG ice breakers**

Removes the ice breaker questions from an Instagram account's Messenger experience.

### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: Ice breakers deleted

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

---
