# Bluesky

Publish text, image, video and thread posts to Bluesky with the Zernio API, connected with an app password instead of OAuth.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Publish text, image, video and thread posts to Bluesky with `POST /v1/posts` and `platform: "bluesky"`. Bluesky connects with an app password instead of OAuth, and the same account also serves DMs and comments.

## Quick reference

| Property | Value |
|----------|-------|
| Character limit | 300 (hard limit) |
| Images per post | 4 |
| Videos per post | 1 |
| Image formats | JPEG, PNG, WebP, GIF |
| Image max size | 1 MB (auto-compressed) |
| Video format | MP4 only |
| Video max size | 50 MB |
| Video max duration | 60 seconds |
| Post types | Text, Image, Video, Thread |
| Scheduling | Yes |
| Inbox (DMs) | Yes (text only) |
| Inbox (comments) | Yes |
| Analytics | Limited (likes, comments, reposts) |

## Before you start

Bluesky requires an [app password](https://bsky.app/settings/app-passwords) for the account, created in Bluesky's settings, and a handle. Custom domain handles work (`brand.com` as well as `brand.bsky.social`).

The 300-character count includes text, URLs and mentions. When you cross-post from a platform with a longer limit, set `customContent` on the Bluesky entry with a shorter version, or the Bluesky entry fails.

## Connect

Call `POST /v1/connect/bluesky/credentials` with the handle, the app password and `state` on [Connect Bluesky account](/connect/connect-bluesky-credentials). `state` is `{userId}-{profileId}`: `userId` is `currentUserId` from `GET /v1/users`, `profileId` from `GET /v1/profiles`. The full sample is under [Bluesky in the connecting accounts guide](/guides/connecting-accounts#bluesky).

```bash
curl -X POST https://zernio.com/api/v1/connect/bluesky/credentials \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "identifier": "yourhandle.bsky.social",
    "appPassword": "xxxx-xxxx-xxxx-xxxx",
    "state": "66a0e8b1c2d3e4f5a6b7c8d9-66a1f0c2a4b9d3e8f1a2b3c4"
  }'
```

Response (`200`):

```json
{
  "message": "Bluesky connected successfully",
  "account": {
    "platform": "bluesky",
    "username": "yourhandle.bsky.social",
    "displayName": "Your Name",
    "isActive": true
  }
}
```

There are no OAuth scopes. The app password grants API access to the account over the AT Protocol without exposing the main password, and the user can revoke it at any time from Bluesky's settings.

## Publish

A plain post becomes a text post. Media in `mediaItems` makes an image or video post, and `threadItems` in `platformSpecificData` on the Bluesky entry makes a thread.

### Text post

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Shipped a new feature today. Notes at https://example.com/changelog',
    platforms: [
      { platform: 'bluesky', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
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
    content="Shipped a new feature today. Notes at https://example.com/changelog",
    platforms=[
        {"platform": "bluesky", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
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
    "content": "Shipped a new feature today. Notes at https://example.com/changelog",
    "platforms": [
      {"platform": "bluesky", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
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
        "platform": "bluesky",
        "status": "published",
        "platformPostUrl": "https://bsky.app/profile/yourhandle.bsky.social/post/..."
      }
    ]
  }
}
```

Every sample below changes only the `mediaItems` or the `platforms` entry of this request.

Zernio converts the text to AT Protocol facets on its own: `@handle.bsky.social` becomes a profile link, `#hashtag` a clickable hashtag, and a URL a clickable link with a preview card. Bluesky builds the link card from the target page's Open Graph tags, so put the URL at the end of the post and check that the page has them.

### Image post

Up to 4 images. Alt text goes in `altText` on the media item:

```json
"mediaItems": [
  { "type": "image", "url": "https://cdn.example.com/photo1.jpg", "altText": "The launch screen" },
  { "type": "image", "url": "https://cdn.example.com/photo2.jpg" },
  { "type": "image", "url": "https://cdn.example.com/photo3.jpg" },
  { "type": "image", "url": "https://cdn.example.com/photo4.jpg" }
]
```

### Video post

One MP4 per post, at most 50 MB and 60 seconds:

```json
"mediaItems": [
  { "type": "video", "url": "https://cdn.example.com/demo.mp4" }
]
```

### Thread

`threadItems` publishes a chain of posts. The first item is the root post and every later item replies to the one before it, each with its own text and media and its own 300-character limit. When `threadItems` is set, the top-level `content` is used for display and search only and is not published, so the first post goes in `threadItems[0]`:

```json
{
  "platform": "bluesky",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "threadItems": [
      {
        "content": "A thread about building APIs",
        "mediaItems": [{ "type": "image", "url": "https://cdn.example.com/api.jpg" }]
      },
      { "content": "First, design your endpoints around resources, not actions." },
      { "content": "Second, version your API from day one." },
      { "content": "Third, document everything. Your future self will thank you." }
    ]
  }
}
```

### Post language

`langs` tags the post with 1 to 3 BCP-47 codes. Bluesky feed generators filter on this field, so a post without it never appears in language-scoped feeds. It is set at creation only, because Bluesky has no post editing, and every item of a thread carries the same `langs`:

```json
{
  "platform": "bluesky",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "langs": ["pt", "en"] }
}
```

When a post has no `langs`, Zernio applies the account's default at publish time. Read it with [Get Bluesky account settings](/accounts/get-bluesky-settings) and set it with [Update Bluesky account settings](/accounts/update-bluesky-settings); a per-post `langs` always wins, and the default applies to posts published after the change:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
await zernio.accounts.updateBlueskySettings({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: { defaultLangs: ['pt'] }
});
```
</Tab>
<Tab value="Python">
```python
client.accounts.update_bluesky_settings(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    default_langs=["pt"]
)
```
</Tab>
<Tab value="curl">
```bash
curl -X PATCH https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/bluesky-settings \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "defaultLangs": ["pt"] }'
```
</Tab>
</Tabs>

Response (`200`), the updated settings. `GET /v1/accounts/{accountId}/bluesky-settings` reads the same shape back:

```json
{
  "defaultLangs": ["pt"]
}
```

Send `"defaultLangs": null` to clear the default.

## Platform fields

All fields go in `platformSpecificData` on the Bluesky entry.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `langs` | Array\<string\> | account default | 1 to 3 BCP-47 codes (`pt`, `en-US`) written to the post's `langs` field. Absent when neither the post nor the account sets one. |
| `threadItems` | Array\<\{content, mediaItems?\}\> | | The whole thread, root post first. Top-level `content` is then display and search only. |

## Media requirements

Images above these limits are compressed, and quality can drop. Videos outside them are rejected.

### Images

| Property | Requirement |
|----------|-------------|
| Max images | 4 per post |
| Formats | JPEG, PNG, WebP, GIF |
| Max file size | 1 MB per image |
| Max dimensions | 2000 x 2000 px |
| Recommended | 1200 x 675 px (16:9) |

| Orientation | Ratio | Dimensions |
|-------------|-------|------------|
| Landscape | 16:9 | 1200 x 675 px |
| Square | 1:1 | 1000 x 1000 px |
| Portrait | 4:5 | 800 x 1000 px |

### Videos

| Property | Requirement |
|----------|-------------|
| Max videos | 1 per post |
| Format | MP4 |
| Max file size | 50 MB |
| Max duration | 60 seconds |
| Max dimensions | 1920 x 1080 px |
| Recommended | 1280 x 720 px, 16:9 or 1:1, 30 fps, H.264 video, AAC audio |

Hosting rules for media URLs are in [Media uploads](/guides/media-uploads).

## Analytics

Call `GET /v1/analytics?platform=bluesky` ([Analytics API](/analytics/get-analytics)). Bluesky exposes engagement counts only, so analytics are limited to these metrics:

| Metric | Available |
|--------|-----------|
| Likes | <Yes /> |
| Comments | <Yes /> |
| Shares (reposts) | <Yes /> |

Bluesky's API does not provide impressions, reach, clicks or view counts.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: analytics } = await zernio.analytics.getAnalytics({
  query: { platform: 'bluesky', fromDate: '2026-08-01', toDate: '2026-08-31' }
});

console.log(analytics.posts);
```
</Tab>
<Tab value="Python">
```python
analytics = client.analytics.get_analytics(
    platform="bluesky",
    from_date="2026-08-01",
    to_date="2026-08-31"
)

print(analytics["posts"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/analytics?platform=bluesky&fromDate=2026-08-01&toDate=2026-08-31" \
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
      "platform": "bluesky",
      "status": "published",
      "publishedAt": "2026-08-14T10:00:05Z",
      "platformPostUrl": "https://bsky.app/profile/yourhandle.bsky.social/post/3kabc123",
      "analytics": {
        "likes": 42,
        "comments": 6,
        "shares": 11
      }
    }
  ]
}
```

## Inbox

Bluesky supports DMs and comments. DMs are text only, because Bluesky's Chat API does not support media.

### Direct messages

| Feature | Supported |
|---------|-----------|
| List conversations | <Yes /> |
| Fetch messages | <Yes /> |
| Send text messages | <Yes /> |
| Send attachments | <No /> (API limitation) |
| Archive and unarchive | <Yes /> |

### Comments

| Feature | Supported |
|---------|-----------|
| List comments on posts | <Yes /> |
| Reply to comments | <Yes /> |
| Delete comments | <Yes /> |
| Like comments | <Yes /> (requires `cid`) |
| Unlike comments | <Yes /> (requires `likeUri`) |

Liking a comment needs its content identifier: pass the `cid` from the comments endpoints in the body of [Like comment](/comments/like-inbox-comment). Store the `likeUri` the response returns, because [Unlike comment](/comments/unlike-inbox-comment) needs it.

[Messages](/messages/list-inbox-conversations) and [Comments](/comments/list-inbox-comments) document the inbox endpoints.

## What you cannot do

Bluesky's API does not expose:

- Lists and starter packs
- Custom feeds
- Pinning a post to the profile
- Content warnings and labels
- DM attachments
- Follower counts and profile analytics
- Editing a published post

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| "Bluesky posts cannot exceed 300 characters" | `content` is above the 300-character hard limit | Shorten it to 300 characters. Use `customContent` when cross-posting. |
| "Thread item N exceeds 300 characters" | One thread item is too long | Every item has its own 300-character limit. Split it into more items. |
| "Publishing failed due to max retries reached" | Every retry failed | Usually temporary. Retry the post. |
| App password invalid | The main account password was used, or the app password was revoked | Use an app password (`xxxx-xxxx-xxxx-xxxx`), not the account password. Create a new one if needed. |
| Image too large | The image is above 1 MB | Compress the image yourself before upload to control the quality loss. |

A `publishNow: true` post that Bluesky rejects returns `207` with `post.status: "failed"` and the message in `platforms[].errorMessage`:

```json
{
  "message": "Post created but publishing failed",
  "error": "All platforms failed",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "failed",
    "platforms": [
      {
        "platform": "bluesky",
        "status": "failed",
        "errorMessage": "Bluesky posts cannot exceed 300 characters"
      }
    ]
  }
}
```

Shorten `content`, or keep the long text for the other platforms and set `customContent` on the Bluesky entry, then create the post again. `207` is a 2xx status, so `fetch(...).ok` is `true`; branch on the status code and on `post.status`. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts#bluesky): the app-password flow.
- [Create post](/posts/create-post): every field of the request.
- [Media uploads](/guides/media-uploads): upload images and videos instead of hosting them.
- [Analytics](/analytics/get-analytics): post performance metrics.
- [Messages](/messages/list-inbox-conversations) and [Comments](/comments/list-inbox-comments): the inbox API.

---
