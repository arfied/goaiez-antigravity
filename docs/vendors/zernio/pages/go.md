# Go

Install zernio-go and call every Zernio API endpoint from Go with context-first, builder-style requests.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { BookOpen, Code } from 'lucide-react';

`github.com/zernio-dev/zernio-go` is the official Go client for the Zernio API. Requests take a `context.Context` and are built with typed setters, so timeouts and cancellation work the way Go code expects. Install it with `go get github.com/zernio-dev/zernio-go`. The module is on [pkg.go.dev](https://pkg.go.dev/github.com/zernio-dev/zernio-go) and the source on [GitHub](https://github.com/zernio-dev/zernio-go).

## First call

```bash
go get github.com/zernio-dev/zernio-go
export ZERNIO_API_KEY="sk_..."
```

The API key travels in the request context under `zernio.ContextAccessToken`. This program publishes a post to a connected LinkedIn account; `accountId` comes from `GET /v1/accounts` ([Step 4 of the quickstart](/#step-4-get-the-account-id)).

```go
package main

import (
    "context"
    "errors"
    "fmt"
    "log"
    "os"

    zernio "github.com/zernio-dev/zernio-go/zernio"
)

func main() {
    client := zernio.NewAPIClient(zernio.NewConfiguration())
    ctx := context.WithValue(context.Background(), zernio.ContextAccessToken, os.Getenv("ZERNIO_API_KEY"))

    req := zernio.NewCreatePostRequest()
    req.SetContent("Hello from the Zernio Go SDK")
    req.SetPublishNow(true)
    req.SetPlatforms([]zernio.CreatePostRequestPlatformsInner{
        *zernio.NewCreatePostRequestPlatformsInner("linkedin", "66b2e19d8c3f5a7e9d0b1c2d"),
    })

    post, httpRes, err := client.PostsAPI.CreatePost(ctx).
        CreatePostRequest(*req).
        Execute()
    if err != nil {
        var apiErr *zernio.GenericOpenAPIError
        if errors.As(err, &apiErr) {
            log.Fatalf("API error (status %d): %s", httpRes.StatusCode, string(apiErr.Body()))
        }
        log.Fatalf("transport error: %v", err)
    }
    fmt.Printf("Created post: %+v\n", post)
}
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

Every call returns the typed response, the `*http.Response` and an error.

## Methods

Each method is named after the operationId of the endpoint it calls, on a service per API tag: `client.PostsAPI.CreatePost`, `client.AccountsAPI.ListAccounts`, `client.ProfilesAPI.CreateProfile`, `client.AnalyticsAPI.GetAnalytics`, `client.WebhooksAPI.CreateWebhookSettings`, `client.WorkflowsAPI.CreateWorkflow`. Request bodies are built with `New...Request()` constructors and `Set...` methods; `.Execute()` sends the call.

<Cards>
  <Card
    icon={<BookOpen />}
    title="Method reference"
    description="Every method, by service, in the GitHub README"
    href="https://github.com/zernio-dev/zernio-go#sdk-reference"
  />
  <Card
    icon={<Code />}
    title="API reference"
    description="Request and response schemas for every endpoint"
    href="/posts/create-post"
  />
</Cards>

## How it behaves

### The base URL defaults to `https://zernio.com/api`

`zernio.NewConfiguration()` points at production, and every method appends its own `/v1` path, so the base URL ends at `/api`. The API key is not read from the environment by the client itself; put it in the context as the first call does.

### `CreatePost` accepts an idempotency key

Chain `.XRequestId("<uuid>")` before `.Execute()` to send the `x-request-id` header; a retry with the same value within about 5 minutes returns the original post instead of creating a second one ([idempotency](/guides/idempotency)).

### Errors carry the raw response body

A non-2xx response returns a `*zernio.GenericOpenAPIError`; `errors.As` unwraps it and `.Body()` holds the JSON envelope, while the status code is on the returned `*http.Response`. Running the first call twice within 24 hours returns status `409` with this body, because the same content is already published to that account:

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

Change the content, or send an idempotency key. The [error handling guide](/guides/error-handling) lists every code.

### Zernio switched code generators in `v0.1.0`

`v0.1.0` is a breaking change from `v0.0.x`: the import path is unchanged, but client construction and method calls differ. Read [MIGRATION.md](https://github.com/zernio-dev/zernio-go/blob/main/MIGRATION.md) before upgrading from `v0.0.x`. A first install gets `v0.1.0` or later and needs nothing.

## Related

- [Quickstart](/): API key, profile, account and first post in 5 calls.
- [Idempotency](/guides/idempotency): what `XRequestId` does.
- [Error handling](/guides/error-handling): the envelope inside `GenericOpenAPIError`.
- [Rate limits](/guides/rate-limits): what a `429` means and how to back off.
- [SDKs](/sdks): the other 7 clients.

---
