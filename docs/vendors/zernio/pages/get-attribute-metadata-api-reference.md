# Get attribute metadata API Reference

Returns metadata about which Google Business Profile attributes are available for
a location or business category. Use this endpoint to discover valid attribute names,
value types, and allowed enum values before reading or writing via gmb-attributes.

Two mutually exclusive query modes:

**Location mode**: pass `locationId` (or rely on the account's stored `selectedLocationId`).
Google returns attributes valid for that specific location.

**Category mode**: pass `categoryName` (must start with `categories/`) and `regionCode`.
Google returns attributes valid for that category across the given region.
`languageCode` is optional in category mode.

Both modes support `pageSize` and `pageToken` for pagination.


## GET /v1/accounts/{accountId}/gmb-attribute-metadata

**Get attribute metadata**

Returns metadata about which Google Business Profile attributes are available for
a location or business category. Use this endpoint to discover valid attribute names,
value types, and allowed enum values before reading or writing via gmb-attributes.

Two mutually exclusive query modes:

**Location mode**: pass `locationId` (or rely on the account's stored `selectedLocationId`).
Google returns attributes valid for that specific location.

**Category mode**: pass `categoryName` (must start with `categories/`) and `regionCode`.
Google returns attributes valid for that category across the given region.
`languageCode` is optional in category mode.

Both modes support `pageSize` and `pageToken` for pagination.


### Parameters

- **accountId** (required) in path: No description
- **locationId** (optional) in query: Google Business Profile location ID (e.g. "6257659026299438786"). If omitted, uses the account's stored selectedLocationId. Mutually exclusive with categoryName.

- **categoryName** (optional) in query: Category resource name, must start with "categories/" (e.g. "categories/gcid:plumber"). Required together with regionCode. Mutually exclusive with locationId.

- **regionCode** (optional) in query: BCP-47 region code (e.g. "US", "ES"). Required when categoryName is provided.

- **languageCode** (optional) in query: BCP-47 language code for display names (e.g. "en", "es"). Optional when categoryName is provided. Omitted from the Google call when not supplied.

- **pageSize** (optional) in query: Maximum number of attribute metadata items to return. Google defaults to 200.
- **pageToken** (optional) in query: Pagination token from a previous response's nextPageToken field.

### Responses

#### 200: Attribute metadata fetched successfully

**Response Body:**

- **success** `boolean`: No description
- **accountId** `string`: No description
- **locationId** `string`: Only present in location mode.
- **attributeMetadata** `array[object]`: 
  - **parent** `string`: Resource name of the attribute (e.g. "attributes/has_delivery").
  - **valueType** `string`: Value type (e.g. BOOL, ENUM, URL, REPEATED_ENUM).
  - **displayName** `string`: Localized human-readable attribute name.
  - **groupDisplayName** `string`: Display name of the attribute group.
  - **repeatable** `boolean`: True if multiple values can be set simultaneously.
  - **deprecated** `boolean`: True if this attribute should no longer be used.
  - **valueMetadata** `array[object]`: Possible enum values (for ENUM / REPEATED_ENUM types).
    - **value** `string`: No description
    - **displayName** `string`: No description
- **nextPageToken** `string`: Present when additional pages of results are available.

#### 400: Invalid request (mixed modes, missing required params, wrong platform, or Google returned 4xx)

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

#### 401: Access token is invalid or revoked. Reconnect the Google Business Profile account.

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

#### 404: Account not found

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
