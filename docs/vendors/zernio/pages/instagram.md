# Instagram

Publish feed posts, carousels, Stories and Reels to Instagram with the Zernio API, with collaborators, user tags, catalog audio, paid partnership labels and location tags.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Publish feed posts, carousels, Stories and Reels to Instagram with `POST /v1/posts` and `platform: "instagram"`. The same account also serves analytics, DMs, comments and comment-to-DM automations.

## Quick reference

| Property | Value |
|----------|-------|
| Character limit | 2,200 (caption) |
| Images per post | 1 (feed), 10 (carousel) |
| Videos per post | 1 |
| Image formats | JPEG, PNG |
| Image max size | 8 MB (auto-compressed) |
| Video formats | MP4, MOV |
| Video max size | 300 MB (feed and Reels), 100 MB (Stories) |
| Video max duration | 90 seconds (Reels), 60 minutes (feed), 60 seconds (Story) |
| Post types | Feed, Carousel, Story, Reel |
| Scheduling | Yes |
| Inbox (DMs) | Yes |
| Inbox (comments) | Yes |
| Comment-to-DM automations | Yes |
| Story-reply automations | Yes |
| Analytics | Yes |

## Before you start

Instagram requires a Business or Creator account; personal accounts cannot post through the API. Every post needs media, so there are no text-only posts. An account can publish 100 posts per rolling 24 hours, all content types combined; read what is left of that window with [Get Instagram publishing limit](/instagram/get-instagram-publishing-limit) and compare against the returned `quotaTotal` rather than hardcoding the cap. The first 125 characters of a caption show before the "more" fold.

## Connect

