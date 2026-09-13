# Hide comment API Reference

Hide a comment on a post. Supported by Facebook, Instagram, Threads, X, and TikTok
(accounts connected through the TikTok for Business app).
Hidden comments are only visible to the commenter and page admin.
For X, the reply must belong to a conversation started by the authenticated user.


## POST /v1/inbox/comments/{postId}/{commentId}/hide

**Hide comment**

Hide a comment on a post. Supported by Facebook, Instagram, Threads, X, and TikTok
(accounts connected through the TikTok for Business app).
Hidden comments are only visible to the commenter and page admin.
For X, the reply must belong to a conversation started by the authenticated user.


### Parameters

- **postId** (required) in path: No description
- **commentId** (required) in path: No description

### Request Body

- **accountId** (required) `string`: The account ID

### Responses

#### 200: Comment hidden

**Response Body:**

- **status** `string`: No description
- **commentId** `string`: No description
- **hidden** `boolean`: No description
- **platform** `string`: No description

#### 400: Platform does not support hiding comments

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

---

## DELETE /v1/inbox/comments/{postId}/{commentId}/hide

**Unhide comment**

Unhide a previously hidden comment. Supported by Facebook, Instagram, Threads, X, and
TikTok (accounts connected through the TikTok for Business app).


### Parameters

- **postId** (required) in path: No description
- **commentId** (required) in path: No description
- **accountId** (required) in query: No description

### Responses

#### 200: Comment unhidden

**Response Body:**

- **status** `string`: No description
- **commentId** `string`: No description
- **hidden** `boolean`: No description
- **platform** `string`: No description

#### 400: Platform does not support unhiding comments

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

---

---
