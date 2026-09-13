# Cancel broadcast API Reference

Cancel a scheduled or in-progress broadcast. Already-sent messages are not affected.

## POST /v1/broadcasts/{broadcastId}/cancel

**Cancel broadcast**

Cancel a scheduled or in-progress broadcast. Already-sent messages are not affected.

### Parameters

- **broadcastId** (required) in path: No description

### Responses

#### 200: Broadcast cancelled

**Response Body:**

- **success** `boolean`: No description
- **broadcast** `object`: 
  - **id** `string`: No description
  - **status** `string`: No description

#### 400: Cannot cancel in current status

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
