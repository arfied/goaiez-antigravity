# Reply to comment API Reference

Post a reply to a post or specific comment. Requires accountId in request body.

**Idempotency:** send an `Idempotency-Key` header to make retries safe
(e.g. after a client-side timeout where delivery is unknown): same key +
same body replays the original response (with `Idempotent-Replayed: true`)
instead of posting the comment a second time; same key + different body
returns 422; a key still in flight returns 409. Keys are retained for 24
hours and are scoped to the credential and to this exact path, so reusing
a key against a different postId returns 422 rather than replaying the
other post's response.

Only successful (2xx) responses are stored for replay. If the request
throws or returns a non-2xx status the key is released, so the header
protects the "request succeeded but the response was lost" case. After an
ambiguous failure (a 5xx or a network timeout) list the post's comments
before retrying with the same key, and treat an empty result as
inconclusive rather than as proof nothing was posted.


## GET /v1/inbox/comments/{postId}

**Get post comments**

Fetch comments for a specific post. Requires accountId query parameter.

On Facebook and Instagram, passing a COMMENT id as `postId` is also supported and
returns that comment's replies instead of the post's top-level comments. This is not
available on YouTube, where `postId` must be a video id.

Responses are cached for up to 10 minutes, so a page may lag new comments by that
window. Do not poll this endpoint for real-time updates: subscribe to the
`comment.received` webhook, which delivers new comments as they arrive. Your own
writes (creating, replying to, or deleting a comment) refresh the cache immediately.

TikTok is served for accounts connected through the TikTok for Business app: `postId`
is the TikTok video id, each top-level comment carries up to three inline replies, and
`commentId` pages the full reply list of one comment. Developer-app TikTok accounts
return 400 with code `PLATFORM_LIMITATION`.


### Parameters

- **postId** (required) in path: Zernio post ID or platform-specific post ID. Zernio IDs are auto-resolved. LinkedIn third-party posts accept full activity URN or numeric ID. On Facebook and Instagram, a comment ID is also accepted here and returns that comment's replies.
- **accountId** (required) in query: No description
- **subreddit** (optional) in query: (Reddit only) Subreddit name
- **limit** (optional) in query: Maximum number of comments to return
- **cursor** (optional) in query: Pagination cursor, returned by a previous call as `pagination.cursor`. This is the platform's own opaque paging value passed through verbatim: never construct, decode or validate it client-side.
- **commentId** (optional) in query: (Reddit and TikTok only) Get replies to a specific comment

### Responses

#### 200: Comments for the post

**Response Body:**

- **status** `string`: No description
- **comments** `array[object]`: 
  - **id** `string`: No description
  - **message** `string`: No description
  - **createdTime** `string` (date-time): No description
  - **from** `object`: 
    - **id** `string`: No description
    - **name** `string`: No description
    - **username** `string`: No description
    - **picture** `string,null`: No description
    - **isOwner** `boolean`: No description
    - **verifiedType** `string,null`: X verified badge type. Only present for X comments. - one of: blue, government, business, none
  - **likeCount** `integer`: No description
  - **replyCount** `integer`: The platform's own reply count, which includes hidden and deleted replies. Can exceed replies[].length even when repliesHasMore is false or absent.
  - **platform** `string`: The platform this comment is from
  - **url** `string,null`: Direct link to the comment on the platform (if available)
  - **replies** `array[object]`: 
    Type: `object`
  - **repliesHasMore** `boolean`: Facebook only. True when replies[] (capped at 10) does not hold the comment's full reply thread; fetch the rest by passing the comment id as postId to GET /v1/inbox/comments/{postId}. Absent (not false) on every other platform, including Instagram, which has no equivalent signal.
  - **canReply** `boolean`: No description
  - **canDelete** `boolean`: No description
  - **canHide** `boolean`: Whether this comment can be hidden (Facebook, Instagram, Threads)
  - **canLike** `boolean`: Whether this comment can be liked (Facebook, X, Bluesky, Reddit, LinkedIn)
  - **isHidden** `boolean`: Whether the comment is currently hidden
  - **isLiked** `boolean`: Whether the current user has liked this comment
  - **likeUri** `string,null`: Bluesky like URI for unliking
  - **cid** `string,null`: Bluesky content identifier
  - **parentId** `string,null`: ID of the parent comment. Present on entries inside replies[] for Facebook, Instagram and X. On X it is also present on top-level entries, where it holds the ID of the post replied to. Omitted entirely (key absent, not null) on top-level Facebook and Instagram entries and on every other platform, which express the parent relationship only through replies[] nesting.
  - **rootUri** `string,null`: Bluesky root post URI
  - **rootCid** `string,null`: Bluesky root post CID
- **post** `object,null`: (Reddit only) Metadata for the target post, returned alongside the comments in Reddit's
single round-trip. Lets integrators render a preview of the post the user is commenting on
without an additional request. Absent for non-Reddit platforms and when the upstream
response is missing the post listing (deleted post, malformed response).

- **pagination** `object`: 
  - **hasMore** `boolean`: No description
  - **cursor** `string,null`: Only present when hasMore is true. Absent on the last page, so treat its absence as the end of the thread.
