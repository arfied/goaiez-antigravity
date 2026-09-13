# Read a Facebook Page's webhook subscription API Reference

Returns the webhook fields Zernio's app is subscribed to on the connected Page, read live from Meta.
Use it to confirm `leadgen` is present: a Page missing it keeps delivering every other event while
lead ads stop arriving, with nothing to indicate it.


## GET /v1/accounts/{accountId}/webhook-subscription

**Read a Facebook Page's webhook subscription**

Returns the webhook fields Zernio's app is subscribed to on the connected Page, read live from Meta.
Use it to confirm `leadgen` is present: a Page missing it keeps delivering every other event while
lead ads stop arriving, with nothing to indicate it.


### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: The Page's current subscription

**Response Body:**

- **pageId** `string`: No description
- **appSubscribed** `boolean`: False when the Page carries no subscription for our app at all.
- **leadgen** `boolean`: Whether lead ads submitted on this Page reach Zernio in real time.
- **subscribedFields** `array[string]`: 
- **warning** `string,null`: Present only when leadgen is missing.

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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 409: The connection has no selected Page

---

## POST /v1/accounts/{accountId}/webhook-subscription

**Re-subscribe a Facebook Page to Zernio's webhooks**

Re-sends the full field set to Meta and returns the subscription read back afterwards.
Meta only honours the field set sent at subscribe time, so a Page connected before a field
existed stays without it until this runs. The response reflects what Meta actually granted,
not what was requested.


### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: The subscription after re-subscribing

**Response Body:**

- **resubscribed** `boolean`: No description
- **pageId** `string`: No description
- **appSubscribed** `boolean`: No description
- **leadgen** `boolean`: No description
- **subscribedFields** `array[string]`: 
- **warning** `string,null`: No description

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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 409: The connection has no selected Page

#### 502: Meta rejected the subscription

---
