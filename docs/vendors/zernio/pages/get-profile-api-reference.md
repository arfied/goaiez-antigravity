# Get profile API Reference

Returns a single profile by ID, including its name, color, and default status.

## GET /v1/profiles/{profileId}

**Get profile**

Returns a single profile by ID, including its name, color, and default status.

### Parameters

- **profileId** (required) in path: No description

### Responses

#### 200: Profile

**Response Body:**

- **profile**: `Profile` - See schema definition

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## PUT /v1/profiles/{profileId}

**Update profile**

Updates a profile's name, description, color, or default status.

### Parameters

- **profileId** (required) in path: No description

### Request Body

- **name** `string`: No description
- **description** `string,null`: Set to null to clear the description.
- **color** `string`: No description
- **isDefault** `boolean`: No description

### Responses

#### 200: Updated

**Response Body:**

- **message** `string`: No description
- **profile**: `Profile` - See schema definition

#### 400: Invalid request, including a body that carries none of name, description, color or isDefault (code: missing_required_field).

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 409: A profile with this name already exists (code: profile_name_conflict).

---

## DELETE /v1/profiles/{profileId}

**Delete profile**

Permanently deletes a profile. Active connected accounts block deletion (returns 400) - disconnect them first. Any remaining disconnected accounts and provisioned WhatsApp numbers are moved to another of your profiles (a new one is created only if needed), never deleted.

### Parameters

- **profileId** (required) in path: No description

### Responses

#### 200: Deleted

**Response Body:**

- **message** `string`: No description

#### 400: Profile has active connected accounts; disconnect them first

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Forbidden

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---
