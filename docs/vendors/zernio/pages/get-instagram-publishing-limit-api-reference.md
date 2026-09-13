# Get Instagram publishing limit API Reference

Returns the account's remaining content-publishing quota for Instagram's rolling
24-hour window, so you can pace publishing and warn before the cap is reached.

`quotaUsage` counts containers published since the start of the window.
Always compare against the returned `quotaTotal` rather than hardcoding a number:
Meta's prose documentation and the live API disagree on the value, and the live
value is authoritative.


## GET /v1/accounts/{accountId}/instagram/publishing-limit

**Get Instagram publishing limit**

Returns the account's remaining content-publishing quota for Instagram's rolling
24-hour window, so you can pace publishing and warn before the cap is reached.

`quotaUsage` counts containers published since the start of the window.
Always compare against the returned `quotaTotal` rather than hardcoding a number:
Meta's prose documentation and the live API disagree on the value, and the live
value is authoritative.


### Parameters

- **accountId** (required) in path: The ID of the Instagram account

### Responses

#### 200: Remaining publishing quota for the rolling window

**Response Body:**

- **quotaUsage** `integer`: Containers published so far in the current window
- **quotaTotal** `integer`: Maximum containers publishable per window
- **quotaDurationSeconds** `integer`: Length of the rolling window in seconds

#### 400: Not an Instagram account

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

#### 502: Instagram rejected the request

---
