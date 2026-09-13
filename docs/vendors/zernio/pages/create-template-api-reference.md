# Create template API Reference

Create a new message template. Supports two modes:

Custom template: Provide components with your own content. Submitted to Meta for review (can take up to 24h).

Library template: Provide library_template_name instead of components to use a pre-built template
from Meta's template library. Library templates are pre-approved (no review wait). You can optionally
customize parameters and buttons via library_template_body_inputs and library_template_button_inputs.

Browse available library templates at: https://business.facebook.com/wa/manage/message-templates/


## GET /v1/whatsapp/templates

**List templates**

List message templates for the WhatsApp Business Account (WABA) associated with the given account.
Templates are fetched directly from the WhatsApp Cloud API. One entry per **name + language**:
a multi-language template appears once per language, each with its own Meta `id`.


### Parameters

- **accountId** (required) in query: WhatsApp account ID
- **name** (optional) in query: Exact template name; returns every language variant of that family.
- **language** (optional) in query: Exact language code (e.g. en_US).
- **status** (optional) in query: No description

### Responses

#### 200: Templates retrieved successfully

**Response Body:**

- **success** `boolean`: No description
- **templates** `array[object]`: 
  - **id** `string`: WhatsApp template ID
  - **name** `string`: No description
  - **status** `string`: No description - one of: APPROVED, PENDING, REJECTED
  - **category** `string`: No description - one of: AUTHENTICATION, MARKETING, UTILITY
  - **language** `string`: No description
  - **message_send_ttl_seconds** `integer`: Only when a custom TTL is set; absent while the category default applies.
  - **components** `array[object]`: 
    Type: `object`

#### 400: accountId is required or WABA ID not found

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

## POST /v1/whatsapp/templates

**Create template**

Create a new message template. Supports two modes:

Custom template: Provide components with your own content. Submitted to Meta for review (can take up to 24h).

Library template: Provide library_template_name instead of components to use a pre-built template
from Meta's template library. Library templates are pre-approved (no review wait). You can optionally
customize parameters and buttons via library_template_body_inputs and library_template_button_inputs.

Browse available library templates at: https://business.facebook.com/wa/manage/message-templates/


### Request Body

- **accountId** (required) `string`: WhatsApp account ID
- **name** (required) `string`: Template name (lowercase, letters/numbers/underscores, must start with a letter)
- **category** (required) `string`: Template category - one of: AUTHENTICATION, MARKETING, UTILITY
- **language** (required) `string`: Template language code (e.g., en_US)
- **parameter_format** `string`: Variable style: POSITIONAL ({{1}}, the default) or NAMED ({{customer_name}}). Named templates provide examples via body_text_named_params / header_text_named_params. Inferred as NAMED when omitted but a named-params example is present. - one of: POSITIONAL, NAMED, positional, named
- **components** `array`: Template components (header, body, footer, buttons, carousel, limited_time_offer). Required for custom templates, omit when using library_template_name.
- **library_template_name** `string`: Name of a pre-built template from Meta's template library (e.g., "appointment_reminder",
"auto_pay_reminder_1", "address_update"). When provided, the template is pre-approved
by Meta with no review wait. Omit components when using this field.

- **library_template_body_inputs** `object`: Optional body customizations for library templates. Available options depend on the
template (e.g., add_contact_number, add_learn_more_link, add_security_recommendation,
add_track_package_link, code_expiration_minutes).

- **library_template_button_inputs** `array`: Optional button customizations for library templates. Each item specifies button type
and configuration (e.g., URL, phone number, quick reply).

- **message_send_ttl_seconds** `integer`: Delivery validity window in seconds: a message not delivered within it is dropped. Range depends on category: AUTHENTICATION 30 to 900, UTILITY 30 to 43200 (12h), MARKETING 43200 to 2592000 (30 days); -1 (create only) keeps the 30-day default on AUTHENTICATION and UTILITY. Meta defaults to 600 for AUTHENTICATION and 30 days otherwise. If Meta later recategorises the template, it clears the TTL (read it back to check).

### Responses

#### 200: Template created (pre-approved for library templates, pending review for custom)

**Response Body:**

- **success** `boolean`: No description
- **template** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **status** `string`: APPROVED for library templates, PENDING for custom
  - **category** `string`: No description
  - **language** `string`: No description
  - **message_send_ttl_seconds** `integer`: Echoed when supplied on the request.

#### 400: Validation error (invalid name format, missing fields, invalid category)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
