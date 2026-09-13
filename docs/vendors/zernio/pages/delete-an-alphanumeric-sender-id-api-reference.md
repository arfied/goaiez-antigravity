# Delete an alphanumeric sender ID API Reference

Deactivates the sender ID so it can no longer send. Re-creating the
same sender ID via `POST /v1/sms/sender-ids` re-activates it.


## DELETE /v1/sms/sender-ids/{id}

**Delete an alphanumeric sender ID**

Deactivates the sender ID so it can no longer send. Re-creating the
same sender ID via `POST /v1/sms/sender-ids` re-activates it.


### Parameters

- **id** (required) in path: Sender ID resource id.

### Responses

#### 200: Sender ID deactivated.

**Response Body:**

- **deleted** `boolean`: No description

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

#### 404: Sender ID not found.

---
