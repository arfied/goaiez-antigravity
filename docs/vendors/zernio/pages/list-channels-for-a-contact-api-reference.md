# List channels for a contact API Reference

Returns all messaging channels linked to a contact (e.g. Instagram DM, Telegram, WhatsApp).

## GET /v1/contacts/{contactId}/channels

**List channels for a contact**

Returns all messaging channels linked to a contact (e.g. Instagram DM, Telegram, WhatsApp).

### Parameters

- **contactId** (required) in path: No description

### Responses

#### 200: List of contact channels

**Response Body:**

- **success** `boolean`: No description
- **channels** `array[object]`: 
  - **id** `string`: No description
  - **accountId** `string`: No description
  - **platform** `string`: No description
  - **platformIdentifier** `string`: No description
  - **displayIdentifier** `string`: No description
  - **isSubscribed** `boolean`: No description
  - **conversationId** `string`: No description
  - **metadata** `object`: No description
  - **createdAt** `string` (date-time): No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
