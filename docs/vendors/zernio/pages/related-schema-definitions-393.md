# Related Schema Definitions

## TrackingTag

A platform measurement tag: the thing you create, install on a
website, send events to, and target ads against. On Meta this is a
Pixel (`kind: pixel`). The shape is platform-neutral so other platforms
(Pinterest Tag, LinkedIn Insight Tag, etc.) can be added without
changing the contract; platform-specific fields are absent where
a platform has no equivalent. Returned by `listTrackingTags`,
`createTrackingTag`, `getTrackingTag`, and `updateTrackingTag`.


### Properties

- **id** (required) `string`: Platform-native tag id. Meta: numeric pixel id, as a string.
- **name** (required) `string`: No description
- **platform** (required) `string`: No description - one of: metaads
- **kind** (required) `string`: Platform-native flavor of the tag (Meta: `pixel`). - one of: pixel, tag, insight_tag
- **status** (required) `string`: `inactive` when the platform reports the tag as broken/unavailable. - one of: active, inactive
- **code** `string`: The base-code `<script>` snippet to install on the site. Meta only;
populated by `getTrackingTag`, omitted from the list view.

- **lastFiredTime** `integer,null`: Unix seconds of the last event the tag received, or `null` if it
never fired. The practical "is it installed and working" signal.

- **isUnavailable** `boolean`: Whether the tag is in a broken/unavailable state (Meta `is_unavailable`).
- **installed** `boolean`: Convenience flag derived from `lastFiredTime`: has the tag ever fired.
- **creationTime** `integer`: Unix seconds the tag was created.
- **ownerBusinessId** `string,null`: Business Manager id that owns the tag, or `null` when the tag lives
on a personal (non-BM) ad account. Such tags can't be shared with
other ad accounts.

- **ownerAdAccountId** `string`: Ad account id (`act_...`) that owns the tag, when reported.

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
