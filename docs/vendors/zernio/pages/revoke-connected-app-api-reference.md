# Revoke connected app API Reference

Ends an app's access: invalidates the client's pending authorization codes and
revokes every live token it holds for the authenticated user. Takes effect on
the app's next request.

Idempotent while the authorization is still on record: revoking an app that
was already revoked returns 200 with `revokedTokens: 0`.

Requires a session or a full-access API key. A profile-scoped API key, a
restricted (zrk_) API key, or an OAuth access token is rejected with 403.


## DELETE /v1/me/connected-apps/{clientId}

**Revoke connected app**

Ends an app's access: invalidates the client's pending authorization codes and
revokes every live token it holds for the authenticated user. Takes effect on
the app's next request.

Idempotent while the authorization is still on record: revoking an app that
was already revoked returns 200 with `revokedTokens: 0`.

Requires a session or a full-access API key. A profile-scoped API key, a
restricted (zrk_) API key, or an OAuth access token is rejected with 403.


### Parameters

- **clientId** (required) in path: OAuth client id, as returned by GET /v1/me/connected-apps.

### Responses

#### 200: Revoked

**Response Body:**

- **revoked** `boolean`: No description
- **clientId** `string`: No description
- **revokedTokens** `integer`: Access and refresh tokens revoked by this call.
- **invalidatedCodes** `integer`: Pending authorization codes invalidated by this call.

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

#### 403: The API key is a restricted key (zrk_ prefix) and may not perform this operation. Three cases. (1) The operation's resource group (see the operation's x-resource-group) is disabled on the key: fix it by creating a key with the group enabled in the dashboard API keys tab and revoking the old one. (2) The operation is admin-plane (x-resource-group admin-plane: API keys, invites, connected apps, member identity), which is never grantable to restricted keys; the error reads "Restricted API keys cannot manage API keys, invites, or member identity." and the fix is a full-access key or the dashboard, never a new restricted key. (3) On webhook subscription writes, delivery-log reads and replays, a named event maps to a resource group the key does not hold, so a restricted key can never create or edit a subscription broader than itself (a no-messages key cannot subscribe to, test-fire, redeliver or read logs for message.* events).

**Response Body:**

- **error** `string`: No description (example: "This API key has the 'messages' resource group disabled. GET /api/v1/inbox/conversations requires it. Create a key with 'messages' enabled in the dashboard API keys tab.")
- **code** `string`: No description - one of: insufficient_permissions, unclassified_resource
- **required_group** `string`: The resource group the key needs for this operation. Absent on admin-plane and unclassified-path denials. - one of: publishing, engagement, messages, contacts, analytics, ads, telephony, accounts, billing, webhooks

#### 404: The authenticated user has never authorized this client. Error code: oauth_client_not_found.

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
