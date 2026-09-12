# List LinkedIn orgs API Reference

Fetch full LinkedIn organization details (logos, vanity names, websites) for custom UI. No authentication required, only the tempToken from OAuth.

## GET /v1/connect/linkedin/organizations

**List LinkedIn orgs**

Fetch full LinkedIn organization details (logos, vanity names, websites) for custom UI. No authentication required, only the tempToken from OAuth.

### Parameters

- **tempToken** (required) in query: The temporary LinkedIn access token from the OAuth redirect
- **orgIds** (required) in query: Comma-separated list of organization IDs to fetch details for (max 100)

### Responses

#### 200: Organization details fetched successfully

**Response Body:**

- **organizations** `array[object]`: 
  - **id** `string`: Organization ID
  - **logoUrl** `string` (uri): Logo URL (may be absent if no logo)
  - **vanityName** `string`: Organization's vanity name/slug
  - **website** `string` (uri): Organization's website URL
  - **industry** `string`: Organization's primary industry
  - **description** `string`: Organization's description

#### 400: Missing required parameters or too many organization IDs

**Response Body:**

- **error** `string`: No description

#### 500: Failed to fetch organization details

---

---
