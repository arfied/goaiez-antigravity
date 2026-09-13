# Create a blog API Reference

Creates a blog on the connected store. The platform generates the URL
`handle` from the title when omitted.

Supported on Shopify (platform `shopify`). Accounts on platforms
without blogs support return 400; a blogs-capable platform that lacks
this specific operation returns 405.


## GET /v1/accounts/{accountId}/blogs

**List blogs**

Lists the blogs on the connected store, newest-first as the platform
returns them. Cursor-paginated: pass `limit` (1-50, default 20) and the
`cursor` from a previous response's `nextCursor`; `nextCursor` is null
when there are no more pages.

Supported on Shopify (platform `shopify`). Accounts on platforms
without blogs support return 400; a blogs-capable platform that lacks
this specific operation returns 405.


### Parameters

- **accountId** (required) in path: Connected Shopify SocialAccount id.
- **limit** (optional) in query: Page size (1-50).
- **cursor** (optional) in query: Opaque cursor from a previous response. Omit for the first page.

### Responses

#### 200: Blogs listed

**Response Body:**

- **platform** `string`: No description - one of: shopify
- **blogs** `array[Blog]`: 
- **nextCursor** `string,null`: Cursor for the next page; null when there are no more pages.

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

#### 403: The platform rejected the request (code insufficient_permissions); reconnect the Shopify account to restore access.

#### 404: Account not found or not accessible (code account_not_found).

#### 405: Platform does not support listing blogs.

#### 429: Rate limited, either by Zernio or by Shopify. Retry later.

---

## POST /v1/accounts/{accountId}/blogs

**Create a blog**

Creates a blog on the connected store. The platform generates the URL
`handle` from the title when omitted.

Supported on Shopify (platform `shopify`). Accounts on platforms
without blogs support return 400; a blogs-capable platform that lacks
this specific operation returns 405.


### Parameters

- **accountId** (required) in path: Connected Shopify SocialAccount id.

### Request Body

- **title** (required) `string`: No description
- **handle** `string`: URL slug. Generated from the title when omitted.

### Responses

#### 201: Blog created

**Response Body:**

- **platform** `string`: No description - one of: shopify
- **blog**: `Blog` - See schema definition

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

#### 403: The platform rejected the request (code insufficient_permissions); reconnect the Shopify account to restore access.

#### 404: Account not found or not accessible (code account_not_found).

#### 405: Platform does not support creating blogs.

#### 429: Rate limited, either by Zernio or by Shopify. Retry later.

---
