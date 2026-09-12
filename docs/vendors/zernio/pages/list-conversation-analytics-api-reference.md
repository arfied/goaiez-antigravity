# List conversation analytics API Reference

Per-conversation listing with per-row totals + first/last message
timestamps. The inbox analog of GET /v1/analytics (posts listing):
same filter shape, same pagination, same sort/order semantics.
Use as the entry point for the per-conversation analytics drawer
at /v1/analytics/inbox/conversations/{conversationId}.

Rows are enriched with the conversation's participant info
(`participantName`, `participantUsername`, `participantPicture`)
and last-message preview by joining the Conversation document
scoped to the caller's team. Max date range is 365 days.


## GET /v1/analytics/inbox/conversations

**List conversation analytics**

Per-conversation listing with per-row totals + first/last message
timestamps. The inbox analog of GET /v1/analytics (posts listing):
same filter shape, same pagination, same sort/order semantics.
Use as the entry point for the per-conversation analytics drawer
at /v1/analytics/inbox/conversations/{conversationId}.

Rows are enriched with the conversation's participant info
(`participantName`, `participantUsername`, `participantPicture`)
and last-message preview by joining the Conversation document
scoped to the caller's team. Max date range is 365 days.


### Parameters

- **fromDate** (required) in query: No description
- **toDate** (optional) in query: No description
- **profileId** (optional) in query: No description
- **platform** (optional) in query: No description
- **accountId** (optional) in query: No description
- **source** (optional) in query: No description
- **limit** (optional) in query: No description
- **page** (optional) in query: No description
- **sortBy** (optional) in query: No description
- **order** (optional) in query: No description

### Responses

#### 200: Paginated conversation analytics list

**Response Body:**

- **success** `boolean`: No description
- **from** `string` (date): No description
- **to** `string,null` (date): No description
- **items** `array[object]`: 
  - **conversationId** `string`: The platformConversationId (the same identity used by metadata.conversationId)
  - **mongoId** `string,null`: The Conversation document _id, when a matching doc exists
  - **accountId** `string`: No description
  - **platform** `string`: No description
  - **participantName** `string,null`: No description
  - **participantUsername** `string,null`: No description
  - **participantPicture** `string,null`: No description
  - **lastMessage** `string,null`: Cached preview from the Conversation doc
  - **totalMessages** `integer`: No description
  - **received** `integer`: No description
  - **sent** `integer`: No description
  - **read** `integer`: No description
  - **failed** `integer`: No description
  - **firstMessageAt** `string` (date-time): No description
  - **lastMessageAt** `string` (date-time): No description
- **pagination** `object`: 
  - **page** `integer`: No description
  - **limit** `integer`: No description
  - **total** `integer`: No description
  - **totalPages** `integer`: No description
  - **hasMore** `boolean`: No description

#### 400: Validation error

**Response Body:**

- **error** `string`: No description
- **details** `object`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

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
