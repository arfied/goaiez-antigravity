# Update sequence API Reference

Update a sequence's name, steps, or exit conditions. Steps can only be modified while the sequence is draft or paused.

## GET /v1/sequences/{sequenceId}

**Get sequence with steps**

Returns a sequence with all its steps and enrollment stats.

### Parameters

- **sequenceId** (required) in path: No description

### Responses

#### 200: Sequence details with steps

**Response Body:**

- **success** `boolean`: No description
- **sequence** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **platform** `string`: No description
  - **accountId** `string`: No description
  - **status** `string`: No description - one of: draft, active, paused
  - **steps** `array[object]`: 
    - **order** `integer`: No description
    - **delayMinutes** `integer`: No description
    - **message** `object`: 
      - **text** `string`: No description
    - **template** `object`: 
      - **name** `string`: No description
      - **language** `string`: No description
      - **variableMapping** `object`: No description
  - **exitOnReply** `boolean`: No description
  - **exitOnUnsubscribe** `boolean`: No description
  - **totalEnrolled** `integer`: No description
  - **totalCompleted** `integer`: No description
  - **totalExited** `integer`: No description
  - **createdAt** `string` (date-time): No description
  - **updatedAt** `string` (date-time): No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## PATCH /v1/sequences/{sequenceId}

**Update sequence**

Update a sequence's name, steps, or exit conditions. Steps can only be modified while the sequence is draft or paused.

### Parameters

- **sequenceId** (required) in path: No description

### Request Body

- **name** `string`: No description
- **description** `string`: No description
- **steps** `array`: Replace the full step list. Only allowed while the sequence is draft or paused.
- **exitOnReply** `boolean`: No description
- **exitOnUnsubscribe** `boolean`: No description

### Responses

#### 200: Sequence updated

**Response Body:**

- **success** `boolean`: No description
- **sequence** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **status** `string`: No description
  - **steps** `array[object]`: 
    Type: `object`
  - **exitOnReply** `boolean`: No description
  - **exitOnUnsubscribe** `boolean`: No description
  - **updatedAt** `string` (date-time): No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## DELETE /v1/sequences/{sequenceId}

**Delete sequence**

Permanently delete a sequence. Active enrollments are stopped.

### Parameters

- **sequenceId** (required) in path: No description

### Responses

#### 200: Sequence deleted

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
