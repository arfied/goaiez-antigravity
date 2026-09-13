# Get upload URL API Reference

Get a presigned URL to upload files directly to cloud storage (up to 5GB). Returns an uploadUrl and publicUrl. PUT your file to the uploadUrl, then use the publicUrl in your posts.

## POST /v1/media/presign

**Get upload URL**

Get a presigned URL to upload files directly to cloud storage (up to 5GB). Returns an uploadUrl and publicUrl. PUT your file to the uploadUrl, then use the publicUrl in your posts.

### Request Body

- **filename** (required) `string`: Name of the file to upload
- **contentType** (required): No description
- **size** `integer`: Optional file size in bytes for pre-validation (max 5GB)

### Responses

#### 200: Presigned URL generated successfully

**Response Body:**

- **uploadUrl** `string` (uri): Presigned URL to PUT your file to (expires in 1 hour)
- **publicUrl** `string` (uri): Public URL where the file will be accessible after upload
- **key** `string`: Storage key/path of the file
- **expiresIn** `integer`: Seconds until the presigned uploadUrl expires (always 3600)

#### 400: Invalid request (missing filename, unsupported contentType, or size out of range)

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

---
