# Delete group API Reference

Permanently deletes an account group. The accounts themselves are not affected.

## PUT /v1/account-groups/{groupId}

**Update group**

Updates the name or account list of an existing group. You can rename the group, change its accounts, or both.

### Parameters

- **groupId** (required) in path: No description

### Request Body

- **name** `string`: No description
- **accountIds** `array`: No description

### Responses

#### 200: Updated

**Response Body:**

- **message** `string`: No description
- **group** `object`: No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 409: Group name already exists

---

## DELETE /v1/account-groups/{groupId}

**Delete group**

Permanently deletes an account group. The accounts themselves are not affected.

### Parameters

- **groupId** (required) in path: No description

### Responses

#### 200: Deleted

**Response Body:**

- **message** `string`: No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
