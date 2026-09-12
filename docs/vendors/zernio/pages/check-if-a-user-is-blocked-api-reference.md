# Check if a user is blocked API Reference

Definitive blocked-state lookup for a single contact. Meta exposes no
membership endpoint, so this reads Zernio's blocklist mirror (kept in
sync by the block/unblock endpoints; the first call per account
backfills the mirror from Meta's full list). Constant-time regardless
of blocklist size.


## GET /v1/whatsapp/block-users/status

**Check if a user is blocked**

Definitive blocked-state lookup for a single contact. Meta exposes no
membership endpoint, so this reads Zernio's blocklist mirror (kept in
sync by the block/unblock endpoints; the first call per account
backfills the mirror from Meta's full list). Constant-time regardless
of blocklist size.


### Parameters

- **accountId** (required) in query: No description
- **user** (required) in query: Consumer wa_id or E.164 phone (leading + optional)

### Responses

#### 200: Blocked state

**Response Body:**

- **blocked** `boolean`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
