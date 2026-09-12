# Update location details API Reference

Updates Google Business Profile location details. The updateMask field is required and specifies which fields to update.
This endpoint proxies Google's Business Information API locations.patch, so any valid updateMask field is supported.
Common fields: regularHours, specialHours, profile.description, websiteUri, phoneNumbers, categories, serviceItems.


## GET /v1/accounts/{accountId}/gmb-location-details

**Get location details**

Returns detailed Google Business Profile location info (hours, description, phone, website, categories, services). Use readMask to request specific fields.

### Parameters

- **accountId** (required) in path: The Zernio account ID (from /v1/accounts)
- **locationId** (optional) in query: Override which location to query. If omitted, uses the account's selected location. Use GET /gmb-locations to list valid IDs.
- **readMask** (optional) in query: Comma-separated fields to return. Available: name, title, phoneNumbers, categories, storefrontAddress, websiteUri, regularHours, specialHours, serviceArea, serviceItems, profile, openInfo, metadata, moreHours.
`title` and `metadata` are always included in the response so the `location` summary block can be populated, even if you omit them here.
Note: `location` is a derived response field, not a Google readMask value, passing it returns 400.


### Responses

#### 200: Location details fetched successfully

**Response Body:**

- **success** `boolean`: No description
- **accountId** `string`: No description
- **locationId** `string`: No description
- **location** `object,null`: Compact public-facing summary derived from Google's `metadata`. Useful
for surfacing the "leave a review" URL (e.g. behind a QR code) without
parsing the raw block. Always populated regardless of readMask.
For unverified or new locations Google omits placeId/reviewUrl/mapsUri,
so those return as null and `isVerified` is false.

- **title** `string`: Business name
- **regularHours** `object`: 
  - **periods** `array[object]`: 
    - **openDay** `string`: No description - one of: MONDAY, TUESDAY, WEDNESDAY, THURSDAY, FRIDAY, SATURDAY, SUNDAY
    - **openTime** `string`: Opening time in HH:MM format
    - **closeDay** `string`: No description
    - **closeTime** `string`: No description
- **specialHours** `object`: 
  - **specialHourPeriods** `array[object]`: 
    - **startDate** `object`: 
      - **year** `integer`: No description
      - **month** `integer`: No description
      - **day** `integer`: No description
    - **endDate** `object`: 
      - **year** `integer`: No description
      - **month** `integer`: No description
      - **day** `integer`: No description
    - **openTime** `string`: No description
    - **closeTime** `string`: No description
    - **closed** `boolean`: No description
- **profile** `object`: 
  - **description** `string`: Business description
- **websiteUri** `string`: No description
- **phoneNumbers** `object`: 
  - **primaryPhone** `string`: No description
  - **additionalPhones** `array[string]`: 
- **categories** `object`: Business categories (returned when readMask includes 'categories')
  - **primaryCategory** `object`: 
    - **name** `string`: Category resource name
    - **displayName** `string`: Human-readable category name
  - **additionalCategories** `array[object]`: 
    - **name** `string`: No description
    - **displayName** `string`: No description
- **serviceItems** `array[object]`: Services offered (returned when readMask includes 'serviceItems')
  - **structuredServiceItem** `object`: 
    - **serviceTypeId** `string`: No description
    - **description** `string`: No description
  - **freeFormServiceItem** `object`: 
    - **category** `string`: No description
    - **label** `object`: 
      - **displayName** `string`: No description
      - **languageCode** `string`: No description
  - **price** `object`: 
    - **currencyCode** `string`: No description
    - **units** `string`: No description
    - **nanos** `integer`: No description

#### 400: Invalid request. Most commonly raised when the readMask query
includes a value that is not a valid Google Business Information
field (e.g. `location`, which is a response-only derived field).


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

#### 401: Unauthorized or token expired

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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## PUT /v1/accounts/{accountId}/gmb-location-details

**Update location details**

Updates Google Business Profile location details. The updateMask field is required and specifies which fields to update.
This endpoint proxies Google's Business Information API locations.patch, so any valid updateMask field is supported.
Common fields: regularHours, specialHours, profile.description, websiteUri, phoneNumbers, categories, serviceItems.


### Parameters

- **accountId** (required) in path: The Zernio account ID (from /v1/accounts)
- **locationId** (optional) in query: Override which location to target. If omitted, uses the account's selected location. Use GET /gmb-locations to list valid IDs.

### Request Body

- **updateMask** (required) `string`: Required. Comma-separated fields to update (e.g. 'regularHours', 'specialHours', 'profile.description', 'categories', 'serviceItems'). Any valid Google Business Information API updateMask field is supported.
- **regularHours** `object`: No description
- **specialHours** `object`: No description
- **profile** `object`: No description
- **websiteUri** `string`: No description
- **phoneNumbers** `object`: No description
- **categories** `object`: Primary and additional business categories. Use updateMask='categories' to update.
- **serviceItems** `array`: Services offered by the business. Use updateMask='serviceItems' to update.
- **title** `string`: Business name. Use updateMask='title'.
- **storeCode** `string`: External store identifier, unique within the account. Use updateMask='storeCode'.
- **labels** `array`: Free-form, internal-only labels for grouping (1-255 characters each). Use updateMask='labels'.
- **storefrontAddress** `object`: Postal address of the storefront. Use updateMask='storefrontAddress'. Omit for service-area-only businesses.
- **serviceArea** `object`: Areas the business serves. Use updateMask='serviceArea'. Full replacement: send every place you want to keep.
- **openInfo** `object`: Open/closed status of the location. Use updateMask='openInfo'.
- **moreHours** `array`: Additional hours for specific services (delivery, drive-through, etc.). Use updateMask='moreHours'.
- **latlng** `object`: Precise coordinates. Use updateMask='latlng'. Google restricts latlng writes to approved clients, so this update may be silently ignored or rejected.
- **adWordsLocationExtensions** `object`: Alternate phone shown in Google Ads location extensions. Use updateMask='adWordsLocationExtensions'.

### Responses

#### 200: Location updated successfully

**Response Body:**

- **success** `boolean`: No description
- **accountId** `string`: No description
- **locationId** `string`: No description

#### 400: Invalid request or missing updateMask

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

#### 401: Unauthorized or token expired

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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---
