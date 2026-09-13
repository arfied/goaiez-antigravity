# List posts API Reference

Returns a paginated list of posts. Published posts include platformPostUrl with the public URL on each platform.

## GET /v1/posts

**List posts**

Returns a paginated list of posts. Published posts include platformPostUrl with the public URL on each platform.

### Parameters

- **undefined** (optional): No description
- **limit** (optional) in query: Page size. Values above the maximum return 400 rather than being clamped.
- **source** (optional) in query: Which collection to read. `zernio` (default) returns posts authored through Zernio. `external` returns posts synced from the platform (existing/historical posts that were published outside Zernio). Combine with `accountId` and paginate via `page`/`limit` to walk the full synced history (we keep up to the last ~12 months per account).
- **status** (optional) in query: No description
- **platform** (optional) in query: No description
- **profileId** (optional) in query: Filter posts to a specific profile (24-char hex ObjectId). Omit it, or send `all` or an empty value, to list posts across every profile.
- **createdBy** (optional) in query: Filter posts to those created by a specific team user (24-char hex ObjectId).
- **dateFrom** (optional) in query: Zero-padded YYYY-MM-DD, or a full ISO 8601 datetime. An empty value means no date filter; any other malformed value returns 400.
- **dateTo** (optional) in query: Zero-padded YYYY-MM-DD, or a full ISO 8601 datetime. An empty value means no date filter; any other malformed value returns 400.
- **includeHidden** (optional) in query: No description
- **search** (optional) in query: Search posts by text content.
- **sortBy** (optional) in query: Sort order for results.
- **accountId** (optional) in query: Filter posts to those published via a specific account (24-char hex ObjectId).

### Responses

#### 200: Paginated posts

**Response Body:**

- **posts** `array[Post]`: 
- **pagination**: `Pagination` - See schema definition

#### 400: Invalid request

**Response Body:**

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

## POST /v1/posts

**Create post**

Create a post, and optionally publish it in the same request. A post published immediately (`publishNow: true`) comes back with `platformPostUrl` in the response.

`content` is optional in four cases:

- media is attached
- all platforms have `customContent`
- every platform entry is an X Article (`platformSpecificData.article`)
- every platform entry is a LinkedIn text-free reshare (`platformSpecificData.reshareUrl` with no text)

See each platform's schema for media constraints.

## Scheduling

Pick one of:

- `scheduledFor`: publish at the scheduled time
- `publishNow: true`: publish synchronously, inside this request
- `queuedFromProfile`: publish in the profile's next queue slot

With none of them and `isDraft` unset, the post is saved as a draft. `platforms` is required unless the post is a draft.

Precedence: `isDraft: true` wins over `publishNow` and `scheduledFor` (the post is saved, never published), and `publishNow: true` wins over `scheduledFor`. A `scheduledFor` already in the past is not rejected: the post is published synchronously in the same request, exactly like `publishNow`.

## Idempotency

Two layers of duplicate-protection apply, so safe-to-retry callers (network blips, n8n / Zapier retries, etc.) don't accidentally double-post.

**1. Same-request idempotency (5-minute window).**
Pass an `x-request-id` header to mark a logical request. If a second request arrives with the same `x-request-id` while the first is in-flight (or within ~5 minutes of completion), we return **HTTP 200** with the original post in the `existingPost` field, and no new post is created.

