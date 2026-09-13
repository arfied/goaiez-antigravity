# Unblock users API Reference

Unblock one or more previously blocked WhatsApp users on this number.
Up to 1,000 users per request; per-user failures are reported in
`failed` without failing the rest of the batch.


## GET /v1/whatsapp/block-users

**List blocked users**

List the WhatsApp users blocked on this number. Cursor-paginated; pass
`nextCursor` back as `after` to fetch the next page. The blocklist holds
up to 64,000 users.


### Parameters

- **accountId** (required) in query: WhatsApp account ID
- **limit** (optional) in query: Page size.
- **after** (optional) in query: Cursor from a previous response's `nextCursor`.

### Responses

#### 200: Blocked users

**Response Body:**

- **blockedUsers** `array[object]`: 
  - **waId** `string`: WhatsApp user ID (usually the phone number without `+`).
- **nextCursor** `string,null`: Pass as `after` to fetch the next page. Null when there are no more pages.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

## POST /v1/whatsapp/block-users

**Block users**

Block one or more WhatsApp users on this number. Blocked users cannot
message your number or see that you are online, and your sends to them
return an error.

Meta constraints, surfaced per-user in `failed` (the request itself still
succeeds for the rest of the batch):
- Only users who messaged your business within the last 24 hours can be
  blocked (failures outside the window report "Re-engagement required").
- Up to 1,000 users per request; the blocklist caps at 64,000.
- Other WhatsApp Business accounts cannot be blocked.


### Request Body

- **accountId** (required) `string`: WhatsApp account ID
- **users** (required) `array`: Phone numbers (E.164, e.g. "+16505551234") or WhatsApp user IDs to block.

### Responses

#### 200: Per-user results

**Response Body:**

- **blocked** `array[object]`: Users successfully blocked.
  - **input** `string`: The value you sent.
  - **waId** `string`: Resolved WhatsApp user ID.
- **failed** `array[object]`: Users that could not be blocked, with reasons.
  - **input** `string`: No description
  - **errors** `array[string]`: 

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

## DELETE /v1/whatsapp/block-users

**Unblock users**

Unblock one or more previously blocked WhatsApp users on this number.
Up to 1,000 users per request; per-user failures are reported in
`failed` without failing the rest of the batch.


### Request Body

- **accountId** (required) `string`: WhatsApp account ID
- **users** (required) `array`: Phone numbers (E.164) or WhatsApp user IDs to unblock.

### Responses

#### 200: Per-user results

**Response Body:**

- **unblocked** `array[object]`: Users successfully unblocked.
  - **input** `string`: The value you sent.
  - **waId** `string`: Resolved WhatsApp user ID.
- **failed** `array[object]`: Users that could not be unblocked, with reasons.
  - **input** `string`: No description
  - **errors** `array[string]`: 

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
