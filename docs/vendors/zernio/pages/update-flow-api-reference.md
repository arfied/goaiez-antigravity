# Update flow API Reference

Update metadata (name, categories, endpointUri) of a DRAFT flow. Published flows are immutable.


## GET /v1/whatsapp/flows/{flowId}

**Get flow**

Get details for a specific flow, including status, categories, validation errors, and preview URL.


### Parameters

- **flowId** (required) in path: Flow ID
- **accountId** (required) in query: WhatsApp account ID
- **fields** (optional) in query: Comma-separated fields to return (default: id,name,status,categories,validation_errors,json_version,preview,data_api_version,endpoint_uri)

### Responses

#### 200: Flow details

**Response Body:**

- **success** `boolean`: No description
- **flow** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **status** `string`: No description
  - **categories** `array[string]`: 
  - **validation_errors** `array[object]`: 
    Type: `object`
  - **json_version** `string`: No description
  - **preview** `object`: 
    - **preview_url** `string`: No description
    - **expires_at** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Flow or account not found

---

## PATCH /v1/whatsapp/flows/{flowId}

**Update flow**

Update metadata (name, categories, endpointUri) of a DRAFT flow. Published flows are immutable.


### Parameters

- **flowId** (required) in path: Flow ID

### Request Body

- **accountId** (required) `string`: WhatsApp account ID
- **name** `string`: New flow name
- **categories** `array`: No description
- **endpointUri** `string`: HTTPS-only data exchange endpoint for the flow. Settable only while the flow is in DRAFT, and the flow's uploaded Flow JSON must declare data_api_version "3.0" for the endpoint to be used.

### Responses

#### 200: Flow updated

**Response Body:**

- **success** `boolean`: No description

#### 400: At least one of name, categories or endpointUri is required, or flow is not in DRAFT status

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account or flow not found

---

## DELETE /v1/whatsapp/flows/{flowId}

**Delete flow**

Delete a DRAFT flow. This is irreversible. Only flows in DRAFT status can be deleted.


### Parameters

- **flowId** (required) in path: Flow ID
- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Flow deleted

**Response Body:**

- **success** `boolean`: No description

#### 400: Flow is not in DRAFT status

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account or flow not found

---

---
