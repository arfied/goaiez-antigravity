# Send conversion events API Reference

Relay one or more conversion events to the target ad platform's native Conversions API.
Platform is inferred from the provided `accountId`. Requires the Ads add-on.

Supported platforms:

- Meta (`metaads`) via Graph API
- Google Ads (`googleads`) via Data Manager API `ingestEvents`
- LinkedIn (`linkedinads`) via `/rest/conversionEvents`
- TikTok (`tiktokads`) via the Offline Events API `/offline/batch/` (OFFLINE conversions only)
- OpenAI Ads (`openaiads`) via its Conversions API (a separate host, `bzr.openai.com`)

`destinationId` semantics differ per platform:

- Meta: pixel (dataset) ID, e.g. `123456789012345`
- Google: conversion action resource name, e.g. `customers/1234567890/conversionActions/987654321`
- LinkedIn: conversion rule ID or URN, e.g. `104012` or `urn:lla:llaPartnerConversion:104012`
- TikTok: Offline Event Set ID, e.g. `7057103914977558530`
- OpenAI Ads: pixel wire id (numeric `pixel_id`, distinct from the internal pixel id), as returned by `GET /v1/accounts/{accountId}/conversion-destinations`

TikTok notes: this path sends OFFLINE conversions (in-store / CRM / call-center), not web-pixel
events. Each event must carry an email or phone (TikTok requires at least one). The connected
TikTok ads account must have granted the Offline Events permission; older grants must reconnect.

OpenAI Ads notes: requires a tracking tag (pixel) to already exist on the account. Returns 422
with code `TRACKING_TAG_REQUIRED` if `POST /v1/accounts/{accountId}/tracking-tags` hasn't been
called yet.

Callers can list valid destinations via `GET /v1/accounts/{accountId}/conversion-destinations`.

All PII (email, phone, names, external IDs) is hashed with SHA-256 server-side per each
platform's normalization spec, including Google's Gmail-specific dot/plus-suffix stripping.
Send plaintext. LinkedIn `externalIds` are passed through as plaintext per LinkedIn's spec;
only emails and phones are hashed.

For LinkedIn, the connected account must have been authorized after the Conversions API
rollout (i.e. the OAuth grant must include `rw_conversions`). Older accounts must reconnect.

Batching is handled automatically. Meta caps at 1000 events per request and rejects the
entire batch if any event is malformed. Google caps at 2000. LinkedIn caps at 5000 and is
also all-or-nothing per chunk. OpenAI Ads caps at 1000 per request; larger submissions are
split into 1000-event chunks, each all-or-nothing (a malformed event fails every event in
that chunk, not the whole request).

Dedup: pass a stable `eventId` on every event. Meta and LinkedIn use it to dedupe against
browser-side pixel/Insight Tag events; Google maps it to `transactionId`.

Per-platform `eventName` semantics:

- Meta: free-form. Standard names (Purchase, Lead, ...) match Meta's built-in events; custom strings are accepted.
- Google: ignored. The conversion action's category determines the event type. Send the standard name closest to your action for documentation, but the platform will not branch on it.
- LinkedIn: ignored. The conversion rule's `type` (LEAD, PURCHASE, etc.) is locked to the destination at rule-creation time. Send the standard name for documentation; LinkedIn does not branch on it.
- OpenAI Ads: a fixed subset of standard names (Purchase, Lead, AddToCart, ViewContent, InitiateCheckout, CompleteRegistration, Subscribe, StartTrial, Schedule) maps 1:1 onto OpenAI's own event-type enum; any other standard name or custom string is sent as `type: custom` with the name preserved.


## POST /v1/ads/conversions

**Send conversion events**

Relay one or more conversion events to the target ad platform's native Conversions API.
Platform is inferred from the provided `accountId`. Requires the Ads add-on.

Supported platforms:

- Meta (`metaads`) via Graph API
- Google Ads (`googleads`) via Data Manager API `ingestEvents`
- LinkedIn (`linkedinads`) via `/rest/conversionEvents`
- TikTok (`tiktokads`) via the Offline Events API `/offline/batch/` (OFFLINE conversions only)
- OpenAI Ads (`openaiads`) via its Conversions API (a separate host, `bzr.openai.com`)

`destinationId` semantics differ per platform:

- Meta: pixel (dataset) ID, e.g. `123456789012345`
- Google: conversion action resource name, e.g. `customers/1234567890/conversionActions/987654321`
- LinkedIn: conversion rule ID or URN, e.g. `104012` or `urn:lla:llaPartnerConversion:104012`
- TikTok: Offline Event Set ID, e.g. `7057103914977558530`
- OpenAI Ads: pixel wire id (numeric `pixel_id`, distinct from the internal pixel id), as returned by `GET /v1/accounts/{accountId}/conversion-destinations`

TikTok notes: this path sends OFFLINE conversions (in-store / CRM / call-center), not web-pixel
events. Each event must carry an email or phone (TikTok requires at least one). The connected
TikTok ads account must have granted the Offline Events permission; older grants must reconnect.

