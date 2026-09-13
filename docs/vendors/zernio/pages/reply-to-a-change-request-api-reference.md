# Reply to a change request API Reference

Replies to a reviewer change request on a registration in
`changes_requested` state: a note, hosted document URLs (from
`POST /v1/sms/opt-in-proof`), or both, sent together. The registration
returns to `requested` (back in review), and you do not need to resubmit the
whole registration. To change the submitted brand/campaign fields
themselves, resubmit via `POST /v1/sms/registrations` with
`resubmitRequestId` instead.


## POST /v1/sms/registrations/{id}/respond

**Reply to a change request**

Replies to a reviewer change request on a registration in
`changes_requested` state: a note, hosted document URLs (from
`POST /v1/sms/opt-in-proof`), or both, sent together. The registration
returns to `requested` (back in review), and you do not need to resubmit the
whole registration. To change the submitted brand/campaign fields
themselves, resubmit via `POST /v1/sms/registrations` with
`resubmitRequestId` instead.


### Parameters

- **id** (required) in path: No description

### Request Body

- **note** `string`: Answer for the reviewer. Required when no files are sent.
- **files** `array`: Hosted document URLs returned by POST /v1/sms/opt-in-proof.

### Responses

#### 200: Reply recorded; the registration is back in review.

**Response Body:**

- **status** `string`: No description - one of: requested

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

#### 404: Registration not found

#### 409: Registration is not waiting on changes

---
