# Setup

Set where a number's inbound calls go and turn on voicemail, business hours, an IVR menu, a caller blocklist, recording and transcription.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page inbound calls to a number reach the destination you chose, with voicemail, business hours, an IVR menu and a blocklist configured as you need them. You need a [number](/platforms/phone-numbers/provisioning) and usage-based billing. Calling is already on when a number is provisioned; everything here is one endpoint, `POST /v1/phone-numbers/{id}/voice`.

## Set the forward destination

Call `POST /v1/phone-numbers/{id}/voice` with `forwardTo`. It accepts 3 kinds of destination:

- `tel:+E164`: a phone number.
- `sip:...`: a SIP endpoint. [Retell](/platforms/voice/integrations/retell) and [ElevenLabs](/platforms/voice/integrations/elevenlabs) agents connect this way.
- `wss://...`: a WebSocket media server, which is how you bridge to an [AI voice agent](/platforms/voice/ai-agents) (Vapi or your own).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const phoneNumberId = '66d4e5f6a7b8c9d0e1f2a3b4';

const { data: voice } = await zernio.voice.enableVoiceOnNumber({
  path: { id: phoneNumberId },
  body: {
    forwardTo: 'tel:+13105551234',
    recordingEnabled: false,
    transcriptionEnabled: false,
  }
});
console.log(voice.enabled, voice.pstnForwardTo);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()
phone_number_id = "66d4e5f6a7b8c9d0e1f2a3b4"

