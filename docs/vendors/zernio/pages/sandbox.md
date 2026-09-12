# Sandbox

Test WhatsApp sending, replies and webhooks against Zernio's shared sandbox number before buying a number of your own.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you have sent a WhatsApp template to your own phone from Zernio's shared sandbox number, received the reply, and can exchange free-form messages with it for 7 days. You need an API key and a phone with WhatsApp. No number purchase, no WABA and no Meta review is involved, and nothing is billed.

| Property | Value |
|----------|-------|
| Shared number | One Zernio-owned WhatsApp number, returned by `GET /v1/phone-numbers` under `sandbox` |
| Verification | Reply-based: Zernio sends a template to your test phone, you reply, the session activates |
| Templates allowed | One, locked: `sandbox_start` (no variables) |
| Free-form text and interactive messages | Yes, inside the 24-hour customer service window the reply opens |
| Test phones per user | 1 (one active session at a time) |
| Daily caps per user | 50 messages, 5 distinct recipients (in practice 1) |
| Pending session TTL | 24 hours |
| Activated session TTL | 7 days |
| Billing | Free: no number charge, no message charge |

The sandbox is for testing. To send to more than one recipient, sync templates from WhatsApp Manager or run production traffic, [get a number of your own](/platforms/whatsapp/phone-numbers).

The number is shared, so a phone can only be targeted once its owner has proved control of it with a WhatsApp reply.

## Step 1: Discover the sandbox

Call `GET /v1/phone-numbers`. The sandbox number, the `accountId` you send from and the locked template come back under `sandbox`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: numbers } = await zernio.phonenumbers.listPhoneNumbers();

if (!numbers.sandbox) throw new Error('Sandbox not configured on this environment');
const sandbox = numbers.sandbox;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

numbers = client.phone_numbers.list_phone_numbers()

if not numbers["sandbox"]:
    raise Exception("Sandbox not configured on this environment")
sandbox = numbers["sandbox"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/phone-numbers" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), trimmed to the sandbox:

```json
{
  "numbers": [],
  "connected": [],
  "sandbox": {
    "phoneNumber": "+12029087457",
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "template": { "name": "sandbox_start", "language": "en" },
    "isSandbox": true
  }
}
```

`sandbox.accountId` is the `accountId` for every send below.

## Step 2: Start activation for your phone

Call `POST /v1/whatsapp/sandbox/sessions` with `phone`. This creates a `pending` session and at once sends the `sandbox_start` template from the sandbox number to that phone. The phone's owner opens WhatsApp and replies; any text reply, including a tap on the "Reply and verify" button, flips the session to `active`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: started } = await zernio.whatsappsandbox.createWhatsAppSandboxSession({
  body: { phone: '+13105551234' }
});

console.log(started.session.id, started.session.status);
```
</Tab>
<Tab value="Python">
```python
started = client.whatsapp_sandbox.create_whats_app_sandbox_session(phone="+13105551234")

print(started["session"]["id"], started["session"]["status"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/whatsapp/sandbox/sessions" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"phone": "+13105551234"}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "session": {
    "id": "66f7d4e5f6a7b8c9d0e1f2a4",
    "phoneE164": "13105551234",
    "status": "pending",
    "expiresAt": "2027-01-02T09:00:00Z",
    "activatedAt": null,
    "createdAt": "2027-01-01T09:00:00Z"
  },
  "sandboxNumber": "+12029087457"
}
```

Tell the user to check WhatsApp on that phone and reply. Posting the same phone again is idempotent and resends the template.

## Step 3: Wait for the reply

Activation is automatic. Poll `GET /v1/whatsapp/sandbox/sessions`, or listen for `message.received` on your [webhook endpoint](/webhooks/inbox), which fires for the reply itself.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: sessions } = await zernio.whatsappsandbox.listWhatsAppSandboxSessions();

const session = sessions.sessions[0];
if (session?.status === 'active') {
  console.log('Verified at', session.activatedAt);
}
```
</Tab>
<Tab value="Python">
```python
sessions = client.whatsapp_sandbox.list_whats_app_sandbox_sessions()

session = sessions["sessions"][0] if sessions["sessions"] else None
if session and session["status"] == "active":
    print("Verified at", session["activatedAt"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/whatsapp/sandbox/sessions" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), once the phone has replied:

```json
{
  "sessions": [
    {
      "id": "66f7d4e5f6a7b8c9d0e1f2a4",
      "phoneE164": "13105551234",
      "status": "active",
      "expiresAt": "2027-01-08T09:02:30Z",
      "activatedAt": "2027-01-01T09:02:30Z",
      "createdAt": "2027-01-01T09:00:00Z"
    }
  ],
  "sandboxNumber": "+12029087457"
}
```

## Step 4: Send to the activated phone

The phone is now a normal WhatsApp conversation in your inbox. Call `POST /v1/inbox/conversations` with the sandbox `accountId`, the phone as `participantId` (digits only, matching `phoneE164`) and the locked template. A phone with no active session is rejected.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: opened } = await zernio.messages.createInboxConversation({
  body: {
    accountId: sandbox.accountId,
    participantId: '13105551234',
    templateName: sandbox.template.name,
    templateLanguage: sandbox.template.language,
    templateParams: []
  }
});

const conversationId = opened.data.conversationId;
```
</Tab>
<Tab value="Python">
```python
opened = client.messages.create_inbox_conversation(
    account_id=sandbox["accountId"],
    participant_id="13105551234",
    template_name=sandbox["template"]["name"],
    template_language=sandbox["template"]["language"],
    template_params=[],
)

conversation_id = opened["data"]["conversationId"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/inbox/conversations" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "participantId": "13105551234",
    "templateName": "sandbox_start",
    "templateLanguage": "en",
    "templateParams": []
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "success": true,
  "data": {
    "messageId": "wamid.HBgLMTMxMDU1NTEyMzQVAgARGBI5QTNEMEY3RjQ4RjE2QjA3QzYA",
    "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b",
    "participantId": "13105551234"
  }
}
```

