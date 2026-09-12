# Media & Video

Image, GIF and video limits for X, and what the longVideo flag does on a Premium account.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page your images, GIFs and videos are within X's limits and you know what `platformSpecificData.longVideo` changes on a Premium account. You need a connected X account (`accountId`) and media on a public HTTPS URL, or an upload through the [media endpoint](/guides/media-uploads).

## Media requirements

A post carries up to 4 images, or 1 GIF, or 1 video. Files above the size limits are rejected.

### Images

| Property | Requirement |
|----------|-------------|
| Max images | 4 per post |
| Formats | JPEG, PNG, WebP, GIF |
| Max file size | 5 MB (images), 15 MB (GIFs) |
| Min dimensions | 4 x 4 px |
| Max dimensions | 8192 x 8192 px |
| Recommended | 1200 x 675 px (16:9) |

| Type | Ratio | Dimensions |
|------|-------|------------|
| Landscape | 16:9 | 1200 x 675 px |
| Square | 1:1 | 1200 x 1200 px |
| Portrait | 4:5 | 1080 x 1350 px |

### GIFs

| Property | Requirement |
|----------|-------------|
| Max per post | 1 (takes all 4 image slots) |
| Max file size | 15 MB |
| Max dimensions | 1280 x 1080 px |
| Behavior | Auto-plays in the timeline |

### Videos

| Property | Requirement |
|----------|-------------|
| Max videos | 1 per post |
| Formats | MP4, MOV |
| Max file size | 512 MB |
| Max duration | Set by X per account, not by Zernio, which enforces the 512 MB file size only. See [Long video uploads](#long-video-uploads) |
| Min duration | 0.5 seconds |
| Min dimensions | 32 x 32 px |
| Max dimensions | 1920 x 1200 px |
| Frame rate | 40 fps max |
| Bitrate | 25 Mbps max |

Recommended encoding: 1280 x 720 px (720p), 16:9 or 1:1, 30 fps, H.264 video, AAC audio at 128 kbps.

## Attach an image or a video

Media rides on the base request: put the files in `mediaItems` and X gets them with the post. No X-specific field is involved.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Ship notes for 2.4',
    mediaItems: [
      { type: 'image', url: 'https://cdn.example.com/release.png' }
    ],
    platforms: [
      { platform: 'twitter', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
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
    content="Ship notes for 2.4",
    media_items=[
        {"type": "image", "url": "https://cdn.example.com/release.png"}
    ],
    platforms=[
        {"platform": "twitter", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
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
    "content": "Ship notes for 2.4",
    "mediaItems": [
      {"type": "image", "url": "https://cdn.example.com/release.png"}
    ],
    "platforms": [
      {"platform": "twitter", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
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
        "platform": "twitter",
        "status": "published",
        "platformPostUrl": "https://twitter.com/acmecorp/status/1852634789012345678"
      }
    ]
  }
}
```

Swap `type: "image"` for `type: "video"` to post a video. Up to 4 images go in one post; a GIF or a video takes the slot on its own.

## Long video uploads

`platformSpecificData.longVideo: true` uploads the video with X's `amplify_video` media category instead of the standard `tweet_video`. It is not what allows a long video: the standard path already publishes videos well past 140 seconds, on free accounts included. Zernio applies it only when the connected X account has a paid X subscription; on other accounts the flag is accepted and ignored. The maximum duration is set by X per account, not by Zernio, which enforces only the 512 MB file size. Some accounts also need X's long-video API allowlisting, without which X rejects an `amplify_video` upload.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: longVideoPost } = await zernio.posts.createPost({
  body: {
    content: 'Full keynote recording',
    mediaItems: [{ type: 'video', url: 'https://cdn.example.com/keynote.mp4' }],
    platforms: [{
      platform: 'twitter',
      accountId: '66b2e19d8c3f5a7e9d0b1c2d',
      platformSpecificData: { longVideo: true }
    }],
    publishNow: true
  }
});

console.log(longVideoPost.post.platforms[0].platformPostUrl);
```
</Tab>
<Tab value="Python">
```python
long_video_post = client.posts.create_post(
    content="Full keynote recording",
    media_items=[{"type": "video", "url": "https://cdn.example.com/keynote.mp4"}],
    platforms=[{
        "platform": "twitter",
        "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
        "platformSpecificData": {"longVideo": True}
    }],
    publish_now=True
)

print(long_video_post["post"]["platforms"][0]["platformPostUrl"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Full keynote recording",
    "mediaItems": [
      {"type": "video", "url": "https://cdn.example.com/keynote.mp4"}
    ],
    "platforms": [{
      "platform": "twitter",
      "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
      "platformSpecificData": {
        "longVideo": true
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
        "platformPostUrl": "https://twitter.com/acmecorp/status/1852634789012345678"
      }
    ]
  }
}
```

## Media URLs

A media URL must be public HTTPS, return the file bytes with the correct `Content-Type` and not redirect to an HTML page; Google Drive, Dropbox, OneDrive and iCloud sharing links return a web page and fail. The [media uploads guide](/guides/media-uploads) has the rules and the upload flow.

## If it fails

A `207` with `post.status: "failed"` and `errorMessage: "Media fetch failed, retrying... (failed after 3 attempts)"` means Zernio could not download the file from the URL:

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
        "errorMessage": "Media fetch failed, retrying... (failed after 3 attempts)"
      }
    ]
  }
}
```

Open the URL in an incognito window: a web page instead of the raw file means the host is not serving the bytes. Upload the file through the [media endpoint](/guides/media-uploads) and create the post again.

## Related

- [Posts & Editing](/platforms/twitter/posts): attaching media to the base request.
- [Media uploads](/guides/media-uploads): presigned uploads up to 5 GB.
- [Fields, Geo & Polls](/platforms/twitter/fields-polls): `geoRestriction` and `sensitiveMedia` apply to attached media.
- [Limits & Errors](/platforms/twitter/reference): every field and error.

---
