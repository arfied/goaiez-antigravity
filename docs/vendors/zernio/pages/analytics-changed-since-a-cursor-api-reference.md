# Analytics changed since a cursor API Reference

Cursor feed of the analytics snapshots that CHANGED, across every account you can
read, in one paginated stream. Built for integrations that would otherwise call
`GET /v1/analytics` once per connected account. Each page carries changes from
many accounts at once, so your call count scales with how much actually changed
rather than with how many accounts you have. Measured against a fleet of roughly
1,600 connected accounts: about 1,599 per-account analytics calls an hour became
about 205 delta calls an hour, a 7.8x reduction.

**Bootstrap once, then stay in sync.** Take the cursor FIRST: call this endpoint
with NO `cursor` and it answers with an empty `data` array plus the feed's current
position in `nextCursor`. Then load your baseline from `GET /v1/analytics`, the
historical endpoint, because this one is a rolling 7-day change log and cannot
replay history. Then resume from the cursor you took before the baseline. Taking
the cursor afterwards instead drops every change that lands while the baseline is
loading: it is in neither the row you already read nor the feed you resume behind
it. The overlap this order creates is safe, because metrics are absolute values
rather than increments, so draining it leaves every post on its newest value.
`nextCursor` is present on every response, empty pages included, so you always
have something to advance with.

**Ordering.** Entries come back oldest first, in the order the feed received
them. That order is NOT `syncedAt`: `syncedAt` is stamped when an account's sync
cycle started, and a slow cycle writes its rows after a faster cycle that started
later, so `syncedAt` can go backwards between consecutive entries. Do not sort,
filter or resume on it. The cursor is the only stable position, and it is opaque:
pass it back verbatim, and do not parse, construct or compare cursors.

**`hasMore: false` does not mean the feed ended.** This stream has no end and
`nextCursor` is never null. `hasMore: true` means more changes are already
waiting, so call again straight away. `hasMore: false` means you are caught up:
keep the cursor and poll again on your normal interval.

**The newest changes settle before they are served.** The feed deliberately holds
back its last few seconds of writes, so that a row can never become visible
behind a cursor you have already advanced past. A read issued the instant an
`analytics.synced` webhook lands will therefore often return an empty page for
that account. Do not read an empty page as "nothing changed": poll again with the
SAME cursor you last used rather than advancing.

**Repeats inside one instant.** A sync cycle occasionally records the same post
twice at the same feed position. When that happens the feed delivers one of those
rows, not both. Measured over a day of production traffic, about 1.3% of rows
fall in such a group and 99.4% of those groups are identical rows, so this is far
more often deduplication than loss. Metrics are absolute values rather than
increments, so a later entry for the same post supersedes an earlier one.

**Retention is 7 days.** Changes older than that leave the feed. A cursor older
than 6 days is rejected with a `400` (a day of margin, because expiry is lazy).
Recover the same way you bootstrapped: take a fresh cursor from a call to this
endpoint with no `cursor`, then re-load from `GET /v1/analytics`, then resume
from that cursor. A consumer that polls at least daily never reaches this.

Pairs with the `analytics.synced` webhook, so changes can be read on notification
instead of on a timer. That event carries no cursor of its own: keep using the
`nextCursor` this endpoint gave you.

Requires the same analytics access as `GET /v1/analytics`, and shares the
stricter per-second rate-limit window applied to analytics endpoints.


## GET /v1/analytics/delta

**Analytics changed since a cursor**

Cursor feed of the analytics snapshots that CHANGED, across every account you can
read, in one paginated stream. Built for integrations that would otherwise call
`GET /v1/analytics` once per connected account. Each page carries changes from
many accounts at once, so your call count scales with how much actually changed
rather than with how many accounts you have. Measured against a fleet of roughly
1,600 connected accounts: about 1,599 per-account analytics calls an hour became
about 205 delta calls an hour, a 7.8x reduction.

**Bootstrap once, then stay in sync.** Take the cursor FIRST: call this endpoint
with NO `cursor` and it answers with an empty `data` array plus the feed's current
position in `nextCursor`. Then load your baseline from `GET /v1/analytics`, the
historical endpoint, because this one is a rolling 7-day change log and cannot
replay history. Then resume from the cursor you took before the baseline. Taking
the cursor afterwards instead drops every change that lands while the baseline is
loading: it is in neither the row you already read nor the feed you resume behind
it. The overlap this order creates is safe, because metrics are absolute values
rather than increments, so draining it leaves every post on its newest value.
`nextCursor` is present on every response, empty pages included, so you always
have something to advance with.

