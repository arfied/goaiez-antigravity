# Like comment API Reference

Like or upvote a comment on a post. Supported platforms: Facebook, X,
Bluesky, Reddit, LinkedIn, and Instagram in limited release (see below). For
Bluesky, the cid (content identifier) is
required in the request body. For LinkedIn, pass the composite comment URN returned
by the comments endpoints as commentId; an optional reactionType picks the reaction
(defaults to LIKE), and accounts connected before the social-feed scopes were
requested get a 403 with code `linkedin_reconnect_required`.

Instagram is in LIMITED RELEASE and not generally available: the call needs
`instagram_manage_engagement`, which Meta has so far granted this app only under
Standard Access, so it works for app admins, developers and testers of our Meta app
and returns a 403 with code `PLATFORM_BETA_RESTRICTED` for every other account.
That restriction lifts when Meta App Review grants Advanced Access; the constraints
below apply once it does.

Instagram covers comments and replies on feed posts, reels and carousels. Only an
account connected through Facebook Login can be granted
`instagram_manage_engagement`: an Instagram Login connection returns a 400 with
code `instagram_likes_require_facebook_login`, and an account whose token predates
the permission returns a 403 with code `reconnect_required`. Content from private
accounts cannot be liked. Instagram also enforces a burst limit of 50 like or
unlike calls per 5 seconds per Instagram account, and exceeding it locks that
account out of the like API for an hour, so pace bulk loops.


## POST /v1/inbox/comments/{postId}/{commentId}/like

**Like comment**

Like or upvote a comment on a post. Supported platforms: Facebook, X,
Bluesky, Reddit, LinkedIn, and Instagram in limited release (see below). For
Bluesky, the cid (content identifier) is
required in the request body. For LinkedIn, pass the composite comment URN returned
by the comments endpoints as commentId; an optional reactionType picks the reaction
(defaults to LIKE), and accounts connected before the social-feed scopes were
requested get a 403 with code `linkedin_reconnect_required`.

Instagram is in LIMITED RELEASE and not generally available: the call needs
`instagram_manage_engagement`, which Meta has so far granted this app only under
Standard Access, so it works for app admins, developers and testers of our Meta app
and returns a 403 with code `PLATFORM_BETA_RESTRICTED` for every other account.
That restriction lifts when Meta App Review grants Advanced Access; the constraints
below apply once it does.

Instagram covers comments and replies on feed posts, reels and carousels. Only an
account connected through Facebook Login can be granted
`instagram_manage_engagement`: an Instagram Login connection returns a 400 with
code `instagram_likes_require_facebook_login`, and an account whose token predates
the permission returns a 403 with code `reconnect_required`. Content from private
accounts cannot be liked. Instagram also enforces a burst limit of 50 like or
unlike calls per 5 seconds per Instagram account, and exceeding it locks that
account out of the like API for an hour, so pace bulk loops.


### Parameters

- **postId** (required) in path: No description
- **commentId** (required) in path: No description

### Request Body

- **accountId** (required) `string`: The account ID
- **reactionType** `string`: (LinkedIn only) Reaction to create. Defaults to LIKE; ignored on other platforms. - one of: LIKE, PRAISE, EMPATHY, INTEREST, APPRECIATION, ENTERTAINMENT
- **cid** `string`: (Bluesky only) Content identifier for the comment

### Responses

#### 200: Comment liked

**Response Body:**

- **status** `string`: No description
- **commentId** `string`: No description
- **liked** `boolean`: No description
- **likeUri** `string`: (Bluesky only) URI to use for unliking
- **alreadyReacted** `boolean`: LinkedIn only: the account already had this exact reaction, so nothing was created
- **reactionType** `string`: LinkedIn only: the reaction type now in effect
- **platform** `string`: No description

#### 400: Platform does not support liking comments

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required, or the account is missing the platform permission

#### 409: LinkedIn only: the account already holds a different reaction on this target (code invalid_resource_state); remove it before creating another.

---

## DELETE /v1/inbox/comments/{postId}/{commentId}/like

**Unlike comment**

Remove a like from a comment. Supported platforms: Facebook, X, Bluesky,
Reddit, LinkedIn, and Instagram in limited release. For Bluesky, the likeUri query
parameter is required. Instagram has the same limited release, Facebook Login,
`instagram_manage_engagement` and burst-limit constraints as liking.


### Parameters

- **postId** (required) in path: No description
- **commentId** (required) in path: No description
- **accountId** (required) in query: No description
- **likeUri** (optional) in query: (Bluesky only) The like URI returned when liking

### Responses

#### 200: Comment unliked

**Response Body:**

- **status** `string`: No description
- **commentId** `string`: No description
- **liked** `boolean`: No description
- **platform** `string`: No description

#### 400: Platform does not support unliking comments

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required, or the account is missing the platform permission

---

---
