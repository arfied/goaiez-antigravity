# Get Google Business Profile search keywords API Reference

Returns search keywords that triggered impressions for a Google Business Profile location.
Data is aggregated monthly. Keywords below a minimum impression threshold set by Google are excluded.
Max 18 months of historical data. Requires the Analytics add-on.


## GET /v1/analytics/googlebusiness/search-keywords

**Get Google Business Profile search keywords**

Returns search keywords that triggered impressions for a Google Business Profile location.
Data is aggregated monthly. Keywords below a minimum impression threshold set by Google are excluded.
Max 18 months of historical data. Requires the Analytics add-on.


### Parameters

- **accountId** (required) in query: The Zernio SocialAccount ID for the Google Business Profile account.
- **startMonth** (optional) in query: Start month (YYYY-MM). Defaults to 3 months ago.
- **endMonth** (optional) in query: End month (YYYY-MM). Defaults to current month.

### Responses

#### 200: Search keywords with impression counts

**Response Body:**

- **success** `boolean`: No description (example: true)
- **accountId** `string`: No description
- **platform** `string`: No description (example: "googlebusiness")
- **monthRange** `object`: 
  - **startMonth** `string`: No description (example: "2026-01")
  - **endMonth** `string`: No description (example: "2026-03")
- **keywords** `array[object]`: 
  - **keyword** `string`: No description (example: "restaurant near me")
  - **impressions** `integer`: No description (example: 245)
- **note** `string`: No description (example: "Keywords below a minimum impression threshold are excluded by Google")

#### 400: Invalid parameters

**Response Body:**

- **error** `string`: No description (example: "Invalid startMonth format. Use YYYY-MM.")

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

**Response Body:**

- **error** `string`: No description (example: "Analytics add-on required")
- **code** `string`: No description (example: "analytics_addon_required")

#### 403: Access denied

**Response Body:**

- **error** `string`: No description (example: "Access denied to this account")

#### 502: The platform returned a server error.

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

#### 503: An upstream service or database is temporarily unavailable. Retry after the indicated delay. A timed-out write may have completed upstream; check its outcome before resubmitting.

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
