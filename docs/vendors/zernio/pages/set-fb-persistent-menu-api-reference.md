# Set FB persistent menu API Reference

Set the persistent menu for a Facebook Messenger account. Max 3 top-level items, max 5 nested items.

## GET /v1/accounts/{accountId}/messenger-menu

**Get FB persistent menu**

Get the persistent menu configuration for a Facebook Messenger account.

### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: Persistent menu configuration

**Response Body:**

- **data** `array[object]`: 
  Type: `object`

#### 400: Not a Facebook account

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

## PUT /v1/accounts/{accountId}/messenger-menu

**Set FB persistent menu**

Set the persistent menu for a Facebook Messenger account. Max 3 top-level items, max 5 nested items.

### Parameters

- **accountId** (required) in path: No description

### Request Body

- **persistent_menu** (required) `array`: Persistent menu configuration array (Meta format)

### Responses

#### 200: Menu set successfully

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

## DELETE /v1/accounts/{accountId}/messenger-menu

**Delete FB persistent menu**

Removes the persistent menu from Facebook Messenger conversations for this account.

### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: Menu deleted

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
