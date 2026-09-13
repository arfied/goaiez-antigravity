# Delete template by id API Reference

Delete one language variant by its Meta id. Other languages of the same name are untouched.
The name cannot be reused for 30 days once its last variant is deleted.


## GET /v1/whatsapp/templates/id/{templateId}

**Get template by id**

Retrieve one template variant by its Meta id, the id every variant of a family has on its own
and the one the `whatsapp.template.status_updated` webhook carries.


### Parameters

- **templateId** (required) in path: Meta template id (numeric).
- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Template retrieved successfully

**Response Body:**

- **success** `boolean`: No description
- **template** `object`: 
  - **id** `string`: Meta template id. Unique per language variant; usable on /v1/whatsapp/templates/id/{templateId}.
  - **name** `string`: No description
  - **status** `string`: No description
  - **category** `string`: No description
  - **language** `string`: The variant actually returned.
  - **components** `array[object]`: 
    Type: `object`
  - **message_send_ttl_seconds** `integer`: Only when a custom TTL is set; absent while the category default applies.
  - **rejected_reason** `string`: Only when status is REJECTED.
  - **quality_score** `object`: Post-approval quality (GREEN/YELLOW/RED), when Meta reports one.

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

#### 404: Account not found.

#### 502: Meta rejected the request (including an id the account cannot access) or was unreachable. Meta 4xx statuses are forwarded as-is.

---

## PATCH /v1/whatsapp/templates/id/{templateId}

**Update template by id**

Update one variant's components and/or its message_send_ttl_seconds by its Meta id. Name, language and category cannot change.

Meta only allows editing templates in `APPROVED`, `REJECTED` or `PAUSED` state; an approved
template can be edited once per 24 hours and up to 10 times per 30 days. A component update
sends the variant back to Meta for review, so the `status` returned here is normally `PENDING`;
a TTL-only update keeps an APPROVED variant approved.
The final outcome arrives on the `whatsapp.template.status_updated` webhook (which carries the
variant's `templateId` and `language`). A variant already in `PENDING` cannot be edited again
until Meta finishes reviewing it.


### Parameters

- **templateId** (required) in path: Meta template id (numeric).

### Request Body

- **accountId** (required) `string`: WhatsApp account ID
- **components** `array`: Updated template components. Optional when only message_send_ttl_seconds changes; at least one of the two is required.
- **message_send_ttl_seconds** `integer`: Delivery validity window in seconds: a message not delivered within it is dropped. Range depends on category: AUTHENTICATION 30 to 900, UTILITY 30 to 43200 (12h), MARKETING 43200 to 2592000 (30 days); -1 is not accepted here (Meta treats it as an empty edit); send a value in range. A TTL-only edit keeps an APPROVED template approved, no re-review. Meta defaults to 600 for AUTHENTICATION and 30 days otherwise. If Meta later recategorises the template, it clears the TTL (read it back to check).

### Responses

#### 200: Template updated successfully

**Response Body:**

- **success** `boolean`: No description
- **template** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **language** `string`: No description
  - **status** `string`: Approval state read back from Meta after the update, normally PENDING. If the state cannot be read back, the last known status is returned instead. (example: "PENDING")

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

#### 404: Account not found.

#### 502: Meta rejected the update (including an id the account cannot access) or was unreachable. Meta 4xx statuses are forwarded as-is.

---

## DELETE /v1/whatsapp/templates/id/{templateId}

**Delete template by id**

Delete one language variant by its Meta id. Other languages of the same name are untouched.
The name cannot be reused for 30 days once its last variant is deleted.


### Parameters

- **templateId** (required) in path: Meta template id (numeric).
- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Variant deleted successfully

**Response Body:**

- **success** `boolean`: No description
- **scope** `string`: No description - one of: language
- **language** `string`: No description
- **message** `string`: No description

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

#### 404: Account not found.

#### 502: Meta rejected the request (including an id the account cannot access) or was unreachable. Meta 4xx statuses are forwarded as-is.

---
