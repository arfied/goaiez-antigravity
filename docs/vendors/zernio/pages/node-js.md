# Node.js

Install @zernio/node and call every Zernio API endpoint from Node.js or TypeScript with typed requests and responses.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { BookOpen, Code } from 'lucide-react';

`@zernio/node` is the official Node.js client for the Zernio API, with TypeScript types for every request and response. Install it with `npm install @zernio/node`; it needs Node.js 18 or later. The package is on [npm](https://www.npmjs.com/package/@zernio/node) and the source on [GitHub](https://github.com/zernio-dev/zernio-node).

## First call

```bash
npm install @zernio/node
export ZERNIO_API_KEY="sk_..."
```

The client reads `ZERNIO_API_KEY` from the environment. This call publishes a post to a connected LinkedIn account; `accountId` comes from `GET /v1/accounts` ([Step 4 of the quickstart](/#step-4-get-the-account-id)).

```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Hello from the Zernio Node.js SDK',
    platforms: [
      { platform: 'linkedin', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
    ],
    publishNow: true
  }
});

console.log(published.post.platforms[0].platformPostUrl);
```

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
        "platformPostUrl": "https://www.linkedin.com/feed/update/..."
      }
    ]
  }
}
```

Every method returns `{ data }`, where `data` is the parsed response body typed for that endpoint.

## Methods

Each method is named after the operationId of the endpoint it calls, on a namespace per API tag: `zernio.posts.createPost`, `zernio.accounts.listAccounts`, `zernio.analytics.getAnalytics`, `zernio.media.getMediaPresignedUrl`, `zernio.webhooks.createWebhookSettings`, `zernio.workflows.createWorkflow`. Arguments go in `{ path, query, body }`.

<Cards>
  <Card
    icon={<BookOpen />}
    title="Method reference"
    description="Every method, by namespace, in the GitHub README"
    href="https://github.com/zernio-dev/zernio-node#sdk-reference"
  />
  <Card
    icon={<Code />}
    title="API reference"
    description="Request and response schemas for every endpoint"
    href="/posts/create-post"
  />
</Cards>

## How it behaves

### The client reads its key from `ZERNIO_API_KEY`

Pass options to set the key in code, change the base URL or the request timeout in milliseconds:

```typescript
const configured = new Zernio({
  apiKey: process.env.ZERNIO_API_KEY,
  baseURL: 'https://zernio.com/api',
  timeout: 60000
});
```

`LATE_API_KEY` is read when `ZERNIO_API_KEY` is not set, so code written against the Late SDK keeps working.

### Uploading media

`zernio.media.getMediaPresignedUrl` returns an `uploadUrl` and a `publicUrl` for a file of up to 5 GB. `PUT` the file to `uploadUrl`, which goes to storage and carries no `Authorization` header, then put `publicUrl` in `mediaItems`:

```typescript
import { readFile } from 'node:fs/promises';

const { data: presigned } = await zernio.media.getMediaPresignedUrl({
  body: {
    filename: 'photo.jpg',
    contentType: 'image/jpeg'
  }
});

await fetch(presigned.uploadUrl, {
  method: 'PUT',
  headers: { 'Content-Type': 'image/jpeg' },
  body: await readFile('photo.jpg')
});

const { data: withPhoto } = await zernio.posts.createPost({
  body: {
    content: 'A photo from the Zernio Node.js SDK',
    mediaItems: [{ url: presigned.publicUrl, type: 'image' }],
    platforms: [{ platform: 'linkedin', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }],
    publishNow: true
  }
});
```

An `uploadUrl` expires 1 hour after it is issued, and the file it holds sits in temporary storage for 7 days ([media uploads](/guides/media-uploads)).

### `createPost` accepts an idempotency key

Put the `x-request-id` header in `headers`. A retry with the same value within about 5 minutes returns the original post instead of creating a second one ([idempotency](/guides/idempotency)). Use one key per logical post, derived from your own id for it, and send that same key on every retry:

```typescript
const { data: retried } = await zernio.posts.createPost({
  headers: { 'x-request-id': 'launch-2027-01-01' },
  body: {
    content: 'Hello from the Zernio Node.js SDK',
    platforms: [{ platform: 'linkedin', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }],
    publishNow: true
  }
});
```

### Errors are typed

A non-2xx response throws `ZernioApiError` with `statusCode`, `code` and `details`. A `429` throws `RateLimitError`, whose `getSecondsUntilReset()` reads the `X-RateLimit-Reset` header; a `400` with field errors throws `ValidationError` with `fields`.

```typescript
import { ZernioApiError, RateLimitError, ValidationError } from '@zernio/node';

try {
  await zernio.posts.createPost({
    body: {
      content: 'Hello from the Zernio Node.js SDK',
      platforms: [{ platform: 'linkedin', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }],
      publishNow: true
    }
  });
} catch (error) {
  if (error instanceof RateLimitError) {
    console.log(`Rate limited. Retry in ${error.getSecondsUntilReset()}s`);
  } else if (error instanceof ValidationError) {
    console.log('Invalid request:', error.fields);
  } else if (error instanceof ZernioApiError) {
    console.log(`Error ${error.statusCode}: ${error.message}`);
  }
}
```

Running the first call twice within 24 hours throws `ZernioApiError` with `statusCode` `409`: the same content is already published to that account. Change the content, or send an idempotency key to make retries return the original post ([idempotency](/guides/idempotency)). The [error handling guide](/guides/error-handling) lists every code.

## Related

- [Quickstart](/): API key, profile, account and first post in 5 calls.
- [Media uploads](/guides/media-uploads): `zernio.media.getMediaPresignedUrl` and the upload flow.
- [Error handling](/guides/error-handling): the envelope behind `ZernioApiError`.
- [Rate limits](/guides/rate-limits): what `RateLimitError` means and how to back off.
- [SDKs](/sdks): the other 7 clients.

---
