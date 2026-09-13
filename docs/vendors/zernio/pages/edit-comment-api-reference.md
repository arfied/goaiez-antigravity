# Edit comment API Reference

Edit the body of a comment the connected account posted. Supported on Reddit only.

Reddit keeps the same comment id after an edit. Reddit exposes no API to edit a post
title, and a link post has no editable body. To edit a published post's body, use
`POST /v1/posts/{postId}/edit`.


## PATCH /v1/inbox/comments/{postId}/{commentId}

**Edit comment**

Edit the body of a comment the connected account posted. Supported on Reddit only.

Reddit keeps the same comment id after an edit. Reddit exposes no API to edit a post
title, and a link post has no editable body. To edit a published post's body, use
`POST /v1/posts/{postId}/edit`.


### Parameters

- **postId** (required) in path: No description
- **commentId** (required) in path: No description

### Request Body

- **accountId** (required) `string`: The account ID
- **platform** (required) `string`: Only Reddit supports editing a comment - one of: reddit
- **content** (required) `string`: The new comment body

### Responses

#### 200: Comment edited

**Response Body:**

- **status** `string`: No description
- **commentId** `string`: No description
- **platform** `string`: No description

#### 400: Platform does not support editing comments (code: platform_not_supported), or content missing.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

#### 404: Account not found

#### 502: Reddit was unreachable or returned an unclassified error. Reddit 4xx statuses are forwarded as-is.

---

---
