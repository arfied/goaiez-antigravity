# Replies & Quotes

Reply to a post on X with replyToTweetId, reply with a whole thread, and quote a post with quoteTweetId.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can publish a reply, a reply thread and a quote post on X (platform value `twitter`) with two `platformSpecificData` fields. You need a connected X account (`accountId`) and the id of the post you are answering. To find one, search the last 7 days with [`GET /v1/twitter/search`](/twitter-engagement/search-tweets) or resolve a post URL with [`GET /v1/twitter/tweet`](/twitter-engagement/get-tweet).

## Step 1: reply to a post

Set `platformSpecificData.replyToTweetId` on the X entry of `POST /v1/posts`. X only accepts replies to your own posts or to posts that mention you; a reply to any other account's post is rejected by X. `replyToTweetId` cannot be combined with `replySettings`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: reply } = await zernio.posts.createPost({
  body: {
    content: 'Thanks, fixed in the release going out today.',
    platforms: [{
      platform: 'twitter',
      accountId: '66b2e19d8c3f5a7e9d0b1c2d',
      platformSpecificData: { replyToTweetId: '1748391029384756102' }
    }],
    publishNow: true
  }
});

console.log(reply.post.platforms[0].platformPostUrl);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

reply = client.posts.create_post(
    content="Thanks, fixed in the release going out today.",
    platforms=[{
        "platform": "twitter",
        "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
        "platformSpecificData": {"replyToTweetId": "1748391029384756102"}
    }],
    publish_now=True
)

print(reply["post"]["platforms"][0]["platformPostUrl"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Thanks, fixed in the release going out today.",
    "platforms": [{
      "platform": "twitter",
      "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
      "platformSpecificData": {
        "replyToTweetId": "1748391029384756102"
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
        "platformPostId": "1852634789012345678",
        "platformPostUrl": "https://twitter.com/acmecorp/status/1852634789012345678"
      }
    ]
  }
}
```

The two steps below change only the `platforms` entry of this request and return the same response.

## Step 2: reply with a thread

Combine `replyToTweetId` with `threadItems`. Only `threadItems[0]` replies to the target; the later items chain under it as in a [standalone thread](/platforms/twitter/posts#step-3-publish-a-thread), and the top-level `content` is not published.

```json
{
  "platform": "twitter",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "replyToTweetId": "1748391029384756102",
    "threadItems": [
      { "content": "1/ Good question. The short answer is yes." },
      { "content": "2/ The longer answer: it depends on whether the account is Premium." }
    ]
  }
}
```

## Step 3: quote a post

Set `platformSpecificData.quoteTweetId` to the id or the full status URL of the post to quote. A quote cannot carry `mediaItems` or a `poll`, and in a thread it applies to the first post only. X only accepts quotes of your own posts or of posts that mention you or belong to a conversation you are part of.

```json
{
  "platform": "twitter",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "quoteTweetId": "1748391029384756102" }
}
```

X bills a quote made with `quoteTweetId` at the standard create rate. Pasting the post's URL into `content` instead is billed at the URL rate, 13 times more; the rates are on [X API usage](/pricing#x-twitter-api-usage).

## If it fails

A `207` with `post.status: "failed"` means X rejected the reply or quote and `platforms[].errorMessage` carries the reason. Replying twice with the same text is the common one, because X rejects a post whose text it already has:

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
        "errorMessage": "X (Twitter) does not allow duplicate tweets"
      }
    ]
  }
}
```

Change the text and create the post again; a failed post is terminal and is not retried. The same `207` shape reports a target post you are not allowed to answer, so check that it is your own post or one you are mentioned in. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Posts & Editing](/platforms/twitter/posts): the base request and standalone threads.
- [Fields, Geo & Polls](/platforms/twitter/fields-polls): `replySettings` and the other options.
- [Search recent tweets](/twitter-engagement/search-tweets): find posts to reply to.
- [Look up a tweet](/twitter-engagement/get-tweet): resolve a post URL to its id and metrics.
- [Limits & Errors](/platforms/twitter/reference): the full field table.

---
