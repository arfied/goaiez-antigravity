# Related Schema Definitions

## CallRecord

One call on a number you own, either channel. `channel` tells you which
lane it took: `whatsapp` (WhatsApp Business Calling) or `pstn` (a regular
phone call). List endpoints omit `transcript`; use `lastTranscriptSnippet`
for a preview and the detail endpoint for the full transcript.


### Properties

- **_id** `string`: No description
- **accountId** `string`: Owning account. The unified /v1/calls/{id} detail + recording endpoints work for any channel; the channel-specific endpoints remain for account-scoped access.
- **conversationId** `string,null`: Inbox conversation with the counterparty, when one exists.
- **contactId** `string,null`: CRM Contact for the counterparty, when resolved.
- **channel** `string`: No description - one of: whatsapp, pstn
- **direction** `string`: No description - one of: inbound, outbound
- **from** `string`: Caller number (E.164).
- **to** `string`: Callee number (E.164).
- **forwardTo** `string,null`: Destination the call was routed to (tel:/sip:/wss:), snapshotted at routing time.
- **greeting** `string,null`: Outbound PSTN only. Message spoken to the callee on answer, before the bridge.
- **status** `string`: No description - one of: ringing, answered, ended, failed
- **isVoicemail** `boolean`: True when an inbound call went to voicemail.
- **amd** `boolean`: Outbound answering-machine detection was requested for this call.
- **answeredMachine** `boolean,null`: With `amd`, whether a machine (vs a human) answered.
- **forwardCallerId** `string`: Caller ID presented on the forwarded leg. - one of: business, caller
- **recordingEnabled** `boolean`: Effective flag for THIS call (number default + per-call override, resolved at create time).
- **transcriptionEnabled** `boolean`: No description
- **transcriptionLanguage** `string`: No description - one of: auto, en, es
- **startedAt** `string`: No description
- **answeredAt** `string,null`: No description
- **endedAt** `string,null`: No description
- **transferredAt** `string,null`: When the call was blind-transferred (POST /v1/voice/calls/{id}/transfer).
- **durationSeconds** `integer`: No description
- **endReason** `string`: No description - one of: hangup, no_answer, rejected, error
- **hangupCause** `string,null`: Raw carrier hangup cause behind endReason (e.g. normal_clearing, not_found, time_limit). The actual motive when endReason is a coarse bucket.
- **sipHangupCause** `string,null`: SIP response code that ended the call, when SIP-signalled (e.g. '403', '488'). The real failure reason for SIP legs.
- **callErrors** `array`: Per-call failure log (dial failed, bridge failed, recording error).
- **recordingUrl** `string,null`: May be expired. Resolve a fresh playable URL via GET /v1/calls/{id}/recording (any channel).
- **lastTranscriptSnippet** `string,null`: Most recent transcript segment, for list previews.
- **transcript** `array`: Full transcript segments (detail endpoint only; omitted from lists).
- **billing** `object`: 
  - **metaMinutes** `number`: 
  - **telnyxSeconds** `number`: 
  - **transcriptionSeconds** `number`: 
  - **transcriptionCostUSD** `number`: 
  - **metaCostUSD** `number`: WhatsApp channel only. Meta per-minute charge, billed by Meta directly to your WABA. Display only; not billed by Zernio.
  - **telnyxCostUSD** `number`: 
  - **recordingCostUSD** `number`: 
  - **billableCostUSD** `number`: Amount Zernio bills you = telephony leg + recording + transcription (excludes any Meta portion).
  - **totalCostUSD** `number`: Full cost incl. any Meta portion you pay directly. Display only.
  - **currency** `string`: 
- **createdAt** `string`: No description
- **updatedAt** `string`: No description

---
