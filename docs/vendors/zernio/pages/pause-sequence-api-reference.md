# Pause sequence API Reference

Pause an active sequence. Enrolled contacts stop receiving messages until the sequence is reactivated.

## POST /v1/sequences/{sequenceId}/pause

**Pause sequence**

Pause an active sequence. Enrolled contacts stop receiving messages until the sequence is reactivated.

### Parameters

- **sequenceId** (required) in path: No description

### Responses

#### 200: Sequence paused

**Response Body:**

- **success** `boolean`: No description
- **sequence** `object`: 
  - **id** `string`: No description
  - **status** `string`: No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
