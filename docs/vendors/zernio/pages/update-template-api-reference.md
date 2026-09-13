# Update template API Reference

Update one variant's components and/or its message_send_ttl_seconds. Name, language and category cannot change after creation.

Meta stores one template per **name + language**, so a name identifies a family of variants,
each with its own Meta id. Pass `language` to address one variant. Without it, a name with a
single variant resolves to that variant; a name with several returns `409 ambiguous_template`
with `details.languages`. A bare language (`es`) matches a single regional variant (`es_ES`);
if the family has several regional variants for it, that is also a 409. A full code (`es_ES`)
must match exactly. Variants in `PENDING_DELETION` are not part of the family.

Meta only allows editing templates in `APPROVED`, `REJECTED` or `PAUSED` state; an approved
template can be edited once per 24 hours and up to 10 times per 30 days. A component update
sends the variant back to Meta for review, so the `status` returned here is normally `PENDING`;
a TTL-only update keeps an APPROVED variant approved.
The final outcome arrives on the `whatsapp.template.status_updated` webhook (which carries the
variant's `templateId` and `language`). A variant already in `PENDING` cannot be edited again
until Meta finishes reviewing it.


## GET /v1/whatsapp/templates/{templateName}

**Get template**

Retrieve one message template variant by name.

Meta stores one template per **name + language**, so a name identifies a family of variants,
each with its own Meta id. Pass `language` to address one variant. Without it, a name with a
single variant resolves to that variant; a name with several returns `409 ambiguous_template`
with `details.languages`. A bare language (`es`) matches a single regional variant (`es_ES`);
if the family has several regional variants for it, that is also a 409. A full code (`es_ES`)
must match exactly. Variants in `PENDING_DELETION` are not part of the family.


### Parameters

- **templateName** (required) in path: Template name (the family).
- **accountId** (required) in query: WhatsApp account ID
- **language** (optional) in query: Language code of the variant (e.g. en_US, es, pt_BR). Required when the family has several languages.

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

#### 404: Account not found, or no template with that name (and language, when given). details.languages lists the family's languages when the name exists (code template_not_found).

#### 409: The template name exists in several languages and no language was given (code ambiguous_template). details.languages lists them.

**Response Body:**

- **error** `string`: No description
- **type** `string`: No description - one of: invalid_request_error
- **code** `string`: No description - one of: ambiguous_template
- **param** `string`: No description - one of: language
- **details** `object`: 
  - **languages** `array[string]`: 

#### 502: Meta rejected the request or was unreachable. Meta 4xx statuses are forwarded as-is.

---

## PATCH /v1/whatsapp/templates/{templateName}

**Update template**

Update one variant's components and/or its message_send_ttl_seconds. Name, language and category cannot change after creation.

Meta stores one template per **name + language**, so a name identifies a family of variants,
each with its own Meta id. Pass `language` to address one variant. Without it, a name with a
single variant resolves to that variant; a name with several returns `409 ambiguous_template`
with `details.languages`. A bare language (`es`) matches a single regional variant (`es_ES`);
if the family has several regional variants for it, that is also a 409. A full code (`es_ES`)
must match exactly. Variants in `PENDING_DELETION` are not part of the family.

Meta only allows editing templates in `APPROVED`, `REJECTED` or `PAUSED` state; an approved
template can be edited once per 24 hours and up to 10 times per 30 days. A component update
sends the variant back to Meta for review, so the `status` returned here is normally `PENDING`;
a TTL-only update keeps an APPROVED variant approved.
The final outcome arrives on the `whatsapp.template.status_updated` webhook (which carries the
variant's `templateId` and `language`). A variant already in `PENDING` cannot be edited again
until Meta finishes reviewing it.


### Parameters

- **templateName** (required) in path: Template name (the family).

### Request Body

- **accountId** (required) `string`: WhatsApp account ID
- **language** `string`: Language code of the variant to edit (e.g. en_US, es, pt_BR). Required when the family has several languages. Body only: a language query parameter on PATCH is a 400.
- **components** `array`: Updated template components. Optional when only message_send_ttl_seconds changes; at least one of the two is required.
- **message_send_ttl_seconds** `integer`: Delivery validity window in seconds: a message not delivered within it is dropped. Range depends on category: AUTHENTICATION 30 to 900, UTILITY 30 to 43200 (12h), MARKETING 43200 to 2592000 (30 days); -1 is not accepted here (Meta treats it as an empty edit); send a value in range. A TTL-only edit keeps an APPROVED template approved, no re-review. Meta defaults to 600 for AUTHENTICATION and 30 days otherwise. If Meta later recategorises the template, it clears the TTL (read it back to check).

### Responses

#### 200: Template updated successfully

**Response Body:**

- **success** `boolean`: No description
- **template** `object`: 
  - **id** `string`: Meta id of the edited variant.
  - **name** `string`: No description
  - **language** `string`: The variant that was edited.
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

#### 404: Account not found, or no template with that name (and language, when given) (code template_not_found).

#### 409: The template name exists in several languages and no language was given (code ambiguous_template). details.languages lists them.

**Response Body:**

- **error** `string`: No description
- **type** `string`: No description - one of: invalid_request_error
- **code** `string`: No description - one of: ambiguous_template
- **param** `string`: No description - one of: language
- **details** `object`: 
  - **languages** `array[string]`: 

#### 502: Meta rejected the update or was unreachable. Meta 4xx statuses are forwarded as-is.

---

## DELETE /v1/whatsapp/templates/{templateName}

**Delete template**

Permanently delete a message template.

**Without `language` this deletes every language variant of the name** (Meta's own
contract for deletion by name). Pass `language` to delete one variant only; the response
`scope` says which happened. Meta keeps a deleted approved template in `PENDING_DELETION`
for a while and the name cannot be reused for 30 days.


### Parameters

- **templateName** (required) in path: Template name (the family).
- **accountId** (required) in query: WhatsApp account ID
- **language** (optional) in query: Delete only this language variant (e.g. es). Omit to delete the whole family.

### Responses

#### 200: Template deleted successfully

**Response Body:**

- **success** `boolean`: No description
- **scope** `string`: Whether the whole family or one variant was deleted. - one of: all_languages, language
- **language** `string`: The deleted variant; only when scope is language.
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

#### 404: Account not found, or (with language) no such variant (code template_not_found).

#### 409: Only with language: a bare code (es) matched several regional variants (es_ES, es_MX), so nothing was deleted (code ambiguous_template). Without language there is no 409: the whole family is deleted.

**Response Body:**

- **error** `string`: No description
- **type** `string`: No description - one of: invalid_request_error
- **code** `string`: No description - one of: ambiguous_template
- **param** `string`: No description - one of: language
- **details** `object`: 
  - **languages** `array[string]`: 

#### 502: Meta rejected the request or was unreachable. Meta 4xx statuses are forwarded as-is.

---
