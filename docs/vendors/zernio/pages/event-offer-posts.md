# Event & Offer Posts

Publish event and offer posts to Google Business Profile with topicType, event and offer in platformSpecificData.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you have published an event post and an offer post to Google Business Profile (`googlebusiness`) with `platformSpecificData.topicType`. You need a connected Google Business Profile account (`accountId`) and the [base request](/platforms/google-business/posts#step-1-create-a-post-with-an-image).

`topicType` picks the post type:

| `topicType` | When to use | Required object |
|-------------|-------------|-----------------|
| `STANDARD` (default) | Regular updates | none |
| `EVENT` | Anything with a date range: grand openings, live music, workshops | `event` |
| `OFFER` | Promotions and discounts | `offer` |

## Step 1: publish an event post

Set `topicType: "EVENT"` and an `event` with a `title` and a `schedule`. `startDate` and `endDate` are `{ year, month, day }` objects and `startTime` and `endTime` are optional `{ hours, minutes }` in 24-hour time; Zernio also accepts ISO 8601 strings for all four and converts them. Google shows the title and schedule as the event heading in Search and Maps, and a `callToAction` button is optional.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: event } = await zernio.posts.createPost({
  body: {
    content: 'Grand opening weekend: free samples and live music both days.',
    mediaItems: [{ type: 'image', url: 'https://cdn.example.com/grand-opening.jpg' }],
    platforms: [{
      platform: 'googlebusiness',
      accountId: '66b2e19d8c3f5a7e9d0b1c2d',
      platformSpecificData: {
        topicType: 'EVENT',
        event: {
          title: 'Grand Opening Weekend',
          schedule: {
            startDate: { year: 2027, month: 5, day: 15 },
            startTime: { hours: 9, minutes: 0 },
            endDate: { year: 2027, month: 5, day: 16 },
            endTime: { hours: 17, minutes: 0 }
          }
        },
        callToAction: { type: 'LEARN_MORE', url: 'https://mybusiness.com/grand-opening' }
      }
    }],
    publishNow: true
  }
});

console.log(event.post.platforms[0].platformPostUrl);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

event = client.posts.create_post(
    content="Grand opening weekend: free samples and live music both days.",
    media_items=[{"type": "image", "url": "https://cdn.example.com/grand-opening.jpg"}],
    platforms=[{
        "platform": "googlebusiness",
        "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
        "platformSpecificData": {
            "topicType": "EVENT",
            "event": {
                "title": "Grand Opening Weekend",
                "schedule": {
                    "startDate": {"year": 2027, "month": 5, "day": 15},
                    "startTime": {"hours": 9, "minutes": 0},
                    "endDate": {"year": 2027, "month": 5, "day": 16},
                    "endTime": {"hours": 17, "minutes": 0}
                }
            },
            "callToAction": {"type": "LEARN_MORE", "url": "https://mybusiness.com/grand-opening"}
        }
    }],
    publish_now=True
)

print(event["post"]["platforms"][0]["platformPostUrl"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Grand opening weekend: free samples and live music both days.",
    "mediaItems": [
      {"type": "image", "url": "https://cdn.example.com/grand-opening.jpg"}
    ],
    "platforms": [{
      "platform": "googlebusiness",
      "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
      "platformSpecificData": {
        "topicType": "EVENT",
        "event": {
          "title": "Grand Opening Weekend",
          "schedule": {
            "startDate": {"year": 2027, "month": 5, "day": 15},
            "startTime": {"hours": 9, "minutes": 0},
            "endDate": {"year": 2027, "month": 5, "day": 16},
            "endTime": {"hours": 17, "minutes": 0}
          }
        },
        "callToAction": {"type": "LEARN_MORE", "url": "https://mybusiness.com/grand-opening"}
      }
    }],
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
        "platformPostUrl": "https://business.google.com/..."
      }
    ]
  }
}
```

The same `schedule` as ISO 8601 strings:

```json
"schedule": {
  "startDate": "2027-05-15T00:00:00Z",
  "startTime": "2027-05-15T09:00:00Z",
  "endDate": "2027-05-16T00:00:00Z",
  "endTime": "2027-05-16T17:00:00Z"
}
```

The step below changes only the `platforms` entry of this request and returns the same response.

## Step 2: publish an offer post

Set `topicType: "OFFER"` and an `offer` with any of `couponCode`, `redeemOnlineUrl` and `termsConditions`; Google makes all three optional, and at least one is worth setting. Add an `event` with a `title` and `schedule` to give the offer a heading and a validity period on Google.

```json
{
  "platform": "googlebusiness",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "topicType": "OFFER",
    "event": {
      "title": "Holiday Sale, 20% Off",
      "schedule": {
        "startDate": { "year": 2027, "month": 12, "day": 1 },
        "endDate": { "year": 2027, "month": 12, "day": 31 }
      }
    },
    "offer": {
      "couponCode": "HOLIDAY20",
      "redeemOnlineUrl": "https://mybusiness.com/shop",
      "termsConditions": "Valid in store and online. Cannot be combined with other offers."
    },
    "callToAction": { "type": "SHOP", "url": "https://mybusiness.com/shop" }
  }
}
```

## If it fails

A `207` with `post.status: "failed"` means the post was saved but Google rejected it. `topicType: "EVENT"` without an `event` object is the usual cause, because Google returns `400` for it:

```json
{
  "message": "Post created but publishing failed",
  "error": "All platforms failed",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "failed",
    "platforms": [
      {
        "platform": "googlebusiness",
        "status": "failed",
        "errorMessage": "Request contains an invalid argument."
      }
    ]
  }
}
```

Add the `event` object with `title`, `startDate` and `endDate` and create the post again; a failed post is terminal and is not retried. `207` is a 2xx status, so `fetch(...).ok` is `true`; branch on the status code and on `post.status`. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Posts & Content Types](/platforms/google-business/posts): the base request, call-to-action buttons and edits.
- [Fields, Media & Limits](/platforms/google-business/reference#platform-fields): the full `platformSpecificData` table.
- [Multi-Location Posting](/platforms/google-business/multi-location): the same event on every location.
- [Create post](/posts/create-post): every field of the request.

---
