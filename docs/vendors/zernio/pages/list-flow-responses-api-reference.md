# List flow responses API Reference

List the responses customers submitted when completing a flow (parsed from the
nfm_reply messages received via webhook), newest first. Scope to a single flow
with `flowId`, which matches responses whose flow_token carries the `<flowId>:`
prefix that Zernio stamps on auto-generated tokens at send time. Responses sent
with a custom integrator-supplied flow_token are not attributed to a flow.


## GET /v1/whatsapp/flow-responses

**List flow responses**

List the responses customers submitted when completing a flow (parsed from the
nfm_reply messages received via webhook), newest first. Scope to a single flow
with `flowId`, which matches responses whose flow_token carries the `<flowId>:`
prefix that Zernio stamps on auto-generated tokens at send time. Responses sent
with a custom integrator-supplied flow_token are not attributed to a flow.


### Parameters

- **accountId** (required) in query: WhatsApp account ID
- **flowId** (optional) in query: Scope to responses for this flow
- **limit** (optional) in query: Max responses to return

### Responses

#### 200: Flow responses

**Response Body:**

- **responses** `array[object]`: 
  - **id** `string`: Message ID
  - **receivedAt** `string` (date-time): No description
  - **from** `string,null`: Sender wa_id / phone
  - **senderName** `string,null`: No description
  - **conversationId** `string,null`: No description
  - **flowToken** `string,null`: No description
  - **data** `object`: Submitted field values (flow_token removed)
  - **raw** `string,null`: Raw response_json string

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
