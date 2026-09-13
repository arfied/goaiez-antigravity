# Activate sequence API Reference

Start a draft or paused sequence. The sequence must have at least one step.

## POST /v1/sequences/{sequenceId}/activate

**Activate sequence**

Start a draft or paused sequence. The sequence must have at least one step.

### Parameters

- **sequenceId** (required) in path: No description

### Responses

#### 200: Sequence activated

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
