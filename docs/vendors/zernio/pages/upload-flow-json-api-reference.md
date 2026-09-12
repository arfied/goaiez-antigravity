# Upload flow JSON API Reference

Upload or update the Flow JSON for a DRAFT flow. The Flow JSON defines all screens,
components (text inputs, dropdowns, date pickers, etc.), and navigation.

Meta validates the JSON on upload and returns any validation errors.
See: https://developers.facebook.com/docs/whatsapp/flows/reference/flowjson


## GET /v1/whatsapp/flows/{flowId}/json

**Get flow JSON asset**

Get the flow JSON asset metadata, including a temporary download URL for the Flow JSON file.


### Parameters

- **flowId** (required) in path: Flow ID
- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Flow JSON asset

**Response Body:**

- **success** `boolean`: No description
- **assets** `array[object]`: 
  - **name** `string`: No description (example: "flow.json")
  - **asset_type** `string`: No description (example: "FLOW_JSON")
  - **download_url** `string`: Temporary URL to download the flow JSON

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

## PUT /v1/whatsapp/flows/{flowId}/json

**Upload flow JSON**

Upload or update the Flow JSON for a DRAFT flow. The Flow JSON defines all screens,
components (text inputs, dropdowns, date pickers, etc.), and navigation.

Meta validates the JSON on upload and returns any validation errors.
See: https://developers.facebook.com/docs/whatsapp/flows/reference/flowjson


### Parameters

- **flowId** (required) in path: Flow ID

### Request Body

- **accountId** (required) `string`: WhatsApp account ID
- **flow_json**: Platform-specific settings (see schema definitions below)

### Responses

#### 200: Flow JSON uploaded

**Response Body:**

- **success** `boolean`: No description
- **validation_errors** `array[object]`: Empty array if valid; otherwise, contains validation error details from Meta
  - **error** `string`: No description
  - **error_type** `string`: No description
  - **message** `string`: No description
  - **line_start** `integer`: No description
  - **line_end** `integer`: No description
  - **column_start** `integer`: No description
  - **column_end** `integer`: No description

#### 400: Invalid JSON or flow is not in DRAFT status

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
