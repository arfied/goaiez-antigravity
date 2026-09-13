# Posts & Content Types

Create a Google Business Profile post with an image, add a call-to-action button, publish text only and edit a published post.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you have published a post with an image to Google Business Profile (`googlebusiness`) with `POST /v1/posts`, added a call-to-action button, and edited a published post with `POST /v1/posts/{postId}/edit`. You need an API key and a connected Google Business Profile account (`accountId`, from the [Google Business Profile page](/platforms/google-business#connect)).

## Step 1: create a post with an image

Call `POST /v1/posts` with `content`, one image in `mediaItems`, a `platforms` entry with `platform: "googlebusiness"` and `publishNow: true` ([Create post](/posts/create-post)). With no `topicType` the post is a `STANDARD` update. To schedule instead, replace `publishNow` with `scheduledFor` and `timezone`; the [post lifecycle guide](/guides/post-lifecycle) covers the statuses.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Open all holiday weekend. Stop by for the seasonal menu.',
    mediaItems: [
      { type: 'image', url: 'https://cdn.example.com/holiday-special.jpg' }
    ],
    platforms: [
      { platform: 'googlebusiness', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
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
    content="Open all holiday weekend. Stop by for the seasonal menu.",
    media_items=[
        {"type": "image", "url": "https://cdn.example.com/holiday-special.jpg"}
    ],
    platforms=[
        {"platform": "googlebusiness", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
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
    "content": "Open all holiday weekend. Stop by for the seasonal menu.",
    "mediaItems": [
      {"type": "image", "url": "https://cdn.example.com/holiday-special.jpg"}
    ],
    "platforms": [
      {"platform": "googlebusiness", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
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
        "platform": "googlebusiness",
        "status": "published",
        "platformPostId": "1234567890123456789",
        "platformPostUrl": "https://business.google.com/..."
      }
    ]
  }
}
```

Omit `mediaItems` for a text-only post. A post carries at most 1 image and no video. Every sample below changes only the `platforms` entry of this request and returns the same response.

## Step 2: add a call-to-action button

Set `platformSpecificData.callToAction` with a `type` and an HTTPS `url`. The button appears under the post.

```json
{
  "platform": "googlebusiness",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "callToAction": { "type": "BOOK", "url": "https://mybusiness.com/book" }
  }
}
```

| `type` | Button | Typical use |
|--------|--------|-------------|
| `LEARN_MORE` | Link to more information | Articles, about pages |
| `BOOK` | Booking or reservation link | Services, appointments |
| `ORDER` | Online ordering link | Restaurants, food |
| `SHOP` | E-commerce link | Retail, products |
| `SIGN_UP` | Registration link | Events, newsletters |
| `CALL` | Phone call | Contact, inquiries |

Event and offer posts add `topicType` and an `event` or `offer` object on top of this; see [Event & Offer Posts](/platforms/google-business/events-offers).

## Step 3: edit a published post

Call `POST /v1/posts/{postId}/edit` with `platform: "googlebusiness"` and the new `content` ([Edit post](/posts/edit-post)). Only the post body changes: the call to action, the event title and schedule, the offer fields and the image stay as published, for every post type. There is no time window and no edit limit, and the post keeps its id: `id` in the response is Google's post id, the `platformPostId` from Step 1, not the Zernio `_id` you pass in the path. To change the image, publish a new post.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: edited } = await zernio.posts.editPost({
  path: { postId: '65f1c0a9e2b5af0012ab34cd' },
  body: {
    platform: 'googlebusiness',
    content: 'Open all holiday weekend, 8am to 8pm. Stop by for the seasonal menu.'
  }
});

console.log(edited.id, edited.url);
```
</Tab>
<Tab value="Python">
```python
edited = client.posts.edit_post(
    post_id="65f1c0a9e2b5af0012ab34cd",
    platform="googlebusiness",
    content="Open all holiday weekend, 8am to 8pm. Stop by for the seasonal menu."
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
    "platform": "googlebusiness",
    "content": "Open all holiday weekend, 8am to 8pm. Stop by for the seasonal menu."
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "id": "1234567890123456789",
  "url": "https://business.google.com/...",
  "message": "googlebusiness post edited successfully"
}
```

If the post went to two connected accounts, pass `accountId` in the body to pick which one to edit. `accountId` selects the account, not the location: a post fanned out to several locations from one account always edits that account's first entry.

## If it fails

A `404` on `POST /v1/posts/{postId}/edit` means the post no longer exists on Google: it was deleted from the Business Profile dashboard, or it is an `EVENT` or `OFFER` post whose end date has passed.

```json
{
  "error": "Not found",
  "type": "not_found"
}
```

Publish a new post instead; the edit cannot be retried. [Error handling](/guides/error-handling) covers the envelope, and [Fields, Media & Limits](/platforms/google-business/reference#common-errors) lists the publishing errors.

## Related

- [Event & Offer Posts](/platforms/google-business/events-offers): `topicType`, `event` and `offer`.
- [Multi-Location Posting](/platforms/google-business/multi-location): `locationId` for accounts with several locations.
- [Fields, Media & Limits](/platforms/google-business/reference): image requirements and the full field table.
- [Post lifecycle](/guides/post-lifecycle): statuses and what you can do in each.
- [Idempotency](/guides/idempotency): safe retries with `x-request-id`.

---
