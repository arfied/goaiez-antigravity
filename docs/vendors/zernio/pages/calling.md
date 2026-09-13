# Calling

Turn on WhatsApp Business Calling for a connected number, forward calls to a phone, SIP endpoint or AI voice agent, and place outbound calls with the customer's permission.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page customers can call your WhatsApp number from inside the chat and the call rings wherever you chose, and you can place calls to customers who allowed it. You need a connected WhatsApp account with usage-based billing active. Zernio bridges each call to a `forwardTo` destination: a phone number (`tel:`), a SIP endpoint such as a Vapi, Retell or ElevenLabs agent (`sip:`), or a WebSocket media server of your own (`wss:`). Calling runs on `/v1/phone-numbers/{id}/whatsapp/calling`; the `/v1/whatsapp/phone-numbers/{id}/calling` paths are deprecated aliases.

<Callout type="warn">
Meta blocks business-initiated calls on numbers registered in the US, Canada, Egypt, Vietnam and Nigeria; inbound calls to those numbers still work. Elsewhere, `POST /v1/phone-numbers/{id}/whatsapp/calling` returns `422` when the team is not on usage-based billing, or when the number's messaging limit is below Meta's ~2,000-daily-recipient threshold. A new number starts at `TIER_250`; send messages from it until Meta raises the limit, then enable calling.
</Callout>

Both directions meter the carrier connection per minute, plus $0.004/minute when recording is on. Meta charges its own per-minute rate on outbound calls only, billed directly to your WABA, and nothing on inbound ([WhatsApp rates](/pricing/whatsapp)). `GET /v1/whatsapp/calls/estimate` with `accountId`, `to` and `minutes` returns the outbound legs before you dial.

## Step 1: Read the calling config

Call `GET /v1/whatsapp/calling` with `accountId`. The response carries `phoneNumberDocId`, the `{id}` every other calling call takes, plus the current destination and recording flag.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const accountId = '66b2e19d8c3f5a7e9d0b1c2d';

const { data: config } = await zernio.whatsappcalling.getWhatsAppCallingConfig({
  query: { accountId }
});

const phoneNumberId = config.phoneNumberDocId;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()
account_id = "66b2e19d8c3f5a7e9d0b1c2d"

config = client.whatsapp_calling.get_whats_app_calling_config(account_id=account_id)

phone_number_id = config["phoneNumberDocId"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/whatsapp/calling?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "phoneNumberDocId": "66f6c3d4e5f6a7b8c9d0e1f3",
  "phoneNumber": "+14155550100",
  "callingEnabled": false,
  "callDeepLink": null,
  "forwardTo": null,
  "recordingEnabled": false,
  "sipAuthUsername": null,
  "sipAuthPasswordConfigured": false,
  "callerIdMode": "business",
  "callerIdVerified": true,
  "maxCallDurationSeconds": null,
  "forwardCallerId": "business"
}
```

## Step 2: Enable calling

Call `POST /v1/phone-numbers/{id}/whatsapp/calling` with `accountId` and `forwardTo`. Zernio sets `calling.status=ENABLED` on Meta, stores the Meta-issued SIP password encrypted, and snapshots the destination.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: enabled } = await zernio.whatsappcalling.enableWhatsAppCalling({
  path: { id: phoneNumberId },
  body: {
    accountId,
    forwardTo: 'tel:+13105559876',
    recordingEnabled: false
  }
});

console.log(enabled.callingEnabled);
```
</Tab>
<Tab value="Python">
```python
enabled = client.whatsapp_calling.enable_whats_app_calling(
    id=phone_number_id,
    account_id=account_id,
    forward_to="tel:+13105559876",
    recording_enabled=False,
)

print(enabled["callingEnabled"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/66f6c3d4e5f6a7b8c9d0e1f3/whatsapp/calling" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"accountId": "66b2e19d8c3f5a7e9d0b1c2d", "forwardTo": "tel:+13105559876", "recordingEnabled": false}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "callingEnabled": true,
  "forwardTo": "tel:+13105559876",
  "callerIdMode": "business"
}
```

Recording is off by default. With `recordingEnabled: true` a consent prompt plays before recording starts and the per-minute surcharge applies. `maxCallDurationSeconds` caps a forwarded call so a destination that never hangs up cannot bill dead air, and `forwardCallerId: "caller"` presents the WhatsApp user's number to a `sip:` destination instead of yours. After enabling, `callDeepLink` on the config is a public `https://wa.me/call/...` link that starts a WhatsApp call to the number from a website, email or QR code.

Change the destination or the recording flag later with `PATCH /v1/phone-numbers/{id}/whatsapp/calling` and the same body shape; turn calling off with `DELETE /v1/phone-numbers/{id}/whatsapp/calling?accountId=...`. Disabling keeps `forwardTo` and the SIP credentials, so re-enabling needs no reconfiguration.

## Step 3: Check call permission

WhatsApp requires the customer to allow calls before you dial them. Call `GET /v1/whatsapp/call-permissions` with `accountId` and `to`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: permission } = await zernio.whatsappcalling.getWhatsAppCallPermissions({
  query: { accountId, to: '+13105551234' }
});

