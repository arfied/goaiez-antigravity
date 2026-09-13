# Check whether an Instagram user follows the account API Reference

Resolves the follow relationship between an Instagram user and the connected
account, plus their public profile counters.

`userId` is the Instagram-scoped id (IGSID) Meta gives you on a webhook:
`sender.id` on `message.received`, `comment.author.id` on `comment.received`.

**Meta only answers for people who have MESSAGED the account.** Commenting grants
no consent, so a commenter who has never DMed you is unresolvable - that is a
platform rule, not a limitation of this endpoint. When it cannot be resolved the
response is still `200` with `isFollower: null` and an `unavailableReason`, because
"unknown" is a normal state to branch on:

  * `consent_required` - the user has never messaged this account.
  * `dm_access_disabled` - the account owner turned off Instagram Direct API access.
  * `not_messageable` - the id is not a messaging-scoped id.
  * `error` - a transient Graph API failure.

To gate a comment automation on this, use the automation's `audience` rules instead
of calling this per comment - they run the same lookup only on comments that
actually match a keyword, and can ask the commenter to confirm with one tap.

Answers are cached briefly per (account, user). Pass `refresh=true` right after
asking someone to follow, so a follow from a moment ago is visible.


## GET /v1/accounts/{accountId}/follow-status/{userId}

**Check whether an Instagram user follows the account**

Resolves the follow relationship between an Instagram user and the connected
account, plus their public profile counters.

`userId` is the Instagram-scoped id (IGSID) Meta gives you on a webhook:
`sender.id` on `message.received`, `comment.author.id` on `comment.received`.

**Meta only answers for people who have MESSAGED the account.** Commenting grants
no consent, so a commenter who has never DMed you is unresolvable - that is a
platform rule, not a limitation of this endpoint. When it cannot be resolved the
response is still `200` with `isFollower: null` and an `unavailableReason`, because
"unknown" is a normal state to branch on:

  * `consent_required` - the user has never messaged this account.
  * `dm_access_disabled` - the account owner turned off Instagram Direct API access.
  * `not_messageable` - the id is not a messaging-scoped id.
  * `error` - a transient Graph API failure.

To gate a comment automation on this, use the automation's `audience` rules instead
of calling this per comment - they run the same lookup only on comments that
actually match a keyword, and can ask the commenter to confirm with one tap.

Answers are cached briefly per (account, user). Pass `refresh=true` right after
asking someone to follow, so a follow from a moment ago is visible.


### Parameters

- **accountId** (required) in path: Instagram account ID
- **userId** (required) in path: Instagram-scoped user id (IGSID) from a webhook payload
- **refresh** (optional) in query: Bypass the cache and re-query Meta

### Responses

#### 200: Follow status (fields are null when Meta would not resolve it)

**Response Body:**

- **userId** (required) `string`: No description
- **accountId** (required) `string`: No description
- **isFollower** (required) `boolean,null`: The user follows this account. Null = unknown, never "no".
- **isFollowedByAccount** `boolean,null`: This account follows the user.
- **followerCount** `integer,null`: No description
- **isVerified** `boolean,null`: No description
- **username** `string,null`: No description
- **name** `string,null`: No description
- **unavailableReason** `string,null`: Why the follow relationship could not be resolved. Null when it was. - one of: consent_required, dm_access_disabled, not_messageable, error, 

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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---
