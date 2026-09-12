# Tools

The parameters of the 20 core Zernio MCP tools, the tools/list call that returns the catalog your client sees, and the browser upload flow for media.

import { Step, Steps } from 'fumadocs-ui/components/steps';

The MCP server exposes 20 core tools for the common flows plus one generated tool per API endpoint. Connect a client on the [setup page](/mcp/setup), then call `tools/list` for the catalog your client sees.

## First call

`tools/list` returns the tools the server offers a client, each with its JSON Schema. It needs no credential:

```bash
curl https://mcp.zernio.com/mcp \
  -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc": "2.0", "method": "tools/list", "id": 1}'
```

The reply is one server-sent event whose `data:` line carries the JSON-RPC result. Response (`200`), trimmed to one tool:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "tools": [
      {
        "name": "media_check_upload_status",
        "title": "Check upload status and get file URLs",
        "description": "Check the status of an upload token and get uploaded file URLs.",
        "inputSchema": {
          "type": "object",
          "properties": {
            "token": {
              "type": "string",
              "description": "The upload token from media_generate_upload_link (required)"
            }
          },
          "required": ["token"],
          "additionalProperties": false
        },
        "annotations": {
          "readOnlyHint": true,
          "destructiveHint": false,
          "openWorldHint": false
        }
      }
    ]
  }
}
```

That list is the core tools plus the everyday endpoints. Every other tool stays callable through `search_tools` and `call_tool`, described under [how it behaves](#search_tools-and-call_tool-load-the-long-tail-on-demand).

## Core tools

The 20 hand-written tools take arguments shaped for an assistant (a platform name, minutes from now) and resolve the account for you:

| Tool | Description |
|------|-------------|
| `accounts_list` | Show all connected accounts |
| `accounts_get` | Get account details for a specific platform |
| `profiles_list` / `get` / `create` / `update` / `delete` | Manage profiles |
| `posts_list` / `get` / `create` / `update` / `delete` | Manage posts |
| `posts_publish_now` | Publish a post immediately |
| `posts_cross_post` | Post to multiple platforms at once |
| `posts_retry` / `posts_list_failed` / `posts_retry_all_failed` | Handle failed posts |
| `media_generate_upload_link` | Get a link to upload media files |
| `media_check_upload_status` | Check if media upload is complete |
| `docs_search` | Search the Zernio API documentation |

Every write tool below refuses an ambiguous account instead of picking one; see [how it behaves](#write-tools-refuse-an-ambiguous-account).

### `posts_create`

Creates a post as a draft, scheduled, or published now. The two booleans pick the mode:

| `is_draft` | `publish_now` | Result |
|---|---|---|
| `true` | any | Saved as a draft, not scheduled |
| `false` | `true` | Published immediately |
| `false` | `false` (default) | Scheduled `schedule_minutes` from now |

| Parameter | Type | Description | Required | Default |
|-----------|------|-------------|----------|---------|
| `content` | `string` | The post text | Yes | - |
| `platform` | `string` | Target platform: twitter, instagram, linkedin, tiktok, bluesky, facebook, youtube, pinterest, threads | Yes | - |
| `account_id` | `string` | The account to post from. Required when the user has more than one account on this platform; `accounts_list` returns the ids | No | `""` |
| `profile_id` | `string` | Scope account resolution to one profile (one client in an agency setup) when `account_id` is unknown but the profile is | No | `""` |
| `is_draft` | `boolean` | Save as a draft, neither published nor scheduled | No | `false` |
| `publish_now` | `boolean` | Publish immediately | No | `false` |
| `schedule_minutes` | `integer` | Minutes from now to schedule. Used only when `is_draft` and `publish_now` are both `false` | No | `60` |
| `media_urls` | `string` | Comma-separated URLs of images or videos to attach | No | `""` |
| `title` | `string` | Post title (required for YouTube, recommended for Pinterest) | No | `""` |

### `posts_publish_now`

`posts_create` with `publish_now=true`.

| Parameter | Type | Description | Required | Default |
|-----------|------|-------------|----------|---------|
| `content` | `string` | The post text | Yes | - |
| `platform` | `string` | Target platform | Yes | - |
| `account_id` | `string` | The account to post from. Required when the user has more than one account on this platform | No | `""` |
| `profile_id` | `string` | Scope account resolution to one profile when `account_id` is unknown | No | `""` |
| `media_urls` | `string` | Comma-separated URLs of media files to attach | No | `""` |

### `posts_cross_post`

Posts the same content to several platforms at once. To target two accounts on the same platform in one call, repeat the platform: `platforms="twitter,twitter"`, `account_ids="66b2e19d8c3f5a7e9d0b1c2d,66b2e19d8c3f5a7e9d0b1c2e"`.

| Parameter | Type | Description | Required | Default |
|-----------|------|-------------|----------|---------|
| `content` | `string` | The post text | Yes | - |
| `platforms` | `string` | Comma-separated platforms (`twitter,linkedin,bluesky`). Repeat a platform to target several of its accounts | Yes | - |
| `account_ids` | `string` | Comma-separated account ids, parallel to `platforms`. An empty position falls back to profile or automatic resolution. Required for users with several accounts on a platform | No | `""` |
| `profile_id` | `string` | Scope resolution to one profile when `account_ids` is empty | No | `""` |
| `is_draft` | `boolean` | Save as a draft | No | `false` |
| `publish_now` | `boolean` | Publish immediately to every platform | No | `false` |
| `media_urls` | `string` | Comma-separated URLs of media files to attach | No | `""` |

### `posts_list`

| Parameter | Type | Description | Required | Default |
|-----------|------|-------------|----------|---------|
| `status` | `string` | Filter by status: draft, scheduled, published, failed | No | `""` |
| `limit` | `integer` | Maximum number of posts to return | No | `10` |

### `posts_get` / `posts_delete` / `posts_retry`

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| `post_id` | `string` | The post id | Yes |

### `posts_update`

| Parameter | Type | Description | Required | Default |
|-----------|------|-------------|----------|---------|
| `post_id` | `string` | The post to update | Yes | - |
| `content` | `string` | New content | No | `""` |
| `scheduled_for` | `string` | New scheduled time (ISO 8601) | No | `""` |
| `title` | `string` | New title | No | `""` |

Only draft, scheduled and failed posts can be updated.

### `media_generate_upload_link`

Takes no parameters. Returns an upload URL for the user to open in a browser, plus the `token` that `media_check_upload_status` polls and its expiry; see [media uploads go through a browser link](#media-uploads-go-through-a-browser-link).

### `media_check_upload_status`

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| `token` | `string` | The upload token from `media_generate_upload_link` | Yes |

The reply is `pending` while the user has not uploaded, `expired` when the link timed out, or `completed` with the filename, type, size and URL of each file and the comma-separated `media_urls` value to pass to `posts_create`.

### `docs_search`

Searches these docs and returns the 5 best-matching sections, each with its heading and its text, so the assistant can answer a question about the API without leaving your client.

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| `query` | `string` | What to look for: `webhooks`, `create post`, `authentication` | Yes |

## Generated tools

One tool per API endpoint, generated from the OpenAPI spec, so new endpoints appear as they ship. Names follow `{category}_{operation}`: the `listAdCampaigns` endpoint becomes `ad_campaigns_list_ad_campaigns`. Each tool maps 1:1 to an [API reference](/posts/create-post) operation, where the request and response schemas live, and the argument names are the snake_case form of the fields documented there.

The names are not retyped on this page. Call `tools/list` for the ones your client sees, and `search_tools` for the rest.

One generated tool is worth naming next to its core twin. `posts_create_post` mirrors the full `createPost` REST surface, so use it instead of `posts_create` for per-target customisation the core tool does not expose: `customContent` (a different caption per platform), `customMedia` (different attachments per target), a per-target `scheduledFor`, or `platformSpecificData` (TikTok privacy, YouTube category). Its `platforms` argument is an array of objects with `platform`, `accountId` and any per-target overrides. Available in `zernio-sdk` 1.4.0 and later.

## How it behaves

### search_tools and call_tool load the long tail on demand

Your client never loads every tool at once. `tools/list` returns about 50: the 20 core tools plus the everyday posting, queue, validation, account-health, analytics and engagement tools. The rest sit behind two tools: `search_tools`, a full-text search over the catalog ("send a WhatsApp template", "TikTok insights", "pause a campaign") that returns the best matches with their schemas, and `call_tool`, which invokes any tool the search found. A tool such as `whatsapp_flows_publish_whats_app_flow` costs no context until the assistant needs it, and there is nothing to configure.

### Write tools refuse an ambiguous account

When you have more than one account on a platform, `posts_create`, `posts_publish_now` and `posts_cross_post` need `account_id` (or `profile_id`) to pick one. Without it they return an error listing the candidate account ids instead of picking one silently; the assistant calls `accounts_list` and retries. Available in `zernio-sdk` 1.4.0 and later.

### Media uploads go through a browser link

An AI client cannot read files on your computer, so media reaches the server through a browser upload:

<Steps>

<Step>
### Ask for an upload link

Say that you want to post an image or video. The assistant calls `media_generate_upload_link` and gives you a URL.
</Step>

<Step>
### Upload the file

Open the URL in your browser and drop the image or video on the upload page. The link expires 30 minutes after it is generated.
</Step>

<Step>
### Say that the upload is done

The assistant calls `media_check_upload_status` with the token, reads the file URLs, and creates the post with `media_urls` set.
</Step>

</Steps>

Accepted file types are JPG, PNG, WebP and GIF images, MP4, MOV, AVI, WebM and M4V videos, and PDF documents, up to 5 GB per file.

## Related

- [MCP](/mcp): the first call from a client, and the `tools/call` request an agent sends.
- [Setup](/mcp/setup): per-client configuration and the local server.
- [Create post](/posts/create-post): the REST operation behind `posts_create` and `posts_create_post`.
- [Media uploads](/guides/media-uploads): size and format limits per platform.
- [Python SDK](https://github.com/zernio-dev/zernio-python): the package the server ships in.

---