console.log(permission.permission.status);
```
</Tab>
<Tab value="Python">
```python
permission = client.whatsapp_calling.get_whats_app_call_permissions(
    account_id=account_id,
    to="+13105551234",
)

print(permission["permission"]["status"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/whatsapp/call-permissions?accountId=66b2e19d8c3f5a7e9d0b1c2d&to=%2B13105551234" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "permission": { "status": "no_permission" },
  "actions": [
    { "action_name": "send_call_permission_request", "can_perform_action": true },
    { "action_name": "start_call", "can_perform_action": false }
  ]
}
```

`status` is `permanent`, `temporary` (with `expiration_time` in Unix seconds) or `no_permission`. When `start_call` is not allowed, send the consent prompt with `POST /v1/whatsapp/calls` and `action: "send_call_permission_request"` (optionally `bodyText`); the customer taps Allow in WhatsApp. Meta limits the prompt to 1 per customer per 24 hours and 2 per 7 days, and it needs an open 24-hour service window.

## Step 4: Place an outbound call

Call `POST /v1/whatsapp/calls` with `accountId` and `to`. The call bridges to the number's `forwardTo` unless you pass a per-call `forwardTo` override. Send an `Idempotency-Key` header so a retry replays the response instead of dialing, and billing, a second call.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: call } = await zernio.whatsappcalling.initiateWhatsAppCall({
  body: { accountId, to: '+13105551234' }
});

console.log(call.callId, call.status);
```
</Tab>
<Tab value="Python">
```python
call = client.whatsapp_calling.initiate_whats_app_call(
    account_id=account_id,
    to="+13105551234",
)

print(call["callId"], call["status"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/whatsapp/calls" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: 3f1c9d2e-7b4a-4e8f-9c0d-1a2b3c4d5e6f" \
  -d '{"accountId": "66b2e19d8c3f5a7e9d0b1c2d", "to": "+13105551234"}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "callId": "66f8e5f6a7b8c9d0e1f2a3b5",
  "status": "dialing",
  "direction": "outbound",
  "to": "+13105551234",
  "forwardTo": "tel:+13105559876",
  "recordingEnabled": false
}
```

The rest of the call's life arrives on the [call webhooks](/webhooks/calls).

## Step 5: Read call history

Call `GET /v1/whatsapp/calls` with `accountId`, optionally filtered by `status`, `direction`, `since` and `until`. Each call carries its duration, end reason, recording URL when recording was on, and a billing breakdown. Pass the returned `nextCursor` as `before` for the next page.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: history } = await zernio.whatsappcalling.listWhatsAppCalls({
  query: { accountId, direction: 'inbound', limit: 50 }
});

for (const c of history.calls) {
  console.log(c.direction, c.status, c.durationSeconds, c.billing?.billableCostUSD);
}
```
</Tab>
<Tab value="Python">
```python
history = client.whatsapp_calling.list_whats_app_calls(
    account_id=account_id,
    direction="inbound",
    limit=50,
)

for c in history["calls"]:
    print(c["direction"], c["status"], c.get("durationSeconds"), c.get("billing", {}).get("billableCostUSD"))
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/whatsapp/calls?accountId=66b2e19d8c3f5a7e9d0b1c2d&direction=inbound&limit=50" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "calls": [
    {
      "_id": "66f8e5f6a7b8c9d0e1f2a3b5",
      "direction": "inbound",
      "from": "+13105551234",
      "to": "+14155550100",
      "status": "ended",
      "startedAt": "2027-01-01T09:00:00Z",
      "endedAt": "2027-01-01T09:04:12Z",
      "durationSeconds": 252,
      "endReason": "hangup",
      "billing": {
        "metaCostUSD": 0,
        "telnyxCostUSD": 0.0336,
        "recordingCostUSD": 0,
        "billableCostUSD": 0.0672,
        "totalCostUSD": 0.0672,
        "currency": "USD"
      }
    }
  ],
  "nextCursor": null
}
```

`billing.telnyxCostUSD` and `billing.recordingCostUSD` are the raw upstream costs; `billing.billableCostUSD` is what Zernio bills for them, at the published [call rate](/pricing/calls). `billing.metaCostUSD` is Meta's per-minute fee, billed by Meta directly to your WABA and shown for reference only; inbound calls carry no Meta fee, which is why it is `0` here.

## If it fails

A `409` from `POST /v1/whatsapp/calls` means the customer has not allowed calls:

```json
{
  "error": "No active call permission. Send a permission request first."
}
```

Run Step 3, send the permission request, and dial once `start_call` is allowed. A `422` means calling is not enabled on the number, the number's country blocks business-initiated calls, or the Meta SIP credentials are missing; a `502` means the carrier could not originate the call and it was marked `failed`. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [WhatsApp rates](/pricing/whatsapp): Meta's per-minute rate by country and the recording surcharge.
- [Calling API](/whatsapp/get-whatsapp-calling-config): every endpoint, including caller-ID verification and recordings.
- [Call webhooks](/webhooks/calls): ringing, answered, ended and failed events.
- [Voice and calls](/platforms/voice): PSTN calling on the same numbers, and the AI agent integrations.
- [Phone numbers](/platforms/whatsapp/phone-numbers): which numbers can carry calling.

---
