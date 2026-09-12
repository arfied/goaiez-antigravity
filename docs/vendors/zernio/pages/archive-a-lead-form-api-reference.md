# Archive a lead form API Reference

Neither platform hard-deletes a form; this archives it (Meta status=ARCHIVED; LinkedIn state=ARCHIVED via PARTIAL_UPDATE). Meta forms must belong to the Page the accountId manages.

## GET /v1/ads/lead-forms/{formId}

**Get a lead form**

Returns the full form, including the thank-you page, so a form can be diffed against what was created. Meta forms are scoped to the Page the accountId manages: a form on any other Page is a 404, never a read.


### Parameters

- **formId** (required) in path: Numeric form id (Meta leadgen_form id or LinkedIn leadForm id).
- **accountId** (required) in query: Connected Meta ads, facebook or linkedin ads account id (selects the platform). A Meta ads connection resolves its Page through the Facebook account linked to the same profile.
- **fields** (optional) in query: Meta only. A Graph field selection passed through verbatim to GET /{form-id}, replacing the default projection, so fields Meta adds later are reachable without an API change. Field names, commas and {} expansion only; anything else (Graph field modifiers such as .limit(), or characters that could open another query parameter) is a 400. Ownership of the form is verified before the selection runs, so this cannot reach any Page but the one accountId manages. Unknown field names are rejected by Meta as a 400.


### Responses

#### 200: Form metadata. Meta forms follow MetaLeadForm; LinkedIn forms return LinkedIn's own adForm shape.

**Response Body:**

- **status** `string`: No description (example: "success")
- **form**: One of multiple types
  - `MetaLeadForm`

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

#### 404: No lead form with that id on the Page this account manages.

---

## DELETE /v1/ads/lead-forms/{formId}

**Archive a lead form**

Neither platform hard-deletes a form; this archives it (Meta status=ARCHIVED; LinkedIn state=ARCHIVED via PARTIAL_UPDATE). Meta forms must belong to the Page the accountId manages.

### Parameters

- **formId** (required) in path: Numeric form id (Meta leadgen_form id or LinkedIn leadForm id).
- **accountId** (required) in query: Connected Meta ads, facebook or linkedin ads account id (selects the platform). A Meta ads connection resolves its Page through the Facebook account linked to the same profile.

### Responses

#### 200: Archived.

**Response Body:**

- **status** `string`: No description
- **formId** `string`: No description
- **archived** `boolean`: No description

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

#### 404: No lead form with that id on the Page this account manages.

---
