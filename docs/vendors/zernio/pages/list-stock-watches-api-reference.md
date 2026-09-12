# List stock watches API Reference



## POST /v1/phone-numbers/stock-watches

**Watch an out-of-stock country**

Get notified the first time an out-of-stock country has deliverable
numbers again: an email to the account holder plus the
`phone_number.stock_available` webhook. Stock is re-checked every 6h.
One watch per country and number type; a repeat request returns the
existing watch (200). The watch is consumed when it fires, so re-create
it if you miss the stock. Up to 20 watches at once.

Countries and types marked `fulfilment: request` by
GET /v1/phone-numbers/countries can also be watched, but anything with
`preOrderable: true` does not need a watch: submit KYC and the carrier
sources the number to order.


### Request Body

- **country** (required) `string`: ISO 3166-1 alpha-2 code of a country listed by GET /v1/phone-numbers/countries.
- **numberType** `string`: Narrow the watch to one number type. Omit to be notified when any type in the country is back. - one of: local, mobile, national, toll_free

### Responses

#### 200: A watch for this country and type already existed; returned unchanged.

**Response Body:**

- **id** (required) `string`: No description
- **country** (required) `string`: ISO 3166-1 alpha-2.
- **countryName** (required) `string`: No description
- **numberType** (required) `string,null`: The watched number type, or null when the watch covers every type in the country. - one of: local, mobile, national, toll_free, 
- **createdAt** (required) `string` (date-time): No description

#### 201: Watch created.

**Response Body:**

- **id** (required) `string`: No description
- **country** (required) `string`: ISO 3166-1 alpha-2.
- **countryName** (required) `string`: No description
- **numberType** (required) `string,null`: The watched number type, or null when the watch covers every type in the country. - one of: local, mobile, national, toll_free, 
- **createdAt** (required) `string` (date-time): No description

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

#### 409: The country is in stock right now (buy instead of watching), or the 20-country watch limit is reached (code invalid_resource_state).

---

## GET /v1/phone-numbers/stock-watches

**List stock watches**

### Responses

#### 200: The caller's active watches, oldest first.

**Response Body:**

- **watches** `array[PhoneNumberStockWatch]`: 

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---
