# Select Google Business Profile location API Reference

Complete the headless Google Business Profile flow by saving the user's selected location. The pendingDataToken is returned in your redirect URL after OAuth completes (step=select_location). Tokens and profile data are stored server-side, so only the pendingDataToken is needed here. Use X-Connect-Token header if connecting via API key.


## POST /v1/connect/googlebusiness/select-location

**Select Google Business Profile location**

Complete the headless Google Business Profile flow by saving the user's selected location. The pendingDataToken is returned in your redirect URL after OAuth completes (step=select_location). Tokens and profile data are stored server-side, so only the pendingDataToken is needed here. Use X-Connect-Token header if connecting via API key.


### Request Body

- **profileId** (required) `string`: Profile ID from your connection flow
- **locationId** (required) `string`: The Google Business Profile location ID selected by the user
- **accountId** `string`: Optional but recommended. The Google Business Profile Account resource name ("accounts/123") that owns the selected location (returned per-location by GET /v1/connect/googlebusiness/locations). When provided, the location is resolved directly instead of by enumerating the account, which is required for accounts that own many locations. Omit only for small accounts.

- **pendingDataToken** (required) `string`: Token from the OAuth callback redirect (pendingDataToken query param). Tokens and profile data are retrieved server-side from this token.
- **redirect_url** `string`: Optional custom redirect URL to return to after selection

### Responses

#### 200: Google Business Profile location connected successfully

**Response Body:**

- **message** `string`: No description
- **redirect_url** `string`: Redirect URL if custom redirect_url was provided
- **account** `object`: 
  - **accountId** `string`: ID of the created SocialAccount
  - **platform** `string`: No description - one of: googlebusiness
  - **username** `string`: No description
  - **displayName** `string`: No description
  - **isActive** `boolean`: No description
  - **selectedLocationName** `string`: Human-readable location display name, NOT a resource name. Do not use it to build API paths.
  - **selectedLocationId** `string`: Bare Google Business Profile location id. Combine with the Google Business Profile account id as accounts/{gbpAccountId}/locations/{selectedLocationId} to form the location resource names that gmb-reviews/batch expects in locationNames.

#### 400: Missing required fields (profileId, locationId, or tempToken), or the provided accountId is not one of the accounts this connection manages

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: User does not have access to the specified profile

#### 404: Selected location not found in available locations

#### 500: Failed to save Google Business Profile connection

---

---
