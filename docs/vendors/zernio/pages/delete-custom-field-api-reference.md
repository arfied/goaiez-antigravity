# Delete custom field API Reference

Delete a custom field definition and remove its values from all contacts.

## PATCH /v1/custom-fields/{fieldId}

**Update custom field**

Update a custom field definition. The field type cannot be changed after creation.

### Parameters

- **fieldId** (required) in path: No description

### Request Body

- **name** `string`: No description
- **options** `array`: No description

### Responses

#### 200: Custom field updated

**Response Body:**

- **success** `boolean`: No description
- **field** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **slug** `string`: No description
  - **type** `string`: No description
  - **options** `array[string]`: 

#### 400: Invalid fieldId or request body

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## DELETE /v1/custom-fields/{fieldId}

**Delete custom field**

Delete a custom field definition and remove its values from all contacts.

### Parameters

- **fieldId** (required) in path: No description

### Responses

#### 200: Custom field deleted

#### 400: Invalid fieldId format

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
