# Ruby

Install the zernio-sdk gem and call every Zernio API endpoint from Ruby with typed models.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { BookOpen, Code } from 'lucide-react';

`zernio-sdk` is the official Ruby gem for the Zernio API. Install it with `gem install zernio-sdk`. The gem is on [RubyGems](https://rubygems.org/gems/zernio-sdk) and the source on [GitHub](https://github.com/zernio-dev/zernio-ruby).

## First call

```bash
gem install zernio-sdk
export ZERNIO_API_KEY="sk_..."
```

Or in a Gemfile:

```ruby
gem "zernio-sdk"
```

Configure the key once with `Zernio.configure`, then instantiate the API class you need. This program publishes a post to a connected LinkedIn account; `accountId` comes from `GET /v1/accounts` ([Step 4 of the quickstart](/#step-4-get-the-account-id)).

```ruby
require 'zernio-sdk'

Zernio.configure do |config|
  config.access_token = ENV['ZERNIO_API_KEY']
end

posts_api = Zernio::PostsApi.new

request = Zernio::CreatePostRequest.new(
  content: 'Hello from the Zernio Ruby SDK',
  platforms: [
    Zernio::CreatePostRequestPlatformsInner.new(platform: 'linkedin', account_id: '66b2e19d8c3f5a7e9d0b1c2d'),
  ],
  publish_now: true
)

begin
  result = posts_api.create_post(request, debug_return_type: 'PostCreateResponse')
  puts result.post.platforms.first.platform_post_url
rescue Zernio::ApiError => e
  puts "Status: #{e.code}"
  puts "Body: #{e.response_body}"
end
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

`create_post` is generated against the endpoint's `200` response, the TikTok dry-run preview, so it returns a `Zernio::CreatePost200Response` that carries none of the `201` body's fields. Pass `debug_return_type: 'PostCreateResponse'` to get the `201` body as a typed `Zernio::PostCreateResponse`: `result.post` is the post above and `result.post.platforms.first.platform_post_url` the published URL.

## Methods

Each method is named after the operationId of the endpoint it calls, in snake_case, on a class per API tag: `Zernio::PostsApi#create_post`, `Zernio::AccountsApi#list_accounts`, `Zernio::ProfilesApi#create_profile`, `Zernio::AnalyticsApi#get_analytics`, `Zernio::WebhooksApi#create_webhook_settings`, `Zernio::WorkflowsApi#create_workflow`.

<Cards>
  <Card
    icon={<BookOpen />}
    title="Method reference"
    description="Every endpoint and model in the GitHub README"
    href="https://github.com/zernio-dev/zernio-ruby#documentation-for-api-endpoints"
  />
  <Card
    icon={<Code />}
    title="API reference"
    description="Request and response schemas for every endpoint"
    href="/posts/create-post"
  />
</Cards>

## How it behaves

### The base URL and the timeout are configuration fields

```ruby
Zernio.configure do |config|
  config.access_token = ENV['ZERNIO_API_KEY']
  config.host = 'zernio.com'
  config.base_path = '/api'
  config.timeout = 30
end
```

`scheme` defaults to `https`, `host` to `zernio.com` and `base_path` to `/api`, so a staging host means setting `host` and `base_path` together. `timeout` is in seconds and defaults to `0`, which never times out.

### Every method has a `_with_http_info` variant

It returns the data, the status code and the headers as 3 values:

```ruby
data, status_code, headers = posts_api.create_post_with_http_info(request)
```

### `create_post` accepts an idempotency key

Pass `x_request_id:` (a UUID you generate, for example `SecureRandom.uuid`) to send the `x-request-id` header: `posts_api.create_post(request, x_request_id: SecureRandom.uuid)`. A retry with the same value within about 5 minutes returns the original post instead of creating a second one ([idempotency](/guides/idempotency)).

### Errors raise `Zernio::ApiError`

A non-2xx response raises `Zernio::ApiError`; `e.code` is the HTTP status and `e.response_body` the JSON envelope. Running the first call twice within 24 hours raises it with code `409`, because the same content is already published to that account:

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
- [Idempotency](/guides/idempotency): what `x_request_id` does.
- [Error handling](/guides/error-handling): the envelope inside `Zernio::ApiError`.
- [Rate limits](/guides/rate-limits): what a `429` means and how to back off.
- [SDKs](/sdks): the other 7 clients.

---
