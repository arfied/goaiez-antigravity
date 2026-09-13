# List comments on an ad API Reference

Returns comments on an ad's underlying creative post. Useful for moderating or analyzing
engagement on dark posts (ad creatives that never went live organically), which the
regular GET /v1/inbox/comments/{postId} endpoint cannot serve because dark posts are
not in Zernio's post database.

An ad that runs on both Facebook feed and Instagram feed has two separate underlying
posts with separate comment threads (the creative's effective_object_story_id and
effective_instagram_media_id). Use the `placement` query param to pick one; with no
param the Instagram side is returned when it exists, otherwise Facebook. The
identifiers are read from the ad record (persisted during sync) with a Marketing-API
fallback for ads that predate the field.

For Instagram-placed comments, the Instagram account that runs the ad must be connected
to Zernio, because those comments are read through that account's token. If no connected
Instagram account on the profile can read the ad's media, the call returns
ads_connection_required (the Facebook side, if any, is still readable via ?placement=facebook).

TikTok uses the connected TikTok Ads advertiser token and supports both paid video
ads and Spark Ads. `since` and `until` select a date window of at most 30 days;
the default is the last 30 days. TikTok searches by ad group, so Zernio filters
each page to this ad. A page can be empty while `pagination.hasMore` is true.
Reuse `pagination.cursor` with the same `limit`; the cursor retains the date window.
`placement` is Meta-only and returns a 400 for TikTok.
Listing needs no identity or video item ID. When the ad group is stored, each
page makes one comment-list call and no ad-detail lookup, including for external
ads that TikTok no longer returns from ad details. `meta.tiktokItemId: null`
does not prevent listing. If the ad group is missing, Zernio fetches ad details;
unavailable details return 404 ad_not_found, and no ad group returns 400 ad_not_commentable.

TikTok returns replies as separate comments with `parentId`; nested reply fetching
is not supported. `canReply` requires a first-level comment, comment-management
permission, a video item ID and a supported TT_USER or CUSTOMIZED_USER identity.
`canDelete` requires TikTok's own-comment deletion capability, a video item ID
and a supported identity. Both flags are false when identity or item is unknown.
Listing uses stored and comment-specific fields without fetching identity.
A direct reply or delete request can lazily resolve missing fields and succeed
even after a false flag. `canHide` is true because visibility changes need only
advertiser and comment IDs. `canLike` is false. Use the ad comment reply, hide
and delete operations below to moderate TikTok comments.
Other platforms return feature_not_available.

Requires the Ads add-on. Response shape matches GET /v1/inbox/comments/{postId}.

The `{adId}` path segment accepts any identifier dialect Zernio indexes for the ad:
Zernio internal `_id` (24-char hex), the numeric `platformAdId` (the value shipped in
`comment.received` webhooks as `comment.ad.id`), or the creative's
`effective_object_story_id` / `effective_instagram_media_id`. Caller doesn't need a
translation step.


## GET /v1/ads/{adId}/comments

**List comments on an ad**

Returns comments on an ad's underlying creative post. Useful for moderating or analyzing
engagement on dark posts (ad creatives that never went live organically), which the
regular GET /v1/inbox/comments/{postId} endpoint cannot serve because dark posts are
not in Zernio's post database.

An ad that runs on both Facebook feed and Instagram feed has two separate underlying
posts with separate comment threads (the creative's effective_object_story_id and
effective_instagram_media_id). Use the `placement` query param to pick one; with no
param the Instagram side is returned when it exists, otherwise Facebook. The
identifiers are read from the ad record (persisted during sync) with a Marketing-API
fallback for ads that predate the field.

For Instagram-placed comments, the Instagram account that runs the ad must be connected
to Zernio, because those comments are read through that account's token. If no connected
Instagram account on the profile can read the ad's media, the call returns
ads_connection_required (the Facebook side, if any, is still readable via ?placement=facebook).

TikTok uses the connected TikTok Ads advertiser token and supports both paid video
ads and Spark Ads. `since` and `until` select a date window of at most 30 days;
the default is the last 30 days. TikTok searches by ad group, so Zernio filters
each page to this ad. A page can be empty while `pagination.hasMore` is true.
Reuse `pagination.cursor` with the same `limit`; the cursor retains the date window.
`placement` is Meta-only and returns a 400 for TikTok.
Listing needs no identity or video item ID. When the ad group is stored, each
page makes one comment-list call and no ad-detail lookup, including for external
ads that TikTok no longer returns from ad details. `meta.tiktokItemId: null`
does not prevent listing. If the ad group is missing, Zernio fetches ad details;
unavailable details return 404 ad_not_found, and no ad group returns 400 ad_not_commentable.

