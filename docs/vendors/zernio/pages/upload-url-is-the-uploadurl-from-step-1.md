# UPLOAD_URL is the uploadUrl from Step 1
curl -X PUT "$UPLOAD_URL" \
  -H "Content-Type: image/jpeg" \
  --data-binary @photo.jpg
```
</Tab>
</Tabs>

Response (`200`), no body. Storage answers with an empty `200` and an `ETag` header, never JSON. The file is now at `publicUrl`.

## Step 3: Create the post

Call `POST /v1/posts` with `publicUrl` in `mediaItems`. `type` is `image`, `video`, `gif` or `document`; it is inferred from the file extension when omitted, and a `type` that contradicts the extension is rejected with `400`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: created } = await zernio.posts.createPost({
  body: {
    content: 'Golden hour at the pier.',
    mediaItems: [
      { url: publicUrl, type: 'image' }
    ],
    platforms: [
      { platform: 'linkedin', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
    ],
    scheduledFor: '2027-01-01T12:00:00',
    timezone: 'America/New_York'
  }
});

console.log(created.post._id);
```
</Tab>
<Tab value="Python">
```python
created = client.posts.create_post(
    content="Golden hour at the pier.",
    media_items=[
        {"url": public_url, "type": "image"}
    ],
    platforms=[
        {"platform": "linkedin", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
    ],
    scheduled_for="2027-01-01T12:00:00",
    timezone="America/New_York"
)

print(created["post"]["_id"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/posts" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Golden hour at the pier.",
    "mediaItems": [
      { "url": "https://media.zernio.com/temp/1234567890_abc123_photo.jpg", "type": "image" }
    ],
    "platforms": [
      { "platform": "linkedin", "accountId": "66b2e19d8c3f5a7e9d0b1c2d" }
    ],
    "scheduledFor": "2027-01-01T12:00:00",
    "timezone": "America/New_York"
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "message": "Post scheduled successfully",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "scheduled",
    "scheduledFor": "2027-01-01T17:00:00Z",
    "mediaItems": [
      { "url": "https://media.zernio.com/temp/1234567890_abc123_photo.jpg", "type": "image" }
    ],
    "platforms": [
      { "platform": "linkedin", "status": "pending" }
    ]
  }
}
```

The [presign endpoint](/media/get-media-presigned-url) lists every parameter.

## How it behaves

### Uploads expire after 7 days

An upload sits in temporary storage for 7 days. When a post that references its `publicUrl` publishes, Zernio copies the file to permanent storage. Schedule posts that use an upload within 7 days of uploading.

### Media URL requirements

Every `mediaItems[].url` must be publicly reachable over HTTPS and return the file itself. Google Drive, Dropbox, OneDrive and iCloud links do not work: they return an HTML page, not the file, so the platform's servers cannot fetch from them. Use a direct media URL or upload through the presign endpoint. Test a URL in an incognito window: a webpage instead of the raw file means the post will fail.

### Formats and sizes

`contentType` must be one of the MIME types the [presign endpoint](/media/get-media-presigned-url) accepts. A generic type such as `application/octet-stream` is rejected with `400`.

| Type | Formats |
|------|---------|
| Images | JPEG, PNG, GIF, WebP |
| Videos | MP4, MPEG, MOV, AVI, WebM, M4V |
| Documents | PDF (LinkedIn only) |
| Audio | MP3, M4A, AAC, OGG, WAV, WebM |

Audio has no `mediaItems[].type`, so an audio file cannot be attached to a post. Send its `publicUrl` as `attachmentUrl` with `attachmentType: "audio"` on [send inbox message](/messages/send-inbox-message) instead.

Presign caps every type at 5 GB; no type has a smaller cap. Platforms are stricter: a LinkedIn document must be a PDF of 100 MB or less, and each platform's limits on count, size, duration and aspect ratio are under "Media requirements" on its [platform page](/platforms).

### Zernio compresses files above a platform's limit

Images and videos above a platform's limit are compressed before upload; videos over 200 MB may not be. Bluesky images are recompressed to stay under its 1 MB blob limit. A custom thumbnail in `mediaItems[].thumbnail` (JPG or PNG, 10 MB max) applies to Facebook video posts, Facebook Reels, YouTube videos and LinkedIn video posts.

### `customMedia` replaces `mediaItems` for one platform

`mediaItems` attaches to every platform in the request. To send a different file to one of them, set `customMedia` on that entry:

```json
{
  "content": "Same text, different media per platform",
  "mediaItems": [{ "url": "https://media.zernio.com/temp/1234567890_abc123_photo.jpg", "type": "image" }],
  "platforms": [
    { "platform": "linkedin", "accountId": "66b2e19d8c3f5a7e9d0b1c2d" },
    {
      "platform": "instagram",
      "accountId": "66b2e19d8c3f5a7e9d0b1c2e",
      "customMedia": [{ "url": "https://media.zernio.com/temp/1234567890_abc123_square.jpg", "type": "image" }]
    }
  ]
}
```

## If it fails

A `400` from `POST /v1/media/presign` means `filename` is missing, `contentType` is not an accepted MIME type, or `size` is above 5 GB:

```json
{
  "error": "Invalid contentType",
  "type": "invalid_request_error",
  "code": "invalid_field_value",
  "param": "contentType"
}
```

Send the file's real MIME type (`image/jpeg`, `video/mp4`, `application/pdf`). A `PUT` to an `uploadUrl` older than 1 hour is refused by storage; request a new one.

## Related

- [Presign endpoint](/media/get-media-presigned-url): every parameter and accepted MIME type.
- [Create post](/posts/create-post): `mediaItems`, `customMedia`, `altText` and `thumbnail`.
- [Platforms](/platforms): per-platform media requirements.
- [Multi-tenant publishing](/multi-tenant/publishing): uploading once for many customers.

---
