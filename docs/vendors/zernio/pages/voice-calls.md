# Voice & Calls

Receive and place phone (PSTN) calls on your numbers, route inbound calls to a phone, a SIP endpoint or an AI voice agent, and read every call from one history.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { Bot, Headset, History, PhoneOutgoing, Plug, Settings } from 'lucide-react';
import { PlatformCapabilities } from '@/components/platform-capabilities';

Receive and place phone (PSTN) calls on the [numbers](/platforms/phone-numbers) you buy. Calling is on as soon as a number is provisioned. Inbound calls route to `forwardTo` on the number (a phone, a SIP endpoint or an AI voice agent); outbound calls dial from the number with `POST /v1/voice/calls` or from a browser softphone. Every call, inbound and outbound, is listed by `GET /v1/calls` with optional recording and transcription.

This is standalone phone calling, available in every country Zernio sells numbers in. [WhatsApp Business Calling](/platforms/whatsapp/calling) is a different feature: voice calls placed inside a WhatsApp chat, which Meta blocks for outbound in the US, Canada, Egypt, Vietnam and Nigeria. Both channels appear in the same [call history](/platforms/voice/history).

## Quick reference

<PlatformCapabilities rows={[
  { property: 'Directions', value: 'Inbound and outbound' },
  { property: 'Inbound destinations', value: 'Phone (tel:), SIP endpoint or hosted agent (sip:), or your own media server (wss:)' },
  { property: 'Outbound', value: 'POST /v1/voice/calls, or the in-browser softphone (WebRTC)' },
  { property: 'AI agents', value: 'SIP (Vapi, Retell, ElevenLabs) or a WebSocket media server of your own' },
  { property: 'Extras', value: 'Voicemail, business hours, IVR menu, caller blocklist' },
  { property: 'Recording', value: 'Off by default, +$0.004 per minute' },
  { property: 'Transcription', value: 'Off by default, +$0.03 per minute' },
  { property: 'History', value: 'One feed across every number and both channels' },
]} />

## Before you start

Voice requires usage-based billing with a payment method on file. Calls are metered per minute at the rate for the route; the [call rate table](/pricing/calls) lists every destination.

## In this section

<Cards>
  <Card icon={<Settings />} title="Setup" href="/platforms/voice/setup" description="Set where inbound calls go, plus voicemail, business hours, an IVR menu and a blocklist" />
  <Card icon={<Bot />} title="AI voice agents" href="/platforms/voice/ai-agents" description="Bridge calls to a Vapi agent over sip:, or your own media server over wss://" />
  <Card icon={<Plug />} title="Integrations" href="/platforms/voice/integrations" description="Put an ElevenLabs or Retell agent on a Zernio number over SIP" />
  <Card icon={<PhoneOutgoing />} title="Outbound calls" href="/platforms/voice/outbound" description="Place, transfer and end calls with the API" />
  <Card icon={<Headset />} title="Browser calling" href="/platforms/voice/browser-calling" description="Dial from a web page with the WebRTC softphone" />
  <Card icon={<History />} title="Call history and recordings" href="/platforms/voice/history" description="List calls across every number and fetch recordings" />
</Cards>

## Related

- [Enable voice on a number](/voice/enable-voice-on-number): every routing field.
- [Place an outbound call](/voice/create-voice-call): dial from your number.
- [Mint a browser softphone session](/voice/create-voice-web-session): the WebRTC handshake.
- [List all calls](/calls/list-calls): every call, both channels.
- [Call webhooks](/webhooks/calls): `call.received`, `call.ended` and `call.failed`.

---
