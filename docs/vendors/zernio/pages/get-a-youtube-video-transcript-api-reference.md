# Get a YouTube video transcript API Reference

Returns the caption track YouTube already holds for one of the connected channel's own videos, as plain text plus timed cues. Use it instead of downloading and transcribing the video yourself.

Auto-generated (ASR) tracks are included: YouTube serves them to the channel owner, which is what the connected account is. Uploaded tracks win over auto-generated ones when both exist for a language.

Caching: we store the transcript on first read and serve it from there afterwards, so you do not need to cache it yourself. A cached read costs no YouTube quota and does not call YouTube at all. `source` tells you which happened (`youtube` on the first read, `cache` after). Pass `refresh=true` only when the captions actually changed on YouTube, since that re-downloads.

Notes:
- Only videos owned by this connected channel. Anything else returns 404.
- `contentDetails.caption` in YouTube's own API reads `false` on videos that DO have a serving auto-generated track, so it is not a usable availability signal. Call this endpoint and handle the 404.
- YouTube generates auto-captions only for videos with recognisable speech, and can take a few hours after upload to publish them.


## GET /v1/accounts/{accountId}/youtube-captions

**Get a YouTube video transcript**

Returns the caption track YouTube already holds for one of the connected channel's own videos, as plain text plus timed cues. Use it instead of downloading and transcribing the video yourself.

Auto-generated (ASR) tracks are included: YouTube serves them to the channel owner, which is what the connected account is. Uploaded tracks win over auto-generated ones when both exist for a language.

Caching: we store the transcript on first read and serve it from there afterwards, so you do not need to cache it yourself. A cached read costs no YouTube quota and does not call YouTube at all. `source` tells you which happened (`youtube` on the first read, `cache` after). Pass `refresh=true` only when the captions actually changed on YouTube, since that re-downloads.

Notes:
- Only videos owned by this connected channel. Anything else returns 404.
- `contentDetails.caption` in YouTube's own API reads `false` on videos that DO have a serving auto-generated track, so it is not a usable availability signal. Call this endpoint and handle the 404.
- YouTube generates auto-captions only for videos with recognisable speech, and can take a few hours after upload to publish them.


### Parameters

- **accountId** (required) in path: The connected YouTube account.
- **videoId** (required) in query: The YouTube video id (the `platformPostId` on a synced external post).
- **language** (optional) in query: BCP-47 language tag as YouTube labels the track. `en` also matches an `en-GB` track. Omit to take the best available track.
- **format** (optional) in query: `json` returns timed `cues`; `srt` returns the raw SubRip body instead. `text` is present either way.
- **refresh** (optional) in query: Re-download from YouTube instead of serving the stored copy. Spends 200 quota units.

### Responses

#### 200: The transcript.

**Response Body:**

- **accountId** `string`: No description
- **videoId** `string`: No description
- **language** `string`: The language of the returned track.
- **trackId** `string`: YouTube's own caption track id.
- **trackKind** `string`: `asr` is YouTube's auto-generated track; `standard` was uploaded by the channel. - one of: asr, standard
- **source** `string`: `cache` when served from our stored copy, `youtube` when this call spent the quota units. - one of: cache, youtube
- **fetchedAt** `string` (date-time): When the stored copy was downloaded from YouTube.
- **text** `string`: The whole transcript as one paragraph, no timings.
- **cues** `array[object]`: Timed cues. Present when format is json. Auto-generated cues overlap in time by design (captions roll), so `start` can precede the previous cue's `end`.
  - **start** `number`: Seconds from the start of the video.
  - **end** `number`: No description
  - **text** `string`: No description
- **srt** `string`: Raw SubRip body. Present when format is srt.
- **availableTracks** `array[object]`: Every track on the video, so you can re-request another language. On a cached read this is the listing as it stood when we downloaded, so a language added to the video since then appears only after a `refresh=true` or when you request that language directly.
  - **trackId** `string`: No description
  - **language** `string`: No description
  - **trackKind** `string`: No description - one of: asr, standard
  - **name** `string`: The track's display name. Empty for auto-generated tracks.

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

#### 404: Account not found, the video does not belong to this channel (`video_not_found`), or the video has no caption track in the requested language (`captions_not_found`).

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
