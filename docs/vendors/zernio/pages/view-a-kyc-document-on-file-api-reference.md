# View a KYC document on file API Reference

Stream a document backing a reusable verification (the `documentId`
values from GET /v1/phone-numbers/kyc `reusable.options[].details[]`), so
the account holder can see what's on file before reusing it. Returned
inline as `application/pdf` (uploads are normalized to PDF). Auth-scoped:
a document is viewable only when its id is referenced by one of the
caller's own numbers. Otherwise `404`.


## GET /v1/phone-numbers/kyc/document/{documentId}

**View a KYC document on file**

Stream a document backing a reusable verification (the `documentId`
values from GET /v1/phone-numbers/kyc `reusable.options[].details[]`), so
the account holder can see what's on file before reusing it. Returned
inline as `application/pdf` (uploads are normalized to PDF). Auth-scoped:
a document is viewable only when its id is referenced by one of the
caller's own numbers. Otherwise `404`.


### Parameters

- **documentId** (required) in path: The Telnyx document id (from `reusable.options[].details[].documentId`).

### Responses

#### 200: The document, streamed inline.

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

#### 404: No such document for this account.

---
