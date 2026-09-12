# Rust

Add the zernio crate and call every Zernio API endpoint from Rust with an async reqwest-based client.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { BookOpen, Code } from 'lucide-react';

`zernio` is the official Rust client for the Zernio API, async and built on `reqwest`. Install it with `cargo add zernio`. The crate is on [crates.io](https://crates.io/crates/zernio) and the source on [GitHub](https://github.com/zernio-dev/zernio-rust).

## First call

```bash
cargo add zernio
cargo add tokio --features macros,rt-multi-thread
export ZERNIO_API_KEY="sk_..."
```

Endpoints are free functions grouped by module under `zernio::apis`, and every call takes a `Configuration` carrying the API key. This program publishes a post to a connected LinkedIn account; `accountId` comes from `GET /v1/accounts` ([Step 4 of the quickstart](/#step-4-get-the-account-id)).

```rust
use zernio::apis::{configuration::Configuration, posts_api, Error};
use zernio::models::{CreatePostRequest, CreatePostRequestPlatformsInner};

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let mut config = Configuration::new();
    config.bearer_access_token = Some(std::env::var("ZERNIO_API_KEY")?);

    let request = CreatePostRequest {
        content: Some("Hello from the Zernio Rust SDK".to_string()),
        platforms: Some(vec![
            CreatePostRequestPlatformsInner::new("linkedin".to_string(), "66b2e19d8c3f5a7e9d0b1c2d".to_string()),
        ]),
        publish_now: Some(true),
        ..Default::default()
    };

    // The third argument is the x-request-id idempotency key; None sends none.
    match posts_api::create_post(&config, request, None).await {
        Ok(post) => println!("{post:?}"),
        Err(Error::ResponseError(resp)) => {
            eprintln!("API error {}: {}", resp.status, resp.content);
        }
        Err(e) => eprintln!("transport error: {e}"),
    }
    Ok(())
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

Each function is named after the operationId of the endpoint it calls, in snake_case, in a module per API tag under `zernio::apis`: `posts_api::create_post`, `accounts_api::list_accounts`, `profiles_api::create_profile`, `analytics_api::get_analytics`, `webhooks_api::create_webhook_settings`, `workflows_api::create_workflow`. Request models live in `zernio::models`.

<Cards>
  <Card
    icon={<BookOpen />}
    title="Method reference"
    description="Every endpoint and model in the GitHub README"
    href="https://github.com/zernio-dev/zernio-rust#documentation-for-api-endpoints"
  />
  <Card
    icon={<Code />}
    title="API reference"
    description="Request and response schemas for every endpoint"
    href="/posts/create-post"
  />
</Cards>

## How it behaves

### `Configuration` also holds the base path and the HTTP client

```rust
use std::time::Duration;

let mut config = Configuration::new();
config.bearer_access_token = Some(std::env::var("ZERNIO_API_KEY")?);
config.base_path = "https://zernio.com/api".to_string();
config.client = reqwest::Client::builder().timeout(Duration::from_secs(30)).build()?;
```

`base_path` defaults to `https://zernio.com/api`; set it to point at another host. `client` is the `reqwest::Client` every call runs on, so timeouts, proxies and connection pooling are set on the builder; add `reqwest` to your own `Cargo.toml` to build one.

### `create_post` accepts an idempotency key

The third argument is `Option<&str>` sent as the `x-request-id` header; a retry with the same UUID within about 5 minutes returns the original post instead of creating a second one ([idempotency](/guides/idempotency)).

### Errors are `zernio::apis::Error<E>`

Every call returns `Result<T, Error<E>>`, where `E` is the operation's typed error. A non-2xx response is `Error::ResponseError` with `status` and the raw `content`; anything else (DNS, TLS, timeout) is a transport error. Running the first call twice within 24 hours returns `ResponseError` with status `409`, because the same content is already published to that account:

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
- [Idempotency](/guides/idempotency): what the `x_request_id` argument does.
- [Error handling](/guides/error-handling): the envelope inside `ResponseError`.
- [Rate limits](/guides/rate-limits): what a `429` means and how to back off.
- [SDKs](/sdks): the other 7 clients.

---
