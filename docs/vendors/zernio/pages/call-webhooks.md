# Call webhooks

Receive an event when a call rings, ends or fails on a phone or WhatsApp number, and when a WhatsApp user answers a call-permission request.

Call events cover every call on your numbers, regular phone (PSTN) and WhatsApp, from ring to hangup. Subscribe with `POST /v1/webhooks/settings` and the event names below ([first event](/webhooks#first-event)). Call events need inbox access: without it that call returns a `403` with code `feature_not_available`, so the subscription is refused rather than silently empty. Delivery, retries and signatures are the same for every event ([how webhooks behave](/webhooks#how-it-behaves)).

## Events

| Event | Description |
| --- | --- |
| [`call.received`](#callreceived) | A call was set up: an inbound call (phone or WhatsApp) reaching one of your numbers, or an outbound WhatsApp call placed through the API. |
| [`call.ended`](#callended) | A call (phone or WhatsApp) ended; carries duration, end reason and the cost breakdown. |
| [`call.failed`](#callfailed) | A call (phone or WhatsApp) failed with a hard error before or during bridging. |
| [`call.permission_request`](#callpermission_request) | A WhatsApp user accepted or rejected your call-permission request. |

## How it behaves

### Which events fire, in what order

Zernio ends every call with exactly one of `call.ended` or `call.failed`. `call.permission_request` sits outside these sequences: it reports a reply to a permission prompt, not a call. Whether a `call.received` precedes the ending event depends on the direction and channel:

| Call | Sequence |
| --- | --- |
| Inbound, phone or WhatsApp | `call.received` at ring time, then `call.ended` or `call.failed` |
| Outbound WhatsApp (through the API) | `call.received` with `direction: "outbound"` at origination, then `call.ended` or `call.failed` |
| Outbound phone (API or browser softphone) | `call.ended` or `call.failed` only |

### `call.received` fires at ring time

Zernio sends `call.received` before anyone answers. A call nobody picks up still emits it, followed by `call.ended` with `endReason: "no_answer"`.

### Unanswered calls end with `call.ended`

Zernio reports no-answer, busy and rejected calls with `call.ended`; branch on `endReason`. `call.failed` is reserved for hard errors before or during bridging.

### Permission requests are independent of any call

Zernio sends `call.permission_request` when a WhatsApp user responds to a [call-permission request](/platforms/whatsapp/calling). It gates the outbound WhatsApp row above: Meta permits a business-initiated call only after the consumer taps Allow, and the accept can land days before the call.

---

## `call.received`

A call was set up on one of your numbers: an inbound call, phone (PSTN) or WhatsApp, reaching the number and routed to its configured destination (an AI voice agent, a SIP endpoint or a phone number), or an outbound WhatsApp call placed through the API, in which case `direction` is `"outbound"`. The payload carries the Zernio call id, the caller and business numbers, the destination snapshot (`forwardTo`), and `contactId` and `conversationId` links into the inbox, so you can message the caller during or after the call with the regular send endpoints.

<br />

**Payload for `call.received`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: call.received
- **call** (required) `object`: 
  - **id** `string`: Internal Zernio Call doc id
  - **metaCallId** `string,null`: Meta wacid.* call id when known
  - **accountId** `string`: No description
  - **phoneNumberId** `string`: Meta phone_number_id
  - **direction** `string`: No description - one of: inbound, outbound
  - **from** `string`: Consumer wa_id / E.164
  - **to** `string`: Business number (E.164)
  - **forwardTo** `string`: Destination snapshot at routing time
  - **contactId** `string`: No description
  - **conversationId** `string`: No description
  - **startedAt** `string` (date-time): No description
- **account** (required): `InboxWebhookAccount` - See schema definition
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `call.ended`

A call (phone or WhatsApp) ended. The payload carries `durationSeconds`, the `endReason` (`hangup`, `no_answer`, `rejected`, `error`) and a cost breakdown under `call.billing`. When recording is enabled it also carries `recordingUrl`, a signed URL that expires at `recordingExpiresAt`; for a fresh URL later, call [`GET /v1/calls/{id}/recording`](/platforms/voice/history#fetch-a-call-and-its-recording) with `call.id`. A missed-call follow-up is one `endReason: "no_answer"` check, then a text to the caller over the linked `conversationId`.

<br />

**Payload for `call.ended`:**

- **id** (required) `string`: No description
- **event** (required) `string`: No description - one of: call.ended
- **call** (required) `object`: 
  - **id** `string`: No description
  - **metaCallId** `string,null`: No description
  - **accountId** `string`: No description
  - **phoneNumberId** `string`: No description
  - **direction** `string`: No description - one of: inbound, outbound
  - **from** `string`: No description
  - **to** `string`: No description
  - **startedAt** `string` (date-time): No description
  - **endedAt** `string` (date-time): No description
  - **durationSeconds** `integer`: No description
  - **endReason** `string`: No description - one of: hangup, no_answer, rejected, error
  - **hangupCause** `string,null`: Raw carrier hangup cause behind endReason (e.g. normal_clearing, call_rejected, not_found). Null when the carrier reported none.
  - **sipHangupCause** `string,null`: SIP response code that ended the call when SIP-signalled (e.g. '403', '486', '603'). endReason collapses all three to 'rejected', so this is what separates a refused destination from a busy line. Null on non-SIP legs.
  - **isVoicemail** `boolean`: True when the inbound call was handled by voicemail, whether scheduled or because the forward did not connect.
  - **callErrors** `array[object]`: Failures recorded on the call up to hangup (bridge failed, dial failed, recording error). Empty on a clean call. `message` is free-form diagnostic text and is not stable, do not parse it. `code` is 0 unless a provider code is known. Errors the carrier reports after hangup appear only on GET /v1/calls/{id}.
    - **code** `integer`: No description
    - **message** `string`: No description
  - **recordingUrl** `string`: No description
  - **recordingExpiresAt** `string` (date-time): No description
  - **billing** `object`: 
    - **metaCostUSD** `number`: Meta per-minute charge. Billed by Meta DIRECTLY to your WhatsApp Business Account payment method (your separate Meta invoice). Zernio does NOT charge this. Display only.
    - **telnyxCostUSD** `number`: No description
    - **recordingCostUSD** `number`: No description
    - **billableCostUSD** `number`: The amount Zernio bills you = Telnyx leg + recording. Excludes Meta (billed by Meta directly).
    - **totalCostUSD** `number`: Full economic cost incl. the Meta portion you pay directly (Meta + Telnyx + recording). Display only, not the Zernio-billed amount.
- **account** (required): `InboxWebhookAccount` - See schema definition
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `call.failed`

A call (phone or WhatsApp) failed with a hard error before or during bridging, for example the destination rejected the SIP INVITE. The payload carries the platform error `code` and `message`.

<br />

**Payload for `call.failed`:**

- **id** (required) `string`: No description
- **event** (required) `string`: No description - one of: call.failed
- **call** (required) `object`: 
  - **id** `string`: No description
  - **metaCallId** `string,null`: No description
  - **accountId** `string`: No description
  - **phoneNumberId** `string`: No description
  - **direction** `string`: No description - one of: inbound, outbound
  - **from** `string`: No description
  - **to** `string`: No description
  - **failedAt** `string` (date-time): No description
  - **error** `object`: 
    - **code** `integer`: No description
    - **message** `string`: No description
- **account** (required): `InboxWebhookAccount` - See schema definition
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `call.permission_request`

A WhatsApp user responded to your call-permission request, which business-initiated calls require. The payload carries the user's number, their `response` (`accept` or `reject`), whether it is permanent, and the expiry when it is not.

<br />

**Payload for `call.permission_request`:**

- **id** (required) `string`: No description
- **event** (required) `string`: No description - one of: call.permission_request
- **permission** (required) `object`: 
  - **from** `string`: Consumer wa_id who replied
  - **response** `string`: No description - one of: accept, reject
  - **isPermanent** `boolean`: No description
  - **expirationTimestamp** `string` (date-time): Present only when temporary
  - **responseSource** `string`: Meta's response source, typically `user_action`
- **account** (required): `InboxWebhookAccount` - See schema definition
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

## Related

- [Webhooks](/webhooks): create an endpoint, retries, signatures.
- [Voice history](/platforms/voice/history): calls and recordings on demand.
- [WhatsApp calling](/platforms/whatsapp/calling): call permissions and business-initiated calls.
- [Phone numbers](/platforms/phone-numbers): provision the numbers these calls land on.

---
