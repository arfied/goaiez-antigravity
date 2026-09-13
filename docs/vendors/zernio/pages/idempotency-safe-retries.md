# Idempotency & Safe Retries

Retry POST /v1/posts safely with the x-request-id header, understand the 24-hour content-hash dedup, and use Idempotency-Key on the other write endpoints.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Retry [`POST /v1/posts`](/posts/create-post) safely by sending an `x-request-id` header: a retried request returns the original post instead of creating a second one. A second layer rejects identical content sent to the same account within 24 hours, whatever the headers. You need an API key and a connected account. Network timeouts and the automatic retries in n8n, Zapier and custom queues all deliver the same create request twice; these two layers are what make that safe.

## First call

Generate a UUID per logical post and send it as `x-request-id`. The Node and Python SDKs generate one for you when you leave it out.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import { randomUUID } from 'crypto';
import Zernio from '@zernio/node';

const zernio = new Zernio();
const requestId = randomUUID();

const { data: created } = await zernio.posts.createPost({
  headers: { 'x-request-id': requestId },
  body: {
    content: 'Our January release notes are out.',
    scheduledFor: '2027-01-01T12:00:00',
    timezone: 'America/New_York',
    platforms: [{ platform: 'linkedin', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }]
  }
});

const postId = created.post._id;
```
</Tab>
<Tab value="Python">
```python
import uuid
from zernio import Zernio

client = Zernio()
request_id = str(uuid.uuid4())

created = client.posts.create_post(
    x_request_id=request_id,
    content="Our January release notes are out.",
    scheduled_for="2027-01-01T12:00:00",
    timezone="America/New_York",
    platforms=[{"platform": "linkedin", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}],
)

post_id = created["post"]["_id"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/posts" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -H "x-request-id: 4b4986f4-77b5-4c22-a3a7-2c56f7a657e1" \
  -d '{
    "content": "Our January release notes are out.",
    "scheduledFor": "2027-01-01T12:00:00",
    "timezone": "America/New_York",
    "platforms": [{ "platform": "linkedin", "accountId": "66b2e19d8c3f5a7e9d0b1c2d" }]
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
    "platforms": [
      { "platform": "linkedin", "status": "pending" }
    ]
  }
}
```

Send the same request again with the same `x-request-id` and no new post is created. Response (`200`):

```json
{
  "existingPost": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "scheduled",
    "scheduledFor": "2027-01-01T17:00:00Z"
  }
}
```

Treat that `200` as success: it is a replay of the first call.

## How it behaves

### Layer 1: `x-request-id` (same-request idempotency)

Zernio remembers each `x-request-id` while the first request is in flight and for about 5 minutes after it completes. A second request with the same value inside that window returns `200` with the original post in `existingPost`.

- Generate a fresh UUID (v4 is fine) per logical call.
- Reuse the UUID only when retrying that exact call after a timeout, a `5xx` or a connection reset.
- Omit the header and every request is treated as new; only Layer 2 then protects you.

The generated SDKs expose the header as an explicit parameter: `.XRequestId("<uuid>")` in [Go](/sdks/go), `x_request_id:` in [Ruby](/sdks/ruby), a `UUID` argument in [Java](/sdks/java), `Guid?` in [.NET](/sdks/dotnet), and `x_request_id` in [PHP](/sdks/php) and [Rust](/sdks/rust).

<Callout type="warn">
A workflow tool that reuses one execution-level `x-request-id` across several HTTP nodes (one value for the whole run, shared by 6 platform calls) makes every call after the first look like a retry of the first, so each returns the first post. Generate a fresh UUID per node, or omit the header.
</Callout>

### Layer 2: Content-hash dedup (24-hour window)

Independently of `x-request-id`, Zernio hashes `(platform, accountId, content + media URLs)` and rejects a match from the last 24 hours with `409`. This catches the same content posted twice to the same account whatever the headers say. To post identical content again within 24 hours, change the fingerprint: the caption, the media or the account.

### Order of evaluation

1. Same `x-request-id` seen in the last 5 minutes: return the original post with `200`. Nothing is created.
2. Otherwise, same content fingerprint seen in the last 24 hours: reject with `409` and `details.existingPostId`.
3. Otherwise, create the post.

A `429` is also safe to retry after waiting `Retry-After` seconds; see [rate limits](/guides/rate-limits).

### Other write endpoints take `Idempotency-Key`

The two layers above apply to post creation. These endpoints accept an optional `Idempotency-Key` header (a client-generated unique key such as a UUID, up to 255 characters): [create profile](/profiles/create-profile), [send inbox message](/messages/send-inbox-message), [reply to a post's comments](/comments/reply-to-inbox-post), [reply to a review](/reviews/reply-to-inbox-review), [start a WhatsApp call](/whatsapp/initiate-whats-app-call), [start a voice call](/calls/create-voice-call), [send SMS](/sms/send-sms), [boost a post](/ad-campaigns/boost-post), [create an ad](/ad-campaigns/create-standalone-ad), and the [create](/ad-campaigns/create-ad-campaign) and duplicate endpoints for campaigns, ad sets and ads.

Send the key on the first attempt, then again on the retry:

```bash
curl -i -X POST "https://zernio.com/api/v1/profiles" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: 9f7c1f2e-6f0a-4a1f-9d2c-2b0a4f8e5c11" \
  -d '{ "name": "Acme Corp" }'
```

Response (`201`):

```json
{
  "message": "Profile created successfully",
  "profile": { "_id": "66a1f0c2a4b9d3e8f1a2b3c4", "name": "Acme Corp" }
}
```

Run the same command again and no second profile is created: the stored response comes back with its original status and one extra header.

```text
HTTP/2 201
Idempotent-Replayed: true
Content-Type: application/json

{ "message": "Profile created successfully", "profile": { "_id": "66a1f0c2a4b9d3e8f1a2b3c4", "name": "Acme Corp" } }
```

The same key with a different body returns `422`; a key whose first request is still processing returns `409`.

Two rules decide how you write the retry. Keys are retained for 24 hours and are scoped to your credential and to that exact path, so the same key sent to a different `postId` returns `422` instead of replaying the other post's response. And only a successful (`2xx`) response is stored for replay: a first attempt that fails releases the key, so the retry runs for real instead of replaying a failure.

So the header covers the "request succeeded, response was lost" case rather than every retry. After an ambiguous failure (a `5xx` or a network timeout) the platform may already have accepted the message, and a failure after that point releases the key as well, so reconcile before you retry: list the conversation's messages, the post's comments or the review first, and treat an empty result as inconclusive rather than as proof nothing was sent.

The remaining write endpoints are `PUT`-style updates or cheap to check first: when in doubt, read before you write.

## If it fails

A `409` on `POST /v1/posts` means this exact content already went to this account in the last 24 hours:

```json
{
  "error": "This exact content is already scheduled, publishing, or was posted to this account within the last 24 hours.",
  "details": {
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platform": "linkedin",
    "existingPostId": "65f1c0a9e2b5af0012ab34cd"
  }
}
```

Look up `details.existingPostId` with [`GET /v1/posts/{postId}`](/posts/get-post) and treat it as success, or surface it to the user. If the `409` came from a retry loop, add a unique `x-request-id` per logical request so the retry hits Layer 1 instead.

## Related

- [Create post](/posts/create-post): the full idempotency contract on the operation.
- [Error handling](/guides/error-handling): the error envelope and which statuses to retry.
- [Rate limits](/guides/rate-limits): `429` and `Retry-After`.
- [Multi-tenant publishing](/multi-tenant/publishing): one key per customer request.

---
