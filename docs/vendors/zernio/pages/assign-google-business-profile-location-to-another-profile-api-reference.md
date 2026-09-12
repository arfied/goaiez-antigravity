# Assign Google Business Profile location to another profile API Reference

Connect a Google Business Profile location onto a DIFFERENT profile by reusing the OAuth grant from an already-connected Google Business Profile account, with no browser and no re-authorization. Built for agencies whose single Google account has manager access to many client locations and who run one profile per client: connect one location the normal way (browser OAuth), then bulk-assign the rest onto each client's profile via this endpoint. The path `accountId` is a SOURCE connected Google Business Profile account (the token holder); the body `profileId` is the TARGET profile. Returns 409 if the target profile already has a Google Business Profile connection (switch its location with PUT gmb-locations instead).


## POST /v1/accounts/{accountId}/gmb-locations/assign

**Assign Google Business Profile location to another profile**

Connect a Google Business Profile location onto a DIFFERENT profile by reusing the OAuth grant from an already-connected Google Business Profile account, with no browser and no re-authorization. Built for agencies whose single Google account has manager access to many client locations and who run one profile per client: connect one location the normal way (browser OAuth), then bulk-assign the rest onto each client's profile via this endpoint. The path `accountId` is a SOURCE connected Google Business Profile account (the token holder); the body `profileId` is the TARGET profile. Returns 409 if the target profile already has a Google Business Profile connection (switch its location with PUT gmb-locations instead).


### Parameters

- **accountId** (required) in path: A source connected Google Business Profile account whose OAuth grant is reused.

### Request Body

- **profileId** (required) `string`: Target profile to connect the location onto.
- **selectedLocationId** (required) `string`: The Google Business Profile location ID to assign (e.g. "locations/123").
- **googleAccountId** `string`: Optional but recommended. The Google Business Profile Account resource name ("accounts/123") that owns the location (from GET gmb-locations). When provided the location is resolved directly instead of by enumerating the account, required for accounts with many locations.


### Responses

#### 200: Location assigned to the target profile

**Response Body:**

- **message** `string`: No description
- **account** `object`: 
  - **accountId** `string`: No description
  - **platform** `string`: No description
  - **username** `string`: No description
  - **displayName** `string`: No description
  - **isActive** `boolean`: No description
  - **selectedLocationName** `string`: Human-readable location display name (e.g. "Snap Fitness Dianella"), NOT a resource name. Do not use it to build API paths.
  - **selectedLocationId** `string`: Bare Google Business Profile location id (digits only). Combine with the Google Business Profile account id as accounts/{gbpAccountId}/locations/{selectedLocationId} to form the location resource names that gmb-reviews/batch expects in locationNames.

#### 400: Invalid body, selected location not found under the Google account, or the provided googleAccountId is not one of the accounts this connection manages

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Payment required, or target profile exceeds plan limit

#### 404: Source Google Business Profile account not found

#### 409: Target profile already has a Google Business Profile connection (use PUT gmb-locations to switch its location)

---

---
