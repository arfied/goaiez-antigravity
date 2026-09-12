# List Discord guild channels API Reference

Returns the text, announcement, and forum channels in the connected Discord guild. Use this to discover available channels when switching the connected channel via PATCH /v1/accounts/{accountId}/discord-settings.

## GET /v1/accounts/{accountId}/discord-channels

**List Discord guild channels**

Returns the text, announcement, and forum channels in the connected Discord guild. Use this to discover available channels when switching the connected channel via PATCH /v1/accounts/{accountId}/discord-settings.

### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: Channel list

**Response Body:**

- **channels** `array[object]`: 
  - **id** `string`: Channel snowflake ID
  - **name** `string`: Channel name
  - **type** `integer`: Channel type: 0 (text), 5 (announcement), 15 (forum)

#### 400: Not a Discord account or missing guild info

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

---

---
