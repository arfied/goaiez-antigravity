# Get conversation API Reference

Retrieve details and metadata for a specific conversation. Requires accountId query parameter.

## GET /v1/inbox/conversations/{conversationId}

**Get conversation**

Retrieve details and metadata for a specific conversation. Requires accountId query parameter.

### Parameters

- **conversationId** (required) in path: Opaque conversation identifier, accepted verbatim from the list endpoint or from the conversationId on inbox webhooks. Format not to be assumed.
- **accountId** (required) in query: The account ID

### Responses

#### 200: Conversation details

**Response Body:**

- **data** `object`: 
  - **id** `string`: No description
  - **accountId** `string`: No description
  - **accountUsername** `string`: No description
  - **platform** `string`: No description
  - **status** `string`: No description - one of: active, archived
  - **participantName** `string`: No description
  - **participantId** `string`: No description
  - **participantVerifiedType** `string,null`: X verified badge type. Only present for X conversations. - one of: blue, government, business, none
  - **lastMessage** `string`: No description
  - **lastMessageAt** `string` (date-time): No description
  - **updatedTime** `string` (date-time): No description
  - **participants** `array[object]`: 
    - **id** `string`: No description
    - **name** `string`: No description
  - **instagramProfile** `object,null`: Instagram profile data for the participant. Only present for Instagram conversations.
  - **metadata** `object,null`: Ad-click attribution for a conversation that started from a Meta ad.
Absent when the conversation did not originate from an ad click.

Captured once, on the first inbound message after the click, and never
overwritten. If the same person later clicks a different ad, the
original values are kept. Meta only sends the referral on that first
message.

This operation currently returns only the `meta_ad_*` family, which
covers Instagram Click-to-Direct and Facebook Messenger
Click-to-Message. WhatsApp Click-to-WhatsApp attribution (the `ctwa_*`
keys, where the ad ID is `ctwa_source_id`) is returned by
`GET /v1/inbox/conversations` instead.

Every key is optional and only the keys Meta supplied are returned, so
read defensively. Meta does not send a campaign or ad set ID, so none is
exposed here. More keys may be added over time. Treat any key you do not
recognise as an opaque string.

Key names differ from the `message.received` webhook on purpose. The
webhook forwards Meta's referral verbatim (`ad_id`, `source`, `type`)
while the stored conversation record uses the prefixed names below.
Renaming either side would break existing integrations, so both
spellings are kept.


#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

#### 404: Conversation not found

---

## PUT /v1/inbox/conversations/{conversationId}

**Update conversation status**

Archive or activate a conversation. Requires accountId in request body.

### Parameters

- **conversationId** (required) in path: Opaque conversation identifier, accepted verbatim from the list endpoint or from the conversationId on inbox webhooks. Format not to be assumed.

### Request Body

- **accountId** (required) `string`: Account ID
- **status** (required) `string`: No description - one of: active, archived

### Responses

#### 200: Conversation updated

**Response Body:**

- **success** `boolean`: No description
- **data** `object`: 
  - **id** `string`: No description
  - **accountId** `string`: No description
  - **status** `string`: No description - one of: active, archived
  - **platform** `string`: No description
  - **updatedAt** `string` (date-time): No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

#### 404: Conversation not found (WhatsApp only; other platforms upsert)

---

---
