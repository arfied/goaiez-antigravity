# Create invite token API Reference

Generate a secure invite link to grant team members access to your profiles.
Invites expire after 7 days and are single-use.

Returns 403 when a requested profile is not found or not owned, or when
called with a restricted (zrk_) API key: invite management is admin-plane.


## POST /v1/invite/tokens

**Create invite token**

Generate a secure invite link to grant team members access to your profiles.
Invites expire after 7 days and are single-use.

Returns 403 when a requested profile is not found or not owned, or when
called with a restricted (zrk_) API key: invite management is admin-plane.


### Request Body

- **scope** (required) `string`: 'all' grants access to all profiles, 'profiles' restricts to specific profiles - one of: all, profiles
- **profileIds** `array`: Required if scope is 'profiles'. Array of profile IDs to grant access to.
- **role** `string`: Org role granted to the invitee. Defaults to 'member'. 'admin' can manage the team (invite/remove members, change roles and access) and billing, but not ownership transfer or account deletion. 'billing_admin' (displayed as Billing Manager) manages billing only. 'viewer' creates a read-only member who can view everything in their profile scope but cannot perform any content mutation (publish, edit, delete, connect accounts). - one of: admin, member, billing_admin, viewer
- **readOnly** `boolean`: Deprecated. Use role 'viewer' instead. When true, the invite is created with role 'viewer'. Cannot be combined with role 'billing_admin' or 'admin'.

### Responses

#### 201: Invite token created

**Response Body:**

- **token** `string`: No description
- **scope** `string`: No description
- **invitedProfileIds** `array[string]`: 
- **expiresAt** `string` (date-time): No description
- **inviteUrl** `string` (uri): No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: The API key is a restricted key (zrk_ prefix) and may not perform this operation. Three cases. (1) The operation's resource group (see the operation's x-resource-group) is disabled on the key: fix it by creating a key with the group enabled in the dashboard API keys tab and revoking the old one. (2) The operation is admin-plane (x-resource-group admin-plane: API keys, invites, connected apps, member identity), which is never grantable to restricted keys; the error reads "Restricted API keys cannot manage API keys, invites, or member identity." and the fix is a full-access key or the dashboard, never a new restricted key. (3) On webhook subscription writes, delivery-log reads and replays, a named event maps to a resource group the key does not hold, so a restricted key can never create or edit a subscription broader than itself (a no-messages key cannot subscribe to, test-fire, redeliver or read logs for message.* events).

**Response Body:**

- **error** `string`: No description (example: "This API key has the 'messages' resource group disabled. GET /api/v1/inbox/conversations requires it. Create a key with 'messages' enabled in the dashboard API keys tab.")
- **code** `string`: No description - one of: insufficient_permissions, unclassified_resource
- **required_group** `string`: The resource group the key needs for this operation. Absent on admin-plane and unclassified-path denials. - one of: publishing, engagement, messages, contacts, analytics, ads, telephony, accounts, billing, webhooks

---

---
