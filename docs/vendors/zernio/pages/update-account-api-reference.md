# Update account API Reference

Updates a connected account's display name or username override.

For X accounts on usage-based billing, also accepts an `xCapabilities`
object to toggle background API operations that incur X API pass-through costs.
Both fields are opt-in (default `false`). When off, no analytics syncs or DM
polling are performed for that account, and no API call is metered for those
operations. Publishing and deleting posts are always available regardless of
these toggles. Setting `xCapabilities` on a non-X account returns 400.


## PUT /v1/accounts/{accountId}

**Update account**

Updates a connected account's display name or username override.

For X accounts on usage-based billing, also accepts an `xCapabilities`
object to toggle background API operations that incur X API pass-through costs.
Both fields are opt-in (default `false`). When off, no analytics syncs or DM
polling are performed for that account, and no API call is metered for those
operations. Publishing and deleting posts are always available regardless of
these toggles. Setting `xCapabilities` on a non-X account returns 400.


### Parameters

- **accountId** (required) in path: No description

### Request Body

- **username** `string`: No description
- **displayName** `string`: No description
- **xCapabilities** `object`: X only. Per-account opt-in toggles for background API
operations that incur X API pass-through costs. Each call is
billed at the X tier rate. Either field can be
sent independently; omitted fields are unchanged.


### Responses

#### 200: Updated

**Response Body:**

- **message** `string`: No description
- **username** `string`: No description
- **displayName** `string`: No description
- **xCapabilities** `object`: Echo of the resulting `xCapabilities` state, returned only
when the request body included an `xCapabilities` object.

  - **analytics** `boolean`: No description
  - **inbox** `boolean`: No description

#### 400: Invalid request (e.g. xCapabilities on a non-X account)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## PATCH /v1/accounts/{accountId}

**Move account to another profile**

Moves a connected account to a different profile owned by the same
user. The target profile must belong to the same user as the account.

For API keys restricted to specific profiles, BOTH the source account's
current profile AND the target profile must be in the key's allowed set.
Calls with a target profile outside the key's scope return 403.


### Parameters

- **accountId** (required) in path: No description

### Request Body

- **profileId** (required) `string`: Target profile ID (must be a valid ObjectId and owned by the same user as the account).

### Responses

#### 200: Account moved

**Response Body:**

- **message** `string`: No description
- **profileId** `string`: No description

#### 400: Missing or invalid profileId

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: API key does not have access to the source account or target profile

#### 404: Account or target profile not found

---

## DELETE /v1/accounts/{accountId}

**Disconnect account**

Disconnects and removes a connected account. Repeating the call for an account already disconnected returns 404, the account stays in its 1h grace window and the disconnect is not re-run.

### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: Disconnected

**Response Body:**

- **message** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
