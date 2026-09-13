# Get a carrier registration API Reference

Poll this for approval progress after starting a registration.

## DELETE /v1/sms/registrations/{id}

**Deactivate a brand/campaign registration**

Terminates the campaign with the carrier registry so the recurring
monthly campaign fee stops (carriers bill the first 3 months of a
campaign regardless). Numbers covered by it can no longer SEND texts
(receiving is unaffected) until they're registered under a new brand.
Irreversible: a deactivated campaign cannot be restored; texting again
later requires a new registration (new one-time and review fees).
Idempotent.


### Parameters

- **id** (required) in path: No description

### Responses

#### 200: Registration deactivated.

**Response Body:**

- **status** `string`: No description - one of: deactivated

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

---

## GET /v1/sms/registrations/{id}

**Get a carrier registration**

Poll this for approval progress after starting a registration.

### Parameters

- **id** (required) in path: No description

### Responses

#### 200: Registration

**Response Body:**

- **id** `string`: No description
- **registrationType** `string`: No description - one of: standard_10dlc, sole_prop_10dlc, toll_free
- **status** `string`: requested/changes_requested = pre-submission review states; customers see them as pending / needs changes. - one of: pending, approved, rejected, requested, changes_requested, deactivated
- **brandStatus** `string`: No description
- **campaignStatus** `string`: No description
- **declineReason** `string,null`: No description
- **phoneNumbers** `array[string]`: 
- **awaitingOtp** `boolean`: No description
- **campaignContent** `object`: The submitted campaign content, present only for rejected
registrations with a campaign. Edit and resubmit it via the
appeal endpoint's optional content fields.

  - **messageFlow** `string`: No description
  - **sample1** `string`: No description
  - **sample2** `string`: No description

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

---
