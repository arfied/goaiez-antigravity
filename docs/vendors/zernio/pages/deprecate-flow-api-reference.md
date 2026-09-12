# Deprecate flow API Reference

Deprecate a PUBLISHED flow. This is irreversible. Deprecated flows cannot be sent
or opened, but existing active sessions may continue until they complete.


## POST /v1/whatsapp/flows/{flowId}/deprecate

**Deprecate flow**

Deprecate a PUBLISHED flow. This is irreversible. Deprecated flows cannot be sent
or opened, but existing active sessions may continue until they complete.


### Parameters

- **flowId** (required) in path: Flow ID

### Request Body

- **accountId** (required) `string`: WhatsApp account ID

### Responses

#### 200: Flow deprecated

**Response Body:**

- **success** `boolean`: No description

#### 400: Flow is not in PUBLISHED status

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
