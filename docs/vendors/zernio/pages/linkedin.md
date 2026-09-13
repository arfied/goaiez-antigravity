# LinkedIn

Publish text, image, video, document and poll posts to LinkedIn personal profiles and organization pages with the Zernio API, with reposts, geo-restriction, multi-organization posting and text edits.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Publish text, images, videos, documents and polls to LinkedIn with `POST /v1/posts` and `platform: "linkedin"`. One connected account posts as a personal profile or as any organization page the member administers, and the same account serves analytics and comments.

## Quick reference

| Property | Value |
|----------|-------|
| Character limit | 3,000 |
| Images per post | 20 |
| Videos per post | 1 |
| Documents per post | 1 (PDF, PPT, PPTX, DOC, DOCX) |
| Image formats | JPEG, PNG, GIF |
| Image max size | 8 MB |
| Video formats | MP4, MOV, AVI |
| Video max size | 5 GB |
| Video max duration | 10 minutes (personal), 30 minutes (organization page) |
| Post types | Text, Image, Multi-image, Video, Document, Poll, Repost |
| Scheduling | Yes |
| Editing published posts | Yes (text only, 3,000 characters) |
| Inbox (comments) | Yes (organization pages only) |
| Inbox (DMs) | No (LinkedIn blocks third-party DM access) |
| Analytics | Yes |

## Before you start

LinkedIn connects personal profiles and organization pages through the same account. Organization pages get full analytics, 30-minute videos and the comments API; personal profiles get limited analytics and 10-minute videos. A post carries one media type: images, one video or one document, never mixed.

<Callout type="warn">
LinkedIn rejects duplicate content with a `422`, even when the earlier post is weeks old.
</Callout>

## Connect

