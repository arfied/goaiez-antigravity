# Post webhooks

Receive an event at every step of a post's publishing lifecycle, per platform, and for posts authored natively on the platform.

Post events tell you when a post is accepted, when each platform finishes, and when a post is later deleted or edited on the platform. Subscribe with `POST /v1/webhooks/settings` and the event names below ([first event](/webhooks#first-event)); delivery, retries and signatures are the same for every event ([how webhooks behave](/webhooks#how-it-behaves)).

## Events

| Event | Description |
| --- | --- |
| [`post.scheduled`](#postscheduled) | The post entered the scheduled state: created with a schedule, added to a queue, promoted from draft, or retried after failing. |
| [`post.platform.published`](#postplatformpublished) | One platform target finished publishing, without waiting for the others. |
| [`post.platform.failed`](#postplatformfailed) | One platform target failed permanently. |
| [`post.published`](#postpublished) | The post published on every target platform. |
| [`post.partial`](#postpartial) | The post published on some platforms and failed on others. |
| [`post.failed`](#postfailed) | The post failed on every target platform. |
| [`post.tiktok.url_resolved`](#posttiktokurl_resolved) | A published TikTok post's public URL became available. |
| [`post.platform.deleted`](#postplatformdeleted) | A published platform target was deleted on the platform. |
| [`post.cancelled`](#postcancelled) | The post's publishing job was cancelled. |
| [`post.recycled`](#postrecycled) | A recycling schedule cloned a published post for republishing. |
| [`post.external.created`](#postexternalcreated) | A post authored natively on the platform (outside Zernio) was detected for the first time. |
| [`post.external.updated`](#postexternalupdated) | A tracked native post's text or media changed on the platform. |
| [`post.external.deleted`](#postexternaldeleted) | A tracked native post was removed from the platform. |

## How it behaves

A post you publish emits events at three moments, in a fixed order:

<Mermaid
  chart={`flowchart LR
  A["post created, promoted, queued, or retried"] --> B(["post.scheduled"])
  B --> C["publish time"]
  C --> D(["post.platform.published"])
  C --> E(["post.platform.failed"])
  D --> F(["post.published / partial / failed"])
  E --> F
  F -.-> G(["post.tiktok.url_resolved"])
  F -.-> H(["post.platform.deleted"])`}
/>

### `post.scheduled` fires on every entry into the scheduled state

Zernio sends `post.scheduled` each time a post enters the scheduled state, not only at creation: a post created with a schedule (including `publishNow`, where it means accepted and queued), a draft promoted to scheduled or queued, a post added to a queue, and a failed or partial post you retry. It does not fire when an already-scheduled post is edited or moved to another time. One post can emit it more than once over its life.

### Platform events stream in as each platform finishes

Zernio sends one `post.platform.published` or `post.platform.failed` per platform target as soon as that target terminates, without waiting for the slowest one. A post targeting 3 platforms emits up to 3 of them. `post.platform.failed` fires only on permanent failure; retryable errors stay silent until they succeed or fail for good.

### The rollup fires once, after every platform has terminated

Zernio sends `post.published` when all targets succeeded, `post.partial` when the result is mixed, and `post.failed` when all failed. Exactly one of the three fires per publishing job.

### Two events can trail the rollup

Zernio sends `post.tiktok.url_resolved` minutes after the rollup, when a TikTok target already reported as published gains its public URL. `post.platform.deleted` can trail by days: a background sync, roughly hourly, detects that a target you published was later deleted on the platform.

### Post events echo the `metadata` you sent

Zernio returns the free-form `metadata` object you supplied on [Create post](/posts/create-post) as `post.metadata` on every rollup event (`post.scheduled`, `post.published`, `post.partial`, `post.failed`, `post.cancelled`, `post.recycled`) and every per-platform event (`post.platform.published`, `post.platform.failed`, `post.platform.deleted`, `post.tiktok.url_resolved`). Put your own record id in it at creation and no event needs a lookup by `post._id`. The key is omitted when the post was created without it, and the `post.external.*` events never carry it, because Zernio did not create those posts.

### Three events sit outside the pipeline

Zernio sends `post.cancelled` when a publishing job is cancelled before anything published; if a platform already published, the rollup is `post.partial` instead. `post.recycled` fires when a recycling schedule clones a published post, carries the new post's id, and that clone emits its own `post.scheduled`. The `post.external.*` family comes from the same background sync of posts authored natively on the platform, roughly hourly, not from publishing at all.

---

## `post.published`

The post published on every target platform. Each `platforms[]` entry carries the `platformPostId` and `publishedUrl`.

<br />

**Payload for `post.published`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: post.scheduled, post.published, post.failed, post.partial, post.cancelled, post.recycled
- **post** (required) `object`: 
  - **id** (required) `string`: No description
  - **content** (required) `string`: No description
  - **status** (required) `string`: No description
  - **scheduledFor** (required) `string` (date-time): No description
  - **publishedAt** `string` (date-time): No description
  - **platforms** (required) `array[object]`: 
    - **platform** (required) `string`: No description
    - **status** (required) `string`: No description
    - **accountId** `string`: SocialAccount id this platform target published through. Use it to route events by connected account (e.g. separate staging vs production endpoints). A post can span multiple accounts.
    - **platformPostId** `string`: No description
    - **publishedUrl** `string`: No description
    - **error** `string`: No description
  - **metadata** `object`: The free-form `metadata` object supplied when the post was created, echoed back so you can map events onto your own records. Omitted when the post was created without it.
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `post.failed`

The post failed on every target platform. Each `platforms[]` entry carries the platform's `error`.

<br />

**Payload for `post.failed`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: post.scheduled, post.published, post.failed, post.partial, post.cancelled, post.recycled
- **post** (required) `object`: 
  - **id** (required) `string`: No description
  - **content** (required) `string`: No description
  - **status** (required) `string`: No description
  - **scheduledFor** (required) `string` (date-time): No description
  - **publishedAt** `string` (date-time): No description
  - **platforms** (required) `array[object]`: 
    - **platform** (required) `string`: No description
    - **status** (required) `string`: No description
    - **accountId** `string`: SocialAccount id this platform target published through. Use it to route events by connected account (e.g. separate staging vs production endpoints). A post can span multiple accounts.
    - **platformPostId** `string`: No description
    - **publishedUrl** `string`: No description
    - **error** `string`: No description
  - **metadata** `object`: The free-form `metadata` object supplied when the post was created, echoed back so you can map events onto your own records. Omitted when the post was created without it.
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `post.partial`

The post published on some platforms and failed on others. Read `platforms[].status` to tell them apart.

<br />

**Payload for `post.partial`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: post.scheduled, post.published, post.failed, post.partial, post.cancelled, post.recycled
- **post** (required) `object`: 
  - **id** (required) `string`: No description
  - **content** (required) `string`: No description
  - **status** (required) `string`: No description
  - **scheduledFor** (required) `string` (date-time): No description
  - **publishedAt** `string` (date-time): No description
  - **platforms** (required) `array[object]`: 
    - **platform** (required) `string`: No description
    - **status** (required) `string`: No description
    - **accountId** `string`: SocialAccount id this platform target published through. Use it to route events by connected account (e.g. separate staging vs production endpoints). A post can span multiple accounts.
    - **platformPostId** `string`: No description
    - **publishedUrl** `string`: No description
    - **error** `string`: No description
  - **metadata** `object`: The free-form `metadata` object supplied when the post was created, echoed back so you can map events onto your own records. Omitted when the post was created without it.
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `post.cancelled`

The post's publishing job was cancelled before anything published.

<br />

**Payload for `post.cancelled`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: post.scheduled, post.published, post.failed, post.partial, post.cancelled, post.recycled
- **post** (required) `object`: 
  - **id** (required) `string`: No description
  - **content** (required) `string`: No description
  - **status** (required) `string`: No description
  - **scheduledFor** (required) `string` (date-time): No description
  - **publishedAt** `string` (date-time): No description
  - **platforms** (required) `array[object]`: 
    - **platform** (required) `string`: No description
    - **status** (required) `string`: No description
    - **accountId** `string`: SocialAccount id this platform target published through. Use it to route events by connected account (e.g. separate staging vs production endpoints). A post can span multiple accounts.
    - **platformPostId** `string`: No description
    - **publishedUrl** `string`: No description
    - **error** `string`: No description
  - **metadata** `object`: The free-form `metadata` object supplied when the post was created, echoed back so you can map events onto your own records. Omitted when the post was created without it.
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `post.scheduled`

The post entered the scheduled state: created with a schedule, added to a queue, promoted from draft, retried after a failure, or created as a recycled clone. Editing or rescheduling an already-scheduled post does not fire it.

<br />

**Payload for `post.scheduled`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: post.scheduled, post.published, post.failed, post.partial, post.cancelled, post.recycled
- **post** (required) `object`: 
  - **id** (required) `string`: No description
  - **content** (required) `string`: No description
  - **status** (required) `string`: No description
  - **scheduledFor** (required) `string` (date-time): No description
  - **publishedAt** `string` (date-time): No description
  - **platforms** (required) `array[object]`: 
    - **platform** (required) `string`: No description
    - **status** (required) `string`: No description
    - **accountId** `string`: SocialAccount id this platform target published through. Use it to route events by connected account (e.g. separate staging vs production endpoints). A post can span multiple accounts.
    - **platformPostId** `string`: No description
    - **publishedUrl** `string`: No description
    - **error** `string`: No description
  - **metadata** `object`: The free-form `metadata` object supplied when the post was created, echoed back so you can map events onto your own records. Omitted when the post was created without it.
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `post.recycled`

A recycling schedule cloned a published post for republishing. `post.id` is the new clone's id.

<br />

**Payload for `post.recycled`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: post.scheduled, post.published, post.failed, post.partial, post.cancelled, post.recycled
- **post** (required) `object`: 
  - **id** (required) `string`: No description
  - **content** (required) `string`: No description
  - **status** (required) `string`: No description
  - **scheduledFor** (required) `string` (date-time): No description
  - **publishedAt** `string` (date-time): No description
  - **platforms** (required) `array[object]`: 
    - **platform** (required) `string`: No description
    - **status** (required) `string`: No description
    - **accountId** `string`: SocialAccount id this platform target published through. Use it to route events by connected account (e.g. separate staging vs production endpoints). A post can span multiple accounts.
    - **platformPostId** `string`: No description
    - **publishedUrl** `string`: No description
    - **error** `string`: No description
  - **metadata** `object`: The free-form `metadata` object supplied when the post was created, echoed back so you can map events onto your own records. Omitted when the post was created without it.
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `post.platform.published`

One platform target finished publishing, without waiting for the other platforms on the same post. Use it for incremental UIs and `post.published` for the post-level rollup. The payload carries a `platform` block (platform post id and URL) and an `account` block identifying the connected account, so a cross-post to 2 accounts on the same platform produces 2 events.

<br />

**Payload for `post.platform.published`:**

- **id** (required) `string`: Stable webhook event ID.
- **event** (required) `string`: No description - one of: post.platform.published, post.platform.failed, post.platform.deleted, post.tiktok.url_resolved
- **post** (required) `object`: 
  - **id** (required) `string`: No description
  - **content** (required) `string`: No description
  - **status** (required) `string`: Post-level status AT FIRE TIME. May still be `publishing`
if other platforms haven't terminated; check this field
rather than assuming.

  - **scheduledFor** (required) `string` (date-time): No description
  - **publishedAt** `string` (date-time): No description
  - **platforms** (required) `array[object]`: 
    - **platform** (required) `string`: No description
    - **status** (required) `string`: No description
    - **accountId** `string`: SocialAccount id this platform target published through. On post.platform.* events see also the top-level `account` block.
    - **platformPostId** `string`: No description
    - **publishedUrl** `string`: No description
    - **error** `string`: No description
  - **metadata** `object`: The free-form `metadata` object supplied when the post was created, echoed back so you can map events onto your own records. Omitted when the post was created without it.
- **platform** (required) `object`: The specific platform that transitioned to a terminal state.
  - **name** (required) `string`: Platform name (e.g. `twitter`, `tiktok`, `instagram`).
  - **status** (required) `string`: Terminal status this event fires on. Matches the event suffix. - one of: published, failed, deleted
  - **platformPostId** `string`: Platform-native post id. Present on `published` and `deleted`, absent on `failed`.
  - **publishedUrl** `string`: Public URL to the platform-side post. Present on `published` (when the platform exposes one and it is not a draft) and on `deleted` (when one was recorded at publish time).
  - **error** `string`: Error message from the platform. Present on `failed` only.
  - **deletedAt** `string` (date-time): When the platform-side deletion was detected by Zernio sync (ISO 8601). Present only on `post.platform.deleted`.
- **account** (required) `object`: The connected account the platform-write went through.
  - **accountId** (required) `string`: No description
  - **platform** (required) `string`: No description
  - **username** (required) `string`: No description
  - **displayName** `string`: No description
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `post.platform.failed`

One platform target failed permanently. Retryable failures do not fire it, so retry loops stay quiet. The rollup (`post.failed` or `post.partial`) fires separately once every platform has terminated.

<br />

**Payload for `post.platform.failed`:**

- **id** (required) `string`: Stable webhook event ID.
- **event** (required) `string`: No description - one of: post.platform.published, post.platform.failed, post.platform.deleted, post.tiktok.url_resolved
- **post** (required) `object`: 
  - **id** (required) `string`: No description
  - **content** (required) `string`: No description
  - **status** (required) `string`: Post-level status AT FIRE TIME. May still be `publishing`
if other platforms haven't terminated; check this field
rather than assuming.

  - **scheduledFor** (required) `string` (date-time): No description
  - **publishedAt** `string` (date-time): No description
  - **platforms** (required) `array[object]`: 
    - **platform** (required) `string`: No description
    - **status** (required) `string`: No description
    - **accountId** `string`: SocialAccount id this platform target published through. On post.platform.* events see also the top-level `account` block.
    - **platformPostId** `string`: No description
    - **publishedUrl** `string`: No description
    - **error** `string`: No description
  - **metadata** `object`: The free-form `metadata` object supplied when the post was created, echoed back so you can map events onto your own records. Omitted when the post was created without it.
- **platform** (required) `object`: The specific platform that transitioned to a terminal state.
  - **name** (required) `string`: Platform name (e.g. `twitter`, `tiktok`, `instagram`).
  - **status** (required) `string`: Terminal status this event fires on. Matches the event suffix. - one of: published, failed, deleted
  - **platformPostId** `string`: Platform-native post id. Present on `published` and `deleted`, absent on `failed`.
  - **publishedUrl** `string`: Public URL to the platform-side post. Present on `published` (when the platform exposes one and it is not a draft) and on `deleted` (when one was recorded at publish time).
  - **error** `string`: Error message from the platform. Present on `failed` only.
  - **deletedAt** `string` (date-time): When the platform-side deletion was detected by Zernio sync (ISO 8601). Present only on `post.platform.deleted`.
- **account** (required) `object`: The connected account the platform-write went through.
  - **accountId** (required) `string`: No description
  - **platform** (required) `string`: No description
  - **username** (required) `string`: No description
  - **displayName** `string`: No description
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `post.platform.deleted`

Zernio's background sync detected that a platform target you published was later deleted on the platform, for example the user removed the Instagram post in the Instagram app. Detection is poll-driven, roughly hourly, because platforms push no deletion notice for published media: Zernio diffs the platform's post listing and probes posts that stop resolving. `platform.status` is `deleted` and `platform.deletedAt` is the detection time, not the moment of deletion. Coverage is bounded to the posts the platform's listing returns, so deletions of very old posts may go undetected.

<Callout type="info">
  Detection is listing-based, so a rare false positive is possible, for example a platform API briefly omitting a post. Zernio heals its own data when the post reappears but does not retract the webhook. Re-check the post against the platform before deleting on your side.
</Callout>

<br />

**Payload for `post.platform.deleted`:**

- **id** (required) `string`: Stable webhook event ID.
- **event** (required) `string`: No description - one of: post.platform.published, post.platform.failed, post.platform.deleted, post.tiktok.url_resolved
- **post** (required) `object`: 
  - **id** (required) `string`: No description
  - **content** (required) `string`: No description
  - **status** (required) `string`: Post-level status AT FIRE TIME. May still be `publishing`
if other platforms haven't terminated; check this field
rather than assuming.

  - **scheduledFor** (required) `string` (date-time): No description
  - **publishedAt** `string` (date-time): No description
  - **platforms** (required) `array[object]`: 
    - **platform** (required) `string`: No description
    - **status** (required) `string`: No description
    - **accountId** `string`: SocialAccount id this platform target published through. On post.platform.* events see also the top-level `account` block.
    - **platformPostId** `string`: No description
    - **publishedUrl** `string`: No description
    - **error** `string`: No description
  - **metadata** `object`: The free-form `metadata` object supplied when the post was created, echoed back so you can map events onto your own records. Omitted when the post was created without it.
- **platform** (required) `object`: The specific platform that transitioned to a terminal state.
  - **name** (required) `string`: Platform name (e.g. `twitter`, `tiktok`, `instagram`).
  - **status** (required) `string`: Terminal status this event fires on. Matches the event suffix. - one of: published, failed, deleted
  - **platformPostId** `string`: Platform-native post id. Present on `published` and `deleted`, absent on `failed`.
  - **publishedUrl** `string`: Public URL to the platform-side post. Present on `published` (when the platform exposes one and it is not a draft) and on `deleted` (when one was recorded at publish time).
  - **error** `string`: Error message from the platform. Present on `failed` only.
  - **deletedAt** `string` (date-time): When the platform-side deletion was detected by Zernio sync (ISO 8601). Present only on `post.platform.deleted`.
- **account** (required) `object`: The connected account the platform-write went through.
  - **accountId** (required) `string`: No description
  - **platform** (required) `string`: No description
  - **username** (required) `string`: No description
  - **displayName** `string`: No description
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `post.tiktok.url_resolved`

A published TikTok post's public URL became available. TikTok exposes the numeric video id asynchronously, often minutes after the upload completes, so `post.published` can carry an empty `publishedUrl` for TikTok. This event delivers the resolved URL and platform post id, at most once per platform target. It never fires for drafts or private posts, which have no public URL.

<br />

**Payload for `post.tiktok.url_resolved`:**

- **id** (required) `string`: Stable webhook event ID.
- **event** (required) `string`: No description - one of: post.platform.published, post.platform.failed, post.platform.deleted, post.tiktok.url_resolved
- **post** (required) `object`: 
  - **id** (required) `string`: No description
  - **content** (required) `string`: No description
  - **status** (required) `string`: Post-level status AT FIRE TIME. May still be `publishing`
if other platforms haven't terminated; check this field
rather than assuming.

  - **scheduledFor** (required) `string` (date-time): No description
  - **publishedAt** `string` (date-time): No description
  - **platforms** (required) `array[object]`: 
    - **platform** (required) `string`: No description
    - **status** (required) `string`: No description
    - **accountId** `string`: SocialAccount id this platform target published through. On post.platform.* events see also the top-level `account` block.
    - **platformPostId** `string`: No description
    - **publishedUrl** `string`: No description
    - **error** `string`: No description
  - **metadata** `object`: The free-form `metadata` object supplied when the post was created, echoed back so you can map events onto your own records. Omitted when the post was created without it.
- **platform** (required) `object`: The specific platform that transitioned to a terminal state.
  - **name** (required) `string`: Platform name (e.g. `twitter`, `tiktok`, `instagram`).
  - **status** (required) `string`: Terminal status this event fires on. Matches the event suffix. - one of: published, failed, deleted
  - **platformPostId** `string`: Platform-native post id. Present on `published` and `deleted`, absent on `failed`.
  - **publishedUrl** `string`: Public URL to the platform-side post. Present on `published` (when the platform exposes one and it is not a draft) and on `deleted` (when one was recorded at publish time).
  - **error** `string`: Error message from the platform. Present on `failed` only.
  - **deletedAt** `string` (date-time): When the platform-side deletion was detected by Zernio sync (ISO 8601). Present only on `post.platform.deleted`.
- **account** (required) `object`: The connected account the platform-write went through.
  - **accountId** (required) `string`: No description
  - **platform** (required) `string`: No description
  - **username** (required) `string`: No description
  - **displayName** `string`: No description
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `post.external.created`

Zernio's background sync detected a post authored natively on the platform, outside Zernio, such as a Google Business Profile post created in the Google interface. Detection is poll-driven, roughly hourly, because most platforms push no notice for merchant-authored posts. `post.source` is always `"external"` and `post.id` is the platform-native post id.

<Callout type="info">
  On a freshly connected account, every existing native post is reported as `post.external.created` on the first sync, a one-time backfill. Treat `created` as an idempotent upsert keyed on `post.id`.
</Callout>

<br />

**Payload for `post.external.created`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: post.external.created, post.external.updated, post.external.deleted
- **post** (required): `ExternalPostWebhookPost` - See schema definition
- **account** (required) `object`: 
  - **id** (required) `string`: No description
  - **accountId** `string`: Account ID (same as id); canonical field for account filtering.
  - **platform** (required) `string`: No description
  - **username** (required) `string`: No description
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `post.external.updated`

A tracked native post's text or media changed on the platform. Zernio detects edits by comparing the post's text and media structure and, where the platform exposes one, the platform's own edit timestamp. A media-URL-only refresh (some platforms rotate expiring CDN URLs) does not fire it.

<br />

**Payload for `post.external.updated`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: post.external.created, post.external.updated, post.external.deleted
- **post** (required): `ExternalPostWebhookPost` - See schema definition
- **account** (required) `object`: 
  - **id** (required) `string`: No description
  - **accountId** `string`: Account ID (same as id); canonical field for account filtering.
  - **platform** (required) `string`: No description
  - **username** (required) `string`: No description
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `post.external.deleted`

A tracked native post was removed from the platform. `post.deletedAt` is the detection time. Coverage is bounded to the most recent posts the platform's listing returns, so deletions of very old posts may go undetected.

<br />

**Payload for `post.external.deleted`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: post.external.created, post.external.updated, post.external.deleted
- **post** (required): `ExternalPostWebhookPost` - See schema definition
- **account** (required) `object`: 
  - **id** (required) `string`: No description
  - **accountId** `string`: Account ID (same as id); canonical field for account filtering.
  - **platform** (required) `string`: No description
  - **username** (required) `string`: No description
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

## Related

- [Webhooks](/webhooks): create an endpoint, retries, signatures.
- [Post lifecycle](/guides/post-lifecycle): the `status` values these events mirror.
- [Error handling](/guides/error-handling): what `platforms[].error` contains.
- [Get post](/posts/get-post): the same data on demand.
- [Inbox webhooks](/webhooks/inbox): comments on these posts.

---
