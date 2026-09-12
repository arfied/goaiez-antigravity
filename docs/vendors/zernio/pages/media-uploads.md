# Media Uploads

Upload an image, video or document with a presigned URL and attach it to a post with mediaItems.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page a file is uploaded to Zernio's storage and attached to a post. You need an API key and an `accountId` ([quickstart](/)). Uploads go straight to storage through a presigned URL, up to 5 GB per file, in 3 calls: `POST /v1/media/presign`, a `PUT` of the file, then `POST /v1/posts` with the returned `publicUrl` in `mediaItems`. A file you already host on a public HTTPS URL skips the first two calls; see [Media URL requirements](#media-url-requirements).

## Step 1: Get an upload URL

Call `POST /v1/media/presign` with `filename` and `contentType`. Pass `size` in bytes to have the 5 GB limit checked before you upload.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import { readFile } from 'node:fs/promises';
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: presigned } = await zernio.media.getMediaPresignedUrl({
  body: {
    filename: 'photo.jpg',
    contentType: 'image/jpeg'
  }
});

const { uploadUrl, publicUrl } = presigned;
```
</Tab>
<Tab value="Python">
```python
import httpx
from zernio import Zernio

client = Zernio()

presigned = client.media.get_media_presigned_url(
    filename="photo.jpg",
    content_type="image/jpeg"
)

upload_url = presigned["uploadUrl"]
public_url = presigned["publicUrl"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/media/presign" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "filename": "photo.jpg",
    "contentType": "image/jpeg"
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "uploadUrl": "https://<bucket>.r2.cloudflarestorage.com/temp/1234567890_abc123_photo.jpg?X-Amz-Signature=...",
  "publicUrl": "https://media.zernio.com/temp/1234567890_abc123_photo.jpg",
  "key": "temp/1234567890_abc123_photo.jpg",
  "expiresIn": 3600
}
```

`uploadUrl` is valid for `expiresIn` seconds (1 hour). `publicUrl` is the address the post uses in Step 3.

## Step 2: Upload the file

`PUT` the file to `uploadUrl` with the same `Content-Type`. This request goes to storage, not to the API, so it carries no `Authorization` header.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const fileBuffer = await readFile('photo.jpg');

await fetch(uploadUrl, {
  method: 'PUT',
  headers: { 'Content-Type': 'image/jpeg' },
  body: fileBuffer
});
```
</Tab>
<Tab value="Python">
```python
with open("photo.jpg", "rb") as f:
    httpx.put(upload_url, content=f.read(), headers={"Content-Type": "image/jpeg"})
```
</Tab>
<Tab value="curl">
```bash
