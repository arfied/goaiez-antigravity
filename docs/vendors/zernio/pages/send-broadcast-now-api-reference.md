# Send broadcast now API Reference

Immediately start sending a draft broadcast to its recipients.

## POST /v1/broadcasts/{broadcastId}/send

**Send broadcast now**

Immediately start sending a draft broadcast to its recipients.

### Parameters

- **broadcastId** (required) in path: No description

### Responses

#### 200: Broadcast sending started

**Response Body:**

- **success** `boolean`: No description
- **status** `string`: Current broadcast status after processing first batch - one of: sending, completed, failed
- **sent** `integer`: Recipients sent in this batch
- **failed** `integer`: Recipients failed in this batch
- **recipientCount** `integer`: Total recipient count

#### 400: Invalid status or no recipients

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
