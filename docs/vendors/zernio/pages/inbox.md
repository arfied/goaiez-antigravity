# Inbox

List Google Business Profile reviews, reply to them and read reviews across several locations in one call.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can list the reviews of a Google Business Profile (`googlebusiness`) location, reply to one and read reviews across several locations in one request. You need a connected Google Business Profile account (`accountId`). Reviews are the only inbox surface on this platform.

| Feature | Supported |
|---------|-----------|
| List reviews | <Yes /> |
| Reply to reviews | <Yes /> |
| Delete a reply | <Yes /> |
| Webhooks | <Yes /> (`review.new`, `review.updated`) |
| DMs | <No /> |
| Comments | <No /> |

## Step 1: list reviews

Call `GET /v1/inbox/reviews?platform=googlebusiness` ([List reviews](/reviews/list-inbox-reviews)). Filter with `accountId`, `minRating`, `maxRating` and `hasReply`, and page with `cursor`. Each review's `id` is Google's full resource name, so it also encodes the location.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: reviews } = await zernio.reviews.listInboxReviews({
  query: { platform: 'googlebusiness', accountId: '66b2e19d8c3f5a7e9d0b1c2d', hasReply: false }
});

console.log(reviews.data);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

reviews = client.reviews.list_inbox_reviews(
    platform="googlebusiness",
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    has_reply=False
)

print(reviews["data"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/inbox/reviews?platform=googlebusiness&accountId=66b2e19d8c3f5a7e9d0b1c2d&hasReply=false" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "status": "success",
  "data": [
    {
      "id": "accounts/123456789/locations/12345678901234567890/reviews/AIe9_BGx1234567890",
      "platform": "googlebusiness",
      "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
      "locationId": "12345678901234567890",
      "locationName": "Joe's Pizza Downtown",
      "reviewer": { "id": null, "name": "John Smith", "profileImage": "https://lh3.googleusercontent.com/a/..." },
      "rating": 5,
      "text": "Great service and friendly staff. Highly recommend.",
      "created": "2026-08-15T10:30:00Z",
      "hasReply": false,
      "hasPhotos": false,
      "photoCount": 0,
      "photos": [],
      "reply": null,
      "reviewUrl": null
    }
  ],
  "pagination": { "hasMore": false, "nextCursor": null }
}
```

## Step 2: reply to a review

Call `POST /v1/inbox/reviews/{reviewId}/reply` with `accountId` and `message` ([Reply to review](/reviews/reply-to-inbox-review)); `reviewId` is the review's `id` from Step 1, URL-encoded because it contains slashes. Google keeps one owner reply per review, so a second call overwrites the first, including a reply someone typed by hand in the Business Profile UI; read the review before retrying. Send an `Idempotency-Key` header to make a retry after a timeout safe. Review replies never count as [outbound messages](/pricing#outbound-messages).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: replied } = await zernio.reviews.replyToInboxReview({
  path: { reviewId: 'accounts/123456789/locations/12345678901234567890/reviews/AIe9_BGx1234567890' },
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    message: 'Thank you, John. See you again soon.'
  }
});

console.log(replied.reply.created);
```
</Tab>
<Tab value="Python">
```python
replied = client.reviews.reply_to_inbox_review(
    review_id="accounts/123456789/locations/12345678901234567890/reviews/AIe9_BGx1234567890",
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    message="Thank you, John. See you again soon."
)

print(replied["reply"]["created"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/inbox/reviews/accounts%2F123456789%2Flocations%2F12345678901234567890%2Freviews%2FAIe9_BGx1234567890/reply" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "message": "Thank you, John. See you again soon."
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "status": "success",
  "reply": {
    "id": "AIe9_BGx1234567890",
    "text": "Thank you, John. See you again soon.",
    "created": "2026-08-16T08:00:00Z"
  },
  "platform": "googlebusiness"
}
```

`DELETE /v1/inbox/reviews/{reviewId}/reply` removes the reply and leaves the review in place. New and edited reviews arrive on the [`review.new`](/webhooks/inbox#reviewnew) and [`review.updated`](/webhooks/inbox#reviewupdated) webhooks.

## Read reviews across several locations

Call `POST /v1/accounts/{accountId}/gmb-reviews/batch` with up to 50 `locationNames` ([Batch get reviews](/google-business/batch-get-google-business-reviews)). Each name is `accounts/{googleAccountId}/locations/{locationId}`, built from the `accountId` and `id` values of [List locations](/platforms/google-business/multi-location#step-1-list-the-locations). The response is a flat `locationReviews` array where each item carries the location `name` it belongs to plus the `review`; Google orders it newest first (`orderBy` defaults to `updateTime desc`), so stop paging once you cross your date window. Paging is `pageSize` (50 at most) plus `nextPageToken` from the response, sent back as `pageToken` in the next request body; a response with no `nextPageToken` is the last page. Aggregate `averageRating` and `totalReviewCount` are not returned here; [Get reviews](/google-business/get-google-business-reviews) has them for one location.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: batch } = await zernio.gmbreviews.batchGetGoogleBusinessReviews({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: {
    locationNames: [
      'accounts/123456789/locations/12345678901234567890',
      'accounts/123456789/locations/22345678901234567890'
    ],
    pageSize: 50
  }
});

