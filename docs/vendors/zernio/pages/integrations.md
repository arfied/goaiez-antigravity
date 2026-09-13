# Integrations

Create posts from n8n, Make, Zapier or OpenClaw, or build a chatbot with the Chat SDK adapter, using one API key and a connected account.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { Bot } from 'lucide-react';
import { SiMake, SiN8N, SiVercel, SiZapier } from 'react-icons/si';

Create posts from n8n, Make, Zapier or OpenClaw, or build a chatbot with the Chat SDK adapter. You need an [API key](https://zernio.com/dashboard/api-keys), a [connected account](/guides/connecting-accounts) and an account on the automation platform. Each integration calls the same endpoints as the [API reference](/); the guides cover the setup that differs per platform.

## Chat SDK

<Cards>
  <Card
    icon={<SiVercel />}
    title="Chat SDK"
    description="For a chatbot of your own: the official adapter that puts Zernio behind Vercel's Chat SDK on Slack, Telegram, Discord and more"
    href="/resources/integrations/chat-sdk"
  />
</Cards>

## Automation platforms

<Cards>
  <Card
    icon={<SiN8N />}
    title="n8n"
    description="Pick n8n to run the automation on your own servers. Verified Zernio node, API key credential, n8n Cloud or self-hosted"
    href="/resources/integrations/n8n"
  />
  <Card
    icon={<SiMake />}
    title="Make"
    description="Pick Make when you need an endpoint the modules do not cover: its Make an API Call module reaches any route"
    href="/resources/integrations/make"
  />
  <Card
    icon={<SiZapier />}
    title="Zapier"
    description="Pick Zapier for the widest choice of triggers: 7,000+ apps can start a Zernio post, with OAuth sign-in"
    href="/resources/integrations/zapier"
  />
  <Card
    icon={<Bot />}
    title="OpenClaw"
    description="Pick OpenClaw to post from a written instruction instead of a builder: the zernio-api ClawHub skill"
    href="/resources/integrations/openclaw"
  />
</Cards>

---
