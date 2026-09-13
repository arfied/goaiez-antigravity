# Check Telegram status API Reference

Poll this endpoint to check if a Telegram access code has been used to connect a channel/group. Recommended polling interval: 3 seconds.
Status values: pending (waiting for user), connected (channel/group linked), expired (generate a new code).


## GET /v1/connect/telegram

**Generate Telegram code**

Generate an access code (valid 15 minutes) for connecting a Telegram channel or group. Add the bot as admin, then send the code + @yourchannel to the bot. Poll PATCH /v1/connect/telegram to check status.

### Parameters

- **profileId** (required) in query: The profile ID to connect the Telegram account to

### Responses

#### 200: Access code generated

**Response Body:**

- **code** `string`: The access code to send to the Telegram bot (example: "ZRN-ABC123")
- **expiresAt** `string` (date-time): When the code expires
- **expiresIn** `integer`: Seconds until expiration (example: 900)
- **botUsername** `string`: The Telegram bot username to message (example: "LateScheduleBot")
- **instructions** `array[string]`: Step-by-step connection instructions

#### 400: Profile ID required or invalid format

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: No access to this profile

#### 404: Profile not found

#### 500: Internal error

---

## POST /v1/connect/telegram

**Connect Telegram directly**

Connect a Telegram channel/group directly using the chat ID. Alternative to the access code flow. The bot must already be an admin in the channel/group.

### Request Body

- **chatId** (required) `string`: The Telegram chat ID. Numeric ID (e.g. "-1001234567890") or username with @ prefix (e.g. "@mychannel").
- **profileId** (required) `string`: The profile ID to connect the account to

### Responses

#### 200: Telegram channel connected successfully

**Response Body:**

- **message** `string`: No description
- **account** `object`: 
  - **_id** `string`: No description
  - **platform** `string`: No description - one of: telegram
  - **username** `string`: No description
  - **displayName** `string`: No description
  - **isActive** `boolean`: No description
  - **chatType** `string`: No description - one of: channel, group, supergroup, private

#### 400: Chat ID required, bot not admin, or cannot access chat

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: No access to this profile

#### 404: Profile not found

#### 500: Internal error

---

## PATCH /v1/connect/telegram

**Check Telegram status**

Poll this endpoint to check if a Telegram access code has been used to connect a channel/group. Recommended polling interval: 3 seconds.
Status values: pending (waiting for user), connected (channel/group linked), expired (generate a new code).


### Parameters

- **code** (required) in query: The access code to check status for

### Responses

#### 200: Connection status

**Response Body:**

*One of the following:*
  - **status** `string`: No description - one of: pending
  - **expiresAt** `string` (date-time): No description
  - **expiresIn** `integer`: Seconds until expiration
  - **status** `string`: No description - one of: connected
  - **chatId** `string`: No description
  - **chatTitle** `string`: No description
  - **chatType** `string`: No description - one of: channel, group, supergroup
  - **account** `object`: 
    - **_id** `string`: No description
    - **platform** `string`: No description
    - **username** `string`: No description
    - **displayName** `string`: No description
  - **status** `string`: No description - one of: expired
  - **message** `string`: No description

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

#### 404: Code not found

#### 500: Internal error

---
