# Create workflow API Reference

Create a branching conversation workflow (draft) from a node/edge graph. Created in `draft` status; activate it to start matching inbound messages. The graph is validated structurally; completeness (a trigger node + reachable entry) is required at activation.


## GET /v1/workflows

**List workflows**

Returns workflows with run stats. Filter by status or profile.

### Parameters

- **profileId** (optional) in query: Filter by profile. Omit to list across all profiles
- **status** (optional) in query: No description
- **limit** (optional) in query: No description
- **skip** (optional) in query: No description

### Responses

#### 200: Workflows list

**Response Body:**

- **success** `boolean`: No description
- **workflows** `array[object]`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **platform** `string`: No description
  - **accountId** `string`: No description
  - **accountName** `string`: No description
  - **status** `string`: No description - one of: draft, active, paused
  - **nodeCount** `integer`: No description
  - **totalStarted** `integer`: No description
  - **totalCompleted** `integer`: No description
  - **totalExited** `integer`: No description
  - **createdAt** `string` (date-time): No description
- **pagination** `object`: 
  - **total** `integer`: No description
  - **limit** `integer`: No description
  - **skip** `integer`: No description
  - **hasMore** `boolean`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

## POST /v1/workflows

**Create workflow**

Create a branching conversation workflow (draft) from a node/edge graph. Created in `draft` status; activate it to start matching inbound messages. The graph is validated structurally; completeness (a trigger node + reachable entry) is required at activation.


### Request Body

- **profileId** (required) `string`: No description
- **accountId** (required) `string`: No description
- **platform** `string`: No description - one of: whatsapp, instagram, facebook, telegram, twitter, bluesky, reddit
- **name** (required) `string`: No description
- **description** `string`: No description
- **nodes** `array`: No description
- **edges** `array`: No description
- **entryNodeId** `string`: The trigger node id; derived from the single trigger node if omitted

### Responses

#### 200: Workflow created

**Response Body:**

- **success** `boolean`: No description
- **workflow** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **platform** `string`: No description
  - **status** `string`: No description
  - **nodeCount** `integer`: No description
  - **entryNodeId** `string`: No description
  - **createdAt** `string` (date-time): No description

#### 400: Invalid graph (duplicate node ids, edges referencing missing nodes, a WhatsApp-only node on another platform, or a WhatsApp interactive list node whose sections carry no rows)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
