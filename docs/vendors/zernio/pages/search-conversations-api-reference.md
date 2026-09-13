# Search conversations API Reference

Search your conversations two ways at once, and get back the matching conversations, most-recent match first:

- Message text: matches words inside message bodies. Case-insensitive and accent-insensitive, exact tokens only (no substrings, no stemming). Each hit carries up to 3 most-recent matching messages. With direction=outgoing you can collect examples of how you write to customers, for example to teach an AI agent your tone of voice.
- Contact identity: matches the participant's name, username, or phone number as a case-insensitive substring. These hits have matchCount 0 and an empty matches array.

A conversation that matches both ways is returned once, carrying its message matches.

Only platforms whose messages are stored by Zernio are searchable: WhatsApp, SMS, Telegram, Facebook, Instagram, X and Reddit. Bluesky conversations are fetched live from the platform and cannot be searched; those accounts are listed in meta.accountsSkipped.


## GET /v1/inbox/conversations/search

**Search conversations**

Search your conversations two ways at once, and get back the matching conversations, most-recent match first:

- Message text: matches words inside message bodies. Case-insensitive and accent-insensitive, exact tokens only (no substrings, no stemming). Each hit carries up to 3 most-recent matching messages. With direction=outgoing you can collect examples of how you write to customers, for example to teach an AI agent your tone of voice.
- Contact identity: matches the participant's name, username, or phone number as a case-insensitive substring. These hits have matchCount 0 and an empty matches array.

A conversation that matches both ways is returned once, carrying its message matches.

Only platforms whose messages are stored by Zernio are searchable: WhatsApp, SMS, Telegram, Facebook, Instagram, X and Reddit. Bluesky conversations are fetched live from the platform and cannot be searched; those accounts are listed in meta.accountsSkipped.


### Parameters

- **query** (required) in query: Text to search for, in message content and in the contact's name, username, or phone number
- **direction** (optional) in query: Only match messages sent to you (incoming) or by you (outgoing). Contact-identity matching is not applied when this is set.
- **profileId** (optional) in query: Filter by profile ID
- **platform** (optional) in query: Filter by platform (searchable platforms only)
- **accountId** (optional) in query: Filter by specific account ID
- **limit** (optional) in query: Maximum number of conversations to return
- **cursor** (optional) in query: Opaque pagination cursor. Pass back pagination.nextCursor verbatim; do not construct one.

### Responses

#### 200: Conversations containing the query, most recent match first

**Response Body:**

- **data** `array[object]`: 
  - **conversation** `object`: 
    - **id** `string`: Conversation ID, usable with the conversation messages endpoints
    - **platform** `string`: No description
    - **accountId** `string`: No description
    - **participantName** `string,null`: No description
    - **participantUsername** `string,null`: No description
    - **participantPicture** `string,null`: No description
    - **status** `string`: No description - one of: active, archived
    - **lastMessage** `string,null`: The conversation's most recent message preview
    - **lastMessageAt** `string,null` (date-time): No description
  - **matchCount** `integer`: Number of matching messages in this conversation. 0 when the conversation matched only on contact identity (name, username, or phone number), not on message text.
  - **matches** `array[object]`: Up to 3 most-recent matching messages (empty for an identity-only match)
    - **id** `string`: No description
    - **text** `string,null`: No description
    - **direction** `string`: No description - one of: incoming, outgoing
    - **timestamp** `string` (date-time): No description
- **pagination** `object`: 
  - **hasMore** `boolean`: No description
  - **nextCursor** `string,null`: No description
- **meta** `object`: 
  - **accountsQueried** `integer`: No description
  - **accountsFailed** `integer`: No description
  - **failedAccounts** `array[object]`: 
    - **accountId** `string`: No description
    - **accountUsername** `string,null`: No description
    - **platform** `string`: No description
    - **error** `string`: No description
  - **lastUpdated** `string` (date-time): No description
  - **accountsSkipped** `array[object]`: Connected messaging accounts that cannot be searched (live-fetched platforms)
    - **accountId** `string`: No description
    - **platform** `string`: No description

#### 400: Invalid query, unsupported platform, or malformed cursor

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

---

---
