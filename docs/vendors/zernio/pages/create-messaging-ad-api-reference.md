# Create messaging ad API Reference

Creates a click-to-message ad; `destination` selects where the tapped ad opens a
conversation: WhatsApp, the Page's Messenger inbox or the linked Instagram account's Direct inbox.
The ad set is created with the matching destination_type and
CONVERSATIONS optimization; the campaign objective defaults to OUTCOME_ENGAGEMENT.
Supports single-creative and multi-creative shapes. Supersedes POST /v1/ads/ctwa
(deprecated, equivalent to `destination: whatsapp`).
Existing posts and reels are supported through `existingPostId` or
`objectStoryId`, either per creative or at the top level. Omit fresh
media and copy for that creative. Optional `whatsappPhoneNumber` selects
a number already paired with the Page (WhatsApp destination only).

## POST /v1/ads/messaging

**Create messaging ad**

Creates a click-to-message ad; `destination` selects where the tapped ad opens a
conversation: WhatsApp, the Page's Messenger inbox or the linked Instagram account's Direct inbox.
The ad set is created with the matching destination_type and
CONVERSATIONS optimization; the campaign objective defaults to OUTCOME_ENGAGEMENT.
Supports single-creative and multi-creative shapes. Supersedes POST /v1/ads/ctwa
(deprecated, equivalent to `destination: whatsapp`).
Existing posts and reels are supported through `existingPostId` or
`objectStoryId`, either per creative or at the top level. Omit fresh
media and copy for that creative. Optional `whatsappPhoneNumber` selects
a number already paired with the Page (WhatsApp destination only).

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
