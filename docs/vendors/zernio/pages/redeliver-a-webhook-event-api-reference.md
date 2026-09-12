# Redeliver a webhook event API Reference

Replay a past delivery: the original payload is re-sent, byte for byte, to the
subscription's current URL. The original event ID is preserved so your endpoint can
dedupe, and the replay is recorded as a fresh attempt, so it shows up in
`GET /v1/webhooks/logs` next to the delivery it replays.

Both `webhookId` and `eventId` come from a row of `GET /v1/webhooks/logs`. Because
the stored payload is replayed as-is, a redelivery reflects the event as it was
emitted, not the current state of the resource.

Only deliveries inside the 30-day log retention window can be replayed; past that
the payload is gone and the request fails with a 422. Replays run the same
resource-group checks as live delivery, against both the key's groups and the
subscription's `disabledResourceGroups`.


## POST /v1/webhooks/logs/redeliver

**Redeliver a webhook event**

Replay a past delivery: the original payload is re-sent, byte for byte, to the
subscription's current URL. The original event ID is preserved so your endpoint can
dedupe, and the replay is recorded as a fresh attempt, so it shows up in
`GET /v1/webhooks/logs` next to the delivery it replays.

Both `webhookId` and `eventId` come from a row of `GET /v1/webhooks/logs`. Because
the stored payload is replayed as-is, a redelivery reflects the event as it was
emitted, not the current state of the resource.

Only deliveries inside the 30-day log retention window can be replayed; past that
the payload is gone and the request fails with a 422. Replays run the same
resource-group checks as live delivery, against both the key's groups and the
subscription's `disabledResourceGroups`.


### Request Body

- **webhookId** (required) `string`: ID of the webhook subscription that delivered the event
- **eventId** (required) `string`: Stable event ID of the delivery to replay

### Responses

#### 200: Event re-delivered successfully

**Response Body:**

- **success** `boolean`: No description
- **message** `string`: No description

#### 400: webhookId or eventId missing or empty, or the subscription has no URL configured

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: The API key is a restricted key (zrk_ prefix) and may not perform this operation. Three cases. (1) The operation's resource group (see the operation's x-resource-group) is disabled on the key: fix it by creating a key with the group enabled in the dashboard API keys tab and revoking the old one. (2) The operation is admin-plane (x-resource-group admin-plane: API keys, invites, connected apps, member identity), which is never grantable to restricted keys; the error reads "Restricted API keys cannot manage API keys, invites, or member identity." and the fix is a full-access key or the dashboard, never a new restricted key. (3) On webhook subscription writes, delivery-log reads and replays, a named event maps to a resource group the key does not hold, so a restricted key can never create or edit a subscription broader than itself (a no-messages key cannot subscribe to, test-fire, redeliver or read logs for message.* events).

**Response Body:**

- **error** `string`: No description (example: "This API key has the 'messages' resource group disabled. GET /api/v1/inbox/conversations requires it. Create a key with 'messages' enabled in the dashboard API keys tab.")
- **code** `string`: No description - one of: insufficient_permissions, unclassified_resource
- **required_group** `string`: The resource group the key needs for this operation. Absent on admin-plane and unclassified-path denials. - one of: publishing, engagement, messages, contacts, analytics, ads, telephony, accounts, billing, webhooks

#### 404: Webhook subscription not found

#### 422: Original payload not replayable: no delivery matches this event inside the 30-day retention window, or the stored payload is truncated or not valid JSON

#### 502: Re-delivery was attempted but your endpoint errored again. The attempt is still
logged; `message` describes the failure.


**Response Body:**

- **success** `boolean`: No description
- **message** `string`: No description

---

---
