# Update Google Business Profile location API Reference

Switch which Google Business Profile location is active for a connected account.

## GET /v1/accounts/{accountId}/gmb-locations

**List Google Business Profile locations**

Returns Google Business Profile locations the connected account can access, plus the currently selected location. The list is bounded (see hasMore); for accounts that own many locations, use the search or filter query params to find a specific one instead of loading them all, or raise limit to enumerate an account with more than 100 locations.


### Parameters

- **accountId** (required) in path: No description
- **search** (optional) in query: Free-text search on the business name, applied server-side by Google. Use for accounts with many locations.
- **filter** (optional) in query: Raw Google Business Information API filter expression (advanced; takes precedence over search), e.g. storeCode="LH279411".
- **limit** (optional) in query: Max locations to return (default 100, max 500). Raise it to enumerate an account with more than 100 locations; for accounts with thousands, use search/filter instead.

### Responses

#### 200: Locations list

**Response Body:**

- **locations** `array[object]`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **accountId** `string`: No description
  - **accountName** `string`: No description
  - **address** `string`: No description
  - **category** `string`: No description
  - **websiteUrl** `string`: No description
  - **storeCode** `string`: No description
- **hasMore** `boolean`: True when more locations exist than were returned (use search to narrow down).
- **selectedLocationId** `string`: No description
- **cached** `boolean`: No description

#### 400: Invalid query parameter (e.g. limit out of range)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

---

## PUT /v1/accounts/{accountId}/gmb-locations

**Update Google Business Profile location**

Switch which Google Business Profile location is active for a connected account.

### Parameters

- **accountId** (required) in path: No description

### Request Body

- **selectedLocationId** (required) `string`: No description
- **googleAccountId** `string`: Optional but recommended. The Google Business Profile Account resource name ("accounts/123") that owns the new location (from GET gmb-locations). When provided, the location is resolved directly instead of by enumerating the account, which is required for accounts with many locations. Named `googleAccountId` to disambiguate from the path `accountId` (the Zernio account). The legacy field name `accountId` is still accepted for backwards compatibility.


### Responses

#### 200: Location updated

**Response Body:**

- **message** `string`: No description
- **selectedLocation** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description

#### 400: Location not in available locations, or the provided googleAccountId is not one of the accounts this connection manages

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

---

---
