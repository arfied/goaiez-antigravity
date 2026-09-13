# Bulk upload from CSV API Reference

Create multiple posts by uploading a CSV file. Use dryRun=true to validate without creating posts.

CSV columns:
- Required: `platforms`, `profiles`, and a schedule (one of `schedule_time`, a `schedule_time_<platform>` override, `publish_now=true`, `use_queue=true`, or `is_draft=true`).
- Content: at least one of `post_content`, `title`, or `media_urls` is required.
- Aliases: a handful of columns accept the JSON field name from POST /v1/posts, since integrators infer the CSV shape from that endpoint's body. When both are present the real CSV column wins, unless it is blank for that row, in which case the alias value is used.
  - `content` aliases `post_content`
  - `timezone` aliases `tz`
  - `scheduledFor` aliases `schedule_time`
  - `mediaUrls` aliases `media_urls`
- Per-platform overrides use three dynamic column prefixes, one column per platform (e.g. `schedule_time_instagram`, `custom_content_tiktok`, `custom_media_youtube`): `schedule_time_<platform>`, `custom_content_<platform>`, `custom_media_<platform>`.
- Any other column is not read. It does not error, but it is reported in the response's `warnings` array as `unknown_columns:<a,b,c>` (see BulkUploadResult), so a misnamed or unsupported column is never silently dropped.
- Row limits: 5000 rows is a hard cap that returns 400 above it. 500 rows is only an advisory threshold, it adds `rows_exceed_advisory_limit:500` to `warnings` and the request still processes.

Example row (header + one data row):
```
post_content,platforms,profiles,schedule_time,tz
"Hello world",instagram,MyProfile,2026-09-01 10:00,America/New_York
```


## POST /v1/posts/bulk-upload

**Bulk upload from CSV**

Create multiple posts by uploading a CSV file. Use dryRun=true to validate without creating posts.

CSV columns:
- Required: `platforms`, `profiles`, and a schedule (one of `schedule_time`, a `schedule_time_<platform>` override, `publish_now=true`, `use_queue=true`, or `is_draft=true`).
- Content: at least one of `post_content`, `title`, or `media_urls` is required.
- Aliases: a handful of columns accept the JSON field name from POST /v1/posts, since integrators infer the CSV shape from that endpoint's body. When both are present the real CSV column wins, unless it is blank for that row, in which case the alias value is used.
  - `content` aliases `post_content`
  - `timezone` aliases `tz`
  - `scheduledFor` aliases `schedule_time`
  - `mediaUrls` aliases `media_urls`
- Per-platform overrides use three dynamic column prefixes, one column per platform (e.g. `schedule_time_instagram`, `custom_content_tiktok`, `custom_media_youtube`): `schedule_time_<platform>`, `custom_content_<platform>`, `custom_media_<platform>`.
- Any other column is not read. It does not error, but it is reported in the response's `warnings` array as `unknown_columns:<a,b,c>` (see BulkUploadResult), so a misnamed or unsupported column is never silently dropped.
- Row limits: 5000 rows is a hard cap that returns 400 above it. 500 rows is only an advisory threshold, it adds `rows_exceed_advisory_limit:500` to `warnings` and the request still processes.

Example row (header + one data row):
```
post_content,platforms,profiles,schedule_time,tz
"Hello world",instagram,MyProfile,2026-09-01 10:00,America/New_York
```


### Parameters

- **dryRun** (optional) in query: No description

### Request Body


### Responses

#### 200: Bulk upload results. Returned when every row succeeded (or every row failed).
A mix of successes and failures returns `207` instead, with the same body shape.


**Response Body:**

- **total** `integer`: Number of data rows processed from the CSV
- **valid** `integer`: Count of rows that succeeded (results[].ok === true)
- **invalid** `integer`: Count of rows that failed (total - valid)
- **results** `array[object]`: One entry per CSV data row, in row order.
  - **rowIndex** `integer`: 1-based index of the CSV data row (header excluded)
  - **ok** `boolean`: Whether the row was created successfully
  - **createdPostId** `string`: ID of the created post. Present only when `ok` is true and not a dry run.
  - **errors** `array[string]`: Machine-readable failure codes for this row. Present only when `ok` is false.
Examples: `unknown_profile:<id>`, `no_account_for_platform:<platform>`,
`schedule_time_missing`, `rate_limited:<platform>:@<username>:<remaining>`.

- **warnings** `array[string]`: Top-level advisory warnings, e.g. `rows_exceed_advisory_limit:500` or `unknown_columns:<a,b,c>` (comma-separated unrecognized CSV column names). Empty when none.
- **rateLimitedAccounts** `array[object]`: Present only when one or more rows targeted an account currently in cooldown.
Lets callers map `rate_limited:*` row errors back to structured metadata without
parsing the error strings.

  - **accountId** `string`: No description
  - **platform** `string`: No description
  - **username** `string`: No description
  - **rateLimitedUntil** `string` (date-time): No description

#### 207: Partial success: some rows were created and some failed. Body is identical in
shape to the `200` response. Inspect each entry in `results` (`ok` plus `errors`)
to see which rows failed and why.


**Response Body:**

- **total** `integer`: Number of data rows processed from the CSV
- **valid** `integer`: Count of rows that succeeded (results[].ok === true)
- **invalid** `integer`: Count of rows that failed (total - valid)
- **results** `array[object]`: One entry per CSV data row, in row order.
  - **rowIndex** `integer`: 1-based index of the CSV data row (header excluded)
  - **ok** `boolean`: Whether the row was created successfully
  - **createdPostId** `string`: ID of the created post. Present only when `ok` is true and not a dry run.
  - **errors** `array[string]`: Machine-readable failure codes for this row. Present only when `ok` is false.
Examples: `unknown_profile:<id>`, `no_account_for_platform:<platform>`,
`schedule_time_missing`, `rate_limited:<platform>:@<username>:<remaining>`.

- **warnings** `array[string]`: Top-level advisory warnings, e.g. `rows_exceed_advisory_limit:500` or `unknown_columns:<a,b,c>` (comma-separated unrecognized CSV column names). Empty when none.
- **rateLimitedAccounts** `array[object]`: Present only when one or more rows targeted an account currently in cooldown.
Lets callers map `rate_limited:*` row errors back to structured metadata without
parsing the error strings.

  - **accountId** `string`: No description
  - **platform** `string`: No description
  - **username** `string`: No description
  - **rateLimitedUntil** `string` (date-time): No description

#### 400: Invalid CSV or validation errors

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

#### 402: Payment required: the account owner has a failed payment. Not returned on dry-run.

**Response Body:**

- **error** `string`: No description

#### 404: Authenticated user not found

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

#### 429: Rate limit exceeded. Possible causes: API rate limit (requests per minute) or account cooldown (one or more accounts for platforms specified in the CSV are temporarily rate-limited).


**Response Body:**

- **error** `string`: No description
- **details** `object`: No description

---
