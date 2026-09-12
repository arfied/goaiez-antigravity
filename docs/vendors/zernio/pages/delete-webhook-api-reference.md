# Delete webhook API Reference

Permanently delete a webhook configuration.

## GET /v1/webhooks/settings

**List webhooks**

Retrieve all configured webhooks for the authenticated user. Supports up to 50 webhooks per user.

### Responses

#### 200: Webhooks retrieved successfully

**Response Body:**

- **webhooks** `array[Webhook]`: 

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: The API key is a restricted key (zrk_ prefix) and may not perform this operation. Three cases. (1) The operation's resource group (see the operation's x-resource-group) is disabled on the key: fix it by creating a key with the group enabled in the dashboard API keys tab and revoking the old one. (2) The operation is admin-plane (x-resource-group admin-plane: API keys, invites, connected apps, member identity), which is never grantable to restricted keys; the error reads "Restricted API keys cannot manage API keys, invites, or member identity." and the fix is a full-access key or the dashboard, never a new restricted key. (3) On webhook subscription writes, delivery-log reads and replays, a named event maps to a resource group the key does not hold, so a restricted key can never create or edit a subscription broader than itself (a no-messages key cannot subscribe to, test-fire, redeliver or read logs for message.* events).

**Response Body:**

- **error** `string`: No description (example: "This API key has the 'messages' resource group disabled. GET /api/v1/inbox/conversations requires it. Create a key with 'messages' enabled in the dashboard API keys tab.")
- **code** `string`: No description - one of: insufficient_permissions, unclassified_resource
- **required_group** `string`: The resource group the key needs for this operation. Absent on admin-plane and unclassified-path denials. - one of: publishing, engagement, messages, contacts, analytics, ads, telephony, accounts, billing, webhooks

---

## POST /v1/webhooks/settings

**Create webhook**

Create a new webhook configuration. Maximum 50 webhooks per user.

`name`, `url` and `events` are required. `url` must be a valid URL and `events` must contain at least one event. Whitespace is trimmed from `url` before validation.

Webhooks are auto-disabled only once the endpoint has had no successful delivery for 3 days AND has either reached 20 consecutive terminal failures (each one an event that exhausted the full retry ladder) or been failing continuously for 3 days. The owner is emailed; re-enable it with `isActive: true`.

A restricted (zrk_) API key can only subscribe to events whose resource group
the key holds; an event outside the key's groups is rejected with 403, so a
restricted key can never create a subscription broader than itself.

`disabledResourceGroups` restricts the subscription itself, independently of
which key or session later reads it. Events in a disabled group are dropped
before delivery to this endpoint, on live delivery and on every replay path
(test fire, redelivery, dead-letter requeue), even if they are listed in
`events`. Omit it to receive everything in `events`, which is how existing
subscriptions behave. A restricted key's own disabled groups are always
unioned in.


### Request Body

- **name** (required) `string`: Webhook name (1-50 characters)
- **url** (required) `string`: Webhook endpoint URL (must be a valid URL, whitespace trimmed)
- **secret** `string`: Secret key for HMAC-SHA256 signature verification
- **events** (required) `array`: Events to subscribe to (at least one required)
- **isActive** `boolean`: Enable or disable webhook delivery. Defaults to `true` when omitted.
- **customHeaders** `object`: Custom headers to include in webhook requests
- **disabledResourceGroups** `array`: Resource groups this subscription does not receive (opt-out denylist). Omit or send an empty array to receive every event in `events`. Listing a group here drops its events before delivery and on every replay path. Set at creation it applies to everything this subscription ever receives; changed later via PUT it applies to events emitted after the change, with a five-minute tail for events already queued (see that operation). When the caller is a restricted (zrk_) key, that key's own disabled groups are unioned into whatever you send here, so a restricted key can never create a subscription wider than itself.

### Responses

#### 200: Webhook created successfully

**Response Body:**

- **success** `boolean`: No description
- **webhook**: `Webhook` - See schema definition

#### 400: Validation error or maximum webhooks reached

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: The API key is a restricted key (zrk_ prefix) and may not perform this operation. Three cases. (1) The operation's resource group (see the operation's x-resource-group) is disabled on the key: fix it by creating a key with the group enabled in the dashboard API keys tab and revoking the old one. (2) The operation is admin-plane (x-resource-group admin-plane: API keys, invites, connected apps, member identity), which is never grantable to restricted keys; the error reads "Restricted API keys cannot manage API keys, invites, or member identity." and the fix is a full-access key or the dashboard, never a new restricted key. (3) On webhook subscription writes, delivery-log reads and replays, a named event maps to a resource group the key does not hold, so a restricted key can never create or edit a subscription broader than itself (a no-messages key cannot subscribe to, test-fire, redeliver or read logs for message.* events).

**Response Body:**

- **error** `string`: No description (example: "This API key has the 'messages' resource group disabled. GET /api/v1/inbox/conversations requires it. Create a key with 'messages' enabled in the dashboard API keys tab.")
- **code** `string`: No description - one of: insufficient_permissions, unclassified_resource
- **required_group** `string`: The resource group the key needs for this operation. Absent on admin-plane and unclassified-path denials. - one of: publishing, engagement, messages, contacts, analytics, ads, telephony, accounts, billing, webhooks

---

## PUT /v1/webhooks/settings

**Update webhook**

