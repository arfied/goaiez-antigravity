# Country porting requirements API Reference

The country-specific information a port-in needs BEYOND the LOA,
invoice, and account/address details, such as an ID copy, proof of
address, a tax id, or a porting code. Call it after the portability
check (which returns each number's `countryCode` and
`phoneNumberType`), render the fields, and pass the collected values as
the create request's `requirements`. US/CA return an empty list.


## GET /v1/phone-numbers/port-in/requirements

**Country porting requirements**

The country-specific information a port-in needs BEYOND the LOA,
invoice, and account/address details, such as an ID copy, proof of
address, a tax id, or a porting code. Call it after the portability
check (which returns each number's `countryCode` and
`phoneNumberType`), render the fields, and pass the collected values as
the create request's `requirements`. US/CA return an empty list.


### Parameters

- **country** (required) in query: ISO country of the numbers being ported (a supported port-in country).
- **numberType** (optional) in query: The portability check's phoneNumberType. Requirements differ by type.

### Responses

#### 200: Requirement fields for the country/type combination.

**Response Body:**

- **country** `string`: No description
- **numberType** `string`: No description
- **supported** `boolean`: false when the combination includes a step that can't be completed through the API (e.g. an in-person identity verification). Porting it needs support.
- **fields** `array[object]`: 
  - **requirementId** `string`: Pass back as requirements[].requirementTypeId.
  - **label** `string`: No description
  - **kind** `string`: text/date take a string value; file takes a documentId from the documents endpoint; address is satisfied automatically from the end-user service address. - one of: text, date, address, file, action
  - **description** `string`: No description
  - **example** `string`: No description
  - **acceptableValues** `array[string]`: When present, the value must be one of these.

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

#### 422: Country not supported for port-in

---
