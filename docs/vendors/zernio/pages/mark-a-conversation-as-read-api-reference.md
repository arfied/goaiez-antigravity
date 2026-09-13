# Mark a conversation as read API Reference

Marks all unread incoming messages in the conversation as read.

For WhatsApp, this also sends read receipts (blue ticks) to the contact,
EXCEPT on coexistence accounts (where the WhatsApp Business app on the
customer's phone owns read state and we never override it).

This is the explicit, human-driven counterpart to `GET .../messages`,
which is side-effect-free and does NOT mark anything read. Call this when
a user actually views the conversation.


## POST /v1/inbox/conversations/{conversationId}/read

**Mark a conversation as read**

Marks all unread incoming messages in the conversation as read.

For WhatsApp, this also sends read receipts (blue ticks) to the contact,
EXCEPT on coexistence accounts (where the WhatsApp Business app on the
customer's phone owns read state and we never override it).

This is the explicit, human-driven counterpart to `GET .../messages`,
which is side-effect-free and does NOT mark anything read. Call this when
a user actually views the conversation.


### Parameters

- **conversationId** (required) in path: The conversation ID

### Request Body

- **accountId** (required) `string`: Account ID

### Responses

#### 200: Conversation marked read

**Response Body:**

- **success** `boolean`: No description
- **markedCount** `integer`: Number of messages marked read by this call

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

#### 404: Account or conversation not found

---

---
