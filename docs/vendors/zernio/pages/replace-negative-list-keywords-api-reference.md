# Replace negative list keywords API Reference

Replaces the full desired keyword set. Existing keywords are diffed by normalized text and match type; creates and removals are applied atomically in one mutation. Unchanged criteria retain their ids. Send an empty keywords array to clear the list. Changes affect every campaign using this list. Each create or removal consumes one daily operation; the entire batch must fit the remaining quota.

## PUT /v1/ads/accounts/negative-keyword-lists/{listId}/keywords

**Replace negative list keywords**

Replaces the full desired keyword set. Existing keywords are diffed by normalized text and match type; creates and removals are applied atomically in one mutation. Unchanged criteria retain their ids. Send an empty keywords array to clear the list. Changes affect every campaign using this list. Each create or removal consumes one daily operation; the entire batch must fit the remaining quota.

### Parameters

- **listId** (required) in path: No description

### Request Body

- **accountId** (required) `string`: Zernio SocialAccount id.
- **customerId** `string`: Connected Google Ads customer id, without dashes. Required when the connection has multiple customers.
- **platform** `string`: Optional courtesy field. The resolved account or campaign determines support; other platforms return 501. - one of: facebook, instagram, tiktok, linkedin, pinterest, google, twitter, openai
- **keywords** (required) `array`: Full desired keyword set. Bare strings use broad match. Send [] to clear the list.

### Responses

#### 200: Successful response.

**Response Body:**

- **created** `integer`: New criteria or campaign links created.
- **removed** `integer`: Existing criteria or campaign links removed.
- **customerId** `string`: Resolved Google Ads customer id.

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

#### 403: Ads access and permission to the selected account are required.

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

#### 409: Ambiguous campaign or account selection. Use a profile-scoped key. A list still attached to a campaign may also be rejected by Google. The account may also be inactive or need reconnection (code ads_connection_required). Reconnect it and read GET /v1/accounts for its current ID before retrying.

#### 422: Google Ads connection is missing or unavailable.

#### 429: Google Ads operations budget or platform quota exhausted.

#### 501: Available only on Google Ads.

---