OpenAI Ads notes: requires a tracking tag (pixel) to already exist on the account. Returns 422
with code `TRACKING_TAG_REQUIRED` if `POST /v1/accounts/{accountId}/tracking-tags` hasn't been
called yet.

Callers can list valid destinations via `GET /v1/accounts/{accountId}/conversion-destinations`.

All PII (email, phone, names, external IDs) is hashed with SHA-256 server-side per each
platform's normalization spec, including Google's Gmail-specific dot/plus-suffix stripping.
Send plaintext. LinkedIn `externalIds` are passed through as plaintext per LinkedIn's spec;
only emails and phones are hashed.

For LinkedIn, the connected account must have been authorized after the Conversions API
rollout (i.e. the OAuth grant must include `rw_conversions`). Older accounts must reconnect.

Batching is handled automatically. Meta caps at 1000 events per request and rejects the
entire batch if any event is malformed. Google caps at 2000. LinkedIn caps at 5000 and is
also all-or-nothing per chunk. OpenAI Ads caps at 1000 per request; larger submissions are
split into 1000-event chunks, each all-or-nothing (a malformed event fails every event in
that chunk, not the whole request).

Dedup: pass a stable `eventId` on every event. Meta and LinkedIn use it to dedupe against
browser-side pixel/Insight Tag events; Google maps it to `transactionId`.

Per-platform `eventName` semantics:

- Meta: free-form. Standard names (Purchase, Lead, ...) match Meta's built-in events; custom strings are accepted.
- Google: ignored. The conversion action's category determines the event type. Send the standard name closest to your action for documentation, but the platform will not branch on it.
- LinkedIn: ignored. The conversion rule's `type` (LEAD, PURCHASE, etc.) is locked to the destination at rule-creation time. Send the standard name for documentation; LinkedIn does not branch on it.
- OpenAI Ads: a fixed subset of standard names (Purchase, Lead, AddToCart, ViewContent, InitiateCheckout, CompleteRegistration, Subscribe, StartTrial, Schedule) maps 1:1 onto OpenAI's own event-type enum; any other standard name or custom string is sent as `type: custom` with the name preserved.


### Request Body

- **accountId** (required) `string`: SocialAccount ID (metaads, googleads, linkedinads, tiktokads, or openaiads).
- **destinationId** (required) `string`: Platform destination identifier. For Meta, the pixel/dataset
ID. For Google, the conversion action resource name. For
LinkedIn, the conversion rule ID or full
`urn:lla:llaPartnerConversion:{id}` URN. For OpenAI Ads, the
pixel wire id.

- **events** (required) `array`: No description
- **testCode** `string`: Meta `test_event_code` passthrough. Ignored by Google, LinkedIn, and OpenAI Ads.
- **consent** `object`: Batch-level user consent. Required by Google for EEA/UK
events under the Feb 2026 restrictions. On Meta, any
DENIED flag enables Limited Data Use on every event in
the batch (data_processing_options ["LDU"] with
geolocation, country 0 / state 0); GRANTED or absent
consent sends events with Meta's default processing.
Ignored by LinkedIn.


### Responses

#### 200: Events processed. Inspect `eventsFailed` and `failures[]` to detect
partial failure. For Meta, a batch is all-or-nothing (either every
event in a chunk succeeds, or every event in the chunk is listed
in failures). For Google, the API returns success/failure at the
request level only. For OpenAI Ads, each 1000-event chunk is
all-or-nothing, same as Meta.


**Response Body:**

- **platform** `string`: No description - one of: metaads, googleads, linkedinads, tiktokads, openaiads
- **eventsReceived** `integer`: Events accepted by the platform.
- **eventsFailed** `integer`: Events rejected (see failures).
- **failures** `array[object]`: 
  - **eventIndex** `integer`: Index into the submitted events array.
  - **eventId** `string`: Echoes back the eventId of the failed event.
  - **message** `string`: No description
  - **code**: One of multiple types
- **traceId** `string`: Platform trace ID for debugging. fbtrace_id for Meta,
requestId for Google. Absent for LinkedIn (LinkedIn's
conversionEvents endpoint does not surface a trace ID)
and OpenAI Ads (no trace ID surfaced).


#### 400: Invalid body (missing accountId/destinationId/events, malformed event shape).

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required (Ads add-on on legacy plans, included on usage-based plans),
OR (for LinkedIn) the connected account lacks the `rw_conversions` scope and must be reconnected.


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

#### 422: OpenAI Ads only: no tracking tag (pixel) exists yet for this account. Code `TRACKING_TAG_REQUIRED`; create one via `POST /v1/accounts/{accountId}/tracking-tags` first.

#### 429: LinkedIn token-level rate limit hit (600 requests/min, 300k/day
per token). Retry with backoff. Meta and Google have their own
rate-limit semantics surfaced via platform-specific 4xx responses.


---
