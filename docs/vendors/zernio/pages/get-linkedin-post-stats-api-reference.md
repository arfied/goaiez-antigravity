# Get LinkedIn post stats API Reference

Returns analytics for a specific LinkedIn post by URN. Works for both personal and organization accounts. Saves and sends are only populated for personal accounts (LinkedIn does not expose these metrics on the organization analytics endpoint).

## GET /v1/accounts/{accountId}/linkedin-post-analytics

**Get LinkedIn post stats**

Returns analytics for a specific LinkedIn post by URN. Works for both personal and organization accounts. Saves and sends are only populated for personal accounts (LinkedIn does not expose these metrics on the organization analytics endpoint).

### Parameters

- **accountId** (required) in path: The ID of the LinkedIn account
- **urn** (required) in query: The LinkedIn post URN

### Responses

#### 200: Post analytics data

**Response Body:**

- **accountId** `string`: No description
- **platform** `string`: No description (example: "linkedin")
- **accountType** `string`: No description - one of: personal, organization
- **username** `string`: No description
- **postUrn** `string`: No description
- **analytics** `object`: 
  - **impressions** `integer`: Times the post was shown
  - **reach** `integer`: Unique members who saw the post
  - **likes** `integer`: Reactions on the post
  - **comments** `integer`: Comments on the post
  - **shares** `integer`: Reshares of the post
  - **saves** `integer`: Times the post was saved (personal accounts only; 0 for organization accounts)
  - **sends** `integer`: Times the post was sent via LinkedIn messaging (personal accounts only; 0 for organization accounts)
  - **clicks** `integer`: Clicks on the post (organization accounts only)
  - **views** `integer`: Video views (video posts only)
  - **engagementRate** `number`: Engagement rate, as a percentage rounded to 2 decimals: (likes + comments + shares + clicks + saves + sends) / impressions * 100. Unlike PostAnalytics.engagementRate on GET /v1/analytics, this one DOES count clicks and has no fallback denominator, so it is 0 whenever impressions is 0. For organization accounts the value is the rate LinkedIn returns, not one computed here.
- **lastUpdated** `string` (date-time): No description

#### 400: Invalid request

**Response Body:**

- **error** `string`: No description
- **code** `string`: No description - one of: missing_urn, invalid_urn, invalid_platform

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

#### 403: Missing required LinkedIn scope

**Response Body:**

- **error** `string`: No description
- **code** `string`: No description (example: "missing_scope")
- **requiredScope** `string`: No description
- **action** `string`: No description (example: "reconnect")

#### 404: Account or post not found

**Response Body:**

- **error** `string`: No description
- **code** `string`: No description

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
