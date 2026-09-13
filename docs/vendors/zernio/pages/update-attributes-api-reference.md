# Update attributes API Reference

Updates location attributes (amenities, services, etc.).

The attributeMask specifies which attributes to update (comma-separated).


## GET /v1/accounts/{accountId}/gmb-attributes

**Get attributes**

Returns Google Business Profile location attributes (amenities, services, accessibility, payment types). Available attributes vary by business category.

### Parameters

- **accountId** (required) in path: No description
- **locationId** (optional) in query: Override which location to query. If omitted, uses the account's selected location. Use GET /gmb-locations to list valid IDs.

### Responses

#### 200: Attributes fetched successfully

**Response Body:**

- **success** `boolean`: No description
- **accountId** `string`: No description
- **locationId** `string`: No description
- **attributes** `array[object]`: 
  - **name** `string`: Attribute identifier (e.g. has_delivery)
  - **valueType** `string`: Value type (BOOL, ENUM, URL, REPEATED_ENUM)
  - **values** `array[items]`: 
  - **repeatedEnumValue** `object`: 
    - **setValues** `array[string]`: 
    - **unsetValues** `array[string]`: 

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

## PUT /v1/accounts/{accountId}/gmb-attributes

**Update attributes**

Updates location attributes (amenities, services, etc.).

The attributeMask specifies which attributes to update (comma-separated).


### Parameters

- **accountId** (required) in path: No description
- **locationId** (optional) in query: Override which location to target. If omitted, uses the account's selected location. Use GET /gmb-locations to list valid IDs.

### Request Body

- **attributes** (required) `array`: No description
- **attributeMask** (required) `string`: Comma-separated attribute names to update (e.g. 'has_delivery,has_takeout')

### Responses

#### 200: Attributes updated successfully

**Response Body:**

- **success** `boolean`: No description
- **accountId** `string`: No description
- **locationId** `string`: No description
- **attributes** `array[object]`: 
  Type: `object`

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