Call `GET /v1/connect/linkedin` with `profileId` on [Get OAuth connect URL](/connect/get-connect-url). After OAuth the user picks a personal profile or one of the organizations they administer, in standard or headless mode; see [platforms requiring secondary selection](/guides/connecting-accounts#platforms-requiring-secondary-selection). The [connecting accounts guide](/guides/connecting-accounts) covers the flow and [scopes](/guides/connecting-accounts#scopes) in general; [Account health](/accounts/get-all-accounts-health) reports what a connected account can do with the scopes the user granted.

To switch an existing account between personal and organization posting without a second OAuth, call [Switch LinkedIn account type](/connect/update-linkedin-organization).

### OAuth scopes

LinkedIn connections request the widest scope set of any platform because one token covers personal posting, organization pages and ads.

Sign-in and profile:

| Scope | What it enables |
|-------|-----------------|
| `openid`, `profile`, `email` | Account identity via OpenID Connect |
| `r_basicprofile` | Profile vanity name (public profile URL) |
| `r_1st_connections_size` | Connection count shown on the account |

Personal posting and analytics:

| Scope | What it enables |
|-------|-----------------|
| `w_member_social` | Publish posts and comments as the member |
| `w_member_social_feed` | Reshares and feed interactions |
| `r_member_postAnalytics` | Per-post analytics for member posts |
| `r_member_profileAnalytics` | Profile-level analytics (impressions, viewers) |

Organization pages, usable only when the member is an admin of an organization:

| Scope | What it enables |
|-------|-----------------|
| `w_organization_social` | Publish posts as an organization |
| `w_organization_social_feed` | Organization reshares and feed interactions |
| `rw_organization_admin` | List organizations you administer, manage page settings |
| `r_organization_social` | Read organization posts and comments |
| `r_organization_social_feed` | Read the organization's feed |
| `r_organization_followers` | Follower statistics for organization pages |

Ads, used by [LinkedIn Ads](/platforms/linkedin-ads):

| Scope | What it enables |
|-------|-----------------|
| `r_ads` | Read ad accounts |
| `rw_ads` | Create and manage campaigns and creatives |
| `r_ads_reporting` | Ads reporting and analytics |
| `rw_conversions` | Conversions API (server-side conversion events) |

## Publish

A plain post becomes a text post. Media in `mediaItems` selects an image, video or document post, and fields in `platformSpecificData` on the LinkedIn entry add polls, reposts, a first comment and organization targeting.

### Text post

Up to 3,000 characters. LinkedIn collapses the rest behind a "see more" fold, so put the hook first.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'I spent 3 years building the wrong product.\n\nHere is what I learned about validating ideas before writing code.',
    platforms: [
      { platform: 'linkedin', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
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
    content="I spent 3 years building the wrong product.\n\nHere is what I learned about validating ideas before writing code.",
    platforms=[
        {"platform": "linkedin", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
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
    "content": "I spent 3 years building the wrong product.\n\nHere is what I learned about validating ideas before writing code.",
    "platforms": [
      {"platform": "linkedin", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
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
        "platform": "linkedin",
        "status": "published",
        "platformPostUrl": "https://www.linkedin.com/feed/update/urn:li:share:..."
      }
    ]
  }
}
```

Every sample below changes only the `mediaItems` or the `platforms` entry of this request.

### Image post

One image, recommended 1200 x 627 px (landscape), or up to 20 images in one post. Images cannot share a post with a video or a document:

```json
"mediaItems": [
  { "type": "image", "url": "https://cdn.example.com/photo1.jpg" },
  { "type": "image", "url": "https://cdn.example.com/photo2.jpg" },
  { "type": "image", "url": "https://cdn.example.com/photo3.jpg" }
]
```

### Video post

One video per post. Personal profiles accept up to 10 minutes, organization pages up to 30 minutes, in MP4, MOV or AVI:

```json
"mediaItems": [
  { "type": "video", "url": "https://cdn.example.com/demo.mp4" }
]
```

### Document post

A PDF, PPT, PPTX, DOC or DOCX displays as a swipeable carousel: at most 100 MB and 300 pages, one per post, with no images or videos alongside it. LinkedIn requires a title; set `documentTitle`, or Zernio falls back to the media item `title`, then the filename:

```json
"mediaItems": [
  { "type": "document", "url": "https://cdn.example.com/report.pdf" }
],
"platforms": [
  {
    "platform": "linkedin",
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platformSpecificData": { "documentTitle": "2027 Industry Report" }
  }
]
```

The first page is the cover. Password-protected PDFs do not work.

### Poll

`poll` creates a LinkedIn poll: a question of 1 to 140 characters, 2 to 4 options of 1 to 30 characters each, and a `duration` of `ONE_DAY`, `THREE_DAYS`, `SEVEN_DAYS` (default) or `FOURTEEN_DAYS`. A poll cannot be combined with `mediaItems` or `reshareUrl`, cannot be edited after publishing, and API-created polls are non-sponsored only (they cannot be promoted as ads):

```json
{
  "platform": "linkedin",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "poll": {
      "question": "Which feature should we build next?",
      "options": ["API keys", "Webhooks", "SDKs"],
      "duration": "SEVEN_DAYS"
    }
  }
}
```

### Repost

`reshareUrl` reposts an existing LinkedIn post from its link (the post's "Copy link to post" action) or a `urn:li:share`, `urn:li:ugcPost` or `urn:li:groupPost` URN. The published post is always a reshare authored by your account with the original embedded underneath: with `content` or `customContent` your text is the commentary ("repost with your thoughts"), and with no text it publishes as a text-free reshare. LinkedIn's API cannot create the one-click "Repost" feed treatment where the original author stays the author. `reshareUrl` is mutually exclusive with `mediaItems` and works on personal profiles and organization pages:

```json
{
  "platform": "linkedin",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "reshareUrl": "https://www.linkedin.com/posts/someone_some-post-id"
  }
}
```

### First comment

`firstComment` posts a comment right after publishing. Put external links there, because LinkedIn suppresses posts with a URL in the caption:

```json
{
  "platform": "linkedin",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "firstComment": "Read the full guide here: https://example.com/api-guide"
  }
}
```

### Link preview

A text post with a URL and no media gets an automatic preview card. `disableLinkPreview: true` suppresses it:

```json
{
  "platform": "linkedin",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "disableLinkPreview": true }
}
```

### Post to several organizations

One connected account can post to every organization page the member administers. List them with [LinkedIn organizations](/connect/get-linkedin-organizations):

```bash
curl "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/linkedin-organizations" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "organizations": [
    { "id": "111111111", "name": "Example Corp", "vanityName": "example-corp", "localizedName": "Example Corp" },
    { "id": "222222222", "name": "Example Labs", "vanityName": "example-labs", "localizedName": "Example Labs" }
  ]
}
```

Then repeat the `platforms` entry with the same `accountId` and a different `organizationUrn` (`urn:li:organization:` followed by the organization id). Omit it to post to the account's default organization or personal profile:

```json
"platforms": [
  {
    "platform": "linkedin",
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platformSpecificData": { "organizationUrn": "urn:li:organization:111111111" }
  },
  {
    "platform": "linkedin",
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platformSpecificData": { "organizationUrn": "urn:li:organization:222222222" }
  }
]
```

### Geo-restriction

`geoRestriction.countries` limits who sees the post: only followers in the listed countries. Organization pages only, and LinkedIn rejects the post unless the targeted audience exceeds 300 followers:

```json
{
  "platform": "linkedin",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "geoRestriction": { "countries": ["US", "ES"] }
  }
}
```

`countries` takes up to 25 uppercase ISO 3166-1 alpha-2 codes. Zernio resolves them to LinkedIn geo URNs; 45 countries are mapped, and unsupported codes are skipped silently.

### Edit a published post

[Edit post](/posts/edit-post) replaces the text of a LinkedIn post that is already published, on member profiles and organization pages alike. Edits are text-only and capped at the same 3,000 characters as publishing. There is no time window and no limit on the number of edits. The post keeps its id and LinkedIn shows an "edited" marker on it. Mentions and hashtags in the new text are preserved. Media, polls, articles and reshare targets cannot be changed.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: edited } = await zernio.posts.editPost({
  path: { postId: '65f1c0a9e2b5af0012ab34cd' },
  body: {
    platform: 'linkedin',
    content: 'Updated post text with corrected information',
    accountId: '66b2e19d8c3f5a7e9d0b1c2d'
  }
});

console.log(edited.url);
```
</Tab>
<Tab value="Python">
```python
edited = client.posts.edit_post(
    post_id="65f1c0a9e2b5af0012ab34cd",
    platform="linkedin",
    content="Updated post text with corrected information",
    account_id="66b2e19d8c3f5a7e9d0b1c2d"
)

print(edited["url"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts/65f1c0a9e2b5af0012ab34cd/edit \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "platform": "linkedin",
    "content": "Updated post text with corrected information",
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d"
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "id": "urn:li:share:7200000000000000000",
  "url": "https://www.linkedin.com/feed/update/urn:li:share:7200000000000000000"
}
```

