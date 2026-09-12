# Update a blog article API Reference

Partial-updates an article. Send any subset of the create fields
(`title`, `bodyHtml`, `handle`, `tags`, `author`, `excerpt`, `image`,
`seo`, `isPublished`, `publishDate`); at least one field is required
(an empty body returns 400). `isPublished` and `publishDate` behave as
on create: `isPublished: false` unpublishes back to a draft and a
future `publishDate` schedules publication natively on the platform.

Supported on Shopify (platform `shopify`). Accounts on platforms
without blogs support return 400; a blogs-capable platform that lacks
this specific operation returns 405.


## GET /v1/accounts/{accountId}/blogs/{blogId}/articles/{articleId}

**Get a blog article**

Fetches a single article. An article addressed through a blog it does
not belong to is a 404 (code blog_article_not_found).

Supported on Shopify (platform `shopify`). Accounts on platforms
without blogs support return 400; a blogs-capable platform that lacks
this specific operation returns 405.


### Parameters

- **accountId** (required) in path: Connected Shopify SocialAccount id.
- **blogId** (required) in path: Platform-native numeric blog id. Non-numeric values return 400.
- **articleId** (required) in path: Platform-native numeric article id. Non-numeric values return 400.

### Responses

#### 200: Article fetched

**Response Body:**

- **platform** `string`: No description - one of: shopify
- **article**: `BlogArticle` - See schema definition

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

#### 404: Account not found or not accessible (code account_not_found), blog not found (code blog_not_found), or article not found (code blog_article_not_found).

#### 405: Platform does not support fetching an article.

#### 429: Rate limited, either by Zernio or by Shopify. Retry later.

---

## PATCH /v1/accounts/{accountId}/blogs/{blogId}/articles/{articleId}

**Update a blog article**

Partial-updates an article. Send any subset of the create fields
(`title`, `bodyHtml`, `handle`, `tags`, `author`, `excerpt`, `image`,
`seo`, `isPublished`, `publishDate`); at least one field is required
(an empty body returns 400). `isPublished` and `publishDate` behave as
on create: `isPublished: false` unpublishes back to a draft and a
future `publishDate` schedules publication natively on the platform.

Supported on Shopify (platform `shopify`). Accounts on platforms
without blogs support return 400; a blogs-capable platform that lacks
this specific operation returns 405.


### Parameters

- **accountId** (required) in path: Connected Shopify SocialAccount id.
- **blogId** (required) in path: Platform-native numeric blog id. Non-numeric values return 400.
- **articleId** (required) in path: Platform-native numeric article id. Non-numeric values return 400.

### Request Body

- **title** `string`: No description
- **bodyHtml** `string`: Article body as HTML.
- **handle** `string`: URL slug of the article.
- **tags** `array`: Replaces the full tag list.
- **author** `string`: Display name of the article author.
- **excerpt** `string`: Short summary shown in blog listings.
- **image** `object`: Featured image. The platform downloads it, so the URL must be publicly reachable.
- **seo** `object`: Search-engine overrides. Maps to Shopify global metafields (title_tag and description_tag).
- **isPublished** `boolean`: Set false to unpublish the article back to a draft.
- **publishDate** `string`: ISO 8601 datetime with offset (or Z). A future date schedules publication natively on the platform.

### Responses

#### 200: Article updated

**Response Body:**

- **platform** `string`: No description - one of: shopify
- **article**: `BlogArticle` - See schema definition

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

#### 404: Account not found or not accessible (code account_not_found), blog not found (code blog_not_found), or article not found (code blog_article_not_found).

#### 405: Platform does not support updating an article.

#### 429: Rate limited, either by Zernio or by Shopify. Retry later.

---

## DELETE /v1/accounts/{accountId}/blogs/{blogId}/articles/{articleId}

**Delete a blog article**

Deletes the article. The delete happens on the platform and is
permanent; Zernio stores nothing to restore it from.

Supported on Shopify (platform `shopify`). Accounts on platforms
without blogs support return 400; a blogs-capable platform that lacks
this specific operation returns 405.


### Parameters

- **accountId** (required) in path: Connected Shopify SocialAccount id.
- **blogId** (required) in path: Platform-native numeric blog id. Non-numeric values return 400.
- **articleId** (required) in path: Platform-native numeric article id. Non-numeric values return 400.

### Responses

#### 204: Article deleted (no content).

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

#### 404: Account not found or not accessible (code account_not_found), blog not found (code blog_not_found), or article not found (code blog_article_not_found).

#### 405: Platform does not support deleting an article.

#### 429: Rate limited, either by Zernio or by Shopify. Retry later.

---
