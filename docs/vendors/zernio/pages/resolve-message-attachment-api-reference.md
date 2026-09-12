# Resolve message attachment API Reference

Resolve one attachment on a message to a media url that works right now.

Instagram and Facebook sign DM media urls per request and expire them, so
the `url` on a message is a snapshot: it works when you read the message
and stops working later. This endpoint checks the stored url and, when it
has gone stale, re-mints the message's media from Meta and persists it
before answering. The message id never expires, so this URL is the one to
store. It is returned ready-made on each attachment as `refreshUrl` when
you read a message over REST.

**Webhook payloads do not carry `refreshUrl`**, so a webhook-driven
integration builds this URL itself. Every piece is in the event:
`message.conversationId`, `message.platformMessageId`, the attachment's
zero-based position, and `account.accountId`. **`accountId` is a
required query parameter**; omitting it returns `400`
`missing_required_field`, which is the same requirement
`GET /v1/whatsapp/media/{mediaId}` has.

By default it responds `302` to the live media url, so it can be used
directly as an `<img src>` on a browser session. API-key integrators
should pass `?format=json` and read `url` off the body, since a browser
cannot attach an Authorization header to an image request.

Only Instagram and Facebook media can be re-minted. On other platforms
the stored url is returned as-is when it still resolves, and `404`
otherwise.


## GET /v1/inbox/conversations/{conversationId}/messages/{messageId}/attachments/{index}

**Resolve message attachment**

Resolve one attachment on a message to a media url that works right now.

Instagram and Facebook sign DM media urls per request and expire them, so
the `url` on a message is a snapshot: it works when you read the message
and stops working later. This endpoint checks the stored url and, when it
has gone stale, re-mints the message's media from Meta and persists it
before answering. The message id never expires, so this URL is the one to
store. It is returned ready-made on each attachment as `refreshUrl` when
you read a message over REST.

**Webhook payloads do not carry `refreshUrl`**, so a webhook-driven
integration builds this URL itself. Every piece is in the event:
`message.conversationId`, `message.platformMessageId`, the attachment's
zero-based position, and `account.accountId`. **`accountId` is a
required query parameter**; omitting it returns `400`
`missing_required_field`, which is the same requirement
`GET /v1/whatsapp/media/{mediaId}` has.

By default it responds `302` to the live media url, so it can be used
directly as an `<img src>` on a browser session. API-key integrators
should pass `?format=json` and read `url` off the body, since a browser
cannot attach an Authorization header to an image request.

Only Instagram and Facebook media can be re-minted. On other platforms
the stored url is returned as-is when it still resolves, and `404`
otherwise.


### Parameters

- **conversationId** (required) in path: The conversation ID (Zernio id or platform conversation id)
- **messageId** (required) in path: The message id as returned by the list-messages endpoint (the platform message id)
- **index** (required) in path: Zero-based position of the attachment in the message's attachments array
- **accountId** (required) in query: Account ID. Required: without it the request returns 400 missing_required_field.
- **format** (optional) in query: `redirect` (default) answers 302 to the media; `json` returns the url in the body

### Responses

#### 200: Resolved url (only when format=json)

**Response Body:**

- **status** `string`: No description (example: "success")
- **url** `string`: Live media url. Short-lived; re-request this endpoint rather than storing it.
- **refreshed** `boolean`: True when the stored url had expired and was re-minted from the platform.

#### 302: Redirect to the live media url

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

#### 403: Inbox addon required

#### 404: Account, conversation, message or attachment not found, or the platform no longer serves the media

---
