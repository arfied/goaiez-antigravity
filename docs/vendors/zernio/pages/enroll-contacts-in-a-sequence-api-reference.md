# Enroll contacts in a sequence API Reference

Enroll one or more contacts into a sequence. Contacts already enrolled are skipped.

## POST /v1/sequences/{sequenceId}/enroll

**Enroll contacts in a sequence**

Enroll one or more contacts into a sequence. Contacts already enrolled are skipped.

### Parameters

- **sequenceId** (required) in path: No description

### Request Body

- **contactIds** (required) `array`: No description
- **channelIds** `array`: Optional. Auto-detected if not provided.

### Responses

#### 200: Enrollment results

**Response Body:**

- **success** `boolean`: No description
- **enrolled** `integer`: Number of contacts successfully enrolled
- **failed** `integer`: Number that failed (already enrolled, or no subscribed channel on the sequence platform)
- **results** `array[object]`: Per-contact outcome
  - **contactId** `string`: No description
  - **success** `boolean`: No description
  - **error** `string`: Present when success is false

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
