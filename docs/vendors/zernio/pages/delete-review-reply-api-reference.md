# Delete review reply API Reference

Delete a reply to a review (Google Business Profile only). Requires accountId in request body.

## POST /v1/inbox/reviews/{reviewId}/reply

**Reply to review**

Post a reply to a review. Requires accountId in request body.

**Idempotency:** send an `Idempotency-Key` header to make retries safe
(e.g. after a client-side timeout where delivery is unknown): same key +
same body replays the original response (with `Idempotent-Replayed: true`)
instead of sending the reply to the platform again; same key + different
body returns 422; a key still in flight returns 409. Keys are retained for
24 hours and are scoped to the credential and to this exact path, so
reusing a key against a different reviewId returns 422 rather than
replaying the other review's response.

Only successful (2xx) responses are stored for replay. If the request
throws or returns a non-2xx status the key is released, so the header
protects the "request succeeded but the response was lost" case. After an
ambiguous failure (a 5xx or a network timeout) fetch the review before
retrying with the same key, and treat a missing reply as inconclusive
rather than as proof nothing was sent.


### Parameters

- **reviewId** (required) in path: Review ID (URL-encoded for Google Business Profile)
- **undefined** (optional): No description

### Request Body

- **accountId** (required) `string`: No description
- **message** (required) `string`: No description

### Responses

#### 200: Reply posted

**Response Body:**

- **status** `string`: No description
- **reply** `object`: 
  - **id** `string`: No description
  - **text** `string`: No description
  - **created** `string` (date-time): No description
- **platform** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

#### 409: Same Idempotency-Key still processing; retry after a short backoff

#### 422: Idempotency-Key reused with a different request

---

## DELETE /v1/inbox/reviews/{reviewId}/reply

**Delete review reply**

Delete a reply to a review (Google Business Profile only). Requires accountId in request body.

### Parameters

- **reviewId** (required) in path: No description

### Request Body

- **accountId** (required) `string`: No description

### Responses

#### 200: Reply deleted

**Response Body:**

- **status** `string`: No description
- **message** `string`: No description
- **platform** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

---

---
