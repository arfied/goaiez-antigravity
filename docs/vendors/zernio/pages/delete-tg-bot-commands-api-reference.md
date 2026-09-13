# Delete TG bot commands API Reference

Clears all bot commands configured for a Telegram bot account.

## GET /v1/accounts/{accountId}/telegram-commands

**Get TG bot commands**

Get the bot commands configuration for a Telegram account.

### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: Bot commands list

**Response Body:**

- **data** `array[object]`: 
  - **command** `string`: No description
  - **description** `string`: No description

#### 400: Not a Telegram account

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

## PUT /v1/accounts/{accountId}/telegram-commands

**Set TG bot commands**

Set bot commands for a Telegram account.

### Parameters

- **accountId** (required) in path: No description

### Request Body

- **commands** (required) `array`: No description

### Responses

#### 200: Commands set successfully

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

## DELETE /v1/accounts/{accountId}/telegram-commands

**Delete TG bot commands**

Clears all bot commands configured for a Telegram bot account.

### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: Commands deleted

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
