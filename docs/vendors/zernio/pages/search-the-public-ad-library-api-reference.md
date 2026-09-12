# Search the public Ad Library API Reference

Competitor and market research over the public ad archives. Meta's Ad Library
(`GET /ads_archive`) is searched with Zernio's own developer access, so `platform=meta` needs
no connected account at all. LinkedIn's Ad Library (`GET /rest/adLibrary`) runs on a connected
`linkedin` / `linkedinads` account, passed as `accountId`. Passing a Meta account as `accountId`
also selects Meta. Rows are returned in the platform's raw shape under `data`; `paging.after`
is an opaque cursor on both (`null` when exhausted).

**Meta coverage.** Political and social-issue ads are searchable worldwide. Every other ad is
in the archive only if it was delivered to the EU or UK within the last year, so a US-only
commercial advertiser is invisible. Spend, impressions and demographics are political-only
fields and are left out of the default projection; request them via `fields`. All customers
share Zernio's Meta quota, so a `429` means back off for a minute.

**LinkedIn coverage.** Ads served after June 1 2023, worldwide, kept for a year after their
last impression. EU-delivered ads carry impression ranges and the disclosed targeting facets.
Pages are capped at 25 ads (`limit` > 25 is a 400); `after` is the next offset.

Which params apply: `q`, `countries`, `since`, `until`, `limit`, `after` on both; `pageIds`,
`adType`, `status`, `platforms`, `mediaType`, `languages`, `searchType`, `fields` are Meta-only;
`advertiser` is LinkedIn-only. Passing a param the account's platform does not support is a 400
naming the param.

## GET /v1/ads/library

**Search the public Ad Library**

Competitor and market research over the public ad archives. Meta's Ad Library
(`GET /ads_archive`) is searched with Zernio's own developer access, so `platform=meta` needs
no connected account at all. LinkedIn's Ad Library (`GET /rest/adLibrary`) runs on a connected
`linkedin` / `linkedinads` account, passed as `accountId`. Passing a Meta account as `accountId`
also selects Meta. Rows are returned in the platform's raw shape under `data`; `paging.after`
is an opaque cursor on both (`null` when exhausted).

**Meta coverage.** Political and social-issue ads are searchable worldwide. Every other ad is
in the archive only if it was delivered to the EU or UK within the last year, so a US-only
commercial advertiser is invisible. Spend, impressions and demographics are political-only
fields and are left out of the default projection; request them via `fields`. All customers
share Zernio's Meta quota, so a `429` means back off for a minute.

**LinkedIn coverage.** Ads served after June 1 2023, worldwide, kept for a year after their
last impression. EU-delivered ads carry impression ranges and the disclosed targeting facets.
Pages are capped at 25 ads (`limit` > 25 is a 400); `after` is the next offset.

Which params apply: `q`, `countries`, `since`, `until`, `limit`, `after` on both; `pageIds`,
`adType`, `status`, `platforms`, `mediaType`, `languages`, `searchType`, `fields` are Meta-only;
`advertiser` is LinkedIn-only. Passing a param the account's platform does not support is a 400
naming the param.

### Parameters

- **platform** (optional) in query: Which archive to search. `meta` needs no accountId. Required unless accountId is given.
- **accountId** (optional) in query: Zernio SocialAccount id. Required for LinkedIn (linkedin / linkedinads: its token searches). Optional for Meta, where any facebook / instagram / metaads account only selects the platform.
- **q** (optional) in query: Keyword search. Meta does not translate it, so write it in the ads' language. Required unless pageIds (Meta) or advertiser (LinkedIn) is given.
- **pageIds** (optional) in query: Meta only. Comma-separated Facebook Page ids (max 10) whose ads to list.
- **advertiser** (optional) in query: LinkedIn only. Advertiser (Page) name to search.
- **countries** (optional) in query: Comma-separated ISO 3166-1 alpha-2 codes the ads reached. Meta defaults to ALL (an explicit ALL is Meta-only); LinkedIn searches every market when omitted.
- **adType** (optional) in query: Meta only.
- **status** (optional) in query: Meta only. ACTIVE = eligible for delivery right now.
- **platforms** (optional) in query: Meta only. Comma-separated publisher platforms: FACEBOOK, INSTAGRAM, AUDIENCE_NETWORK, MESSENGER, WHATSAPP, OCULUS, THREADS, STREAMING_SERVICES.
- **mediaType** (optional) in query: Meta only.
- **languages** (optional) in query: Meta only. Comma-separated ISO 639-1 codes of the ad text.
- **since** (optional) in query: Earliest delivery date (YYYY-MM-DD).
- **until** (optional) in query: Latest delivery date (YYYY-MM-DD).
- **searchType** (optional) in query: Meta only. Whether q matches words in any order or as an exact phrase (comma-separate phrases to match all of them).
- **fields** (optional) in query: Meta only. Comma-separated Graph field override. Supports nested {} projections and Graph field modifiers, so a nested edge can be paged explicitly: without a .limit() modifier the expansion runs at the Meta default page size and the tail is dropped silently.
- **limit** (optional) in query: Rows per page. LinkedIn accepts at most 25.
- **after** (optional) in query: paging.after of the previous page.

### Responses

#### 200: Archived ads (raw platform shape)

**Response Body:**

- **platform** (required) `string`: No description - one of: meta, linkedin
- **data** (required) `array[object]`: 
  Type: `object`
- **paging** (required) `object`: 
  - **after** `string,null`: Cursor for the next page; null when exhausted.
  - **total** `integer`: LinkedIn only. Total matching ads.

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

#### 403: Ads access required (legacy plans need the Ads add-on; included on usage-based plans), or `payment_required`: the billing owner has no payment method on file and no legacy paid plan. Searches are free; the card keeps the shared archive quota for real accounts.

#### 404: The account or requested resource was not found or is not accessible. An account ID may have been disconnected and removed. Read GET /v1/accounts for current account IDs.

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

#### 409: The account exists but is inactive or needs reconnection. Reconnect it, then read GET /v1/accounts for its current account ID before retrying. Code: ads_connection_required.

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

#### 501: Only supported on Meta and LinkedIn accounts

#### 503: Meta's Ad Library is unavailable on Zernio's side (`PLATFORM_DISABLED`); LinkedIn searches are unaffected.

---
