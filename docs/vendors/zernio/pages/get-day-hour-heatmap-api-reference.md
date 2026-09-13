# Get day × hour heatmap API Reference

Day-of-week × hour-of-day breakdown of inbox messages. Buckets are
sparse: only cells with at least one event are returned; clients
zero-fill the rest to render the full 7×24 grid. The `dow` field
follows ClickHouse's `toDayOfWeek` convention (1 = Monday … 7 =
Sunday). Max date range is 365 days.


## GET /v1/analytics/inbox/heatmap

**Get day × hour heatmap**

Day-of-week × hour-of-day breakdown of inbox messages. Buckets are
sparse: only cells with at least one event are returned; clients
zero-fill the rest to render the full 7×24 grid. The `dow` field
follows ClickHouse's `toDayOfWeek` convention (1 = Monday … 7 =
Sunday). Max date range is 365 days.


### Parameters

- **fromDate** (required) in query: No description
- **toDate** (optional) in query: No description
- **profileId** (optional) in query: No description
- **platform** (optional) in query: No description
- **accountId** (optional) in query: No description
- **source** (optional) in query: No description
- **action** (optional) in query: Narrow to a single event type. "all" or omitted means no filter.

### Responses

#### 200: Heatmap buckets

**Response Body:**

- **success** `boolean`: No description
- **from** `string` (date): No description
- **to** `string,null` (date): No description
- **buckets** `array[object]`: 
  - **dow** `integer`: 1 = Monday, 7 = Sunday
  - **hour** `integer`: No description
  - **received** `integer`: No description
  - **sent** `integer`: No description
  - **read** `integer`: No description

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
