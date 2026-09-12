# Check account health API Reference

Returns detailed health info for a specific account including token status, permissions, and recommendations.

For WhatsApp accounts the response also includes `platformConnection`, a live probe of the
Meta link behind the channel (the same read as `GET /v1/whatsapp/number-info`). The OAuth
token can be perfectly valid while Meta refuses to serve the phone-number object (for
example after a phone-side coexistence disconnect), so `tokenStatus` alone is not a
liveness signal for WhatsApp. When the Meta link is dead, `platformConnection.status` is
`disconnected` and the overall `status` is `error`.


## GET /v1/accounts/{accountId}/health

**Check account health**

Returns detailed health info for a specific account including token status, permissions, and recommendations.

For WhatsApp accounts the response also includes `platformConnection`, a live probe of the
Meta link behind the channel (the same read as `GET /v1/whatsapp/number-info`). The OAuth
token can be perfectly valid while Meta refuses to serve the phone-number object (for
example after a phone-side coexistence disconnect), so `tokenStatus` alone is not a
liveness signal for WhatsApp. When the Meta link is dead, `platformConnection.status` is
`disconnected` and the overall `status` is `error`.


### Parameters

- **accountId** (required) in path: The account ID to check

### Responses

#### 200: Account health details

**Response Body:**

- **accountId** `string`: No description
- **platform** `string`: No description
- **username** `string`: No description
- **displayName** `string`: No description
- **status** `string`: Overall health status - one of: healthy, warning, error
- **tokenStatus** `object`: 
  - **valid** `boolean`: Whether the token is valid
  - **expiresAt** `string` (date-time): No description
  - **expiresIn** `string`: Human-readable time until expiry
  - **needsRefresh** `boolean`: Whether token expires within 24 hours
- **permissions** `object`: 
  - **posting** `array[object]`: 
    - **scope** `string`: No description
    - **granted** `boolean`: No description
    - **required** `boolean`: No description
  - **analytics** `array[object]`: 
    - **scope** `string`: No description
    - **granted** `boolean`: No description
    - **required** `boolean`: No description
  - **optional** `array[object]`: 
    - **scope** `string`: No description
    - **granted** `boolean`: No description
    - **required** `boolean`: No description
  - **canPost** `boolean`: No description
  - **canFetchAnalytics** `boolean`: No description
  - **missingRequired** `array[string]`: 
- **issues** `array[string]`: List of issues found
- **recommendations** `array[string]`: Actionable recommendations to fix issues
- **messagingRestriction** `object,null`: Observed from Meta's own error subcodes on our own sends (2534122, 1893063, 2534029), not a live probe. Set on the first refused send and cleared when a later send succeeds, so it lags reality by one send in each direction.
- **platformConnection** `object`: WhatsApp accounts only. Live probe of the Meta link behind the channel, performed at request time (the same read as GET /v1/whatsapp/number-info).
  - **status** `string`: `connected` = Meta served the channel object. `disconnected` = Meta refused to serve it (Graph error 100, subcode 33), which is how a phone-side coexistence disconnect surfaces. `unknown` = the live read failed for another reason (timeout, transient Meta error), not evidence either way. - one of: connected, disconnected, unknown
  - **checkedAt** `string` (date-time): When this live probe ran (always the current request; never cached)
  - **phoneStatus** `string,null`: Meta's own `status` field from the phone-number node (for example CONNECTED), when the object was readable
  - **metaError** `object,null`: Set only when status is `disconnected`

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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---
