# Reddit

Publish text, link, image, gallery and native video posts to a subreddit with the Zernio API, with flairs, NSFW and spoiler tags.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Publish text, link, image, gallery and native video posts to Reddit with `POST /v1/posts` and `platform: "reddit"`. Every post targets one subreddit, and each subreddit has its own rules, so read them before you submit. The same account also serves DMs, comments and subreddit search.

## Quick reference

| Property | Value |
|----------|-------|
| Title limit | 300 characters (required, cannot be edited after posting) |
| Body text | 40,000 characters |
| Images per post | 1 (single), 2 to 20 (gallery) |
| Videos per post | 1 |
| Video format | MP4 |
| Video max size | 1 GB |
| Image formats | JPEG, PNG, GIF |
| Image max size | 20 MB |
| Post types | Text, Link, Image, Gallery, Native video |
| Scheduling | Yes |
| Inbox (DMs) | Yes (text only) |
| Inbox (comments) | Yes |
| Editing published posts | Body of a text post; titles and link posts cannot be edited |
| Analytics | Limited (upvotes and comments only) |

## Before you start

Reddit requires a subreddit for every post, and each subreddit is moderated independently with its own rules. What one subreddit accepts, another removes. Before posting to a subreddit through the API, check that it allows your post type (text, link or image), whether it requires a flair (many subreddits auto-remove posts without one), whether it allows third-party or automated posting, and its karma and account-age requirements. [Get subreddit rules](/connect/get-subreddit-rules) returns a subreddit's posting rules plus Reddit's site-wide rules, and [Check subreddit](/validate/validate-subreddit) confirms that a subreddit exists and which post types it allows.

<Callout type="warn">
The title is permanent: Reddit titles cannot be edited after posting. New accounts are restricted: low karma and a young account block most subreddits. Video rules vary by subreddit: native video with body text fails if the subreddit blocks videos. Video posts without body text can fall back to a link post.
</Callout>

## Connect

