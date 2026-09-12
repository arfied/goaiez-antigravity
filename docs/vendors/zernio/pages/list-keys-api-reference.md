# List keys API Reference

Returns all API keys for the authenticated user. Keys are returned with a preview only, not the full key value.

## GET /v1/api-keys

**List keys**

Returns all API keys for the authenticated user. Keys are returned with a preview only, not the full key value.

### Responses

#### 200: API keys

**Response Body:**

- **apiKeys** `array[ApiKey]`: 

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: The API key is a restricted key (zrk_ prefix) and may not perform this operation. Three cases. (1) The operation's resource group (see the operation's x-resource-group) is disabled on the key: fix it by creating a key with the group enabled in the dashboard API keys tab and revoking the old one. (2) The operation is admin-plane (x-resource-group admin-plane: API keys, invites, connected apps, member identity), which is never grantable to restricted keys; the error reads "Restricted API keys cannot manage API keys, invites, or member identity." and the fix is a full-access key or the dashboard, never a new restricted key. (3) On webhook subscription writes, delivery-log reads and replays, a named event maps to a resource group the key does not hold, so a restricted key can never create or edit a subscription broader than itself (a no-messages key cannot subscribe to, test-fire, redeliver or read logs for message.* events).

**Response Body:**

- **error** `string`: No description (example: "This API key has the 'messages' resource group disabled. GET /api/v1/inbox/conversations requires it. Create a key with 'messages' enabled in the dashboard API keys tab.")
- **code** `string`: No description - one of: insufficient_permissions, unclassified_resource
- **required_group** `string`: The resource group the key needs for this operation. Absent on admin-plane and unclassified-path denials. - one of: publishing, engagement, messages, contacts, analytics, ads, telephony, accounts, billing, webhooks

---

## POST /v1/api-keys

**Create key**

Creates a new API key with an optional expiry. The full key value is only returned once in the response.

### Request Body

- **name** (required) `string`: No description
- **expiresIn** `integer`: Days until expiry
- **scope** `string`: 'full' grants access to all profiles (default), 'profiles' restricts to specific profiles - one of: full, profiles
- **profileIds** `array`: Profile IDs this key can access. Required when scope is 'profiles'.
- **permission** `string`: 'read-write' allows all operations (default), 'read' restricts to GET requests only - one of: read-write, read
- **disabledResourceGroups** `array`: Resource groups to DISABLE on this key (opt-out denylist). Omit for a legacy full-access key. A key with any group disabled mints with the zrk_ prefix, gets 403 with code=insufficient_permissions and required_group on operations in disabled groups (each operation's group is published as x-resource-group), and can never manage API keys, invites, or member identity. With 'messages' disabled, the key cannot read or send direct messages through any API surface and cannot create or edit a webhook subscription broader than itself. Subscriptions that already exist are governed by their own `disabledResourceGroups`, not by this key's. OAuth connector tokens resolve against the same registry, but their groups are not settable yet.

### Responses

#### 201: Created

**Response Body:**

- **message** `string`: No description
- **apiKey**: `ApiKey` - See schema definition

#### 400: Invalid request (missing name, invalid scope/permission, or missing profileIds when scope is 'profiles')

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: The API key is a restricted key (zrk_ prefix) and may not perform this operation. Three cases. (1) The operation's resource group (see the operation's x-resource-group) is disabled on the key: fix it by creating a key with the group enabled in the dashboard API keys tab and revoking the old one. (2) The operation is admin-plane (x-resource-group admin-plane: API keys, invites, connected apps, member identity), which is never grantable to restricted keys; the error reads "Restricted API keys cannot manage API keys, invites, or member identity." and the fix is a full-access key or the dashboard, never a new restricted key. (3) On webhook subscription writes, delivery-log reads and replays, a named event maps to a resource group the key does not hold, so a restricted key can never create or edit a subscription broader than itself (a no-messages key cannot subscribe to, test-fire, redeliver or read logs for message.* events).

**Response Body:**

- **error** `string`: No description (example: "This API key has the 'messages' resource group disabled. GET /api/v1/inbox/conversations requires it. Create a key with 'messages' enabled in the dashboard API keys tab.")
- **code** `string`: No description - one of: insufficient_permissions, unclassified_resource
- **required_group** `string`: The resource group the key needs for this operation. Absent on admin-plane and unclassified-path denials. - one of: publishing, engagement, messages, contacts, analytics, ads, telephony, accounts, billing, webhooks

---
