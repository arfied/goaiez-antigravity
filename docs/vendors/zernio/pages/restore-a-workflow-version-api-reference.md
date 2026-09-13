# Restore a workflow version API Reference

Replace the current graph with the named version's snapshot. Before the swap, the current graph is itself snapshotted as a new version, so a restore is reversible. The workflow must be in `draft` or `paused` status (same gate as a normal graph edit). The returned workflow carries `restoredFromVersion` so the UI can surface which version was rolled back to.


## POST /v1/workflows/{workflowId}/versions/{version}/restore

**Restore a workflow version**

Replace the current graph with the named version's snapshot. Before the swap, the current graph is itself snapshotted as a new version, so a restore is reversible. The workflow must be in `draft` or `paused` status (same gate as a normal graph edit). The returned workflow carries `restoredFromVersion` so the UI can surface which version was rolled back to.


### Parameters

- **workflowId** (required) in path: No description
- **version** (required) in path: No description

### Responses

#### 200: Workflow restored to the named version

**Response Body:**

- **success** `boolean`: No description
- **workflow** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **status** `string`: No description
  - **entryNodeId** `string,null`: No description
  - **nodeCount** `integer`: No description
  - **updatedAt** `string` (date-time): No description
- **restoredFromVersion** `integer`: No description

#### 400: Workflow is not draft/paused, or the named version's graph is invalid for the current platform

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
