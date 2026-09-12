# Activate workflow API Reference

Validate the graph is runnable and set the workflow live. Once active, matching inbound messages start executions. Idempotent.

## POST /v1/workflows/{workflowId}/activate

**Activate workflow**

Validate the graph is runnable and set the workflow live. Once active, matching inbound messages start executions. Idempotent.

### Parameters

- **workflowId** (required) in path: No description

### Responses

#### 200: Workflow activated

**Response Body:**

- **success** `boolean`: No description
- **workflow** `object`: 
  - **id** `string`: No description
  - **status** `string`: No description
  - **entryNodeId** `string`: No description

#### 400: Incomplete or invalid graph

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
