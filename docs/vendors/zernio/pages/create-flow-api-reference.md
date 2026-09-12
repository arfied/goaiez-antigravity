# Create flow API Reference

Create a new WhatsApp Flow in DRAFT status. Optionally clone an existing flow.
After creating, upload a Flow JSON definition, then publish to make it sendable.


## GET /v1/whatsapp/flows

**List flows**

List all WhatsApp Flows for the Business Account (WABA) associated with the given account.


### Parameters

- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Flows retrieved

**Response Body:**

- **success** `boolean`: No description
- **flows** `array[object]`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **status** `string`: No description - one of: DRAFT, PUBLISHED, DEPRECATED, BLOCKED, THROTTLED
  - **categories** `array[string]`: 
  - **validation_errors** `array[object]`: 
    Type: `object`
  - **version** `integer`: 1-based version within the flow's clone lineage (Zernio-tracked; Meta has no native versioning). Standalone flows are version 1.
  - **lineageId** `string`: Stable group key for the flow's version lineage (the root flow's ID).

#### 400: WABA ID not found on account

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

## POST /v1/whatsapp/flows

**Create flow**

Create a new WhatsApp Flow in DRAFT status. Optionally clone an existing flow.
After creating, upload a Flow JSON definition, then publish to make it sendable.


### Request Body

- **accountId** (required) `string`: WhatsApp account ID
- **name** (required) `string`: Flow display name
- **categories** (required) `array`: Flow categories
- **cloneFlowId** `string`: Optional: ID of an existing flow to clone the Flow JSON from
- **asVersion** `boolean`: When cloning, true keeps the clone in cloneFlowId's version lineage (auto-numbered next version); false/absent creates an independent flow. Ignored without cloneFlowId.
- **endpointUri** `string`: HTTPS-only data exchange endpoint for the flow. Settable only while the flow is in DRAFT, and the flow's uploaded Flow JSON must declare data_api_version "3.0" for the endpoint to be used.

### Responses

#### 200: Flow created

**Response Body:**

- **success** `boolean`: No description
- **flow** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **status** `string`: No description (example: "DRAFT")
  - **categories** `array[string]`: 
  - **version** `integer`: Version within the clone lineage
  - **lineageId** `string`: Version-lineage group key

#### 400: Validation error

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
