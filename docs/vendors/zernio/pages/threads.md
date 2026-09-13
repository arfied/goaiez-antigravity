# Threads

Publish text, image, video and carousel posts and thread sequences to Threads with the Zernio API, with topic tags, first comments and reply management.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Publish text, image, video and carousel posts and thread sequences to Threads with `POST /v1/posts` and `platform: "threads"`. The same account serves analytics and reply management in the inbox.

## Quick reference

| Property | Value |
|----------|-------|
| Character limit | 500 |
| Images per post | 10 (carousel) |
| Videos per post | 1 |
| Image formats | JPEG, PNG |
| Image max size | 8 MB (auto-compressed) |
| Video format | MP4 (H.264/AAC) |
| Video max size | 1 GB |
| Video max duration | 5 minutes |
| Post types | Text, Image, Video, Carousel, Thread sequence |
| Scheduling | Yes |
| Inbox (comments) | Comment, reply, delete, hide and unhide |
| Inbox (DMs) | No |
| Analytics | Yes (views, likes, comments, shares) |

## Before you start

Threads requires an Instagram Business or Creator account with Threads enabled. The account connects through Instagram authentication (the same Facebook app), so losing Instagram access means losing Threads, and a restricted Instagram account cannot publish to Threads either. An account can publish 250 API posts per 24 hours.

<Callout type="warn">
Threads posts are at most 500 characters. A caption cross-posted from LinkedIn (3,000), Facebook (63,206) or Instagram (2,200) fails with "Param text must be at most 500 characters long." Send a shorter Threads version in `customContent`.
</Callout>

## Connect

