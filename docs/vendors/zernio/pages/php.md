# PHP

Install zernio-dev/zernio-php with Composer and call every Zernio API endpoint from PHP 8.1 or later on Guzzle.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { BookOpen, Code } from 'lucide-react';

`zernio-dev/zernio-php` is the official PHP client for the Zernio API, built on Guzzle; it needs PHP 8.1 or later. Install it with `composer require zernio-dev/zernio-php`. The package is on [Packagist](https://packagist.org/packages/zernio-dev/zernio-php) and the source on [GitHub](https://github.com/zernio-dev/zernio-php).

## First call

```bash
composer require zernio-dev/zernio-php
export ZERNIO_API_KEY="sk_..."
```

Set the API key on a `Configuration`, then construct the API class you need with a Guzzle client. This script publishes a post to a connected LinkedIn account; `accountId` comes from `GET /v1/accounts` ([Step 4 of the quickstart](/#step-4-get-the-account-id)).

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

$config = Zernio\Configuration::getDefaultConfiguration()
    ->setAccessToken(getenv('ZERNIO_API_KEY'));

$postsApi = new Zernio\Api\PostsApi(new GuzzleHttp\Client(), $config);

$linkedin = new Zernio\Model\CreatePostRequestPlatformsInner();
$linkedin->setPlatform('linkedin');
$linkedin->setAccountId('66b2e19d8c3f5a7e9d0b1c2d');

$request = new Zernio\Model\CreatePostRequest();
$request->setContent('Hello from the Zernio PHP SDK');
$request->setPlatforms([$linkedin]);
$request->setPublishNow(true);

try {
    $result = $postsApi->createPost($request);
    print_r($result);
} catch (Zernio\ApiException $e) {
    echo 'Status: ', $e->getCode(), PHP_EOL;
    echo 'Body: ', $e->getResponseBody(), PHP_EOL;
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

`$result` is a `\Zernio\Model\PostCreateResponse` model, not raw JSON: `$result->getPost()` returns the `post` object above and `$result->getPost()->getPlatforms()[0]->getPlatformPostUrl()` the published URL. Every method returns the model for the status code it got back.

## Methods

Each method is named after the operationId of the endpoint it calls, on a class per API tag in `Zernio\Api`: `PostsApi::createPost`, `AccountsApi::listAccounts`, `ProfilesApi::createProfile`, `AnalyticsApi::getAnalytics`, `WebhooksApi::createWebhookSettings`, `WorkflowsApi::createWorkflow`. Request models live in `Zernio\Model` and use `set...` methods.

<Cards>
  <Card
    icon={<BookOpen />}
    title="Method reference"
    description="Every endpoint and model in the GitHub README"
    href="https://github.com/zernio-dev/zernio-php#api-endpoints"
  />
  <Card
    icon={<Code />}
    title="API reference"
    description="Request and response schemas for every endpoint"
    href="/posts/create-post"
  />
</Cards>

## How it behaves

### The base URL is on `Configuration`, the timeout on the Guzzle client

```php
$config = Zernio\Configuration::getDefaultConfiguration()
    ->setAccessToken(getenv('ZERNIO_API_KEY'))
    ->setHost('https://zernio.com/api');

$postsApi = new Zernio\Api\PostsApi(new GuzzleHttp\Client(['timeout' => 30]), $config);
```

`setHost` defaults to `https://zernio.com/api`; pass another URL to point at a different host. Timeouts are Guzzle's, not the SDK's: the client you hand the API class carries them, `timeout` in seconds for the whole request and `connect_timeout` for the connection.

### The `Late\` namespace still resolves

Use the `Zernio\` namespace in new code. The legacy `Late\` namespace is aliased on load, so code written against the Late SDK keeps working.

### `createPost` accepts an idempotency key

The second argument is sent as the `x-request-id` header: `$postsApi->createPost($request, $xRequestId)`. A retry with the same value within about 5 minutes returns the original post instead of creating a second one ([idempotency](/guides/idempotency)).

### Errors throw `Zernio\ApiException`

A non-2xx response throws `Zernio\ApiException`; `getCode()` is the HTTP status and `getResponseBody()` the JSON envelope. Running the first call twice within 24 hours throws it with code `409`, because the same content is already published to that account:

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
- [Idempotency](/guides/idempotency): what `$xRequestId` does.
- [Error handling](/guides/error-handling): the envelope inside `ApiException`.
- [Rate limits](/guides/rate-limits): what a `429` means and how to back off.
- [SDKs](/sdks): the other 7 clients.

---
