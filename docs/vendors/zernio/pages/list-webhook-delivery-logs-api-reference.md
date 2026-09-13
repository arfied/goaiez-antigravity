# List webhook delivery logs API Reference

Retrieve recorded webhook delivery attempts for the authenticated user, most recent first.
Logs are retained for 30 days. Supports filtering by status, event type, webhook ID, and event ID,
plus offset-based pagination.

For a restricted (zrk_) API key, rows for events outside the key's resource
groups are omitted (`pagination.total` may over-count), and an `event` filter
naming such an event is rejected with 403. Events blocked by a subscription's
own `disabledResourceGroups` are dropped before delivery, so they produce no
log rows for anyone; the exception is the five-minute tail after a denylist
change, where an already-queued event can still be delivered and logged.


## GET /v1/webhooks/logs

**List webhook delivery logs**

Retrieve recorded webhook delivery attempts for the authenticated user, most recent first.
Logs are retained for 30 days. Supports filtering by status, event type, webhook ID, and event ID,
plus offset-based pagination.

For a restricted (zrk_) API key, rows for events outside the key's resource
groups are omitted (`pagination.total` may over-count), and an `event` filter
naming such an event is rejected with 403. Events blocked by a subscription's
own `disabledResourceGroups` are dropped before delivery, so they produce no
log rows for anyone; the exception is the five-minute tail after a denylist
change, where an already-queued event can still be delivered and logged.


### Parameters

- **limit** (optional) in query: Maximum number of logs to return
- **skip** (optional) in query: Number of logs to skip (offset-based pagination)
- **status** (optional) in query: Filter by delivery outcome
- **event** (optional) in query: Filter by event type (e.g. post.published)
- **webhookId** (optional) in query: Filter by webhook configuration ID
- **eventId** (optional) in query: Filter by stable webhook event ID

### Responses

#### 200: Webhook logs retrieved successfully

**Response Body:**

- **logs** `array[WebhookLog]`: 
- **pagination** `object`: 
  - **total** `integer`: Total number of matching logs
  - **limit** `integer`: Maximum number of logs returned per page
  - **skip** `integer`: Number of logs skipped
  - **pages** `integer`: Total number of pages
  - **hasMore** `boolean`: Whether more logs are available beyond this page

#### 400: Invalid query parameter

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: The API key is a restricted key (zrk_ prefix) and may not perform this operation. Three cases. (1) The operation's resource group (see the operation's x-resource-group) is disabled on the key: fix it by creating a key with the group enabled in the dashboard API keys tab and revoking the old one. (2) The operation is admin-plane (x-resource-group admin-plane: API keys, invites, connected apps, member identity), which is never grantable to restricted keys; the error reads "Restricted API keys cannot manage API keys, invites, or member identity." and the fix is a full-access key or the dashboard, never a new restricted key. (3) On webhook subscription writes, delivery-log reads and replays, a named event maps to a resource group the key does not hold, so a restricted key can never create or edit a subscription broader than itself (a no-messages key cannot subscribe to, test-fire, redeliver or read logs for message.* events).

**Response Body:**

- **error** `string`: No description (example: "This API key has the 'messages' resource group disabled. GET /api/v1/inbox/conversations requires it. Create a key with 'messages' enabled in the dashboard API keys tab.")
- **code** `string`: No description - one of: insufficient_permissions, unclassified_resource
- **required_group** `string`: The resource group the key needs for this operation. Absent on admin-plane and unclassified-path denials. - one of: publishing, engagement, messages, contacts, analytics, ads, telephony, accounts, billing, webhooks

---
