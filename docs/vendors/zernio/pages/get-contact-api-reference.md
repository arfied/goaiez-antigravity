# Get contact API Reference

Returns a contact with all associated messaging channels.

## GET /v1/contacts/{contactId}

**Get contact**

Returns a contact with all associated messaging channels.

### Parameters

- **contactId** (required) in path: No description

### Responses

#### 200: Contact with channels

**Response Body:**

- **success** `boolean`: No description
- **contact** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **email** `string`: No description
  - **company** `string`: No description
  - **avatarUrl** `string`: No description
  - **tags** `array[string]`: 
  - **isSubscribed** `boolean`: No description
  - **isBlocked** `boolean`: No description
  - **messagesSentCount** `integer`: Messages sent to the contact, derived live from message history across all linked conversations.
  - **messagesReceivedCount** `integer`: Messages received from the contact, derived live from message history across all linked conversations.
  - **lastMessageSentAt** `string,null` (date-time): Timestamp of the most recent outgoing message, or null if none.
  - **lastMessageReceivedAt** `string,null` (date-time): Timestamp of the most recent incoming message, or null if none.
  - **customFields** `object`: No description
  - **notes** `string`: No description
  - **conversationIds** `array[string]`: 
  - **createdAt** `string` (date-time): No description
  - **updatedAt** `string` (date-time): No description
- **channels** `array[object]`: 
  - **id** `string`: No description
  - **accountId** `string`: No description
  - **platform** `string`: No description
  - **platformIdentifier** `string`: No description
  - **displayIdentifier** `string`: No description
  - **isSubscribed** `boolean`: No description
  - **conversationId** `string`: No description
  - **lastActiveAt** `string,null` (date-time): Most recent message (either direction) in this channel's conversation, or null if none.
  - **createdAt** `string` (date-time): No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## PATCH /v1/contacts/{contactId}

**Update contact**

Update one or more fields on a contact. Only provided fields are changed.

### Parameters

- **contactId** (required) in path: No description

### Request Body

- **name** `string`: No description
- **email** `string`: No description
- **company** `string`: No description
- **avatarUrl** `string`: No description
- **tags** `array`: No description
- **isSubscribed** `boolean`: No description
- **isBlocked** `boolean`: No description
- **notes** `string`: No description

### Responses

#### 200: Contact updated

**Response Body:**

- **success** `boolean`: No description
- **contact** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **email** `string`: No description
  - **company** `string`: No description
  - **avatarUrl** `string`: No description
  - **tags** `array[string]`: 
  - **isSubscribed** `boolean`: No description
  - **isBlocked** `boolean`: No description
  - **notes** `string`: No description
  - **updatedAt** `string` (date-time): No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## DELETE /v1/contacts/{contactId}

**Delete contact**

Permanently deletes a contact and all associated channels.

### Parameters

- **contactId** (required) in path: No description

### Responses

#### 200: Contact deleted

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
