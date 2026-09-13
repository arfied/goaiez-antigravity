# Publishing

Publish for every customer with an idempotency key, one media upload per asset, each customer's own queue and outcomes from webhooks.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page your app creates posts for every customer without duplicates, uploads each file once, spreads publishing across each customer's queue and learns the outcome from webhooks. You need a customer's `profileId` and the account map from the [core model](/multi-tenant). A duplicate here goes out on your customer's account, which is why each step is retry-safe.

## Step 1: Upload media once

Call `POST /v1/media/presign`, `PUT` the file to `uploadUrl`, and put `publicUrl` in the post's `mediaItems`. One upload serves every platform entry in the post and can be reused across posts. Uploads sit in temporary storage for 7 days and are copied to permanent storage when a post using them publishes, so upload when you create the post, not when the customer picks the file. The [media uploads guide](/guides/media-uploads) has the full flow.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import { readFile } from 'node:fs/promises';
import { randomUUID } from 'crypto';
import Zernio from '@zernio/node';

const zernio = new Zernio();
const profileId = '66a1f0c2a4b9d3e8f1a2b3c4';

const { data: presigned } = await zernio.media.getMediaPresignedUrl({
  body: { filename: 'launch.mp4', contentType: 'video/mp4' },
});

const fileBuffer = await readFile('launch.mp4');

await fetch(presigned.uploadUrl, {
  method: 'PUT',
  headers: { 'Content-Type': 'video/mp4' },
  body: fileBuffer,
});
```
</Tab>
<Tab value="Python">
```python
import uuid
import httpx
from zernio import Zernio

client = Zernio()
profile_id = "66a1f0c2a4b9d3e8f1a2b3c4"

presigned = client.media.get_media_presigned_url(
    filename="launch.mp4",
    content_type="video/mp4",
)

with open("launch.mp4", "rb") as f:
    httpx.put(presigned["uploadUrl"], content=f.read(), headers={"Content-Type": "video/mp4"})
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/media/presign" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "filename": "launch.mp4", "contentType": "video/mp4" }'