voice = client.voice.enable_voice_on_number(
    id=phone_number_id,
    forward_to="tel:+13105551234",
    recording_enabled=False,
    transcription_enabled=False,
)
print(voice["enabled"], voice["pstnForwardTo"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/66d4e5f6a7b8c9d0e1f2a3b4/voice" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"forwardTo": "tel:+13105551234", "recordingEnabled": false, "transcriptionEnabled": false}'
```
</Tab>
</Tabs>

Response (`200`), the full effective config:

```json
{
  "enabled": true,
  "phoneNumber": "+14155550100",
  "pstnForwardTo": "tel:+13105551234",
  "recordingEnabled": false,
  "transcriptionEnabled": false,
  "transcriptionLanguage": "auto",
  "voicemailEnabled": true,
  "businessHoursEnabled": false,
  "blockedCallers": [],
  "forwardCallerId": "business",
  "ivrEnabled": false
}
```

The endpoint is idempotent and writes only the fields you send, so the same call updates settings later. Omitting `forwardTo` keeps the current destination; an empty string clears it, which leaves the number voice-enabled for outbound only.

## Optional inbound features

Send any of these in the same body:

| Feature | Fields | What it does |
|---------|--------|--------------|
| Voicemail | `voicemailEnabled`, `voicemailGreeting` | Takes a message when the call is not answered. On by default. |
| Business hours | `businessHoursEnabled`, `businessHoursTimezone`, `businessHours` | Forwards only inside the windows; outside them the call goes to voicemail. |
| IVR menu | `ivrEnabled`, `ivrPrompt`, `ivrOptions` | Plays a menu and routes by keypress. |
| Caller blocklist | `blockedCallers` | Rejects calls from listed numbers. Replaces the whole list on every write. |
| Caller ID | `forwardCallerId` | Shows your number (`business`) or the original caller (`caller`) on the forwarded leg. |
| Recording | `recordingEnabled` | Records the call, off by default; a consent prompt plays first; +$0.004 per minute. |
| Transcription | `transcriptionEnabled`, `transcriptionLanguage` | Produces a transcript, off by default; +$0.03 per minute. |

`businessHours` and `ivrOptions` are the two that take a body rather than a value. A window has `day` (0 is Sunday), `open` and `close` as `HH:MM` in `businessHoursTimezone`, at most 21 windows. A menu option has `digit` (`0` to `9`, `*` or `#`), a `forwardTo` destination of its own and an optional `label`, at most 12 options:

```json
{
  "businessHoursEnabled": true,
  "businessHoursTimezone": "Europe/Madrid",
  "businessHours": [
    { "day": 1, "open": "09:00", "close": "18:00" },
    { "day": 2, "open": "09:00", "close": "18:00" },
    { "day": 6, "open": "10:00", "close": "14:00" }
  ],
  "ivrEnabled": true,
  "ivrPrompt": "Press 1 for sales, 2 for support, 3 to leave a message.",
  "ivrOptions": [
    { "digit": "1", "forwardTo": "tel:+13105551234", "label": "Sales" },
    { "digit": "2", "forwardTo": "sip:support@pbx.example.com", "label": "Support" },
    { "digit": "3", "forwardTo": "wss://agent.example.com/media", "label": "AI agent" }
  ]
}
```

A day with no window is closed all day, and a window whose `close` is earlier than its `open` runs past midnight into the next day. [Enable voice on a number](/voice/enable-voice-on-number) lists each field's exact shape.

## How the features interact

An inbound call walks through the features in a fixed order:

<Mermaid
  chart={`flowchart LR
  A["inbound call"] --> B{"caller on blocklist?"}
  B -->|"yes"| X["rejected, busy signal"]
  B -->|"no"| H{"within business hours?"}
  H -->|"no"| V["voicemail"]
  H -->|"yes"| M{"IVR menu on?"}
  M -->|"yes"| I["menu plays, keypress picks a destination"]
  M -->|"no"| F["ring forwardTo"]
  I --> F
  F -->|"answered"| L["live call"]
  F -->|"no answer or failure"| V`}
/>

- Blocklisted callers get a busy signal. Zernio rejects the call before answering it: no charge, no voicemail and no webhook.
- Business hours gate everything live. Outside the windows the call skips the menu and the forward and goes to voicemail. With voicemail off too, the call hangs up.
- The IVR menu replaces the plain forward. When a menu with options is enabled, the caller hears it instead of `forwardTo` ringing, and the pressed digit picks that option's destination. No input or an invalid digit ends the call.
- Voicemail catches every non-answer: unanswered forwards (about 25 seconds of ringing), failed bridges and after-hours calls. The greeting (yours, or a default) plays, a beep follows, and Zernio records up to 2 minutes. You get an email, and the recording and transcript (when transcription is on) land on the [call record](/platforms/voice/history). There is no separate voicemail webhook.
- Recording never starts silently. On bridged calls a consent notice plays first. The recording lands on the call record shortly after hangup, not on the [`call.ended` payload](/webhooks/calls#callended).
- [`call.received`](/webhooks/calls#callreceived) fires as the call enters the pipeline, after the blocklist check and before any menu or ring; every answered path ends with [`call.ended`](/webhooks/calls#callended).

## Turn voice off

Call `DELETE /v1/phone-numbers/{id}/voice`. Zernio keeps the forward destination and settings, so re-enabling restores them.

```bash
curl -X DELETE "https://zernio.com/api/v1/phone-numbers/66d4e5f6a7b8c9d0e1f2a3b4/voice" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "enabled": false,
  "phoneNumber": "+14155550100"
}
```

## If it fails

A `409` with `code: "invalid_resource_state"` means the number is attached to a [SIP trunk](/platforms/voice/integrations/elevenlabs#sip-trunk), which owns its calls while attached:

```json
{
  "error": "This number is attached to a SIP trunk; detach it first.",
  "type": "invalid_request_error",
  "code": "invalid_resource_state"
}
```

Detach it with `DELETE /v1/phone-numbers/{id}/sip-trunk`, then retry. A `422` means the number lives on your own carrier (brought in through WhatsApp embedded signup), so Zernio cannot route its calls. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [AI voice agents](/platforms/voice/ai-agents): a `wss://` destination.
- [Outbound calls](/platforms/voice/outbound): dial from the number.
- [Browser calling](/platforms/voice/browser-calling): the WebRTC softphone.
- [Call webhooks](/webhooks/calls): which events fire, in what order.
- [Call rates](/pricing/calls): per-minute prices by destination.

---
