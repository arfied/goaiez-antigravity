# Overview

Publish to 16 platforms with one request shape, and check what each platform's API exposes for analytics, the inbox, webhooks and ads before you build on it.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Publish to 16 platforms with `POST /v1/posts` and one `platforms[]` entry per account. Shopify connects as well, for blog articles only. Each platform page follows the same layout: connect, publish, platform fields, media requirements, analytics, inbox, what the platform's API does not expose, and common errors. The tables on this page say what each platform supports, so you can pick the platform page you need.

## Platform quick reference

<PlatformOverviewTable />

## Connect and publish

Every platform except Shopify connects through `GET /v1/connect/{platform}` with `profileId`, where `{platform}` is `twitter`, `instagram`, `facebook`, `linkedin`, `tiktok`, `youtube`, `pinterest`, `reddit`, `bluesky`, `threads`, `googlebusiness`, `telegram`, `snapchat`, `whatsapp`, `discord` or `slack`. Bluesky and Telegram take credentials instead of OAuth, and 5 platforms add a selection step after OAuth; the [connecting accounts guide](/guides/connecting-accounts) covers every path. Shopify connects through [`GET /v1/connect/shopify`](/connect/get-shopify-connect-url), which also needs the store's `shop` domain; see the [Shopify page](/platforms/shopify).

One request publishes to several accounts at once. Each entry names its platform and account, and `platformSpecificData` on an entry carries that platform's fields:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Our changelog for September is out',
    platforms: [
      { platform: 'twitter', accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
      { platform: 'linkedin', accountId: '66b2e19d8c3f5a7e9d0b1c2e' },
      { platform: 'bluesky', accountId: '66b2e19d8c3f5a7e9d0b1c2f' }
    ],
    publishNow: true
  }
});

console.log(published.post.status);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

