# Check accounts health API Reference

Returns health status of all connected accounts including token validity, permissions, and issues needing attention.

## GET /v1/accounts/health

**Check accounts health**

Returns health status of all connected accounts including token validity, permissions, and issues needing attention.

### Parameters

- **profileId** (optional) in query: Filter by profile ID
- **platform** (optional) in query: Filter by platform
- **status** (optional) in query: Filter by health status

### Responses

#### 200: Account health summary

**Response Body:**

- **summary** `object`: 
  - **total** `integer`: Total number of accounts
  - **healthy** `integer`: Number of healthy accounts
  - **warning** `integer`: Number of accounts with warnings
  - **error** `integer`: Number of accounts with errors
  - **needsReconnect** `integer`: Number of accounts needing reconnection
- **accounts** `array[object]`: 
  - **accountId** `string`: No description
  - **platform** `string`: No description
  - **username** `string`: No description
  - **displayName** `string`: No description
  - **profileId** `string`: No description
  - **status** `string`: No description - one of: healthy, warning, error
  - **canPost** `boolean`: No description
  - **canFetchAnalytics** `boolean`: No description
  - **tokenValid** `boolean`: No description
  - **tokenExpiresAt** `string` (date-time): No description
  - **needsReconnect** `boolean`: No description
  - **issues** `array[string]`: 
  - **messagingRestriction** `object,null`: Observed from Meta's own error subcodes on our own sends (2534122, 1893063, 2534029), not a live probe. Set on the first refused send and cleared when a later send succeeds, so it lags reality by one send in each direction.

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

---
