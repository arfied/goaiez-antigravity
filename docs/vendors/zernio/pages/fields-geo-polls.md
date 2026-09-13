# Fields, Geo & Polls

Restrict media by country, add paid partnership and AI labels, flag sensitive media, limit who can reply and attach a poll on X with platformSpecificData.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can restrict a post's media to specific countries, label it as a paid partnership or as AI-generated media, flag sensitive media, choose who can reply and attach a poll on X (platform value `twitter`). Every option is a field in `platformSpecificData` on the X entry of `POST /v1/posts`; the [full field table](/platforms/twitter/reference#platform-fields) lists them with their types. You need a connected X account (`accountId`).

Each block below is the `platforms` entry of the [base request](/platforms/twitter/posts#step-1-create-a-post) and returns the same `201`.

## Restrict media by country

`geoRestriction.countries` hides the attached media outside the listed countries; the post text stays visible everywhere. Pass up to 25 uppercase ISO 3166-1 alpha-2 codes. The field only applies when the post has media and is ignored on text-only posts.

```json
{
  "platform": "twitter",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "geoRestriction": { "countries": ["US", "ES"] }
  }
}
```

## Label a paid partnership

`paidPartnership: true` makes X label the post as a paid partnership or paid promotion. In a thread it applies to the root post only. Whether X accepts the field depends on your X API access tier.

```json
{
  "platform": "twitter",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "paidPartnership": true }
}
```

## Label AI-generated media

`madeWithAi: true` makes X label the post as containing AI-generated media. The label is for AI-generated images and video, not AI-written text. In a thread it applies to the root post only.

```json
{
  "platform": "twitter",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "madeWithAi": true }
}
```

## Flag sensitive media

`sensitiveMedia` marks every attached media item with a sensitive-content warning. At least one of `adultContent`, `graphicViolence` and `other` must be `true`, and the field is ignored on text-only posts.

```json
{
  "platform": "twitter",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "sensitiveMedia": { "adultContent": true, "graphicViolence": false, "other": false }
  }
}
```

## Limit who can reply

`replySettings` restricts replies to `following` (accounts you follow), `mentionedUsers`, `subscribers` or `verified`. Omit it to let everyone reply. In a thread it applies to the first post only, and it cannot be combined with `replyToTweetId`.

```json
{
  "platform": "twitter",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "replySettings": "mentionedUsers" }
}
```

## Add a poll

`poll` attaches 2 to 4 options of up to 25 characters each, open for `duration_minutes` between 5 (5 minutes) and 10080 (7 days). A poll cannot be combined with `mediaItems`, `threadItems` or `quoteTweetId`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Which feature should we ship next?',
    platforms: [{
      platform: 'twitter',
      accountId: '66b2e19d8c3f5a7e9d0b1c2d',
      platformSpecificData: {
        poll: {
          options: ['Dark mode', 'New analytics', 'More integrations'],
          duration_minutes: 1440
        }
      }
    }],
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
    content="Which feature should we ship next?",
    platforms=[{
        "platform": "twitter",
        "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
        "platformSpecificData": {
            "poll": {
                "options": ["Dark mode", "New analytics", "More integrations"],
                "duration_minutes": 1440
            }
        }
    }],
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
    "content": "Which feature should we ship next?",
    "platforms": [{
      "platform": "twitter",
      "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
      "platformSpecificData": {
        "poll": {
          "options": ["Dark mode", "New analytics", "More integrations"],
          "duration_minutes": 1440
        }
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
        "platform": "twitter",
        "status": "published",
        "platformPostUrl": "https://twitter.com/acmecorp/status/1852634789012345678"
      }
    ]
  }
}
```

## If it fails

A `400` means the request failed validation before anything was published, for example a poll with 1 option:

```json
{ "error": "Poll must have 2-4 options" }
```

The create call answers a validation failure with that single `error` string, so read it rather than a code: an option over 25 characters returns "Each poll option must be a non-empty string of 1-25 characters". Nothing was created, so fix the poll and send the request again. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Limits & Errors](/platforms/twitter/reference#platform-fields): the full `platformSpecificData` table.
- [Replies & Quotes](/platforms/twitter/replies-quotes): `replyToTweetId` and `quoteTweetId`.
- [Media & Video](/platforms/twitter/media): the media these labels and restrictions apply to.
- [Create post](/posts/create-post): every field of the request.

---