Each reply from the phone opens a 24-hour customer service window, inside which `POST /v1/inbox/conversations/{conversationId}/messages` with `accountId` and `message` sends free-form text, attachments and interactive messages exactly as in any other conversation ([WhatsApp inbox](/platforms/whatsapp/inbox)).

## Replace the activated phone

Revoke the current session with `DELETE /v1/whatsapp/sandbox/sessions/{sessionId}`, then start activation for the new phone:

```bash
curl -X DELETE "https://zernio.com/api/v1/whatsapp/sandbox/sessions/66f7d4e5f6a7b8c9d0e1f2a4" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"

curl -X POST "https://zernio.com/api/v1/whatsapp/sandbox/sessions" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"phone": "+442071234567"}'
```

Response (`200`), the revoke:

```json
{
  "success": true
}
```

Response (`200`), the new activation, which sends the template to the new phone at once:

```json
{
  "session": {
    "id": "66f7d4e5f6a7b8c9d0e1f2a5",
    "phoneE164": "442071234567",
    "status": "pending",
    "expiresAt": "2027-01-02T09:10:00Z",
    "activatedAt": null,
    "createdAt": "2027-01-01T09:10:00Z"
  },
  "sandboxNumber": "+12029087457"
}
```

Messages already exchanged with the old phone stay in the inbox; revocation only blocks future sends.

## If it fails

A `400` from Step 2 while another phone still has a session names that phone so you can revoke it first:

```json
{
  "error": "You can only test one phone at a time. Revoke +13105551234 first",
  "code": "invalid_field_value"
}
```

The other `400`s on the sandbox endpoints:

| Error | Cause | Fix |
|-------|-------|-----|
| `invalid_field_value` "This phone is not activated for sandbox sends" | The recipient has no active session for the calling user | Run Steps 2 and 3 for that phone first |
| `invalid_field_value` "Cannot activate the sandbox number itself" | `phone` is the sandbox number | Use a real, different WhatsApp number |
| `invalid_field_value` "Could not send the activation message..." | Meta rejected the template send (the number is not on WhatsApp, the WABA is paused); Meta's message is included | Check the phone is a WhatsApp account on a registered number |
| `rate_limited` | The 50 messages or 5 recipients per 24 hours cap | Wait for the rolling window, or use a number of your own |
| `403` from any `/sandbox/*` endpoint, `code: "INBOX_REQUIRED"` | The team is on legacy billing without inbox access | Move to usage-based billing, which includes the inbox on every account, or start the free 7-day trial the error offers while `trialAvailable` is `true` ([inbox access](/workflows#workflows-need-inbox-access)) |

## Related

- [WhatsApp phone numbers](/platforms/whatsapp/phone-numbers): get a number of your own when the sandbox is not enough.
- [WhatsApp inbox](/platforms/whatsapp/inbox): what you can send inside the 24-hour window.
- [Inbox webhooks](/webhooks/inbox): `message.received` for the activation reply and every message after it.
- [Sandbox API](/whatsapp/list-whatsapp-sandbox-sessions): the 3 session endpoints.

---
