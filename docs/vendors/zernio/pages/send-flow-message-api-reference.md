# Send flow message API Reference

Send a published flow as an interactive message with a CTA button.
When the recipient taps the button, the flow opens natively in WhatsApp.
Flow responses are received via webhooks.


## POST /v1/whatsapp/flows/send

**Send flow message**

Send a published flow as an interactive message with a CTA button.
When the recipient taps the button, the flow opens natively in WhatsApp.
Flow responses are received via webhooks.


### Request Body

- **accountId** (required) `string`: WhatsApp account ID
- **to** (required) `string`: Recipient phone number (E.164 format, e.g. +1234567890)
- **flow_id** (required) `string`: Published flow ID
- **flow_cta** (required) `string`: CTA button text (e.g. 'Book Now', 'Sign Up')
- **flow_action** `string`: Action type: navigate opens a screen directly, data_exchange hits your endpoint first - one of: navigate, data_exchange
- **flow_token** `string`: Unique token to correlate responses. If omitted, auto-generated as '<flowId>:<uuid>' so the response can be attributed to this flow in the Flow Responses view.
- **flow_action_payload** `object`: No description
- **body** (required) `string`: Message body text
- **header** `object`: No description
- **footer** `string`: Optional footer text
- **draft** `boolean`: Set true to test an unpublished (DRAFT) flow

### Responses

#### 200: Flow message sent

**Response Body:**

- **success** `boolean`: No description
- **messageId** `string`: WhatsApp message ID (WAMID)

#### 400: Validation error or missing phone number ID

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
