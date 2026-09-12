# AI Voice Agents

Answer a number's calls with an AI voice agent: a Vapi agent over a sip: address, or your own media server over wss://.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page inbound calls to a number are answered by an AI voice agent. You need a [voice-enabled number](/platforms/voice/setup) and an agent to hand the calls to. An agent is a forward destination like any other, in one of 2 shapes: a hosted platform such as [Vapi](#vapi) takes a `sip:` address, and a [server of your own](#custom-agents) takes a `wss://` URL that the call's live, bidirectional audio streams over.

Retell and ElevenLabs are `sip:` destinations with guides of their own: [Retell](/platforms/voice/integrations/retell) and [ElevenLabs](/platforms/voice/integrations/elevenlabs).

## Step 1: Point the number at the agent

Call `POST /v1/phone-numbers/{id}/voice` with `forwardTo` set to the agent's address. This sample uses a `wss://` media server of your own; a `sip:` address goes in the same field.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const phoneNumberId = '66d4e5f6a7b8c9d0e1f2a3b4';

const { data: voice } = await zernio.voice.enableVoiceOnNumber({
  path: { id: phoneNumberId },
  body: { forwardTo: 'wss://your-agent.example.com/media' }
});
console.log(voice.pstnForwardTo);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()
phone_number_id = "66d4e5f6a7b8c9d0e1f2a3b4"

voice = client.voice.enable_voice_on_number(
    id=phone_number_id,
    forward_to="wss://your-agent.example.com/media",
)
print(voice["pstnForwardTo"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/66d4e5f6a7b8c9d0e1f2a3b4/voice" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"forwardTo": "wss://your-agent.example.com/media"}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "enabled": true,
  "phoneNumber": "+14155550100",
  "pstnForwardTo": "wss://your-agent.example.com/media",
  "recordingEnabled": false,
  "transcriptionEnabled": false,
  "voicemailEnabled": true
}
```

## How the bridge works

<Mermaid
  chart={`flowchart LR
  C["caller"] <--> Z["Zernio answers the call and owns the carrier leg"]
  Z <--> A["your agent over wss:// or sip:, audio in both directions"]`}
/>

1. Your agent exposes a WebSocket endpoint that streams call audio in both directions, or a SIP address that accepts the call.
2. On an inbound call, Zernio answers and bridges the audio to that destination; the agent speaks back over the same connection. To the caller it is one continuous call.
3. There is no second phone leg either way, so you pay only the [inbound leg](/pricing/calls#inbound-calls).

If the stream cannot be established (a bad URL, a failed handshake), the already-answered caller falls to [voicemail](/platforms/voice/setup#how-the-features-interact) when it is enabled, and the call ends otherwise.

The same `wss://` destination works as a per-call `forwardTo` override on [outbound calls](/platforms/voice/outbound), so you can hand one outbound call to an agent without changing the number's default. Turn on [recording or transcription](/platforms/voice/setup#optional-inbound-features) on the number when you want Zernio to capture the conversation as well.

## Vapi

A Vapi assistant answers over SIP. Create the assistant's SIP username in Vapi, then point the number at `sip:{username}@sip.vapi.ai`. Zernio authenticates with SIP digest, so nothing else is needed on your side.

```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/66d4e5f6a7b8c9d0e1f2a3b4/voice" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"forwardTo": "sip:agent-acme@sip.vapi.ai"}'
```

Response (`200`):

```json
{
  "enabled": true,
  "phoneNumber": "+14155550100",
  "pstnForwardTo": "sip:agent-acme@sip.vapi.ai",
  "voicemailEnabled": true
}
```

The prompt, the voice and the tools stay on Vapi's side; Zernio owns the carrier leg and bridges the audio. A `sip:` agent bills the same way a `wss://` one does: there is no PSTN leg on the far side, so you pay only for the inbound call.

## Custom agents

Point `forwardTo` at your own `wss://` endpoint to run your own speech-to-text, LLM and text-to-speech pipeline. Zernio does not proxy or re-encode the audio: it starts the stream on the carrier leg, and Telnyx, the carrier behind Zernio voice, opens the WebSocket to your server. Your side implements [Telnyx's media streaming over WebSockets](https://developers.telnyx.com/docs/voice/programmable-voice/media-streaming), which defines the start, media, stop and error events and carries the audio as base64 RTP payloads. Zernio fixes 3 of that protocol's settings:

| Setting | Value |
|---|---|
| `stream_track` | `inbound_track`, so you receive the caller's audio |
| `stream_bidirectional_mode` | `rtp`, so media frames you write back on the same socket reach the caller |
| `stream_bidirectional_codec` | `PCMU`, G.711 u-law at 8 kHz, in both directions |

They are not configurable per number today, so build the agent against PCMU at 8 kHz. This is the same bidirectional media bridge that [WhatsApp Calling](/platforms/whatsapp/calling) uses, so an agent you build once serves both channels.

## If it fails

A `409` with `code: "invalid_resource_state"` on the voice call means the number is attached to a SIP trunk; detach it with `DELETE /v1/phone-numbers/{id}/sip-trunk` first. A call that reaches voicemail or ends right after answering usually means the handshake failed: check the `wss://` URL and that the server accepts the connection, then read `callErrors` on the [call record](/platforms/voice/history). Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Setup](/platforms/voice/setup): voicemail, business hours, IVR and the blocklist around the agent.
- [ElevenLabs](/platforms/voice/integrations/elevenlabs) and [Retell](/platforms/voice/integrations/retell): SIP-based agents.
- [Outbound calls](/platforms/voice/outbound): hand an outbound call to the agent.
- [Enable voice on a number](/voice/enable-voice-on-number): every field of the request.

---
