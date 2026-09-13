# Cancel a port-in API Reference

Cancel an in-flight port (wrong number, staying with the old carrier).
Only orders that haven't ported can be cancelled; a completed port is a
normal number release instead. The carrier may report `cancel-pending`
briefly while the losing carrier acknowledges; it settles to
`cancelled`.


## DELETE /v1/phone-numbers/port-in/{id}

**Cancel a port-in**

Cancel an in-flight port (wrong number, staying with the old carrier).
Only orders that haven't ported can be cancelled; a completed port is a
normal number release instead. The carrier may report `cancel-pending`
briefly while the losing carrier acknowledges; it settles to
`cancelled`.


### Parameters

- **id** (required) in path: Porting order ID (from the port-in list).

### Responses

#### 200: Cancel accepted (idempotent when already cancelled).

**Response Body:**

- **id** `string`: No description
- **status** `string`: No description - one of: draft, pending, foc_confirmed, ported, exception, cancelled

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Porting order not found

#### 409: Port already completed (release the number instead), or the carrier rejected the cancel (reason included)

---

---