Call `GET /v1/connect/reddit` with `profileId` on [Get OAuth connect URL](/connect/get-connect-url). The [connecting accounts guide](/guides/connecting-accounts) covers the OAuth flow and [scopes](/guides/connecting-accounts#scopes); [Account health](/accounts/get-all-accounts-health) reports what a connected account can do with the scopes the user granted.

A connected account has a default subreddit. [List Reddit subreddits](/connect/get-reddit-subreddits) returns the subreddits the account can post to and the current default, and [Set default subreddit](/connect/update-reddit-subreddits) changes it without a second OAuth.

### OAuth scopes

| Scope | What it enables |
|-------|-----------------|
| `identity` | Account identity (username, avatar) |
| `submit` | Submit posts and comments |
| `read` | Read posts and comments (analytics and comment fetching) |
| `mysubreddits` | List subreddits the user is subscribed to or moderates |
| `flair` | Read and set post flair |
| `history` | Fetch post and comment history |
| `privatemessages` | Reddit private messages in the inbox |
| `edit` | Edit and delete the account's own posts and comments |
| `vote` | Vote on posts and comments |

## Publish

A plain post becomes a text post (a self post) in the account's default subreddit. The first line of `content` becomes the title, the rest becomes the body, and Reddit Markdown works in the body. Fields in `platformSpecificData` on the Reddit entry pick the subreddit, turn the post into a link post, and set flair, NSFW and spoiler tags.

### Text post

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Tips for learning a new programming language\n\nHere is what worked for me:\n\n1. Start with the official tutorial\n2. Build a small project immediately\n3. Read other people\'s code',
    platforms: [
      {
        platform: 'reddit',
        accountId: '66b2e19d8c3f5a7e9d0b1c2d',
        platformSpecificData: { subreddit: 'learnprogramming' }
      }
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
    content="Tips for learning a new programming language\n\nHere is what worked for me:\n\n1. Start with the official tutorial\n2. Build a small project immediately\n3. Read other people's code",
    platforms=[
        {
            "platform": "reddit",
            "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
            "platformSpecificData": {"subreddit": "learnprogramming"}
        }
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
    "content": "Tips for learning a new programming language\n\nHere is what worked for me:\n\n1. Start with the official tutorial\n2. Build a small project immediately\n3. Read other people'\''s code",
    "platforms": [
      {
        "platform": "reddit",
        "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
        "platformSpecificData": {"subreddit": "learnprogramming"}
      }
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
        "platform": "reddit",
        "status": "published",
        "platformPostUrl": "https://www.reddit.com/r/learnprogramming/comments/..."
      }
    ]
  }
}
```

Every sample below changes only the `mediaItems` or the `platforms` entry of this request.

### Link post

`url` turns the post into a link post; `content` becomes the title:

```json
{
  "platform": "reddit",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "subreddit": "programming",
    "url": "https://example.com/api-design-article"
  }
}
```

When a link post fails with `NO_LINKS` because the subreddit allows text posts only, Zernio retries it as a text post with the URL in the body.

### Image post

A single image; `content` becomes the title:

```json
"mediaItems": [
  { "type": "image", "url": "https://cdn.example.com/hiking-photo.jpg" }
]
```

### Gallery

2 or more images publish as a gallery, up to 20. Images past the 20th are dropped, without an error, so trim the list yourself when you have more. Not every subreddit allows galleries:

```json
"mediaItems": [
  { "type": "image", "url": "https://cdn.example.com/step1.jpg" },
  { "type": "image", "url": "https://cdn.example.com/step2.jpg" },
  { "type": "image", "url": "https://cdn.example.com/step3.jpg" },
  { "type": "image", "url": "https://cdn.example.com/finished.jpg" }
]
```

### Native video

A video uploads to Reddit's CDN and publishes as a native video that plays in Reddit's embedded player. Reddit transcodes it server-side with a 1080p and 30 fps cap. The post `content`, or the Reddit entry’s `customContent` override, becomes the original video post’s Markdown body. If body text is present, upload failures and subreddit video restrictions fail the post without converting it to a link. Automatic link fallback applies only to videos without body text. `nativeVideo: false` skips the upload and posts the video URL as an external link instead:

```json
"mediaItems": [
  { "type": "video", "url": "https://cdn.example.com/demo.mp4" }
]
```

For a native video with an explanation and CTA URL, send a request like this to `POST /v1/posts`:

```json
{
  "content": "How the product works\n\nTry it: https://example.com/product",
  "mediaItems": [
    { "type": "video", "url": "https://cdn.example.com/demo.mp4" }
  ],
  "platforms": [
    {
      "platform": "reddit",
      "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
      "platformSpecificData": {
        "subreddit": "your_community",
        "title": "A short product demo",
        "nativeVideo": true
      }
    }
  ],
  "publishNow": true
}
```

Leave `forceSelf` unset for this combination. `forceSelf: true` creates a text-only self post and skips native media uploads. Adding body text does not bypass a subreddit's video restrictions.

Zernio extracts the video's first frame as the poster. `videoPosterUrl` replaces it:

```json
{
  "platform": "reddit",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "subreddit": "videos",
    "videoPosterUrl": "https://cdn.example.com/poster.jpg"
  }
}
```

`videogif: true` submits the same upload as a silent looping clip. The video is still uploaded natively; only Reddit's kind changes:

```json
{
  "platform": "reddit",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "subreddit": "gifs", "videogif": true }
}
```

### Flair

Some subreddits require a post flair. List the flairs with [List subreddit flairs](/connect/get-reddit-flairs), then pass the `id` as `flairId`:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: flairs } = await zernio.connect.getRedditFlairs({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  query: { subreddit: 'socialmedia' }
});

console.log(flairs.flairs);
```
</Tab>
<Tab value="Python">
```python
flairs = client.connect.get_reddit_flairs(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    subreddit="socialmedia"
)

print(flairs["flairs"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/reddit-flairs?subreddit=socialmedia" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "flairs": [
    { "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890", "text": "Discussion", "textColor": "dark", "backgroundColor": "#edeff1" },
    { "id": "b2c3d4e5-f6a7-8901-bcde-f12345678901", "text": "News", "textColor": "light", "backgroundColor": "#ff4500" }
  ]
}
```

```json
{
  "platform": "reddit",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "subreddit": "socialmedia",
    "flairId": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
  }
}
```

If the subreddit requires a flair and the request has no `flairId`, Zernio uses the first available flair. To change the flair of a post that is already live, call [Set Reddit post flair](/connect/set-reddit-post-flair).

### NSFW and spoiler tags

