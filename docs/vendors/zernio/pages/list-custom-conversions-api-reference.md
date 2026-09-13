# List custom conversions API Reference

The ad account's Meta custom conversions, including archived ones (`isArchived`).

## GET /v1/accounts/{accountId}/custom-conversions

**List custom conversions**

The ad account's Meta custom conversions, including archived ones (`isArchived`).

### Parameters

- **accountId** (required) in path: Meta ads SocialAccount id.
- **adAccountId** (required) in query: Meta ad account id (act_<n>).

### Responses

#### 200: Custom conversions

**Response Body:**

- **adAccountId** `string`: No description
- **data** `array[CustomConversion]`: 

#### 400: Invalid input, or Meta rejected the query

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required, or the token lacks the ads permissions.

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

## POST /v1/accounts/{accountId}/custom-conversions

**Create custom conversion**

Provision the Meta custom conversion an ads flow optimises toward, and hand back the
`customConversionId` for `promotedObject.customConversionId` on POST /v1/ads/create.
Removes the manual "create it in Ads Manager first" step.

**Reuse is ours, not Meta's.** Meta's create is not idempotent, so a retried request
would otherwise mint a duplicate carrying none of the original's optimisation history.
A non-archived conversion with the same `name` on the same `pixelId` is returned
instead of created, with `reused: true` and a 200 rather than a 201.

`rule` is forwarded verbatim in Meta's own grammar (e.g.
`{"url": {"i_contains": "thank-you"}}`); Meta validates it and rejects a malformed one
with "A conversion rule is required at creation time".

### Parameters

- **accountId** (required) in path: Meta ads SocialAccount id.

### Request Body

- **adAccountId** (required) `string`: Platform ad account id (Meta act_<n>, Google customer id, LinkedIn account id, ...).
- **name** (required) `string`: Also the reuse key, together with pixelId.
- **pixelId** (required) `string`: Meta pixel id (event_source_id). From GET /v1/accounts/{accountId}/tracking-tags.
- **customEventType** (required) `string`: Meta custom_event_type, e.g. LEAD, PURCHASE, OTHER.
- **rule** (required) `object`: Meta conversion rule, forwarded verbatim.

### Responses

#### 200: An existing custom conversion was reused

**Response Body:**

- **adAccountId** `string`: No description
- **customConversionId** `string`: Drops straight into promotedObject.customConversionId on POST /v1/ads/create.
- **reused** `boolean`: True when an existing conversion matched name + pixelId; the response is then a 200.
- **customConversion**: `CustomConversion` - See schema definition

#### 201: Custom conversion created

**Response Body:**

- **adAccountId** `string`: No description
- **customConversionId** `string`: Drops straight into promotedObject.customConversionId on POST /v1/ads/create.
- **reused** `boolean`: True when an existing conversion matched name + pixelId; the response is then a 200.
- **customConversion**: `CustomConversion` - See schema definition

#### 400: Invalid input, or Meta rejected the conversion (bad rule, per-account cap reached)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required, or the token lacks the ads permissions.

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
