# Analytics & Engagement

Read post analytics for X with GET /v1/analytics, and retweet, bookmark and follow with the Twitter engagement endpoints.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can read impressions, likes, comments, shares, saves, clicks and views for your X posts, and retweet, bookmark and follow from a connected X account. You need a connected X account (`accountId`). Every X read and write is billed at X's pass-through rate, listed on [X API usage](/pricing#x-twitter-api-usage).

## Read post analytics

Call `GET /v1/analytics?platform=twitter` with a date range ([Get post analytics](/analytics/get-analytics)). Impressions, likes, comments, shares, clicks and `saves` (X's bookmark count) are available on every post; `fromDate` defaults to 90 days ago and the range is at most 366 days. `views` is X's media view count, which X publishes on the media object alone, so a post carrying a video or an animated GIF reports it and a text or image post reports `0`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: analytics } = await zernio.analytics.getAnalytics({
  query: { platform: 'twitter', fromDate: '2026-08-01', toDate: '2026-08-31' }
});

console.log(analytics.posts);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

analytics = client.analytics.get_analytics(
    platform="twitter",
    from_date="2026-08-01",
    to_date="2026-08-31"
)

print(analytics["posts"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/analytics?platform=twitter&fromDate=2026-08-01&toDate=2026-08-31" \
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
      "platform": "twitter",
      "status": "published",
      "publishedAt": "2026-08-14T10:00:05Z",
      "platformPostUrl": "https://twitter.com/acmecorp/status/1852634789012345678",
      "analytics": {
        "impressions": 15420,
        "likes": 342,
        "comments": 28,
        "shares": 45,
        "saves": 63,
        "clicks": 189,
        "views": 9820,
        "engagementRate": 2.78
      }
    }
  ]
}
```

## Retweet, bookmark or follow

Call `POST /v1/twitter/retweet`, `POST /v1/twitter/bookmark` or `POST /v1/twitter/follow` with `accountId` and the target ([Twitter engagement](/twitter-engagement/retweet-post)). X allows 50 requests per 15-minute window on each of them, and retweets also count against the 300 posts per 3 hours that post creation uses. Bookmarks need the `bookmark.write` scope and follows the `follows.write` scope; a follow request to a protected account comes back with `pending_follow: true`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: retweet } = await zernio.twitterengagement.retweetPost({
  body: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', tweetId: '1748391029384756102' }
});

console.log(retweet.retweeted);
```
</Tab>
<Tab value="Python">
```python
retweet = client.twitter_engagement.retweet_post(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    tweet_id="1748391029384756102"
)

print(retweet["retweeted"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/twitter/retweet \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"accountId": "66b2e19d8c3f5a7e9d0b1c2d", "tweetId": "1748391029384756102"}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "status": "success",
  "tweetId": "1748391029384756102",
  "retweeted": true,
  "platform": "twitter"
}
```

Bookmark and follow take the same body and answer in the same shape, with their own boolean. Each action has a `DELETE` on the same path that undoes it, with the ids as query parameters; the undo returns the same body with the boolean set to `false`.

| Action | Call | Body | Boolean in the response | Undo |
|--------|------|------|-------------------------|------|
| Retweet | `POST /v1/twitter/retweet` | `accountId`, `tweetId` | `retweeted` | `DELETE /v1/twitter/retweet?accountId=&tweetId=` |
| Bookmark | `POST /v1/twitter/bookmark` | `accountId`, `tweetId` | `bookmarked` | `DELETE /v1/twitter/bookmark?accountId=&tweetId=` |
| Follow | `POST /v1/twitter/follow` | `accountId`, `targetUserId` | `following`, plus `pending_follow` | `DELETE /v1/twitter/follow?accountId=&targetUserId=` |

To find posts to act on, [Search recent tweets](/twitter-engagement/search-tweets) covers the last 7 days with X's search operators, and [Look up a tweet](/twitter-engagement/get-tweet) resolves any post id or URL to its text, author and public metrics.

## If it fails

A `403` on an engagement endpoint means X refused the action for this account, for example because the OAuth token lacks the `bookmark.write` or `follows.write` scope or the account is suspended:

```json
{
  "error": "X rejected the request (e.g. suspended account, missing OAuth scope)",
  "type": "platform_error",
  "code": "platform_api_error",
  "platform": "twitter"
}
```

Reconnect the account so the consent screen grants every scope on the [X page](/platforms/twitter#oauth-scopes), then retry. A `404` means `accountId` is not one of your connected accounts. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Get post analytics](/analytics/get-analytics): every query parameter and the single-post lookup.
- [Twitter engagement](/twitter-engagement/retweet-post): retweet, bookmark, follow, search and look up.
- [Rate limits](/guides/rate-limits): how Zernio handles a `429` from X.
- [X API usage](/pricing#x-twitter-api-usage): what each read and write costs.

---
