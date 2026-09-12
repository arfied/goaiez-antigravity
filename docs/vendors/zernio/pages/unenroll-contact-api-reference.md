# Unenroll contact API Reference

Remove a contact from a sequence. No further messages will be sent to this contact.

## DELETE /v1/sequences/{sequenceId}/enroll/{contactId}

**Unenroll contact**

Remove a contact from a sequence. No further messages will be sent to this contact.

### Parameters

- **sequenceId** (required) in path: No description
- **contactId** (required) in path: No description

### Responses

#### 200: Contact unenrolled

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
