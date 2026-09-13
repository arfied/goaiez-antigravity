# List activity logs API Reference

Unified logs endpoint. Returns logs for publishing, connections, webhooks, and messaging.
Filter by type, platform, status, and time range. Logs are retained for 90 days.


## GET /v1/logs

**List activity logs**

Unified logs endpoint. Returns logs for publishing, connections, webhooks, and messaging.
Filter by type, platform, status, and time range. Logs are retained for 90 days.


### Parameters

- **type** (optional) in query: Log category to query. Use `all` for the unified view across every category,
or `api_request` for your API request logs (method, path, status, latency).

- **status** (optional) in query: Filter by status
- **platform** (optional) in query: Filter by platform
- **action** (optional) in query: Filter by action (e.g., post.published, message.sent, account.connected, webhook.delivered)
- **search** (optional) in query: Free-text search across log fields
- **days** (optional) in query: Number of days to look back (max 90)
- **limit** (optional) in query: Maximum number of logs to return (max 100)
- **skip** (optional) in query: Number of logs to skip (for pagination)
- **account_id** (optional) in query: Filter by connected account ID
- **event** (optional) in query: Filter webhook logs by event (e.g. post.published, message.received)
- **request_id** (optional) in query: Correlation ID. Returns every log spawned by a single API request
- **from** (optional) in query: Precise start instant (ISO 8601); narrows within the day range
- **to** (optional) in query: Precise end instant (ISO 8601)
- **status_code** (optional) in query: Filter by exact HTTP status code (api_request logs)
- **api_key_id** (optional) in query: Filter by the API key that made the request (api_request logs)
- **include_read_receipts** (optional) in query: Include message.read / message.delivered events (hidden by default for messaging logs)

### Responses

#### 200: Logs retrieved successfully

**Response Body:**

- **logs** `array[object]`: 
  - **type** `string`: Log category (publishing, connections, webhooks, messaging)
  - **action** `string`: Specific action (post.published, message.sent, account.connected, etc.)
  - **user_id** `string`: No description
  - **platform** `string`: No description
  - **account_id** `string`: No description
  - **status** `string`: No description - one of: success, failed, pending, skipped
  - **status_code** `integer`: No description
  - **error_message** `string`: No description
  - **error_code** `string`: No description
  - **duration_ms** `integer`: No description
  - **endpoint** `string`: The API endpoint that triggered this log
  - **request_body** `string`: Request JSON (truncated to 5KB)
  - **response_body** `string`: Response JSON (truncated to 10KB)
  - **created_at** `string` (date-time): No description
  - **metadata** `string`: Additional context as JSON string
  - **request_id** `string`: Correlation ID linking every log from one API request (api_request logs)
  - **api_key_id** `string`: The API key that made the request (api_request logs)
  - **method** `string`: HTTP method (api_request logs)
  - **path** `string`: Request path (api_request logs)
  - **ip_address** `string`: Client IP address (api_request logs)
  - **user_agent** `string`: Client user-agent (api_request logs)
- **pagination** `object`: 
  - **total** `integer`: No description
  - **limit** `integer`: No description
  - **skip** `integer`: No description
  - **pages** `integer`: No description
  - **hasMore** `boolean`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
