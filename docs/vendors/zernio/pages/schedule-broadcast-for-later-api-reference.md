# Schedule broadcast for later API Reference

Schedule a draft broadcast to be sent at a future date and time.

## POST /v1/broadcasts/{broadcastId}/schedule

**Schedule broadcast for later**

Schedule a draft broadcast to be sent at a future date and time.

### Parameters

- **broadcastId** (required) in path: No description

### Request Body

- **scheduledAt** (required) `string`: No description

### Responses

#### 200: Broadcast scheduled

**Response Body:**

- **success** `boolean`: No description
- **broadcast** `object`: 
  - **id** `string`: No description
  - **status** `string`: No description
  - **scheduledAt** `string` (date-time): No description

#### 400: Invalid date or status

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
