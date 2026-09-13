# Get pending OAuth data API Reference

Fetch pending OAuth data for headless mode using the pendingDataToken from the redirect URL.

**Scope**: This endpoint is used for LinkedIn organizations, Google Business Profile locations, Slack channels, Snapchat profiles, and Pinterest boards, where the selection list is too large to fit in URL params. The redirect carries a `pendingDataToken` instead of the full payload; the response includes the corresponding selection array (e.g. `boards` for Pinterest). WhatsApp, Facebook and other platforms pass selection state directly via URL query params on the redirect (`profileId`, `tempToken`, `step`), no pending record is created, so this endpoint will return 404 for those flows. Use the platform-specific selection endpoint instead (e.g. `/v1/connect/whatsapp/select-phone-number`).

Reading the token does not consume it, so this fetch is repeatable until the token expires 1 hour after issuance. Completing the platform selection deletes the pending record, so the token stops working from then on. No authentication required.


## GET /v1/connect/pending-data

**Get pending OAuth data**

Fetch pending OAuth data for headless mode using the pendingDataToken from the redirect URL.

**Scope**: This endpoint is used for LinkedIn organizations, Google Business Profile locations, Slack channels, Snapchat profiles, and Pinterest boards, where the selection list is too large to fit in URL params. The redirect carries a `pendingDataToken` instead of the full payload; the response includes the corresponding selection array (e.g. `boards` for Pinterest). WhatsApp, Facebook and other platforms pass selection state directly via URL query params on the redirect (`profileId`, `tempToken`, `step`), no pending record is created, so this endpoint will return 404 for those flows. Use the platform-specific selection endpoint instead (e.g. `/v1/connect/whatsapp/select-phone-number`).

Reading the token does not consume it, so this fetch is repeatable until the token expires 1 hour after issuance. Completing the platform selection deletes the pending record, so the token stops working from then on. No authentication required.


### Parameters

- **token** (required) in query: The pending data token from the OAuth redirect URL (pendingDataToken parameter)

### Responses

#### 200: OAuth data fetched successfully

**Response Body:**

- **platform** `string`: The platform (e.g., "linkedin")
- **profileId** `string`: The Zernio profile ID
- **tempToken** `string`: Temporary access token for the platform
- **refreshToken** `string`: Refresh token (if available)
- **expiresIn** `number`: Token expiry in seconds
- **userProfile** `object`: User profile data (id, username, displayName, profilePicture)
- **selectionType** `string`: Type of selection data - one of: organizations, pages, boards, locations, profiles
- **organizations** `array[object]`: LinkedIn organizations (when selectionType is "organizations")
  - **id** `string`: No description
  - **urn** `string`: No description
  - **name** `string`: No description
  - **vanityName** `string`: No description

#### 400: Missing token parameter

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

#### 404: Token not found or expired

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
