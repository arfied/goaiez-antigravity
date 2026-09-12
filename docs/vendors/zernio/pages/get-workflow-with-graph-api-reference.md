# Get workflow with graph API Reference

Returns a workflow including its full node/edge graph and run stats.

## GET /v1/workflows/{workflowId}

**Get workflow with graph**

Returns a workflow including its full node/edge graph and run stats.

### Parameters

- **workflowId** (required) in path: No description

### Responses

#### 200: Workflow details

**Response Body:**

- **success** `boolean`: No description
- **workflow** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **platform** `string`: No description
  - **accountId** `string`: No description
  - **profileId** `string`: No description
  - **status** `string`: No description - one of: draft, active, paused
  - **entryNodeId** `string`: No description
  - **nodes** `array[WorkflowNode]`: 
  - **edges** `array[WorkflowEdge]`: 
  - **totalStarted** `integer`: No description
  - **totalCompleted** `integer`: No description
  - **totalExited** `integer`: No description
  - **createdAt** `string` (date-time): No description
  - **updatedAt** `string` (date-time): No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## PATCH /v1/workflows/{workflowId}

**Update workflow**

Update name, description, the graph, or reassign to a different account. The graph can only be modified while the workflow is draft or paused. Account swaps re-validate the graph against the new platform (so e.g. moving from WhatsApp to Facebook surfaces a `start_call` node as an error instead of silently saving an unrunnable graph).


### Parameters

- **workflowId** (required) in path: No description

### Request Body

- **name** `string`: No description
- **description** `string`: No description
- **nodes** `array`: No description
- **edges** `array`: No description
- **entryNodeId** `string,null`: No description
- **accountId** `string`: Reassign the workflow to a different `SocialAccount`. `platform` and `profileId` are derived server-side from the new account (the client never sends them directly). The account must belong to the caller's team and be on a workflow-supported platform (whatsapp, instagram, facebook, telegram, twitter, bluesky, reddit). Changing this triggers a graph revalidation against the new platform.


### Responses

#### 200: Workflow updated

**Response Body:**

- **success** `boolean`: No description
- **workflow** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **status** `string`: No description
  - **entryNodeId** `string`: No description
  - **nodeCount** `integer`: No description
  - **updatedAt** `string` (date-time): No description

#### 400: Invalid graph (including a WhatsApp interactive list node whose sections carry no rows), or a graph edit attempted while the workflow is active

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## DELETE /v1/workflows/{workflowId}

**Delete workflow**

Permanently delete a workflow and all of its executions.

### Parameters

- **workflowId** (required) in path: No description

### Responses

#### 200: Workflow deleted

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---
