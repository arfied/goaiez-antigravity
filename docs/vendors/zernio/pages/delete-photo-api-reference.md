# Delete photo API Reference

Deletes a photo or media item from a Google Business Profile location.

## GET /v1/accounts/{accountId}/gmb-media

**List media**

Lists media items (photos) for a Google Business Profile location.
Returns photo URLs, descriptions, categories, and metadata.


### Parameters

- **accountId** (required) in path: No description
- **locationId** (optional) in query: Override which location to query. If omitted, uses the account's selected location. Use GET /gmb-locations to list valid IDs.
- **pageSize** (optional) in query: Number of items to return (max 100)
- **pageToken** (optional) in query: Pagination token from previous response

### Responses

#### 200: Media items fetched successfully

**Response Body:**

- **success** `boolean`: No description
- **accountId** `string`: No description
- **locationId** `string`: No description
- **mediaItems** `array[object]`: 
  - **name** `string`: Resource name
  - **mediaFormat** `string`: No description - one of: PHOTO, VIDEO
  - **sourceUrl** `string`: No description
  - **googleUrl** `string`: Google-hosted URL
  - **thumbnailUrl** `string`: No description
  - **description** `string`: No description
  - **createTime** `string` (date-time): No description
  - **locationAssociation** `object`: 
    - **category** `string`: No description
- **nextPageToken** `string`: No description
- **totalMediaItemsCount** `integer`: No description

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

## POST /v1/accounts/{accountId}/gmb-media

**Upload photo**

Creates a media item (photo) for a location from a publicly accessible URL.

Categories determine where the photo appears: CATEGORY_UNSPECIFIED, COVER, PROFILE, LOGO, EXTERIOR, INTERIOR, PRODUCT, FOOD_AND_DRINK, MENU, COMMON_AREA, ROOMS, TEAMS, AT_WORK, ADDITIONAL.


### Parameters

- **accountId** (required) in path: No description
- **locationId** (optional) in query: Override which location to target. If omitted, uses the account's selected location. Use GET /gmb-locations to list valid IDs.

### Request Body

- **sourceUrl** (required) `string`: Publicly accessible image URL
- **mediaFormat** `string`: No description - one of: PHOTO, VIDEO
- **description** `string`: Photo description
- **category** `string`: Where the photo appears on the listing - one of: CATEGORY_UNSPECIFIED, COVER, PROFILE, LOGO, EXTERIOR, INTERIOR, PRODUCT, FOOD_AND_DRINK, MENU, COMMON_AREA, ROOMS, TEAMS, AT_WORK, ADDITIONAL

### Responses

#### 200: Media created successfully

**Response Body:**

- **success** `boolean`: No description
- **name** `string`: No description
- **mediaFormat** `string`: No description
- **googleUrl** `string`: No description

#### 400: Invalid request or unsupported media format

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

## DELETE /v1/accounts/{accountId}/gmb-media

**Delete photo**

Deletes a photo or media item from a Google Business Profile location.

### Parameters

- **accountId** (required) in path: No description
- **locationId** (optional) in query: Override which location to target. If omitted, uses the account's selected location. Use GET /gmb-locations to list valid IDs.
- **mediaId** (required) in query: The media item ID to delete

### Responses

#### 200: Media deleted successfully

**Response Body:**

- **success** `boolean`: No description
- **deleted** `boolean`: No description
- **mediaId** `string`: No description

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
