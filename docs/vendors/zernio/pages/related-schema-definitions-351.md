# Related Schema Definitions

## BulkUploadResult

Result of a CSV bulk upload. The same shape is returned for `200` (all rows
succeeded or all failed) and `207` (mixed). Per-row outcomes live in `results`;
the row's success is `ok`, and failures carry machine-readable codes in `errors`.


### Properties

- **total** `integer`: Number of data rows processed from the CSV
- **valid** `integer`: Count of rows that succeeded (results[].ok === true)
- **invalid** `integer`: Count of rows that failed (total - valid)
- **results** `array`: One entry per CSV data row, in row order.
- **warnings** `array`: Top-level advisory warnings, e.g. `rows_exceed_advisory_limit:500` or `unknown_columns:<a,b,c>` (comma-separated unrecognized CSV column names). Empty when none.
- **rateLimitedAccounts** `array`: Present only when one or more rows targeted an account currently in cooldown.
Lets callers map `rate_limited:*` row errors back to structured metadata without
parsing the error strings.


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
