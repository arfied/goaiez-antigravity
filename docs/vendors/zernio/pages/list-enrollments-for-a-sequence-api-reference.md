# List enrollments for a sequence API Reference

Returns enrolled contacts with their progress, status, and next scheduled step.

## GET /v1/sequences/{sequenceId}/enrollments

**List enrollments for a sequence**

Returns enrolled contacts with their progress, status, and next scheduled step.

### Parameters

- **sequenceId** (required) in path: No description
- **status** (optional) in query: No description
- **limit** (optional) in query: No description
- **skip** (optional) in query: No description

### Responses

#### 200: Enrollments list with progress

**Response Body:**

- **success** `boolean`: No description
- **enrollments** `array[object]`: 
  - **id** `string`: No description
  - **contactId** `string`: No description
  - **channelId** `string`: No description
  - **platformIdentifier** `string`: No description
  - **contactName** `string`: No description
  - **currentStepIndex** `integer`: No description
  - **status** `string`: No description - one of: active, completed, exited, paused
  - **exitReason** `string,null`: No description
  - **nextStepAt** `string,null` (date-time): No description
  - **stepsSent** `integer`: No description
  - **lastStepSentAt** `string,null` (date-time): No description
  - **createdAt** `string` (date-time): No description
- **pagination** `object`: 
  - **total** `integer`: No description
  - **limit** `integer`: No description
  - **skip** `integer`: No description
  - **hasMore** `boolean`: No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
