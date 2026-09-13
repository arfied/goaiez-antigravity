# Python

Install zernio-sdk and call every Zernio API endpoint from Python with a sync or async client and direct media uploads.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { BookOpen, Code } from 'lucide-react';

`zernio-sdk` is the official Python client for the Zernio API, with a synchronous and an asynchronous client. Install it with `pip install zernio-sdk`; it needs Python 3.10 or later. The package is on [PyPI](https://pypi.org/project/zernio-sdk/) and the source on [GitHub](https://github.com/zernio-dev/zernio-python).

## First call

```bash
pip install zernio-sdk
export ZERNIO_API_KEY="sk_..."
```

The client reads `ZERNIO_API_KEY` from the environment. This call publishes a post to a connected LinkedIn account; `accountId` comes from `GET /v1/accounts` ([Step 4 of the quickstart](/#step-4-get-the-account-id)).

```python
from zernio import Zernio

client = Zernio()

published = client.posts.create_post(
    content="Hello from the Zernio Python SDK",
    platforms=[
        {"platform": "linkedin", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
    ],
    publish_now=True,
)

print(published["post"]["platforms"][0]["platformPostUrl"])
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

Every endpoint method returns the parsed response body as a dict; the `client.media` upload helpers are the exception and return a typed model. Arguments are keyword arguments in snake_case (`publish_now`, `scheduled_for`, `media_items`); values inside `platforms` and `media_items` keep the API's field names.

## Methods

Each method is named after the operationId of the endpoint it calls, in snake_case, on a namespace per API tag: `client.posts.create_post`, `client.accounts.list_accounts`, `client.analytics.get_analytics`, `client.webhooks.create_webhook_settings`, `client.workflows.create_workflow`.

<Cards>
  <Card
    icon={<BookOpen />}
    title="Method reference"
    description="Every method, by namespace, in the GitHub README"
    href="https://github.com/zernio-dev/zernio-python#sdk-reference"
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

Pass arguments to set the key in code, change the base URL or the request timeout in seconds:

```python
configured = Zernio(
    api_key="sk_...",
    base_url="https://zernio.com/api",
    timeout=30.0,
)
```

`LATE_API_KEY` is read when `ZERNIO_API_KEY` is not set, and `from late import Late` still imports the same client, so code written against the Late SDK keeps working.

### Every method has an async variant

The async name is the sync name with an `a` in front, so `list_posts` is `alist_posts` and `activate_workflow` is `aactivate_workflow`. Use the client as an async context manager:

```python
import asyncio


async def main():
    async with Zernio() as async_client:
        scheduled = await async_client.posts.alist_posts(status="scheduled")
        print(len(scheduled["posts"]))


asyncio.run(main())
```

### Files under 4 MB upload directly

`client.media.upload(path)` and `client.media.upload_bytes(content, filename, mime_type=...)` send the file in one request and return a `MediaUploadResponse` model, whose `files` list carries the URL to put in `media_items`. Larger files go through the presigned URL flow in [media uploads](/guides/media-uploads).

```python
uploaded = client.media.upload("path/to/video.mp4")
media_url = str(uploaded.files[0].url)

with_video = client.posts.create_post(
    content="A video from the Zernio Python SDK",
    media_items=[{"type": "video", "url": media_url}],
    platforms=[
        {"platform": "linkedin", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
    ],
    publish_now=True,
)
```

### `create_post` accepts an idempotency key

Pass `x_request_id` (a UUID you generate) to send the `x-request-id` header; a retry with the same value within about 5 minutes returns the original post instead of creating a second one ([idempotency](/guides/idempotency)).

### Errors are typed

A non-2xx response raises `ZernioAPIError` with `status_code` and `details`. A `429` raises `ZernioRateLimitError` with `reset_time`; a client-side validation failure raises `ZernioValidationError`.

```python
from zernio import ZernioAPIError, ZernioRateLimitError, ZernioValidationError

try:
    client.posts.create_post(
        content="Hello from the Zernio Python SDK",
        platforms=[{"platform": "linkedin", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}],
        publish_now=True,
    )
except ZernioRateLimitError as e:
    print(f"Rate limited: {e}")
except ZernioValidationError as e:
    print(f"Invalid request: {e}")
except ZernioAPIError as e:
    print(f"API error: {e}")
```

Running the first call twice within 24 hours raises `ZernioAPIError` with `status_code` `409`: the same content is already published to that account. Change the content, or send `x_request_id` so the retry returns the original post. The [error handling guide](/guides/error-handling) lists every code.

## Related

- [Quickstart](/): API key, profile, account and first post in 5 calls.
- [Media uploads](/guides/media-uploads): the presigned URL flow for files over 4 MB.
- [Error handling](/guides/error-handling): the envelope behind `ZernioAPIError`.
- [MCP](/mcp/setup#run-the-server-locally): the MCP server that ships inside this package.
- [SDKs](/sdks): the other 7 clients.

---
