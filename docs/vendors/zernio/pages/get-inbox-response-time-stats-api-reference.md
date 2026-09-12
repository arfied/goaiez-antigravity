# Get inbox response-time stats API Reference

Time-to-first-response stats. Pairs each received message with the
next sent message in the same conversation and reports the delta
as both summary statistics and a fixed-bucket histogram suited
for the analytics page's TTR chart.

`sampleSize` reflects only conversations that received AND got a
reply in the window. Received-but-never-answered conversations
are excluded. Compare against /v1/analytics/inbox/volume's
`summary.received` to compute reply rate.

Max date range is 365 days.


## GET /v1/analytics/inbox/response-time

**Get inbox response-time stats**

Time-to-first-response stats. Pairs each received message with the
next sent message in the same conversation and reports the delta
as both summary statistics and a fixed-bucket histogram suited
for the analytics page's TTR chart.

`sampleSize` reflects only conversations that received AND got a
reply in the window. Received-but-never-answered conversations
are excluded. Compare against /v1/analytics/inbox/volume's
`summary.received` to compute reply rate.

Max date range is 365 days.


### Parameters

- **fromDate** (required) in query: No description
- **toDate** (optional) in query: No description
- **profileId** (optional) in query: No description
- **platform** (optional) in query: No description
- **accountId** (optional) in query: No description

### Responses

#### 200: Response-time summary + histogram

**Response Body:**

- **success** `boolean`: No description
- **from** `string` (date): No description
- **to** `string,null` (date): No description
- **summary** `object`: 
  - **sampleSize** `integer`: No description
  - **medianSeconds** `integer`: No description
  - **p90Seconds** `integer`: No description
  - **p99Seconds** `integer`: No description
  - **meanSeconds** `integer`: No description
  - **fastestSeconds** `integer`: No description
  - **slowestSeconds** `integer`: No description
- **histogram** `array[object]`: 
  - **bucket** `string`: Human label (0-1m, 1-5m, 5-15m, 15-60m, 1-4h, 4-24h, 1d+)
  - **lowerSeconds** `integer`: No description
  - **upperSeconds** `integer,null`: null on the open-ended last bucket
  - **count** `integer`: No description

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
