# Retell

Put a Retell AI agent on a Zernio number by forwarding calls to its SIP address, or import the number into Retell over a SIP trunk.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';
import { Steps, Step } from 'fumadocs-ui/components/steps';

When you finish this page a [Retell AI](https://www.retellai.com) agent answers calls to your Zernio number. You need a [number](/platforms/phone-numbers/provisioning), a Retell agent and usage-based billing. The 2 lanes work like the [ElevenLabs integration](/platforms/voice/integrations/elevenlabs): forward inbound calls to Retell's SIP address, or import the number into Retell over a SIP trunk so the agent can also place outbound calls. On both lanes the number, its countries and KYC, SMS and WhatsApp stay with Zernio.

Retell's SIP ingest is `sip.retellai.com` on port `5060` over TCP.

## Forward inbound calls

Call `POST /v1/phone-numbers/{id}/voice` with `forwardTo` set to `sip:{number}@sip.retellai.com:5060;transport=tcp`, using the number's E.164 as the identifier.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const phoneNumberId = '66d4e5f6a7b8c9d0e1f2a3b4';

const { data: voice } = await zernio.voice.enableVoiceOnNumber({
  path: { id: phoneNumberId },
  body: { forwardTo: 'sip:+34911234567@sip.retellai.com:5060;transport=tcp' }
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
    forward_to="sip:+34911234567@sip.retellai.com:5060;transport=tcp",
)
print(voice["pstnForwardTo"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/66d4e5f6a7b8c9d0e1f2a3b4/voice" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"forwardTo": "sip:+34911234567@sip.retellai.com:5060;transport=tcp"}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "enabled": true,
  "phoneNumber": "+34911234567",
  "pstnForwardTo": "sip:+34911234567@sip.retellai.com:5060;transport=tcp",
  "voicemailEnabled": true
}
```

In Retell, add the number under Phone Numbers, "Connect to your number via SIP trunking", with the same E.164 (keep the leading `+` identical on both sides) and assign your agent.

## SIP trunk

A SIP trunk takes Zernio out of the call path: Retell receives the number's inbound calls directly, and the agent can place outbound calls through Zernio presenting the number as caller ID. Turn off Calls and WhatsApp calling on the number before attaching it; while attached, Zernio-side voice features are paused for that number and SMS and WhatsApp messaging are unaffected.

<Steps>
<Step>
### Step 1: Create the trunk

Call `POST /v1/phone-numbers/sip-trunks` with Retell's host, port and transport. The dashboard's SIP page has a Retell preset that fills these in.

```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/sip-trunks" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"label": "Retell", "sipHost": "sip.retellai.com", "sipPort": 5060, "transport": "tcp"}'
```

Response (`201`):

```json
{
  "id": "66f6a7b8c9d0e1f2a3b4c5d6",
  "label": "Retell",
  "sipHost": "sip.retellai.com",
  "sipPort": 5060,
  "transport": "tcp",
  "termination": {
    "uri": "sip.telnyx.com",
    "username": "zernio66f6a7b8c9d0e1f2"
  },
  "numbersAttached": 0,
  "digestPassword": "rK9v...Qw2"
}
```

`digestPassword` appears only in this response; store it. `POST /v1/phone-numbers/sip-trunks/{id}/rotate-credentials` mints a new one if it is lost.
</Step>
<Step>
### Step 2: Configure Retell

In Retell's SIP trunking setup, point outbound at `termination.uri` (`sip.telnyx.com`) with the digest `username` and `digestPassword` from Step 1, and register the number in E.164 with the leading `+`.
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

Detach any time with `DELETE /v1/phone-numbers/{id}/sip-trunk`; Zernio routing and voice features come back immediately.
</Step>
</Steps>

The [ElevenLabs technical FAQ](/platforms/voice/integrations/elevenlabs#technical-faq) (codecs, authentication, caller ID, limits, countries) applies to any trunk destination, Retell included. One difference: Retell's ingest is TCP, so signaling is not TLS-encrypted and media is not SRTP on the inbound leg.

## If it fails

A `403` on `POST /v1/phone-numbers/sip-trunks` means SIP trunking is not enabled for your team, or the team is on legacy billing, which cannot invoice trunk call costs:

```json
{
  "error": "SIP trunking is not enabled for this team",
  "type": "permission_error",
  "code": "feature_not_available"
}
```

Branch on `code`, not the message. Move to usage-based billing, or contact support to enable trunking. A `409` on the attach means the number still has Calls or WhatsApp calling enabled; disable them first. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [ElevenLabs](/platforms/voice/integrations/elevenlabs): the same 2 lanes, with the technical FAQ.
- [Setup](/platforms/voice/setup): voicemail, business hours and IVR on the forwarding lane.
- [Create a SIP trunk](/voice/create-sip-trunk) and [Attach a number to a SIP trunk](/voice/attach-number-to-sip-trunk): every field.

---
