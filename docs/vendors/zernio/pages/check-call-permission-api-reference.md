# Check call permission API Reference

Returns the permission state and the list of available actions for
a given consumer wa_id (e.g. `start_call`, `send_call_permission_request`).
Use this before placing a call to decide whether to prompt for
consent first.


## GET /v1/whatsapp/call-permissions

**Check call permission**

Returns the permission state and the list of available actions for
a given consumer wa_id (e.g. `start_call`, `send_call_permission_request`).
Use this before placing a call to decide whether to prompt for
consent first.


### Parameters

- **accountId** (required) in query: No description
- **to** (required) in query: Consumer wa_id (E.164, leading + optional)

### Responses

#### 200: Permission state

**Response Body:**

- **permission** `object`: 
  - **status** `string`: No description - one of: temporary, no_permission, permanent
  - **expiration_time** `integer`: Unix seconds when temporary
- **actions** `array[object]`: 
  - **action_name** `string`: No description - one of: send_call_permission_request, start_call
  - **can_perform_action** `boolean`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
