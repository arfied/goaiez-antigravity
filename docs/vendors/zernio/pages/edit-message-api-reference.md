# Edit message API Reference

Edit the text and/or reply markup of a previously sent Telegram message.
Only supported for Telegram. Returns 400 for other platforms.


## PATCH /v1/inbox/conversations/{conversationId}/messages/{messageId}

**Edit message**

Edit the text and/or reply markup of a previously sent Telegram message.
Only supported for Telegram. Returns 400 for other platforms.


### Parameters

- **conversationId** (required) in path: The conversation ID
- **messageId** (required) in path: The Telegram message ID to edit

### Request Body

- **accountId** (required) `string`: Account ID
- **text** `string`: New message text
- **replyMarkup** `object`: New inline keyboard markup

### Responses

#### 200: Message edited

**Response Body:**

- **success** `boolean`: No description
- **data** `object`: 
  - **messageId** `integer`: No description

#### 400: Not supported or invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

---

## DELETE /v1/inbox/conversations/{conversationId}/messages/{messageId}

**Delete message**

Delete a message from a conversation. Platform support varies:
- Telegram: Full delete (bot's own messages anytime, others if admin)
- X: Full delete (own DM events only)
- Bluesky: Delete for self only (recipient still sees it)
- Reddit: Delete from sender's view only
- Facebook, Instagram, WhatsApp: Not supported (returns 400)


### Parameters

- **conversationId** (required) in path: The conversation ID
- **messageId** (required) in path: The platform message ID to delete
- **accountId** (required) in query: Account ID

### Responses

#### 200: Message deleted

**Response Body:**

- **success** `boolean`: No description

#### 400: Platform does not support deletion or invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

#### 404: Account or conversation not found

---

---
