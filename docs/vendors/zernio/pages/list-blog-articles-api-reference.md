# List blog articles API Reference

Lists the articles of a blog. Cursor-paginated: pass `limit` (1-50,
default 20) and the `cursor` from a previous response's `nextCursor`;
`nextCursor` is null when there are no more pages.

Supported on Shopify (platform `shopify`). Accounts on platforms
without blogs support return 400; a blogs-capable platform that lacks
this specific operation returns 405.


## GET /v1/accounts/{accountId}/blogs/{blogId}/articles

**List blog articles**

Lists the articles of a blog. Cursor-paginated: pass `limit` (1-50,
default 20) and the `cursor` from a previous response's `nextCursor`;
`nextCursor` is null when there are no more pages.

Supported on Shopify (platform `shopify`). Accounts on platforms
without blogs support return 400; a blogs-capable platform that lacks
this specific operation returns 405.


### Parameters

- **accountId** (required) in path: Connected Shopify SocialAccount id.
- **blogId** (required) in path: Platform-native numeric blog id. Non-numeric values return 400.
- **limit** (optional) in query: Page size (1-50).
- **cursor** (optional) in query: Opaque cursor from a previous response. Omit for the first page.

### Responses

#### 200: Articles listed

**Response Body:**

- **platform** `string`: No description - one of: shopify
- **articles** `array[BlogArticle]`: 
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

#### 404: Account not found or not accessible (code account_not_found), or blog not found (code blog_not_found).

#### 405: Platform does not support listing articles.

#### 429: Rate limited, either by Zernio or by Shopify. Retry later.

---

## POST /v1/accounts/{accountId}/blogs/{blogId}/articles

**Create a blog article**

Creates an article on the blog. Publishing behavior:

- `isPublished: false` keeps the article as a draft.
- A future `publishDate` schedules publication natively on the
  platform; the platform publishes it at that time with no Zernio
  queue involved.
- `seo.title` / `seo.description` map to Shopify's global `title_tag`
  and `description_tag` metafields (the fields Shopify themes read for
  the page title and meta description).

Supported on Shopify (platform `shopify`). Accounts on platforms
without blogs support return 400; a blogs-capable platform that lacks
this specific operation returns 405.


### Parameters

- **accountId** (required) in path: Connected Shopify SocialAccount id.
- **blogId** (required) in path: Platform-native numeric blog id. Non-numeric values return 400.

### Request Body

- **title** (required) `string`: No description
- **bodyHtml** `string`: Article body as HTML.
- **handle** `string`: URL slug. Generated from the title when omitted.
- **tags** `array`: No description
- **author** `string`: Display name of the article author.
- **excerpt** `string`: Short summary shown in blog listings.
- **image** `object`: Featured image. The platform downloads it, so the URL must be publicly reachable.
- **seo** `object`: Search-engine overrides. Maps to Shopify global metafields (title_tag and description_tag).
- **isPublished** `boolean`: Set false to create the article as a draft.
- **publishDate** `string`: ISO 8601 datetime with offset (or Z). A future date schedules publication natively on the platform.

### Responses

#### 201: Article created

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

#### 404: Account not found or not accessible (code account_not_found), or blog not found (code blog_not_found).

#### 405: Platform does not support creating articles.

#### 429: Rate limited, either by Zernio or by Shopify. Retry later.

---
