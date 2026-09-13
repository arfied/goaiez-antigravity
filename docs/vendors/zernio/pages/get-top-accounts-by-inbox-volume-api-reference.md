# Get top accounts by inbox volume API Reference

Leaderboard of accounts by inbox message volume. Decorates
each row with display labels from the live SocialAccount record
(so the UI shows username + displayName, not only an ID). Accounts
that no longer map to a SocialAccount surface as "(disconnected)"
so the row stays visible. Max date range is 365 days.


## GET /v1/analytics/inbox/top-accounts

**Get top accounts by inbox volume**

Leaderboard of accounts by inbox message volume. Decorates
each row with display labels from the live SocialAccount record
(so the UI shows username + displayName, not only an ID). Accounts
that no longer map to a SocialAccount surface as "(disconnected)"
so the row stays visible. Max date range is 365 days.


### Parameters

- **fromDate** (required) in query: No description
- **toDate** (optional) in query: No description
- **profileId** (optional) in query: No description
- **platform** (optional) in query: No description
- **source** (optional) in query: No description
- **limit** (optional) in query: Cap on returned rows. Lower than the posting listing's 100 because each row triggers a SocialAccount Mongo lookup.

### Responses

#### 200: Top accounts leaderboard

**Response Body:**

- **success** `boolean`: No description
- **from** `string` (date): No description
- **to** `string,null` (date): No description
- **accounts** `array[object]`: 
  - **accountId** `string`: No description
  - **platform** `string`: No description
  - **displayName** `string`: (disconnected) when the SocialAccount no longer exists
  - **username** `string`: No description
  - **received** `integer`: No description
  - **sent** `integer`: No description
  - **total** `integer`: No description
  - **conversations** `integer`: No description
  - **medianResponseSeconds** `integer`: No description
  - **repliedCount** `integer`: Distinguishes 'instant replies' from 'no replies at all' so a zero medianResponseSeconds with repliedCount=0 renders as an em dash instead of '0s'

#### 400: Validation error

**Response Body:**

- **error** `string`: No description
- **details** `object`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 500: Internal server error

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

---
