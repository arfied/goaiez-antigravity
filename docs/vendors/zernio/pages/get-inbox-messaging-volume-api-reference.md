# Get inbox messaging volume API Reference

Daily inbox messaging volume + breakdowns. Folds the raw messaging
events into three projections so the client can render the volume
chart, KPI strip, and per-platform stacked bar from a single call.
Max date range is 365 days.


## GET /v1/analytics/inbox/volume

**Get inbox messaging volume**

Daily inbox messaging volume + breakdowns. Folds the raw messaging
events into three projections so the client can render the volume
chart, KPI strip, and per-platform stacked bar from a single call.
Max date range is 365 days.


### Parameters

- **fromDate** (required) in query: Inclusive lower bound (YYYY-MM-DD). Required.
- **toDate** (optional) in query: Inclusive upper bound (YYYY-MM-DD). Defaults to today.
- **profileId** (optional) in query: No description
- **platform** (optional) in query: Filter by single platform (facebook, instagram, twitter, etc.).
- **accountId** (optional) in query: No description
- **source** (optional) in query: Filter by metadata.source lineage (human, workflow, sequence, broadcast, comment_automation, api, contact, platform).

### Responses

#### 200: Volume breakdown

**Response Body:**

- **success** `boolean`: No description
- **from** `string` (date): No description
- **to** `string,null` (date): No description
- **summary** `object`: 
  - **received** `integer`: No description
  - **sent** `integer`: No description
  - **read** `integer`: No description
  - **failed** `integer`: No description
  - **uniqueConversations** `integer`: No description
- **timeseries** `array[object]`: 
  - **date** `string` (date): No description
  - **sent** `integer`: No description
  - **received** `integer`: No description
  - **read** `integer`: No description
  - **failed** `integer`: No description
- **byPlatform** `array[object]`: 
  - **platform** `string`: No description
  - **sent** `integer`: No description
  - **received** `integer`: No description
  - **read** `integer`: No description
  - **failed** `integer`: No description

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
