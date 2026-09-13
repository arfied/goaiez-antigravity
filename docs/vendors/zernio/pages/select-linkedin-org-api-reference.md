# Select LinkedIn org API Reference

Complete the LinkedIn connection flow. Set accountType to "personal" or "organization" to connect as a company page. Use X-Connect-Token if connecting via API key.

## POST /v1/connect/linkedin/select-organization

**Select LinkedIn org**

Complete the LinkedIn connection flow. Set accountType to "personal" or "organization" to connect as a company page. Use X-Connect-Token if connecting via API key.

### Request Body

- **profileId** (required) `string`: No description
- **tempToken** (required) `string`: No description
- **userProfile** (required) `object`: No description
- **accountType** (required) `string`: No description - one of: personal, organization
- **selectedOrganization** `object`: No description
- **redirect_url** `string`: No description

### Responses

#### 200: LinkedIn account connected

**Response Body:**

- **message** `string`: No description
- **redirect_url** `string`: The redirect URL with connection params appended (only if redirect_url was provided in request)
- **account** `object`: 
  - **accountId** `string`: ID of the created SocialAccount
  - **platform** `string`: No description - one of: linkedin
  - **username** `string`: No description
  - **displayName** `string`: No description
  - **profilePicture** `string`: No description
  - **isActive** `boolean`: No description
  - **accountType** `string`: No description - one of: personal, organization
- **bulkRefresh** `object`: 
  - **updatedCount** `integer`: No description
  - **errors** `integer`: No description

#### 400: Missing required fields

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 500: Failed to connect LinkedIn account

---

---
