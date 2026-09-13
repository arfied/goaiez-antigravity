# A port-in order's pending requirements API Reference

The live requirements on an EXISTING porting order: which are filled,
which are still pending, and which bounced on review
(`requirement-info-exception`). Use it to fix and resubmit a rejected
international port. Same field shape as the country-level requirements
endpoint, plus per-requirement status.


## GET /v1/phone-numbers/port-in/{id}/requirements

**A port-in order's pending requirements**

The live requirements on an EXISTING porting order: which are filled,
which are still pending, and which bounced on review
(`requirement-info-exception`). Use it to fix and resubmit a rejected
international port. Same field shape as the country-level requirements
endpoint, plus per-requirement status.


### Parameters

- **id** (required) in path: Porting order ID (from the port-in list).

### Responses

#### 200: The order's requirements with statuses.

**Response Body:**

- **country** `string`: No description
- **requirements** `array[object]`: 
  - **requirementId** `string`: No description
  - **label** `string`: No description
  - **kind** `string`: No description - one of: text, date, address, file, action
  - **description** `string`: No description
  - **example** `string`: No description
  - **acceptableValues** `array[string]`: 
  - **status** `string`: requirement-info-pending | requirement-info-under-review | requirement-info-exception | approved
  - **filled** `boolean`: No description

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

#### 404: Porting order not found

---