`nsfw: true` marks the post as over 18. `spoiler: true` marks it as a spoiler, which takes effect only when the subreddit has spoiler tagging enabled:

```json
{
  "platform": "reddit",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "subreddit": "television", "spoiler": true }
}
```

### Edit a published post

`POST /v1/posts/{postId}/edit` with `platform: "reddit"` and the new `content` replaces the body of a published text post. Reddit keeps the same post id, and there is no time window. A link post has no editable body and is rejected with a `400` before the write; the title can never be edited.

```bash
curl -X POST "https://zernio.com/api/v1/posts/65f1c0a9e2b5af0012ab34cd/edit" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "platform": "reddit", "content": "Updated write-up, with the benchmark numbers." }'
```

Response (`200`):

```json
{
  "success": true,
  "id": "1abcd2",
  "url": "https://www.reddit.com/r/learnprogramming/comments/1abcd2/tips/",
  "message": "reddit post edited successfully"
}
```

## Platform fields

All fields go in `platformSpecificData` on the Reddit entry.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `subreddit` | string | account default | Target subreddit without the `r/` prefix. |
| `title` | string | first line of `content` | Post title, at most 300 characters. Cannot be edited after posting. |
| `url` | string (URL) | | Makes a link post instead of a text post. |
| `forceSelf` | boolean | `false` | Makes a text-only self post and skips native media uploads, even when media is provided. Omit for native video with body text. |
| `nativeVideo` | boolean | `true` | Uploads video to Reddit's CDN for the embedded player. `false` explicitly publishes an external link instead. When body text is present, upload failures or subreddit video restrictions fail the post without falling back to a link. |
| `videogif` | boolean | `false` | Submits the native video as a silent looping clip. |
| `videoPosterUrl` | string (URL) | first frame | Poster image for a native video. |
| `flairId` | string | | Flair id from [List subreddit flairs](/connect/get-reddit-flairs). Required by some subreddits. |
| `flairText` | string | | Free-text flair for subreddits that allow it. Ignored when `flairId` is set. |
| `nsfw` | boolean | `false` | Marks the post as over 18. |
| `spoiler` | boolean | `false` | Marks the post as a spoiler. The subreddit must have spoiler tagging enabled. |
| `sendreplies` | boolean | `true` | `false` stops comment replies from reaching the account's Reddit inbox. |

## Media requirements

Images are limited to 20 MB.

### Images

| Property | Requirement |
|----------|-------------|
| Max images | 1 (single), 2 to 20 (gallery) |
| Formats | JPEG, PNG, GIF |
| Max file size | 20 MB |
| Recommended | 1200 x 628 px |

Reddit accepts 16:9, 4:3, 1:1 and 9:16 images. GIFs display as a still frame until clicked, may be converted to video by Reddit, and load faster under 10 MB.

### Videos

| Property | Requirement |
|----------|-------------|
| Max videos | 1 per post |
| Format | MP4 |
| Max file size | 1 GB |
| Resolution | Transcoded server-side, capped at 1080p and 30 fps |

Upload at 1080p or below, because Reddit re-encodes anything larger anyway. 1 GB is Reddit's own cap, and a file above it is too large for Zernio to compress, so it reaches Reddit unchanged and Reddit refuses it. Reddit publishes no maximum duration for an API upload, so file size is the limit that binds. Hosting rules for media URLs are in [Media uploads](/guides/media-uploads).

## Analytics

Call `GET /v1/analytics?platform=reddit` ([Analytics API](/analytics/get-analytics)). Reddit exposes the score and the comment count only, so analytics are limited to these two metrics:

| Metric | Available |
|--------|-----------|
| Likes (upvotes) | <Yes /> |
| Comments | <Yes /> |

Reddit's API does not provide impressions, reach, shares, clicks or view counts.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: analytics } = await zernio.analytics.getAnalytics({
  query: { platform: 'reddit', fromDate: '2026-08-01', toDate: '2026-08-31' }
});

console.log(analytics.posts);
```
</Tab>
<Tab value="Python">
```python
analytics = client.analytics.get_analytics(
    platform="reddit",
    from_date="2026-08-01",
    to_date="2026-08-31"
)

