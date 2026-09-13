# Get broadcast details API Reference

Returns a broadcast with its full configuration and delivery stats.

## GET /v1/broadcasts/{broadcastId}

**Get broadcast details**

Returns a broadcast with its full configuration and delivery stats.

### Parameters

- **broadcastId** (required) in path: No description

### Responses

#### 200: Broadcast details with stats

**Response Body:**

- **success** `boolean`: No description
- **broadcast** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **platform** `string`: No description
  - **accountId** `string`: No description
  - **message** `object`: 
    - **text** `string`: No description
  - **template** `object`: 
    - **name** `string`: No description
    - **language** `string`: No description
  - **segmentFilters** `object`: 
    - **tags** `array[string]`: 
  - **status** `string`: No description - one of: draft, scheduled, sending, completed, failed, cancelled
  - **scheduledAt** `string` (date-time): No description
  - **startedAt** `string` (date-time): No description
  - **completedAt** `string` (date-time): No description
  - **recipientCount** `integer`: No description
  - **sentCount** `integer`: No description
  - **deliveredCount** `integer`: No description
  - **readCount** `integer`: No description
  - **failedCount** `integer`: No description
  - **createdAt** `string` (date-time): No description
  - **updatedAt** `string` (date-time): No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## PATCH /v1/broadcasts/{broadcastId}

**Update broadcast**

Update a broadcast's name, message, template, or segment filters. Only draft broadcasts can be updated.

### Parameters

- **broadcastId** (required) in path: No description

### Request Body

- **name** `string`: No description
- **description** `string`: No description
- **message** `object`: Generic message payload (used for non-WhatsApp platforms).
- **template** `object`: WhatsApp template payload (used when platform is `whatsapp`).
- **segmentFilters** `object`: Recipient segment filters (tags, channels, subscription state).

### Responses

#### 200: Broadcast updated

**Response Body:**

- **success** `boolean`: No description
- **broadcast** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **status** `string`: No description
  - **updatedAt** `string` (date-time): No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## DELETE /v1/broadcasts/{broadcastId}

**Delete broadcast**

Permanently delete a broadcast. Only drafts can be deleted.

### Parameters

- **broadcastId** (required) in path: No description

### Responses

#### 200: Broadcast deleted

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
