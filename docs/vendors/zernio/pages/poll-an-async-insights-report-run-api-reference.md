# Poll an async insights report run API Reference

Status and results for a report run created via POST /v1/ads/insights/reports. While the job
runs, returns `status` and `percentCompletion`. Once `status` is "Job Completed" the response
also carries a `data` page, cursor-paginated via `limit` / `after`.


## GET /v1/ads/insights/reports/{reportRunId}

**Poll an async insights report run**

Status and results for a report run created via POST /v1/ads/insights/reports. While the job
runs, returns `status` and `percentCompletion`. Once `status` is "Job Completed" the response
also carries a `data` page, cursor-paginated via `limit` / `after`.


### Parameters

- **reportRunId** (required) in path: No description
- **accountId** (required) in query: Zernio SocialAccount id used to resolve the Meta token (must be the same connection that created the run).
- **limit** (optional) in query: No description
- **after** (optional) in query: No description

### Responses

#### 200: Report run status (plus results when completed)

**Response Body:**

- **reportRunId** `string`: No description
- **status** `string`: Meta async_status: Job Not Started, Job Started, Job Running, Job Completed, Job Failed, Job Skipped.
- **percentCompletion** `integer`: No description
- **dateStart** `string`: No description
- **dateStop** `string`: No description
- **data** `array[object]`: Present only when status is Job Completed.
  Type: `object`
- **paging** `object`: 
  - **after** `string,null`: No description

#### 400: Invalid input, or the report run is not readable with this account's token

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: The account or requested resource was not found or is not accessible. An account ID may have been disconnected and removed. Read GET /v1/accounts for current account IDs.

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

#### 409: The account exists but is inactive or needs reconnection. Reconnect it, then read GET /v1/accounts for its current account ID before retrying. Code: ads_connection_required.

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

#### 429: Meta rate limit reached

#### 501: Only supported on Meta (facebook/instagram)

---