`accountId` picks which copy to edit when the post went to several LinkedIn accounts; without it, the first `linkedin` entry on the post is edited.

## Platform fields

All fields go in `platformSpecificData` on the LinkedIn entry.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `documentTitle` | string | media item `title`, then filename | Title shown on document (PDF/carousel) posts. LinkedIn requires one for document posts. |
| `organizationUrn` | string | (default organization or personal profile) | Post to a specific organization page, `urn:li:organization:123456`. List the options with `GET /v1/accounts/{accountId}/linkedin-organizations`. |
| `firstComment` | string | | Posted as the first comment after publishing. |
| `disableLinkPreview` | boolean | `false` | `true` suppresses the automatic URL preview card. |
| `reshareUrl` | string | | Post link or `urn:li:share` / `urn:li:ugcPost` / `urn:li:groupPost` URN to repost. Mutually exclusive with `mediaItems`. See [Repost](#repost). |
| `poll` | \{question, options, duration?\} | | Create a poll. Cannot be combined with `mediaItems` or `reshareUrl`. See [Poll](#poll). |
| `poll.question` | string | | 1 to 140 characters. |
| `poll.options` | Array\<string\> | | 2 to 4 choices, 1 to 30 characters each. |
| `poll.duration` | `"ONE_DAY"` \| `"THREE_DAYS"` \| `"SEVEN_DAYS"` \| `"FOURTEEN_DAYS"` | `"SEVEN_DAYS"` | How long the poll accepts votes. |
| `geoRestriction` | \{countries\} | | Restrict visibility to up to 25 countries. Organization pages only, 300+ targeted followers. See [Geo-restriction](#geo-restriction). |

## Media requirements

### Images

| Property | Requirement |
|----------|-------------|
| Max images | 20 per post |
| Formats | JPEG, PNG, GIF |
| Max file size | 8 MB per image |
| Recommended | 1200 x 627 px |
| Min dimensions | 552 x 276 px |
| Max dimensions | 8192 x 8192 px |

| Type | Ratio | Dimensions | Use |
|------|-------|------------|-----|
| Landscape | 1.91:1 | 1200 x 627 px | Link shares, standard |
| Square | 1:1 | 1080 x 1080 px | Engagement |
| Portrait | 1:1.25 | 1080 x 1350 px | Mobile feed |

### Videos

| Property | Requirement |
|----------|-------------|
| Max videos | 1 per post |
| Formats | MP4, MOV, AVI |
| Max file size | 5 GB |
| Max duration | 10 minutes (personal), 30 minutes (organization page) |
| Min duration | 3 seconds |
| Resolution | 256 x 144 px to 4096 x 2304 px |
| Aspect ratio | 1:2.4 to 2.4:1 |
| Frame rate | 10 to 60 fps |

Recommended: 1920 x 1080 px, 16:9 or 1:1, 30 fps, H.264 with AAC audio at 192 kbps, 10 to 30 Mbps.

### Documents

| Property | Requirement |
|----------|-------------|
| Max documents | 1 per post |
| Formats | PDF, PPT, PPTX, DOC, DOCX |
| Max file size | 100 MB |
| Max pages | 300 |

### Media URLs

A media URL must be publicly accessible with no authentication, return the media bytes with the correct `Content-Type` header, not redirect to an HTML page, and sit on a fast host. Google Drive, Dropbox, OneDrive and iCloud links return an HTML page instead of the file and fail; test a URL in an incognito window, or upload through the [media endpoint](/guides/media-uploads). Zernio proxies Supabase storage URLs automatically.

## Analytics

Call `GET /v1/analytics?platform=linkedin` ([Analytics API](/analytics/get-analytics)). Personal accounts get metrics only for posts published through Zernio, because LinkedIn returns metrics only for posts authored by the authenticated member.

| Metric | Available |
|--------|-----------|
| Impressions | <Yes /> |
| Reach | <Yes /> |
| Likes | <Yes /> |
| Comments | <Yes /> |
| Shares | <Yes /> |
| Saves | <Yes /> (personal accounts only) |
| Sends | <Yes /> (personal accounts only, LinkedIn messaging) |
| Clicks | <Yes /> (organization accounts only) |
| Views | <Yes /> (video posts only) |

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: analytics } = await zernio.analytics.getAnalytics({
  query: { platform: 'linkedin', fromDate: '2026-08-01', toDate: '2026-08-31' }
});

console.log(analytics.posts);
```
</Tab>
<Tab value="Python">
```python
analytics = client.analytics.get_analytics(
    platform="linkedin",
    from_date="2026-08-01",
    to_date="2026-08-31"
)

print(analytics["posts"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/analytics?platform=linkedin&fromDate=2026-08-01&toDate=2026-08-31" \
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
      "platform": "linkedin",
      "status": "published",
      "publishedAt": "2026-08-14T10:00:05Z",
      "platformPostUrl": "https://www.linkedin.com/feed/update/urn:li:share:7200000000000000000",
      "analytics": {
        "impressions": 8420,
        "reach": 6210,
        "likes": 131,
        "comments": 17,
        "shares": 9,
        "clicks": 240,
        "engagementRate": 1.86
      }
    }
  ]
}
```

Four LinkedIn-only endpoints go deeper:

- [Personal aggregate analytics](/analytics/get-linkedin-aggregate-analytics): totals or a daily series across a personal account's posts, covering impressions, reach, reactions, comments, shares, saves and sends.
- [Organization aggregate analytics](/analytics/get-linkedin-org-aggregate-analytics): impressions, clicks, reactions, engagement rate, follower gains (organic and paid) and page-view metrics for organization pages. The member must be an ADMINISTRATOR of the organization and the account must hold `r_organization_social`, `r_organization_followers` and `r_organization_admin`.
- [Post analytics](/analytics/get-linkedin-post-analytics): per-post metrics by URN for personal and organization accounts; saves and sends are personal-only, organizations return 0.
- [Post reactions](/analytics/get-linkedin-post-reactions): individual reactions with reactor profiles, organization accounts only.

## Inbox

LinkedIn supports comments on organization pages and reactions from either account type; its messaging API is closed to third-party apps, so there are no DMs.

| Feature | Supported |
|---------|-----------|
| List comments on posts | <Yes /> |
| Reply to comments | <Yes /> |
| Delete comments | <Yes /> |
| Like comments | <Yes /> (reactions, on accounts connected with the social-feed scopes) |
| React to a post | <Yes /> (any post, not only your own) |

[Like comment](/comments/like-inbox-comment) takes the composite comment URN the comments endpoints return as `commentId`, and an optional `reactionType` picks the reaction instead of the default `LIKE`. An account connected before Zernio requested the social-feed scopes gets a `403` with code `linkedin_reconnect_required`: reconnect it. [Like post](/comments/like-post) reacts to a post from any connected account, so an executive account can react to the brand's post; it takes the same `reactionType` and the same scopes. You can also read a post's reactions with [Post reactions](/analytics/get-linkedin-post-reactions) on organization accounts.

## What you cannot do

LinkedIn's API does not expose:

- Long-form articles (linkedin.com/article/new/)
- Events
- InMail and DMs
- Newsletters
- Following hashtags or tagging connections
- Mixed media in one post (images with a video, or images with a document)
- The one-click "Repost" feed treatment (reposts are always authored by your account)

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| "Content is a duplicate of urn:li:share:XXXX" (`422`) | Identical or very similar content was already posted | Change the text meaningfully. LinkedIn's duplicate detection is strict, and a minor rephrase may not be enough. |
| "Publishing failed during preflight checks" | Rate limiting or validation caught an issue before publishing | Space posts further apart. Check [Account health](/accounts/get-all-accounts-health). |
| "Publishing failed due to max retries reached" | All 3 retry attempts failed | A temporary issue. Retry, or check LinkedIn's status page. |
| Token expired | The OAuth token expired or was revoked | Reconnect the account. Subscribe to the `account.disconnected` webhook to catch this early. |
| "Cannot mix media types" | The post has images with a video or a document | Use one media type per post. |
| Video processing failed | Codec, duration or aspect ratio is out of spec | H.264, within the duration limit (10 minutes personal, 30 minutes organization) and an aspect ratio between 1:2.4 and 2.4:1. |
| Link preview shows the wrong image | Missing or wrong Open Graph tags on the URL | Fix `og:image` on the site and refresh with the [LinkedIn Post Inspector](https://www.linkedin.com/post-inspector/). |

A `publishNow: true` post that LinkedIn rejects returns `207` with `post.status: "failed"` and the message in `platforms[].errorMessage`:

```json
{
  "message": "Post created but publishing failed",
  "error": "All platforms failed",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "failed",
    "platforms": [
      {
        "platform": "linkedin",
        "status": "failed",
        "errorMessage": "Content is a duplicate of urn:li:share:7200000000000000000"
      }
    ]
  }
}
```

`207` is a 2xx status, so `fetch(...).ok` is `true`; branch on the status code and on `post.status`. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts): the OAuth flow and the organization selection step.
- [Create post](/posts/create-post) and [Edit post](/posts/edit-post): every field of the request.
- [LinkedIn mentions](/linkedin-mentions/get-linkedin-mentions): resolve profile and company URLs to mention URNs for post content.
- [Media uploads](/guides/media-uploads): upload images, videos and documents instead of hosting them.
- [Comments](/comments/list-inbox-comments): the inbox API.
- [Pricing](/pricing): what analytics, the inbox and outbound messages cost, and which replies count.

---
