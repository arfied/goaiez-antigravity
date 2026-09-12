# List automation logs API Reference

Paginated list of every comment that triggered this automation, with send status and commenter info.

## GET /v1/comment-automations/{automationId}/logs

**List automation logs**

Paginated list of every comment that triggered this automation, with send status and commenter info.

### Parameters

- **automationId** (required) in path: No description
- **status** (optional) in query: Filter by result status
- **limit** (optional) in query: No description
- **skip** (optional) in query: No description

### Responses

#### 200: Trigger logs with pagination

**Response Body:**

- **success** `boolean`: No description
- **logs** `array[object]`: 
  - **id** `string`: No description
  - **commentId** `string`: No description
  - **commenterId** `string`: No description
  - **commenterName** `string`: No description
  - **commentText** `string`: No description
  - **source** `string`: Which door triggered this send. Absent on rows written before this field existed (all of those are comment-triggered). - one of: comment, story_reply, dm
  - **status** `string`: DM outcome. 'pending' = the automation has a dmDelaySeconds and the response is queued but not sent yet. 'gated' = the follow-gate confirmation DM went out and we are waiting for the tap; it flips to 'sent' or 'skipped' when they tap. - one of: pending, sent, failed, skipped, gated
  - **audienceOutcome** `string`: How the audience rule resolved. Absent on automations without one. - one of: passed, blocked, gate_sent, gate_passed, gate_failed
  - **commenterIsFollower** `boolean`: Follow relationship at decision time. Absent when Instagram would not tell us (the commenter never messaged the account).
  - **commenterFollowerCount** `integer`: No description
  - **error** `string`: DM error message if status is failed
  - **commentReplyStatus** `string`: Outcome of the optional public reply on the triggering comment. 'skipped' if no commentReply was configured or if the DM failed (the public reply is not attempted in that case). - one of: sent, failed, skipped
  - **commentReplyError** `string`: Public-reply error message if commentReplyStatus is failed
  - **nextDueAt** `string` (date-time): When the next queued send fires. Present only while something is still pending.
  - **createdAt** `string` (date-time): No description
- **pagination** `object`: 
  - **total** `integer`: No description
  - **limit** `integer`: No description
  - **skip** `integer`: No description
  - **hasMore** `boolean`: No description
- **misses** `object`: Comments that reached this automation but matched none of its keywords. These produce no log entry, so this is the only signal that a keyword is catching nothing. Retained for a short window, then dropped.
  - **total** `integer`: Number of non-matching comments in the retention window
  - **retentionDays** `integer`: How many days of non-matching comments the total covers
  - **samples** `array[object]`: A few of the most recent non-matching comments, for diagnosing a keyword setup.
    - **commentText** `string`: No description
    - **commenterName** `string`: No description
    - **excludedBy** `string`: Set when an exclusion keyword vetoed an otherwise matching comment
    - **at** `string` (date-time): No description

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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---
