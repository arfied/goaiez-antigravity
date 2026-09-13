# Get Slack account settings API Reference

Returns the connected Slack channel details and the default message identity (name and avatar shown as the author on every post, with Slack's APP badge). The identity applies to messages only; the app's own Slack profile is global and cannot be changed per workspace.

## GET /v1/accounts/{accountId}/slack-settings

**Get Slack account settings**

Returns the connected Slack channel details and the default message identity (name and avatar shown as the author on every post, with Slack's APP badge). The identity applies to messages only; the app's own Slack profile is global and cannot be changed per workspace.

### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: Slack account settings

**Response Body:**

- **account** `object`: 
  - **_id** `string`: No description
  - **platform** `string`: No description (example: "slack")
  - **displayName** `string,null`: No description
  - **channelId** `string,null`: No description
  - **channelName** `string,null`: No description
  - **channelType** `string,null`: public or private
  - **teamId** `string,null`: No description
  - **teamName** `string,null`: No description
  - **defaultUsername** `string,null`: No description
  - **defaultIconUrl** `string,null`: No description

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

#### 404: Account not found

---

## PATCH /v1/accounts/{accountId}/slack-settings

**Update Slack account settings**

Set or clear the default message identity for this channel. Empty string clears a field; per-post platformSpecificData.username/iconUrl still override these defaults.

### Parameters

- **accountId** (required) in path: No description

### Request Body

- **defaultUsername** `string`: Author name shown on posts. Empty string clears it.
- **defaultIconUrl** `string`: Author avatar image URL. Empty string clears it.

### Responses

#### 200: Updated settings

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

#### 404: Account not found

---
