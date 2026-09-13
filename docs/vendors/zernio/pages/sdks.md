# SDKs

Call the Zernio API from Node.js, Python, Go, Ruby, Java, PHP, .NET or Rust with an official client, or generate your own from the OpenAPI spec.

import { Cards, Card } from 'fumadocs-ui/components/card';
import {
  SiNodedotjs,
  SiPython,
  SiGo,
  SiRuby,
  SiOpenjdk,
  SiPhp,
  SiDotnet,
  SiRust,
} from 'react-icons/si';

Zernio ships an official client for 8 languages, each generated from the same OpenAPI spec, so every endpoint (posts, accounts, profiles, analytics, inbox, ads, phone numbers, WhatsApp, workflows) is a method call. Install the client for your language and set `ZERNIO_API_KEY` ([API keys](https://zernio.com/dashboard/api-keys)).

## Clients

Each page has the install command, a first call in that language, and the client's own rules (errors, async, idempotency keys).

<Cards>
  <Card
    icon={<SiNodedotjs />}
    title="Node.js"
    description="npm install @zernio/node. TypeScript types for every request and response."
    href="/sdks/node"
  />
  <Card
    icon={<SiPython />}
    title="Python"
    description="pip install zernio-sdk. Sync and async clients, direct media uploads."
    href="/sdks/python"
  />
  <Card
    icon={<SiGo />}
    title="Go"
    description="go get github.com/zernio-dev/zernio-go. Context-first, builder-style requests."
    href="/sdks/go"
  />
  <Card
    icon={<SiRuby />}
    title="Ruby"
    description="gem install zernio-sdk. Typed models with per-call response metadata."
    href="/sdks/ruby"
  />
  <Card
    icon={<SiOpenjdk />}
    title="Java"
    description="com.zernio:zernio-sdk on Maven Central. Built on java.net.http, Java 11 or later."
    href="/sdks/java"
  />
  <Card
    icon={<SiPhp />}
    title="PHP"
    description="composer require zernio-dev/zernio-php. Guzzle-based, PHP 8.1 or later."
    href="/sdks/php"
  />
  <Card
    icon={<SiDotnet />}
    title=".NET"
    description="dotnet add package Zernio. C# client with reusable HttpClient support."
    href="/sdks/dotnet"
  />
  <Card
    icon={<SiRust />}
    title="Rust"
    description="cargo add zernio. Async client built on reqwest."
    href="/sdks/rust"
  />
</Cards>

The [Chat SDK adapter](/resources/integrations/chat-sdk) (`@zernio/chat-sdk-adapter`, [source](https://github.com/zernio-dev/chat-sdk-adapter)) is a separate package for chatbots that answer Instagram, Facebook, Telegram, WhatsApp, X, Bluesky and Reddit conversations through Vercel's [Chat SDK](https://chat-sdk.dev).

## How it behaves

### Method names follow the operationId

The operationId of each endpoint in the API reference is the method name in every client: `createPost` on `POST /v1/posts` is `zernio.posts.createPost` in Node.js, `client.posts.create_post` in Python, `PostsAPI.CreatePost` in Go and `posts_api::create_post` in Rust. The namespace is the endpoint's tag (`posts`, `accounts`, `webhooks`).

### Every client is generated from the OpenAPI spec

The full API is one OpenAPI 3.1 document at [zernio.com/openapi.yaml](https://zernio.com/openapi.yaml). Generate a client for a language Zernio does not ship with any OpenAPI generator; the spec is also the source of the [API reference](/posts/create-post).

## Related

- [Quickstart](/): API key, profile, account and first post in 5 calls.
- [Error handling](/guides/error-handling): the error envelope every client surfaces.
- [Idempotency](/guides/idempotency): the `x-request-id` header the clients expose.
- [CLI](/cli) and [MCP](/mcp): the same API from a terminal or an AI assistant.

---
