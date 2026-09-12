# List lead conversations API Reference

Conversation entries of one Local Services lead: phone calls (duration,
recording URL) and messages (text, attachment URLs), oldest first. Read
live from `local_services_lead_conversation`, always scoped to a single
lead. Call-recording URLs require read access on the Google Ads account.
Draws on the shared Google Ads operations budget.

## GET /v1/ads/local-services/leads/{leadId}/conversations

**List lead conversations**

Conversation entries of one Local Services lead: phone calls (duration,
recording URL) and messages (text, attachment URLs), oldest first. Read
live from `local_services_lead_conversation`, always scoped to a single
lead. Call-recording URLs require read access on the Google Ads account.
Draws on the shared Google Ads operations budget.

### Parameters

- **leadId** (required) in path: Numeric lead id from /v1/ads/local-services/leads.
- **accountId** (required) in query: Google ads SocialAccount id.
- **customerId** (optional) in query: Numeric Google Ads customer id (no dashes). Defaults to the account's connected customer.
- **pageToken** (optional) in query: Cursor from paging.nextPageToken of the previous page.

### Responses

#### 200: Lead conversations

**Response Body:**

- **customerId** `string`: No description
- **data** `array[object]`: 
  - **id** `string,null`: No description
  - **channel** `string,null`: PHONE_CALL / MESSAGE / SMS / EMAIL / WHATSAPP / ADS_API.
  - **participantType** `string,null`: ADVERTISER or CONSUMER.
  - **eventDateTime** `string,null`: No description
  - **phoneCall** `object,null`: Only on PHONE_CALL entries.
  - **message** `object,null`: Only on message-channel entries.
- **paging** `object`: 
  - **nextPageToken** `string,null`: Null when the last page was returned.

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

#### 429: Google Ads operations budget exhausted; retry later.

#### 501: Only available on Google Ads accounts

---
