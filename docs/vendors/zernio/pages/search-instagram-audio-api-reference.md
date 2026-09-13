# Search Instagram audio API Reference

Search Instagram's audio catalog (licensed music or original sounds),
or list what is currently trending by omitting `q`. Returns up to ~30
assets; Meta exposes no pagination on this edge.

Pass the returned `audioId` as
`platformSpecificData.audioConfiguration.audioId` when creating a Reel
to publish it with that track.

Requires an Instagram account connected via **Facebook Login**. Meta
hosts this catalog on graph.facebook.com only, so accounts connected
with classic Instagram Login receive a 400
(`instagram_audio_requires_facebook_login`) and must be reconnected
choosing the Facebook option.


## GET /v1/accounts/{accountId}/instagram/audio

**Search Instagram audio**

Search Instagram's audio catalog (licensed music or original sounds),
or list what is currently trending by omitting `q`. Returns up to ~30
assets; Meta exposes no pagination on this edge.

Pass the returned `audioId` as
`platformSpecificData.audioConfiguration.audioId` when creating a Reel
to publish it with that track.

Requires an Instagram account connected via **Facebook Login**. Meta
hosts this catalog on graph.facebook.com only, so accounts connected
with classic Instagram Login receive a 400
(`instagram_audio_requires_facebook_login`) and must be reconnected
choosing the Facebook option.


### Parameters

- **accountId** (required) in path: The ID of the Instagram account
- **audioType** (required) in query: Catalog to search: licensed music or original sounds from Reels.
- **q** (optional) in query: Search keywords. Omit to get the current trending list.

### Responses

#### 200: Matching audio assets (may be empty)

**Response Body:**

- **audio** `array[InstagramAudioAsset]`: 

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

#### 502: Instagram rejected the request

---
