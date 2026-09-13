# List Slack workspace members API Reference

Members of the connected Slack workspace that can receive a direct message, for populating a recipient picker. Bots, deactivated members and Slackbot are excluded. Start a DM by passing a member id as `participantId` to POST /v1/inbox/conversations.

## GET /v1/accounts/{accountId}/slack-members

**List Slack workspace members**

Members of the connected Slack workspace that can receive a direct message, for populating a recipient picker. Bots, deactivated members and Slackbot are excluded. Start a DM by passing a member id as `participantId` to POST /v1/inbox/conversations.

### Parameters

- **accountId** (required) in path: No description
- **query** (optional) in query: Case-insensitive filter over display name and handle.
- **limit** (optional) in query: No description

### Responses

#### 200: Workspace members

**Response Body:**

- **members** `array[object]`: 
  - **id** `string`: Slack member id (pass as participantId to start a DM).
  - **name** `string`: Display name
  - **username** `string,null`: Slack handle
  - **picture** `string,null`: Avatar URL

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

#### 404: Slack account not found

---
