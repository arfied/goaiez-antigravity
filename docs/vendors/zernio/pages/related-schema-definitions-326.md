# Related Schema Definitions

## MetaLeadForm

A Meta Lead Gen form as Graph returns it, in Meta's own snake_case. Read through GET /v1/ads/lead-forms/{formId}. Every setting POST /v1/ads/lead-forms writes is present here, so a form can be diffed against what was created and drift from edits made in Meta's form builder is detectable. A compound field is omitted entirely when the form has no value for it, and `fields` narrows the selection.


### Properties

- **id** `string`: No description
- **name** `string`: No description
- **status** `string`: One of ACTIVE, ARCHIVED, DELETED or DRAFT.
- **locale** `string`: No description
- **created_time** `string`: No description
- **page_id** `string`: Owning Facebook Page. A form on any other Page is a 404, whether read or archived.
- **leads_count** `integer`: No description
- **organic_leads_count** `integer`: No description
- **expired_leads_count** `integer`: Leads Meta has aged out of the retention window.
- **privacy_policy_url** `string`: No description
- **follow_up_action_url** `string`: No description
- **follow_up_action_text** `string`: No description
- **question_page_custom_headline** `string`: No description
- **is_optimized_for_quality** `boolean`: No description
- **block_display_for_non_targeted_viewer** `boolean`: No description
- **allow_organic_lead** `boolean`: Whether the form can also be submitted from an organic Page post.
- **tracking_parameters** `array`: Custom key/value pairs attached to every lead of this form.
- **legal_content** `object`: Privacy policy and custom disclaimer as Meta stores them.
  - **id** `string`: 
  - **privacy_policy** `object`: 
  - **custom_disclaimer** `object`: Set in Meta form builder only; there is no create parameter for it.
- **context_card** `object`: 
  - **id** `string`: 
  - **title** `string`: 
  - **style** `string`:  - one of: LIST_STYLE, PARAGRAPH_STYLE
  - **content** `array`: 
  - **button_text** `string`: 
  - **cover_photo** `object`: 
- **thank_you_page** `object`: The form's single ending page, mirroring the thankYou* create fields. Meta has exactly one per form; there is no multiple-ending-page API (thank_you_pages and ending_pages are not Graph fields).

  - **id** `string`: 
  - **title** `string`: 
  - **body** `string`: 
  - **button_text** `string`: 
  - **button_type** `string`: 
  - **website_url** `string`: 
  - **enable_messenger** `boolean`: 
  - **status** `string`: 
  - **lead_gen_use_case** `string`: 
  - **business_phone_number** `string`: 
  - **country_code** `string`: 
- **questions** `array`: No description

## ErrorResponse

Canonical error envelope. `error` is the human-readable message; `type`,
`code`, `param`, `platform`, and `platformError` are top-level siblings
for programmatic handling. For upstream platform failures (`type:
platform_error`), `platformError` carries the provider's raw payload
verbatim (for Meta: `error_subcode`, `error_user_title`, `error_user_msg`).


### Properties

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
