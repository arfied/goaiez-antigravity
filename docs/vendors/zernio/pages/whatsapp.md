# WhatsApp

Send template messages, broadcasts, flows and replies from a WhatsApp Business Account with the Zernio API, plus groups, calling and Click-to-WhatsApp attribution.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { Tab, Tabs } from 'fumadocs-ui/components/tabs';
import { Bot, CreditCard, FileImage, Inbox, LayoutTemplate, Megaphone, MessagesSquare, Phone, PhoneCall, Plug, Radio, Users, Workflow } from 'lucide-react';
import { PlatformCapabilities } from '@/components/platform-capabilities';

Send template messages, broadcasts, flows and replies on WhatsApp from a connected WhatsApp Business Account (platform value `whatsapp`). A conversation starts with `POST /v1/inbox/conversations` and a template; a broadcast starts with `POST /v1/broadcasts`. The same account serves the [inbox](/platforms/whatsapp/inbox), [group chats](/platforms/whatsapp/groups), [voice calling](/platforms/whatsapp/calling) and [Click-to-WhatsApp attribution](/platforms/whatsapp/ctwa).

## Quick reference

<PlatformCapabilities rows={[
  { property: 'Message types', value: 'Text, template, image, video, document, audio, interactive, location, contact card' },
  { property: 'Template required', value: 'Yes, outside the 24-hour customer service window' },
  { property: 'Media limits', value: <><a href="/platforms/whatsapp/reference#media-requirements">Media requirements</a></> },
  { property: 'Scheduling', value: 'Broadcasts only (no scheduled single messages)' },
  { property: 'Inbox (DMs)', value: 'Yes' },
  { property: 'Inbox (comments)', value: 'No' },
  { property: 'Group chats', value: <>Yes, non-coexistence numbers only (<a href="/platforms/whatsapp/groups">Group chats</a>)</> },
  { property: 'Flows', value: 'Yes (forms, surveys, booking)' },
  { property: 'Calling', value: <>Yes, inbound and outbound voice (<a href="/platforms/whatsapp/calling">Calling</a>)</> },
  { property: 'Analytics', value: 'No (delivery status per message only)' },
]} />

## Before you start

WhatsApp requires a WhatsApp Business Account (WABA) inside a Meta Business account ([business.facebook.com](https://business.facebook.com)); a personal WhatsApp account cannot be connected. The WABA is created during the connection flow if you do not have one, and it needs at least one phone number registered to it.

Two rules shape every send. You can message a customer freely for 24 hours after their last message; outside that window the message must be an approved template ([Templates](/platforms/whatsapp/templates)). A freshly connected number starts on Meta's lowest messaging tier, 250 unique contacts per day, and Meta raises it as you build messaging history and keep quality up.

## Connect

Call `GET /v1/connect/whatsapp` with `profileId` and `redirect_url`. Embedded Signup is the only browser route onto Meta's Cloud API; the coexistence variant adds a QR code the business scans with the WhatsApp Business app ([coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence)).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: connect } = await zernio.connect.getConnectUrl({
  path: { platform: 'whatsapp' },
  query: {
    profileId: '66a1f0c2a4b9d3e8f1a2b3c4',
    redirect_url: 'https://myapp.com/callback',
    onboarding: 'api'
  }
});

console.log(connect.authUrl);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

connect = client.connect.get_connect_url(
    platform="whatsapp",
    profile_id="66a1f0c2a4b9d3e8f1a2b3c4",
    redirect_url="https://myapp.com/callback",
    onboarding="api",
)

