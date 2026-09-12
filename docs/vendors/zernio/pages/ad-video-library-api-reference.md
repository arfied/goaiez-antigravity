# Ad video library API Reference

Lists the ad account's video library (Meta's `/act_X/advideos`), rows returned verbatim.
The default projection covers id, title, status, poster frames, length and `source` (the
playable MP4); `fields` is a raw-passthrough override. Any `id` here is reusable as
`video.id` on the create endpoints, so N ads that differ only in copy share one upload.

`source` lets you PLAY a video before picking it, which a poster frame alone can't settle
when several videos share a first frame. It is a signed CDN URL that EXPIRES, so treat it
as good for preview at selection time only. Never persist it; re-list to get a fresh one.

This is the only way to reach a video uploaded OUTSIDE Zernio (Ads Manager, another
tool); videos we uploaded also come back as `creative.videoId` on GET /v1/ads.

Meta transcodes asynchronously, so a row is only usable once `status.video_status`
reads `ready`. Upload a new video via POST /v1/ads/videos, or inline via `video.url`
on POST /v1/ads/create.

## POST /v1/ads/videos

**Upload an ad video**

Standalone ad-video upload (parallel to POST /v1/ads/images), so a video creative can
be rendered via POST /v1/ads/preview or attached via `video.id` on POST /v1/ads/create
before an ad exists.

Accepts either an https `videoUrl` we download server-side (SSRF-guarded) or raw
`videoBase64` bytes; exactly one is required. `videoBase64` is capped by Vercel's body
limit, around 4.5 MB payload in practice, so larger videos must come via `videoUrl`.

Returns the Meta `video.id` (reusable wherever `video.id` is accepted) plus Meta's
auto-generated poster URL when available. The endpoint waits until Meta reports the
video ready (chunked upload + transcode can take minutes; the handler runs up to
800 s).

### Request Body

- **accountId** (required) `string`: Zernio SocialAccount id (posting or ads variant); its platform decides where the campaign is created.
- **adAccountId** (required) `string`: Platform ad account id (Meta act_<n>, Google customer id, LinkedIn account id, ...).
- **videoUrl** `string`: Public https URL of the video; downloaded server-side (SSRF-guarded) before chunked upload. Provide exactly one of videoUrl or videoBase64.
- **videoBase64** `string`: Raw base64 video bytes, or a full data URL (the data:video/...;base64, prefix is stripped). Capped by Vercel's body limit (~4.5 MB payload). Provide exactly one of videoUrl or videoBase64.
- **filename** `string`: Optional filename shown alongside the upload session. Applied only when uploading via videoBase64.

### Responses

#### 201: Video uploaded and ready

**Response Body:**

- **adAccountId** `string`: No description
- **video** `object`: 
  - **id** `string`: Meta video id, reusable as video.id on POST /v1/ads/create and inside POST /v1/ads/preview creativeSpec.
  - **thumbnailUrl** `string,null`: Meta-hosted poster URL if available; null when Meta has not produced a poster yet.

#### 400: Invalid input, or Meta rejected the upload

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: The account or requested resource was not found or is not accessible. An account ID may have been disconnected and removed. Read GET /v1/accounts for current account IDs.

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

#### 409: The account exists but is inactive or needs reconnection. Reconnect it, then read GET /v1/accounts for its current account ID before retrying. Code: ads_connection_required.

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

#### 501: Only supported on Meta (facebook/instagram)

#### 502: Meta accepted the request then failed to produce the media (upload session, chunk transfer, processing timeout, or a response with no video id). Inspect `platformError.reason`.

---

## GET /v1/ads/videos

**Ad video library**

Lists the ad account's video library (Meta's `/act_X/advideos`), rows returned verbatim.
The default projection covers id, title, status, poster frames, length and `source` (the
playable MP4); `fields` is a raw-passthrough override. Any `id` here is reusable as
`video.id` on the create endpoints, so N ads that differ only in copy share one upload.

`source` lets you PLAY a video before picking it, which a poster frame alone can't settle
when several videos share a first frame. It is a signed CDN URL that EXPIRES, so treat it
as good for preview at selection time only. Never persist it; re-list to get a fresh one.

This is the only way to reach a video uploaded OUTSIDE Zernio (Ads Manager, another
tool); videos we uploaded also come back as `creative.videoId` on GET /v1/ads.

Meta transcodes asynchronously, so a row is only usable once `status.video_status`
reads `ready`. Upload a new video via POST /v1/ads/videos, or inline via `video.url`
on POST /v1/ads/create.

### Parameters

- **accountId** (required) in query: Zernio SocialAccount id (posting or ads variant) used to resolve the Meta token.
- **adAccountId** (required) in query: Meta ad account id (act_<n>).
- **fields** (optional) in query: Comma-separated Graph field override. Supports nested {} projections and Graph field modifiers, so a nested edge can be paged explicitly: without a .limit() modifier the expansion runs at the Meta default page size and the tail is dropped silently.
- **limit** (optional) in query: Rows per page
- **after** (optional) in query: Cursor from paging.after of the previous page.

### Responses

#### 200: Ad videos (raw Meta shape)

**Response Body:**

- **adAccountId** `string`: No description
- **data** `array[object]`: 
  Type: `object`
- **paging** `object`: 
  - **after** `string,null`: Cursor for the next page; null when exhausted.

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

#### 404: The account or requested resource was not found or is not accessible. An account ID may have been disconnected and removed. Read GET /v1/accounts for current account IDs.

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

#### 409: The account exists but is inactive or needs reconnection. Reconnect it, then read GET /v1/accounts for its current account ID before retrying. Code: ads_connection_required.

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

#### 501: Only supported on Meta (facebook/instagram)

---
