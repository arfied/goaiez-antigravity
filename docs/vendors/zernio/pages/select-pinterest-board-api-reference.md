# Select Pinterest board API Reference

Complete the Pinterest connection flow. After OAuth, use this endpoint to save the selected board and complete the account connection. Use the X-Connect-Token header if you initiated the connection via API key.


## GET /v1/connect/pinterest/select-board

**List Pinterest boards**

For headless flows. Returns Pinterest boards the user can post to. Use X-Connect-Token from the redirect URL.

### Parameters

- **X-Connect-Token** (required) in header: Short-lived connect token from the OAuth redirect
- **profileId** (required) in query: Your Zernio profile ID
- **tempToken** (required) in query: Temporary Pinterest access token from the OAuth callback redirect

### Responses

#### 200: List of Pinterest Boards available for connection

**Response Body:**

- **boards** `array[object]`: 
  - **id** `string`: Pinterest Board ID
  - **name** `string`: Board name
  - **description** `string`: Board description
  - **privacy** `string`: Board privacy setting

#### 400: Missing required parameters

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: No access to profile

#### 500: Failed to fetch boards

---

## POST /v1/connect/pinterest/select-board

**Select Pinterest board**

Complete the Pinterest connection flow. After OAuth, use this endpoint to save the selected board and complete the account connection. Use the X-Connect-Token header if you initiated the connection via API key.


### Request Body

- **profileId** (required) `string`: Your Zernio profile ID
- **boardId** (required) `string`: The Pinterest Board ID selected by the user
- **boardName** `string`: The board name (for display purposes)
- **tempToken** (required) `string`: Temporary Pinterest access token from OAuth
- **userProfile** `object`: User profile data from OAuth redirect
- **refreshToken** `string`: Pinterest refresh token (if available)
- **expiresIn** `integer`: Token expiration time in seconds
- **redirect_url** `string`: Custom redirect URL after connection completes

### Responses

#### 200: Pinterest Board connected successfully

**Response Body:**

- **message** `string`: No description
- **redirect_url** `string`: Redirect URL with connection params (if provided)
- **account** `object`: 
  - **accountId** `string`: ID of the created SocialAccount
  - **platform** `string`: No description - one of: pinterest
  - **username** `string`: No description
  - **displayName** `string`: No description
  - **profilePicture** `string`: No description
  - **isActive** `boolean`: No description
  - **defaultBoardName** `string`: No description

#### 400: Missing required fields

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: No access to profile or profile limit exceeded

#### 500: Failed to save Pinterest connection

---

---
