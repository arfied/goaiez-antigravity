# Setup

Connect Cursor, Claude Code, Codex, ChatGPT, Claude, VS Code or any MCP client to the hosted Zernio MCP server with OAuth or an API key, or run the server locally.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Connect Cursor, Claude Code, Codex, ChatGPT, Claude, VS Code or any MCP client to `https://mcp.zernio.com/mcp` with OAuth or an API key. The server is hosted, so there is nothing to install; the [MCP page](/mcp) has a first tool call over plain HTTP.

## Authentication

The hosted server accepts two credentials:

- **OAuth**: your MCP client opens a browser and you sign in with your Zernio account. There is no key to copy, and you can revoke a client from your dashboard at any time.
- **API key**: pass an [API key](https://zernio.com/dashboard/api-keys) in the `Authorization: Bearer` header. Use this for clients without OAuth support and for autonomous agents.

A client that supports OAuth needs only the server URL: the server advertises its authorization flow and the client walks you through sign-in on first use. The server implements the MCP authorization spec, OAuth 2.1 with PKCE, discovery metadata, and open dynamic client registration (RFC 7591), so any MCP client can register itself without pre-approval.

<Callout type="warn">
Config files are not shell scripts. Wherever a sample on this page shows `$ZERNIO_API_KEY`, paste the key itself if your client does not expand it. Some clients (Claude Desktop on Windows among them) do not expand environment variables into a config value or into `npx` args, which leaves the header empty and produces a misleading 404 or OAuth-discovery error.
</Callout>

## Connect your client

<Tabs items={['Cursor', 'Claude Code', 'Codex', 'ChatGPT', 'Claude', 'VS Code', 'Other']}>
<Tab value="Cursor">

[Install in Cursor](cursor://anysphere.cursor-deeplink/mcp/install?name=zernio&config=eyJ1cmwiOiJodHRwczovL21jcC56ZXJuaW8uY29tL21jcCJ9)

The install link opens Cursor and adds the server. To add it by hand instead, put this in `~/.cursor/mcp.json` (or a project's `.cursor/mcp.json`):

```json
{
  "mcpServers": {
    "zernio": {
      "url": "https://mcp.zernio.com/mcp"
    }
  }
}
```

Cursor prompts you to sign in with your Zernio account. To use an API key instead, pass it as a header:

```json
{
  "mcpServers": {
    "zernio": {
      "url": "https://mcp.zernio.com/mcp",
      "headers": {
        "Authorization": "Bearer $ZERNIO_API_KEY"
      }
    }
  }
}
```

The Cursor [documentation](https://docs.cursor.com/context/model-context-protocol) covers the file format.

</Tab>
<Tab value="Claude Code">

Add the server:

```bash
claude mcp add --transport http zernio https://mcp.zernio.com/mcp
```

Then sign in with your Zernio account:

```bash
claude /mcp
```

To use an API key instead of OAuth, pass it when adding the server:

```bash
claude mcp add --transport http zernio https://mcp.zernio.com/mcp \
  --header "Authorization: Bearer $ZERNIO_API_KEY"
```

The [Zernio plugin](https://github.com/zernio-dev/zernio-claude-plugin) bundles the MCP server with skills and slash commands, and prompts once for an API key that it stores in your system keychain:

```
/plugin marketplace add zernio-dev/zernio-claude-plugin
/plugin install zernio@zernio
```

The Claude Code [documentation](https://docs.anthropic.com/en/docs/claude-code/mcp) covers MCP servers in general.

</Tab>
<Tab value="Codex">

Add the server:

```bash
codex mcp add zernio --url https://mcp.zernio.com/mcp
```

Then sign in with your Zernio account:

```bash
codex mcp login zernio
```

This writes the following to `~/.codex/config.toml`:

```toml
[mcp_servers.zernio]
url = "https://mcp.zernio.com/mcp"
```

To use an API key instead of OAuth, reference it from an environment variable:

```toml
[mcp_servers.zernio]
url = "https://mcp.zernio.com/mcp"
bearer_token_env_var = "ZERNIO_API_KEY"
```

The Codex [documentation](https://developers.openai.com/codex/mcp) covers the file format.

</Tab>
<Tab value="ChatGPT">

MCP servers are available on ChatGPT Pro, Plus, Business, Enterprise and Education accounts. Follow the [OpenAI documentation](https://platform.openai.com/docs/guides/developer-mode) to enable developer mode, then create a custom connector with:

- **Server URL:** `https://mcp.zernio.com/mcp`
- **Authentication:** OAuth

ChatGPT redirects you to sign in with your Zernio account, and the connector activates.

</Tab>
<Tab value="Claude">

In Claude (web, desktop or mobile), open Settings, then Connectors, then Add custom connector:

- **Name:** Zernio
- **URL:** `https://mcp.zernio.com/mcp`

Click **Add**. Claude redirects you to sign in with your Zernio account, then the connector activates; no API key is needed. Claude Desktop's `claude_desktop_config.json` only supports local stdio servers, so use the connector for the hosted server, or the config-file bridge in the **Other** tab.

</Tab>
<Tab value="VS Code">

[Install in VS Code](https://vscode.dev/redirect/mcp/install?name=zernio&config=%7B%22type%22%3A%22http%22%2C%22url%22%3A%22https%3A%2F%2Fmcp.zernio.com%2Fmcp%22%7D)

The install link opens VS Code and adds the server. To add it by hand instead, put this in the project's `.vscode/mcp.json`:

```json
{
  "servers": {
    "zernio": {
      "type": "http",
      "url": "https://mcp.zernio.com/mcp"
    }
  }
}
```

The VS Code [documentation](https://code.visualstudio.com/docs/copilot/chat/mcp-servers) covers the file format.

</Tab>
<Tab value="Other">

Zernio is published to the [MCP Registry](https://registry.modelcontextprotocol.io) as `com.zernio/zernio`. Use the server URL `https://mcp.zernio.com/mcp` and OAuth when your client supports it.

If your client does not support OAuth, pass an API key in the `Authorization` header. A client might accept this shape:

```json
{
  "zernio": {
    "url": "https://mcp.zernio.com/mcp",
    "headers": {
      "Authorization": "Bearer $ZERNIO_API_KEY"
    }
  }
}
```

#### stdio-only clients

If your client only supports local stdio servers (`command` plus `args`), bridge to the hosted server with [`mcp-remote`](https://www.npmjs.com/package/mcp-remote):

```json
{
  "mcpServers": {
    "zernio": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote@latest",
        "https://mcp.zernio.com/mcp",
        "--header",
        "Authorization: Bearer $ZERNIO_API_KEY"
      ]
    }
  }
}
```

Omit the two `--header` entries to authenticate with OAuth instead; `mcp-remote` opens a browser window for sign-in.

</Tab>
</Tabs>

Confirm the connection the same way in any client: ask the assistant to list your connected Zernio accounts. A working server answers from the `accounts_list` tool with one line per account (`- instagram: acmecorp (ID: 66b2e19d8c3f5a7e9d0b1c2d)`), or with `No accounts connected` when you have none yet. Anything else is one of the failures below.

## Building autonomous agents

An agent passes an API key as the bearer credential straight to the hosted server; it speaks Streamable HTTP, so any MCP SDK or plain HTTP works. The [MCP page](/mcp#autonomous-agents) has a complete `tools/call` request with its response.

Do not embed API keys in code. Provide them to the agent through a secrets vault or an environment variable, and create a dedicated key per agent on [API keys](https://zernio.com/dashboard/api-keys) so you can revoke it independently.

## Run the server locally

The MCP server ships inside the [Python SDK](https://github.com/zernio-dev/zernio-python), so you can also run it as a local stdio process for clients without HTTP support, air-gapped setups, or to pin a version. The local server authenticates with an API key from the `ZERNIO_API_KEY` environment variable; OAuth is only available on the hosted server.

<Tabs items={['uvx (no install)', 'pip']}>
<Tab value="uvx (no install)">

With [uv](https://docs.astral.sh/uv/) installed, configure your client to run the server through `uvx`, with no install step:

```json
{
  "mcpServers": {
    "zernio": {
      "command": "uvx",
      "args": ["--from", "zernio-sdk[mcp]", "zernio-mcp"],
      "env": {
        "ZERNIO_API_KEY": "$ZERNIO_API_KEY"
      }
    }
  }
}
```

To install uv:

```bash
