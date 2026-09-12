# Send test webhook API Reference

Send a test webhook to verify your endpoint is configured correctly. The test payload includes event: "webhook.test" to distinguish it from real events.

`webhook.test` belongs to the `webhooks` resource group, so a key with that
group disabled is rejected with 403, as is a test fire on a subscription that
lists `webhooks` in its own `disabledResourceGroups` (a 403, not a reported
delivery failure). Replays of real events (redelivery, dead-letter requeue) run
the same checks as live delivery, against both the key's groups and the
subscription's.


## POST /v1/webhooks/test

**Send test webhook**

Send a test webhook to verify your endpoint is configured correctly. The test payload includes event: "webhook.test" to distinguish it from real events.

`webhook.test` belongs to the `webhooks` resource group, so a key with that
group disabled is rejected with 403, as is a test fire on a subscription that
lists `webhooks` in its own `disabledResourceGroups` (a 403, not a reported
delivery failure). Replays of real events (redelivery, dead-letter requeue) run
the same checks as live delivery, against both the key's groups and the
subscription's.


### Request Body

- **webhookId** (required) `string`: ID of the webhook to test

### Responses

#### 200: Test webhook sent successfully

**Response Body:**

- **success** `boolean`: No description
- **message** `string`: No description

#### 400: Webhook ID required

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: The API key is a restricted key (zrk_ prefix) and may not perform this operation. Three cases. (1) The operation's resource group (see the operation's x-resource-group) is disabled on the key: fix it by creating a key with the group enabled in the dashboard API keys tab and revoking the old one. (2) The operation is admin-plane (x-resource-group admin-plane: API keys, invites, connected apps, member identity), which is never grantable to restricted keys; the error reads "Restricted API keys cannot manage API keys, invites, or member identity." and the fix is a full-access key or the dashboard, never a new restricted key. (3) On webhook subscription writes, delivery-log reads and replays, a named event maps to a resource group the key does not hold, so a restricted key can never create or edit a subscription broader than itself (a no-messages key cannot subscribe to, test-fire, redeliver or read logs for message.* events).

**Response Body:**

- **error** `string`: No description (example: "This API key has the 'messages' resource group disabled. GET /api/v1/inbox/conversations requires it. Create a key with 'messages' enabled in the dashboard API keys tab.")
- **code** `string`: No description - one of: insufficient_permissions, unclassified_resource
- **required_group** `string`: The resource group the key needs for this operation. Absent on admin-plane and unclassified-path denials. - one of: publishing, engagement, messages, contacts, analytics, ads, telephony, accounts, billing, webhooks

#### 404: Webhook not found

#### 500: Test webhook failed to deliver

**Response Body:**

- **success** `boolean`: No description
- **message** `string`: No description

---

---
