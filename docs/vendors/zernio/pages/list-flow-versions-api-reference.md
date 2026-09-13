# List flow versions API Reference

List the flow's version history (the clone lineage Zernio tracks, since Meta has no
native versioning), newest version first. Each entry is enriched with the version's
live name and status from Meta. A flow with no lineage returns only itself as version 1.


## GET /v1/whatsapp/flows/{flowId}/versions

**List flow versions**

List the flow's version history (the clone lineage Zernio tracks, since Meta has no
native versioning), newest version first. Each entry is enriched with the version's
live name and status from Meta. A flow with no lineage returns only itself as version 1.


### Parameters

- **flowId** (required) in path: Flow ID
- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Version history

**Response Body:**

- **versions** `array[object]`: 
  - **flowId** `string`: No description
  - **version** `integer`: No description
  - **parentFlowId** `string,null`: No description
  - **name** `string,null`: No description
  - **status** `string,null`: No description
  - **missing** `boolean`: True when Meta no longer has this flow

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Flow or account not found

---

---