- **meta** `object`: 
  - **platform** `string`: No description
  - **postId** `string`: No description
  - **accountId** `string`: No description
  - **subreddit** `string,null`: (Reddit only) Subreddit name
  - **lastUpdated** `string` (date-time): No description
  - **adComments** `object,null`: (Facebook/Instagram only) Present when this post has no organic comments but is a boosted post: the engagement lives on the ad. Use the ad-comments endpoint instead.

#### 400: Invalid request, or the postId belongs to a Meta ad creative / ad ID rather than an organic post
(code USE_AD_COMMENTS_ENDPOINT; the response includes `adId` and `adCommentsUrl`), or the upstream
platform rejected the request (type platform_error, code platform_api_error; the provider's own
payload is in platformError). Meta returns code 100 with error_subcode 33 both for a story past
its 24h life and for a deleted post, so the two are indistinguishable from the response.


#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required, or the connected account is not permitted to read this post on the platform (code platform_api_error, type platform_error)

#### 429: The connected account's upstream platform quota is exhausted.

Reddit rate-limits per connected Reddit user (1000 requests per
10-minute window), and that budget is shared by every operation using
that account. Retry after the window resets rather than retrying
immediately; repeated calls while exhausted do not succeed and keep the
budget spent.


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

#### 502: Upstream platform error (code platform_api_error, type platform_error)

---

## POST /v1/inbox/comments/{postId}

**Reply to comment**

Post a reply to a post or specific comment. Requires accountId in request body.

**Idempotency:** send an `Idempotency-Key` header to make retries safe
(e.g. after a client-side timeout where delivery is unknown): same key +
same body replays the original response (with `Idempotent-Replayed: true`)
instead of posting the comment a second time; same key + different body
returns 422; a key still in flight returns 409. Keys are retained for 24
hours and are scoped to the credential and to this exact path, so reusing
a key against a different postId returns 422 rather than replaying the
other post's response.

Only successful (2xx) responses are stored for replay. If the request
throws or returns a non-2xx status the key is released, so the header
protects the "request succeeded but the response was lost" case. After an
ambiguous failure (a 5xx or a network timeout) list the post's comments
before retrying with the same key, and treat an empty result as
inconclusive rather than as proof nothing was posted.


### Parameters

- **postId** (required) in path: Zernio post ID or platform-specific post ID. LinkedIn third-party posts accept full activity URN or numeric ID.
- **undefined** (optional): No description

### Request Body

- **accountId** (required) `string`: No description
- **message** (required) `string`: No description
- **attachmentUrl** `string`: (Facebook only) URL of an image to attach, publishing a photo comment alongside the text. The URL must be publicly accessible so Meta can fetch it. Returns 400 for other platforms.
- **commentId** `string`: Reply to specific comment (optional)
- **parentCid** `string`: (Bluesky only) Parent content identifier
- **rootUri** `string`: (Bluesky only) Root post URI
- **rootCid** `string`: (Bluesky only) Root post CID

### Responses

#### 200: Reply posted

**Response Body:**

- **success** `boolean`: No description
- **data** `object`: 
  - **commentId** `string`: No description
  - **isReply** `boolean`: No description
  - **cid** `string,null`: Bluesky CID

#### 400: Invalid request (e.g. attachmentUrl on a platform other than Facebook, code PLATFORM_NOT_SUPPORTED)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required, or the connected account is not permitted to comment on this post on the platform (code platform_api_error, type platform_error)

#### 409: Same Idempotency-Key still processing; retry after a short backoff

#### 422: Idempotency-Key reused with a different request

#### 429: The connected account's upstream platform quota is exhausted.

Reddit rate-limits per connected Reddit user (1000 requests per
10-minute window), and that budget is shared by every operation using
that account. Retry after the window resets rather than retrying
immediately; repeated calls while exhausted do not succeed and keep the
budget spent.


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

#### 502: Upstream platform error (code platform_api_error, type platform_error)

---

## DELETE /v1/inbox/comments/{postId}

**Delete comment**

Delete a comment on a post. Supported by Facebook, Instagram, Bluesky, Reddit, YouTube, and LinkedIn.
Requires accountId and commentId query parameters.


### Parameters

- **postId** (required) in path: Zernio post ID or platform-specific post ID. LinkedIn third-party posts accept full activity URN or numeric ID.
- **accountId** (required) in query: No description
- **commentId** (required) in query: For LinkedIn, accepts either the numeric comment ID or the composite comment URN returned by the comments listing (e.g. urn:li:comment:(threadUrn,id))

### Responses

#### 200: Comment deleted

**Response Body:**

- **success** `boolean`: No description
- **data** `object`: 
  - **message** `string`: No description

#### 400: Platform rejected the operation (e.g., comment already deleted)

**Response Body:**

- **error** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required, or the connected account is not permitted to delete this comment on the platform (code platform_api_error, type platform_error)

#### 429: The connected account's upstream platform quota is exhausted.

Reddit rate-limits per connected Reddit user (1000 requests per
10-minute window), and that budget is shared by every operation using
that account. Retry after the window resets rather than retrying
immediately; repeated calls while exhausted do not succeed and keep the
budget spent.


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

#### 502: Upstream platform error (code platform_api_error, type platform_error)

---
