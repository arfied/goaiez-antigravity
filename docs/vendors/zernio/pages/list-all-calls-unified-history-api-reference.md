# List all calls (unified history) API Reference

Unified call history across ALL of your numbers: both channels
(WhatsApp Business Calling + regular phone/PSTN), inbound and outbound,
newest first. Unlike `GET /v1/voice/calls` (PSTN-only) and
`GET /v1/whatsapp/calls` (one account at a time), this endpoint needs no
`accountId` and never requires fanning out one request per number.

Any row can be opened channel-agnostically via `GET /v1/calls/{id}` and
`GET /v1/calls/{id}/recording`; no branching on `channel` needed. When
the counterparty number matches a CRM contact, `contactId` and
`contactName` are set.

Cursor pagination: pass the returned `nextCursor` as `before` to fetch
the next page. `nextCursor` is null on the last page.


## GET /v1/calls

**List all calls (unified history)**

Unified call history across ALL of your numbers: both channels
(WhatsApp Business Calling + regular phone/PSTN), inbound and outbound,
newest first. Unlike `GET /v1/voice/calls` (PSTN-only) and
`GET /v1/whatsapp/calls` (one account at a time), this endpoint needs no
`accountId` and never requires fanning out one request per number.

Any row can be opened channel-agnostically via `GET /v1/calls/{id}` and
`GET /v1/calls/{id}/recording`; no branching on `channel` needed. When
the counterparty number matches a CRM contact, `contactId` and
`contactName` are set.

Cursor pagination: pass the returned `nextCursor` as `before` to fetch
the next page. `nextCursor` is null on the last page.


### Parameters

- **channel** (optional) in query: No description
- **status** (optional) in query: No description
- **direction** (optional) in query: No description
- **number** (optional) in query: Exact filter: calls involving this number (typically one of YOUR numbers, to scope history to a single line). E.164, leading + optional.
- **search** (optional) in query: Free-text match on the from/to numbers. Non-digits are stripped, so partial queries like `302` or `+1 302` work.
- **before** (optional) in query: Return calls with startedAt strictly before this instant (use the previous page's nextCursor).
- **limit** (optional) in query: No description

### Responses

#### 200: Calls, newest first

**Response Body:**

- **calls** `array[items]`: 
- **nextCursor** `string,null` (date-time): Pass as `before` for the next page; null on the last page.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
