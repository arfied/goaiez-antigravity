# Complete Meta business login API Reference

Facebook Login for Business redirect target. Meta supplies the single-use authorization code and the authenticated state returned by connectAds. The state expires after 30 minutes and binds the user, profile, Page selection and ad-account scope. No bearer token is sent by the browser. Success reconnects only metaads and redirects to the original redirect_url. Invalid state returns 400; inaccessible profiles or missing ads access cannot connect. Dashboard logins with several Pages redirect to the Facebook Page picker with an encrypted selectionToken valid for ten minutes. Listing and selecting require the initiating user and current profile access. No plaintext platform token is returned to the browser.

## GET /v1/connect/meta-ads/callback

**Complete Meta business login**

Facebook Login for Business redirect target. Meta supplies the single-use authorization code and the authenticated state returned by connectAds. The state expires after 30 minutes and binds the user, profile, Page selection and ad-account scope. No bearer token is sent by the browser. Success reconnects only metaads and redirects to the original redirect_url. Invalid state returns 400; inaccessible profiles or missing ads access cannot connect. Dashboard logins with several Pages redirect to the Facebook Page picker with an encrypted selectionToken valid for ten minutes. Listing and selecting require the initiating user and current profile access. No plaintext platform token is returned to the browser.

### Parameters

- **state** (required) in query: Authenticated state from the initial connectAds response.
- **code** (optional) in query: Single-use authorization code returned by Meta.
- **error** (optional) in query: Meta authorization error when the user declines the dialog.

### Responses

#### 307: Redirect to the original callback URL with connected=metaads, profileId and accountId on success; authorization denial redirects with an error. Dashboard logins requiring a Page choice redirect to /connect/facebook/select-page with selectionToken.

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

#### 403: Ads access or profile access required.

#### 409: The new token grants do not match the existing connection, or its previous grants cannot be verified.

#### 503: Business login state signing is not configured.

---
