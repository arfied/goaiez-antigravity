# Get a call recording API Reference

Channel-agnostic recording fetch: resolves a fresh, playable MP3 URL
for any call regardless of channel (provider-signed URLs expire ~10
minutes after signing, so this re-signs on demand). Default responds
`302 Found` redirecting to the fresh URL; pass `as=json` to receive
`{ url }` instead.


## GET /v1/calls/{id}/recording

**Get a call recording**

Channel-agnostic recording fetch: resolves a fresh, playable MP3 URL
for any call regardless of channel (provider-signed URLs expire ~10
minutes after signing, so this re-signs on demand). Default responds
`302 Found` redirecting to the fresh URL; pass `as=json` to receive
`{ url }` instead.


### Parameters

- **id** (required) in path: No description
- **as** (optional) in query: `json` returns `{ url }` instead of a 302 redirect.

### Responses

#### 200: Recording URL (`as=json` only).

**Response Body:**

- **url** `string`: No description

#### 302: Redirect to a freshly-signed recording URL.

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

#### 404: Call not found, or no recording is available for this call

#### 502: Recording provider lookup failed

---