Call `GET /v1/connect/instagram` with `profileId` and, optionally, `loginMethod` ([connecting accounts guide](/guides/connecting-accounts)). Publishing, analytics, comments and the inbox work the same with either login method; Facebook Login is required for ads scopes, [catalog audio](#reels-with-catalog-audio) and the [paid partnership label](#paid-partnership-label).

### OAuth scopes

With Instagram Login (`loginMethod=instagram_login`, the default) the user authorizes their Instagram professional account directly, with no Facebook Page involved. Ads permissions exist only on Facebook Login, so this method never requests them; to run ads against such an account, connect a [Facebook](/platforms/facebook) account in the same profile and use its token ([Meta Ads](/platforms/meta-ads)).

With Facebook Login (`loginMethod=facebook_login`) the user authorizes a Facebook Page that has a linked Instagram professional account, and every API call for that account runs through the Page. Use it when the customer manages Instagram through a Page and expects the Facebook consent screen. Picking the Page is a second step after OAuth, in standard or headless mode ([platforms requiring secondary selection](/guides/connecting-accounts#platforms-requiring-secondary-selection)). If the profile has ads access, the dialog also requests `ads_management`, `ads_read`, `pages_manage_ads` and `leads_retrieval`, so the same account can drive [Meta Ads](/platforms/meta-ads); call [Connect ads](/connect/connect-ads) with `platform=instagram` to create the ads account.

| Instagram Login | Facebook Login | What it enables |
|-----------------|----------------|-----------------|
| `instagram_business_basic` | `instagram_basic` | Account identity and basic profile data |
| `instagram_business_content_publish` | `instagram_content_publish` | Publish posts, Reels, Stories and carousels |
| `instagram_business_manage_insights` | `instagram_manage_insights` | Post and account analytics |
| `instagram_business_manage_comments` | `instagram_manage_comments` | Read and reply to comments (including the first comment) |
| `instagram_business_manage_messages` | `instagram_manage_messages` | Instagram DMs in the inbox |
| | `pages_show_list` | List the Pages the user manages, to find the linked Instagram account |
| | `pages_read_engagement` | Read the linked Page |
| | `business_management` | Resolve Pages owned through a Business Manager |

## Publish

A plain post becomes a feed post when the media is an image and a Reel when it is a single video. Fields in `platformSpecificData` on the Instagram entry select Stories and change Reel behaviour.

### Feed post

A single image or video in the main feed; no `contentType` is needed.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Golden hour at the pier #photography',
    mediaItems: [
      { type: 'image', url: 'https://cdn.example.com/pier.jpg' }
    ],
    platforms: [
      { platform: 'instagram', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
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
    content="Golden hour at the pier #photography",
    media_items=[
        {"type": "image", "url": "https://cdn.example.com/pier.jpg"}
    ],
    platforms=[
        {"platform": "instagram", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
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
    "content": "Golden hour at the pier #photography",
    "mediaItems": [
      {"type": "image", "url": "https://cdn.example.com/pier.jpg"}
    ],
    "platforms": [
      {"platform": "instagram", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
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
        "platform": "instagram",
        "status": "published",
        "platformPostUrl": "https://www.instagram.com/p/DGx7Yk2ScAb/"
      }
    ]
  }
}
```

Every sample below changes only the `mediaItems` or the `platforms` entry of this request.

### Carousel

Up to 10 items, images and videos mixed. All items share the aspect ratio of the first item:

```json
"mediaItems": [
  { "type": "image", "url": "https://cdn.example.com/photo1.jpg" },
  { "type": "image", "url": "https://cdn.example.com/photo2.jpg" },
  { "type": "video", "url": "https://cdn.example.com/clip.mp4" },
  { "type": "image", "url": "https://cdn.example.com/photo3.jpg" }
]
```

### Story

`contentType: "story"` publishes a Story. Stories disappear after 24 hours, show no caption, and get no link sticker through Instagram's Graph API:

```json
{
  "platform": "instagram",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "contentType": "story" }
}
```

### Reel

A single video publishes as a Reel with no `contentType`. `shareToFeed` (default `true`) controls whether it also appears on the main feed:

```json
{
  "platform": "instagram",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "shareToFeed": false }
}
```

### Reels with catalog audio

`audioConfiguration` attaches a licensed music track or an original sound from Instagram's audio catalog to a Reel. It requires Facebook Login: accounts connected with Instagram Login get a `400` with code `instagram_audio_requires_facebook_login` until reconnected with `loginMethod=facebook_login`.

Find an `audioId` with [Search Instagram audio](/instagram/search-instagram-audio); omit `q` to get what is trending:

```bash
curl -G "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/instagram/audio" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -d "audioType=music" \
  -d "q=summer"
```

Response (`200`):

```json
{
  "audio": [
    {
      "audioId": "482851939985510",
      "title": "Summer Nights",
      "audioType": "music",
      "durationInMs": 182000,
      "displayArtist": "The Example Band",
      "downloadUrl": "https://scontent.cdninstagram.com/o1/v/t2/f2/m86/482851939985510.mp4"
    }
  ]
}
```

Original sounds carry `igUsername` instead of `displayArtist`. `downloadUrl` is a preview that Meta expires after roughly 1.5 days; `GET /v1/accounts/{accountId}/instagram/audio/{audioId}` refreshes it and re-validates a stored id before a scheduled publish. Attach the track:

```json
{
  "platform": "instagram",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "audioConfiguration": {
      "audioId": "482851939985510",
      "audioVolume": 80,
      "videoVolume": 100
    }
  }
}
```

Volumes are integers from 0 to 100, default 100; `videoVolume: 0` mutes the video's own sound. Stories, images and carousels reject `audioConfiguration` at creation. If the track becomes unavailable between scheduling and publish time (removed, region-blocked, licensing change), the post fails with an error naming the cause instead of publishing with different audio; resubmit with another track or without `audioConfiguration`. `audioName` is unrelated: it renames the video's own ("original") audio.

## Platform fields

All fields go in `platformSpecificData` on the Instagram entry.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `contentType` | `"story"` | (feed) | `"story"` publishes a Story. Omit otherwise: single videos publish as Reels, images go to the feed. |
| `shareToFeed` | boolean | `true` | Reels only. `false` shows the Reel in the Reels tab only. |
| `collaborators` | Array\<string\> | | Up to 3 usernames of public Business or Creator accounts. Not for Stories. |
| `userTags` | Array\<\{username, x?, y?, mediaIndex?\}\> | | Images require `x`/`y` (0.0 to 1.0); Reels and videos ignore coordinates; Stories take them optionally. `mediaIndex` picks the carousel slide (0-based, default 0), video slides included. |
| `trialParams` | \{graduationStrategy\} | | Trial Reels, shown only to non-followers. `graduationStrategy` is `"MANUAL"` or `"SS_PERFORMANCE"` (auto-graduate when it performs well). |
| `thumbOffset` | number (ms) | `0` | Offset from video start to use as the Reel cover. Ignored when `instagramThumbnail` is set. |
| `instagramThumbnail` | string (URL) | | Custom Reel cover, JPEG or PNG, recommended 1080 x 1920 px. Takes priority over `thumbOffset`. Also accepted as `reelCover`. |
| `audioName` | string | | Renames the Reel's own audio (replaces "Original Audio"). Set at creation only. |
| `audioConfiguration` | \{audioId, audioVolume?, videoVolume?\} | | Catalog track for a Reel; see [Reels with catalog audio](#reels-with-catalog-audio). Requires `loginMethod=facebook_login`. |
| `muteAudio` | boolean | `false` | Reels, Stories and video carousel slides; ignored for images. Instagram has no mute parameter, so Zernio strips the audio track before sending and the published video is permanently silent; if stripping fails, the post fails rather than publishing with sound. Videos above 200 MB cannot be muted. |
| `isAiGenerated` | boolean | `false` | Instagram labels the post as containing AI-generated images or video (not AI-written captions). Feed posts, Reels, Stories and carousels. |
| `isPaidPartnership` | boolean | `false` | Shows the "Paid partnership" label. Feed posts, Reels and carousels; Stories reject it at creation with a `400`. Requires `loginMethod=facebook_login`: Instagram Login accounts get a `400` with code `instagram_paid_partnership_requires_facebook_login`. Implied by `brandedContentSponsors`. See [Paid partnership label](#paid-partnership-label). |
| `brandedContentSponsors` | Array\<string\> | | Up to 2 sponsors, each an Instagram username (leading @ optional) or numeric Instagram user id, of public Business or Creator accounts. Same login and content-type rules as `isPaidPartnership`. |
| `commentsEnabled` | boolean | `true` | `false` turns comments off right after publishing (Zernio publishes first, then disables). Feed posts, Reels and carousels; ignored for Stories. Both login methods. Best-effort: if Instagram rejects the toggle, the post stays up with comments on; turn them off in the Instagram app. |
| `locationId` | string | | Numeric id of a Facebook Page that has location data, not a place name. Feed posts, Reels and the carousel as a whole; not single slides, and a Story with `locationId` is rejected at creation with a `400`. See [Location tags](#location-tags). |
| `firstComment` | string | | Posted as the first comment after publishing. Feed posts and carousels, not Stories. The place for links, because captions have none. |

### Paid partnership label

`isPaidPartnership: true` shows the label; `brandedContentSponsors` names the brand as well and turns the label on by itself:

```json
{
  "platform": "instagram",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "brandedContentSponsors": ["brandpartner"] }
}
```

Zernio resolves sponsor usernames at publish time through Meta's Business Discovery API on the publishing account; a sponsor that cannot be resolved fails the post with an error naming it. Pass the numeric id to skip the lookup. A brand that has pre-approved you as a creator shows "Paid partnership with @brand" at once; otherwise the post publishes with the plain label, the brand receives an approval request in Instagram, and the name appears once they approve. No approval is needed to publish. Instagram does not return the label or the sponsors when reading the post back, so Get post echoes the values you sent in `platformSpecificData`.

### Location tags

Zernio has no location search endpoint yet. Find the Page id in Facebook (the Page's "About" section or its URL) or through Meta's Pages Search API:

```json
{
  "platform": "instagram",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": { "locationId": "105890561436614" }
}
```

A Page that does not exist or has no location data fails the post at publish time with "Instagram rejected locationId: the Facebook Page has no location data or does not exist."

## Media requirements

Files above these limits are compressed; original files are preserved.

### Images

| Property | Feed post | Story | Carousel |
|----------|-----------|-------|----------|
| Max images | 1 | 1 | 10 |
| Formats | JPEG, PNG | JPEG, PNG | JPEG, PNG |
| Max file size | 8 MB | 8 MB | 8 MB each |
| Recommended | 1080 x 1350 px | 1080 x 1920 px | 1080 x 1080 px |

Feed posts and carousels accept aspect ratios from 9:16 (0.5625, 1080 x 1920 px, the tallest) through 4:5 (1080 x 1350 px, best engagement) and 1:1 (1080 x 1080 px) to 1.91:1 (1080 x 566 px, the widest). Stories and Reels are 9:16. Images outside the feed range must be posted as Stories or Reels.

### Videos

| Property | Feed | Reel | Story |
|----------|------|------|-------|
| Formats | MP4, MOV | MP4, MOV | MP4, MOV |
| Max file size | 300 MB | 300 MB | 100 MB |
| Max duration | 60 minutes | 90 seconds | 60 seconds |
| Min duration | 3 seconds | 3 seconds | 3 seconds |
| Aspect ratio | 4:5 to 1.91:1 | 9:16 | 9:16 |
| Resolution | 1080 px wide | 1080 x 1920 px | 1080 x 1920 px |
| Codec | H.264 | H.264 | H.264 |
| Frame rate | 30 fps | 30 fps | 30 fps |

### Media URLs

A media URL must be publicly accessible with no authentication, return the media bytes with the correct `Content-Type` header, not redirect to an HTML page, and sit on a fast host. Google Drive, Dropbox, OneDrive and iCloud sharing links return an HTML page instead of the file, so Instagram's servers cannot fetch from them; use a direct media URL or upload through the [media endpoint](/guides/media-uploads). Test a URL in an incognito window: a webpage instead of the raw file means the post will fail. Zernio proxies Supabase storage URLs automatically, so they work without any change.

## Analytics

Call `GET /v1/analytics?platform=instagram` ([Analytics API](/analytics/get-analytics)).

| Metric | Available |
|--------|-----------|
| Impressions | <Yes /> |
| Reach | <Yes /> |
| Likes | <Yes /> |
| Comments | <Yes /> |
| Shares | <Yes /> |
| Saves | <Yes /> |
| Views | <Yes /> |

Three Instagram-only endpoints go deeper:

- [Account insights](/analytics/get-instagram-account-insights): account-level reach, views, accounts engaged, total interactions, follows and unfollows, profile link taps. Only `reach` supports `metricType=time_series`; Instagram serves every other metric as `total_value` only.
- [Follower history](/analytics/get-instagram-follower-history): a daily follower count series plus `followers_gained` and `followers_lost`, served from Zernio's daily snapshots because Instagram removed `follower_count` from `/insights` in Graph API v22 and never exposed a historical daily series.
- [Demographics](/analytics/get-instagram-demographics): audience by age, city, country or gender. Requires at least 100 followers.

### Stories

Two endpoints read Stories while they are live, and Zernio keeps their final metrics from Meta's `story_insights` webhook so they stay queryable after the 24 hours.

- [List active stories](/instagram/list-instagram-stories): `GET /v1/accounts/{accountId}/instagram/stories` returns the live stories with `mediaType`, `permalink`, `mediaUrl`, `thumbnailUrl` and `timestamp`. Meta excludes live videos, reshared stories and copyright-flagged media, and `caption`, `likeCount` and `commentsCount` do not apply to stories.
- [Story insights](/instagram/get-instagram-story-insights): `GET /v1/accounts/{accountId}/instagram/stories/{storyId}/insights` returns `views`, `reach`, `replies`, `shares`, `profileVisits`, `follows`, `totalInteractions` and the navigation breakdown (`tapsForward`, `tapsBack`, `exits`, `swipesForward`). `source` is `live` (fetched from Meta), `cached` (expired, webhook payload captured) or `unavailable` (expired, no payload, typical when the account connected after the story expired). Counts below 5 may come back as 0 because of Meta's privacy floor.

## Inbox

Instagram supports DMs and comments. New comments, replies, hiding and deleting all work; liking a comment is a limited release described under [Comments](#comments).

### Direct messages

| Feature | Supported |
|---------|-----------|
| List conversations | <Yes /> |
| Fetch messages | <Yes /> |
| Send text messages | <Yes /> |
| Send attachments | <Yes /> (images, videos, audio via URL) |
| Quick replies | <Yes /> (up to 13, Meta quick_replies) |
| Buttons | <Yes /> (up to 3, generic template) |
| Carousels | <Yes /> (generic template, up to 10 elements) |
| React to a message | <Yes /> (any emoji) via [Add message reaction](/messages/add-message-reaction) and [Remove message reaction](/messages/remove-message-reaction) |
| Message tags | <Yes /> (`HUMAN_AGENT` only) |
| Archive and unarchive | <Yes /> |

Reactions a customer adds or removes arrive on the [`reaction.received`](/webhooks/inbox#reactionreceived) webhook; accounts connected before August 2026 need their Meta webhook registration refreshed first, sending is unaffected. To message outside the 24-hour window, send `messageTag: "HUMAN_AGENT"` with `messagingType: "MESSAGE_TAG"`.

Attachments:

| Type | Formats | Max size |
|------|---------|----------|
| Image | PNG, JPEG | 8 MB |
| Video | MP4, OGG, AVI, MOV, WEBM | 25 MB |
| Audio | AAC, M4A, WAV, MP4 | 25 MB |
| File | PDF | 25 MB |

Instagram rejects every other container, including MP3 and OGG/Opus audio. Attachment URLs must be public HTTPS with no authentication or redirects and return a `Content-Type` matching the file, for example `audio/mp4` for M4A.

#### Instagram profile data

Participants and webhook senders can carry an `instagramProfile` object: `isFollower`, `isFollowing`, `followerCount`, `isVerified` and, on conversations only, `fetchedAt`. It appears in `GET /v1/inbox/conversations` and `GET /v1/inbox/conversations/{id}`, on `message.sender` in `message.received`, on `comment.author` in `comment.received` when the commenter has messaged you before, and on demand from `GET /v1/accounts/{accountId}/follow-status/{userId}` for any Instagram-scoped user id.

<Callout type="warn">
Meta reveals the follow relationship only for people who have messaged you, and commenting does not grant that consent. For someone who has never sent your account a DM, `follow-status` returns `200` with `isFollower: null` and `unavailableReason: "consent_required"`, and `comment.received` omits `instagramProfile`. Treat a missing value as unknown, never as "not a follower"; to follower-gate a comment automation, use its [audience rules](#comment-to-dm-automations) instead.
</Callout>

### Ice breakers

Ice breakers are the prompts shown when someone starts a new DM conversation: up to 4, each question at most 80 characters. Manage them with `GET`, `PUT` and `DELETE /v1/accounts/{accountId}/instagram-ice-breakers` ([Account settings](/account-settings/get-instagram-ice-breakers)).

### Comments

| Feature | Supported |
|---------|-----------|
| List comments on posts | <Yes /> |
| Post a new top-level comment | <Yes /> |
| Reply to comments | <Yes /> |
| Delete comments | <Yes /> |
| Like comments | Limited release (see below) |
| Hide and unhide comments | <Yes /> |
| Send a private reply (DM after a comment) | <Yes /> (text plus up to 13 quick replies or 1 to 3 inline buttons, 7-day window, one per comment) |

Liking a comment is in limited release. It needs Meta's `instagram_manage_engagement` permission, which this app holds under Standard Access, so the call works for admins, developers and testers of Zernio's Meta app and returns a `403` with code `PLATFORM_BETA_RESTRICTED` for everyone else. It covers comments and replies on feed posts, reels and carousels, and only on an account connected through Facebook Login: an Instagram Login connection returns a `400` with code `instagram_likes_require_facebook_login`.

#### Comment-to-DM automations

Keyword-triggered auto-DMs, created with [Create comment automation](/comment-automations/create-comment-automation). `template` sends a product card instead of the plain `dmMessage`: an image, a title, a subtitle for the description or price, and up to 3 `url` or `postback` buttons (no phone buttons); up to 10 elements render as a swipeable carousel. The card is mutually exclusive with `dmMessage` plus `buttons`, its url buttons are click-tracked like flat buttons, and it renders in the Instagram mobile app only, because Meta does not support the generic template on Instagram desktop web.

```json
{
  "template": {
    "type": "generic",
    "elements": [{
      "imageUrl": "https://example.com/product.jpg",
      "title": "Handmade Leather Bag",
      "subtitle": "Free shipping, $49",
      "buttons": [{ "type": "url", "title": "Order Now", "url": "https://example.com/order" }]
    }]
  }
}
```

`audience` restricts who gets the DM (Instagram only; Facebook automations reject it because Meta exposes the follow relationship on Instagram only):

| Field | Values | What it does |
|-------|--------|--------------|
| `followerStatus` | `any` (default), `follower`, `non_follower` | Only DM followers, or only non-followers |
| `minFollowerCount` | integer | Skip commenters below this follower count |
| `whenUnknown` | `send` (default), `skip`, `verify` | What to do when Instagram will not reveal the follow relationship |

Because of the consent rule above, a first-time commenter is unresolvable until they message you, so `whenUnknown` decides most outcomes. `send` delivers anyway, so a real customer is never silently dropped. `skip` stays silent. `verify` sends `followGate.message` with a confirm button; the tap is itself a message, which grants consent, so the follow check resolves and the real DM (or `followGate.notFollowingMessage`) goes out. Anyone who has messaged you before is checked at once and never sees this step.

```json
{
  "audience": { "followerStatus": "follower", "whenUnknown": "verify" },
  "followGate": {
    "message": "Follow the account, then tap below to unlock the link 👇",
    "buttonLabel": "I'm following ✅",
    "notFollowingMessage": "Looks like you're not following yet. Follow and try again 🙌"
  }
}
```

### Webhooks

Instagram emits every message lifecycle event: `message.received`, `message.sent`, `message.edited`, `message.deleted` (the sender unsent it) and `message.read`. The [webhooks page](/webhooks) has the payloads. Zernio stores messages locally: live messages arrive through webhooks, and on connect Zernio replays the DM history the account already has on Meta, up to 500 conversations per account and the newest 500 messages per conversation, including conversations that began before the account was connected. The replay runs in the background, fires no webhooks, and arrives already read, so it never affects unread counts; read it from [List inbox conversations](/messages/list-inbox-conversations).

The `message.deleted` payload keeps the original `text` and `attachments`, so API consumers can read pre-delete content for moderation or compliance. The Zernio dashboard hides that content.

## What you cannot do

Instagram's API does not expose:

- Story stickers (polls, questions, links, countdowns)
- Location search by name (pass a Facebook Page id in `locationId` instead)
- Going live
- Guides
- Filters
- Product tags
- Posting to personal accounts (Business or Creator only)

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| "Cannot process video from this URL. Instagram cannot fetch videos from Google Drive, Dropbox, or OneDrive." | A cloud storage sharing link instead of a direct media URL | Use a direct media URL ([Media URLs](#media-urls)). |
| "You have reached the maximum of 100 posts per day allowed for your account." | Instagram's rolling 24-hour limit, every content type counted | Reduce posting volume. |
| "Instagram blocked your request." | Automation detection | Reduce posting frequency, vary content, wait before retrying. |
| "Duplicate content detected." | Identical content published recently | Change the caption or media. |
| "Media fetch failed, retrying... (failed after 3 attempts)" | Zernio could not download the media | Check that the URL is public and returns media bytes, not an HTML page. |
| "Instagram access token expired." | The OAuth token expired | Reconnect the account. The `account.disconnected` webhook catches this early. |
| No DM history after connecting (new messages still arrive) | The user turned message access off on their own account | Instagram > Settings > Website permissions > Connected tools: turn it on, then reconnect. |

A `publishNow: true` post that Instagram rejects returns `207` with `post.status: "failed"` and the message in `platforms[].errorMessage`:

```json
{
  "message": "Post created but publishing failed",
  "error": "All platforms failed",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "failed",
    "platforms": [
      {
        "platform": "instagram",
        "status": "failed",
        "errorMessage": "Cannot process video from this URL. Instagram cannot fetch videos from Google Drive, Dropbox, or OneDrive."
      }
    ]
  }
}
```

`207` is a 2xx status, so `fetch(...).ok` is `true`; branch on the status code and on `post.status`. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts): the OAuth flow for Instagram Login and Facebook Login.
- [Create post](/posts/create-post): every field of the request.
- [Media uploads](/guides/media-uploads): upload images and videos instead of hosting them.
- [Messages](/messages/list-inbox-conversations) and [Comments](/comments/list-inbox-comments): the inbox API.
- [Account settings](/account-settings/get-instagram-ice-breakers): ice breakers.

---
