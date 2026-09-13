# Integrations

Put an ElevenLabs or Retell agent on a Zernio number by SIP forwarding or a SIP trunk, while the number, its KYC, SMS and WhatsApp stay with Zernio.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { SiElevenlabs } from 'react-icons/si';

Put an external voice platform on a Zernio number. You need a [number](/platforms/phone-numbers/provisioning), an agent on the platform and usage-based billing, on either lane. The number, its countries and KYC, SMS and WhatsApp stay with Zernio; the platform handles the calls. Each guide covers 2 lanes: forward inbound calls to the platform's SIP address, or import the number into the platform over a SIP trunk so it can also place outbound calls.

<Cards>
  <Card icon={<SiElevenlabs />} title="ElevenLabs" href="/platforms/voice/integrations/elevenlabs" description="Conversational AI agents over SIP forwarding, or number import over a SIP trunk" />
  <Card icon={<img src="/logos/retell.png" alt="" width={16} height={16} style={{ borderRadius: 2 }} />} title="Retell" href="/platforms/voice/integrations/retell" description="Retell agents over SIP forwarding, or number import over a SIP trunk" />
</Cards>

Vapi is a `sip:` destination too, and a media server of your own takes a `wss://` forward destination; see [AI voice agents](/platforms/voice/ai-agents).

---