print(analytics["posts"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/analytics?platform=reddit&fromDate=2026-08-01&toDate=2026-08-31" \
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
      "platform": "reddit",
      "status": "published",
      "publishedAt": "2026-08-14T10:00:05Z",
      "platformPostUrl": "https://www.reddit.com/r/learnprogramming/comments/1abcd2/tips/",
      "analytics": {
        "likes": 342,
        "comments": 28
      }
    }
  ]
}
```

## Inbox

Reddit supports DMs (Reddit private messages) and comments. DMs are text only, because Reddit's API does not support attachments in private messages.

### Direct messages

| Feature | Supported |
|---------|-----------|
| List conversations | <Yes /> |
| Fetch messages | <Yes /> |
| Send text messages | <Yes /> |
| Send attachments | <No /> (API limitation) |
| Archive and unarchive | <Yes /> |

### Comments

| Feature | Supported |
|---------|-----------|
| List comments on posts | <Yes /> |
| Reply to comments | <Yes /> |
| Delete comments | <Yes /> |
| Upvote and downvote | <Yes /> |
| Remove vote | <Yes /> |
| Edit comments | <Yes /> |

When replying to comments, you must provide the `subreddit` parameter. `PATCH /v1/inbox/comments/{postId}/{commentId}` edits the body of a comment the account posted, which Reddit is the only platform to allow, and the comment id is unchanged. Votes go through [Vote on a Reddit post or comment](/connect/vote-reddit-thing) with `thingId` (`t3_` for a post, `t1_` for a comment) and `direction` (`1`, `-1` or `0` to clear). Reddit's terms require every vote to be a human's own action proxied one to one; automated or agent-decided voting is vote manipulation and puts API access at risk.

[Messages](/messages/list-inbox-conversations) and [Comments](/comments/list-inbox-comments) document the inbox endpoints.

## What you cannot do

Reddit's API does not expose:

- Polls
- Crossposts to other subreddits
- Editing a post title after creation
- Collections
- Live chat threads
- Awards
- Separate upvote and downvote counts (score only)

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| "SUBREDDIT_NOTALLOWED: only trusted members" | The subreddit restricts who can post | Join the community, build karma, or choose another subreddit. |
| "NO_SELFS: doesn't allow text posts" | The subreddit accepts link or image posts only | Set `url` or attach an image. |
| "SUBMIT_VALIDATION_FLAIR_REQUIRED" | The subreddit requires a flair on every post | Fetch the flairs with [List subreddit flairs](/connect/get-reddit-flairs) and pass the right `flairId`. |
| "SUBREDDIT_NOEXIST" | A typo in the subreddit name, or the subreddit is private | Check the spelling and drop the `r/` prefix. |
| "AI-generated content not allowed" | The subreddit bans AI-generated content | Write original content or choose another subreddit. |
| "Reddit removed the post" | Moderators or AutoMod removed the post after submission | Read the subreddit rules and make the content comply. |
| "Reddit requires a subreddit" | No `subreddit` in the request and no default on the account | Set `platformSpecificData.subreddit`. |
| "Reddit rate limit reached... Retry in Ns." or "Quota resets in Ns" (`429`) | Reddit rate-limits per OAuth application, and the budget is shared across every Zernio customer, so a `429` can arrive at low request volume on your side | Wait the number of seconds in the message, then retry. New accounts are limited to around 10 posts per day. See [Reddit rate limits](/guides/rate-limits#reddits-budget-is-shared). |

A `publishNow: true` post that Reddit rejects returns `207` with `post.status: "failed"` and the message in `platforms[].errorMessage`:

```json
{
  "message": "Post created but publishing failed",
  "error": "All platforms failed",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "failed",
    "platforms": [
      {
        "platform": "reddit",
        "status": "failed",
        "errorMessage": "SUBMIT_VALIDATION_FLAIR_REQUIRED"
      }
    ]
  }
}
```

Fetch the subreddit's flairs, add `flairId` to `platformSpecificData` and create the post again. `207` is a 2xx status, so `fetch(...).ok` is `true`; branch on the status code and on `post.status`. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts): the OAuth flow.
- [Create post](/posts/create-post): every field of the request.
- [Reddit search](/reddit-search/search-reddit): search posts and browse subreddit feeds.
- [Get subreddit rules](/connect/get-subreddit-rules) and [List Reddit subreddits](/connect/get-reddit-subreddits): check a subreddit before posting.
- [Messages](/messages/list-inbox-conversations) and [Comments](/comments/list-inbox-comments): the inbox API.

---
