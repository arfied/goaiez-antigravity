# OpenClaw

Install the Zernio skill from ClawHub so OpenClaw can create, schedule and retry posts, connect accounts and read analytics from natural language.

import { Step, Steps } from 'fumadocs-ui/components/steps';

When you finish this page OpenClaw creates and schedules posts on your connected accounts from natural language, using the `zernio-api` skill from ClawHub. You need an [API key](https://zernio.com/dashboard/api-keys), a [connected account](/guides/connecting-accounts) and a working OpenClaw installation.

| Detail | Value |
|--------|-------|
| Skill name | `zernio-api` |
| ClawHub | [clawhub.ai/mikipalet/zernio-api](https://clawhub.ai/mikipalet/zernio-api) |
| Install command | `npx clawhub@latest install mikipalet/zernio-api` |
| Auth | API key in the `ZERNIO_API_KEY` environment variable |
| Base URL | `https://zernio.com/api/v1` |

The skill teaches OpenClaw these parts of the API:

| Area | What OpenClaw can do |
|------|-------------|
| Profiles | Create and manage profiles |
| Accounts | Start the OAuth flow that connects an account |
| Posts | Create, schedule, bulk upload and retry posts |
| Media | Upload images and video through presigned URLs |
| Queue | Manage queue slots for scheduled publishing |
| Webhooks | Delivery and status events, webhook verification |
| Analytics | Post performance |

It covers every platform Zernio posts to: X (platform value `twitter`), Instagram, Facebook, LinkedIn, TikTok, YouTube, Pinterest, Reddit, Bluesky, Threads, Google Business Profile, Telegram, Snapchat, Discord, Slack and WhatsApp.

## Step 1: Install the skill

<Steps>

<Step>
### Install from ClawHub

```bash
npx clawhub@latest install mikipalet/zernio-api
```

This downloads the skill and its rule files (`authentication.md`, `posts.md`, `accounts.md`, `connect.md`, `platforms.md`, `webhooks.md`, `media.md`) into your local skills directory.
</Step>

<Step>
### Add the API key

```bash
echo "ZERNIO_API_KEY=$ZERNIO_API_KEY" >> ~/.openclaw/.env
```

The key starts with `sk_`; keep it free of quotes and spaces.
</Step>

<Step>
### Restart OpenClaw

```bash
openclaw restart
```

OpenClaw loads the skill and the environment file on start.
</Step>

<Step>
### Check the connection

Ask OpenClaw to list what it can post to:

> Show my Zernio profiles and connected accounts

It calls `GET /v1/profiles` and `GET /v1/accounts` and prints them. An empty list means the key is valid but nothing is connected yet; an error here means the key did not load, which the "If it fails" section covers.
</Step>

</Steps>

## Step 2: Post from a prompt

Tell OpenClaw what to publish. The skill knows the calls, so a request like this is enough:

> Post "We shipped scheduled Stories" to X

OpenClaw lists your accounts with `GET /v1/accounts`, then calls `POST /v1/posts` with `publishNow: true`. Other prompts the skill handles: "Schedule a LinkedIn post for 1 January 2027 at 12:00 New York time", "Cross-post this to X, LinkedIn and Bluesky", "Show my recent posts", "Retry all my failed posts", "Connect my Instagram account" (OpenClaw returns the `authUrl` for you to open), "How did my last X post perform".

For media, "Post this product photo to Instagram" makes the skill request a presigned upload URL, upload the file and create the post with the resulting media URL, the same flow as the [media uploads guide](/guides/media-uploads).

## Step 3: Trigger posts from a webhook

OpenClaw can receive a POST from another service and turn it into a post. Add an endpoint to `~/.openclaw/openclaw.json`:

```json
{
  "webhooks": {
    "zernio-post": {
      "path": "/webhooks/zernio-post",
      "secret": "${OPENCLAW_WEBHOOK_SECRET}",
      "action": "Run the zernio-api skill to publish the post content from the payload"
    }
  }
}
```

OpenClaw substitutes `${VAR_NAME}` in config string values from the environment when it starts, so keep the braces: the `$VAR` shorthand is not expanded and would be stored as the literal secret. Give the sending service the same value. A service then POSTs to `http://your-openclaw-host:port/webhooks/zernio-post`:

```json
{
  "text": "Hello from a webhook",
  "platform": "twitter"
}
```

OpenClaw runs the skill and creates the post. The same pattern runs a blog-to-social pipeline (OpenClaw watches an RSS feed, summarizes each new article, writes a version per platform and posts each one) or a content calendar (a scheduled OpenClaw task reads today's entries from Notion, Apple Notes or a file and posts them).

## If it fails

A `401` from the API means the key in `~/.openclaw/.env` is wrong or was revoked. Check it under [API keys](https://zernio.com/dashboard/api-keys), fix the file and restart OpenClaw, because it reads `.env` only on start:

```bash
grep ZERNIO_API_KEY ~/.openclaw/.env
```

"Skill not found" means the install did not land: `ls ~/.openclaw/skills/zernio-api/` should list `SKILL.md` and the rule files; if it does not, run the install command again. A `429` means your team hit its request limit; space out automated posts and honour the `Retry-After` header ([rate limits](/guides/rate-limits)).

## Related

- [Zernio skill on ClawHub](https://clawhub.ai/mikipalet/zernio-api)
- [API keys](https://zernio.com/dashboard/api-keys)
- [API reference](/)
- [Media uploads](/guides/media-uploads)

---
