# Select Snapchat profile API Reference

Complete the Snapchat connection flow by saving the selected Public Profile. Snapchat requires a Public Profile to publish content. Use X-Connect-Token if connecting via API key.

## GET /v1/connect/snapchat/select-profile

**List Snapchat profiles**

For headless flows. Returns Snapchat Public Profiles the user can post to. Use X-Connect-Token from the redirect URL.

### Parameters

- **X-Connect-Token** (required) in header: Short-lived connect token from the OAuth redirect
- **profileId** (required) in query: Your Zernio profile ID
- **tempToken** (required) in query: Temporary Snapchat access token from the OAuth callback redirect

### Responses

#### 200: List of Snapchat Public Profiles available for connection

**Response Body:**

- **publicProfiles** `array[object]`: 
  - **id** `string`: Snapchat Public Profile ID
  - **display_name** `string`: Public profile display name
  - **username** `string`: Public profile username/handle
  - **profile_image_url** `string`: Profile image URL
  - **subscriber_count** `integer`: Number of subscribers

#### 400: Missing required parameters (profileId or tempToken)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: No access to profile

#### 500: Failed to fetch public profiles

---

## POST /v1/connect/snapchat/select-profile

**Select Snapchat profile**

Complete the Snapchat connection flow by saving the selected Public Profile. Snapchat requires a Public Profile to publish content. Use X-Connect-Token if connecting via API key.

### Parameters

- **X-Connect-Token** (optional) in header: Short-lived connect token from the OAuth redirect (for API users)

### Request Body

- **profileId** (required) `string`: Your Zernio profile ID
- **selectedPublicProfile** (required) `object`: The selected Snapchat Public Profile
- **tempToken** (required) `string`: Temporary Snapchat access token from OAuth
- **userProfile** (required) `object`: User profile data from OAuth redirect
- **refreshToken** `string`: Snapchat refresh token (if available)
- **expiresIn** `integer`: Token expiration time in seconds
- **redirect_url** `string`: Custom redirect URL after connection completes

### Responses

#### 200: Snapchat Public Profile connected successfully

**Response Body:**

- **message** `string`: No description
- **redirect_url** `string`: Redirect URL with connection params (if provided in request)
- **account** `object`: 
  - **accountId** `string`: ID of the created SocialAccount
  - **platform** `string`: No description - one of: snapchat
  - **username** `string`: No description
  - **displayName** `string`: No description
  - **profilePicture** `string`: No description
  - **isActive** `boolean`: No description
  - **publicProfileName** `string`: No description

#### 400: Missing required fields

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: No access to profile or profile limit exceeded

#### 500: Failed to connect Snapchat account

---

---
