# Delete an ad video API Reference

Removes a video from the ad account's video library. Meta's canonical
`DELETE /{video_id}` fails with code 10 / subcode 1363055 for videos uploaded via
`/act_X/advideos` even with `ads_management`; this endpoint uses the working
account-scoped shape `DELETE /act_X/advideos?video_id=<id>` and returns Meta's
`{success: true}` verbatim. Deleting a video that lives in a different ad account,
or that Meta has already removed, returns Meta's error verbatim as a 4xx.

## DELETE /v1/ads/videos/{videoId}

**Delete an ad video**

Removes a video from the ad account's video library. Meta's canonical
`DELETE /{video_id}` fails with code 10 / subcode 1363055 for videos uploaded via
`/act_X/advideos` even with `ads_management`; this endpoint uses the working
account-scoped shape `DELETE /act_X/advideos?video_id=<id>` and returns Meta's
`{success: true}` verbatim. Deleting a video that lives in a different ad account,
or that Meta has already removed, returns Meta's error verbatim as a 4xx.

### Parameters

- **videoId** (required) in path: Meta ad video id (numeric).
- **accountId** (required) in query: Zernio SocialAccount id (posting or ads variant) used to resolve the Meta token.
- **adAccountId** (required) in query: Meta ad account id (act_<n>) that owns the video.

### Responses

#### 200: Video deleted

**Response Body:**

- **adAccountId** `string`: No description
- **videoId** `string`: No description
- **success** `boolean`: No description

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

#### 501: Only supported on Meta (facebook/instagram)

---
