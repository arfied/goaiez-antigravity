# Switch LinkedIn account type API Reference

Switch a LinkedIn account between personal profile and organization (company page) posting.

## PUT /v1/accounts/{accountId}/linkedin-organization

**Switch LinkedIn account type**

Switch a LinkedIn account between personal profile and organization (company page) posting.

### Parameters

- **accountId** (required) in path: No description

### Request Body

- **accountType** (required) `string`: No description - one of: personal, organization
- **selectedOrganization** `object`: No description

### Responses

#### 200: Account updated

**Response Body:**

- **message** `string`: No description
- **accountType** `string`: No description - one of: personal, organization
- **accountName** `string`: No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

---

---
