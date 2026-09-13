# TikTok

Publish videos and photo carousels to TikTok with the Zernio API, with creator privacy levels, duet and stitch controls, custom covers, AI disclosure and Creator Inbox drafts.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Publish videos and photo carousels to TikTok with `POST /v1/posts` and `platform: "tiktok"`. Settings go in a top-level `tiktokSettings` object. The same account also serves post and account analytics, DMs and comments.

## Quick reference

| Property | Value |
|----------|-------|
| Character limit | 2,200 (video caption), 4,000 (photo `description`) |
| Photo title | 90 characters (auto-truncated, hashtags stripped) |
| Photos per post | 35 (carousel) |
| Videos per post | 1 |
| Photo formats | JPEG, PNG, WebP |
| Photo max size | 20 MB |
| Video formats | MP4, MOV, WebM |
| Video max size | 4 GB |
| Video duration | 3 seconds to 10 minutes |
| Post types | Video, Photo carousel |
| Scheduling | Yes |
| Video privacy | Public only on TikTok Business app connections; the creator's allowed levels on developer app connections |
| Inbox (comments) | Yes (accounts connected through TikTok's Business app) |
| Inbox (DMs) | Yes (TikTok Business Accounts, replies only) |
| Analytics | Limited |

## Before you start

TikTok requires media on every post; there are no text-only posts. Every post also needs `content_preview_confirmed: true` and `express_consent_given: true` (a legal requirement from TikTok), and `privacy_level` must be one of the values TikTok reports for that creator, so read [creator info](#creator-info) before you build the request; on an account connected through TikTok's Business app, a video direct post is public and any other level is rejected ([connection lanes](#connection-lanes)). Both Zernio and TikTok cap how many posts an account can create through the API per rolling 24 hours ([daily posting caps](#daily-posting-caps)). Content moderation is stricter through the API than in the app.

<Callout type="warn">
A post from an account connected through TikTok's developer app can fail with "TikTok direct posting is at capacity right now" although nothing is wrong with the account or the media. TikTok caps how many distinct developer app accounts can direct-post through the Zernio app per rolling 24 hours, shared by every Zernio customer on that lane. Accounts connected through TikTok's Business app, which is every new connection, never hit it. See [Direct posting at capacity](#direct-posting-at-capacity).
</Callout>

## Connect

Call `GET /v1/connect/tiktok` with `profileId` on [Get OAuth connect URL](/connect/get-connect-url). The [connecting accounts guide](/guides/connecting-accounts) covers the OAuth flow and [scopes](/guides/connecting-accounts#scopes) in general; [Account health](/accounts/get-all-accounts-health) reports what a connected account can do with the scopes the user granted.

### Connection lanes

TikTok connections now go through the TikTok for Business app. An account connected earlier stays on TikTok's developer app until its owner reconnects it, and the lane is decided when the account is connected. Which lane an account is on changes three things:

- **Capacity**: developer app accounts share the app-wide [direct posting cap](#direct-posting-at-capacity), Business app accounts are exempt from it.
- **Video privacy**: Business app accounts publish videos as public only, and [Creator Inbox drafts](#draft-delivery) are the only route to a non-public video. Photo posts keep every level the creator allows on both lanes.
- **Inbox**: DMs and comment management need a Business app connection ([Inbox](#inbox)).

Reconnecting an account is what moves it to the Business app:

- One TikTok account per profile: connecting on a profile that already has a TikTok account replaces it.
- Reconnecting the same account keeps it and its history.
- Authorizing a different account takes the slot and permanently deletes the previous account's analytics, inbox and DM history.
- Zernio tells “same vs different” by the last connected `@handle`; if the handle was renamed on TikTok since the last connect, it may be treated as a different account.

> **Note:** If the authorization leaves out a permission the already-connected account needs, nothing changes and the flow returns `missing_tiktok_permissions`. Connect again and accept every permission on TikTok’s consent screen.

### OAuth scopes

| Scope | What it enables |
|-------|-----------------|
| `user.info.basic` | Account identity (username, avatar) for the connected account |
| `user.info.profile` | Extended profile info (bio, verified status, profile link) |
| `user.info.stats` | Follower, likes and video counts for account analytics |
| `video.publish` | Direct-post publishing of videos and photo posts |
| `video.upload` | Upload to the creator's TikTok inbox as a draft |
| `video.list` | Read the account's videos for post analytics |

## Publish

A plain post becomes a video post when the media is one video and a photo carousel when it is images. Send `tiktokSettings` at the top level of the request body, where it applies to every TikTok entry in `platforms`; TikTok requires it on every post. Both `snake_case` and `camelCase` field names are accepted.

### Video post

A single video between 3 seconds and 10 minutes. Vertical 9:16 is the only aspect ratio that works well on TikTok.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'New cooking tutorial #recipe #foodtok',
    mediaItems: [
      { type: 'video', url: 'https://cdn.example.com/cooking-tutorial.mp4' }
    ],
    platforms: [
      { platform: 'tiktok', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
    ],
    tiktokSettings: {
      privacy_level: 'PUBLIC_TO_EVERYONE',
      allow_comment: true,
      allow_duet: true,
      allow_stitch: true,
      content_preview_confirmed: true,
      express_consent_given: true
    },
    publishNow: true
  }
});

console.log(published.post.platforms[0].status);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

published = client.posts.create_post(
    content="New cooking tutorial #recipe #foodtok",
    media_items=[
        {"type": "video", "url": "https://cdn.example.com/cooking-tutorial.mp4"}
    ],
    platforms=[
        {"platform": "tiktok", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
    ],
    tiktok_settings={
        "privacy_level": "PUBLIC_TO_EVERYONE",
        "allow_comment": True,
        "allow_duet": True,
        "allow_stitch": True,
        "content_preview_confirmed": True,
        "express_consent_given": True
    },
    publish_now=True
)

print(published["post"]["platforms"][0]["status"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "New cooking tutorial #recipe #foodtok",
    "mediaItems": [
      {"type": "video", "url": "https://cdn.example.com/cooking-tutorial.mp4"}
    ],
    "platforms": [
      {"platform": "tiktok", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
    ],
    "tiktokSettings": {
      "privacy_level": "PUBLIC_TO_EVERYONE",
      "allow_comment": true,
      "allow_duet": true,
      "allow_stitch": true,
      "content_preview_confirmed": true,
      "express_consent_given": true
    },
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
        "platform": "tiktok",
        "status": "published",
        "platformPostUrl": null
      }
    ]
  }
}
```

TikTok exposes the video id some minutes after publishing, so `platformPostUrl` can be empty at first; the [`post.tiktok.url_resolved`](/webhooks/posts#posttiktokurl_resolved) webhook delivers it once it exists. Every sample below changes only `mediaItems` or `tiktokSettings` of this request.

### Video cover

`video_cover_timestamp_ms` picks the frame used as the cover, and `video_cover_image_url` replaces it with a custom image (JPG, PNG or WebP, at most 20 MB). Any image URL Zernio can download works.

- For most accounts, Zernio downloads the image, rehosts it, stitches it in as a single frame at the start of the video, and TikTok uses that as the cover.
- Accounts connected through the TikTok for Business app skip the stitching: Zernio rehosts the image and hands TikTok that URL as the cover. If `video_cover_image_url` is omitted, those accounts fall back to `video_cover_timestamp_ms`.

When both are set, the image wins:

```json
"tiktokSettings": {
  "privacy_level": "PUBLIC_TO_EVERYONE",
  "allow_comment": true,
  "allow_duet": true,
  "allow_stitch": true,
  "video_cover_image_url": "https://cdn.example.com/teaser-cover.jpg",
  "content_preview_confirmed": true,
  "express_consent_given": true
}
```

### Photo carousel

Up to 35 images. `content` becomes the photo title (90 characters, hashtags and URLs stripped), so put the full caption in `description`, which takes up to 4,000 characters. Photos and videos cannot be mixed in one post, and `allow_duet` and `allow_stitch` do not apply:

```json
"mediaItems": [
  { "type": "image", "url": "https://cdn.example.com/photo1.jpg" },
  { "type": "image", "url": "https://cdn.example.com/photo2.jpg" },
  { "type": "image", "url": "https://cdn.example.com/photo3.jpg" }
],
"tiktokSettings": {
  "privacy_level": "PUBLIC_TO_EVERYONE",
  "allow_comment": true,
  "media_type": "photo",
  "photo_cover_index": 0,
  "description": "Full trip recap from our weekend across the coast. #travel #roadtrip #adventure",
  "auto_add_music": true,
  "content_preview_confirmed": true,
  "express_consent_given": true
}
```

### Creator info

Call `GET /v1/accounts/{accountId}/tiktok/creator-info` ([Get TikTok creator info](/accounts/get-tiktok-creator-info)) before you build a post. It returns the creator's allowed `privacyLevels`, `postingLimits` with the interaction toggles, and the `commercialContentTypes` the account can disclose. A non-TikTok `accountId` returns `400`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: info } = await zernio.accounts.getTikTokCreatorInfo({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  query: { mediaType: 'video' }
});

console.log(info.privacyLevels);
```
</Tab>
<Tab value="Python">
```python
info = client.accounts.get_tik_tok_creator_info(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    media_type="video"
)

print(info["privacyLevels"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/tiktok/creator-info?mediaType=video" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "creator": { "nickname": "myaccount", "isVerified": false, "canPostMore": true },
  "privacyLevels": [
    { "value": "PUBLIC_TO_EVERYONE", "label": "Public To Everyone" },
    { "value": "MUTUAL_FOLLOW_FRIENDS", "label": "Mutual Follow Friends" },
    { "value": "SELF_ONLY", "label": "Self Only" }
  ],
  "postingLimits": {
    "maxVideoDurationSec": 600,
    "interactionSettings": {
      "allow_comment": { "enabled": true, "required": true, "default": false, "label": "Allow Comment" },
      "allow_duet": { "enabled": true, "required": true, "default": false, "label": "Allow Duet" },
      "allow_stitch": { "enabled": false, "required": true, "default": false, "label": "Allow Stitch" }
    }
  },
  "commercialContentTypes": [
    { "value": "none", "label": "No Commercial Content" },
    { "value": "brand_organic", "label": "Your Brand", "requires": ["is_brand_organic_post"] }
  ]
}
```

`enabled: false` on a toggle means the creator turned that interaction off in the TikTok app; `required` is always `true` because TikTok forbids defaulting these fields, and `default` is only a value for your composer to render. With `mediaType=photo`, `allow_duet` and `allow_stitch` are `null`.

### Commercial content

`commercialContentType` discloses a commercial post: `"brand_organic"` (the creator's own brand) or `"brand_content"` (a paid partnership). Each value implies its boolean flag, so `isBrandOrganicPost` and `brandPartnerPromote` are only needed to disclose both at once or to override the implied value. Branded content cannot be posted with `privacy_level: "SELF_ONLY"`.

### Daily posting caps

On an account connected through TikTok's Business app, Zernio allows 15 videos and 15 photo posts per account per rolling 24 hours. The 2 counts are separate, so 15 videos leave the photo allowance untouched. A post beyond the cap is not failed: Zernio holds it and publishes it by itself once the rolling window frees a slot. The window trails the posts that fill it, so there is no clock time at which it resets. Accounts on TikTok's developer app keep Zernio's generic per-platform daily cap instead ([posting velocity limits](/guides/rate-limits#posting-velocity-limits)).

TikTok enforces a limit of its own on how many posts an account creates through the API in 24 hours. A post TikTok rejects for it fails with "You have created too many posts in the last 24 hours via the API"; wait for TikTok's own window to roll, or post in the TikTok app.

### Direct posting at capacity

A TikTok post from an account connected through TikTok's developer app can fail with this message even though nothing is wrong with the account or the media:

```
TikTok direct posting is at capacity right now. Use tiktokSettings.draft: true to deliver via Creator Inbox, or try again in a few hours as capacity frees up.
```

TikTok caps how many distinct accounts can direct-post through one developer app in a rolling 24-hour window (TikTok's own error for it is `reached_active_user_cap`, which Zernio surfaces as `Daily active user quota reached`). Every Zernio customer still on that lane publishes through the same TikTok app, so the budget is app-wide: it is not tied to your account, your API key, or how many posts you have made. An account that has already direct-posted inside the window keeps posting; the cap only blocks accounts that would need a new slot.

Accounts connected through TikTok's Business app do not draw on that budget and never see this error. Reconnecting an account moves it to the Business app, with the caveat in [connection lanes](#connection-lanes).

The window is rolling, so slots free up continuously as activity from 24 hours ago ages out. There is no midnight reset to wait for.

When the window is close to full, Zernio stops handing new developer app slots to accounts whose owner has never added a payment method. Teams with a card on file (or a paid plan) are not gated by Zernio at all, only by TikTok's hard cap, and adding a card moves a team to that group even if the bill stays $0 inside the free allowance. See [Pricing](/pricing).

The post is marked `failed` and is not retried automatically. Either:

- [Retry](/posts/retry-post) it a few hours later, or reschedule it.
- Send it as a draft instead: `tiktokSettings.draft: true` is exempt from the cap (next section).

To check before you create anything, send the same request with `dryRun: true`. It returns `200` with `canPublish` and one verdict per `tiktok` entry, persists no post and claims no slot, so you can repeat it freely. A Business app account comes back `canPublish: true` here whatever the developer app budget looks like, because the cap does not apply to it.

### Draft delivery

Set `tiktokSettings.draft: true` and Zernio uploads the media to the creator's TikTok inbox instead of publishing it. Videos go through TikTok's inbox upload endpoint; photo posts go through the content endpoint with `post_mode: MEDIA_UPLOAD`. Drafts need the `video.upload` scope (granted in the standard connect flow) and TikTok app version 31.8 or later on the creator's phone.

The creator gets an inbox notification in the TikTok app and finishes the post in TikTok's own editor: caption, cover, privacy, and the final Post tap all happen there. Nothing is public until the creator posts it.

Zernio marks the platform entry `published` as soon as TikTok accepts the upload, which is the moment the draft is handed over, not the moment it goes live. The entry carries `platformSpecificData.isDraft: true` and no `platformPostUrl`, and the `post.tiktok.url_resolved` webhook never fires for drafts, because Zernio never learns whether or when the creator posts it.

Limits that still apply:

- TikTok allows 5 pending drafts per account in any 24-hour period. A 6th fails with `TikTok allows only 5 pending drafts per account in any 24-hour period...` and the only fix is to finish or discard drafts in the TikTok app; waiting does not free the slot.
- TikTok's own per-account limit on posts created through the API in the last 24 hours (`You have created too many posts in the last 24 hours via the API`) and Zernio's own caps (25 posts per hour, plus the [daily posting caps](#daily-posting-caps)) count drafts and direct posts alike.
- Drafts are exempt from the [direct posting capacity](#direct-posting-at-capacity) gate.

## Platform fields

All fields go in `tiktokSettings` at the top level of the request. Names are shown in `snake_case`; `camelCase` is accepted too.

Zernio merges that object into each TikTok entry's `platformSpecificData.tiktokSettings`, and a key set there wins over the root-level one. Use the per-entry object when 2 TikTok accounts in the same request need different settings, for example one publishing directly and one with `draft: true`.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `privacy_level` | string | | Required. One of the creator's values from [creator info](#creator-info): `PUBLIC_TO_EVERYONE`, `MUTUAL_FOLLOW_FRIENDS`, `FOLLOWER_OF_CREATOR`, `SELF_ONLY`. Accounts connected through TikTok's Business app publish videos as public only: non-public values on video direct posts are rejected unless `draft: true` (photo posts keep every level). See [connection lanes](#connection-lanes). |
| `allow_comment` | boolean | | Required. Enable or disable comments on the post. |
| `allow_duet` | boolean | | Required for videos. Enable or disable duets. |
| `allow_stitch` | boolean | | Required for videos. Enable or disable stitches. |
| `content_preview_confirmed` | boolean | | Required, must be `true`. Legal requirement from TikTok. |
| `express_consent_given` | boolean | | Required, must be `true`. Legal requirement from TikTok. |
| `video_cover_timestamp_ms` | number | `1000` | Cover frame position in milliseconds. Ignored when `video_cover_image_url` is set. |
| `video_cover_image_url` | string (URL) | | Custom cover image (JPG, PNG or WebP, at most 20 MB). Overrides `video_cover_timestamp_ms`. |
| `media_type` | `"video"` \| `"photo"` | (from media) | Set to `"photo"` for photo carousels. |
| `photo_cover_index` | number | `0` | Which image is the cover (0-based). |
| `description` | string | | Long-form caption for photo carousels, up to 4,000 characters. |
| `auto_add_music` | boolean | | Let TikTok add recommended music. Photo carousels only. |
| `video_made_with_ai` | boolean | | AI-generated content disclosure. Accounts connected through TikTok's Business app carry the disclosure on video posts only: direct photo posts reject `true`; use `draft: true` and set the disclosure in the TikTok app. |
| `draft` | boolean | `false` | Send to the Creator Inbox instead of publishing. See [Draft delivery](#draft-delivery). |
| `commercialContentType` | `"none"` \| `"brand_organic"` \| `"brand_content"` | | Commercial content disclosure. See [Commercial content](#commercial-content). |
| `isBrandOrganicPost` | boolean | | Implied by `commercialContentType: "brand_organic"`; set it only to disclose both types at once. |
| `brandPartnerPromote` | boolean | | Implied by `commercialContentType: "brand_content"`; set it only to disclose both types at once. |

## Media requirements

Videos are uploaded in chunks of 5 to 64 MB. A post holds either one video or up to 35 photos, never both.

### Images

| Property | Requirement |
|----------|-------------|
| Max photos | 35 per carousel |
| Formats | JPEG, PNG, WebP |
| Max file size | 20 MB per image |
| Aspect ratio | 9:16 recommended |
| Resolution | Downscaled to fit inside 1080 x 1920 px, aspect ratio kept; an image already inside those bounds is sent untouched |

### Videos

| Property | Requirement |
|----------|-------------|
| Max videos | 1 per post |
| Formats | MP4, MOV, WebM |
| Max file size | 4 GB |
| Max duration | 10 minutes |
| Min duration | 3 seconds |
| Aspect ratio | 9:16 vertical (the only format that works well) |
| Resolution | 1080 x 1920 px recommended |
| Codec | H.264 |
| Frame rate | 30 fps recommended |

### Media URLs

Google Drive, Dropbox, OneDrive, SharePoint and iCloud links return an HTML page, not the file, so TikTok cannot download from them. A media URL must be public with no authentication, return the media bytes with the correct `Content-Type`, not redirect to an HTML page, and sit on a fast host; test it in an incognito window. Or upload through the [media endpoint](/guides/media-uploads).

## Analytics

Call `GET /v1/analytics?platform=tiktok` ([Analytics API](/analytics/get-analytics)).

| Metric | Available |
|--------|-----------|
| Likes | <Yes /> |
| Comments | <Yes /> |
| Shares | <Yes /> |
| Views | <Yes /> |
| Profile views | <Yes /> (Business app connections) |
| Completion rate | <Yes /> (Business app connections) |

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: analytics } = await zernio.analytics.getAnalytics({
  query: { platform: 'tiktok', fromDate: '2026-08-01', toDate: '2026-08-31' }
});

console.log(analytics.posts);
```
</Tab>
<Tab value="Python">
```python
analytics = client.analytics.get_analytics(
    platform="tiktok",
    from_date="2026-08-01",
    to_date="2026-08-31"
)

print(analytics["posts"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/analytics?platform=tiktok&fromDate=2026-08-01&toDate=2026-08-31" \
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
      "platform": "tiktok",
      "status": "published",
      "publishedAt": "2026-08-14T10:00:05Z",
      "platformPostUrl": "https://www.tiktok.com/@myaccount/video/7300000000000000000",
      "analytics": {
        "likes": 342,
        "comments": 28,
        "shares": 45,
        "views": 15420
      }
    }
  ]
}
```

[Account insights](/analytics/get-tiktok-account-insights) adds account-level counters (`follower_count`, `following_count`, `likes_count`, `video_count`) plus `followers_gained` and `followers_lost` deltas. Live values come from the `user.info.stats` scope; the historical series is joined from Zernio's daily snapshotter.

Accounts connected through TikTok's Business app also report `profileViews`, the profile views a post brought in, and `completionRate`, the share of viewers who watched to the end as a number from 0 to 1. Both land 24 to 48 hours after publishing, and TikTok reports completion only for posts active in the last 7 days. The remaining TikTok Studio metrics (account-level impressions and reach, per-video watch time and average watch time, impression sources such as For You, Following, Hashtag and Search) are not available on any public TikTok API. TikTok's Research API does not expose them either and is restricted to non-commercial academic use under TikTok's eligibility policy.

## Inbox

TikTok supports DMs and comments for accounts connected through TikTok's Business app, which every new TikTok connection uses. An account still connected through TikTok's developer app gets a `400` with code `PLATFORM_LIMITATION` for both.

### Direct messages

TikTok decides which accounts can use DMs:

- The account is a TikTok Business Account. TikTok refuses personal accounts.
- The account's sign-up region is outside the EEA, Switzerland and the UK.
- The account accepts DMs, from everyone or by accepting message requests. Otherwise TikTok sends no webhook for incoming messages.

You can only reply: TikTok does not let a business start a conversation. After the user's last message, TikTok accepts up to 10 messages within 48 hours, and a send outside that window fails with TikTok's error.

| Feature | Supported |
|---------|-----------|
| List conversations | <Yes /> |
| Fetch messages | <Yes /> |
| Send text messages | <Yes /> (up to 6,000 characters) |
| Send an image | <Yes /> (one JPG or PNG, up to 3 MB) |
| Start a conversation | <No /> |

Reply with [Send message](/messages/send-inbox-message) (`POST /v1/inbox/conversations/{conversationId}/messages`). A message is text or one image, never both: a request with `message` and an attachment returns `400` with code `PLATFORM_LIMITATION`.

### Comments

Comment management does not need a Business Account.

- Read comments: `GET /v1/inbox/comments/{postId}` (TikTok video id). Each top-level comment includes up to three inline replies; pass `commentId` to page the full reply list for one comment.
- Post a comment, reply, and like, hide or unhide any comment. Delete works on your own comments and replies.
- Pin and unpin a top-level comment, as shown below.

### Pin and unpin comments

Pin a top-level comment:

<Tabs items={['curl', 'JavaScript', 'Python']}>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/inbox/comments/{postId}/{commentId}/pin \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d"
  }'
```
</Tab>
<Tab value="JavaScript">
```javascript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data } = await zernio.request({
  method: 'POST',
  path: `/v1/inbox/comments/${postId}/${commentId}/pin`,
  body: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

console.log(data);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

data = client.request(
    method="POST",
    path=f"/v1/inbox/comments/{post_id}/{comment_id}/pin",
    body={"accountId": "66b2e19d8c3f5a7e9d0b1c2d"},
)

print(data)
```
</Tab>
</Tabs>

Unpin a previously pinned comment:

<Tabs items={['curl', 'JavaScript', 'Python']}>
<Tab value="curl">
```bash
curl -X DELETE "https://zernio.com/api/v1/inbox/comments/{postId}/{commentId}/pin?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
<Tab value="JavaScript">
```javascript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data } = await zernio.request({
  method: 'DELETE',
  path: `/v1/inbox/comments/${postId}/${commentId}/pin`,
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

console.log(data);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

data = client.request(
    method="DELETE",
    path=f"/v1/inbox/comments/{post_id}/{comment_id}/pin",
    query={"accountId": "66b2e19d8c3f5a7e9d0b1c2d"},
)

print(data)
```
</Tab>
</Tabs>

### Webhooks

TikTok supports `comment.received`, `message.received` and `message.sent` webhooks. Comment events include only the author id (no username, picture, or owner flag).

## What you cannot do

TikTok's API does not expose:

- TikTok's sound and music library (except `auto_add_music` for photo carousels)
- Creating duets or stitches
- Going live
- Effects or filters
- Editing posts after publishing
- For You Page analytics
- Playlists
- Starting a DM conversation (you can only reply)
- Text-only posts (media required)

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| "You have created too many posts in the last 24 hours via the API." | TikTok's daily API posting limit for the account | Wait for the rolling 24-hour window or post in the TikTok app. |
| "TikTok direct posting is at capacity right now." / "Daily active user quota reached" | TikTok's app-wide cap on distinct posting accounts per rolling 24 hours, on developer app connections only | Retry a few hours later, send as a draft, or reconnect the account to TikTok's Business app, which is exempt. See [Direct posting at capacity](#direct-posting-at-capacity). |
| "Publishing failed during platform API call (timeout waiting for platform response)" | TikTok took too long to process the upload | Normal for large videos. Check the post status after a few minutes. |
| "Selected privacy level 'X' is not available for this creator. Available options: ..." | `privacy_level` is not in the creator's allowed values | Read [creator info](#creator-info) and use one of its `privacyLevels`. |
| "TikTok flagged this post as potentially risky (spam_risk)" | Content moderation flagged the post | Review the content. TikTok's API moderation is stricter than the app. |
| "Duplicate content detected." | The same content was posted recently | Change the caption or media before retrying. |
| "TikTok video upload failed: Your video URL returned an error (download failed)" | TikTok could not download the video from the URL | Use a direct download URL, not a cloud storage sharing page. |
| "Missing required TikTok permissions. Please reconnect with all required scopes." | The OAuth token lacks a required scope | Reconnect the TikTok account and grant every scope. An authorization that leaves one out returns `missing_tiktok_permissions` and changes nothing ([connection lanes](#connection-lanes)). |

A `publishNow: true` post that TikTok rejects returns `207` with `post.status: "failed"` and the message in `platforms[].errorMessage`:

```json
{
  "message": "Post created but publishing failed",
  "error": "All platforms failed",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "failed",
    "platforms": [
      {
        "platform": "tiktok",
        "status": "failed",
        "errorMessage": "TikTok direct posting is at capacity right now. Use tiktokSettings.draft: true to deliver via Creator Inbox, or try again in a few hours as capacity frees up."
      }
    ]
  }
}
```

`207` is a 2xx status, so `fetch(...).ok` is `true`; branch on the status code and on `post.status`. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts): the OAuth flow.
- [Create post](/posts/create-post): every field of the request.
- [Media uploads](/guides/media-uploads): upload videos and photos instead of hosting them.
- [Analytics](/analytics/get-analytics) and [Account insights](/analytics/get-tiktok-account-insights): post and account metrics.
- [Rate limits](/guides/rate-limits#tiktok-caps-direct-posting-per-application): how Zernio handles TikTok's quotas.
- [Pricing](/pricing): what a connected account and analytics cost.

---
