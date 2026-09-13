# Adjust uploaded conversions API Reference

Adjust conversions that were previously uploaded via `POST /v1/ads/conversions`:
retract them, restate their value, or enhance them with first-party data. Requires
the Ads add-on.

**Google Ads only.** Google handles adjustments through the classic Google Ads API
(`ConversionAdjustmentUploadService`); the Data Manager `ingestEvents` path used for
sending conversions is ingest-only. Meta and LinkedIn have no equivalent, so this
endpoint returns `405` for those platforms.

Adjustment types:

- `RETRACTION`: remove the conversion entirely (refund, chargeback, cancelled order, churn).
- `RESTATEMENT`: change the conversion's value (upgrade / downgrade / partial refund). Send the corrected **total** value in `restatementValue` (not a delta).
- `ENHANCEMENT`: attach first-party identifiers (hashed email / phone) to an existing conversion (enhanced conversions applied after the fact).

Identifying the original conversion (per adjustment):

- `orderId`: the transaction ID you sent as `eventId` on the original conversion. Recommended, and **required** for `ENHANCEMENT`.
- or `gclid` + `conversionTime`: the click ID and the original conversion's time (unix seconds). Not available for `ENHANCEMENT`.

`destinationId` is the conversion action resource name, e.g.
`customers/1234567890/conversionActions/987654321` (same value you send to
`POST /v1/ads/conversions`). PII in `user` is hashed with SHA-256 server-side
(Gmail-specific normalization included). Send plaintext.

Times are unix seconds; we convert to Google's required
`yyyy-MM-dd HH:mm:ss+00:00` format. Up to 2000 adjustments per request; partial
failure is supported (inspect `adjustmentsFailed` / `failures[]`).


## POST /v1/ads/conversions/adjustments

**Adjust uploaded conversions**

Adjust conversions that were previously uploaded via `POST /v1/ads/conversions`:
retract them, restate their value, or enhance them with first-party data. Requires
the Ads add-on.

**Google Ads only.** Google handles adjustments through the classic Google Ads API
(`ConversionAdjustmentUploadService`); the Data Manager `ingestEvents` path used for
sending conversions is ingest-only. Meta and LinkedIn have no equivalent, so this
endpoint returns `405` for those platforms.

Adjustment types:

- `RETRACTION`: remove the conversion entirely (refund, chargeback, cancelled order, churn).
- `RESTATEMENT`: change the conversion's value (upgrade / downgrade / partial refund). Send the corrected **total** value in `restatementValue` (not a delta).
- `ENHANCEMENT`: attach first-party identifiers (hashed email / phone) to an existing conversion (enhanced conversions applied after the fact).

Identifying the original conversion (per adjustment):

- `orderId`: the transaction ID you sent as `eventId` on the original conversion. Recommended, and **required** for `ENHANCEMENT`.
- or `gclid` + `conversionTime`: the click ID and the original conversion's time (unix seconds). Not available for `ENHANCEMENT`.

`destinationId` is the conversion action resource name, e.g.
`customers/1234567890/conversionActions/987654321` (same value you send to
`POST /v1/ads/conversions`). PII in `user` is hashed with SHA-256 server-side
(Gmail-specific normalization included). Send plaintext.

Times are unix seconds; we convert to Google's required
`yyyy-MM-dd HH:mm:ss+00:00` format. Up to 2000 adjustments per request; partial
failure is supported (inspect `adjustmentsFailed` / `failures[]`).


### Request Body

- **accountId** (required) `string`: SocialAccount ID. Must be a `googleads` account.
- **destinationId** (required) `string`: Conversion action resource name, e.g. `customers/1234567890/conversionActions/987654321`.
- **adjustments** (required) `array`: No description

### Responses

#### 200: Adjustments processed. Inspect `adjustmentsFailed` and `failures[]` for
partial failure (Google reports per-row errors via partial failure).


**Response Body:**

- **platform** `string`: No description - one of: googleads
- **adjustmentsReceived** `integer`: Adjustments accepted by Google.
- **adjustmentsFailed** `integer`: Adjustments rejected (see failures).
- **failures** `array[object]`: 
  - **adjustmentIndex** `integer`: Index into the submitted adjustments array.
  - **message** `string`: No description
  - **code**: One of multiple types
- **traceId** `string`: No description

#### 400: Invalid body, or a malformed adjustment (missing key, missing restatementValue for RESTATEMENT, missing identifiers for ENHANCEMENT).

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required (Ads add-on on legacy plans, included on usage-based plans).

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

#### 405: Conversion adjustments are only available for Google Ads (the account's platform is not `googleads`).

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

---
