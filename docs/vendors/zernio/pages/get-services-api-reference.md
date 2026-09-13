# Get services API Reference

Gets the services offered by a Google Business Profile location.
Returns an array of service items (structured or free-form with optional price).


## GET /v1/accounts/{accountId}/gmb-services

**Get services**

Gets the services offered by a Google Business Profile location.
Returns an array of service items (structured or free-form with optional price).


### Parameters

- **accountId** (required) in path: No description
- **locationId** (optional) in query: Override which location to query. If omitted, uses the account's selected location.

### Responses

#### 200: Services fetched successfully

**Response Body:**

- **success** `boolean`: No description
- **accountId** `string`: No description
- **locationId** `string`: No description
- **services** `array[object]`: 
  - **structuredServiceItem** `object`: 
    - **serviceTypeId** `string`: No description
    - **description** `string`: No description
  - **freeFormServiceItem** `object`: 
    - **category** `string`: No description
    - **label** `object`: 
      - **displayName** `string`: No description
      - **description** `string`: No description
  - **price** `object`: 
    - **currencyCode** `string`: No description (example: "USD")
    - **units** `string`: No description (example: "50")
    - **nanos** `integer`: No description

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

## PUT /v1/accounts/{accountId}/gmb-services

**Replace services**

Replaces the entire service list for a location.
Google's API requires full replacement; individual item updates are not supported.
Each service can be structured (using a predefined serviceTypeId) or free-form (custom label).


### Parameters

- **accountId** (required) in path: No description
- **locationId** (optional) in query: Override which location to target. If omitted, uses the account's selected location.

### Request Body

- **serviceItems** (required) `array`: No description

### Responses

#### 200: Services updated successfully

**Response Body:**

- **success** `boolean`: No description
- **services** `array[object]`: 
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
