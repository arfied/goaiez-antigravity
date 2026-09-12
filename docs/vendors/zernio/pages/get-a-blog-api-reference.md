# Get a blog API Reference

Fetches a single blog. `blogId` is the platform's numeric blog id from
`GET /v1/accounts/{accountId}/blogs`, not a Zernio id.

Supported on Shopify (platform `shopify`). Accounts on platforms
without blogs support return 400; a blogs-capable platform that lacks
this specific operation returns 405.


## GET /v1/accounts/{accountId}/blogs/{blogId}

**Get a blog**

Fetches a single blog. `blogId` is the platform's numeric blog id from
`GET /v1/accounts/{accountId}/blogs`, not a Zernio id.

Supported on Shopify (platform `shopify`). Accounts on platforms
without blogs support return 400; a blogs-capable platform that lacks
this specific operation returns 405.


### Parameters

- **accountId** (required) in path: Connected Shopify SocialAccount id.
- **blogId** (required) in path: Platform-native numeric blog id. Non-numeric values return 400.

### Responses

#### 200: Blog fetched

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

#### 404: Account not found or not accessible (code account_not_found), or blog not found (code blog_not_found).

#### 405: Platform does not support fetching a blog.

#### 429: Rate limited, either by Zernio or by Shopify. Retry later.

---

## PATCH /v1/accounts/{accountId}/blogs/{blogId}

**Update a blog**

Partial-updates a blog. Send any subset of `title` and `handle`; at
least one field is required (an empty body returns 400).

Supported on Shopify (platform `shopify`). Accounts on platforms
without blogs support return 400; a blogs-capable platform that lacks
this specific operation returns 405.


### Parameters

- **accountId** (required) in path: Connected Shopify SocialAccount id.
- **blogId** (required) in path: Platform-native numeric blog id. Non-numeric values return 400.

### Request Body

- **title** `string`: No description
- **handle** `string`: URL slug. Changing it changes the blog URL on the store.

### Responses

#### 200: Blog updated

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

#### 404: Account not found or not accessible (code account_not_found), or blog not found (code blog_not_found).

#### 405: Platform does not support updating a blog.

#### 429: Rate limited, either by Zernio or by Shopify. Retry later.

---

## DELETE /v1/accounts/{accountId}/blogs/{blogId}

**Delete a blog**

Deletes the blog AND every article in it. The delete happens on the
platform and is permanent; Zernio stores nothing to restore it from.

Supported on Shopify (platform `shopify`). Accounts on platforms
without blogs support return 400; a blogs-capable platform that lacks
this specific operation returns 405.


### Parameters

- **accountId** (required) in path: Connected Shopify SocialAccount id.
- **blogId** (required) in path: Platform-native numeric blog id. Non-numeric values return 400.

### Responses

#### 204: Blog deleted (no content).

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

#### 404: Account not found or not accessible (code account_not_found), or blog not found (code blog_not_found).

#### 405: Platform does not support deleting a blog.

#### 429: Rate limited, either by Zernio or by Shopify. Retry later.

---
