# Get aggregated event stats API Reference

Returns aggregated event counts for the pixel (`GET /{pixel_id}/stats`).
Rows are passed through from Meta as-is; their shape depends on the
`aggregation` requested. Meta only (platform `metaads`); other platforms
return 405.


## GET /v1/accounts/{accountId}/tracking-tags/{tagId}/stats

**Get aggregated event stats**

Returns aggregated event counts for the pixel (`GET /{pixel_id}/stats`).
Rows are passed through from Meta as-is; their shape depends on the
`aggregation` requested. Meta only (platform `metaads`); other platforms
return 405.


### Parameters

- **accountId** (required) in path: No description
- **tagId** (required) in path: Pixel id.
- **aggregation** (optional) in query: Aggregation dimension. Defaults to `event`.
- **startTime** (optional) in query: Unix seconds lower bound.
- **endTime** (optional) in query: Unix seconds upper bound.

### Responses

#### 200: Stats fetched

**Response Body:**

- **platform** `string`: No description - one of: metaads
- **stats** `object`: 
  - **aggregation** `string`: No description
  - **startTime** `integer`: No description
  - **endTime** `integer`: No description
  - **rows** `array[object]`: 
    Type: `object` with additional properties

#### 400: Invalid query parameter.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required (Ads add-on on legacy plans, included on usage-based plans), or the Meta token lacks ads permissions (reconnect required).

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

#### 405: Platform does not support tracking-tag stats.

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

#### 502: Meta was unreachable or returned an unclassified error (type: platform_error; the raw Meta payload is in platformError). Retryable.

---
