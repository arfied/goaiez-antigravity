# Add reaction API Reference

Add an emoji reaction to a message. Platform support:
- Telegram: Supports a subset of Unicode emoji reactions
- WhatsApp: Supports any standard emoji (one reaction per message per sender)
- Instagram and Facebook Messenger: Any standard emoji, subject to Meta's 24h messaging window
- Slack: The emoji must have a Slack name (e.g. `:thumbsup:`); unnamed characters return 400
- All others: Returns 400 (not supported)


## POST /v1/inbox/conversations/{conversationId}/messages/{messageId}/reactions

**Add reaction**

Add an emoji reaction to a message. Platform support:
- Telegram: Supports a subset of Unicode emoji reactions
- WhatsApp: Supports any standard emoji (one reaction per message per sender)
- Instagram and Facebook Messenger: Any standard emoji, subject to Meta's 24h messaging window
- Slack: The emoji must have a Slack name (e.g. `:thumbsup:`); unnamed characters return 400
- All others: Returns 400 (not supported)


### Parameters

- **conversationId** (required) in path: The conversation ID
- **messageId** (required) in path: The platform message ID (as returned by GET /messages) or the Zernio message ID (as returned by the reaction webhook)

### Request Body

- **accountId** (required) `string`: Account ID
- **emoji** (required) `string`: Emoji character (e.g. "👍", "❤️")

### Responses

#### 200: The platform accepted the reaction request. This does not guarantee the reaction was placed: the platform never confirms what it acted on.

**Response Body:**

- **success** `boolean`: No description
- **messageId** `string`: The Zernio message ID the reaction was resolved against
- **platformMessageId** `string`: The platform message ID the reaction was sent for

#### 400: Platform does not support reactions or invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

#### 404: Account, conversation or message not found (message_not_found when messageId does not resolve to a message in this conversation)

---

## DELETE /v1/inbox/conversations/{conversationId}/messages/{messageId}/reactions

**Remove reaction**

Remove a reaction from a message. Platform support:
- Telegram: Send empty reaction array to clear
- WhatsApp: Send empty emoji to remove
- Instagram and Facebook Messenger: Sends Meta's `unreact` action; the emoji does not need to be repeated
- Slack: Removes the reaction we previously sent on that message
- All others: Returns 400 (not supported)


### Parameters

- **conversationId** (required) in path: The conversation ID
- **messageId** (required) in path: The platform message ID (as returned by GET /messages) or the Zernio message ID (as returned by the reaction webhook)
- **accountId** (required) in query: Account ID

### Responses

#### 200: The platform accepted the removal request. This does not guarantee a reaction was removed: the platform never confirms what it acted on, and a reaction placed by the other participant cannot be removed (platform rule). Check `fromMe` on GET /messages to know who placed a reaction.

**Response Body:**

- **success** `boolean`: No description
- **messageId** `string`: The Zernio message ID the removal was resolved against
- **platformMessageId** `string`: The platform message ID the removal was sent for

#### 400: Platform does not support reactions or invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

#### 404: Account, conversation or message not found (message_not_found when messageId does not resolve to a message in this conversation)

---

---
