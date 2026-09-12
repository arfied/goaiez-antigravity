# Reply to a mention API Reference

Reply to a mention of the connected account. Supported on Instagram only.

Two shapes, selected by whether `commentId` is present:

- **Comment mention** (someone @mentioned the account inside a comment): pass both
  `mediaId` and `commentId`. Instagram posts a reply under that comment.
- **Caption mention** (someone @mentioned the account in their media caption, so no
  comment exists): pass `mediaId` only. Instagram posts a comment on their media.

Story mentions are not supported by Instagram's API.

`GET /v1/inbox/mentions` currently returns LinkedIn mentions only and does
not surface Instagram mentions. Source `mediaId` and `commentId` from Instagram's
`comments` webhook, which is where mention notifications are delivered for accounts
connected through Instagram Login.


## POST /v1/inbox/mentions/reply

**Reply to a mention**

Reply to a mention of the connected account. Supported on Instagram only.

Two shapes, selected by whether `commentId` is present:

- **Comment mention** (someone @mentioned the account inside a comment): pass both
  `mediaId` and `commentId`. Instagram posts a reply under that comment.
- **Caption mention** (someone @mentioned the account in their media caption, so no
  comment exists): pass `mediaId` only. Instagram posts a comment on their media.

Story mentions are not supported by Instagram's API.

`GET /v1/inbox/mentions` currently returns LinkedIn mentions only and does
not surface Instagram mentions. Source `mediaId` and `commentId` from Instagram's
`comments` webhook, which is where mention notifications are delivered for accounts
connected through Instagram Login.


### Request Body

- **accountId** (required) `string`: The Instagram account ID
- **mediaId** (required) `string`: The ID of the media the account was mentioned in
- **commentId** `string`: The mentioning comment's ID. Omit for a caption mention.
- **message** (required) `string`: The reply text

### Responses

#### 200: Reply posted

**Response Body:**

- **success** `boolean`: No description
- **id** `string`: ID of the created reply or comment

#### 400: Platform does not support replying to mentions (code: platform_not_supported), or missing mediaId/message.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

#### 404: Account not found

#### 502: Instagram was unreachable or returned an unclassified error. Instagram 4xx statuses are forwarded as-is.

---

---
