# n8n

Create and schedule posts from an n8n workflow with the verified Zernio node, upload media, and receive post and account events.

import { Step, Steps } from 'fumadocs-ui/components/steps';

When you finish this page an n8n workflow creates a post on a connected account through the Zernio node, with no HTTP node to configure. You need an [API key](https://zernio.com/dashboard/api-keys), a [connected account](/guides/connecting-accounts) and n8n Cloud or a self-hosted n8n.

| Detail | Value |
|--------|-------|
| Node package | `n8n-nodes-zernio` |
| Source | [github.com/zernio-dev/n8n-nodes-zernio](https://github.com/zernio-dev/n8n-nodes-zernio) |
| npm | [npmjs.com/package/n8n-nodes-zernio](https://www.npmjs.com/package/n8n-nodes-zernio) |
| License | MIT |
| Works on | n8n Cloud and self-hosted |

The node covers these resources:

| Resource | Operations |
|----------|------------|
| Posts | Create and schedule posts to X (platform value `twitter`), Instagram, Facebook, LinkedIn, TikTok, YouTube, Threads, Bluesky and Pinterest |
| Profiles | Create profiles, connect platforms |
| Media | Upload files, presign large files (over 4 MB) |
| Webhooks | Receive post and account events (`post.published`, `post.failed` and more) |
| Usage | Read usage statistics |

The Posts resource covers 9 of the platforms `POST /v1/posts` accepts. WhatsApp, Reddit, Discord, Slack, Snapchat, Google Business Profile and Telegram have no entry in the node's platform picker; reach them from n8n with the built-in HTTP Request node against `POST /v1/posts` ([Create post](/posts/create-post)), using the same API key credential.

## Step 1: Install the node

<Steps>

<Step>
### Add the node

On n8n Cloud, search for **Zernio** in the node palette; the node is verified, so no community-node install is needed.

On a self-hosted instance, open **Settings**, then **Community nodes**, click **Install a community node**, enter `n8n-nodes-zernio` and confirm. n8n installs it and the node appears in the palette without a restart.

Where that screen is unavailable (an older version, or `N8N_COMMUNITY_PACKAGES_ENABLED=false`), install it into n8n's own node directory and restart the process:

```bash
cd ~/.n8n/nodes && npm install n8n-nodes-zernio
```
</Step>

<Step>
### Create the credential

The first Zernio node you add prompts for a **Zernio API** credential. Click **Create New Credential**, paste your API key and click **Save**. The credential is reused by every Zernio node in your workflows.
</Step>

<Step>
### Check the connection

Add a Zernio node, set **Resource** to **Profiles** and **Operation** to **List Profiles**, and click **Execute Node**. The output lists your profiles.
</Step>

</Steps>

## Step 2: Create a post

1. Add a **Zernio** node.
2. Set **Resource** to **Posts**.
3. Set **Operation** to the target platform, for example **Add Post to Twitter/X**.
4. Pick the **Profile** and the **Social Account** from the dropdowns.
5. Enter the **Content**.
6. Set the scheduled time to publish later, or leave it empty to publish now.

Each platform has its own operation and fields:

| Operation | Fields |
|-----------|--------|
| Add Post to Twitter/X | Content, Media URLs |
| Add Post to Instagram | Content, Media URLs |
| Add Instagram Stories | Content, Image URL |
| Add Instagram Reels | Content, Video URL, Thumbnail |
| Add Post to LinkedIn | Content, Media URLs |
| Add Post to Facebook | Content, Media URLs |
| Add Post to TikTok | Content, Video URL |
| Upload YouTube Video | Content, Video URL, Thumbnail |
| Add Post to Threads | Content, Media URLs |
| Add Post to Bluesky | Content, Media URLs |
| Upload Pinterest Image Pin | Content, Image URL, Board, Link |
| Upload Pinterest Video Pin | Content, Video URL, Board, Cover Image, Link |

Map values from earlier nodes with n8n expressions: `{{ $json.postText }}` from a Google Sheets, RSS or Code node into **Content**, `{{ $json.publishDate }}` (ISO 8601) into the scheduled time, `{{ $json.imageUrl }}` into **Media URLs**.

## Step 3: Schedule a post

Set the scheduled time to a future timestamp and the post publishes then. To compute it, put a **Code** node before the Zernio node:

```javascript
const tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);
tomorrow.setHours(9, 0, 0, 0);

return [{ json: { scheduledFor: tomorrow.toISOString() } }];
```

Then map `{{ $json.scheduledFor }}` into the scheduled time.

## Step 4: Upload media

For files under 4 MB, set **Resource** to **Media** and **Operation** to **Upload**, and pass the file from a previous node (HTTP Request, Google Drive, Read Binary File). For larger files, run the **Presign** operation first, upload the file to the URL it returns, then use the media URL in the post. Size limits and per-platform requirements are in the [media uploads guide](/guides/media-uploads).

## Step 5: Receive events

Add a Zernio node with **Resource** set to **Webhooks**, enter your n8n webhook URL and select the events. Receive them with a **Webhook** trigger node.

| Event | Description |
|-------|-------------|
| `post.scheduled` | Post entered the scheduled state (created, queued, promoted from draft, or retried) |
| `post.published` | Post published |
| `post.failed` | Post failed on every platform |
| `post.partial` | Post published on some platforms and failed on others |
| `account.connected` | Account connected |
| `account.disconnected` | Account disconnected |

The payloads are documented under [webhooks](/webhooks). A `post.failed` event feeding a Slack or email node is the usual failure alert.

## Workflow examples

Daily posting from a content calendar: **Schedule Trigger** at 9:00 every day, **Google Sheets** reads the next row, **Zernio** creates the post from the row's content, platform and media.

RSS to social: **Schedule Trigger** every hour, **RSS Feed Read** on your blog feed, **IF** checks for new items, **Zernio** posts them to X and LinkedIn.

AI pipeline: **Schedule Trigger** daily, **HTTP Request** fetches topics, **OpenAI** writes a version per platform, **Zernio** posts each one. Prebuilt templates for these are at [zernio.com/n8n-templates](https://zernio.com/n8n-templates).

## If it fails

A `429` from the Zernio node means your team hit its request limit ([rate limits](/guides/rate-limits)). On the node, open **Settings** and set **Retry on Fail** with **Max Tries** 3 and **Wait Between Tries (ms)** 2000, and space high-volume runs with the Schedule Trigger interval or **Wait** nodes between batches. For every other failure, an **Error Workflow** (a workflow with an **Error Trigger** node, selected under **Settings** in the main workflow) receives the error and can notify you.

## Related

- [Zernio node on n8n](https://n8n.io/integrations/zernio/)
- [Workflow templates](https://zernio.com/n8n-templates)
- [Source code](https://github.com/zernio-dev/n8n-nodes-zernio) and the [npm package](https://www.npmjs.com/package/n8n-nodes-zernio)
- [API keys](https://zernio.com/dashboard/api-keys)
- [API reference](/)

---
