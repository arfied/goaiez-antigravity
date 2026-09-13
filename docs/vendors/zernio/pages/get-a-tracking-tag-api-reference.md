# Get a tracking tag API Reference

Returns the full tag record including the base-code `code` snippet,
`lastFiredTime`, `ownerBusinessId`, `isUnavailable`, etc. Meta only
(platform `metaads`); other platforms return 405. OpenAI Ads has no
get-by-id endpoint, so it 405s here too. Use
`GET /v1/accounts/{accountId}/tracking-tags` (list) instead.


## GET /v1/accounts/{accountId}/tracking-tags/{tagId}

**Get a tracking tag**

Returns the full tag record including the base-code `code` snippet,
`lastFiredTime`, `ownerBusinessId`, `isUnavailable`, etc. Meta only
(platform `metaads`); other platforms return 405. OpenAI Ads has no
get-by-id endpoint, so it 405s here too. Use
`GET /v1/accounts/{accountId}/tracking-tags` (list) instead.


### Parameters

- **accountId** (required) in path: No description
- **tagId** (required) in path: Pixel id.

### Responses

#### 200: Tracking tag fetched

**Response Body:**

- **platform** `string`: No description - one of: metaads
- **tag**: `TrackingTag` - See schema definition

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

#### 405: Platform does not support fetching a tracking tag.

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

## PATCH /v1/accounts/{accountId}/tracking-tags/{tagId}

**Update a tracking tag**

Partial-update a pixel. Whitelisted fields: `name` (rename),
`enableAutomaticMatching`, `automaticMatchingFields`,
`firstPartyCookieStatus`, `dataUseSetting`. At least one is required.
Returns the re-fetched canonical tag. Meta only (platform `metaads`);
other platforms return 405.

There is no DELETE: Meta has no API to delete a pixel. To stop using
one, unshare it from your ad accounts (`DELETE
.../tracking-tags/{tagId}/shared-accounts`) or disable it in Events
Manager.


### Parameters

- **accountId** (required) in path: No description
- **tagId** (required) in path: Pixel id.

### Request Body

- **name** `string`: No description
- **enableAutomaticMatching** `boolean`: Meta Advanced Matching toggle (`enable_automatic_matching`).
- **automaticMatchingFields** `array`: Which user fields Advanced Matching may collect. Meta's
terse codes: em=email, ph=phone, fn=first name, ln=last
name, ge=gender, db=date of birth, ct=city, st=state,
zp=zip.

- **firstPartyCookieStatus** `string`: No description - one of: empty, first_party_cookie_disabled, first_party_cookie_enabled
- **dataUseSetting** `string`: No description - one of: advertising_and_analytics, analytics_only, empty

### Responses

#### 200: Tracking tag updated (re-fetched canonical state)

**Response Body:**

- **platform** `string`: No description - one of: metaads
- **tag**: `TrackingTag` - See schema definition

#### 400: Invalid body (e.g. no fields supplied) or Meta validation failure.

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

#### 405: Platform does not support updating tracking tags.

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
