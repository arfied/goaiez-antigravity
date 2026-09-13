# List Google Business Profile locations API Reference

For headless flows. Returns the list of Google Business Profile locations the user can manage. Use pendingDataToken (from the OAuth callback redirect) to list locations without consuming the token, so it remains available for select-location. Use X-Connect-Token header if connecting via API key.


## GET /v1/connect/googlebusiness/locations

**List Google Business Profile locations**

For headless flows. Returns the list of Google Business Profile locations the user can manage. Use pendingDataToken (from the OAuth callback redirect) to list locations without consuming the token, so it remains available for select-location. Use X-Connect-Token header if connecting via API key.


### Parameters

- **profileId** (optional) in query: Profile ID from your connection flow. Required for auth validation when provided.
- **pendingDataToken** (optional) in query: Token from the OAuth callback redirect. Preferred over tempToken because it preserves server-side token storage. One of pendingDataToken or tempToken is required.
- **tempToken** (optional) in query: Legacy. Direct Google access token. Use pendingDataToken instead when available.
- **search** (optional) in query: Free-text search on the business name, applied server-side by Google. Use this for accounts that own many locations (the response is bounded, see hasMore) so the user can find a specific location without loading the full list.

- **filter** (optional) in query: Raw Google Business Information API filter expression (advanced; takes precedence over search). Supports fields such as title, storeCode, storefront_address.postal_code, labels and categories, e.g. storeCode="LH279411". See Google's "Work with location data" guide.


### Responses

#### 200: List of Google Business Profile locations available for connection

**Response Body:**

- **locations** `array[object]`: 
  - **id** `string`: Location ID
  - **name** `string`: Business name
  - **accountId** `string`: Google Business Profile Account ID
  - **accountName** `string`: Account name
  - **address** `string`: Business address
  - **category** `string`: Business category
  - **storeCode** `string`: Store code set on the location in Google Business Profile (if any)
- **hasMore** `boolean`: True when more locations exist than were returned (the list is bounded). Prompt the user to narrow the result set with search.


#### 400: Missing required parameters (profileId or tempToken)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 500: Failed to fetch locations (e.g., invalid token, insufficient permissions)

**Response Body:**

- **error** `string`: No description

---

---
