# Get a conversion destination API Reference

LinkedIn-only today. Returns the full destination record for one
conversion rule. The `adAccountId` query parameter is required because
LinkedIn rules are scoped to a sponsored ad account.


## GET /v1/accounts/{accountId}/conversion-destinations/{destinationId}

**Get a conversion destination**

LinkedIn-only today. Returns the full destination record for one
conversion rule. The `adAccountId` query parameter is required because
LinkedIn rules are scoped to a sponsored ad account.


### Parameters

- **accountId** (required) in path: No description
- **destinationId** (required) in path: No description
- **adAccountId** (required) in query: Numeric ID or full `urn:li:sponsoredAccount:{id}` URN.

### Responses

#### 200: Destination fetched

**Response Body:**

- **platform** `string`: No description - one of: linkedinads
- **destination**: `ConversionDestination` - See schema definition

#### 400: Validation error.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads add-on or LinkedIn reconnect required.

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

#### 405: Platform does not support fetching a single destination.

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

#### 429: LinkedIn rate limit hit. Retry with backoff.

---

## PATCH /v1/accounts/{accountId}/conversion-destinations/{destinationId}

**Update a conversion destination**

Partial-update a conversion rule. LinkedIn-only today. Whitelisted
fields: `name`, `enabled`, attribution windows, `valueType`, `value`,
`attributionType`. The rule's `type` and parent ad account are
intentionally not exposed for update. Recreate the rule if those
need to change.


### Parameters

- **accountId** (required) in path: No description
- **destinationId** (required) in path: No description

### Request Body

- **adAccountId** (required) `string`: No description
- **name** `string`: No description
- **enabled** `boolean`: Setting `false` is equivalent to calling DELETE: the
rule will appear as `inactive` afterwards.

- **attributionType** `string`: No description - one of: LAST_TOUCH_BY_CAMPAIGN, LAST_TOUCH_BY_CONVERSION
- **postClickAttributionWindowSize** `integer`: 365 only allowed for LEAD, PURCHASE, ADD_TO_CART,
QUALIFIED_LEAD, SUBMIT_APPLICATION rule types.
 - one of: 1, 7, 30, 90, 365
- **viewThroughAttributionWindowSize** `integer`: 365 only allowed for LEAD, PURCHASE, ADD_TO_CART,
QUALIFIED_LEAD, SUBMIT_APPLICATION rule types.
 - one of: 1, 7, 30, 90, 365
- **valueType** `string`: No description - one of: DYNAMIC, FIXED, NO_VALUE
- **value** `object`: Used when `valueType=FIXED`.

### Responses

#### 200: Destination updated (re-fetched canonical state)

**Response Body:**

- **platform** `string`: No description - one of: linkedinads
- **destination**: `ConversionDestination` - See schema definition

#### 400: Invalid body or LinkedIn validation failure.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads add-on or LinkedIn reconnect required.

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

#### 405: Platform does not support updating destinations.

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

#### 429: LinkedIn rate limit hit. Retry with backoff.

---

## DELETE /v1/accounts/{accountId}/conversion-destinations/{destinationId}

**Delete a conversion destination**

LinkedIn-only today. LinkedIn does not expose hard-delete on conversion
rules; what their UI calls "delete" is the same `enabled: false` flip
we apply here. The rule remains fetchable via GET with
`status: 'inactive'`; the unified discovery endpoint hides it by
default.

`adAccountId` may be passed as a query parameter (recommended) or as
a JSON body field for clients that can send DELETE bodies.


### Parameters

- **accountId** (required) in path: No description
- **destinationId** (required) in path: No description
- **adAccountId** (optional) in query: Required as query OR in JSON body.

### Responses

#### 204: Soft-deleted.

#### 400: adAccountId missing, or accountId is not a valid id.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads add-on or LinkedIn reconnect required.

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

#### 405: Platform does not support deleting destinations.

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

#### 429: LinkedIn rate limit hit. Retry with backoff.

---
