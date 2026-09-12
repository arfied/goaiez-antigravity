# Create group API Reference

Creates a new account group with a name and a list of account IDs.
Accounts can belong to different profiles; the caller must have access to
every account's profile. Group names must be unique per user.


## GET /v1/account-groups

**List groups**

Returns all account groups visible to the authenticated user. Groups can
contain accounts from multiple profiles. For API keys scoped to specific
profiles, only groups whose accounts all live in allowed profiles are
returned.


### Responses

#### 200: Groups

**Response Body:**

- **groups** `array[object]`: 
  - **_id** `string`: No description
  - **name** `string`: No description
  - **accountIds** `array[string]`: 
  - **createdBy** `string`: No description
  - **profileId** `string`: Legacy field. Present only on groups created before
cross-profile groups were supported. New groups omit it.


#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

## POST /v1/account-groups

**Create group**

Creates a new account group with a name and a list of account IDs.
Accounts can belong to different profiles; the caller must have access to
every account's profile. Group names must be unique per user.


### Request Body

- **name** (required) `string`: No description
- **accountIds** (required) `array`: No description
- **profileId** `string`: Deprecated. Accepted for backward compatibility but ignored.
Groups are no longer scoped to a single profile.


### Responses

#### 201: Created

**Response Body:**

- **message** `string`: No description
- **group** `object`: 
  - **_id** `string`: No description
  - **name** `string`: No description
  - **accountIds** `array[string]`: 

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 409: Group name already exists

---

---