published = client.posts.create_post(
    content="Our changelog for September is out",
    platforms=[
        {"platform": "twitter", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"},
        {"platform": "linkedin", "accountId": "66b2e19d8c3f5a7e9d0b1c2e"},
        {"platform": "bluesky", "accountId": "66b2e19d8c3f5a7e9d0b1c2f"},
    ],
    publish_now=True,
)

print(published["post"]["status"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Our changelog for September is out",
    "platforms": [
      {"platform": "twitter", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"},
      {"platform": "linkedin", "accountId": "66b2e19d8c3f5a7e9d0b1c2e"},
      {"platform": "bluesky", "accountId": "66b2e19d8c3f5a7e9d0b1c2f"}
    ],
    "publishNow": true
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "published",
    "platforms": [
      { "platform": "twitter", "status": "published", "platformPostUrl": "https://x.com/..." },
      { "platform": "linkedin", "status": "published", "platformPostUrl": "https://www.linkedin.com/feed/update/..." },
      { "platform": "bluesky", "status": "published", "platformPostUrl": "https://bsky.app/profile/..." }
    ]
  }
}
```

Each platform entry succeeds or fails on its own; [post lifecycle](/guides/post-lifecycle) explains the statuses. The [quickstart](/) walks through the API key, the profile and the first scheduled post.

## Platform-specific features

Each platform page documents these under Publish and Platform fields:

- **X** (`twitter`): threads, polls, scheduled spaces
- **Instagram**: Stories, Reels, carousels, collaborators
- **Facebook**: Reels, Stories, Page posts
- **LinkedIn**: documents (PDFs), company pages, personal profiles
- **TikTok**: privacy settings, duet and stitch controls
- **YouTube**: Shorts, playlists, visibility settings
- **Pinterest**: boards, rich pins
- **Reddit**: subreddits, flairs, NSFW tags, native video uploads with videogif and custom poster support
- **Bluesky**: custom feeds, app passwords
- **Threads**: reply controls
- **Google Business Profile**: location posts, offers, events, performance metrics, search keywords
- **Telegram**: channels, groups, silent messages, protected content
- **Snapchat** (closed beta): Stories, Saved Stories, Spotlight, public profiles. New connections return `403 PLATFORM_BETA_RESTRICTED` until the account is approved; there is no public release date yet.
- **WhatsApp**: template messages, broadcasts, contacts, conversations, Flows (interactive forms)
- **Discord**: messages, embeds, native polls, forum posts, threads, crosspost
- **Slack**: channel messages, thread replies, file uploads, per-message bot identity
- **Shopify** (connect-only): storefront blogs and articles, HTML bodies, SEO fields, native scheduled publish

## Analytics metrics

The [Analytics API](/analytics/get-analytics) returns these metrics per platform:

| Platform | Impressions | Reach | Likes | Comments | Shares | Saves | Clicks | Views |
|----------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Instagram | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <No /> | <Yes /> |
| Facebook | <Yes /> | <No /> | <Yes /> | <Yes /> | <Yes /> | <No /> | <Yes /> | <Yes /> |
| X | <Yes /> | <No /> | <Yes /> | <Yes /> | <Yes /> | <No /> | <Yes /> | <Yes /> |
| LinkedIn | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes />\* | <Yes />\*\* | <Yes />\*\*\* |
| TikTok | <No /> | <No /> | <Yes /> | <Yes /> | <Yes /> | <No /> | <No /> | <Yes /> |
| YouTube | <No /> | <No /> | <Yes /> | <Yes /> | <Yes />\*\*\*\* | <No /> | <No /> | <Yes /> |
| Threads | <Yes /> | <No /> | <Yes /> | <Yes /> | <Yes /> | <No /> | <No /> | <Yes /> |
| Bluesky | <No /> | <No /> | <Yes /> | <Yes /> | <Yes /> | <No /> | <No /> | <No /> |
| Reddit | <No /> | <No /> | <Yes /> | <Yes /> | <No /> | <No /> | <No /> | <No /> |
| Pinterest | <Yes /> | <No /> | <No /> | <No /> | <No /> | <Yes /> | <Yes /> | <No /> |
| Snapchat | <No /> | <Yes /> | <No /> | <No /> | <Yes /> | <No /> | <No /> | <Yes /> |
| Telegram | <No /> | <No /> | <No /> | <No /> | <No /> | <No /> | <No /> | <No /> |
| Discord | <No /> | <No /> | <No /> | <No /> | <No /> | <No /> | <No /> | <No /> |
| Slack | <No /> | <No /> | <No /> | <No /> | <No /> | <No /> | <No /> | <No /> |
| WhatsApp | <No /> | <No /> | <No /> | <No /> | <No /> | <No /> | <No /> | <No /> |
| Google Business Profile\*\*\*\*\* | <No /> | <No /> | <No /> | <No /> | <No /> | <No /> | <No /> | <No /> |

\* LinkedIn saves: personal accounts only.

\*\* LinkedIn clicks: organization accounts only.

\*\*\* LinkedIn views: video posts only.

\*\*\*\* YouTube shares come from the daily Analytics API, not the basic Data API.

\*\*\*\*\* Google deprecated per-post analytics with no replacement. Use the [Performance API](/analytics/get-google-business-performance) for location-level metrics (impressions, clicks, calls, directions, bookings).

Dedicated endpoints go further on 3 platforms: YouTube [audience demographics](/analytics/get-youtube-demographics) (age, gender, country), Google Business Profile [daily performance metrics](/analytics/get-google-business-performance) and [search keywords](/analytics/get-google-business-search-keywords), and the Instagram account, follower and Story endpoints listed on the [Instagram page](/platforms/instagram#analytics).

## Inbox

The Inbox API serves DMs, comments and reviews from every platform through [Messages](/messages/list-inbox-conversations), [Comments](/comments/list-inbox-comments) and [Reviews](/reviews/list-inbox-reviews).

### DMs

| Platform | List | Fetch | Send text | Attachments | Quick replies | Buttons | Edit | Archive |
|----------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Facebook | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <No /> | <Yes /> |
| Instagram | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <No /> | <Yes /> |
| X | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <No /> | <No /> | <No /> | <No /> |
| Bluesky | <Yes /> | <Yes /> | <Yes /> | <No /> | <No /> | <No /> | <No /> | <Yes /> |
| Reddit | <Yes /> | <Yes /> | <Yes /> | <No /> | <No /> | <No /> | <No /> | <Yes /> |
| Telegram | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> |
| WhatsApp | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <No /> | <No /> | <No /> | <Yes /> |
| SMS | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <No /> | <No /> | <No /> | <Yes /> |
| Slack | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <No /> | <No /> | <No /> | <Yes /> |
| TikTok | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <No /> | <No /> | <No /> | <Yes /> |

Discord sends outbound DMs from the bot through `POST /v1/discord/dms`; replies do not arrive, so it is not part of the inbox. See the [Discord page](/platforms/discord#inbox).

### Comments

| Platform | List | Post | Reply | Delete | Like | Hide |
|----------|:---:|:---:|:---:|:---:|:---:|:---:|
| Facebook | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> |
| Instagram | <Yes /> | <No /> | <Yes /> | <Yes /> | Limited release | <Yes /> |
| X | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> |
| Bluesky | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <No /> |
| Threads | <Yes /> | <No /> | <Yes /> | <Yes /> | <No /> | <Yes /> |
| Reddit | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <No /> |
| YouTube | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <No /> | <No /> |
| LinkedIn | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <No /> |
| TikTok | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> | <Yes /> |

[Like comment](/comments/like-inbox-comment) covers Facebook, X, Bluesky, Reddit, LinkedIn and TikTok. On LinkedIn it takes the composite comment URN as `commentId` and an optional `reactionType`. On Instagram it is in limited release: it needs Meta's `instagram_manage_engagement` permission, which Zernio holds under Standard Access, so it works for admins, developers and testers of Zernio's Meta app and returns a `403` with code `PLATFORM_BETA_RESTRICTED` for every other account, and only on accounts connected through Facebook Login ([Instagram comments](/platforms/instagram#comments)).

### Reviews

| Platform | List | Reply | Delete reply |
|----------|:---:|:---:|:---:|
| Facebook | <Yes /> | <Yes /> | <No /> |
| Google Business Profile | <Yes /> | <Yes /> | <Yes /> |

### Webhooks

Message and comment events:

| Platform | `comment.received` | `message.received` | `message.sent` |
|----------|:---:|:---:|:---:|
| Instagram | <Yes /> | <Yes /> | <Yes /> |
| Facebook | <Yes /> | <Yes /> | <Yes /> |
| X | <Yes /> | <No /> | <No /> |
| YouTube | <Yes /> | <No /> | <No /> |
| LinkedIn | <Yes /> | <No /> | <No /> |
| Bluesky | <Yes /> | <Yes /> | <Yes /> |
| Reddit | <Yes /> | <Yes /> | <Yes /> |
| Telegram | <No /> | <Yes /> | <Yes /> |
| WhatsApp | <No /> | <Yes /> | <Yes /> |
| TikTok | <Yes /> | <Yes /> | <Yes /> |

Message lifecycle events (edits, unsends, delivery status):

| Platform | `message.edited` | `message.deleted` | `message.delivered` | `message.read` | `message.failed` |
|----------|:---:|:---:|:---:|:---:|:---:|
| Instagram | <Yes /> | <Yes /> | <No /> | <Yes /> | <No /> |
| Facebook | <Yes /> | <No /> | <Yes /> | <Yes /> | <No /> |
| WhatsApp | <Yes /> | <Yes /> (business-side) | <Yes /> | <Yes /> | <Yes /> |
| Telegram | <Yes /> | <No /> | <No /> | <No /> | <No /> |
| X | <No /> | <No /> | <No /> | <No /> | <No /> |
| Bluesky | <No /> | <No /> | <No /> | <No /> | <No /> |
| Reddit | <No /> | <No /> | <No /> | <No /> | <No /> |

A "no" means the platform's API does not expose the event. Slack fires `message.received` for DMs and mentions ([Slack page](/platforms/slack#inbox)). `reaction.received` fires on Instagram, Facebook, WhatsApp, Telegram and Slack. The [webhooks page](/webhooks) has every payload.

### Account settings

| Platform | Feature | Endpoint |
|----------|---------|----------|
| Facebook | Persistent menu | `/v1/accounts/{accountId}/messenger-menu` |
| Instagram | Ice breakers | `/v1/accounts/{accountId}/instagram-ice-breakers` |
| Telegram | Bot commands | `/v1/accounts/{accountId}/telegram-commands` |

[Account settings](/account-settings/get-messenger-menu) documents each endpoint.

### No inbox

| Platform | Status | Notes |
|----------|--------|-------|
| Pinterest | No API | Pinterest exposes no DMs or comments |
| Snapchat | No API | Snapchat exposes no DMs or comments |

### Platform limitations

| Platform | Limitation |
|----------|------------|
| Instagram | Comment likes in limited release |
| X | DMs require the `dm.read` and `dm.write` scopes, no archive or unarchive, reply search cached for 2 minutes |
| Bluesky | No DM attachments, liking a comment requires its `cid` |
| Threads | No DMs, no comment likes; a comment is a reply to the thread, hide and unhide supported |
| Reddit | No DM attachments |
| Telegram | Bot-based, media limits (photos 10 MB, videos 50 MB) |
| YouTube | No DMs, no comment likes |
| LinkedIn | Organization accounts only, comment likes need the social-feed scopes |
| WhatsApp | Template messages required outside the 24-hour window, no comments |
| TikTok | Accounts connected through TikTok's Business app only; DMs need a Business Account outside the EEA, Switzerland and the UK, and are replies only |

## Ad platforms

The `/v1/ads` endpoints create campaigns, boost organic posts, manage audiences and read analytics on 7 ad networks:

| Platform | Key | Create | Boost | Audiences | Analytics |
|----------|-----|--------|-------|-----------|-----------|
| [Meta Ads](/platforms/meta-ads) (Facebook and Instagram) | `metaads` | Yes | Yes | Customer list, website, lookalike, engagement | Yes |
| [Google Ads](/platforms/google-ads) | `googleads` | Search and Display | Yes (`engagement`, `traffic`, `awareness`, `video_views`) | Customer Match | Yes |
| [LinkedIn Ads](/platforms/linkedin-ads) | `linkedinads` | Yes | Yes | Contact list, company list, engagement retargeting, website retargeting | Yes |
| [TikTok Ads](/platforms/tiktok-ads) | `tiktokads` | Yes | Spark Ads | Customer list | Yes |
| [Pinterest Ads](/platforms/pinterest-ads) | `pinterestads` | Yes | Yes | Customer list | Yes |
| [X Ads](/platforms/x-ads) | `xads` | Yes | Yes | Tailored Audiences | Yes |
| [OpenAI Ads](/platforms/openai-ads) (ChatGPT) | `openaiads` | Yes | No | No | Yes |

### Ad hierarchy

| Platform | Top level | Middle | Bottom |
|----------|-----------|--------|--------|
| Meta Ads | Campaign | Ad Set | Ad |
| Google Ads | Campaign | Ad Group | Ad |
| LinkedIn Ads | Campaign Group | Campaign | Creative |
| TikTok Ads | Campaign | Ad Group | Ad |
| Pinterest Ads | Campaign | Ad Group | Pin |
| X Ads | Campaign | Line Item | Promoted Tweet |
| OpenAI Ads | Campaign | Ad Group | Chat Card Ad |

Each ad platform page has the create and boost calls in Node.js, Python and curl.

## Common errors

One entry can fail while the others publish. The request above with a caption over Bluesky's 300-character limit returns `207` with `post.status: "partial"`, the two published entries intact and the reason on the failing one:

```json
{
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "partial",
    "platforms": [
      { "platform": "twitter", "status": "published", "platformPostUrl": "https://x.com/..." },
      { "platform": "linkedin", "status": "published", "platformPostUrl": "https://www.linkedin.com/feed/update/..." },
      { "platform": "bluesky", "status": "failed", "errorMessage": "Bluesky posts cannot exceed 300 characters" }
    ]
  },
  "platformResults": [
    { "platform": "twitter", "status": "published", "error": null },
    { "platform": "linkedin", "status": "published", "error": null },
    { "platform": "bluesky", "status": "failed", "error": "Bluesky posts cannot exceed 300 characters" }
  ]
}
```

`207` is a 2xx status, so `fetch(...).ok` is `true`; branch on the status code and on `post.status`, which [post lifecycle](/guides/post-lifecycle) defines. Send a shorter `customContent` on the entry that rejected the text. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts): the OAuth flow, the selection step and the credential platforms.
- [Create post](/posts/create-post): every field of the request.
- [Media uploads](/guides/media-uploads): upload images and videos instead of hosting them.
- [Analytics](/analytics/get-analytics): post performance metrics.
- [Ads](/ad-campaigns/list-ads): campaigns, boosts and ad analytics.
- [Pricing](/pricing): what connected accounts, analytics, the inbox, ads and outbound messages cost.

---
