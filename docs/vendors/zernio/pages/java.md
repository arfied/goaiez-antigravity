# Java

Add com.zernio:zernio-sdk and call every Zernio API endpoint from Java 11 or later on the JDK's java.net.http client.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { BookOpen, Code } from 'lucide-react';

`com.zernio:zernio-sdk` is the official Java client for the Zernio API, built on the JDK's `java.net.http` client; it needs Java 11 or later. Add it from [Maven Central](https://central.sonatype.com/artifact/com.zernio/zernio-sdk); the source is on [GitHub](https://github.com/zernio-dev/zernio-java). The Maven groupId is `com.zernio` and the Java package is `dev.zernio.*`.

## First call

Add the dependency (check Maven Central for the latest version):

```xml
<dependency>
  <groupId>com.zernio</groupId>
  <artifactId>zernio-sdk</artifactId>
  <version>0.0.803</version>
</dependency>
```

Or with Gradle:

```groovy
implementation "com.zernio:zernio-sdk:0.0.803"
```

Attach the API key with a request interceptor. This program publishes a post to a connected LinkedIn account; `accountId` comes from `GET /v1/accounts` ([Step 4 of the quickstart](/#step-4-get-the-account-id)).

```java
import java.util.List;

import dev.zernio.ApiClient;
import dev.zernio.ApiException;
import dev.zernio.Configuration;
import dev.zernio.api.PostsApi;
import dev.zernio.model.CreatePostRequest;
import dev.zernio.model.CreatePostRequestPlatformsInner;

public class Example {

    public static void main(String[] args) {
        ApiClient client = Configuration.getDefaultApiClient();
        String apiKey = System.getenv("ZERNIO_API_KEY");
        client.setRequestInterceptor(b -> b.header("Authorization", "Bearer " + apiKey));

        PostsApi postsApi = new PostsApi(client);

        CreatePostRequest request = new CreatePostRequest()
            .content("Hello from the Zernio Java SDK")
            .platforms(List.of(
                new CreatePostRequestPlatformsInner().platform("linkedin").accountId("66b2e19d8c3f5a7e9d0b1c2d")
            ))
            .publishNow(true);

        try {
            var result = postsApi.createPost(request, null);
            System.out.println(result);
        } catch (ApiException e) {
            System.err.println("Status code: " + e.getCode());
            System.err.println("Body: " + e.getResponseBody());
            System.err.println("Headers: " + e.getResponseHeaders());
        }
    }
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

Each method is named after the operationId of the endpoint it calls, on a class per API tag in `dev.zernio.api`: `PostsApi#createPost`, `AccountsApi#listAccounts`, `ProfilesApi#createProfile`, `AnalyticsApi#getAnalytics`, `WebhooksApi#createWebhookSettings`, `WorkflowsApi#createWorkflow`. Request models in `dev.zernio.model` use fluent setters.

<Cards>
  <Card
    icon={<BookOpen />}
    title="Method reference"
    description="Every endpoint and model in the GitHub README"
    href="https://github.com/zernio-dev/zernio-java#documentation-for-api-endpoints"
  />
  <Card
    icon={<Code />}
    title="API reference"
    description="Request and response schemas for every endpoint"
    href="/posts/create-post"
  />
</Cards>

## How it behaves

### The base URI and the read timeout are `ApiClient` settings

```java
import java.time.Duration;

ApiClient client = Configuration.getDefaultApiClient();
client.updateBaseUri("https://zernio.com/api");
client.setReadTimeout(Duration.ofSeconds(30));

PostsApi postsApi = new PostsApi(client);
```

`updateBaseUri` defaults to `https://zernio.com/api`; pass another URI to point at a different host. `setReadTimeout` takes a `java.time.Duration` and is unset by default, so a request has no timeout of its own. Each API class copies the base URI, the read timeout and the interceptor from the `ApiClient` when you construct it, so set them before `new PostsApi(client)`.

### `createPost` accepts an idempotency key

The second argument is a `UUID` sent as the `x-request-id` header; a retry with the same value within about 5 minutes returns the original post instead of creating a second one ([idempotency](/guides/idempotency)). Use one key per logical post, derived from your own id for it, and send that same key on every retry:

```java
import java.nio.charset.StandardCharsets;
import java.util.UUID;

UUID requestId = UUID.nameUUIDFromBytes("launch-2027-01-01".getBytes(StandardCharsets.UTF_8));

var retried = postsApi.createPost(request, requestId);
```

A fresh `UUID.randomUUID()` on each attempt makes the retry a new request, which creates a second post. Pass `null` to send no key, as the first call does.

### Errors throw `dev.zernio.ApiException`

A non-2xx response throws `ApiException`; `getCode()` is the HTTP status, `getResponseBody()` the JSON envelope and `getResponseHeaders()` the headers. Running the first call twice within 24 hours throws it with code `409`, because the same content is already published to that account:

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

Change the content, or send the same `UUID` twice so the retry returns the original post. The [error handling guide](/guides/error-handling) lists every code.

## Related

- [Quickstart](/): API key, profile, account and first post in 5 calls.
- [Idempotency](/guides/idempotency): what the `x-request-id` UUID does.
- [Error handling](/guides/error-handling): the envelope inside `ApiException`.
- [Rate limits](/guides/rate-limits): what a `429` means and how to back off.
- [SDKs](/sdks): the other 7 clients.

---
