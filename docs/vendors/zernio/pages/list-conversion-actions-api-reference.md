# List conversion actions API Reference

Lists Google Ads conversion actions on the resolved customer, all types by
default. Each action's `tagSnippets` (global site tag + event snippet) is
included when Google has them for that action's type, e.g. `WEBPAGE`.
Google-only; other platforms return `501`. Requires the Ads add-on.

`customerId` is optional: when omitted, it is resolved from the connection's
accessible Google Ads customers, and the call fails with `400` when more than
one is accessible (pass `customerId` to disambiguate).

The list itself is cached for the quota window (1 hour fresh, up to 7 days
last-good; the cache key does not vary on `type`). The response carries
`cachedAt` and `stale`, set when a quota-exhausted call falls back to the
last-good copy instead of a live read.


## GET /v1/ads/conversions/actions

**List conversion actions**

Lists Google Ads conversion actions on the resolved customer, all types by
default. Each action's `tagSnippets` (global site tag + event snippet) is
included when Google has them for that action's type, e.g. `WEBPAGE`.
Google-only; other platforms return `501`. Requires the Ads add-on.

`customerId` is optional: when omitted, it is resolved from the connection's
accessible Google Ads customers, and the call fails with `400` when more than
one is accessible (pass `customerId` to disambiguate).

The list itself is cached for the quota window (1 hour fresh, up to 7 days
last-good; the cache key does not vary on `type`). The response carries
`cachedAt` and `stale`, set when a quota-exhausted call falls back to the
last-good copy instead of a live read.


### Parameters

- **accountId** (required) in query: SocialAccount _id (must be a googleads account).
- **customerId** (optional) in query: Google Ads customer id (digits only). Resolved automatically when the connection has exactly one accessible customer.
- **type** (optional) in query: Filter by Google's ConversionActionType enum (e.g. WEBPAGE, UPLOAD_CLICKS).

### Responses

#### 200: The resolved customer and its conversion actions.

**Response Body:**

- **customerId** `string`: The Google Ads customer id the actions were read from.
- **actions** `array[ConversionAction]`: 
- **cachedAt** `string,null` (date-time): When this list was fetched from Google. Null when it was never served from cache.
- **stale** `boolean`: True when Google's daily API quota was exhausted and this is the last successful fetch, not a live read.

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

#### 403: Ads access required (Ads add-on on legacy plans, included on usage-based plans).

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

#### 501: Conversion actions are only available for Google Ads (the account's platform is not `googleads`).

---

## POST /v1/ads/conversions/actions

**Create website conversion action**

Creates a `WEBPAGE` conversion action (category `DEFAULT`) and returns it with
its tag snippets, read back after creation since Google never returns them on
the create response itself. Invalidates the cached list `GET` on this resource
would otherwise keep serving. Google-only; other platforms return `501`.
Requires the Ads add-on.


### Request Body

- **accountId** (required) `string`: SocialAccount ID. Must be a `googleads` account.
- **customerId** `string`: Google Ads customer id (digits only). Resolved automatically when the connection has exactly one accessible customer.
- **name** (required) `string`: No description
- **type** (required) `string`: Only WEBPAGE is supported for creation today. - one of: WEBPAGE
- **defaultValue** `number`: Default conversion value used when an event doesn't carry its own value.
- **alwaysUseDefaultValue** `boolean`: When true, always use defaultValue and ignore any value sent with the event. Defaults to true when defaultValue is set.

### Responses

#### 201: The created conversion action, with its tag snippets.

**Response Body:**

- **action**: `ConversionAction` - See schema definition

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

#### 403: Ads access required (Ads add-on on legacy plans, included on usage-based plans).

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

#### 501: Conversion actions are only available for Google Ads (the account's platform is not `googleads`).

---
