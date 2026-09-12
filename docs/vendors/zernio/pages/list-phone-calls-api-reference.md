# List phone calls API Reference

Your PSTN voice calls (inbound + outbound), newest first. Cursor
pagination: pass the returned `nextCursor` as `before` for the next
page. For a history that also includes WhatsApp calls, use
`GET /v1/calls`.


## POST /v1/voice/calls

**Place an outbound phone call**

Dials `to` FROM one of your voice-enabled numbers and, on answer,
bridges the callee to the number's stored forward destination, or to
the per-call `forwardTo` override. Destinations can be your own AI
voice agent (Vapi/Retell), a phone, or a SIP endpoint. An optional
`greeting` is spoken to the callee before the bridge.

The 200 response means the call is dialing; the lifecycle continues
asynchronously (track it via `GET /v1/voice/calls/{id}` or the `call.*`
webhooks). Outbound calls are capped per rolling hour (429 when hit).

**Idempotency:** send an `Idempotency-Key` header to make retries safe;
same key + same body replays the original response instead of dialing
(and billing) a second call.


### Parameters

- **undefined** (optional): No description

### Request Body

- **to** (required) `string`: Destination to dial, E.164 with leading +.
- **fromNumber** `string`: Which of your voice-enabled numbers to dial from. Optional when you have exactly one.
- **forwardTo** `string`: Per-call agent override (tel:+E164, sip:..., or wss://...); defaults to the number's stored forward destination.
- **greeting** `string`: Spoken to the callee when they answer, before the bridge.
- **recordOverride** `boolean`: Per-call recording toggle; defaults to the number's setting.
- **transcribeOverride** `boolean`: Per-call transcription toggle; defaults to the number's setting.
- **transcriptionLanguage** `string`: 'auto' derives from the callee's country; 'en'/'es' force it. - one of: auto, en, es
- **amd** `boolean`: Answering-machine detection; defers the bridge until human vs machine is known.
- **voicemailDropMessage** `string`: Spoken to a detected machine, then hang up (implies `amd`). For outbound voicemail drops.

### Responses

#### 200: Call originated; lifecycle continues asynchronously.

**Response Body:**

- **success** `boolean`: No description
- **callId** `string`: Internal Call doc ID
- **telnyxCallControlId** `string`: No description
- **status** `string`: No description - one of: dialing
- **direction** `string`: No description - one of: outbound
- **from** `string`: No description
- **to** `string`: No description
- **forwardTo** `string`: No description
- **greeting** `string,null`: No description
- **recordingEnabled** `boolean`: No description
- **transcriptionEnabled** `boolean`: No description
- **transcriptionLanguage** `string`: No description - one of: auto, en, es

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 422: No voice-enabled number matches `fromNumber`, or no forward destination configured (set the number's forward or pass `forwardTo`).

#### 429: Outbound call limit reached (per rolling hour).

#### 502: Carrier-side originate failed; the call has been marked failed.

---

## GET /v1/voice/calls

**List phone calls**

Your PSTN voice calls (inbound + outbound), newest first. Cursor
pagination: pass the returned `nextCursor` as `before` for the next
page. For a history that also includes WhatsApp calls, use
`GET /v1/calls`.


### Parameters

- **status** (optional) in query: No description
- **direction** (optional) in query: No description
- **number** (optional) in query: Exact filter: calls involving this number (typically one of your DIDs). E.164, leading + optional.
- **before** (optional) in query: No description
- **limit** (optional) in query: No description

### Responses

#### 200: Calls, newest first

**Response Body:**

- **calls** `array[CallRecord]`: 
- **nextCursor** `string,null` (date-time): No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---
