# List broadcast recipients API Reference

Returns recipients for a broadcast with individual delivery status. Filter by status.

## GET /v1/broadcasts/{broadcastId}/recipients

**List broadcast recipients**

Returns recipients for a broadcast with individual delivery status. Filter by status.

### Parameters

- **broadcastId** (required) in path: No description
- **status** (optional) in query: No description
- **limit** (optional) in query: No description
- **skip** (optional) in query: No description

### Responses

#### 200: Recipients list with delivery status

**Response Body:**

- **success** `boolean`: No description
- **recipients** `array[object]`: 
  - **id** `string`: No description
  - **contactId** `string`: No description
  - **channelId** `string`: No description
  - **platformIdentifier** `string`: No description
  - **contactName** `string,null`: No description
  - **status** `string`: No description - one of: pending, sent, delivered, read, failed
  - **messageId** `string`: No description
  - **error** `string`: No description
  - **errorCode** `integer,null`: Meta WhatsApp error code (e.g. 131049 for antispam, 131021 for invalid phone, 131026 for re-engagement required). Only populated for status=failed.
  - **errorExplanation** `string,null`: Plain-language translation of errorCode (e.g. for 131026, that the recipient has likely opted out of marketing messages). Null for unmapped codes; fall back to error.
  - **errorTraceId** `string,null`: Meta trace id (fbtrace_id) for the failed send. Quote this when escalating to Meta Direct Support. Only populated for status=failed on Meta platforms.
  - **sentAt** `string` (date-time): No description
  - **deliveredAt** `string` (date-time): No description
  - **readAt** `string` (date-time): No description
- **pagination** `object`: 
  - **total** `integer`: No description
  - **limit** `integer`: No description
  - **skip** `integer`: No description
  - **hasMore** `boolean`: No description
- **summary** `object`: Delivery totals across all recipients in the broadcast, independent of pagination and status filtering.
  - **total** `integer`: No description
  - **pending** `integer`: No description
  - **sent** `integer`: No description
  - **delivered** `integer`: No description
  - **read** `integer`: No description
  - **failed** `integer`: No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## POST /v1/broadcasts/{broadcastId}/recipients

**Add recipients to a broadcast**

Add recipients by contact IDs, raw phone numbers, or from the broadcast's segment filters.

### Parameters

- **broadcastId** (required) in path: No description

### Request Body

- **contactIds** `array`: Specific contact IDs to add. Zernio contact ids (24-character hex), as returned by the list-contacts endpoint. A platform identifier such as a WhatsApp wa_id is rejected with 400; use phones for raw numbers.
- **phones** `array`: Raw phone numbers (auto-creates contacts). Useful for WhatsApp/Telegram manual entry
- **useSegment** `boolean`: Auto-populate from broadcast segment filters

### Responses

#### 200: Recipients added

**Response Body:**

- **success** `boolean`: No description
- **added** `integer`: Number of recipients successfully added
- **skipped** `integer`: Number skipped (duplicates or missing channels)

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
