# Get Instagram story insights API Reference

Returns metrics for a single story. The `source` field discriminates
between three states:

- `live`: fetched from Meta in real time (story is still active)
- `cached`: fetched from a persisted `story_insights` webhook payload
  (story has expired but we received its final-state metrics from Meta)
- `unavailable`: story has expired and we never received its webhook
  payload (for example, the account connected after the story expired)

Meta can report an expired story as an empty successful result rather
than an error, so an expired story resolves to `cached` or `unavailable`
even though the upstream request itself succeeded.

Field semantics follow Meta's API. Counts below 5 may be returned as 0
due to Meta's privacy floor on small audiences. The `navigation` field
is the sum of `tapsForward + tapsBack + exits + swipesForward`.


## GET /v1/accounts/{accountId}/instagram/stories/{storyId}/insights

**Get Instagram story insights**

Returns metrics for a single story. The `source` field discriminates
between three states:

- `live`: fetched from Meta in real time (story is still active)
- `cached`: fetched from a persisted `story_insights` webhook payload
  (story has expired but we received its final-state metrics from Meta)
- `unavailable`: story has expired and we never received its webhook
  payload (for example, the account connected after the story expired)

Meta can report an expired story as an empty successful result rather
than an error, so an expired story resolves to `cached` or `unavailable`
even though the upstream request itself succeeded.

Field semantics follow Meta's API. Counts below 5 may be returned as 0
due to Meta's privacy floor on small audiences. The `navigation` field
is the sum of `tapsForward + tapsBack + exits + swipesForward`.


### Parameters

- **accountId** (required) in path: The Instagram account ID
- **storyId** (required) in path: The Instagram media ID of the story.

### Responses

#### 200: Story insights

**Response Body:**

- **data** (required) `object`: 
  - **source** (required) `string`: No description - one of: live, cached, unavailable
  - **metrics** (required) `object`: 
    - **views** (required) `integer`: Total story plays. Replaces deprecated 'impressions' for media created after 2024-07-02.
    - **reach** (required) `integer`: Unique accounts that saw the story.
    - **replies** (required) `integer`: DMs sent in reply to the story.
    - **shares** (required) `integer`: No description
    - **navigation** (required) `integer`: Total nav actions (tapsForward + tapsBack + exits + swipesForward).
    - **tapsForward** (required) `integer`: Tapped right to next slide of SAME story.
    - **tapsBack** (required) `integer`: Tapped left to previous slide.
    - **exits** (required) `integer`: Closed Stories interface entirely.
    - **swipesForward** (required) `integer`: Swiped left to next account's story.
    - **profileVisits** (required) `integer`: No description
    - **follows** (required) `integer`: No description
    - **reposts** (required) `integer`: No description
    - **totalInteractions** (required) `integer`: No description

#### 400: Invalid request.

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

#### 502: Instagram rejected the request.

---
