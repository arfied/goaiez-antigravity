# Initiate outbound call API Reference

Initiates an outbound Business-Initiated Call. The Telnyx-side SIP
leg is originated server-side (Option B: SIP-first). Telnyx INVITEs
Meta directly over TLS:5061 with the SIP digest credentials we
captured at calling-enablement time). No client-side SDP is
required; pass only `accountId` and `to`.

To send the consumer the call-consent prompt instead of placing a
call, pass `action: "send_call_permission_request"` (+ optional
`bodyText`). The consumer must tap Allow in WhatsApp before
`start_call` is permitted; Meta limits the prompt to 1 per consumer
per 24h (2 per 7 days) and requires an open 24h service window.

**Idempotency:** send an `Idempotency-Key` header to make retries
safe; same key + same body replays the original response instead of
dialing (and billing) a second call.


## POST /v1/whatsapp/calls

**Initiate outbound call**

Initiates an outbound Business-Initiated Call. The Telnyx-side SIP
leg is originated server-side (Option B: SIP-first). Telnyx INVITEs
Meta directly over TLS:5061 with the SIP digest credentials we
captured at calling-enablement time). No client-side SDP is
required; pass only `accountId` and `to`.

To send the consumer the call-consent prompt instead of placing a
call, pass `action: "send_call_permission_request"` (+ optional
`bodyText`). The consumer must tap Allow in WhatsApp before
`start_call` is permitted; Meta limits the prompt to 1 per consumer
per 24h (2 per 7 days) and requires an open 24h service window.

**Idempotency:** send an `Idempotency-Key` header to make retries
safe; same key + same body replays the original response instead of
dialing (and billing) a second call.


### Parameters

- **undefined** (optional): No description

### Request Body

- **accountId** (required) `string`: No description
- **to** (required) `string`: Consumer wa_id (E.164, leading + optional)
- **action** `string`: Omit to place a call. Set to send the consent prompt instead. - one of: send_call_permission_request
- **bodyText** `string`: Body text shown with the consent prompt (send_call_permission_request only).
- **forwardTo** `string`: Per-call destination override. Same accepted shape as the
number's stored forwardTo (tel:+E164, sip:..., wss://...).

- **recordOverride** `boolean`: No description
- **biz_opaque_callback_data** `string`: Accepted for forward compatibility. Not currently echoed
back in webhook payloads (SIP-first flow does not pass
through Meta's Graph API where Meta would echo this).


### Responses

#### 200: Call originated; lifecycle continues asynchronously via webhooks.

**Response Body:**

- **success** `boolean`: No description
- **callId** `string`: Internal Call doc ID
- **telnyxCallControlId** `string`: Telnyx call_control_id of the outbound leg
- **status** `string`: No description - one of: dialing
- **direction** `string`: No description - one of: outbound
- **to** `string`: No description
- **forwardTo** `string,null`: No description
- **recordingEnabled** `boolean`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 409: No active call permission. Send a permission request first.

#### 422: Calling not enabled, BIC country blocked, or missing Meta SIP credentials

#### 502: Telnyx-side originate failed; the Call doc has been marked failed.

---

## GET /v1/whatsapp/calls

**List call history for an account**

Compact history listing for a single connected account. Results are
scoped to the resolved SocialAccount; profile-scoped team members
cannot read calls on sibling accounts.

Cursor pagination: pass the returned `nextCursor` as `before` to fetch
the next page (same scheme as `GET /v1/calls`). `since`/`until` remain
as absolute range filters and combine with the cursor.


### Parameters

- **accountId** (required) in query: No description
- **status** (optional) in query: No description
- **direction** (optional) in query: No description
- **since** (optional) in query: No description
- **until** (optional) in query: No description
- **before** (optional) in query: Return calls with startedAt strictly before this instant (use the previous page's nextCursor).
- **limit** (optional) in query: No description

### Responses

#### 200: Calls

**Response Body:**

- **calls** `array[object]`: 
  - **_id** `string`: No description
  - **direction** `string`: No description - one of: inbound, outbound
  - **from** `string`: No description
  - **to** `string`: No description
  - **status** `string`: No description - one of: ringing, answered, ended, failed
  - **startedAt** `string` (date-time): No description
  - **endedAt** `string` (date-time): No description
  - **durationSeconds** `integer`: No description
  - **endReason** `string`: No description - one of: hangup, no_answer, rejected, error
  - **recordingUrl** `string`: No description
  - **billing** `object`: 
    - **metaCostUSD** `number`: Meta per-minute charge, billed by Meta directly to your WABA. Display only; not billed by Zernio.
    - **telnyxCostUSD** `number`: No description
    - **recordingCostUSD** `number`: No description
    - **billableCostUSD** `number`: Amount Zernio bills you = Telnyx leg + recording (excludes Meta).
    - **totalCostUSD** `number`: Full cost incl. the Meta portion you pay directly. Display only.
    - **currency** `string`: No description
- **nextCursor** `string,null` (date-time): Pass as `before` for the next page; null on the last page.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