Update an existing webhook configuration. All fields except `_id` are optional; only provided fields will be updated.

When provided, `name` must be 1-50 characters, `url` must be a valid URL, and `events` must contain at least one event. Whitespace is trimmed from `url` before validation.

Webhooks are auto-disabled only once the endpoint has had no successful delivery for 3 days AND has either reached 20 consecutive terminal failures (each one an event that exhausted the full retry ladder) or been failing continuously for 3 days. The owner is emailed; re-enable it with `isActive: true`.

A restricted (zrk_) API key can only set `events` to events whose resource
group the key holds; an event outside the key's groups is rejected with 403.
It also cannot widen an existing subscription past its own groups.

`disabledResourceGroups` replaces the subscription's own denylist, which
applies to delivery regardless of which key or session created it. Send an
empty array to clear it. A restricted key's own disabled groups are unioned
into the stored value on every update, so repointing a legacy unrestricted
subscription with a restricted key also narrows it.

Timing: the new denylist applies to every event emitted after the update.
Events already queued for delivery when the update landed were filtered
against the previous denylist and can still arrive at your endpoint for up
to five minutes after they were enqueued, because the delivery worker
trusts a five-minute enqueue-time snapshot before re-checking the
subscription. Retries beyond that window, dead-letter replays, test fires,
and redeliveries are all checked against the current denylist.


### Request Body

- **_id** (required) `string`: Webhook ID to update (required)
- **name** `string`: Webhook name (1-50 characters). Must be non-empty if provided.
- **url** `string`: Webhook endpoint URL (must be a valid URL, whitespace trimmed). Must be a valid URL if provided.
- **secret** `string`: Secret key for HMAC-SHA256 signature verification
- **events** `array`: Events to subscribe to. Must contain at least one event if provided.
- **isActive** `boolean`: Enable or disable webhook delivery
- **customHeaders** `object`: Custom headers to include in webhook requests
- **disabledResourceGroups** `array`: Replaces the subscription's denylist. Send an empty array to clear it and receive every event in `events` again. Omitting the field leaves the current denylist untouched. Applies to events emitted after the update; already-queued events can still deliver for up to five minutes after they were enqueued. When the caller is a restricted (zrk_) key, that key's own disabled groups are unioned back in either way, so a restricted key can neither clear nor widen a subscription past its own groups.

### Responses

#### 200: Webhook updated successfully

**Response Body:**

- **success** `boolean`: No description
- **webhook**: `Webhook` - See schema definition

#### 400: Validation error or missing webhook ID

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: The API key is a restricted key (zrk_ prefix) and may not perform this operation. Three cases. (1) The operation's resource group (see the operation's x-resource-group) is disabled on the key: fix it by creating a key with the group enabled in the dashboard API keys tab and revoking the old one. (2) The operation is admin-plane (x-resource-group admin-plane: API keys, invites, connected apps, member identity), which is never grantable to restricted keys; the error reads "Restricted API keys cannot manage API keys, invites, or member identity." and the fix is a full-access key or the dashboard, never a new restricted key. (3) On webhook subscription writes, delivery-log reads and replays, a named event maps to a resource group the key does not hold, so a restricted key can never create or edit a subscription broader than itself (a no-messages key cannot subscribe to, test-fire, redeliver or read logs for message.* events).

**Response Body:**

- **error** `string`: No description (example: "This API key has the 'messages' resource group disabled. GET /api/v1/inbox/conversations requires it. Create a key with 'messages' enabled in the dashboard API keys tab.")
- **code** `string`: No description - one of: insufficient_permissions, unclassified_resource
- **required_group** `string`: The resource group the key needs for this operation. Absent on admin-plane and unclassified-path denials. - one of: publishing, engagement, messages, contacts, analytics, ads, telephony, accounts, billing, webhooks

#### 404: Webhook not found

---

## DELETE /v1/webhooks/settings

**Delete webhook**

Permanently delete a webhook configuration.

### Parameters

- **id** (required) in query: Webhook ID to delete

### Responses

#### 200: Webhook deleted successfully

**Response Body:**

- **success** `boolean`: No description

#### 400: Webhook ID missing or not a valid ID

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: The API key is a restricted key (zrk_ prefix) and may not perform this operation. Three cases. (1) The operation's resource group (see the operation's x-resource-group) is disabled on the key: fix it by creating a key with the group enabled in the dashboard API keys tab and revoking the old one. (2) The operation is admin-plane (x-resource-group admin-plane: API keys, invites, connected apps, member identity), which is never grantable to restricted keys; the error reads "Restricted API keys cannot manage API keys, invites, or member identity." and the fix is a full-access key or the dashboard, never a new restricted key. (3) On webhook subscription writes, delivery-log reads and replays, a named event maps to a resource group the key does not hold, so a restricted key can never create or edit a subscription broader than itself (a no-messages key cannot subscribe to, test-fire, redeliver or read logs for message.* events).

**Response Body:**

- **error** `string`: No description (example: "This API key has the 'messages' resource group disabled. GET /api/v1/inbox/conversations requires it. Create a key with 'messages' enabled in the dashboard API keys tab.")
- **code** `string`: No description - one of: insufficient_permissions, unclassified_resource
- **required_group** `string`: The resource group the key needs for this operation. Absent on admin-plane and unclassified-path denials. - one of: publishing, engagement, messages, contacts, analytics, ads, telephony, accounts, billing, webhooks

---
