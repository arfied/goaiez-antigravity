# List Facebook pages API Reference

Returns Facebook Pages after OAuth. Classic connections require profileId and tempToken from the OAuth redirect. Use X-Connect-Token for headless connections. The dashboard business-login picker instead sends only selectionToken, an encrypted grant valid for ten minutes. This requires the initiating user and current profile access and returns only Page IDs and names. X-Connect-Token cannot authorize business selection.

## GET /v1/connect/facebook/select-page

**List Facebook pages**

Returns Facebook Pages after OAuth. Classic connections require profileId and tempToken from the OAuth redirect. Use X-Connect-Token for headless connections. The dashboard business-login picker instead sends only selectionToken, an encrypted grant valid for ten minutes. This requires the initiating user and current profile access and returns only Page IDs and names. X-Connect-Token cannot authorize business selection.

### Parameters

- **profileId** (optional) in query: Profile ID from your classic connection flow. Required with tempToken.
- **tempToken** (optional) in query: Temporary Facebook access token from the classic OAuth callback. Required with profileId.
- **selectionToken** (optional) in query: Encrypted dashboard business-login grant. Send alone instead of profileId and tempToken. Expires after ten minutes.

### Responses

#### 200: List of Facebook Pages available for connection

**Response Body:**

- **pages** `array[object]`: 
  - **id** `string`: Facebook Page ID
  - **name** `string`: Page name
  - **username** `string`: Page username/handle (may be null)
  - **access_token** `string`: Page-specific access token
  - **category** `string`: Page category
  - **tasks** `array[string]`: User permissions for this page

#### 400: Invalid or expired selectionToken, no granted Pages, or missing classic profileId and tempToken.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: The caller is not the initiating user or no longer has profile access.

#### 500: Failed to fetch pages (e.g., invalid token, insufficient permissions)

**Response Body:**

- **error** `string`: No description

---

## POST /v1/connect/facebook/select-page

**Select Facebook page**

Complete a classic Facebook Page connection with profileId, pageId, tempToken and userProfile. Use X-Connect-Token for headless connections. The dashboard business-login picker instead sends only selectionToken and pageId to complete a Meta Ads connection. The server verifies the initiating user, profile access, current grants and connection eligibility. The profile, platform token, ad-account scope and return URL come only from the encrypted grant. Business selection requires a session or bearer authentication for the initiating user; X-Connect-Token is not accepted. It returns redirect_url with connected=metaads on success or an eligibility error redirect.

### Request Body

- **platformSpecificData**: Platform-specific settings (see schema definitions below)

### Responses

#### 200: Facebook Page connected or business-login redirect returned.

**Response Body:**

- **message** `string`: No description
- **redirect_url** `string`: Redirect URL when a custom redirect_url was provided or a business Page was selected.
- **account** `object`: 
  - **accountId** `string`: ID of the created SocialAccount
  - **platform** `string`: No description - one of: facebook
  - **username** `string`: No description
  - **displayName** `string`: No description
  - **profilePicture** `string`: No description
  - **isActive** `boolean`: No description
  - **selectedPageName** `string`: No description

#### 400: Invalid or expired selectionToken, invalid Page choice, forbidden grant overrides, or missing classic connection fields.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: User does not have access to the specified profile

#### 404: Selected page not found in available pages

#### 409: Reconnect identity mismatch. The OAuth
was initiated as a `force=true` token-recovery re-auth
(`GET /v1/connect/{platform}/ads`), but the grant landed on a different
Facebook user or page than the connected account. The existing account
is left untouched.


**Response Body:**

- **error** `string`: No description
- **code** `string`: No description - one of: RECONNECT_ACCOUNT_MISMATCH

#### 500: Failed to save Facebook connection

---

---
