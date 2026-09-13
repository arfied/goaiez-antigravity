# List users API Reference

Returns all users in the team including roles and profile access. Also returns the currentUserId of the caller.

## GET /v1/users

**List users**

Returns all users in the team including roles and profile access. Also returns the currentUserId of the caller.

### Responses

#### 200: Users

**Response Body:**

- **currentUserId** `string`: No description
- **users** `array[object]`: 
  - **_id** `string`: No description
  - **name** `string`: No description
  - **email** `string`: No description
  - **role** `string`: No description
  - **isRoot** `boolean`: No description
  - **profileAccess** `array[string]`: 
  - **createdAt** `string` (date-time): No description
  - **lastLoginAt** `string` (date-time): Last sign-in, stamped at most once an hour, so it is accurate to within an hour rather than to the exact session. Omitted for members with no recorded sign-in since the field shipped, which does not mean they never signed in.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
