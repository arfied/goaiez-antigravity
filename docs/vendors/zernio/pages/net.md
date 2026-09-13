# .NET

Add the Zernio NuGet package and call every Zernio API endpoint from C# with a reusable HttpClient.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { BookOpen, Code } from 'lucide-react';

`Zernio` is the official C# client for the Zernio API. Install it with `dotnet add package Zernio`. The package is on [NuGet](https://www.nuget.org/packages/Zernio) and the source on [GitHub](https://github.com/zernio-dev/zernio-dotnet).

## First call

```bash
dotnet add package Zernio
export ZERNIO_API_KEY="sk_..."
```

Set the API key on a `Configuration`, then construct the API class with an `HttpClient` you reuse. `BasePath` ends at `/api`: every method appends its own `/v1` path, so the `/v1` you see in the curl samples is already there. This program publishes a post to a connected LinkedIn account; `accountId` comes from `GET /v1/accounts` ([Step 4 of the quickstart](/#step-4-get-the-account-id)).

```csharp
using System;
using System.Collections.Generic;
using System.Net.Http;
using Zernio.Api;
using Zernio.Client;
using Zernio.Model;

Configuration config = new Configuration();
config.BasePath = "https://zernio.com/api";
config.AccessToken = Environment.GetEnvironmentVariable("ZERNIO_API_KEY");

HttpClient httpClient = new HttpClient();
HttpClientHandler httpClientHandler = new HttpClientHandler();
var postsApi = new PostsApi(httpClient, config, httpClientHandler);

var request = new CreatePostRequest(
    content: "Hello from the Zernio .NET SDK",
    platforms: new List<CreatePostRequestPlatformsInner>
    {
        new CreatePostRequestPlatformsInner(platform: "linkedin", accountId: "66b2e19d8c3f5a7e9d0b1c2d"),
    },
    publishNow: true
);

try
{
    var result = postsApi.CreatePost(request);
    Console.WriteLine(result);
}
catch (ApiException e)
{
    Console.WriteLine("Status code: " + e.ErrorCode);
    Console.WriteLine("Message: " + e.Message);
    Console.WriteLine(e.ErrorContent);
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

## Methods

Each method is named after the operationId of the endpoint it calls, on a class per API tag in `Zernio.Api`: `PostsApi.CreatePost`, `AccountsApi.ListAccounts`, `ProfilesApi.CreateProfile`, `AnalyticsApi.GetAnalytics`, `WebhooksApi.CreateWebhookSettings`, `WorkflowsApi.CreateWorkflow`. Request models live in `Zernio.Model`.

<Cards>
  <Card
    icon={<BookOpen />}
    title="Method reference"
    description="Every endpoint and model in the GitHub README"
    href="https://github.com/zernio-dev/zernio-dotnet#documentation-for-api-endpoints"
  />
  <Card
    icon={<Code />}
    title="API reference"
    description="Request and response schemas for every endpoint"
    href="/posts/create-post"
  />
</Cards>

## How it behaves

### One `HttpClient` serves every API class

Pass the same `HttpClient` and `HttpClientHandler` to each API class you construct, as the first call does. In ASP.NET Core, register the class with `IHttpClientFactory` instead, and pass the same `config`:

```csharp
services.AddHttpClient<PostsApi>(httpClient => new PostsApi(httpClient, config, httpClientHandler));
```

The `Configuration` is what carries `AccessToken`, so a registration that constructs `new PostsApi(httpClient)` alone sends no API key and every call returns `401`.

### `CreatePost` accepts an idempotency key

Pass `xRequestId:` (a `Guid`) to send the `x-request-id` header: `postsApi.CreatePost(request, xRequestId: Guid.NewGuid())`. A retry with the same value within about 5 minutes returns the original post instead of creating a second one ([idempotency](/guides/idempotency)).

### Errors throw `Zernio.Client.ApiException`

A non-2xx response throws `ApiException`; `ErrorCode` is the HTTP status and `ErrorContent` the JSON envelope. Running the first call twice within 24 hours throws it with `ErrorCode` `409`, because the same content is already published to that account:

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

## Related

- [Quickstart](/): API key, profile, account and first post in 5 calls.
- [Idempotency](/guides/idempotency): what `xRequestId` does.
- [Error handling](/guides/error-handling): the envelope inside `ApiException`.
- [Rate limits](/guides/rate-limits): what a `429` means and how to back off.
- [SDKs](/sdks): the other 7 clients.

---
