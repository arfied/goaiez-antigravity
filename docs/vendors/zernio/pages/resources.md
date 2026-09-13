# Resources

Create posts from n8n, Make, Zapier, Chat SDK or OpenClaw, generate a client from the OpenAPI spec, or move from Ayrshare, Kapso or Twilio.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { Workflow, Code, ArrowRightLeft } from 'lucide-react';

Pick by where the calling code lives. Integrations put Zernio inside something that already runs (n8n, Make, Zapier, OpenClaw, or a bot you write with the Chat SDK), so there is no client to maintain. Open source is for code you own: generate a client from the OpenAPI spec, or fork a project already built on the API. Migrations are for code that already calls another provider, and each guide maps that provider's fields onto Zernio's. Every page here uses the same [API key](https://zernio.com/dashboard/api-keys) and the endpoints in the [API reference](/).

<Cards>
  <Card
    icon={<Workflow />}
    title="Integrations"
    description="Create posts from n8n, Make, Zapier and OpenClaw, or build a chatbot with the Chat SDK adapter."
    href="/resources/integrations"
  />
  <Card
    icon={<Code />}
    title="Open source"
    description="SDKs, the OpenAPI specification and the projects built on the Zernio API."
    href="/resources/open-source"
  />
</Cards>

## Migrations

Each guide also gives a cutover order that does not double-post.

<Cards>
  <Card
    icon={<ArrowRightLeft />}
    title="From Ayrshare"
    description="Social posting, profiles and analytics."
    href="/resources/migrations/migrating-from-ayrshare"
  />
  <Card
    icon={<ArrowRightLeft />}
    title="From Kapso"
    description="WhatsApp messaging."
    href="/resources/migrations/migrating-from-kapso"
  />
  <Card
    icon={<ArrowRightLeft />}
    title="From Twilio"
    description="WhatsApp, SMS and voice."
    href="/resources/migrations/migrating-from-twilio"
  />
</Cards>

---
