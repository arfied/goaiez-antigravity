# Move a number to another profile API Reference

Move a provisioned number to a different profile.

A number is not a single record. Alongside the number itself there are
hidden telephony owner accounts (platform `phone`, plus `sms` when SMS is
enabled) and, once WhatsApp is connected, the `whatsapp` account. They all
carry a profileId and this endpoint moves them together.

Use this instead of `PATCH /v1/accounts/{accountId}`: that one moves the
account only and leaves the number itself pinned to its original
profile, which splits the number across two profiles. Connecting a
Zernio-provisioned number from any profile but its own is rejected with a
`409` (`WHATSAPP_NUMBER_PINNED_TO_PROFILE`). This endpoint is how you
re-home the number first, so it can then be connected from the new profile.

`id` is the number record id from `GET /v1/phone-numbers`, not an account id.

A profile holds at most one account per platform, so the destination must be
free of every platform this number occupies.


## PATCH /v1/whatsapp/phone-numbers/{id}/profile

**Move a number to another profile**

Move a provisioned number to a different profile.

A number is not a single record. Alongside the number itself there are
hidden telephony owner accounts (platform `phone`, plus `sms` when SMS is
enabled) and, once WhatsApp is connected, the `whatsapp` account. They all
carry a profileId and this endpoint moves them together.

Use this instead of `PATCH /v1/accounts/{accountId}`: that one moves the
account only and leaves the number itself pinned to its original
profile, which splits the number across two profiles. Connecting a
Zernio-provisioned number from any profile but its own is rejected with a
`409` (`WHATSAPP_NUMBER_PINNED_TO_PROFILE`). This endpoint is how you
re-home the number first, so it can then be connected from the new profile.

`id` is the number record id from `GET /v1/phone-numbers`, not an account id.

A profile holds at most one account per platform, so the destination must be
free of every platform this number occupies.


### Parameters

- **id** (required) in path: WhatsAppPhoneNumber id.

### Request Body

- **profileId** (required) `string`: Destination profile id. Must belong to the same team.

### Responses

#### 200: Number moved, or already on that profile.

**Response Body:**

- **message** `string`: No description
- **profileId** `string`: The profile the number is now on.
- **movedPlatforms** `array[string]`: Platforms whose accounts travelled with the number (phone, sms, whatsapp). Absent when the number was already on the destination profile.

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

#### 403: No access to the source or destination profile, or the Inbox add-on is not active.

#### 404: Number not found, or the destination profile does not exist.

#### 409: The destination profile already holds an account on one of the platforms this number occupies.

---
