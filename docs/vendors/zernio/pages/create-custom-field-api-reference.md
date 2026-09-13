# Create custom field API Reference

Create a new custom field definition. Supported types are text, number, date, boolean, and select.

## GET /v1/custom-fields

**List custom field definitions**

Returns all custom field definitions. Optionally filter by profile.

### Parameters

- **profileId** (optional) in query: Filter by profile. Omit to list across all profiles

### Responses

#### 200: List of custom field definitions

**Response Body:**

- **success** `boolean`: No description
- **fields** `array[object]`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **slug** `string`: No description
  - **type** `string`: No description - one of: text, number, date, boolean, select
  - **options** `array[string]`: 
  - **createdAt** `string` (date-time): No description

#### 400: Invalid profileId format

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

## POST /v1/custom-fields

**Create custom field**

Create a new custom field definition. Supported types are text, number, date, boolean, and select.

### Request Body

- **profileId** (required) `string`: No description
- **name** (required) `string`: No description
- **slug** `string`: Auto-generated from name if not provided
- **type** (required) `string`: No description - one of: text, number, date, boolean, select
- **options** `array`: Required for select type

### Responses

#### 200: Custom field created

**Response Body:**

- **success** `boolean`: No description
- **field** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **slug** `string`: No description
  - **type** `string`: No description - one of: text, number, date, boolean, select
  - **options** `array[string]`: 
  - **createdAt** `string` (date-time): No description

#### 400: Invalid request body

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 409: Duplicate slug

---

---
