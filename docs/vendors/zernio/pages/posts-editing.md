# Posts & Editing

Create a post on X, attach images, a GIF or a video, publish a thread with threadItems, publish an Article and edit a published post.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you have published a post, a post with media, a thread and a long-form Article to X (platform value `twitter`) with `POST /v1/posts`, and edited a published post with `POST /v1/posts/{postId}/edit`. You need an API key and a connected X account (`accountId`, from the [X page](/platforms/twitter#connect)). Connecting one takes a card on file: X bills every API call and Zernio passes that cost through, so `GET /v1/connect/twitter` answers `402` with `reason: "twitter_passthrough"` until the team has a payment method.

## Step 1: create a post

Call `POST /v1/posts` with `content`, a `platforms` entry with `platform: "twitter"` and `publishNow: true` ([Create post](/posts/create-post)). Keep `content` under 280 characters on a free account. To schedule instead, replace `publishNow` with `scheduledFor` and `timezone`; the [post lifecycle guide](/guides/post-lifecycle) covers the statuses.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Shipping day. The API is live.',
    platforms: [
      { platform: 'twitter', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
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
    content="Shipping day. The API is live.",
    platforms=[
        {"platform": "twitter", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
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
    "content": "Shipping day. The API is live.",
    "platforms": [
      {"platform": "twitter", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
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
        "platform": "twitter",
        "status": "published",
        "platformPostId": "1852634789012345678",
        "platformPostUrl": "https://twitter.com/acmecorp/status/1852634789012345678"
      }
    ]
  }
}
```

Every sample below changes only `mediaItems` or the `platforms` entry of this request.

## Step 2: attach media

Add `mediaItems` to the request. Up to 4 images (JPEG, PNG, WebP, up to 5 MB each), or 1 video (MP4 or MOV, up to 512 MB, the only limit Zernio enforces; duration is set by X per account), or 1 GIF (up to 15 MB and 1280 x 1080 px; it takes all 4 image slots). The full limits are on [Media & Video](/platforms/twitter/media#media-requirements).

```json
"mediaItems": [
  { "type": "image", "url": "https://cdn.example.com/photo.jpg" }
]
```

A video uses `"type": "video"` and a GIF `"type": "gif"`:

```json
"mediaItems": [
  { "type": "gif", "url": "https://cdn.example.com/animation.gif" }
]
```

The response is the one from Step 1.

## Step 3: publish a thread

Put the whole sequence in `platformSpecificData.threadItems`. The first item is the root post and each later item replies to the one before it; every item can carry its own `mediaItems`. When `threadItems` is set, the top-level `content` is stored for display and search only and is not published, so the first post goes in `threadItems[0]`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: thread } = await zernio.posts.createPost({
  body: {
    platforms: [{
      platform: 'twitter',
      accountId: '66b2e19d8c3f5a7e9d0b1c2d',
      platformSpecificData: {
        threadItems: [
          {
            content: '1/ A thread about API design',
            mediaItems: [{ type: 'image', url: 'https://cdn.example.com/image1.jpg' }]
          },
          { content: '2/ Use the HTTP method that matches the action.' },
          { content: '3/ Version the API from day one.' },
          { content: '4/ Document everything. /end' }
        ]
      }
    }],
    publishNow: true
  }
});

console.log(thread.post.platforms[0].platformPostUrl);
```
</Tab>
<Tab value="Python">
```python
thread = client.posts.create_post(
    platforms=[{
        "platform": "twitter",
        "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
        "platformSpecificData": {
            "threadItems": [
                {
                    "content": "1/ A thread about API design",
                    "mediaItems": [{"type": "image", "url": "https://cdn.example.com/image1.jpg"}]
                },
                {"content": "2/ Use the HTTP method that matches the action."},
                {"content": "3/ Version the API from day one."},
                {"content": "4/ Document everything. /end"}
            ]
        }
    }],
    publish_now=True
)

print(thread["post"]["platforms"][0]["platformPostUrl"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "platforms": [{
      "platform": "twitter",
      "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
      "platformSpecificData": {
        "threadItems": [
          {
            "content": "1/ A thread about API design",
            "mediaItems": [{"type": "image", "url": "https://cdn.example.com/image1.jpg"}]
          },
          {"content": "2/ Use the HTTP method that matches the action."},
          {"content": "3/ Version the API from day one."},
          {"content": "4/ Document everything. /end"}
        ]
      }
    }],
    "publishNow": true
  }'
```
</Tab>
</Tabs>

Response (`201`), where `platformPostUrl` points at the root post:

```json
{
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "published",
    "platforms": [
      {
        "platform": "twitter",
        "status": "published",
        "platformPostUrl": "https://twitter.com/acmecorp/status/1852634789012345678"
      }
    ]
  }
}
```

To publish a thread as a reply to an existing post, see [reply with a thread](/platforms/twitter/replies-quotes#step-2-reply-with-a-thread). A thread cannot carry a `poll`.

## Step 4: edit a published post

Call `POST /v1/posts/{postId}/edit` with `platform: "twitter"` and the new `content` ([Edit post](/posts/edit-post)). X allows this on accounts with an active X Premium subscription, within 1 hour of publishing, up to 5 times per post, on single posts only (not threads) and for text only. X mints a new post id on every edit and returns it as `id`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: edited } = await zernio.posts.editPost({
  path: { postId: '65f1c0a9e2b5af0012ab34cd' },
  body: { platform: 'twitter', content: 'Shipping day. The API is live at zernio.com.' }
});

console.log(edited.id, edited.url);
```
</Tab>
<Tab value="Python">
```python
edited = client.posts.edit_post(
    post_id="65f1c0a9e2b5af0012ab34cd",
    platform="twitter",
    content="Shipping day. The API is live at zernio.com."
)

print(edited["id"], edited["url"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts/65f1c0a9e2b5af0012ab34cd/edit \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "platform": "twitter",
    "content": "Shipping day. The API is live at zernio.com."
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "id": "1852634789012345690",
  "url": "https://twitter.com/i/web/status/1852634789012345690",
  "message": "twitter post edited successfully"
}
```

If the post was published to several X accounts, pass `accountId` in the body to pick which copy to edit; without it the first `twitter` entry on the post is edited. A `400` means the edit window has passed, the account is not Premium, the post is a thread, or `content` is missing.

## Publish an Article

`platformSpecificData.article` publishes a long-form X Article instead of a post, on an eligible X Premium+ account. `content_state` is X's own snake_case content format, not free text: `blocks` carries the body and `entities` the links and images the blocks point at. Both keys are required, and `entities` may be empty. The smallest Article that publishes:

```json
{
  "platformSpecificData": {
    "article": {
      "title": "Building a better publishing workflow",
      "content_state": {
        "blocks": [
          { "type": "header-one", "text": "A reliable publishing workflow" },
          { "type": "unstyled", "text": "Long-form publishing should be observable." }
        ],
        "entities": []
      }
    }
  }
}
```

A block's `type` is `unstyled`, `header-one`, `header-two`, `header-three`, `unordered-list-item`, `ordered-list-item`, `blockquote` or `atomic`. `inline_style_ranges` (`bold`, `italic`, `strikethrough`) and `entity_ranges` mark up a block's own `text` by `offset` and `length`; an `entity_ranges` entry's `key` is the zero-based index into `entities`, which is how a link or an inline image attaches. DraftJS camelCase (`entityMap`, `inlineStyleRanges`, `entityRanges`) is rejected. `cover.url` takes a public JPG, PNG or WebP; GIF, MP4 and extensionless URLs are not accepted.

`mode: "publish"` creates the X draft and publishes it, 2 billable X calls at $0.010 each. `mode: "draft"` stops after the draft and returns X's draft id with no public URL, for 1 call. [X's Article format reference](https://docs.x.com/x-api/articles/create-draft-article) documents the shape in full.

## If it fails

A `207` on `POST /v1/posts` with `publishNow: true` means the post was saved but X rejected it. `post.status` is `failed` and `platforms[].errorMessage` names the cause; the most common one is the character limit:

```json
{
  "message": "Post created but publishing failed",
  "error": "All platforms failed",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "failed",
    "platforms": [
      {
        "platform": "twitter",
        "status": "failed",
        "errorMessage": "Tweet text is too long (312 characters). Twitter's limit is 280 characters. Note: URLs count as 23 characters."
      }
    ]
  }
}
```

Shorten `content`, or set `customContent` on the X entry when the same post goes to platforms with higher limits. `207` is a 2xx status, so `fetch(...).ok` is `true`; branch on the status code and on `post.status`. [Error handling](/guides/error-handling) covers the envelope, and [Limits & Errors](/platforms/twitter/reference#common-errors) lists the other messages.

## Related

- [Replies & Quotes](/platforms/twitter/replies-quotes): `replyToTweetId` and `quoteTweetId`.
- [Fields, Geo & Polls](/platforms/twitter/fields-polls): every other `platformSpecificData` option.
- [Media & Video](/platforms/twitter/media): the limits behind Step 2.
- [Post lifecycle](/guides/post-lifecycle): statuses and what you can do in each.
- [Idempotency](/guides/idempotency): safe retries with `x-request-id`.

---
