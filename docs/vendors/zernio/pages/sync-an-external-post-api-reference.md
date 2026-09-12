# Sync an external post API Reference

Fetch an account's latest external posts (published directly on the platform, not through Zernio) on demand, so a newly published post is retrievable within seconds instead of waiting for the background sync (which refreshes each account at most every ~90 minutes).

Primary use case: verifying a submitted post. When a user publishes on the platform and immediately pastes the post URL into your app, call this with `accountId` plus `url` (or `postId`) to confirm the post exists and return its metadata.

Behavior:
- Account access and connection state are checked before any platform call, including requests inside the debounce window.
- Inactive accounts or accounts marked `needsReconnection` return `409` with code `ads_connection_required`. Stop scheduled retries for that account until it is reconnected, then read `GET /v1/accounts` for its current account ID.
- For connected accounts, we fetch the latest posts live from the platform, then match and return the submitted post.
- Requests are debounced per account (~15s): if the account was synced inside that window, the live fetch is skipped.

`accountId` is required, because a post URL or id alone cannot be resolved to an account, and the account must be connected to Zernio (we use its token to read the platform). Supported for every platform with a listing API (Instagram, Facebook, TikTok, YouTube, X, Threads, Pinterest, Reddit, Bluesky, Google Business Profile, and LinkedIn organization accounts).

LinkedIn personal profiles: LinkedIn has no listing API for personal profiles, so a `url` is REQUIRED and imports that single post. Pass any LinkedIn post URL (`linkedin.com/posts/…`, `linkedin.com/feed/update/urn:li:activity:…`) or a `urn:li:share:…` / `urn:li:ugcPost:…` URN. Works for posts published outside Zernio and before the account was connected, any age; the post must be authored by the connected member. Imported posts return full analytics (impressions, reach, reactions, comments, reshares, saves) and keep refreshing on the background analytics cycle, but carry no content/media (LinkedIn does not expose them for personal profiles).

`url` accepts any format the platform uses (e.g. `instagram.com/p/…`, `instagram.com/reel/…`, `youtu.be/…`, `youtube.com/shorts/…`, `tiktok.com/@user/video/…`, `vm.tiktok.com` short links, `pinterest.com/pin/…` on any regional domain, and `pin.it` short links). Pass `postId` (the platform media/video/pin id) as an alternative locator.

Note: post-level analytics (reach, impressions) still carry the platform's own delay (e.g. ~24h on Instagram). This endpoint confirms the post exists and returns its metadata plus basic engagement (likes, comments), not delayed insights.


## POST /v1/posts/sync-external

**Sync an external post**

Fetch an account's latest external posts (published directly on the platform, not through Zernio) on demand, so a newly published post is retrievable within seconds instead of waiting for the background sync (which refreshes each account at most every ~90 minutes).

Primary use case: verifying a submitted post. When a user publishes on the platform and immediately pastes the post URL into your app, call this with `accountId` plus `url` (or `postId`) to confirm the post exists and return its metadata.

Behavior:
- Account access and connection state are checked before any platform call, including requests inside the debounce window.
- Inactive accounts or accounts marked `needsReconnection` return `409` with code `ads_connection_required`. Stop scheduled retries for that account until it is reconnected, then read `GET /v1/accounts` for its current account ID.
- For connected accounts, we fetch the latest posts live from the platform, then match and return the submitted post.
- Requests are debounced per account (~15s): if the account was synced inside that window, the live fetch is skipped.

`accountId` is required, because a post URL or id alone cannot be resolved to an account, and the account must be connected to Zernio (we use its token to read the platform). Supported for every platform with a listing API (Instagram, Facebook, TikTok, YouTube, X, Threads, Pinterest, Reddit, Bluesky, Google Business Profile, and LinkedIn organization accounts).

LinkedIn personal profiles: LinkedIn has no listing API for personal profiles, so a `url` is REQUIRED and imports that single post. Pass any LinkedIn post URL (`linkedin.com/posts/…`, `linkedin.com/feed/update/urn:li:activity:…`) or a `urn:li:share:…` / `urn:li:ugcPost:…` URN. Works for posts published outside Zernio and before the account was connected, any age; the post must be authored by the connected member. Imported posts return full analytics (impressions, reach, reactions, comments, reshares, saves) and keep refreshing on the background analytics cycle, but carry no content/media (LinkedIn does not expose them for personal profiles).

`url` accepts any format the platform uses (e.g. `instagram.com/p/…`, `instagram.com/reel/…`, `youtu.be/…`, `youtube.com/shorts/…`, `tiktok.com/@user/video/…`, `vm.tiktok.com` short links, `pinterest.com/pin/…` on any regional domain, and `pin.it` short links). Pass `postId` (the platform media/video/pin id) as an alternative locator.

Note: post-level analytics (reach, impressions) still carry the platform's own delay (e.g. ~24h on Instagram). This endpoint confirms the post exists and returns its metadata plus basic engagement (likes, comments), not delayed insights.


### Request Body

- **accountId** (required) `string`: SocialAccount ID whose posts to sync. Must be connected to Zernio.
- **url** `string`: The post URL to locate. Optional. Provide `url` or `postId` to return a specific post; omit both to refresh and return the account's recent posts.
- **postId** `string`: The platform post/media/video id to locate, as an alternative to `url`. Optional.

### Responses

#### 200: Sync result. When `url` or `postId` is provided, returns the matched post (or `found: false`). When neither is provided, returns the account's freshly-synced recent posts.


**Response Body:**

- **synced** `object`: 
  - **postsFound** `integer`: Posts returned by the platform listing during the on-demand sync
  - **postsSynced** `integer`: Posts inserted or updated in Zernio
  - **skipped** `boolean`: True when the account was synced within the debounce window and no live fetch ran.
- **found** `boolean`: Present only when a locator (`url`/`postId`) was provided: whether the post was found.
- **post**: The matched external post, or null when not found. Present only when a locator was provided.
  - `ExternalPostSummary`
- **posts** `array[ExternalPostSummary]`: The account's recent external posts. Present only when no locator was provided.

#### 400: Invalid request (e.g. `accountId` missing or malformed)

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

#### 401: The platform authorization has expired. Reconnect the account.

#### 403: The API key lacks access to this account or the platform denied access.

#### 404: Account not found (or not owned by the authenticated user)

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

#### 409: The account is inactive or needs reconnection. Stop scheduled retries until it is reconnected.

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

#### 502: The platform returned a server error.

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

#### 503: An upstream service or database is temporarily unavailable. Retry after the indicated delay. A timed-out write may have completed upstream; check its outcome before resubmitting.

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

---
