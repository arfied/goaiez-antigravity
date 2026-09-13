# Ad Comments

Read the comments on an ad, dark posts and TikTok ads included, with GET /v1/ads/{adId}/comments.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Read the comments on an ad's creative, including dark posts, with `GET /v1/ads/{adId}/comments`. Dark posts (ad creatives that never went live organically on the Page feed) are not in Zernio's post database, so the regular [`GET /v1/inbox/comments/{postId}`](/comments/list-inbox-comments) cannot serve them. On Meta, this endpoint uses stored story identifiers with a Marketing API fallback; [TikTok listing](#tiktok-ads) uses the ad group. It returns the same response shape as inbox comments, so the same rendering code works on both.

## Read the comments on an ad

The path segment takes any identifier Zernio indexes for the ad: the Zernio `_id` (24 hex characters), Meta's numeric `platformAdId` (the value the `comment.received` webhook ships as `comment.ad.id`), or the creative's `effective_object_story_id` or `effective_instagram_media_id`. No translation step is needed.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: thread } = await zernio.adaccounts.getAdComments({
  path: { adId: '66d4a1b2c3e4f5a6b7c8d9e2' },
  query: { limit: 50 }
});

for (const comment of thread.comments) console.log(comment.from.name, comment.message);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

thread = client.ad_accounts.get_ad_comments(
    ad_id="66d4a1b2c3e4f5a6b7c8d9e2",
    limit=50,
)

for comment in thread["comments"]:
    print(comment["from"]["name"], comment["message"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/66d4a1b2c3e4f5a6b7c8d9e2/comments?limit=50" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "status": "success",
  "comments": [
    {
      "id": "1029384756102938_5566778899",
      "message": "Does this ship to Spain?",
      "from": { "id": "7788990011223344", "name": "Jamie Rivera" },
      "created_time": "2027-02-14T10:31:05+0000"
    }
  ],
  "pagination": { "hasMore": false, "cursor": null },
  "meta": {
    "platform": "instagram",
    "placement": "instagram",
    "adId": "66d4a1b2c3e4f5a6b7c8d9e2",
    "platformAdId": "120260000000000000",
    "effectiveStoryId": "17912345678901234",
    "instagramPermalink": "https://www.instagram.com/p/DGx7Yk2ScAb/",
    "instagramAccountId": "66b2e19d8c3f5a7e9d0b1c2d"
  }
}
```

`meta.effectiveStoryId` is the underlying post the comments belong to, and `pagination.cursor` pages the thread through the `cursor` parameter.

Reply through the regular inbox endpoint: send `meta.effectiveStoryId` as `postId` on [`POST /v1/inbox/comments/{postId}`](/comments/reply-to-inbox-post), with the comment's `id` as `commentId` and the placement's own account as `accountId`. That account comes back in the same response: `meta.instagramAccountId` on the Instagram side, `meta.facebookAccountId` on the Facebook side, which is `null` when no connected Page was used and moderation is then impossible. Any `postId` that is not a 24-character Zernio id goes to the platform as it is, so a dark post replies fine without being in the post database.


## TikTok ads

The same endpoint serves TikTok ads, in the same response shape, and TikTok additionally supports moderation.

Listing works from the stored ad group alone, with no identity resolution or ad-detail lookup to find a video item ID. `meta.tiktokItemId` can be `null` and does not prevent listing, including for external ads TikTok no longer returns from ad details. There is no ad-level identity or item-ID precondition to check before listing. If the ad group is missing, Zernio fetches ad details to find it; unavailable details return `404 ad_not_found`, and a missing ad group returns `400 ad_not_commentable`.

TikTok's comment API works on a date window rather than a cursor into all history, so two rules apply that Meta does not have. The window is at most 30 days, and it defaults to the last 30 days when you pass neither `since` nor `until`. Both accept a plain `YYYY-MM-DD` date. Pagination stays a `cursor`, and the cursor carries the window with it, so you do not re-send the dates while paging.

```bash
curl -H "Authorization: Bearer $ZERNIO_API_KEY" \
  'https://zernio.com/api/v1/ads/1790166588666881/comments?since=2026-08-15&until=2026-09-09&limit=50'
```

Each comment carries `canReply`, `canDelete` and `canHide`. `canReply` requires a first-level comment, comment-management permission, a video item ID and a supported identity. `canDelete` requires TikTok's own-comment deletion capability, a video item ID and a supported identity. Both are false when the identity or item needed to moderate that comment is unknown. Hiding needs only the advertiser and comment IDs, so `canHide` is true without an identity.

These are per-comment capabilities, not a reason to block the listing. A direct reply or delete request can resolve missing identity or item fields lazily and succeed even after a false flag. Listing filters each ad-group page to the requested ad, so a page can be empty while `pagination.hasMore` is true; keep paging with the returned cursor and the same `limit`.

Reply to a comment, hide or restore it, or delete one of your own:

```bash
curl -X POST -H "Authorization: Bearer $ZERNIO_API_KEY" -H 'Content-Type: application/json' \
  -d '{"text":"Thanks, sending you the specs now."}' \
  'https://zernio.com/api/v1/ads/1790166588666881/comments/7412345678901234567/reply'

curl -X POST -H "Authorization: Bearer $ZERNIO_API_KEY" -H 'Content-Type: application/json' \
  -d '{"hidden":true}' \
  'https://zernio.com/api/v1/ads/1790166588666881/comments/7412345678901234567/hide'

curl -X DELETE -H "Authorization: Bearer $ZERNIO_API_KEY" \
  'https://zernio.com/api/v1/ads/1790166588666881/comments/7412345678901234567'
```

The moderation routes take the same `since` and `until` as the listing, because TikTok looks a comment up inside a window. Pass the window you listed with when you act on a comment near the edge of it.

Organic TikTok comments have their own surface, the [inbox comment endpoints](/platforms/tiktok#comments), on accounts connected through TikTok's Business app; this endpoint is the one that reaches comments on ad creatives, dark posts included. Moderation is TikTok-only; on Meta these three routes answer `501`.

## Pick a placement

An ad that runs on the Facebook feed and the Instagram feed has 2 separate underlying posts with separate comment threads: the creative's `effective_object_story_id` and its `effective_instagram_media_id`. `placement` picks one, taking `facebook` or `instagram`. With no `placement`, the Instagram side comes back when it exists and the Facebook side otherwise.

Instagram-placed comments are read through the token of the Instagram account that runs the ad, so that account has to be connected to Zernio. The identifiers themselves are read from the ad record, persisted during sync, with a Marketing API fallback for ads that predate the field.

## Common errors

A `400` means the creative format exposes no commentable post:

```json
{
  "error": "This ad's creative has no commentable underlying post",
  "type": "invalid_request_error",
  "code": "ad_not_commentable"
}
```

On Meta, Story ads and dynamic product ads return this, and so does a `placement` the ad does not run on. A `422` with `code: "ads_connection_required"` means the ads token is unavailable or no connected Instagram account on the profile can read the ad's media; for the latter, connect that account or read the Facebook side with `placement=facebook`. A `403` with `code: "feature_not_available"` means the ad is on neither Meta nor TikTok.

## Related

- [Inbox comments](/comments/list-inbox-comments): the same shape for organic posts.
- [Creatives](/platforms/meta-ads/creatives): where `effectiveObjectStoryId` comes from.
- [Inbox webhooks](/webhooks/inbox): `comment.received` for ads as well as posts.
- [Get ad comments](/ad-accounts/get-ad-comments): every parameter.

---
