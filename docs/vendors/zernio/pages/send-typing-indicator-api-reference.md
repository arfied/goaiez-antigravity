# Send typing indicator API Reference

Show a typing indicator in a conversation. Platform support:
- Facebook Messenger: Shows "Page is typing..." for 20 seconds
- Instagram: Shows "typing..." to the recipient (works for both Instagram Login and Facebook Login accounts). The recipient must be signed in to Instagram to see it.
- Telegram: Shows "Bot is typing..." for 5 seconds
- WhatsApp: Shows "typing..." for up to 25 seconds. Requires a recent inbound message in the conversation (Meta references the inbound message id) and also marks that message as read as a side-effect.
- All others: Returns 200 but no-op (platform doesn't support it)

Typing indicators are best-effort. The endpoint always returns 200 even if the platform call fails; `success` reports whether a typing indicator was actually sent to the platform (`false` on unsupported platforms or when the platform call failed).


## POST /v1/inbox/conversations/{conversationId}/typing

**Send typing indicator**

Show a typing indicator in a conversation. Platform support:
- Facebook Messenger: Shows "Page is typing..." for 20 seconds
- Instagram: Shows "typing..." to the recipient (works for both Instagram Login and Facebook Login accounts). The recipient must be signed in to Instagram to see it.
- Telegram: Shows "Bot is typing..." for 5 seconds
- WhatsApp: Shows "typing..." for up to 25 seconds. Requires a recent inbound message in the conversation (Meta references the inbound message id) and also marks that message as read as a side-effect.
- All others: Returns 200 but no-op (platform doesn't support it)

Typing indicators are best-effort. The endpoint always returns 200 even if the platform call fails; `success` reports whether a typing indicator was actually sent to the platform (`false` on unsupported platforms or when the platform call failed).


### Parameters

- **conversationId** (required) in path: The conversation ID

### Request Body

- **accountId** (required) `string`: Account ID

### Responses

#### 200: Typing indicator sent (or no-op on unsupported platforms)

**Response Body:**

- **success** `boolean`: No description

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

#### 403: Inbox addon required

#### 404: Account or conversation not found

---
