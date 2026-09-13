# Set comment moderation status API Reference

Set a comment's moderation status. Supported on YouTube only.

Use this to work a moderation queue: approve a held comment (`published`), reject it
(`rejected`), or send it back for review (`heldForReview`).

The request must be authorized by the owner of the channel or video the comment
belongs to. You cannot moderate comments on videos you do not own.

This is distinct from `POST /v1/inbox/comments/{postId}/{commentId}/hide`, which
covers Facebook, Instagram, Threads, and X and does not apply to YouTube.


## POST /v1/inbox/comments/{postId}/{commentId}/moderation

**Set comment moderation status**

Set a comment's moderation status. Supported on YouTube only.

Use this to work a moderation queue: approve a held comment (`published`), reject it
(`rejected`), or send it back for review (`heldForReview`).

The request must be authorized by the owner of the channel or video the comment
belongs to. You cannot moderate comments on videos you do not own.

This is distinct from `POST /v1/inbox/comments/{postId}/{commentId}/hide`, which
covers Facebook, Instagram, Threads, and X and does not apply to YouTube.


### Parameters

- **postId** (required) in path: No description
- **commentId** (required) in path: No description

### Request Body

- **accountId** (required) `string`: The account ID
- **platform** (required) `string`: Only YouTube supports comment moderation - one of: youtube
- **moderationStatus** (required) `string`: published approves the comment, rejected removes it, heldForReview returns it to the queue. - one of: published, rejected, heldForReview
- **banAuthor** `boolean`: Also ban the comment's author, auto-rejecting their future comments. Only valid when moderationStatus is "rejected"; any other pairing is a 400.


### Responses

#### 200: Moderation status applied

**Response Body:**

- **success** `boolean`: No description

#### 400: Platform does not support comment moderation (code: platform_not_supported), or banAuthor was set without moderationStatus=rejected.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

#### 404: Account not found

#### 502: YouTube rejected the request (e.g. the account does not own the video).

---

---
