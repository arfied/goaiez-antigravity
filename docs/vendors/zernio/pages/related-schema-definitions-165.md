# Related Schema Definitions

## BlogArticle

An article inside a blog on the connected platform.

### Properties

- **id** `string`: Platform-native article id (numeric string for Shopify).
- **blogId** `string`: Platform-native id of the blog the article belongs to.
- **platform** `string`: No description - one of: shopify
- **title** `string`: No description
- **bodyHtml** `string,null`: Article body as HTML.
- **handle** `string`: URL slug of the article.
- **tags** `array`: No description
- **author** `string,null`: Display name of the article author.
- **excerpt** `string,null`: Short summary shown in blog listings.
- **image** `object,null`: Featured image.
- **isPublished** `boolean`: False while the article is a draft or its publish date is still in the future.
- **publishedAt** `string,null`: When the article was (or is scheduled to be) published; null for drafts.
- **createdAt** `string,null`: No description
- **updatedAt** `string,null`: No description

## ErrorResponse

Canonical error envelope. `error` is the human-readable message; `type`,
`code`, `param`, `platform`, and `platformError` are top-level siblings
for programmatic handling. For upstream platform failures (`type:
platform_error`), `platformError` carries the provider's raw payload
verbatim (for Meta: `error_subcode`, `error_user_title`, `error_user_msg`).


### Properties

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

---
