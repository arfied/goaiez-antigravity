# Glossary

Every Zernio term defined in 2 sentences, with the field or endpoint where it appears.

The terms the docs and the API use, each defined once with the field or endpoint it appears in. The 3 terms people mix up most: a **team** owns the API keys and the billing, a **profile** groups accounts (one per brand, or one per customer when you [build a platform](/multi-tenant)), and an **account** is one connected platform login inside a profile.

### 10DLC

US carrier registration required before a US number can send SMS: a one-time brand registration plus a monthly campaign fee. One approved registration [covers all your numbers](/platforms/sms/registration#reuse-an-approval-skip-the-fee). Other countries need no registration.

### Account (connected account)

A platform login (an Instagram account, a Facebook Page, an X account) that a user authorized through the [connect flow](#connect-flow). Every account lives in exactly one [profile](#profile), and every post, analytics and inbox call names it by its `accountId`.

### Account-day

The billing meter for connected accounts: one unit per account per day it stays connected. At month end Zernio sums the account-days, divides by 30, and runs the result through the [graduated price ladder](/pricing#connected-accounts). See [Billing](/billing).

### accountId

The `_id` of a [connected account](#account-connected-account), returned by `GET /v1/accounts`. Most [events](#event) carry it to say which account the event belongs to, so keep an `accountId` to customer mapping when you serve several customers. Compare [profileId](#profileid).

### API key

The credential every request sends as `Authorization: Bearer $ZERNIO_API_KEY`, created on the [API keys](https://zernio.com/dashboard/api-keys) page. One key covers a whole integration: [rate limits](/guides/rate-limits) scale with your connected accounts, not with your key count. See also [scoped API key](#scoped-api-key).

### Broadcast

A bulk message sent to a list of [contacts](#contact) on any inbox platform, created with `POST /v1/broadcasts` and delivered with `POST /v1/broadcasts/{broadcastId}/send` or queued with `/schedule`. WhatsApp broadcasts send an approved template, every other platform a plain message, and each recipient counts as one [metered message](/pricing/messages).

### Connect flow

The OAuth handshake that turns a user's platform login into a [connected account](#account-connected-account): `GET /v1/connect/{platform}?profileId=` returns an `authUrl`, the user approves there, and the account lands in that profile. The [connecting accounts guide](/guides/connecting-accounts) walks through it.

### Contact

One person's identity across platforms, linked to the platform channels that reach them (phone number, Instagram-scoped id and the rest), listed by `GET /v1/contacts` and addressed by `contactId`. Zernio creates a contact when a message arrives, or you create one with `POST /v1/contacts`.

### Content-hash dedup

The second layer of duplicate-post protection: the same `(platform, accountId, content + media)` fingerprint within 24 hours is rejected with `409` and the `existingPostId`. See [Idempotency](/guides/idempotency).

### Conversation

One inbox thread of messages between an account and a person, listed by `GET /v1/inbox/conversations` and addressed by `conversationId`. Reply with `POST /v1/inbox/conversations/{conversationId}/messages`.

### Direct message (DM)

A message sent to the account inside a [conversation](#conversation) on Instagram, Facebook, WhatsApp or X. The inbox reads and answers them through the [Messages endpoints](/messages/list-inbox-conversations); comments and reviews are separate resources.

### Event

What Zernio sends to a [webhook endpoint](#webhook-endpoint) when something happens: `post.published`, `message.received`, `account.connected` and the rest of the [event list](/webhooks). Delivery is at-least-once, so dedupe on the event `id`.

### External post

A post published on the platform itself (in the Instagram app, for example) rather than through the API. A background sync picks external posts up roughly every 90 minutes per account; [`POST /v1/posts/sync-external`](/analytics/sync-external-posts) fetches one on demand.

### Google Business Profile

The platform with value `googlebusiness`. Posts appear in Google Search and Maps, and the account is a location picked after OAuth; see the [platform page](/platforms/google-business).

### Headless mode

A [connect-flow](#connect-flow) variant (`headless=true`) where you build the Page, organization or board selection screen yourself instead of using the screen Zernio hosts, so nothing Zernio-branded is shown. See [standard vs headless](/guides/connecting-accounts#standard-vs-headless-mode).

### Idempotency key

A client-generated unique value that makes a retry safe. On [`POST /v1/posts`](/posts/create-post) it is the `x-request-id` header: the same value within about 5 minutes returns the original post (`200` with `existingPost`) instead of a second post. Profiles, SMS, inbox replies and ads take an `Idempotency-Key` header instead. See [Idempotency](/guides/idempotency).

### KYC

One-time identity verification required before provisioning phone numbers in regulated countries; review takes 1 to 3 business days. Unregulated countries provision instantly. See [number availability](/platforms/phone-numbers/availability).

### Managed ad

The billing meter for ads: one unit per ad that is running or in review, counted daily across every connected ad account. Paused, ended and rejected ads leave the meter, and the first 500 are free; see [Ads pricing](/pricing/ads).

### MCP server

Zernio's [Model Context Protocol](/mcp) server at `mcp.zernio.com`. It lets AI agents (Claude, Cursor, ChatGPT) call the API directly: post, read analytics, manage the inbox. See [MCP setup](/mcp/setup).

### Platform

One of the 16 platforms the API publishes to (Instagram, TikTok, X, LinkedIn and the rest), named by the `platform` value on each `platforms[]` entry. Each has its own capabilities and fields; see [Platforms](/platforms).

### Post

Content created with `POST /v1/posts`, targeting one or more accounts through its `platforms` array. You create it as a draft, schedule it with [`scheduledFor`](#scheduledfor), or publish it now with `publishNow: true`; it then moves through the statuses `draft`, `scheduled`, `publishing` and `published`, `partial` or `failed`. See the [post lifecycle](/guides/post-lifecycle).

### Presigned upload

The media upload flow: [`POST /v1/media/presign`](/guides/media-uploads) returns an `uploadUrl` to `PUT` the file to and a `publicUrl` to reference in posts. Uploads sit in temporary storage for 7 days until a post using them publishes.

### Profile

A group of accounts inside your [team](#team), created with `POST /v1/profiles`: one per brand, or one per customer when you build for other people (the docs call that setup multi-tenant). Every new team starts with a "Default" profile, and profile names are unique within a team. See [Build a platform](/multi-tenant).

### profileId

The `_id` of a [profile](#profile), and the customer filter across the API: pass it to the connect flow, account listing, analytics and inbox endpoints to scope a request to one profile. Compare [accountId](#accountid).

### Queue

Recurring posting slots defined per [profile](#profile). Create a post with `queuedFromProfile` and no `scheduledFor`, and it lands on the profile's next free slot. See [Queue scheduling](/guides/queue-scheduling).

### `scheduledFor`

The field on `POST /v1/posts` that sets the scheduled time, read in the `timezone` you send with it. Omit it and set `publishNow: true` to publish now, or omit both to save a draft. See [Timezones](/guides/timezones).

### Scoped API key

An [API key](#api-key) restricted to specific profiles (`scope: "profiles"` plus `profileIds`), optionally read-only (`permission: "read"`). It isolates customers; it does not raise rate limits. See [scoped keys](/multi-tenant#scoped-api-keys).

### Secondary selection

The extra step some platforms need after OAuth: picking which Facebook Page, LinkedIn organization, Pinterest board, Google Business Profile location or Snapchat public profile to connect. See [connecting accounts](/guides/connecting-accounts#platforms-requiring-secondary-selection).

### Segment (SMS)

The SMS billing unit: one 160-character GSM-7 message part (153 each when a message spans parts; 70/67 for emoji and non-Latin scripts via UCS-2). Rates are per segment, per destination; see [SMS rates](/pricing/sms).

### Sequence

A linear drip campaign: a series of messages sent to enrolled [contacts](#contact) with a delay between steps, created with `POST /v1/sequences` and started with `POST /v1/sequences/{sequenceId}/activate`. Enrol contacts with `POST /v1/sequences/{sequenceId}/enroll`; a reply or an unsubscribe exits them. Compare [workflow](#workflow).

### Team

The owner plus every invited member. A team owns the API keys and the billing, and Zernio computes [rate limits](/guides/rate-limits) and the connected-account count that drives [billing](/billing) per team. A team contains [profiles](#profile), which contain [accounts](#account-connected-account).

### Usage-based billing

How Zernio charges: every account includes every feature, and you pay per connected account per day, with the first 2 accounts free. Messages meter after 10,000 a month and managed ads after 500; see [Pricing](/pricing).

### WABA (WhatsApp Business Account)

Your business's account with Meta, which a Zernio number connects to. Meta bills template delivery and its per-minute calling fee to the WABA's payment method directly, never through Zernio. See [WhatsApp rates](/pricing/whatsapp).

### Webhook endpoint

An HTTPS URL you register on the [webhooks](https://zernio.com/dashboard/webhooks) page (up to 50 per [team](#team)) that receives [events](#event). Zernio signs every delivery with `X-Zernio-Signature` when the endpoint has a secret. See the [webhooks overview](/webhooks).

### Workflow

A branching conversation automation: an inbound message matches the workflow's trigger and walks a graph of nodes (send message, wait for reply, condition, delay, webhook, handoff). Created with `POST /v1/workflows` and started with `POST /v1/workflows/{workflowId}/activate`; unlike a [sequence](#sequence) it is event-driven rather than time-based. See [Workflows](/workflows).

### X

The platform with value `twitter`. X bills every API call, so connecting an X account needs a card on file; see the [platform page](/platforms/twitter) and [X API pricing](/pricing#x-twitter-api-usage).

---