The official Zernio SDKs auto-generate a unique `x-request-id` per call. On a generic HTTP client (curl, n8n's HTTP node, Zapier, custom code), either:

- Set a unique `x-request-id` per logical call (recommended, UUIDv4 is fine)
- Or omit the header, and we'll treat each request as new

**Common pitfall**: if your workflow tool uses a single execution-level request ID and reuses it across multiple HTTP nodes (e.g. one ID for the whole run, shared across 6 different platform calls), every call after the first will look like a retry of the first and return its post. Generate a fresh ID per node.

**2. Content-hash dedup (24-hour window).**
Independently, we hash `(platform, accountId, content + media URLs)` and reject duplicates within 24 hours with **HTTP 409**. This catches genuine "same content posted twice to the same account" cases regardless of `x-request-id`. The response carries `error`, `accountId`, `platform`, and `existingPostId` so you can find the original.

To intentionally re-post identical content within 24h, change something (the caption, the media, the account), because the dedup is keyed on the full content fingerprint.

Order: same-`x-request-id` retries (200) are checked first; if no idempotency match, the content-hash dedup (409) runs.


### Parameters

- **x-request-id** (optional) in header: Optional client-generated request identifier for safe retry (idempotency). When two requests carry the same value, the second is treated as a retry of the first and returns the original post (HTTP 200) instead of creating a duplicate. Window is ~5 minutes from the first request. Generate a UUID per logical call. SDKs do this automatically; HTTP clients should set it themselves or omit it. See the operation description for the full idempotency contract.


### Request Body

- **title** `string`: Stored on the post for reference/display only. This field is NOT used as the video title when publishing. To set a YouTube video title, use platformSpecificData.title on the youtube platform target (falls back to the first line of content when omitted).
- **content** `string`: Post caption/text. Optional when media is attached, all platforms have customContent, every platform entry is an X Article (platformSpecificData.article), or every platform entry is a LinkedIn text-free reshare (platformSpecificData.reshareUrl with no text). Required for other text-only posts.
- **mediaItems** `array`: Media attached to every platform in the request (a platform entry can override it with `customMedia`). Each entry needs a publicly reachable HTTPS `url`; `type` (image, video, gif, document) is inferred from the URL extension when omitted and a `type` that contradicts the extension is rejected with 400. Upload files with `POST /v1/media/presign` first; per-platform size, duration and format limits are listed on each platform schema.
- **platforms** `array`: Target platforms and accounts for this post. Required for non-draft posts (returns 400 if empty). Drafts can omit platforms.
- **scheduledFor** `string`: When to publish. Required unless `publishNow` is true, `queuedFromProfile` is set, or the post is a draft. An ISO 8601 value with a `Z` or offset (`2026-01-15T10:00:00Z`, `2026-01-15T11:00:00+01:00`) is taken as-is; a value without one (`2026-01-15T10:00:00` or `2026-01-15 10:00`) is read as local time in `timezone`. A value already in the past is published synchronously in the same request. Ignored when `publishNow` is true.
- **publishNow** `boolean`: Publish to every platform synchronously in this request instead of scheduling; the response then carries each platform result and `platformPostUrl`, with HTTP 207 when some platforms failed. Takes precedence over `scheduledFor`; ignored when `isDraft` is true.
- **isDraft** `boolean`: When true, saves the post as a draft. When none of scheduledFor, publishNow, or queuedFromProfile are provided, the post defaults to draft automatically.
- **dryRun** `boolean`: TikTok only. Preview whether each `tiktok` entry in `platforms` could publish right now under the TikTok Direct Post daily limits, without creating, scheduling or publishing anything: no post is persisted and no upload slot is claimed, so it can be repeated freely. The request still goes through auth, the payment gate and body validation, then returns HTTP 200 with `{ dryRun: true, canPublish, tiktok: [...] }` instead of 201. Only `tiktok` entries are evaluated; other platforms in the body are ignored, and a body with no `tiktok` entry is rejected with 400 `invalid_field_value` on `platforms`. An entry with `platformSpecificData.tiktokSettings.draft: true` (Creator Inbox upload) is not subject to the limit and always reports `canPublish: true`. Accounts connected through the TikTok for Business app do not go through these limits at all and also always report `canPublish: true`, so on those accounts a dry run confirms the request is well-formed rather than gating it.
- **timezone** `string`: IANA timezone (`Europe/Madrid`, `America/New_York`) used to interpret a `scheduledFor` (root or per-platform) that carries no `Z` or offset. Has no effect on values that already carry one. An unknown name returns 400 when `scheduledFor` is set.
- **tags** `array`: Tags/keywords. YouTube constraints: each tag max 100 chars, combined max 500 chars, duplicates auto-removed.
- **hashtags** `array`: Stored for reference only. Hashtags are NOT automatically appended to the caption when publishing. Include hashtags directly in the content field (platforms like Instagram only support hashtags as caption text). For YouTube keywords, use the tags field instead.
- **mentions** `array`: Stored for reference only. This field does NOT automatically create @mentions when publishing. For LinkedIn @mentions, use the /v1/accounts/{accountId}/linkedin-mentions endpoint to resolve profile URLs to URNs, then embed the returned mentionFormat directly in the post content field.
- **crosspostingEnabled** `boolean`: Stored on the post and echoed back on reads. Publishing does not branch on it: every entry in `platforms` is published regardless, so treat it as a label for your own tooling.
- **metadata** `object`: Free-form key/value pairs of your own, stored on the post and returned on reads and in webhook payloads. Zernio also writes the bookkeeping keys `usageCounted`, `usageRefunded` and `hidden` into this object; do not set them, and they are stripped from webhook payloads.
- **tiktokSettings**: Root-level TikTok settings applied to the TikTok platforms sent in the same request. Merged into each platform's platformSpecificData, with platform-specific settings taking precedence.
- **facebookSettings**: Root-level Facebook settings applied to the Facebook platforms sent in the same request. Merged into each platform's platformSpecificData.facebookSettings, with platform-specific settings taking precedence.
- **recycling**: No description
- **queuedFromProfile** `string`: Profile ID to schedule via queue. When provided without scheduledFor, the post is auto-assigned to the next available slot. Do not call /v1/queue/next-slot and use that time in scheduledFor, as that bypasses queue locking.
- **queueId** `string`: Specific queue ID to use when scheduling via queue.
Only used when queuedFromProfile is also provided.
If omitted, uses the profile's default queue.


### Responses

#### 200: A dryRun preview (TikTok only): nothing was created. Deliberately carries no numeric
cap detail, only a per-account go/no-go and a reason.

The schema is a union only so generated clients can type both success shapes of this
operation: a 200 is always the dry-run verdict, and a created post is always a 201.


**Response Body:**

*One of the following:*
- `TikTokDryRunVerdict`
- `PostCreateResponse`

#### 201: Post created

**Response Body:**

- **message** `string`: No description
- **post**: `Post` - See schema definition
- **warnings** `array[string]`: Advisory notices about a post that was still created: media truncated for a platform, a recycling caveat, or a field that was ignored because it sat outside platforms[].platformSpecificData. Absent when there are none.

#### 207: The post was created, but the inline publish (`publishNow: true`, or a `scheduledFor` that is already due) did not fully succeed.

**207 is a 2xx status.** `fetch(...).ok` is `true` and axios' default `validateStatus` resolves, so a client that only checks for success will read this as a published post. Branch on the status code explicitly.

Tell the outcomes apart with `post.status`:
- `partial` - at least one platform published and at least one failed. Per-platform detail is in `platformResults` and in `post.platforms[]`.
- `failed` - no platform published. Terminal; nothing will be retried. Read `platforms[].errorMessage`, `platforms[].errorCategory` and `platforms[].errorSource` to decide whether the caller, the platform or Zernio must act.
- `scheduled` - every platform hit a transient error and was reset to `pending`. Zernio retries automatically. This is **not** a failure and must not be surfaced to an end user as one.

A publish attempt that aborted before it started (for example the post was already being processed) reports none of the three: `post.status` is whatever it already was and `platformResults` is absent. Read `error` and `post.platforms[]`, which is always present.


**Response Body:**

- **post**: `Post` - See schema definition
- **message** `string`: Human-readable summary of the publish outcome.
- **error** `string`: Present when no platform published. Absent on a partial success. Informational only; the per-platform detail is in `platformResults` and in `post.platforms[]`.
- **platformResults** `array[object]`: Per-platform outcome of the publish attempt. Omitted when the attempt aborted before producing per-platform results (for example the post was already being processed); read `post.platforms[]` in that case.
  - **platform** (required) `string`: Platform slug, matching `post.platforms[].platform`.
  - **status** (required) `string`: Per-platform status: pending, processing, published, failed, cancelled, uploading. (example: "failed")
  - **error** (required) `string,null`: Failure detail for this platform, or null when it did not fail.
- **warnings** `array[string]`: Advisory notices about the post that was still created. Absent when there are none.

#### 400: Validation error

**Response Body:**

- **error** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Forbidden. Distinguish by the `code` field:
- `ACCOUNT_DISCONNECTED`: a target account exists but its platform connection is no longer active (token expired or revoked, or the account was disconnected). Reconnect the account, then refresh account IDs from `GET /v1/accounts` (accounts report their connection state via `isActive`). The disconnect itself is also emitted as the `account.disconnected` webhook event.
- `ACCOUNT_NOT_ENABLED_FOR_POSTING`: a target account was connected for ads only (`enabled: false`) and cannot be posted to. Connect it as a posting account (it then counts as a connected account), then refresh account IDs from `GET /v1/accounts`.
- `PROFILE_OVER_LIMIT`: a target account belongs to a profile beyond the plan's profile limit.
- No `code`: a target `accountId` does not belong to the authenticated user (or is outside the API key's profile scope).


**Response Body:**

- **error** `string`: No description (example: "Account 6a0f6d2e520992756d96bb6c (facebook \"My Page\") is disconnected and cannot be posted to. Facebook tokens expired. Please reconnect your Facebook account. After reconnecting, refresh your account IDs from GET /v1/accounts.")
- **code** `string`: Stable machine-readable cause. Absent for ownership failures. - one of: ACCOUNT_DISCONNECTED, ACCOUNT_NOT_ENABLED_FOR_POSTING, PROFILE_OVER_LIMIT

#### 409: Duplicate content detected. Returned when the requested post matches an existing one on `(platform, accountId, content-hash)` within the last 24 hours, AND the request was NOT an `x-request-id` retry of an in-flight call. Distinct from same-`x-request-id` retries (which return HTTP 200 with the original post; see operation description for the idempotency contract).

Body fields:
- `error`: human-readable message
- `details.accountId`: the account that already has this content
- `details.platform`: the platform that already has this content
- `details.existingPostId`: Zernio `_id` of the original post

To intentionally re-post identical content within 24h, vary the content fingerprint (change the caption, swap a media item, or use a different account). To avoid 409s caused by retry loops, set a unique `x-request-id` per logical request. See `parameters.x-request-id` above.


**Response Body:**

- **error** `string`: No description (example: "This exact content is already scheduled, publishing, or was posted to this account within the last 24 hours.")
- **details** `object`: 
  - **accountId** `string`: No description
  - **platform** `string`: No description
  - **existingPostId** `string`: No description

#### 429: Rate limit exceeded. Possible causes: API rate limit, velocity limit (25 posts/hour per account), account cooldown, or daily platform limits.

**Response Body:**

- **error** `string`: No description
- **details** `object`: Additional context about the rate limit

---
