# List workflow runs API Reference

Returns recent executions (runs) with their status, current node, and accumulated variables.

## GET /v1/workflows/{workflowId}/executions

**List workflow runs**

Returns recent executions (runs) with their status, current node, and accumulated variables.

### Parameters

- **workflowId** (required) in path: No description
- **status** (optional) in query: No description
- **limit** (optional) in query: No description
- **skip** (optional) in query: No description

### Responses

#### 200: Executions list

**Response Body:**

- **success** `boolean`: No description
- **executions** `array[object]`: 
  - **id** `string`: No description
  - **status** `string`: No description - one of: running, waiting, completed, exited, failed
  - **currentNodeId** `string`: No description
  - **waitingFor** `object,null`: No description
  - **variables** `object`: No description
  - **platformIdentifier** `string`: No description
  - **conversationId** `string`: No description
  - **stepCount** `integer`: No description
  - **lastError** `string,null`: No description
  - **resumeAt** `string,null` (date-time): No description
  - **createdAt** `string` (date-time): No description
  - **updatedAt** `string` (date-time): No description
  - **completedAt** `string,null` (date-time): No description
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

## POST /v1/workflows/{workflowId}/executions

**Manually start a workflow run**

Kick off a run without waiting for an inbound message (useful for testing). Target an existing conversation by `conversationId`, or (WhatsApp only) a phone number via `to` (a conversation is found or created). `text` seeds the run's `lastMessage` variable. The graph must be runnable.


### Parameters

- **workflowId** (required) in path: No description

### Request Body

- **to** `string`: Recipient phone (WhatsApp only)
- **conversationId** `string`: An existing conversation to run in (required for non-WhatsApp workflows)
- **text** `string`: Simulated inbound text, seeded as the run's lastMessage variable

### Responses

#### 200: Run started

**Response Body:**

- **success** `boolean`: No description
- **execution** `object,null`: No description

#### 400: Missing target, invalid graph, or `to` used on a non-WhatsApp workflow

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
