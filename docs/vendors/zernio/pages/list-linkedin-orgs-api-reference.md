# List LinkedIn orgs API Reference

Returns LinkedIn organizations (company pages) the connected account has admin access to.

## GET /v1/accounts/{accountId}/linkedin-organizations

**List LinkedIn orgs**

Returns LinkedIn organizations (company pages) the connected account has admin access to.

### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: Organizations list

**Response Body:**

- **organizations** `array[object]`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **vanityName** `string`: No description
  - **localizedName** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

---

---
