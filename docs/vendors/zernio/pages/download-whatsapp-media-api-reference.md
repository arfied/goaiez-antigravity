# Download WhatsApp media API Reference

Streams the binary for a WhatsApp attachment. This is the endpoint the
`url` on a WhatsApp `attachments[]` entry points at, in both the
`message.received` webhook and the List messages response.

**This is an authenticated endpoint, not a public link.** Send
`Authorization: Bearer <your API key>` exactly as you would for any other
call. Passing the URL straight to a browser, an LLM vision API, or a
no-code "download file" step without the header returns `401`. This is
the most common integration mistake on this endpoint, and it differs from
Instagram, Facebook and Telegram, whose `attachments[].url` is a direct
CDN link that needs no header.

**Fetch on receipt, not lazily.** WhatsApp media lives in Meta's media
store, not ours, and it is removed after a limited retention window
(currently 7 days, and Meta has been dropping some inbound media sooner).
Once Meta drops it the media is unrecoverable and this endpoint answers
`400` permanently, so retrying will never succeed. Download and store the
bytes when the webhook arrives.


## GET /v1/whatsapp/media/{mediaId}

**Download WhatsApp media**

Streams the binary for a WhatsApp attachment. This is the endpoint the
`url` on a WhatsApp `attachments[]` entry points at, in both the
`message.received` webhook and the List messages response.

**This is an authenticated endpoint, not a public link.** Send
`Authorization: Bearer <your API key>` exactly as you would for any other
call. Passing the URL straight to a browser, an LLM vision API, or a
no-code "download file" step without the header returns `401`. This is
the most common integration mistake on this endpoint, and it differs from
Instagram, Facebook and Telegram, whose `attachments[].url` is a direct
CDN link that needs no header.

**Fetch on receipt, not lazily.** WhatsApp media lives in Meta's media
store, not ours, and it is removed after a limited retention window
(currently 7 days, and Meta has been dropping some inbound media sooner).
Once Meta drops it the media is unrecoverable and this endpoint answers
`400` permanently, so retrying will never succeed. Download and store the
bytes when the webhook arrives.


### Parameters

- **mediaId** (required) in path: The media id from `attachments[].payload.id`.
- **accountId** (required) in query: The WhatsApp account that received the media.

### Responses

#### 200: The media binary, streamed with its original content type.

#### 400: Media is no longer available on WhatsApp servers (expired or deleted by Meta). Permanent, do not retry.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found, not accessible to the caller, or the media does not belong to it.

#### 502: Meta could not be reached or returned an unexpected error.

---

---
