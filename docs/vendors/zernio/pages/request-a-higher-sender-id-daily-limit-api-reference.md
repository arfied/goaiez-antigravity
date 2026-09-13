# Request a higher sender ID daily limit API Reference

Asks support to raise the team's daily sender-ID message cap.
There is no self-serve raise: the request (desired cap + use case) is
reviewed manually, usually within a business day.


## POST /v1/sms/sender-ids/limit-request

**Request a higher sender ID daily limit**

Asks support to raise the team's daily sender-ID message cap.
There is no self-serve raise: the request (desired cap + use case) is
reviewed manually, usually within a business day.


### Request Body

- **requestedCap** (required) `integer`: Desired daily message cap. Must exceed the current cap.
- **reason** (required) `string`: Use case and audience (what you send, to whom, opt-in status).

### Responses

#### 200: Request submitted for review.

**Response Body:**

- **requested** `boolean`: No description
- **requestedCap** `integer`: No description

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

#### 409: A cap-raise request is already awaiting review (code `sender_id_raise_pending`); one at a time.

#### 503: Request could not be submitted; retry or contact support.

---
