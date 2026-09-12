# Related Schema Definitions

## AnalyticsDeltaResponse

### Properties

- **data** (required) `array`: Changed snapshots, oldest first, in the order the feed received them. Empty on
the bootstrap call (no `cursor` supplied) and whenever nothing has changed
since your cursor.

- **nextCursor** (required) `string`: Cursor to send on the next call. ALWAYS present, including on an empty page,
so you always have something to advance with, and it never moves backwards.
Opaque: pass it back verbatim, and do not parse, construct or compare cursors.

- **hasMore** (required) `boolean`: True when more changes are already waiting past `nextCursor`, so call again
immediately. False means you are caught up: keep `nextCursor` and poll again
later. This feed never ends, so `hasMore: false` does NOT mean `nextCursor`
is null.


## AnalyticsDeltaEntry

One changed analytics snapshot. Metrics are the absolute values recorded at
`syncedAt`, not the amount they moved by since the previous snapshot, so a later
entry for the same `postId` always supersedes an earlier one.


### Properties

- **postId** (required) `string`: External post ID. The same identifier as `posts[]._id` in GET /v1/analytics.
- **accountId** (required) `string`: Account this post was published through
- **profileId** (required) `string`: Profile the account belongs to
- **platform** (required) `string`: No description
- **platformPostId** (required) `string`: Platform-side post ID (for example the YouTube video ID)
- **publishedAt** (required) `string`: When the post was published, ISO-8601 UTC
- **syncedAt** (required) `string`: When the sync cycle that produced this snapshot STARTED, ISO-8601 UTC. This
is NOT the order entries arrive in and it is not a resume point: a slow cycle
writes its rows after a faster cycle that started later, so `syncedAt` can go
backwards between consecutive entries. Use `nextCursor` to resume.

- **isDeleted** (required) `boolean`: True when the post was detected as deleted on the platform at this sync
- **metrics** (required) `object`: Metrics a platform does not report are 0, not absent.
  - **impressions** `integer`: 
  - **reach** `integer`: 
  - **likes** `integer`: 
  - **comments** `integer`: 
  - **shares** `integer`: 
  - **saves** `integer`: 
  - **sends** `integer`: 
  - **clicks** `integer`: 
  - **views** `integer`: 
  - **follows** `integer`: Follows attributed to this post (Instagram)
  - **igReelsAvgWatchTime** `integer`: Instagram Reels average watch time, in milliseconds
  - **igReelsVideoViewTotalTime** `integer`: Instagram Reels total watch time, in milliseconds
  - **reposts** `integer`: 
  - **reelsSkipRate** `number`: Instagram Reels skip rate, 0 to 1
  - **completionRate** `number`: TikTok business lane: share of viewers who watched to the end, 0 to 1
  - **profileViews** `integer`: TikTok business lane: profile views attributed to the post

## ErrorResponse

Canonical error envelope. `error` is the human-readable message; `type`,
`code`, `param`, `platform`, and `platformError` are top-level siblings
for programmatic handling. For upstream platform failures (`type:
platform_error`), `platformError` carries the provider's raw payload
verbatim (for Meta: `error_subcode`, `error_user_title`, `error_user_msg`).


### Properties

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
