# Publish flow API Reference

Publish a DRAFT flow. This is irreversible. Once published, the flow and its JSON
become immutable and the flow can be sent to users. To update a published flow,
create a new flow (optionally cloning this one via cloneFlowId).


## POST /v1/whatsapp/flows/{flowId}/publish

**Publish flow**

Publish a DRAFT flow. This is irreversible. Once published, the flow and its JSON
become immutable and the flow can be sent to users. To update a published flow,
create a new flow (optionally cloning this one via cloneFlowId).


### Parameters

- **flowId** (required) in path: Flow ID

### Request Body

- **accountId** (required) `string`: WhatsApp account ID

### Responses

#### 200: Flow published

**Response Body:**

- **success** `boolean`: No description

#### 400: Flow is not in DRAFT status or has validation errors

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
