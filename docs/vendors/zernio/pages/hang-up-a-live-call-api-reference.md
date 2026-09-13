# Hang up a live call API Reference

Hangs up a live call on demand. Idempotent: ending a call that already
ended (or never connected) returns success with the call's current
status. Final duration/cost are written asynchronously when the hangup
event lands, so the call doc may briefly still show its prior status.


## POST /v1/voice/calls/{id}/end

**Hang up a live call**

Hangs up a live call on demand. Idempotent: ending a call that already
ended (or never connected) returns success with the call's current
status. Final duration/cost are written asynchronously when the hangup
event lands, so the call doc may briefly still show its prior status.


### Parameters

- **id** (required) in path: No description

### Responses

#### 200: Hangup issued (or the call was already over).

**Response Body:**

- **success** `boolean`: No description
- **callId** `string`: No description
- **status** `string`: `ending` when a hangup was issued; otherwise the call's current status.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Call not found

#### 502: Carrier-side hangup failed

---

---
