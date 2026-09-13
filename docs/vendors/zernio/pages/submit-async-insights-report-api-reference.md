# Submit async insights report API Reference

Submits an asynchronous Meta insights report. Same query surface as GET /v1/ads/insights, but
in the JSON body; Meta processes the report server-side, which is the right choice for long
ranges or large accounts where the sync query is slow or rate-limited. Returns a `reportRunId`
to poll via GET /v1/ads/insights/reports/{reportRunId}.


## POST /v1/ads/insights/reports

**Submit async insights report**

Submits an asynchronous Meta insights report. Same query surface as GET /v1/ads/insights, but
in the JSON body; Meta processes the report server-side, which is the right choice for long
ranges or large accounts where the sync query is slow or rate-limited. Returns a `reportRunId`
to poll via GET /v1/ads/insights/reports/{reportRunId}.


### Request Body

- **accountId** (required) `string`: Zernio SocialAccount id (posting or ads variant).
- **objectId** (required) `string`: Meta insights node: act_<n>, campaign id, ad set id or ad id.
- **level** `string`: No description - one of: ad, adset, campaign, account
- **fields** `string`: Comma-separated Graph insights fields.
- **breakdowns** `string`: Comma-separated Graph breakdowns.
- **actionBreakdowns** `string`: Comma-separated Graph action breakdowns (e.g. action_type,action_destination).
- **actionAttributionWindows** `array`: Meta attribution windows (e.g. ["7d_click", "1d_view"]). Action values are returned keyed per window.
- **actionReportTime** `string`: When actions are counted: impression, conversion or mixed.
- **useUnifiedAttributionSetting** `boolean`: Use the ad sets' own attribution settings for action counting.
- **filtering** `array`: Meta filter objects, applied server-side.
- **datePreset** `string`: Mutually exclusive with fromDate/toDate.
- **fromDate** `string`: No description
- **toDate** `string`: No description
- **timeIncrement**: Platform-specific settings (see schema definitions below)

### Responses

#### 202: Report run submitted

**Response Body:**

- **reportRunId** `string`: No description
- **status** `string`: No description (example: "Job Started")

#### 400: Invalid input, or Meta rejected the report parameters

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
