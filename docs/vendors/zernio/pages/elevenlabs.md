# ElevenLabs

Put an ElevenLabs Conversational AI agent on a Zernio number by forwarding calls to its SIP address, or import the number into ElevenLabs over a SIP trunk.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';
import { Steps, Step } from 'fumadocs-ui/components/steps';

When you finish this page an [ElevenLabs Conversational AI](https://elevenlabs.io/conversational-ai) agent answers calls to your Zernio number. You need a [number](/platforms/phone-numbers/provisioning), an ElevenLabs agent and usage-based billing. The number, its countries and KYC, SMS and WhatsApp stay with Zernio on both lanes; what changes is who handles the calls.

| | Forwarding | SIP trunk |
|---|---|---|
| Inbound calls reach the agent | Yes, bridged through Zernio | Yes, delivered directly to ElevenLabs |
| Agent-initiated outbound calls | No | Yes, with your number as caller ID |
| Zernio voice features (IVR, voicemail, recording) | Available | Paused while the number is on the trunk |
| Works with WhatsApp Business Calling | Yes | Not yet |

<Mermaid
  chart={`flowchart LR
  C["caller"] --> Z["Zernio carrier leg"]
  Z -->|"forwarding: bridged SIP leg"| E["ElevenLabs agent"]
  Z -.->|"trunk: delivered directly, both directions"| E`}
/>

## Forward inbound calls

Set the number's forward destination to your agent's ElevenLabs SIP address. Zernio answers the carrier leg and bridges the audio to ElevenLabs over TLS with SRTP media, ElevenLabs' recommended production configuration, applied automatically.

<Steps>
<Step>
### Step 1: Import the number in ElevenLabs

In your ElevenLabs workspace, add the phone number through SIP trunking, in E.164 with the leading `+` (for example `+34911234567`). The identifier you import must match the one in the SIP URI below byte for byte: a missing `+` on either side makes ElevenLabs fail to route the call without an error.
</Step>
<Step>
### Step 2: Point the number at your agent

Call `POST /v1/phone-numbers/{id}/voice` with `forwardTo` set to `sip:{number}@sip.rtc.elevenlabs.io:5061;transport=tls`, using the number's E.164 as the identifier.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const phoneNumberId = '66d4e5f6a7b8c9d0e1f2a3b4';

const { data: voice } = await zernio.voice.enableVoiceOnNumber({
  path: { id: phoneNumberId },
  body: { forwardTo: 'sip:+34911234567@sip.rtc.elevenlabs.io:5061;transport=tls' }
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
    forward_to="sip:+34911234567@sip.rtc.elevenlabs.io:5061;transport=tls",
)
print(voice["pstnForwardTo"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/66d4e5f6a7b8c9d0e1f2a3b4/voice" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"forwardTo": "sip:+34911234567@sip.rtc.elevenlabs.io:5061;transport=tls"}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "enabled": true,
  "phoneNumber": "+34911234567",
  "pstnForwardTo": "sip:+34911234567@sip.rtc.elevenlabs.io:5061;transport=tls",
  "voicemailEnabled": true
}
```

The dashboard's forward picker has an ElevenLabs Conversational AI preset that builds this URI for you.
</Step>
</Steps>

Forwarding also works on a [WhatsApp Business Calling](/platforms/whatsapp/calling) number: WhatsApp voice calls route to the ElevenLabs agent while WhatsApp messaging stays where it is. This is the voice-only integration ElevenLabs documents for numbers whose messaging lives with another provider.

## SIP trunk

A SIP trunk takes Zernio out of the call path: ElevenLabs receives the number's inbound calls directly, and the agent can place outbound calls through Zernio presenting the number as caller ID. Calls bill per minute at the route's carrier rate, like any [voice usage](/pricing/calls).

Everything below is also in the dashboard on the SIP page of the Numbers section: create the trunk (the credentials show there once), then attach or detach numbers.

<Callout type="warn">
While a number is attached to a trunk, Zernio-side voice features (call forwarding, IVR, voicemail, recording, the softphone) and WhatsApp calling are paused for that number; SMS and WhatsApp messaging are unaffected. Turn off Calls and WhatsApp calling on the number before attaching it, or the attach returns `409`.
</Callout>

<Steps>
<Step>
### Step 1: Create the trunk

Call `POST /v1/phone-numbers/sip-trunks` with `label` and `sipHost`. Attach as many numbers as you like. Several trunks can point at the same host; each carries its own credentials and spend cap, which keeps separate ElevenLabs workspaces (an agency's clients, prod and staging) isolated.

```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/sip-trunks" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"label": "ElevenLabs", "sipHost": "sip.rtc.elevenlabs.io"}'
```

Response (`201`):

```json
{
  "id": "66f6a7b8c9d0e1f2a3b4c5d6",
  "label": "ElevenLabs",
  "sipHost": "sip.rtc.elevenlabs.io",
  "sipPort": 5061,
  "transport": "tls",
  "termination": {
    "uri": "sip.telnyx.com",
    "username": "zernio66f6a7b8c9d0e1f2"
  },
  "numbersAttached": 0,
  "digestPassword": "rK9v...Qw2"
}
```

`digestPassword` appears only in this response. Store it now; `POST /v1/phone-numbers/sip-trunks/{id}/rotate-credentials` mints a new one later and invalidates the old password immediately.
</Step>
<Step>
### Step 2: Configure ElevenLabs

In ElevenLabs, import the number through SIP trunking with outbound pointed at `termination.uri` and the digest `username` and `digestPassword` from Step 1. Use the number in E.164 with the leading `+`; the trunk enforces `+E.164` in both directions, so the formats always match.
</Step>
<Step>
### Step 3: Attach the number

Call `POST /v1/phone-numbers/{id}/sip-trunk` with `trunkId`.

```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/66d4e5f6a7b8c9d0e1f2a3b4/sip-trunk" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"trunkId": "66f6a7b8c9d0e1f2a3b4c5d6"}'
```

Response (`200`):

```json
{
  "attached": true,
  "phoneNumber": "+34911234567",
  "trunkId": "66f6a7b8c9d0e1f2a3b4c5d6"
}
```

From this moment the number's inbound calls are delivered to `sip.rtc.elevenlabs.io` and ElevenLabs can originate outbound calls through the trunk. Detach any time with `DELETE /v1/phone-numbers/{id}/sip-trunk`; Zernio routing and voice features come back immediately.
</Step>
</Steps>

## Technical FAQ

Answers to what ElevenLabs' integration checklist asks about.

**Codecs.** The SIP leg is a carrier trunk: G.711 (PCMU/PCMA) is native, and any transcoding happens in the carrier core, never on your side. No SBC is required to import a number.

**Transport security.** TLS is the default signaling transport (the ElevenLabs preset pins `:5061;transport=tls`), with TLS 1.2+ on the carrier's SIP edge. Certificate handling for follow-up requests (BYE, REFER) happens at the carrier edge too; nothing runs on your infrastructure.

**Media encryption.** SRTP is paired with TLS automatically, so calls to ElevenLabs run their recommended "TLS + Required media encryption" configuration by default.

**Number matching.** Zernio dials forward URIs verbatim (the `+` is preserved), and trunk connections pin `+E.164` number formats in both directions, so the number-format mismatch ElevenLabs warns about cannot occur.

**Authentication.** Trunks use SIP digest credentials, generated by Zernio, shown once and rotatable. Each trunk has its own credentials, and no IP allowlisting is required on either side.

**Caller ID.** Outbound calls can only present numbers hosted on the team, or verified onto it through the caller-ID verification flow (a one-time code to the number being claimed). Arbitrary `From` numbers are rejected at the carrier, so calls through a trunk cannot spoof numbers the team does not own.

**Limits.** Each trunk carries protective caps: 10 concurrent calls, a daily spend ceiling, a per-minute destination-rate cap, and a destination country whitelist matching where Zernio sells numbers. Contact support to raise them.

**Call context.** The forwarded SIP INVITE carries two custom headers: `X-Zernio-Call-Id`, the call's id in [history](/platforms/voice/history), and `X-WhatsApp-From`, the caller's number in E.164 (sent on phone calls too, despite the name). Both are fixed; there is no field for adding your own. Read ElevenLabs' own SIP documentation for how an agent reads inbound headers. Trunked calls do not pass through Zernio, so neither header is present on that lane.

**Call events.** On the forwarding lane Zernio emits [call webhooks](/webhooks/calls) (received, ended, failed, with caller, destination and duration) alongside recordings and transcripts. Trunked calls are delivered directly to ElevenLabs, so their post-call webhooks are the event source on that lane.

**Transfers.** Supported on both lanes. On the forwarding lane, the agent hands the caller off like any [call transfer](/platforms/voice/outbound#transfer-and-hang-up). On trunks, inbound SIP REFER is honored: an agent's `transfer_to_number` (SIP REFER type) mid-call transfers the caller, verified live.

**Countries and KYC.** Numbers are available in every country Zernio sells (including Spain, Italy, Portugal, Greece and Cyprus), with regulatory KYC handled inside the purchase flow and [number porting](/platforms/phone-numbers/porting) supported.

**Emergency calling.** Zernio numbers do not carry emergency (112/911) service.

## If it fails

A `409` with `code: "invalid_resource_state"` on the attach means the number still has Calls or WhatsApp calling enabled, is mid WhatsApp verification, or is not active:

```json
{
  "error": "The number still has Calls or WhatsApp calling enabled; disable them before attaching it to a trunk.",
  "type": "invalid_request_error",
  "code": "invalid_resource_state"
}
```

Call `DELETE /v1/phone-numbers/{id}/voice` (and disable WhatsApp calling), then attach again. On the forwarding lane, a call that connects and drops at once is almost always a `+` mismatch between the ElevenLabs import and the `forwardTo` URI. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Retell](/platforms/voice/integrations/retell): the same 2 lanes for Retell.
- [Setup](/platforms/voice/setup): voicemail, business hours and IVR on the forwarding lane.
- [Create a SIP trunk](/voice/create-sip-trunk) and [Attach a number to a SIP trunk](/voice/attach-number-to-sip-trunk): every field.
- [Call rates](/pricing/calls): per-minute prices by destination.

---
