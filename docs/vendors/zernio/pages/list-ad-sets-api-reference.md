# List ad sets API Reference

Ad sets (Google ad groups) synced for the connection, optionally
filtered by platform and campaignId. Reads the `ad_sets` table
directly, independent of the `ads` rollup GET /v1/ads/tree uses, so a
newly created standalone ad group with no ad yet (POST /v1/ads/ad-sets,
Google only) is visible here even though it is invisible in the tree
until an ad joins it via `adSetId` on POST /v1/ads/create. Returns at most 500
rows, newest first.

## GET /v1/ads/ad-sets

**List ad sets**

Ad sets (Google ad groups) synced for the connection, optionally
filtered by platform and campaignId. Reads the `ad_sets` table
directly, independent of the `ads` rollup GET /v1/ads/tree uses, so a
newly created standalone ad group with no ad yet (POST /v1/ads/ad-sets,
Google only) is visible here even though it is invisible in the tree
until an ad joins it via `adSetId` on POST /v1/ads/create. Returns at most 500
rows, newest first.

### Parameters

- **accountId** (optional) in query: Account ID
- **campaignId** (optional) in query: Platform campaign ID
- **platform** (optional) in query: No description

### Responses

#### 200: Ad sets

**Response Body:**

- **adSets** `array[object]`: 
  - **platformAdSetId** `string`: No description
  - **platform** `string`: No description
  - **adSetName** `string,null`: No description
  - **status** `string,null`: No description
  - **platformAdSetStatus** `string,null`: No description
  - **platformCampaignId** `string,null`: No description
  - **platformAdAccountId** `string`: No description
  - **accountId** `string,null`: No description
  - **profileId** `string`: No description
  - **currency** `string,null`: No description
  - **budget** `object,null`: No description
  - **isExternal** `boolean,null`: No description
  - **platformCreatedAt** `string,null` (date-time): No description

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

#### 403: Ads access required (Ads add-on on legacy plans, included on usage-based plans).

---

## POST /v1/ads/ad-sets

**Create a standalone ad group**

Google Ads compliance row C.190: creates an ad group WITHOUT an ad,
under an existing campaign. Ads join it later via `adSetId`
on POST /v1/ads/create. Google only; every other platform returns 501.

Created `PAUSED` unless `status: ACTIVE`. The new ad group has no ad
yet, so it will not appear in GET /v1/ads/tree (built purely from `ads`
rows) until one is added; use GET /v1/ads/ad-sets to see it in the
meantime.

**Idempotency:** send an `Idempotency-Key` header to make retries safe.

### Parameters

- **Idempotency-Key** (optional) in header: Optional client-generated unique key (e.g. a UUID) that makes retries safe. Same key + same body replays the original response; same key + different body → 422; key still processing → 409. Only 2xx responses are stored, so a request that failed with a 4xx can be retried with a corrected body under the SAME key.

### Request Body

- **accountId** (required) `string`: Zernio SocialAccount id owning the Google Ads connection.
- **platform** (required) `string`: Only "google" is implemented today; every other value returns 501. - one of: facebook, instagram, tiktok, linkedin, pinterest, google, twitter, openai
- **campaignId** (required) `string`: Google platform campaign ID (numeric) the ad group is created under.
- **name** (required) `string`: No description
- **status** `string`: No description - one of: ACTIVE, PAUSED
- **customerId** `string`: Numeric Google Ads customer id. Only required when the connection has more than one.

### Responses

#### 201: Ad group created

**Response Body:**

- **adSetId** `string`: Platform id of the new ad group
- **campaignId** `string`: No description

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

#### 403: Returned with code `ads_allowance_exceeded` when the team has no payment method on file and has reached the 500 free live ads: add a card to resume.

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

#### 501: Only supported on Google Ads

---
