# Changelog

Every change to the Zernio API, newest first.

import { Changelog } from '@/components/changelog';

Significant changes are announced here, on the [Telegram channel](https://t.me/zernio_dev) and on [X](https://x.com/zernionews).

Nothing below breaks a working integration. Every endpoint is versioned in the URL path, currently `/v1`, and a breaking change ships only as a new path version: `/v1` keeps working. New endpoints, new response fields and new error codes arrive inside `/v1` at any time, which is why [error handling](/guides/error-handling#codes-are-stable-messages-are-not) asks you to branch on `code`. An operation on its way out is marked `deprecated: true` in the [OpenAPI spec](https://zernio.com/openapi.yaml) and announced here before it is removed.

<Changelog />

---
