# Outbound Calls

Place a phone call from one of your numbers with POST /v1/voice/calls, estimate its cost first, then transfer or end it while it is live.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you have placed a call from your number, transferred it and hung up, all over the API. You need a [voice-enabled number](/platforms/voice/setup) and usage-based billing. Calls are metered per minute at the [rate for the route](/pricing/calls).

## Place a call

Call `POST /v1/voice/calls` with `to`. Zernio dials from `fromNumber` (optional when you own exactly one voice-enabled number) and, when the callee answers, bridges them to the number's stored forward destination or to a per-call `forwardTo` override (a phone, a SIP endpoint or a `wss://` [AI agent](/platforms/voice/ai-agents)). An optional `greeting` is spoken to the callee before the bridge.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: call } = await zernio.voice.createVoiceCall({
  body: {
    fromNumber: '+14155550100',
    to: '+13105551234',
    greeting: 'Connecting you now.',
  }
});
const callId = call.callId;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

call = client.voice.create_voice_call(
    from_number="+14155550100",
    to="+13105551234",
    greeting="Connecting you now.",
)
call_id = call["callId"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/voice/calls" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"fromNumber": "+14155550100", "to": "+13105551234", "greeting": "Connecting you now."}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "callId": "66e5f6a7b8c9d0e1f2a3b4c5",
  "status": "dialing",
  "direction": "outbound",
  "from": "+14155550100",
  "to": "+13105551234",
  "forwardTo": "tel:+13105551234",
  "greeting": "Connecting you now.",
  "recordingEnabled": false,
  "transcriptionEnabled": false
}
```

`200` means the call is dialing; the rest of the lifecycle is asynchronous. Track it with `GET /v1/voice/calls/{id}` or the [`call.ended` and `call.failed` webhooks](/webhooks/calls#which-events-fire-in-what-order). Outbound phone calls do not emit `call.received`.

`recordOverride`, `transcribeOverride` and `transcriptionLanguage` change the number's defaults for this call only. `amd: true` turns on answering-machine detection and defers the bridge until Zernio knows whether a human or a machine answered; `voicemailDropMessage` speaks a message to a detected machine and hangs up.

Outbound calls are capped at 60 per rolling hour, counted across every number on your key, and return `429` past the cap with the current cap in the message. Send an `Idempotency-Key` header so a retry replays the original response instead of dialing (and billing) a second call ([idempotency](/guides/idempotency)).

## Estimate the cost first

Call `GET /v1/voice/calls/estimate` with `to` before dialing. It uses the same billing formula as the invoice, so the quote and the final charge cannot disagree; the per-minute figure is conservative, so estimates trend slightly over the settled cost.

```bash
curl "https://zernio.com/api/v1/voice/calls/estimate?to=%2B13105551234&minutes=5&recording=true" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "destinationCountry": "US",
  "minutes": 5,
  "perMinuteUsd": 0.044,
  "breakdown": {
    "telnyxCostUSD": 0.1,
    "recordingCostUSD": 0.01,
    "transcriptionCostUSD": 0,
    "billableCostUSD": 0.22,
    "totalCostUSD": 0.22
  }
}
```

`telnyxCostUSD`, `recordingCostUSD` and `transcriptionCostUSD` are the raw upstream costs; `billableCostUSD` is what Zernio charges for them, and `perMinuteUsd` is that figure over `minutes`. `totalCostUSD` equals `billableCostUSD` on a phone call: unlike a [WhatsApp call](/platforms/whatsapp/calling), there is no separate Meta bill.

## Transfer and hang up

While a call is live, blind-transfer the callee with `POST /v1/voice/calls/{id}/transfer` and `to` (a `+E164` number or a `sip:` URI; `wss://` is not a valid transfer target). Control of the leg is handed off, the caller ID on the new leg is always your own number, and the call ends normally when the transferred leg hangs up.

```bash
curl -X POST "https://zernio.com/api/v1/voice/calls/66e5f6a7b8c9d0e1f2a3b4c5/transfer" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"to": "tel:+13105559999"}'
```

Response (`200`):

```json
{
  "success": true,
  "callId": "66e5f6a7b8c9d0e1f2a3b4c5",
  "transferredTo": "tel:+13105559999"
}
```

End a call with `POST /v1/voice/calls/{id}/end`. It is idempotent: ending a call that already ended returns `200` with the call's current status.

```bash
curl -X POST "https://zernio.com/api/v1/voice/calls/66e5f6a7b8c9d0e1f2a3b4c5/end" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "success": true,
  "callId": "66e5f6a7b8c9d0e1f2a3b4c5",
  "status": "ending"
}
```

Final duration and cost are written when the hangup event lands, so the call record can briefly still show its prior status.

## If it fails

A `422` on `POST /v1/voice/calls` means no voice-enabled number matches `fromNumber`, or the number has no forward destination and the request sent no `forwardTo`:

```json
{
  "error": "No forward destination configured for +14155550100. Set forwardTo on the number or pass it per call.",
  "type": "invalid_request_error"
}
```

[Set the forward destination](/platforms/voice/setup#set-the-forward-destination) on the number, or pass `forwardTo` in the call. A `409` on transfer means the call is not connected yet or has already ended. A `502` means the carrier refused to originate; the call is marked `failed`. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Browser calling](/platforms/voice/browser-calling): place the call from a web page instead.
- [Call history and recordings](/platforms/voice/history): read the call back.
- [Place an outbound call](/voice/create-voice-call): every field of the request.
- [Call webhooks](/webhooks/calls): `call.ended` carries duration, `endReason` and the cost breakdown.
- [Call rates](/pricing/calls): per-minute prices by destination.

---
