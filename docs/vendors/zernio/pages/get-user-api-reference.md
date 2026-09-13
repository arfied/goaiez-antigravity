# Get user API Reference

Returns a single user's details by ID, including name, email, and role.

## GET /v1/users/{userId}

**Get user**

Returns a single user's details by ID, including name, email, and role.

### Parameters

- **userId** (required) in path: No description

### Responses

#### 200: User

**Response Body:**

- **user** `object`: 
  - **_id** `string`: No description
  - **name** `string`: No description
  - **email** `string`: No description
  - **role** `string`: No description
  - **isRoot** `boolean`: No description
  - **profileAccess** `array[string]`: 

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Forbidden

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