TikTok returns replies as separate comments with `parentId`; nested reply fetching
is not supported. `canReply` requires a first-level comment, comment-management
permission, a video item ID and a supported TT_USER or CUSTOMIZED_USER identity.
`canDelete` requires TikTok's own-comment deletion capability, a video item ID
and a supported identity. Both flags are false when identity or item is unknown.
Listing uses stored and comment-specific fields without fetching identity.
A direct reply or delete request can lazily resolve missing fields and succeed
even after a false flag. `canHide` is true because visibility changes need only
advertiser and comment IDs. `canLike` is false. Use the ad comment reply, hide
and delete operations below to moderate TikTok comments.
Other platforms return feature_not_available.

Requires the Ads add-on. Response shape matches GET /v1/inbox/comments/{postId}.

The `{adId}` path segment accepts any identifier dialect Zernio indexes for the ad:
Zernio internal `_id` (24-char hex), the numeric `platformAdId` (the value shipped in
`comment.received` webhooks as `comment.ad.id`), or the creative's
`effective_object_story_id` / `effective_instagram_media_id`. Caller doesn't need a
translation step.


### Parameters

- **adId** (required) in path: Internal Zernio ad ID or indexed platform ad/post ID.
- **placement** (optional) in query: Which side of the ad to return comments for. Omit to default to the Instagram side when present, else Facebook. Returns ad_not_commentable if the ad has no such placement.
- **limit** (optional) in query: No description
- **since** (optional) in query: TikTok-only start date. Defaults to 30 days before until. Maximum window is 30 days.
- **until** (optional) in query: TikTok-only end date. Defaults to today in UTC.
- **cursor** (optional) in query: Pagination cursor from a previous response.

### Responses

#### 200: Comments on the ad.

**Response Body:**

- **status** (required) `string`: No description - one of: success
- **comments** (required) `array[object]`: 
  Type: `object`
- **pagination** (required) `object`: 
  - **hasMore** `boolean`: No description
  - **cursor** `string`: No description
- **meta** (required) `object`: 
  - **platform** (required) `string`: Platform of the comments. - one of: facebook, instagram, tiktok
  - **placement** `string`: The placement these comments are for, useful when you didn't pass ?placement= and want to know which one you got. - one of: facebook, instagram
  - **adId** (required) `string`: Internal Zernio ad ID.
  - **platformAdId** `string`: Platform ad ID.
  - **effectiveStoryId** `string`: Underlying post ID the comments belong to. effective_object_story_id for the Facebook side, effective_instagram_media_id for the Instagram side.
  - **tiktokItemId** `string,null`: TikTok-only video item ID from stored ad fields or returned comments. Null does not prevent listing; ad details are not fetched to populate it.
  - **since** `string` (date): TikTok-only resolved start date.
  - **until** `string` (date): TikTok-only resolved end date.
  - **facebookAccountId** `string,null`: Facebook-only. The connected Facebook Page SocialAccount these comments were read through. Pass it as `accountId` (with `effectiveStoryId` as the postId) to /v1/inbox/comments to reply/hide/delete. Null when no connected Page was used (then moderation isn't possible).
  - **instagramUserId** `string`: Instagram-only. The Instagram-scoped business ID that owns the boosted media (creative.instagram_user_id).
  - **instagramPermalink** `string`: Instagram-only. Public permalink of the boosted IG post (creative.instagram_permalink_url).
  - **instagramAccountId** `string`: Instagram-only. The connected Instagram SocialAccount these comments were read through. Pass it as `accountId` (with `effectiveStoryId` as the postId) to /v1/inbox/comments to reply/hide/delete.
  - **accountId** (required) `string`: Account ID (ads SocialAccount).
  - **lastUpdated** (required) `string` (date-time): No description

#### 400: Invalid ad ID format, or the ad's creative format does not expose a commentable
underlying post (code ad_not_commentable).


#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required (legacy plans need the Ads add-on; included by default on usage-based plans), or ad platform is not Meta or TikTok (code feature_not_available).

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 422: Ads account token unavailable, or (for Instagram-placed ads) no connected
Instagram account on the profile can read the ad's media (code ads_connection_required).


---

---
