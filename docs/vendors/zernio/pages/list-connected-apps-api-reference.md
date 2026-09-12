# List connected apps API Reference

Returns the OAuth clients (AI assistants and MCP connectors) the authenticated
user has authorized and that still hold a live token.

Requires a session or a full-access API key. A profile-scoped API key, a
restricted (zrk_) API key, or an OAuth access token is rejected with 403: an
app must not be able to enumerate its sibling authorizations, and connected-app
management is admin-plane.


## GET /v1/me/connected-apps

**List connected apps**

Returns the OAuth clients (AI assistants and MCP connectors) the authenticated
user has authorized and that still hold a live token.

Requires a session or a full-access API key. A profile-scoped API key, a
restricted (zrk_) API key, or an OAuth access token is rejected with 403: an
app must not be able to enumerate its sibling authorizations, and connected-app
management is admin-plane.


### Responses

#### 200: Connected apps

**Response Body:**

- **connectedApps** `array[ConnectedApp]`: 

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: The API key is a restricted key (zrk_ prefix) and may not perform this operation. Three cases. (1) The operation's resource group (see the operation's x-resource-group) is disabled on the key: fix it by creating a key with the group enabled in the dashboard API keys tab and revoking the old one. (2) The operation is admin-plane (x-resource-group admin-plane: API keys, invites, connected apps, member identity), which is never grantable to restricted keys; the error reads "Restricted API keys cannot manage API keys, invites, or member identity." and the fix is a full-access key or the dashboard, never a new restricted key. (3) On webhook subscription writes, delivery-log reads and replays, a named event maps to a resource group the key does not hold, so a restricted key can never create or edit a subscription broader than itself (a no-messages key cannot subscribe to, test-fire, redeliver or read logs for message.* events).

**Response Body:**

- **error** `string`: No description (example: "This API key has the 'messages' resource group disabled. GET /api/v1/inbox/conversations requires it. Create a key with 'messages' enabled in the dashboard API keys tab.")
- **code** `string`: No description - one of: insufficient_permissions, unclassified_resource
- **required_group** `string`: The resource group the key needs for this operation. Absent on admin-plane and unclassified-path denials. - one of: publishing, engagement, messages, contacts, analytics, ads, telephony, accounts, billing, webhooks

---
