# List Facebook pages API Reference

Returns all Facebook pages the connected account has access to, including the currently selected page.

## GET /v1/accounts/{accountId}/facebook-page

**List Facebook pages**

Returns all Facebook pages the connected account has access to, including the currently selected page.

### Parameters

- **accountId** (required) in path: No description
- **refresh** (optional) in query: When true, bypasses the page cache and fetches fresh pages from Meta. Rate-limited server-side to 1 refresh per 60s. Pages no longer accessible to the connected account will be removed from the list on refresh.


### Responses

#### 200: Pages list

**Response Body:**

- **pages** `array[object]`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **username** `string`: No description
  - **category** `string`: No description
  - **fan_count** `integer`: No description
- **selectedPageId** `string`: No description
- **cached** `boolean`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

---

## PUT /v1/accounts/{accountId}/facebook-page

**Update Facebook page**

Switch which Facebook Page is active for a connected account.

### Parameters

- **accountId** (required) in path: No description

### Request Body

- **selectedPageId** (required) `string`: No description

### Responses

#### 200: Page updated

**Response Body:**

- **message** `string`: No description
- **selectedPage** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description

#### 400: Page not in available pages

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

---

---