for (const item of batch.locationReviews) {
  console.log(item.name, item.review.starRating, item.review.comment);
}
```
</Tab>
<Tab value="Python">
```python
batch = client.gmb_reviews.batch_get_google_business_reviews(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    location_names=[
        "accounts/123456789/locations/12345678901234567890",
        "accounts/123456789/locations/22345678901234567890"
    ],
    page_size=50
)

for item in batch["locationReviews"]:
    print(item["name"], item["review"].get("starRating"), item["review"].get("comment"))
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-reviews/batch \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "locationNames": [
      "accounts/123456789/locations/12345678901234567890",
      "accounts/123456789/locations/22345678901234567890"
    ],
    "pageSize": 50
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "locationReviews": [
    {
      "name": "accounts/123456789/locations/22345678901234567890",
      "review": {
        "reviewId": "AIe9_BGx0987654321",
        "name": "accounts/123456789/locations/22345678901234567890/reviews/AIe9_BGx0987654321",
        "starRating": "FOUR",
        "comment": "Good experience overall.",
        "reviewer": { "displayName": "Anonymous", "isAnonymous": true },
        "createTime": "2026-08-10T14:20:00Z",
        "updateTime": "2026-08-10T14:20:00Z",
        "reviewReply": null
      }
    }
  ],
  "nextPageToken": "CiAKHAoUMTIzNDU2Nzg5"
}
```

## If it fails

A `403` on any reviews endpoint means the team has no inbox access, which is the first failure a legacy plan hits. Every account on usage-based billing includes it ([pricing](/pricing)).

A `401` with code `token_invalid` means Google revoked or expired the account's token:

```json
{
  "error": "Access token invalid. Please reconnect your Google Business Profile account.",
  "code": "token_invalid"
}
```

Reconnect the account through `GET /v1/connect/googlebusiness`, then retry. An empty `data` array for a location that shows reviews on Google is a different problem: the location is not verified or not yet matched to its Maps place, which [verification](/platforms/google-business/business-profile#verification) reports as `hasVoiceOfMerchant: false`. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [List reviews](/reviews/list-inbox-reviews) and [Reply to review](/reviews/reply-to-inbox-review): the inbox API.
- [Get reviews](/google-business/get-google-business-reviews): one location with `averageRating` and `totalReviewCount`.
- [Inbox webhooks](/webhooks/inbox#reviewnew): the `review.new` and `review.updated` payloads.
- [Multi-Location Posting](/platforms/google-business/multi-location): where the location names come from.

---
