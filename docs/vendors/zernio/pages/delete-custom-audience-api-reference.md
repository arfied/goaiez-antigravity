# Delete custom audience API Reference

Deletes the audience from both the platform and the local database. `saved_targeting` audiences exist only on Zernio, so only the local record is removed.

## GET /v1/ads/audiences/{audienceId}

**Get audience details**

Returns the local audience record and fresh data from Meta (if available).

### Parameters

- **audienceId** (required) in path: The Zernio audience id (the id field of GET /v1/ads/audiences), not the platform segment id.

### Responses

#### 200: Audience details

**Response Body:**

- **audience** `object`: No description
- **platformData** `object,null`: Fresh data from the platform API

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required. Legacy plans need the Ads add-on; included by default on usage-based plans.

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## PUT /v1/ads/audiences/{audienceId}

**Update an audience**

Update an audience. `saved_targeting` audiences accept `name`, `description`, and `spec`
(full replacement, no merge, Zernio-only, no platform call). Platform audiences
(uploaded/website/lookalike) accept `name` and `description` only, updated on the
platform first and then mirrored locally; their rules are immutable, so `spec` returns
400 for them. Platform audience updates are Meta-only for now (other platforms return
501). Ads already created from a saved_targeting audience are unaffected, they snapshot
the targeting at creation.


### Parameters

- **audienceId** (required) in path: No description

### Request Body

- **name** `string`: No description
- **description** `string`: No description
- **spec**: Full replacement for the stored targeting spec.

### Responses

#### 200: Audience updated

**Response Body:**

- **audience** `object`: No description
- **message** `string`: No description

#### 400: Invalid body (no fields provided, malformed spec, or spec on a platform audience)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required. Legacy plans need the Ads add-on; included by default on usage-based plans.

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 422: The audience has no platform counterpart to update

#### 501: Platform audience updates are only supported on Meta

---

## DELETE /v1/ads/audiences/{audienceId}

**Delete custom audience**

Deletes the audience from both the platform and the local database. `saved_targeting` audiences exist only on Zernio, so only the local record is removed.

### Parameters

- **audienceId** (required) in path: No description

### Responses

#### 200: Audience deleted

**Response Body:**

- **message** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required. Legacy plans need the Ads add-on; included by default on usage-based plans.

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

---