**Ordering.** Entries come back oldest first, in the order the feed received
them. That order is NOT `syncedAt`: `syncedAt` is stamped when an account's sync
cycle started, and a slow cycle writes its rows after a faster cycle that started
later, so `syncedAt` can go backwards between consecutive entries. Do not sort,
filter or resume on it. The cursor is the only stable position, and it is opaque:
pass it back verbatim, and do not parse, construct or compare cursors.

**`hasMore: false` does not mean the feed ended.** This stream has no end and
`nextCursor` is never null. `hasMore: true` means more changes are already
waiting, so call again straight away. `hasMore: false` means you are caught up:
keep the cursor and poll again on your normal interval.

**The newest changes settle before they are served.** The feed deliberately holds
back its last few seconds of writes, so that a row can never become visible
behind a cursor you have already advanced past. A read issued the instant an
`analytics.synced` webhook lands will therefore often return an empty page for
that account. Do not read an empty page as "nothing changed": poll again with the
SAME cursor you last used rather than advancing.

**Repeats inside one instant.** A sync cycle occasionally records the same post
twice at the same feed position. When that happens the feed delivers one of those
rows, not both. Measured over a day of production traffic, about 1.3% of rows
fall in such a group and 99.4% of those groups are identical rows, so this is far
more often deduplication than loss. Metrics are absolute values rather than
increments, so a later entry for the same post supersedes an earlier one.

**Retention is 7 days.** Changes older than that leave the feed. A cursor older
than 6 days is rejected with a `400` (a day of margin, because expiry is lazy).
Recover the same way you bootstrapped: take a fresh cursor from a call to this
endpoint with no `cursor`, then re-load from `GET /v1/analytics`, then resume
from that cursor. A consumer that polls at least daily never reaches this.

Pairs with the `analytics.synced` webhook, so changes can be read on notification
instead of on a timer. That event carries no cursor of its own: keep using the
`nextCursor` this endpoint gave you.

Requires the same analytics access as `GET /v1/analytics`, and shares the
stricter per-second rate-limit window applied to analytics endpoints.


### Parameters

- **cursor** (optional) in query: Opaque cursor from a previous response's `nextCursor`. Omit it to start from
now: the response is then an empty page carrying the feed's current position.
Rejected with a `400` when malformed, or when older than the retention window.

- **limit** (optional) in query: Page size. Out-of-range values are a 400, never a silent clamp.
- **platform** (optional) in query: Filter to a single platform (for example "youtube"). Omit for every platform.
- **profileId** (optional) in query: Filter by profile ID (default "all"). Must be a valid profile ID or "all".

### Responses

#### 200: One page of changed analytics

**Response Body:**

- **data** (required) `array[AnalyticsDeltaEntry]`: Changed snapshots, oldest first, in the order the feed received them. Empty on
the bootstrap call (no `cursor` supplied) and whenever nothing has changed
since your cursor.

- **nextCursor** (required) `string`: Cursor to send on the next call. ALWAYS present, including on an empty page,
so you always have something to advance with, and it never moves backwards.
Opaque: pass it back verbatim, and do not parse, construct or compare cursors.
 (example: "v1.WyIyMDI2LTA5LTAxIDE3OjEyOjA0IiwiNjVmMWMwYTllMmI1YWYwMDEyYWIzNGNkIl0")
- **hasMore** (required) `boolean`: True when more changes are already waiting past `nextCursor`, so call again
immediately. False means you are caught up: keep `nextCursor` and poll again
later. This feed never ends, so `hasMore: false` does NOT mean `nextCursor`
is null.


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

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

**Response Body:**

- **error** `string`: No description (example: "Analytics add-on required")
- **code** `string`: No description (example: "analytics_addon_required")

#### 403: The caller cannot read the requested profile, the API key is scoped to other
profiles, or the profile sits outside the plan's profile limit (`code`
`PROFILE_OVER_LIMIT`).


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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 500: Internal server error

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

#### 503: The delta feed is temporarily unavailable, for example in the minutes between
an application release and the analytics store catching up with it. Nothing is
wrong with the request: retry it unchanged, honouring `Retry-After`. The feed
answers this rather than an empty page, because an empty page is
indistinguishable from "nothing changed" and would advance you past a window
you never received.


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
