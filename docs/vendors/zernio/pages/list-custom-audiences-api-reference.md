# List custom audiences API Reference

Returns custom audiences for the given ad account. Supports Meta, Google, TikTok, Pinterest, LinkedIn, and X.

## GET /v1/ads/audiences

**List custom audiences**

Returns custom audiences for the given ad account. Supports Meta, Google, TikTok, Pinterest, LinkedIn, and X.

### Parameters

- **accountId** (required) in query: Account ID
- **adAccountId** (required) in query: Platform ad account ID
- **platform** (optional) in query: No description
- **type** (optional) in query: Filter to one audience type. `saved_targeting` returns stored TargetingSpec audiences; the other types return uploaded/derived audiences.

### Responses

#### 200: Audiences

**Response Body:**

- **audiences** `array[object]`: 
  - **id** `string,null`: The Zernio audience id. Pass this as audienceId on GET /v1/ads/audiences/{audienceId} and the companies/users upload endpoints. Null when the audience was not created through Zernio.
  - **accountId** `string`: Account the audience was created against. Returned for saved_targeting items.
  - **platformAudienceId** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **type** `string`: No description - one of: customer_list, company_list, engagement, meta_engagement, website, website_retargeting, lookalike, saved_targeting
  - **spec**: Present (and the only meaningful payload) when `type` is `saved_targeting`. Null for uploaded/derived audience types.
  - **platform** `string`: No description
  - **size** `integer`: No description
  - **status** `string`: No description

#### 400: Invalid request

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

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required. Legacy plans need the Ads add-on; included by default on usage-based plans.

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

---

## POST /v1/ads/audiences

**Create custom audience**

Create a custom audience. `customer_list` is supported on Meta, Google, X, LinkedIn, TikTok, and Pinterest;
`website` and `lookalike` are Meta-only; `company_list`, `engagement` and `website_retargeting` are LinkedIn-only.
`saved_targeting` stores a reusable TargetingSpec (no member upload, no adAccountId) that you reference later via
`savedTargetingId` on `POST /v1/ads/create`.

How the audience gets filled depends on the type:

- `customer_list` is created empty. Add members with `POST /v1/ads/audiences/{audienceId}/users`.
  On TikTok and Pinterest the audience is provisioned lazily on that first upload (until then its status is `pending`).
- `company_list` is filled AT CREATION from the `companies` array below, which is required. To change the list
  afterwards send the new full list to `POST /v1/ads/audiences/{audienceId}/companies` (a replace, not a merge).
  The `/users` endpoint rejects these audiences with a 422.
- `website`, `website_retargeting`, `engagement`, `meta_engagement` and `lookalike` fill themselves from the pixel,
  engagement source or seed audience you point them at. They take no member upload at all.

Create is not idempotent, never auto-retry.


### Request Body

- **platformSpecificData**: Platform-specific settings (see schema definitions below)

### Responses

#### 201: Audience created

**Response Body:**

- **audience** `object`: No description
- **message** `string`: No description

#### 400: Missing required fields

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required. Legacy plans need the Ads add-on; included by default on usage-based plans.

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

---
