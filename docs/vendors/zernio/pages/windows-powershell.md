# Windows (PowerShell)
powershell -c "irm https://astral.sh/uv/install.ps1 | iex"
```

</Tab>
<Tab value="pip">

Install the SDK with the MCP extra:

```bash
pip install "zernio-sdk[mcp]"
```

This puts the `zernio-mcp` command on your PATH. Configure your client:

```json
{
  "mcpServers": {
    "zernio": {
      "command": "zernio-mcp",
      "env": {
        "ZERNIO_API_KEY": "$ZERNIO_API_KEY"
      }
    }
  }
}
```

If your client cannot find the command, use the absolute path (`which zernio-mcp` prints it) or the uvx method.

</Tab>
</Tabs>

The package also includes `zernio-mcp-http`, which serves the same Streamable HTTP transport as the hosted server if you want to self-host it; see the [HTTP deployment guide](https://github.com/zernio-dev/zernio-python/blob/main/docs/HTTP_DEPLOYMENT.md).

## If it fails

### `401 Unauthorized`

The server rejected the credential. With an API key, check it on [API keys](https://zernio.com/dashboard/api-keys): it must be active and copied without extra spaces. With OAuth, remove and re-add the server so the client runs the sign-in flow again.

### `Couldn't register with Zernio's sign-in service`

Your client failed OAuth dynamic client registration. Registration is open to any MCP client (any https, loopback or app-scheme callback), so this is usually transient: remove the server or connector and add it again to restart the flow. If it persists, check [status.zernio.com](https://status.zernio.com) or fall back to an API key in the `Authorization` header.

### Claude connector says `Couldn't reach the MCP server`

Remove the connector and add it again. Claude caches a failed authorization attempt, so a stale failure persists until you re-add it.

### `No accounts connected`

Connect an account at [zernio.com](https://zernio.com) before you post; the [connecting accounts guide](/guides/connecting-accounts) covers every platform.

### Changes not taking effect

After editing your client's MCP configuration, restart the client completely.

### `Command not found: uvx` (local setup only)

Check that uv is installed and on your PATH:

```bash
uvx --version
curl -LsSf https://astral.sh/uv/install.sh | sh
```

Restart your terminal or add uv to your PATH after installing.

## Related

- [MCP](/mcp): a first tool call over plain HTTP and how the server behaves.
- [Tools](/mcp/tools): the core tool parameters and the `tools/list` catalog.
- [API keys](https://zernio.com/dashboard/api-keys)
- [Python SDK](https://github.com/zernio-dev/zernio-python): the package the server ships in.
- [Status page](https://status.zernio.com)

---
