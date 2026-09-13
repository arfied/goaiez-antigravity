# Get conversation analytics API Reference

Per-conversation inbox analytics. The inbox analog of
/v1/analytics/post-timeline: one conversation, daily totals,
source mix.

The {conversationId} path param accepts EITHER the Mongo `_id` of
the Conversation document OR its `platformConversationId` (the
same identity used by metadata.conversationId at ingest time).
Ownership is verified in MongoDB against the caller's team
before the Tinybird query fires.

Max date range is 365 days.


## GET /v1/analytics/inbox/conversations/{conversationId}

**Get conversation analytics**

Per-conversation inbox analytics. The inbox analog of
/v1/analytics/post-timeline: one conversation, daily totals,
source mix.

The {conversationId} path param accepts EITHER the Mongo `_id` of
the Conversation document OR its `platformConversationId` (the
same identity used by metadata.conversationId at ingest time).
Ownership is verified in MongoDB against the caller's team
before the Tinybird query fires.

Max date range is 365 days.


### Parameters

- **conversationId** (required) in path: Mongo _id or platformConversationId.
- **fromDate** (required) in query: No description
- **toDate** (optional) in query: No description

### Responses

#### 200: Per-conversation analytics

**Response Body:**

- **success** `boolean`: No description
- **conversationId** `string`: The platformConversationId
- **mongoId** `string`: No description
- **platform** `string,null`: No description
- **from** `string` (date): No description
- **to** `string,null` (date): No description
- **summary** `object`: 
  - **received** `integer`: No description
  - **sent** `integer`: No description
  - **read** `integer`: No description
  - **failed** `integer`: No description
  - **totalMessages** `integer`: No description
  - **firstMessageAt** `string,null` (date-time): No description
  - **lastMessageAt** `string,null` (date-time): No description
- **timeseries** `array[object]`: 
  - **date** `string` (date): No description
  - **sent** `integer`: No description
  - **received** `integer`: No description
  - **read** `integer`: No description
  - **failed** `integer`: No description
- **bySource** `array[object]`: 
  - **source** `string`: (unspecified) for legacy rows with no metadata.source
  - **count** `integer`: No description

#### 400: Validation error

**Response Body:**

- **error** `string`: No description
- **details** `object`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Conversation not found or not owned by the caller's team

**Response Body:**

- **error** `string`: No description (example: "Conversation not found.")
- **code** `string`: No description (example: "conversation_not_found")

#### 500: Internal server error

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

---
