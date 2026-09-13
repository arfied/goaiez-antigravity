# Update a crawled website API Reference



## GET /v1/accounts/{accountId}/business-agent/websites/{websiteId}

**Get a crawled website**

### Parameters

- **undefined** (optional): No description
- **websiteId** (required) in path: No description

### Responses

#### 200: Website

**Response Body:**

- **url** (required) `string` (uri): No description
- **included_sub_domains** `array[string]`: 
- **included_url_patterns** `array[string]`: Only URLs containing one of these substrings are ingested.
- **excluded_sub_domains** `array[string]`: 
- **excluded_url_patterns** `array[string]`: 
- **single_urls** `array[string]`: Crawl only these exact pages instead of the whole site.
- **id** (required) `string`: No description
- **crawl_status** `string`: not_started, pending, in_progress, completed, completed_no_data or failed (see crawl_error).
- **crawl_error** `string`: No description
- **pages_crawled** `integer`: No description
- **last_crawled_at** `integer`: Unix seconds.
- **created_at** `integer`: Unix seconds.

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

#### 403: Inbox add-on required, the WhatsApp token lacks the Business Agent permissions (code reconnect_required), or the merchant has not accepted the Meta Business Agent terms in WhatsApp Manager (code business_agent_terms_not_accepted).

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

#### 404: Account not found, or no agent exists on the number yet or the referenced item does not exist (code business_agent_not_found).

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

## PUT /v1/accounts/{accountId}/business-agent/websites/{websiteId}

**Update a crawled website**

### Parameters

- **undefined** (optional): No description
- **websiteId** (required) in path: No description

### Request Body

- **url** (required) `string`: No description
- **included_sub_domains** `array`: No description
- **included_url_patterns** `array`: Only URLs containing one of these substrings are ingested.
- **excluded_sub_domains** `array`: No description
- **excluded_url_patterns** `array`: No description
- **single_urls** `array`: Crawl only these exact pages instead of the whole site.

### Responses

#### 200: Website updated

**Response Body:**

- **url** (required) `string` (uri): No description
- **included_sub_domains** `array[string]`: 
- **included_url_patterns** `array[string]`: Only URLs containing one of these substrings are ingested.
- **excluded_sub_domains** `array[string]`: 
- **excluded_url_patterns** `array[string]`: 
- **single_urls** `array[string]`: Crawl only these exact pages instead of the whole site.
- **id** (required) `string`: No description
- **crawl_status** `string`: not_started, pending, in_progress, completed, completed_no_data or failed (see crawl_error).
- **crawl_error** `string`: No description
- **pages_crawled** `integer`: No description
- **last_crawled_at** `integer`: Unix seconds.
- **created_at** `integer`: Unix seconds.

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

#### 403: Inbox add-on required, the WhatsApp token lacks the Business Agent permissions (code reconnect_required), or the merchant has not accepted the Meta Business Agent terms in WhatsApp Manager (code business_agent_terms_not_accepted).

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

#### 404: Account not found, or no agent exists on the number yet or the referenced item does not exist (code business_agent_not_found).

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

## DELETE /v1/accounts/{accountId}/business-agent/websites/{websiteId}

**Remove a crawled website**

### Parameters

- **undefined** (optional): No description
- **websiteId** (required) in path: No description

### Responses

#### 200: Deleted

**Response Body:**

- **success** (required) `boolean`: No description

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

#### 403: Inbox add-on required, the WhatsApp token lacks the Business Agent permissions (code reconnect_required), or the merchant has not accepted the Meta Business Agent terms in WhatsApp Manager (code business_agent_terms_not_accepted).

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

#### 404: Account not found, or no agent exists on the number yet or the referenced item does not exist (code business_agent_not_found).

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
