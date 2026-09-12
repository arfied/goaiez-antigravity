# Create a registration share link API Reference

Creates a single-use, expiring link (valid 7 days) that lets someone
else (whoever has the legal business details) fill in the carrier
registration form for one of your numbers, without a Zernio login. The
registration is created under your account once the form is submitted.


## POST /v1/sms/registrations/share

**Create a registration share link**

Creates a single-use, expiring link (valid 7 days) that lets someone
else (whoever has the legal business details) fill in the carrier
registration form for one of your numbers, without a Zernio login. The
registration is created under your account once the form is submitted.


### Request Body

- **numberId** (required) `string`: Your phone number's ID (from GET /v1/phone-numbers).

### Responses

#### 200: Share link created.

**Response Body:**

- **url** `string`: No description
- **expiresAt** `string` (date-time): No description

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

#### 404: Number not found

---
