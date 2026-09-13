# MCP

Connect Claude, ChatGPT, Codex, Cursor or any MCP client to the hosted Zernio MCP server and let the assistant create posts, upload media and manage the inbox.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { Settings, Wrench } from 'lucide-react';

Point any MCP client at `https://mcp.zernio.com/mcp` and sign in with your Zernio account; the assistant can then call every API endpoint, from creating and scheduling posts to answering DMs, running ads and reading analytics. The server is hosted, so there is nothing to install, and it implements the [Model Context Protocol](https://modelcontextprotocol.io/) so it works with Claude, Claude Code, ChatGPT, Codex, Cursor, Windsurf, VS Code and any other MCP client.

## First call

Add the server to your client and sign in. In Claude Code that is 2 commands:

```bash
claude mcp add --transport http zernio https://mcp.zernio.com/mcp
claude /mcp
```

Then ask the assistant to list your connected accounts. It calls `accounts_list`, which answers with the platform, username and id of each one:

```
Found 2 connected account(s):

- instagram: acmecorp (ID: 66b2e19d8c3f5a7e9d0b1c2d)
- linkedin: acme (ID: 66b2e19d8c3f5a7e9d0b1c2e)
```

[Setup](/mcp/setup) has the configuration for Cursor, Codex, ChatGPT, Claude, VS Code and every other client, from a one-line command to a config file.

## Autonomous agents

An agent has nobody at a browser to sign in, so it sends an [API key](https://zernio.com/dashboard/api-keys) and calls the tools itself. The server speaks Streamable HTTP, so plain HTTP works. This publishes a post with the `posts_create` tool; `account_id` names the account when the platform has more than one.

```bash
curl https://mcp.zernio.com/mcp \
  -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/call",
    "params": {
      "name": "posts_create",
      "arguments": {
        "content": "Hello world. This is my first post from an agent.",
        "platform": "linkedin",
        "account_id": "66b2e19d8c3f5a7e9d0b1c2d",
        "publish_now": true
      }
    },
    "id": 1
  }'
```

Response (`200`), a JSON-RPC result whose `content[0].text` is the tool's reply:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "content": [
      { "type": "text", "text": "✅ Published to linkedin (@acme)\nPost ID: 65f1c0a9e2b5af0012ab34cd" }
    ]
  }
}
```

Omit `publish_now` and the tool schedules the post `schedule_minutes` from now (60 by default); pass `is_draft: true` to save a draft instead. When the platform has several accounts and you omit `account_id`, the reply is an error that lists the candidate ids: call `accounts_list`, then retry with one of them.

Create a dedicated key per agent so you can revoke it on its own; [Setup](/mcp/setup#building-autonomous-agents) has the rest.

## Tools

20 core tools cover the common flows. The rest are generated from the OpenAPI spec, one per API endpoint, named `{category}_{operation}` (`ad_campaigns_list_ad_campaigns` for `listAdCampaigns`).

<Cards>
  <Card
    icon={<Settings />}
    title="Setup"
    description="Config for Cursor, Claude Code, Codex, ChatGPT, Claude, VS Code and other clients, running the server locally, and what to do when it fails"
    href="/mcp/setup"
  />
  <Card
    icon={<Wrench />}
    title="Tools"
    description="The core tool parameters, the tools/list call that returns your client's catalog, and the browser media upload flow"
    href="/mcp/tools"
  />
</Cards>

## How it behaves

### The server keeps about 50 tools visible and the rest behind search_tools

Your client sees the core tools plus the everyday posting, queue, validation, account-health, analytics and engagement tools, so a client that caps its tool list still shows everything it needs. The other endpoints cost no context until the assistant looks for one: [search_tools and call_tool load the long tail on demand](/mcp/tools#search_tools-and-call_tool-load-the-long-tail-on-demand).

### OAuth or an API key

Clients that support OAuth need only the server URL, and you can revoke that client from your dashboard. Clients without OAuth, and autonomous agents, send an [API key](https://zernio.com/dashboard/api-keys) as `Authorization: Bearer $ZERNIO_API_KEY`, as the request above does. [Setup](/mcp/setup#authentication) has both flows.

## Related

- [Setup](/mcp/setup): per-client configuration, local server, and fixes when it fails.
- [Tools](/mcp/tools): core tool parameters and the `tools/list` catalog.
- [API keys](https://zernio.com/dashboard/api-keys)
- [Python SDK](https://github.com/zernio-dev/zernio-python): the package the MCP server ships in.
- [SDKs](/sdks)

---
