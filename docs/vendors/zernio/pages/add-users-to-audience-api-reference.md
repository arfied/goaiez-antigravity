# Add users to audience API Reference

Upload user data to a customer_list audience. Data is SHA256-hashed server-side before sending to the platform.
Email is used on every platform; phone is used on Meta only (other platforms ignore it). On TikTok and Pinterest,
the first upload also provisions the audience (deferred create). LinkedIn uploads are full-replace. Max 10,000 users per request.

customer_list only. A LinkedIn `company_list` audience takes company rows, not people: send those to
`POST /v1/ads/audiences/{audienceId}/companies`. This endpoint 422s for every other audience type.


## POST /v1/ads/audiences/{audienceId}/users

**Add users to audience**

Upload user data to a customer_list audience. Data is SHA256-hashed server-side before sending to the platform.
Email is used on every platform; phone is used on Meta only (other platforms ignore it). On TikTok and Pinterest,
the first upload also provisions the audience (deferred create). LinkedIn uploads are full-replace. Max 10,000 users per request.

customer_list only. A LinkedIn `company_list` audience takes company rows, not people: send those to
`POST /v1/ads/audiences/{audienceId}/companies`. This endpoint 422s for every other audience type.


### Parameters

- **audienceId** (required) in path: The Zernio audience id (the id field of GET /v1/ads/audiences), not the platform segment id.

### Request Body

- **users** (required) `array`: No description

### Responses

#### 200: Users added

**Response Body:**

- **message** `string`: No description
- **numReceived** `integer`: No description
- **numInvalid** `integer`: No description

#### 400: Invalid input (malformed audienceId, empty users array, missing email/phone)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required. Legacy plans need the Ads add-on; included by default on usage-based plans.

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 422: Audience is not a customer_list type or has no platform ID yet

---

---