Call `GET /v1/connect/threads` with `profileId` on [Get OAuth connect URL](/connect/get-connect-url). The user authorizes with the Instagram account that owns the Threads profile. The [connecting accounts guide](/guides/connecting-accounts) covers the OAuth flow and [scopes](/guides/connecting-accounts#scopes) in general; [Account health](/accounts/get-all-accounts-health) reports what a connected account can do with the scopes the user granted.

### OAuth scopes

| Scope | What it enables |
|-------|-----------------|
| `threads_basic` | Account identity and basic profile data |
| `threads_content_publish` | Publish threads and carousels |
| `threads_read_replies` | Read replies to your threads |
| `threads_manage_replies` | Reply to, hide and manage replies |
| `threads_manage_insights` | Post and account analytics |
| `threads_delete` | Delete threads through the API |

## Publish

A plain post becomes a text post. Add `mediaItems` for an image, a video or a carousel, and `threadItems` in `platformSpecificData` for a chain of connected posts.

### Text post

Threads accepts text-only posts of up to 500 characters, with no media required.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Hot take: the best API is the one with the best docs.',
    platforms: [
      { platform: 'threads', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
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
    content="Hot take: the best API is the one with the best docs.",
    platforms=[
        {"platform": "threads", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
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
    "content": "Hot take: the best API is the one with the best docs.",
    "platforms": [
      {"platform": "threads", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
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
        "platform": "threads",
        "status": "published",
        "platformPostUrl": "https://www.threads.net/@yourhandle/post/..."
      }
    ]
  }
}
```

Every sample below changes only the `mediaItems` or the `platforms` entry of this request.

### Image post

A single image with an optional caption:

```json
"mediaItems": [
  { "type": "image", "url": "https://cdn.example.com/office.jpg" }
]
```

### Video post

A single video with an optional caption, at most 1 GB and 5 minutes:

```json
"mediaItems": [
  { "type": "video", "url": "https://cdn.example.com/launch.mp4" }
]
```

### Carousel

Up to 10 images in one swipeable post:

```json
"mediaItems": [
  { "type": "image", "url": "https://cdn.example.com/feature1.jpg" },
  { "type": "image", "url": "https://cdn.example.com/feature2.jpg" },
  { "type": "image", "url": "https://cdn.example.com/feature3.jpg" }
]
```

### Thread sequence

`threadItems` publishes a chain of connected posts. The first item is the root post and each later item is a reply to the one before it, in order; every item carries its own text and optional media. With `threadItems` set, the top-level `content` is stored for display and search only and is not published, so the first post must be `threadItems[0]`.

```json
{
  "platform": "threads",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "threadItems": [
      {
        "content": "Here is a thread about API design",
        "mediaItems": [{ "type": "image", "url": "https://cdn.example.com/cover.jpg" }]
      },
      { "content": "1/ First, REST principles." },
      {
        "content": "2/ Authentication. Here is what we recommend.",
        "mediaItems": [{ "type": "image", "url": "https://cdn.example.com/auth-diagram.jpg" }]
      },
      { "content": "3/ Finally, always version your API. /end" }
    ]
  }
}
```

### Topic tags

`topic_tag` sets the post's topic tag for discoverability. It is 1 to 50 characters, cannot contain periods (`.`) or ampersands (`&`), and overrides the tag Threads would extract from hashtags in the content.

```json
{
  "platform": "threads",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "topic_tag": "APIDesign" }
}
```

## Platform fields

All fields go in `platformSpecificData` on the Threads entry.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `topic_tag` | string | (from hashtags) | Topic tag for categorisation and discoverability. 1 to 50 characters, no periods (`.`) or ampersands (`&`). Overrides auto-extraction from content hashtags. |
| `firstComment` | string | | Posted right after publishing as a reply to the published post (with `threadItems`, a reply to the root post). Up to 500 characters. It is itself a Threads post, so it counts toward the 250 posts per 24 hours. |
| `threadItems` | Array\<\{content, mediaItems?\}\> | | The complete sequence of posts in a thread. The first item becomes the root post and must be `threadItems[0]`. When set, the top-level `content` is for display and search only and is not published. |

## Media requirements

Images above 8 MB are compressed automatically. Original files are preserved.

### Images

| Property | Requirement |
|----------|-------------|
| Max images | 10 per post (carousel) |
| Formats | JPEG, PNG |
| Max file size | 8 MB per image (auto-compressed) |
| Recommended | 1080 x 1350 px (4:5 portrait) |

| Ratio | Dimensions | Notes |
|-------|------------|-------|
| 4:5 | 1080 x 1350 px | Portrait, recommended |
| 1:1 | 1080 x 1080 px | Square |
| 16:9 | 1080 x 608 px | Landscape |

### Videos

| Property | Requirement |
|----------|-------------|
| Max videos | 1 per post |
| Format | MP4 (H.264/AAC) |
| Max file size | 1 GB |
| Max duration | 5 minutes |
| Aspect ratio | 9:16 (vertical), 16:9 (landscape), 1:1 (square) |
| Resolution | 1080p recommended |
| Codec | H.264 |
| Frame rate | 30 fps recommended |
| Audio | AAC, 128 kbps |

### Media URLs

Threads fetches media the same way Instagram does. A media URL must be publicly accessible with no authentication, return the media bytes with the correct `Content-Type` header, and not redirect to an HTML page; Google Drive, Dropbox, OneDrive and iCloud sharing links return a webpage and fail. WebP images may fail, so use JPEG or PNG. To host files instead, use the [media endpoint](/guides/media-uploads).

## Analytics

Call `GET /v1/analytics?platform=threads` ([Analytics API](/analytics/get-analytics)).

| Metric | Available |
|--------|-----------|
| Likes | <Yes /> |
| Comments | <Yes /> |
| Shares | <Yes /> |
| Views | <Yes /> |

Threads' insights expose no impressions metric, so `views` is the reach figure to report. `shares` adds reposts and quotes together.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: analytics } = await zernio.analytics.getAnalytics({
  query: { platform: 'threads', fromDate: '2026-08-01', toDate: '2026-08-31' }
});

console.log(analytics.posts);
```
</Tab>
<Tab value="Python">
```python
analytics = client.analytics.get_analytics(
    platform="threads",
    from_date="2026-08-01",
    to_date="2026-08-31"
)

print(analytics["posts"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/analytics?platform=threads&fromDate=2026-08-01&toDate=2026-08-31" \
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
      "platform": "threads",
      "status": "published",
      "publishedAt": "2026-08-14T10:00:05Z",
      "platformPostUrl": "https://www.threads.net/@yourhandle/post/ABC123xyz",
      "analytics": {
        "likes": 164,
        "comments": 19,
        "shares": 12,
        "views": 8210,
        "engagementRate": 2.38
      }
    }
  ]
}
```

## Inbox

Threads supports comments only: you can comment, reply, delete, hide and unhide, but not like a comment. A comment on Threads is a reply to the thread, so omitting `commentId` replies to the post itself. Threads has no DMs.

| Feature | Supported |
|---------|-----------|
| List comments on posts | <Yes /> |
| Post a new top-level comment | <Yes /> (omit `commentId` and the reply lands on the post) |
| Reply to comments | <Yes /> |
| Delete comments | <Yes /> |
| Like comments | <No /> |
| Hide and unhide comments | <Yes /> |

Hide a reply with `POST /v1/inbox/comments/{postId}/{commentId}/hide` and unhide it with `DELETE` on the same path; the [Comments API](/comments/list-inbox-comments) lists every endpoint.

## What you cannot do

Threads' API does not expose:

- Polls
- GIF search
- Editing a post after publishing
- Who liked or reposted a post
- Quote posts
- Liking or unliking comments
- DMs

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| "Param text must be at most 500 characters long." | The post exceeds 500 characters | Shorten to 500 characters. Use `customContent` on cross-platform posts so each platform gets its own version. |
| "Media download has failed. The media URI doesn't meet our requirements." (2207052) | Threads cannot fetch media from the URL | The URL must return media bytes, not an HTML page. WebP may fail; use JPEG or PNG. |
| "Instagram account is restricted." (2207050) | The linked Instagram account is restricted | Check the account status on Instagram and resolve any policy violation before retrying. |
| "Publishing failed due to max retries reached" | Every publishing retry was exhausted | Usually temporary. Wait a few minutes and retry. |

A `publishNow: true` post that Threads rejects returns `207` with `post.status: "failed"` and the message in `platforms[].errorMessage`:

```json
{
  "message": "Post created but publishing failed",
  "error": "All platforms failed",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "failed",
    "platforms": [
      {
        "platform": "threads",
        "status": "failed",
        "errorMessage": "Param text must be at most 500 characters long."
      }
    ]
  }
}
```

`207` is a 2xx status, so `fetch(...).ok` is `true`; branch on the status code and on `post.status`. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts): the OAuth flow through Instagram.
- [Create post](/posts/create-post): every field of the request.
- [Media uploads](/guides/media-uploads): upload images and videos instead of hosting them.
- [Analytics](/analytics/get-analytics): post performance metrics.
- [Comments](/comments/list-inbox-comments): the inbox API for replies.
- [Pricing](/pricing): what analytics, the inbox and outbound messages cost, and which replies count.

---
