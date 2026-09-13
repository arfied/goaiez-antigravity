# Related Schema Definitions

## InstagramAudioAsset

One asset from the Instagram audio catalog. Licensed music carries artist/artwork fields; original sounds carry creator fields instead, so most fields are nullable.

### Properties

- **audioId** `string`: Audio asset ID. Pass it as platformSpecificData.audioConfiguration.audioId when creating a Reel.
- **title** `string,null`: Track or sound title.
- **audioType** `string,null`: Catalog type of the asset. - one of: music, original_sound, 
- **durationInMs** `integer,null`: Asset duration in milliseconds.
- **displayArtist** `string,null`: Artist name (licensed music only).
- **coverArtworkThumbnailUrl** `string,null`: Cover artwork thumbnail (licensed music only).
- **downloadUrl** `string,null`: Temporary preview URL. Meta expires it after roughly 1.5 days; re-fetch the asset to refresh it.
- **igUsername** `string,null`: Creator username (original sounds only).
- **profilePictureUrl** `string,null`: Creator profile picture (original sounds only).
- **isAdsEligible** `boolean,null`: Whether the asset is eligible for ads use.
- **onPlatformAudioPreviewLink** `string,null`: Instagram web link to preview the audio.

## ErrorResponse

Canonical error envelope. `error` is the human-readable message; `type`,
`code`, `param`, `platform`, and `platformError` are top-level siblings
for programmatic handling. For upstream platform failures (`type:
platform_error`), `platformError` carries the provider's raw payload
verbatim (for Meta: `error_subcode`, `error_user_title`, `error_user_msg`).


### Properties

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