print(connect["authUrl"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/connect/whatsapp?profileId=66a1f0c2a4b9d3e8f1a2b3c4&redirect_url=https://myapp.com/callback&onboarding=api" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "authUrl": "https://www.facebook.com/v21.0/dialog/oauth?client_id=...",
  "state": "..."
}
```

Send the user to `authUrl`. They land back on `redirect_url` with `accountId`, the id every WhatsApp call takes from here on.

Three questions decide how you connect, and the [connection guide](/platforms/whatsapp/connection) answers each with the call to make:

- **Whose number.** A number you [buy through Zernio](/platforms/whatsapp/phone-numbers) can also carry Calls and SMS and can only be connected on the profile it was bought for. A number you already own connects through Embedded Signup or with a Meta System User token.
- **Coexistence or Cloud API only.** Keeping the number in the WhatsApp Business app (coexistence) caps throughput at 20 messages per second and turns off the Groups API and calling. Pass `onboarding=api` to skip it.
- **One number per profile.** Each profile holds exactly one WhatsApp number; connect a second number to a second profile.

## Who bills what

WhatsApp has two billers, Zernio and Meta. [Pricing & costs](/platforms/whatsapp/pricing) says which charge lands on which invoice; [WhatsApp rates](/pricing/whatsapp) has Meta's numbers by country.

## In this section

<Cards>
  <Card icon={<Plug />} title="Connection & Setup" href="/platforms/whatsapp/connection" description="Embedded Signup, coexistence, System User credentials, and the business profile customers see" />
  <Card icon={<Radio />} title="Broadcasts" href="/platforms/whatsapp/broadcasts" description="Send a template to many recipients with per-recipient variables and scheduling" />
  <Card icon={<LayoutTemplate />} title="Templates" href="/platforms/whatsapp/templates" description="Create templates, read Meta's review status, import from the template library" />
  <Card icon={<Users />} title="Contacts" href="/platforms/whatsapp/contacts" description="Create and import contacts with a WhatsApp channel so broadcasts can target them" />
  <Card icon={<Phone />} title="Phone Numbers" href="/platforms/whatsapp/phone-numbers" description="Which number gets WhatsApp, the liveness check, and the two-step PIN" />
  <Card icon={<PhoneCall />} title="Calling" href="/platforms/whatsapp/calling" description="Inbound and outbound WhatsApp voice calls forwarded to a phone, SIP or AI agent" />
  <Card icon={<MessagesSquare />} title="Sandbox" href="/platforms/whatsapp/sandbox" description="Test against Zernio's shared number before buying one" />
  <Card icon={<MessagesSquare />} title="Group Chats" href="/platforms/whatsapp/groups" description="Create groups, manage participants, invite links and join requests" />
  <Card icon={<Inbox />} title="Inbox" href="/platforms/whatsapp/inbox" description="Receive and reply, interactive and commerce messages, delivery webhooks" />
  <Card icon={<Bot />} title="Meta Business Agent" href="/platforms/whatsapp/business-agent" description="Provision and run Meta's AI agent on a number without the merchant opening Business Manager" />
  <Card icon={<Workflow />} title="Flows" href="/platforms/whatsapp/flows" description="Native forms, surveys and booking screens inside WhatsApp" />
  <Card icon={<Megaphone />} title="Click-to-WhatsApp Ads" href="/platforms/whatsapp/ctwa" description="Capture the ad click and send conversions back to Meta" />
  <Card icon={<CreditCard />} title="Pricing & Costs" href="/platforms/whatsapp/pricing" description="What Zernio charges and what Meta charges" />
  <Card icon={<FileImage />} title="Media & Limits" href="/platforms/whatsapp/reference" description="Media formats and sizes, what the API does not expose, and every Meta error code with its fix" />
</Cards>

## Analytics

WhatsApp exposes no post-level analytics through its API. Delivery status (sent, delivered, read) is tracked per message and per broadcast recipient, and arrives as the `message.delivered` and `message.read` events ([inbox webhooks](/platforms/whatsapp/inbox#webhooks)).

## Common errors

The connect call fails on a `404` when `profileId` is unknown, and on a `402` when the team has no payment method ([connecting accounts](/guides/connecting-accounts#if-it-fails)). The conflicts that matter for WhatsApp appear later, when the user picks a number: they come back on your `redirect_url` as `error=one_whatsapp_per_profile`, `whatsapp_number_pinned_to_profile` or `whatsapp_number_already_connected`, each with its fix in [connection and setup](/platforms/whatsapp/connection#if-it-fails).

## Related

- [Connecting accounts](/guides/connecting-accounts): the redirect flow, headless mode and [scopes](/guides/connecting-accounts#scopes) shared by every platform.
- [Messages](/messages/list-inbox-conversations): the inbox API reference.
- [Broadcasts](/broadcasts/create-broadcast) and [Contacts](/contacts/list-contacts): API reference.
- [WhatsApp webhooks](/webhooks/whatsapp) and [phone number webhooks](/webhooks/phone-numbers): template reviews, number lifecycle.
- [Phone numbers](/platforms/phone-numbers): buying, porting, KYC and per-country pricing.

---
