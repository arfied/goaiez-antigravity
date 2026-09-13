# Get inbox source breakdown API Reference

Breakdown of inbox messages by their lineage source (the
`metadata.source` field set at ingest time: human / workflow /
sequence / broadcast / comment_automation / api / contact /
platform). Each source row also carries a per-platform sub-split.
Max date range is 365 days.


## GET /v1/analytics/inbox/source-breakdown

**Get inbox source breakdown**

Breakdown of inbox messages by their lineage source (the
`metadata.source` field set at ingest time: human / workflow /
sequence / broadcast / comment_automation / api / contact /
platform). Each source row also carries a per-platform sub-split.
Max date range is 365 days.


### Parameters

- **fromDate** (required) in query: No description
- **toDate** (optional) in query: No description
- **profileId** (optional) in query: No description
- **platform** (optional) in query: No description
- **accountId** (optional) in query: No description

### Responses

#### 200: Source breakdown

**Response Body:**

- **success** `boolean`: No description
- **from** `string` (date): No description
- **to** `string,null` (date): No description
- **sources** `array[object]`: 
  - **source** `string`: No description
  - **received** `integer`: No description
  - **sent** `integer`: No description
  - **read** `integer`: No description
  - **byPlatform** `array[object]`: 
    - **platform** `string`: No description
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
