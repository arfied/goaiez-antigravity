# Blind-transfer a live call API Reference

Moves the call's current leg to a new destination (a phone number or a
SIP endpoint). This is a BLIND transfer: control of the leg is handed
off and the call ends normally when the transferred leg hangs up. The
caller ID presented on the transfer leg is always your own number.


## POST /v1/voice/calls/{id}/transfer

**Blind-transfer a live call**

Moves the call's current leg to a new destination (a phone number or a
SIP endpoint). This is a BLIND transfer: control of the leg is handed
off and the call ends normally when the transferred leg hangs up. The
caller ID presented on the transfer leg is always your own number.


### Parameters

- **id** (required) in path: No description

### Request Body

- **to** (required) `string`: +E164 phone number (tel: prefix optional) or a sip: URI. wss:// is not a valid transfer target.

### Responses

#### 200: Transfer issued.

**Response Body:**

- **success** `boolean`: No description
- **callId** `string`: No description
- **transferredTo** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Call not found

#### 409: Call is not connected yet, or has already ended

---

---
