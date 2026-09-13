# Like post API Reference

Like (or react to) a post as a connected account. Supported platforms: LinkedIn,
X, Facebook, YouTube, Bluesky, and Instagram in limited release (see below).
Threads, TikTok and Pinterest
expose no like endpoint in their APIs and return 400. Reddit returns 400 too,
pointing at `POST /v1/accounts/{accountId}/reddit-vote`, which covers upvote,
downvote and clear on both posts and comments.

The account does not have to be the one that published the post, which is what
makes executive engagement possible: pass an exec's `accountId` and the brand
post's ID. `postId` accepts either a Zernio post ID or the platform's native post
ID. A Zernio post ID resolves to the entry for `accountId`, falling back to the
post's single entry on the same platform (two entries on that platform is a 400,
so pass the native ID).

LinkedIn requires the `w_member_social_feed` / `w_organization_social_feed`
scopes, which are not retroactive: accounts connected before those were requested
get a 403 with code `linkedin_reconnect_required` until the user reconnects the
account. YouTube spends 50 quota units per call.

Instagram is in LIMITED RELEASE and not generally available: the call needs
`instagram_manage_engagement`, which Meta has so far granted this app only under
Standard Access, so it works for app admins, developers and testers of our Meta app
and returns a 403 with code `PLATFORM_BETA_RESTRICTED` for every other account.
That restriction lifts when Meta App Review grants Advanced Access; the constraints
below apply once it does.

Instagram covers feed images, reels and carousels (stories and private-account
media are not likeable). Only an account connected through Facebook Login can be
granted `instagram_manage_engagement`: an Instagram Login
connection returns a 400 with code `instagram_likes_require_facebook_login`, and an
account whose token predates the permission returns a 403 with code
`reconnect_required`. Instagram also enforces a burst limit of 50 like or unlike
calls per 5 seconds per Instagram account, and exceeding it locks that account out
of the like API for an hour, so pace bulk loops.


## POST /v1/inbox/posts/{postId}/like

**Like post**

Like (or react to) a post as a connected account. Supported platforms: LinkedIn,
X, Facebook, YouTube, Bluesky, and Instagram in limited release (see below).
Threads, TikTok and Pinterest
expose no like endpoint in their APIs and return 400. Reddit returns 400 too,
pointing at `POST /v1/accounts/{accountId}/reddit-vote`, which covers upvote,
downvote and clear on both posts and comments.

The account does not have to be the one that published the post, which is what
makes executive engagement possible: pass an exec's `accountId` and the brand
post's ID. `postId` accepts either a Zernio post ID or the platform's native post
ID. A Zernio post ID resolves to the entry for `accountId`, falling back to the
post's single entry on the same platform (two entries on that platform is a 400,
so pass the native ID).

LinkedIn requires the `w_member_social_feed` / `w_organization_social_feed`
scopes, which are not retroactive: accounts connected before those were requested
get a 403 with code `linkedin_reconnect_required` until the user reconnects the
account. YouTube spends 50 quota units per call.

Instagram is in LIMITED RELEASE and not generally available: the call needs
`instagram_manage_engagement`, which Meta has so far granted this app only under
Standard Access, so it works for app admins, developers and testers of our Meta app
and returns a 403 with code `PLATFORM_BETA_RESTRICTED` for every other account.
That restriction lifts when Meta App Review grants Advanced Access; the constraints
below apply once it does.

Instagram covers feed images, reels and carousels (stories and private-account
media are not likeable). Only an account connected through Facebook Login can be
granted `instagram_manage_engagement`: an Instagram Login
connection returns a 400 with code `instagram_likes_require_facebook_login`, and an
account whose token predates the permission returns a 403 with code
`reconnect_required`. Instagram also enforces a burst limit of 50 like or unlike
calls per 5 seconds per Instagram account, and exceeding it locks that account out
of the like API for an hour, so pace bulk loops.


### Parameters

- **postId** (required) in path: Zernio post ID or the platform's native post ID

### Request Body

- **accountId** (required) `string`: The account acting as the liker
- **reactionType** `string`: (LinkedIn only) Reaction to create. Defaults to LIKE; ignored on other platforms. - one of: LIKE, PRAISE, EMPATHY, INTEREST, APPRECIATION, ENTERTAINMENT
- **cid** `string`: (Bluesky only) Content identifier of the post

### Responses

#### 200: Post liked

**Response Body:**

- **status** `string`: No description
- **postId** `string`: The resolved native post ID
- **platform** `string`: No description
- **liked** `boolean`: No description
- **likeUri** `string`: (Bluesky only) URI to use for unliking
- **alreadyReacted** `boolean`: LinkedIn only: the account already had this exact reaction, so nothing was created
- **reactionType** `string`: LinkedIn only: the reaction type now in effect

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

#### 403: Inbox addon required, or the account is missing the platform scope

#### 404: Account or post not found

#### 409: LinkedIn only: the account already holds a different reaction on this target (code invalid_resource_state); remove it before creating another.

---

## DELETE /v1/inbox/posts/{postId}/like

**Unlike post**

Remove this account's like from a post. Supported platforms: LinkedIn, X,
Facebook, YouTube, Bluesky, and Instagram in limited release. On YouTube this clears
the rating. Instagram has the same limited release, Facebook Login,
`instagram_manage_engagement` and burst-limit constraints as liking. For Bluesky,
`likeUri` (returned when the post was liked) is required. Reddit uses
`POST /v1/accounts/{accountId}/reddit-vote` with `direction: 0`.


### Parameters

- **postId** (required) in path: Zernio post ID or the platform's native post ID
- **accountId** (required) in query: No description
- **likeUri** (optional) in query: (Bluesky only) The like URI returned when liking

### Responses

#### 200: Post unliked

**Response Body:**

- **status** `string`: No description
- **postId** `string`: The resolved native post ID
- **platform** `string`: No description
- **liked** `boolean`: No description

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

#### 403: Inbox addon required, or the account is missing the platform scope

#### 404: Account or post not found

---
