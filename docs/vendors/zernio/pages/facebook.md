# Facebook

Publish feed posts, multi-image posts, link carousels, Stories and Reels to a Facebook Page with the Zernio API, with drafts, first comments and country targeting.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Publish feed posts, multi-image posts, link carousels, Stories and Reels to a Facebook Page with `POST /v1/posts` and `platform: "facebook"`. The same account also serves analytics, Messenger DMs, comments, Page reviews and comment-to-DM automations.

## Quick reference

| Property | Value |
|----------|-------|
| Character limit | 63,206 (truncated at about 480 with "See more") |
| Images per post | 10 |
| Videos per post | 1 |
| Image formats | JPEG, PNG, GIF (WebP converted to JPEG) |
| Image max size | 4 MB (Facebook rejects larger files in practice) |
| Video formats | MP4, MOV |
| Video max size | 4 GB |
| Video max duration | 240 minutes (feed), 60 seconds (Reel), 120 seconds (Stories) |
| Post types | Feed (text, image, video, multi-image, link carousel), Story, Reel |
| Scheduling | Yes |
| Inbox (DMs) | Yes |
| Inbox (comments) | Yes |
| Inbox (reviews) | Yes |
| Comment-to-DM automations | Yes |
| Analytics | Yes |

## Before you start

Facebook requires a Page; personal profiles cannot post through the API, and the user must be an Admin or Editor of the Page. One connected account can manage several Pages ([Post to several Pages](#post-to-several-pages)). Facebook tokens expire frequently, so subscribe to the `account.disconnected` webhook.

## Connect

Call `GET /v1/connect/facebook` with `profileId` on [Get OAuth connect URL](/connect/get-connect-url). After OAuth the user picks which Page to connect, in standard or headless mode; see [platforms requiring secondary selection](/guides/connecting-accounts#platforms-requiring-secondary-selection). The [connecting accounts guide](/guides/connecting-accounts) covers the OAuth flow and [scopes](/guides/connecting-accounts#scopes) in general; [Account health](/accounts/get-all-accounts-health) reports what a connected account can do with the scopes the user granted.

### OAuth scopes

| Scope | What it enables |
|-------|-----------------|
| `pages_show_list` | List the Pages the user manages during connection |
| `pages_manage_posts` | Create, edit and delete Page posts |
| `pages_read_engagement` | Read Page content and engagement (also used for analytics) |
| `read_insights` | Page and post analytics (views, clicks, reach) |
| `pages_manage_engagement` | Manage comments on Page posts |
| `pages_read_user_content` | Read user-generated content (comments) on the Page |
| `pages_messaging` | Messenger conversations in the inbox |
| `pages_manage_metadata` | Page webhook subscriptions and settings |
| `business_management` | Discover Pages owned through a Meta Business Manager |

If your team has ads enabled, the consent screen also requests `ads_management`, `ads_read`, `pages_manage_ads` and `leads_retrieval`, so the same token can drive [Meta Ads](/platforms/meta-ads) and lead forms. Accounts connected before ads were enabled need a reconnect to pick these up.

## Publish

A plain post becomes a feed post: text on its own, up to 10 images, or 1 video. Fields in `platformSpecificData` on the Facebook entry select Stories and Reels; `facebookSettings` holds drafts, link carousels and text backgrounds and can also be sent at the request root.

### Feed post

Text with an optional image. Facebook is one of the few platforms that accepts a post with no media, so `mediaItems` can be omitted.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'New collection in store from Monday',
    mediaItems: [
      { type: 'image', url: 'https://cdn.example.com/collection.jpg' }
    ],
    platforms: [
      { platform: 'facebook', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
    ],
    publishNow: true
  }
});

console.log(published.post.platforms[0].platformPostUrl);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

published = client.posts.create_post(
    content="New collection in store from Monday",
    media_items=[
        {"type": "image", "url": "https://cdn.example.com/collection.jpg"}
    ],
    platforms=[
        {"platform": "facebook", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
    ],
    publish_now=True
)

print(published["post"]["platforms"][0]["platformPostUrl"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "New collection in store from Monday",
    "mediaItems": [
      {"type": "image", "url": "https://cdn.example.com/collection.jpg"}
    ],
    "platforms": [
      {"platform": "facebook", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
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
      {
        "platform": "facebook",
        "status": "published",
        "platformPostUrl": "https://www.facebook.com/..."
      }
    ]
  }
}
```

Every sample below changes only the `mediaItems`, the `platforms` entry or `facebookSettings` of this request.

### Multi-image post

Up to 10 images in one post. Images and videos cannot be mixed:

```json
"mediaItems": [
  { "type": "image", "url": "https://cdn.example.com/photo1.jpg" },
  { "type": "image", "url": "https://cdn.example.com/photo2.jpg" },
  { "type": "image", "url": "https://cdn.example.com/photo3.jpg" }
]
```

### Link carousel

`facebookSettings.carouselCards` renders a post as a carousel of 2 to 10 cards, each image with its own click-through link and headline. Cards are layered onto `mediaItems` in order, one card per image, so both arrays must have the same length and every item must be an image. `carouselLink` sets the "See more" destination on the end card and defaults to the first card's link. Facebook displays about 35 characters of `name` and 30 of `description`; the API accepts up to 255 of each and truncates on render.

```json
{
  "content": "This week's inventory",
  "mediaItems": [
    { "type": "image", "url": "https://cdn.example.com/car-1.jpg" },
    { "type": "image", "url": "https://cdn.example.com/car-2.jpg" },
    { "type": "image", "url": "https://cdn.example.com/car-3.jpg" }
  ],
  "platforms": [
    { "platform": "facebook", "accountId": "66b2e19d8c3f5a7e9d0b1c2d" }
  ],
  "publishNow": true,
  "facebookSettings": {
    "carouselLink": "https://example.com/inventory",
    "carouselCards": [
      { "link": "https://example.com/inventory/car-1", "name": "2027 Sedan", "description": "Low miles" },
      { "link": "https://example.com/inventory/car-2", "name": "2026 SUV", "description": "Certified pre-owned" },
      { "link": "https://example.com/inventory/car-3", "name": "2027 Truck", "description": "Loaded" }
    ]
  }
}
```

### Video and GIF

One video per post. Send a GIF as `type: "video"`: Facebook treats it as a video that auto-plays and loops.

```json
"mediaItems": [
  { "type": "video", "url": "https://cdn.example.com/launch.mp4" }
]
```

### Large text background

`facebookSettings.textFormatPresetId` renders a text-only feed post as large text on a coloured background, using Facebook's `text_format_preset_id`. Pass the raw numeric id from Meta; Zernio publishes no catalog of presets and Facebook may change the set.

```json
{
  "platform": "facebook",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "facebookSettings": { "textFormatPresetId": "123" }
  }
}
```

Pages and text-only feed posts only. Zernio rejects the request with a `400` when `mediaItems` or `carouselCards` are present, when `contentType` is `story` or `reel`, or when `content` is empty, because an attachment makes Facebook drop the background silently. Length is not rejected: Facebook's composer stops offering a background at around 130 characters, but Meta documents no API limit, so longer content publishes and returns a warning. While a preset is set, a URL in `content` is not attached as a link preview, for the same reason.

### Story

`contentType: "story"` publishes a Page Story. Media is required, Stories disappear after 24 hours, captions are not displayed, and interactive stickers are not available through the API:

```json
{
  "platform": "facebook",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "contentType": "story" }
}
```

### Reel

`contentType: "reel"` publishes a Reel from a single vertical (9:16) video of 3 to 60 seconds. `content` is the caption; `title` sets a separate Reel title:

```json
{
  "platform": "facebook",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "contentType": "reel", "title": "Studio day" }
}
```

### Draft in Publishing Tools

`facebookSettings.draft: true` creates an unpublished draft in Facebook Publishing Tools instead of publishing, for review and approval workflows. Drafts work for feed posts (text, link, image, video) and Reels, not Stories, and expire after about 30 days. `firstComment` is skipped on a draft.

```json
{
  "platform": "facebook",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "facebookSettings": { "draft": true }
  }
}
```

### First comment

`firstComment` is posted as the first comment right after publishing. Feed posts and Reels, not Stories, and skipped when `facebookSettings.draft` is `true`:

```json
{
  "platform": "facebook",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "firstComment": "Order here: https://shop.example.com"
  }
}
```

### Geo-restriction

`geoRestriction.countries` limits who can see the post to up to 25 countries, as uppercase ISO 3166-1 alpha-2 codes. It is a hard visibility restriction: people outside those countries cannot see the post at all. Feed posts, videos and Reels; not Stories.

```json
{
  "platform": "facebook",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "geoRestriction": { "countries": ["US", "ES"] }
  }
}
```

### Post to several Pages

When the connected account manages more than one Page, list them with [List Facebook pages](/connect/get-facebook-pages):

```bash
curl "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/facebook-page" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "pages": [
    { "id": "123456789012345", "name": "My Brand Page", "username": "mybrand", "category": "Brand", "fan_count": 5000 },
    { "id": "234567890123456", "name": "My Other Page", "username": "myotherpage", "category": "Business", "fan_count": 1200 }
  ],
  "selectedPageId": "123456789012345",
  "cached": true
}
```

`refresh=true` bypasses the cache and fetches the Pages from Meta again, at most once per 60 seconds. To post to two Pages in one request, repeat the same `accountId` with a different `pageId` in each entry:

```json
"platforms": [
  {
    "platform": "facebook",
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platformSpecificData": { "pageId": "123456789012345" }
  },
  {
    "platform": "facebook",
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platformSpecificData": { "pageId": "234567890123456" }
  }
]
```

To change the default Page instead, so `pageId` can be omitted, call `PUT /v1/accounts/{accountId}/facebook-page` with `selectedPageId` ([Update Facebook page](/connect/update-facebook-page)).

## Platform fields

All fields go in `platformSpecificData` on the Facebook entry. `facebookSettings` can also be sent at the request root, where it applies to every Facebook entry in the request; a per-entry value wins.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `contentType` | `"story"` \| `"reel"` | (feed) | `"story"` for a Page Story (24 hours, no caption), `"reel"` for a Reel (single vertical video). Omit for a feed post. |
| `title` | string | | Reel title, separate from the `content` caption. `contentType: "reel"` only. |
| `firstComment` | string | | Posted as the first comment after publishing. Feed posts and Reels, not Stories. Skipped when `facebookSettings.draft` is `true`. |
| `pageId` | string | (default Page) | Target Page when the account manages several. List them with `GET /v1/accounts/{accountId}/facebook-page`. |
| `geoRestriction` | \{countries\} | | Up to 25 uppercase ISO 3166-1 alpha-2 codes. Feed posts, videos and Reels; not Stories. See [Geo-restriction](#geo-restriction). |
| `facebookSettings.draft` | boolean | `false` | Create an unpublished draft in Facebook Publishing Tools. Feed posts and Reels, not Stories. Drafts expire after about 30 days. |
| `facebookSettings.carouselCards` | Array\<\{link, name?, description?\}\> | | 2 to 10 cards, one per image in `mediaItems` (same length, images only). Mutually exclusive with `contentType`. `name` and `description` take up to 255 characters; Facebook displays about 35 and 30. |
| `facebookSettings.carouselLink` | string (URL) | (first card's link) | "See more" destination on the carousel end card. Only with `carouselCards`. |
| `facebookSettings.textFormatPresetId` | string (digits) | | Facebook preset id for a large-text background. Text-only feed posts on Pages; `400` when media or cards are present, `contentType` is set, or `content` is empty. |

## Media requirements

Facebook rejects images above 4 MB and videos above 4 GB. Zernio converts WebP images to JPEG before upload.

### Images

| Property | Feed post | Story |
|----------|-----------|-------|
| Max images | 10 | 1 |
| Formats | JPEG, PNG, GIF (WebP converted) | JPEG, PNG |
| Max file size | 4 MB | 4 MB |
| Recommended | 1200 x 630 px | 1080 x 1920 px |

### Videos

| Property | Feed video | Reel | Story |
|----------|------------|------|-------|
| Max videos | 1 | 1 | 1 |
| Formats | MP4, MOV | MP4, MOV | MP4, MOV |
| Max file size | 4 GB | 4 GB | 4 GB |
| Max duration | 240 minutes | 60 seconds | 120 seconds |
| Min duration | 1 second | 3 seconds | 1 second |
| Recommended resolution | 1280 x 720 px minimum | 9:16 vertical, required | 1080 x 1920 px |
| Frame rate | 30 fps recommended | 30 fps recommended | 30 fps |
| Codec | H.264 | H.264 | H.264 |

### Media URLs

A media URL must be publicly accessible over HTTPS with no authentication and no redirects. Cloud storage sharing links (Google Drive, Dropbox) may not work; use a direct download URL or upload through the [media endpoint](/guides/media-uploads).

## Analytics

Call `GET /v1/analytics?platform=facebook` ([Analytics API](/analytics/get-analytics)).

| Metric | Available |
|--------|-----------|
| Impressions | <Yes /> |
| Likes | <Yes /> |
| Comments | <Yes /> |
| Shares | <Yes /> |
| Clicks | <Yes /> |
| Views | <Yes /> |

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: analytics } = await zernio.analytics.getAnalytics({
  query: { platform: 'facebook', fromDate: '2026-08-01', toDate: '2026-08-31' }
});

console.log(analytics.posts);
```
</Tab>
<Tab value="Python">
```python
analytics = client.analytics.get_analytics(
    platform="facebook",
    from_date="2026-08-01",
    to_date="2026-08-31"
)

print(analytics["posts"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/analytics?platform=facebook&fromDate=2026-08-01&toDate=2026-08-31" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), one entry per post:

```json
{
  "posts": [
    {
      "_id": "65f1c0a9e2b5af0012ab34cd",
      "platform": "facebook",
      "status": "published",
      "publishedAt": "2026-08-14T10:00:05Z",
      "platformPostUrl": "https://www.facebook.com/123456789012345/posts/987654321",
      "analytics": {
        "impressions": 15420,
        "likes": 342,
        "comments": 28,
        "shares": 45,
        "clicks": 189,
        "views": 0,
        "engagementRate": 2.78
      }
    }
  ]
}
```

Three Facebook-only endpoints go deeper:

- [Page insights](/analytics/get-facebook-page-insights): page-level aggregates (media views, post engagements, video metrics, follower counts) over at most 89 days, defaulting to the last 30. Metric names follow the current Meta Graph API; the legacy `page_impressions`, `page_fans`, `page_fan_adds` and `page_fan_removes` metrics were deprecated by Meta on November 15, 2025 and are rejected. `followers_gained` and `followers_lost` come from Zernio's daily follower snapshotter.
- [Post reactions](/analytics/get-facebook-post-reactions): a count per reaction type for one post. The post analytics above report only the aggregate, as `likes`.
- [Post earnings](/analytics/get-facebook-post-earnings): lifetime monetization earnings for one post, read live from Meta. There is no date range, so never sum the totals across dates. A Page not enrolled in monetization returns `total: 0`.

## Inbox

Facebook supports DMs, comments and Page reviews.

### Direct messages

| Feature | Supported |
|---------|-----------|
| List conversations | <Yes /> |
| Fetch messages | <Yes /> |
| Send text messages | <Yes /> |
| Send attachments | <Yes /> (images, videos, audio, files) |
| Quick replies | <Yes /> (up to 13, Meta quick_replies) |
| Buttons | <Yes /> (up to 3, generic template) |
| Carousels | <Yes /> (generic template, up to 10 elements; `imageAspectRatio: "square"` for 1:1 card images, default 1.91:1) |
| React to a message | <Yes /> (any emoji) via [Add message reaction](/messages/add-message-reaction) and [Remove message reaction](/messages/remove-message-reaction) |
| Message tags | <Yes /> (4 types) |
| Archive and unarchive | <Yes /> |

Reactions a customer adds or removes arrive on the [`reaction.received`](/webhooks/inbox#reactionreceived) webhook. Pages connected before August 2026 need their webhook subscription refreshed before inbound reactions arrive; sending is unaffected.

To message outside the 24-hour window, send `messagingType: "MESSAGE_TAG"` with `messageTag` set to `CONFIRMED_EVENT_UPDATE`, `POST_PURCHASE_UPDATE`, `ACCOUNT_UPDATE` or `HUMAN_AGENT`.

### Persistent menu

The persistent menu shown in Messenger conversations takes at most 3 top-level items and 5 nested items. Manage it with `GET`, `PUT` and `DELETE /v1/accounts/{accountId}/messenger-menu` ([Account settings](/account-settings/get-messenger-menu)).

### Comments

| Feature | Supported |
|---------|-----------|
| List comments on posts | <Yes /> |
| Reply to comments | <Yes /> |
| Delete comments | <Yes /> |
| Like comments | <Yes /> |
| Hide and unhide comments | <Yes /> |
| Send a private reply (DM after a comment) | <Yes /> (text plus up to 13 quick replies or 1 to 3 inline buttons, 7-day window, one per comment) |

#### Comment-to-DM automations

Keyword-triggered auto-DMs, created with [Create comment automation](/comment-automations/create-comment-automation). A `template` sends a product card instead of the plain `dmMessage`: an image, a title, a subtitle for the description or price, and up to 3 `url` or `postback` buttons, with up to 10 elements rendering as a carousel. Phone buttons are not supported inside a card, and the card and `dmMessage` plus `buttons` are mutually exclusive. `imageAspectRatio: "square"` renders the card images at 1:1 instead of Messenger's default 1.91:1, and it is Facebook only: Instagram rejects it with a `400`. The [Instagram page](/platforms/instagram#comment-to-dm-automations) has the full field shape; `audience` rules are Instagram only.

### Reviews

| Feature | Supported |
|---------|-----------|
| List reviews | <Yes /> |
| Reply to reviews | <Yes /> |

### Webhooks

Messenger emits every message lifecycle event except unsend:

| Event | When it fires |
|-------|---------------|
| `message.received` | New incoming DM |
| `message.sent` | Outgoing DM is sent |
| `message.edited` | The sender edits a sent message (up to 5 edits per Meta) |
| `message.delivered` | An outgoing DM is delivered |
| `message.read` | The recipient reads an outgoing DM |

Messenger does not expose an incoming-message unsend, so `message.deleted` never fires for Facebook.

Messages are stored locally: live messages arrive through webhooks, and on connect Zernio replays the DM history the account already has on Meta. The [webhooks page](/webhooks) has the payloads.

The replay covers conversations that began before the account was connected, up to 500 conversations per account and the newest 500 messages per conversation. It runs in the background, so it can take a while to appear, and it fires no webhooks; read it from [List inbox conversations](/messages/list-inbox-conversations). Replayed messages arrive already read, so they never affect unread counts.

## What you cannot do

Facebook's API does not expose:

- Posting to personal profiles (Pages only)
- Events
- Posting to Groups (deprecated by Facebook)
- Going live (the separate Facebook Live API)
- Interactive Story stickers
- Demographic targeting (age, gender, interests) for organic posts; country-level [geo-restriction](#geo-restriction) is supported

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| "Photos should be smaller than 4MB and saved as JPG or PNG." | The image is above Facebook's real size limit or in an unsupported format | Reduce it to under 4 MB as JPEG or PNG. |
| "Missing or invalid image file" | Facebook could not process the image: corrupt, wrong format or an inaccessible URL | Open the URL in an incognito window. Use JPEG or PNG under 4 MB. |
| "Unable to fetch video file from URL." | Facebook's servers could not download the video | Use a direct, public URL, not a cloud storage sharing link. |
| "Facebook tokens expired. Please reconnect." | The OAuth token expired; Facebook tokens are short-lived | Reconnect the account. Subscribe to the `account.disconnected` webhook to catch this early. |
| "Confirm your identity before you can publish as this Page." | Facebook triggered a security check on the Page | Log into Facebook, open the Page and complete identity verification. |
| "Publishing failed due to max retries reached" | All 3 retry attempts failed | Usually temporary. Retry, or wait and try again. |

A `publishNow: true` post that Facebook rejects returns `207` with `post.status: "failed"` and the message in `platforms[].errorMessage`:

```json
{
  "message": "Post created but publishing failed",
  "error": "All platforms failed",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "failed",
    "platforms": [
      {
        "platform": "facebook",
        "status": "failed",
        "errorMessage": "Facebook needs you to confirm your identity before you can publish as this Page. Open the Facebook mobile app, go to this Page, and complete the identity confirmation, then try again.",
        "errorCategory": "platform_rejected",
        "errorSource": "platform"
      }
    ]
  }
}
```

`207` is a 2xx status, so `fetch(...).ok` is `true`; branch on the status code and on `post.status`. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts): the OAuth flow and the Page selection step.
- [Create post](/posts/create-post): every field of the request.
- [Media uploads](/guides/media-uploads): upload images and videos instead of hosting them.
- [Messages](/messages/list-inbox-conversations), [Comments](/comments/list-inbox-comments) and [Reviews](/reviews/list-inbox-reviews): the inbox API.
- [Meta Ads](/platforms/meta-ads): run ads from the same connection.
- [Pricing](/pricing): what analytics, the inbox and outbound messages cost.

---
