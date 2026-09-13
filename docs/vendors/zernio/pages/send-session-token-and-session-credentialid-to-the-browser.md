# Send session["token"] and session["credentialId"] to the browser
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/voice/calls/web" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "token": "eyJhbGciOi...",
  "credentialId": "b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e",
  "expiresAt": "2027-01-01T13:00:00Z",
  "sdk": "@telnyx/webrtc"
}
```

The token lives about 1 hour and must outlive the whole call, not only the handshake. Pass it to the client; never ship your API key to the browser.

## Step 2: Register in the browser

Register the token with the [`@telnyx/webrtc`](https://www.npmjs.com/package/@telnyx/webrtc) SDK. When the client emits `telnyx.ready`, it is registered. Registration alone carries no audio: the dialed leg arrives as a `telnyx.notification` with a call in state `ringing`, and the page has to answer it.

```typescript
import { TelnyxRTC } from '@telnyx/webrtc';

const client = new TelnyxRTC({ login_token: token });
let activeCall;

client.on('telnyx.ready', () => {
  // registered; tell your server it can dial
});

client.on('telnyx.notification', (notification) => {
  if (notification.type !== 'callUpdate' || !notification.call) return;
  const call = notification.call;
  if (call.state === 'ringing') {
    activeCall = call;
    call.answer();
  }
  if (call.state === 'destroy') {
    activeCall = undefined;
  }
});

client.connect();

// End the call from the page
activeCall?.hangup();
```

## Step 3: Dial from the server

Call `POST /v1/voice/calls/web/dial` with `to` and the `credentialId` from Step 1. `fromNumber` picks which of your numbers to dial from; omit it when you own exactly one.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: call } = await zernio.voice.dialVoiceWebCall({
  body: {
    credentialId: session.credentialId,
    to: '+13105551234',
    fromNumber: '+14155550100'
  }
});

console.log(call.callId);
```
</Tab>
<Tab value="Python">
```python
call = client.voice.dial_voice_web_call(
    credential_id=session["credentialId"],
    to="+13105551234",
    from_number="+14155550100",
)

print(call["callId"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/voice/calls/web/dial" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"credentialId": "b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e", "to": "+13105551234", "fromNumber": "+14155550100"}'
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
  "recordingEnabled": false
}
```

The answered leg is bridged to the registered browser. The call runs through the normal outbound lane: it is logged as outbound in [history](/platforms/voice/history), honors the number's recording and transcription settings (`recordOverride` changes recording for this call), and terminates with [`call.ended` or `call.failed`](/webhooks/calls#which-events-fire-in-what-order).

## If it fails

A `422` on the dial step means the `credentialId` is unknown or expired, or no voice-enabled number matches `fromNumber`:

```json
{
  "error": "Invalid or unknown WebRTC credential",
  "type": "invalid_request_error"
}
```

Mint a new session and register it before dialing again. A `429` means the [outbound cap](/platforms/voice/outbound#place-a-call) of 60 calls per rolling hour was hit. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Outbound calls](/platforms/voice/outbound): dial without a browser.
- [Call history and recordings](/platforms/voice/history): read the call back.
- [Mint a browser softphone session](/voice/create-voice-web-session) and [Dial from the browser softphone](/voice/dial-voice-web-call): every field.

---
