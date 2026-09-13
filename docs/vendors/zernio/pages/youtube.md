# YouTube

Publish videos and Shorts to YouTube with the Zernio API, with custom thumbnails, visibility, playlists, COPPA and AI disclosure flags, and description edits after publishing.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Publish videos and Shorts to YouTube with `POST /v1/posts` and `platform: "youtube"`. The same account serves analytics and comments.

## Quick reference

| Property | Value |
|----------|-------|
| Title limit | 100 characters |
| Description limit | 5,000 characters |
| Tags | Top-level `tags` array on the create request; 100 characters per tag, 500 combined |
| Videos per post | 1 |
| Video formats | MP4, MOV, AVI, WMV, FLV, 3GP, WebM |
| Video max size | 256 GB |
| Video max duration | 15 minutes (unverified channel), 12 hours (verified) |
| Thumbnail formats | JPEG, PNG, GIF |
| Thumbnail max size | 2 MB |
| Post types | Video, Shorts |
| Scheduling | Yes (a public video is uploaded early as private and released by YouTube at the scheduled time) |
| Editing published posts | Yes (description only; title and tags through a separate endpoint) |
| Inbox (comments) | Yes |
| Inbox (DMs) | No (YouTube has no DM system) |
| Analytics | Yes |

## Before you start

