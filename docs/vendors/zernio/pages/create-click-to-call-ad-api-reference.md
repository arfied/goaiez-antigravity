# Create Click-to-Call ad API Reference

Same shape and flow as POST /v1/ads/ctwa, but the CTA is CALL_NOW dialing `phoneNumber`
via a tel: link. The ad set is destination_type PHONE_CALL optimizing QUALITY_CALL
and the campaign objective defaults to OUTCOME_LEADS.
Supports the same single-creative and multi-creative shapes as CTWA.

## POST /v1/ads/call

**Create Click-to-Call ad**

Same shape and flow as POST /v1/ads/ctwa, but the CTA is CALL_NOW dialing `phoneNumber`
via a tel: link. The ad set is destination_type PHONE_CALL optimizing QUALITY_CALL
and the campaign objective defaults to OUTCOME_LEADS.
Supports the same single-creative and multi-creative shapes as CTWA.

### Request Body


### Responses

#### 201: Ad(s) created and submitted for review. The route shares its handler with
`POST /v1/ads/ctwa`, so the body is the same tagged union discriminated by
`adType`: `single` carries `{ adType, ad, message }`, and `multi` carries
`{ adType, ads, platformCampaignId, platformAdSetId, message }`.


**Response Body:**

*One of the following:*
- `CtwaSingleResponse`
- `CtwaMultiResponse`

#### 400: Invalid input

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

#### 422: No Facebook Page resolved for the account

#### 502: Meta accepted the request then failed to produce the media (upload session, chunk transfer, processing timeout, or a response with no image hash). Inspect `platformError.reason`.

---
