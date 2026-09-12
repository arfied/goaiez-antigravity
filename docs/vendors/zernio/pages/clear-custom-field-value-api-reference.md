# Clear custom field value API Reference

Remove a custom field value from a contact. The field definition is not affected.

## PUT /v1/contacts/{contactId}/fields/{slug}

**Set custom field value**

Set or overwrite a custom field value on a contact. The value type must match the field definition.

### Parameters

- **contactId** (required) in path: No description
- **slug** (required) in path: No description

### Request Body

- **value** (required): Field value (type depends on field definition)

### Responses

#### 200: Field value set

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## DELETE /v1/contacts/{contactId}/fields/{slug}

**Clear custom field value**

Remove a custom field value from a contact. The field definition is not affected.

### Parameters

- **contactId** (required) in path: No description
- **slug** (required) in path: No description

### Responses

#### 200: Field value cleared

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
