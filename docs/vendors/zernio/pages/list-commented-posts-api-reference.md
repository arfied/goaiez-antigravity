# List commented posts API Reference

Returns posts with comment counts from all connected accounts. Aggregates data across multiple accounts.

Responses are cached for up to 10 minutes, so the feed may lag new comments by that
window. Do not poll this endpoint for real-time updates: subscribe to the
`comment.received` webhook, which fires for every new comment across your posts and
carries the post reference needed to keep this list current.

For users with the Ads add-on (accounts on usage-based billing always qualify), the user's Meta ads
(boosted/dark posts) are included too. There's one row per (ad, placement-with-comments):
an ad that runs on both Facebook feed and Instagram feed produces up to two rows (the
Page dark post and the IG media have separate comment threads), each flagged
`isAd: true` with `adId` and `placement` (`id` is `{adId}:{placement}`). Use
`?platform=metaads` to return *only* ad rows; passing `facebook`/`instagram` returns
*organic* posts only (no ads); omitting `platform` returns both. Fetch a row's thread
from GET /v1/ads/{adId}/comments?placement={placement}. Ad comment counts are read with
the Marketing API token (Facebook side) or the connected Instagram account's token
(Instagram side); a row whose count can't be read is omitted.

Pagination walks each account's platform listing. Following `nextCursor` reaches past
the first page on Facebook, Instagram, Threads, LinkedIn and YouTube, since they are
the platforms that support a server-side date window; on the others the listing stops
at its first page. Cursor pagination is only coherent for the default sort
(`sortBy=date`, `sortOrder=desc`): with `sortOrder=asc`, or with `sortBy=comments`,
the cursor filter does not match the sort order and the second page is unreliable.

`nextCursor` is opaque: pass it back verbatim, never construct or parse it, its
composition may change without notice. Because each page re-queries a live window,
results can still shift between requests, so dedupe by `id` on the client.

`commentCount` semantics differ by platform: YouTube's includes replies, Facebook's counts
top-level comments only.


## GET /v1/inbox/comments

**List commented posts**

Returns posts with comment counts from all connected accounts. Aggregates data across multiple accounts.

Responses are cached for up to 10 minutes, so the feed may lag new comments by that
window. Do not poll this endpoint for real-time updates: subscribe to the
`comment.received` webhook, which fires for every new comment across your posts and
carries the post reference needed to keep this list current.

For users with the Ads add-on (accounts on usage-based billing always qualify), the user's Meta ads
(boosted/dark posts) are included too. There's one row per (ad, placement-with-comments):
an ad that runs on both Facebook feed and Instagram feed produces up to two rows (the
Page dark post and the IG media have separate comment threads), each flagged
`isAd: true` with `adId` and `placement` (`id` is `{adId}:{placement}`). Use
`?platform=metaads` to return *only* ad rows; passing `facebook`/`instagram` returns
*organic* posts only (no ads); omitting `platform` returns both. Fetch a row's thread
from GET /v1/ads/{adId}/comments?placement={placement}. Ad comment counts are read with
the Marketing API token (Facebook side) or the connected Instagram account's token
(Instagram side); a row whose count can't be read is omitted.

Pagination walks each account's platform listing. Following `nextCursor` reaches past
the first page on Facebook, Instagram, Threads, LinkedIn and YouTube, since they are
the platforms that support a server-side date window; on the others the listing stops
at its first page. Cursor pagination is only coherent for the default sort
(`sortBy=date`, `sortOrder=desc`): with `sortOrder=asc`, or with `sortBy=comments`,
the cursor filter does not match the sort order and the second page is unreliable.

`nextCursor` is opaque: pass it back verbatim, never construct or parse it, its
composition may change without notice. Because each page re-queries a live window,
results can still shift between requests, so dedupe by `id` on the client.

`commentCount` semantics differ by platform: YouTube's includes replies, Facebook's counts
top-level comments only.


### Parameters

- **profileId** (optional) in query: Filter by profile ID
- **platform** (optional) in query: Filter by platform. `metaads` is a synthetic value meaning the user's ads (boosted/dark posts) only; `facebook`/`instagram` return organic posts only.
- **minComments** (optional) in query: Minimum comment count
- **since** (optional) in query: Posts created after this date
- **sortBy** (optional) in query: Sort field
- **sortOrder** (optional) in query: Sort order
- **limit** (optional) in query: No description
- **cursor** (optional) in query: No description
- **accountId** (optional) in query: Filter by specific account ID

### Responses

#### 200: Aggregated posts with comments

**Response Body:**

- **data** `array[object]`: 
  - **id** `string`: No description
  - **platform** `string`: No description
  - **accountId** `string`: No description
  - **accountUsername** `string`: No description
  - **content** `string`: The post text/caption. On ad rows (isAd: true) this is the AD NAME, not the underlying post's caption. The creative text isn't exposed here.
  - **picture** `string,null`: Post media thumbnail. On ad rows this is the ad creative thumbnail.
  - **permalink** `string,null`: Public URL of the post. On ad rows: the Facebook dark-post URL (facebook placement) or the IG media permalink (instagram placement); may be null when unknown.
  - **createdTime** `string` (date-time): No description
  - **commentCount** `integer`: No description
  - **likeCount** `integer`: Not fetched for ad rows (always 0 there).
  - **cid** `string,null`: Bluesky content identifier
  - **subreddit** `string,null`: Reddit subreddit name
  - **isAd** `boolean`: True when this row is an ad (boosted/dark post). `platform` is then the placement (facebook = the Page dark post / instagram = the IG media), `id` is `{adId}:{placement}`, and the thread is at GET /v1/ads/{adId}/comments?placement={placement}.
  - **adId** `string`: Internal Zernio ad id, only on ad rows.
  - **placement** `string`: Which side of the ad this row's comments are on, only on ad rows. - one of: facebook, instagram
- **pagination** `object`: 
  - **hasMore** `boolean`: No description
  - **nextCursor** `string,null`: No description
- **meta** `object`: 
  - **accountsQueried** `integer`: No description
  - **accountsFailed** `integer`: No description
  - **failedAccounts** `array[object]`: 
    - **accountId** `string`: No description
    - **accountUsername** `string,null`: No description
    - **platform** `string`: No description
    - **error** `string`: No description
    - **code** `string,null`: Error code if available (e.g. TOKEN_EXPIRED, or X_INBOX_NOT_ENABLED for an X account whose owner has not enabled X inbox)
    - **retryAfter** `integer,null`: Seconds to wait before retry (rate limits)
  - **lastUpdated** `string` (date-time): No description
  - **accountsSkipped** `array[object]`: Connected accounts that were not queried: their platform does not support this feature, or the account is not enabled for it
    - **accountId** `string`: No description
    - **platform** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

---

---