YouTube requires a channel owned by the Google identity you authorize, on a personal Google account or a Brand Account; see [Brand Accounts and multiple channels](#brand-accounts-and-multiple-channels). Every post is exactly one video, so there are no image-only or text-only posts. Unverified channels are limited to 15-minute videos; verify the channel by phone at [youtube.com/verify](https://www.youtube.com/verify) for longer uploads. Daily upload quotas vary by channel, and Shorts are detected from duration and aspect ratio, not chosen with a flag.

<Callout type="warn">
If a channel is suspended, every upload fails with a `403`. Call [Account health](/accounts/get-all-accounts-health) before scheduling posts to a channel you do not control.
</Callout>

## Connect

Call `GET /v1/connect/youtube` with `profileId` on [Get OAuth connect URL](/connect/get-connect-url). The [connecting accounts guide](/guides/connecting-accounts) covers the OAuth flow and [scopes](/guides/connecting-accounts#scopes) in general; [Account health](/accounts/get-all-accounts-health) reports what a connected account can do with the scopes the user granted.

### OAuth scopes

| Scope | What it enables |
|-------|-----------------|
| `https://www.googleapis.com/auth/youtube.upload` | Upload videos to the channel |
| `https://www.googleapis.com/auth/youtube` | Manage the channel: video metadata, playlists, thumbnails |
| `https://www.googleapis.com/auth/youtube.force-ssl` | Read and post comments |
| `https://www.googleapis.com/auth/yt-analytics.readonly` | Channel and video analytics |

### Brand Accounts and multiple channels

There is no channel picker. YouTube connects straight after Google's OAuth screen, and the channel Zernio connects is the one owned by the Google identity you pick in Google's account chooser. Zernio forces that chooser on every connect (`prompt=select_account`), so the choice is always yours to make:

- A channel on your personal Google account: pick your personal identity.
- A channel that lives on a Brand Account: pick the Brand Account entry in the chooser, not your personal identity. You need owner or manager access to the Brand Account at [account.google.com/brandaccounts](https://account.google.com/brandaccounts).
- YouTube Studio "Manage access" permissions do not grant API access. Someone added as an editor or manager only inside YouTube Studio cannot connect that channel; a Brand Account owner or manager has to do it.

If the identity you picked owns no channel (personal identity chosen by mistake, Studio-only access, or no channel created yet), the connect fails with `We couldn't find a YouTube channel for the Google account you authorized...`. Restart the flow and pick the right entry in the chooser.

A profile holds one YouTube channel. To connect another one, create a second [profile](/guides/profiles), select it, start the YouTube connect again, and pick the other channel's identity in the chooser. Each channel then has its own `accountId`.

## Publish

A plain post becomes a public video whose title is the first line of `content` and whose description is the whole of `content`. YouTube classifies it as a Short on its own when it is 3 minutes or shorter and vertical.

### Video

Long-form content: longer than 3 minutes or horizontal. 16:9 is the recommended aspect ratio, and a `thumbnail` on the media item sets the custom cover.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'In this tutorial, I walk through building a REST API from scratch.\n\n#programming #tutorial',
    tags: ['rest api', 'node.js', 'backend tutorial'],
    mediaItems: [{
      type: 'video',
      url: 'https://cdn.example.com/long-form-video.mp4',
      thumbnail: 'https://cdn.example.com/thumbnail.jpg'
    }],
    platforms: [{
      platform: 'youtube',
      accountId: '66b2e19d8c3f5a7e9d0b1c2d',
      platformSpecificData: {
        title: 'Build a REST API from scratch',
        visibility: 'public',
        categoryId: '27',
        madeForKids: false
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
    content="In this tutorial, I walk through building a REST API from scratch.\n\n#programming #tutorial",
    tags=["rest api", "node.js", "backend tutorial"],
    media_items=[{
        "type": "video",
        "url": "https://cdn.example.com/long-form-video.mp4",
        "thumbnail": "https://cdn.example.com/thumbnail.jpg"
    }],
    platforms=[{
        "platform": "youtube",
        "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
        "platformSpecificData": {
            "title": "Build a REST API from scratch",
            "visibility": "public",
            "categoryId": "27",
            "madeForKids": False
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
    "content": "In this tutorial, I walk through building a REST API from scratch.\n\n#programming #tutorial",
    "tags": ["rest api", "node.js", "backend tutorial"],
    "mediaItems": [{
      "type": "video",
      "url": "https://cdn.example.com/long-form-video.mp4",
      "thumbnail": "https://cdn.example.com/thumbnail.jpg"
    }],
    "platforms": [{
      "platform": "youtube",
      "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
      "platformSpecificData": {
        "title": "Build a REST API from scratch",
        "visibility": "public",
        "categoryId": "27",
        "madeForKids": false
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
        "platform": "youtube",
        "status": "published",
        "platformPostUrl": "https://www.youtube.com/watch?v=dQw4w9WgXcQ"
      }
    ]
  }
}
```

`tags` sits at the top level of the request, alongside `content`, and reaches YouTube as `snippet.tags` on the upload. Zernio strips a leading `#`, splits a comma-joined entry into separate tags and drops duplicates, then keeps tags in order until the combined length would pass 500 characters. A single tag longer than 100 characters is skipped.

Every sample below changes only the `platforms` entry of this request.

### Shorts

YouTube detects Shorts on its own: a video that is 3 minutes or shorter and vertical (9:16) is classified as a Short. There is no post type or flag to set, so the request is the one above with a short vertical video. Videos under 15 seconds loop automatically, and custom thumbnails are not supported for Shorts through the API.

### Playlists

`playlistId` adds the video to an existing playlist after upload, for immediate and scheduled uploads alike. Without it the video is uploaded normally. List the channel's playlists with `GET /v1/accounts/{accountId}/youtube-playlists` ([List YouTube playlists](/connect/get-youtube-playlists)):

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: playlists } = await zernio.connect.getYoutubePlaylists({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

console.log(playlists.playlists);
```
</Tab>
<Tab value="Python">
```python
playlists = client.connect.get_youtube_playlists(
    account_id="66b2e19d8c3f5a7e9d0b1c2d"
)

print(playlists["playlists"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/youtube-playlists" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "playlists": [
    {
      "id": "PLxxxxxxxxxxxxx",
      "title": "Tutorials",
      "privacy": "public",
      "itemCount": 12
    }
  ],
  "defaultPlaylistId": null
}
```

Then pass the id when creating the post:

```json
{
  "platform": "youtube",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "title": "Build a REST API from scratch",
    "visibility": "public",
    "playlistId": "PLxxxxxxxxxxxxx"
  }
}
```

`PUT /v1/accounts/{accountId}/youtube-playlists` ([Set default YouTube playlist](/connect/update-youtube-default-playlist)) stores a default playlist on the account to prefill your own UI. It does not apply to posts that omit `playlistId`:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: saved } = await zernio.connect.updateYoutubeDefaultPlaylist({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: { defaultPlaylistId: 'PLxxxxxxxxxxxxx', defaultPlaylistName: 'Tutorials' }
});

console.log(saved.success);
```
</Tab>
<Tab value="Python">
```python
saved = client.connect.update_youtube_default_playlist(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    default_playlist_id="PLxxxxxxxxxxxxx",
    default_playlist_name="Tutorials"
)

print(saved["success"])
```
</Tab>
<Tab value="curl">
```bash
curl -X PUT https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/youtube-playlists \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "defaultPlaylistId": "PLxxxxxxxxxxxxx",
    "defaultPlaylistName": "Tutorials"
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{ "success": true }
```

### Scheduling

A post with `scheduledFor` in the future runs in this order:

1. Zernio uploads the video ahead of its scheduled time, so YouTube has finished processing it before the video goes live.
2. A video targeting `"public"` goes up as `"private"` and carries YouTube's own `publishAt`, so YouTube releases it at the scheduled second. A video targeting `"private"` or `"unlisted"` is uploaded with that visibility and never changes.
3. A video URL exists as soon as the upload finishes, but the video is not publicly accessible before the release.
4. `firstComment` is posted at the scheduled time, not at upload time.

```json
{
  "platform": "youtube",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "scheduledFor": "2027-01-01T12:00:00",
  "platformSpecificData": {
    "title": "Build a REST API from scratch",
    "visibility": "public",
    "firstComment": "Chapters and source code are in the description."
  }
}
```

Set `timezone` on the request so the scheduled time is read in the right zone; see [post lifecycle](/guides/post-lifecycle).

### Edit a published video

[Edit post](/posts/edit-post) replaces the video description only. There is no time window and no limit on the number of edits, and the video id does not change, so the video keeps its URL. The title is left exactly as it was, including when it was derived from the first line of `content` at publish time. Title, tags, thumbnail and visibility changes go through [Update post metadata](/posts/update-post-metadata), which also works on videos uploaded outside Zernio when you pass `videoId` and `accountId` with `_` as the post id.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: edited } = await zernio.posts.editPost({
  path: { postId: '65f1c0a9e2b5af0012ab34cd' },
  body: {
    platform: 'youtube',
    content: 'Updated description with corrected chapter timestamps.'
  }
});

console.log(edited.url);
```
</Tab>
<Tab value="Python">
```python
edited = client.posts.edit_post(
    post_id="65f1c0a9e2b5af0012ab34cd",
    platform="youtube",
    content="Updated description with corrected chapter timestamps."
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
    "platform": "youtube",
    "content": "Updated description with corrected chapter timestamps."
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "id": "dQw4w9WgXcQ",
  "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
  "message": "youtube post edited successfully"
}
```

The new description is sanitized before it is written: angle brackets (`<` and `>`) are stripped, and anything past 5,000 characters is truncated. Neither one fails the call, so a request that hits either rule still returns success with the video id and URL. If the post was published to several YouTube channels, pass `accountId` to pick which copy to edit; without it, the first `youtube` entry on the post is edited.

## Platform fields

All fields go in `platformSpecificData` on the YouTube entry.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `title` | string | First line of `content`, or `"Untitled Video"` | Video title, at most 100 characters. |
| `visibility` | `"public"` \| `"private"` \| `"unlisted"` | `"public"` | Who can see the video. |
| `madeForKids` | boolean | `false` | Marks the video as child-directed for COPPA. `true` permanently disables comments, the notification bell, personalized ads, end screens and cards on the video. YouTube may block views when the flag is never set. |
| `containsSyntheticMedia` | boolean | `false` | Discloses that the video contains synthetic content that could be mistaken for real. YouTube may add a label to the video. |
| `categoryId` | string | `"22"` (People & Blogs) | Video category. Common values: `"1"` Film, `"10"` Music, `"20"` Gaming, `"22"` People & Blogs, `"27"` Education, `"28"` Science & Technology. |
| `playlistId` | string | | Playlist to add the video to after upload. See [Playlists](#playlists). |
| `firstComment` | string | | Posted and pinned as the first comment, at most 10,000 characters. Posted immediately with `publishNow`, at the scheduled time otherwise. |

## Media requirements

Files above these limits are rejected: 256 GB per video, 15 minutes on an unverified channel, 2 MB per thumbnail after compression. Large videos (1 GB or more) can take 30 to 60 minutes or longer to process on YouTube's side; the video shows a "processing" state meanwhile, so do not retry the upload.

### Videos

| Property | Shorts | Video |
|----------|--------|-------|
| Max duration | 3 minutes | 12 hours (verified), 15 minutes (unverified) |
| Min duration | 1 second | 1 second |
| Max file size | 256 GB | 256 GB |
| Formats | MP4, MOV, AVI, WMV, FLV, 3GP, WebM | MP4, MOV, AVI, WMV, FLV, 3GP, WebM |
| Aspect ratio | 9:16 (vertical) | 16:9 (horizontal) |
| Resolution | 1080 x 1920 px | 1920 x 1080 px (1080p) |

Recommended encoding:

| Property | Shorts | Video |
|----------|--------|-------|
| Resolution | 1080 x 1920 px | 3840 x 2160 px (4K) |
| Frame rate | 30 fps | 24 to 60 fps |
| Codec | H.264 | H.264 or H.265 |
| Audio | AAC, 128 kbps | AAC, 384 kbps |
| Bitrate | 10 Mbps | 35 to 68 Mbps (4K) |

### Custom thumbnails

Custom thumbnails work on videos only, not Shorts. Set `thumbnail` on the video media item.

| Property | Requirement |
|----------|-------------|
| Format | JPEG, PNG, GIF |
| Max size | 2 MB |
| Recommended resolution | 1280 x 720 px (16:9) |
| Min width | 640 px |

Zernio enforces YouTube's rules before upload: JPEG, PNG or GIF, and 2 MB at most (oversized images are compressed first, and rejected if they are still over 2 MB). YouTube itself only accepts custom thumbnails on phone-verified channels ([youtube.com/verify](https://www.youtube.com/verify)). On an unverified channel the video still uploads and publishes, only the thumbnail is skipped; Zernio remembers the refusal for 7 days and does not retry thumbnails on that channel until then, so after verifying allow up to a week for thumbnails to resume.

### Media URLs

The URL must return the video bytes, not an HTML page, with no authentication and no expired link. Or upload through the [media endpoint](/guides/media-uploads).

## Analytics

Call `GET /v1/analytics?platform=youtube` ([Analytics API](/analytics/get-analytics)).

| Metric | Available |
|--------|-----------|
| Likes | <Yes /> |
| Comments | <Yes /> |
| Shares | <Yes /> (through Daily views only) |
| Views | <Yes /> |

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: analytics } = await zernio.analytics.getAnalytics({
  query: { platform: 'youtube', fromDate: '2026-08-01', toDate: '2026-08-31' }
});

console.log(analytics.posts);
```
</Tab>
<Tab value="Python">
```python
analytics = client.analytics.get_analytics(
    platform="youtube",
    from_date="2026-08-01",
    to_date="2026-08-31"
)

print(analytics["posts"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/analytics?platform=youtube&fromDate=2026-08-01&toDate=2026-08-31" \
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
      "platform": "youtube",
      "status": "published",
      "publishedAt": "2026-08-14T10:00:05Z",
      "platformPostUrl": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
      "analytics": {
        "likes": 342,
        "comments": 28,
        "shares": 0,
        "views": 15420
      }
    }
  ]
}
```

Four YouTube-only endpoints go deeper:

- [Daily views](/analytics/get-youtube-daily-views): per-day views, watch time, subscriber changes and per-day likes, comments and shares for one video. Data has a 2 to 3 day delay.
- [Channel insights](/analytics/get-youtube-channel-insights): channel-level views, watch time, average view duration and subscribers gained and lost, without looping through every video. Impressions and impressions click-through rate (the thumbnail metrics in YouTube Studio) are not exposed by YouTube's Analytics API v2 for any principal type; the only way to get those is a manual Studio CSV export.
- [Demographics](/analytics/get-youtube-demographics): audience by age, gender and country, for the channel or one video. Age and gender values are viewer percentages (0 to 100), country values are view counts. Based on signed-in viewers only, with a 2 to 3 day delay.
- [Video retention](/analytics/get-youtube-video-retention): the audience retention curve of one video, up to 100 points over the whole date range.

## Transcripts and captions

To transcribe a YouTube video, read the caption track YouTube already holds for it rather than downloading the file and running your own transcription. Call `GET /v1/accounts/{accountId}/youtube-captions` with `videoId` ([Get a YouTube video transcript](/connect/get-youtube-captions)), for one of the connected channel's own videos. Auto-generated (ASR) tracks count: YouTube serves them to the channel owner, which is what the connected account is. An uploaded track wins over an auto-generated one for the same language.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: transcript } = await zernio.connect.getYoutubeCaptions({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  query: { videoId: 'dQw4w9WgXcQ', language: 'en' }
});

console.log(transcript.text);
```
</Tab>
<Tab value="Python">
```python
transcript = client.connect.get_youtube_captions(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    video_id="dQw4w9WgXcQ",
    language="en"
)

print(transcript["text"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/youtube-captions?videoId=dQw4w9WgXcQ&language=en" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), trimmed:

```json
{
  "videoId": "dQw4w9WgXcQ",
  "language": "en",
  "trackKind": "asr",
  "source": "cache",
  "fetchedAt": "2026-08-27T21:09:54.000Z",
  "text": "Hey, this is Mickey. I'm the founder of this portfolio of three websites.",
  "cues": [
    { "start": 1.6, "end": 8.88, "text": "Hey, this is Mickey. I'm the founder of" }
  ]
}
```

`format=srt` returns the raw SubRip body in `srt` instead of `cues`; `text` is there either way. The first read downloads from YouTube and costs 200 quota units, and Zernio stores the result, so `source` reads `youtube` once and `cache` afterwards. Pass `refresh=true` only when the captions changed on YouTube, since that spends the quota again. `availableTracks` lists every track on the video so you can request another language.

Only videos owned by the connected channel are readable; anything else is a `404`. A video with no track in the requested language is also a `404`, with `code: "captions_not_found"`. YouTube generates auto-captions only for videos with recognizable speech and can take a few hours after upload to publish them, so treat that `404` as "not yet" rather than "never". `contentDetails.caption` in YouTube's own API reads `false` on videos that do have a serving auto-generated track, so it is not a usable availability signal.

## Inbox

YouTube supports comments only; the platform has no DMs.

| Feature | Supported |
|---------|-----------|
| List comments on videos | <Yes /> |
| Reply to comments | <Yes /> |
| Delete comments | <Yes /> |
| Moderate comments | <Yes /> (YouTube only: approve, reject or hold, with an optional author ban) |
| Like comments | <No /> (no API available) |

Read and reply with the [Comments API](/comments/list-inbox-comments). Work a moderation queue with `POST /v1/inbox/comments/{postId}/{commentId}/moderation`: `moderationStatus` takes `published` to approve, `rejected` to remove or `heldForReview` to send it back to the queue, and `banAuthor: true` (valid only alongside `rejected`) auto-rejects that author from then on. You have to own the channel or the video ([Set comment moderation status](/comments/set-comment-moderation)).

Like a video as any connected channel with `POST /v1/inbox/posts/{postId}/like`, and clear the rating again with `DELETE`. Each call spends 50 of the project's 10,000 daily quota units, the tightest per-day ceiling of any platform here, and a video whose owner turned ratings off returns `403`.

## What you cannot do

YouTube's API does not expose:

- Community posts
- Going live or scheduling Premieres
- End screens, cards or chapters (timestamps in the description do work)
- Monetization settings
- Creating or deleting playlists (you can list playlists and add videos to an existing one)
- Disliking a video (YouTube's rating call takes a like or no rating, so a like can only be set or cleared)
- Uploading captions or subtitles (reading an existing track is supported; see [Transcripts and captions](#transcripts-and-captions))
- Liking comments

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| "The YouTube account of the authenticated user is suspended." (`403`) | YouTube suspended the channel | Check the channel status on YouTube. Use [Account health](/accounts/get-all-accounts-health). |
| "Social account not found" | The account was disconnected or deleted from Zernio | Reconnect the YouTube account. Subscribe to the `account.disconnected` webhook. |
| "Account was deleted" | The user deleted the account | Reconnect the account. |
| "Failed to fetch video from URL: 404" | The video URL returned a 404 | Check that the URL is still valid and public. Links expire on some hosts. |
| "YouTube permission error: Ensure the channel has required scopes and features enabled." | The OAuth token lacks a required scope | Reconnect the YouTube account and grant every scope. |
| "YouTube upload initialization failed: 403" | YouTube rejected the upload before the file transfer began | Check whether the channel is suspended, the upload quota is exhausted, or permissions are missing. |

A `publishNow: true` post that YouTube rejects returns `207` with `post.status: "failed"` and the message in `platforms[].errorMessage`:

```json
{
  "message": "Post created but publishing failed",
  "error": "All platforms failed",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "failed",
    "platforms": [
      {
        "platform": "youtube",
        "status": "failed",
        "errorMessage": "The YouTube account of the authenticated user is suspended."
      }
    ]
  }
}
```

`207` is a 2xx status, so `fetch(...).ok` is `true`; branch on the status code and on `post.status`. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts): the OAuth flow.
- [Create post](/posts/create-post): every field of the request.
- [Edit post](/posts/edit-post) and [Update post metadata](/posts/update-post-metadata): change a published video.
- [Media uploads](/guides/media-uploads): upload videos instead of hosting them.
- [Analytics](/analytics/get-analytics): post performance metrics.
- [Comments](/comments/list-inbox-comments): read and reply to comments.
- [Get a YouTube video transcript](/connect/get-youtube-captions): every parameter of the captions call.
- [Pricing](/pricing): what analytics, the inbox and outbound messages cost, and which replies count.

---
