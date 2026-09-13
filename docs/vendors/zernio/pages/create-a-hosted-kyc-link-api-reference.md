# Create a hosted KYC link API Reference

Create a single-use, 7-day hosted KYC link that your end customer
completes WITHOUT a Zernio login. Useful when the person who holds the
ID and address is not your team. They fill the regulated verification on
a Zernio-hosted page; the number provisions under YOUR account once they
submit. Only regulated (KYC) countries are valid: a country that does not
require KYC returns 400.

White-label the page with `branding` (your company name, logo, brand
color). Supply `redirect_url` to send the end customer back to your own
site after a successful submit (completion params are appended; see
below). Listen for the `whatsapp.number.kyc_submitted` webhook to react
when the form is completed.


## POST /v1/phone-numbers/kyc/share

**Create a hosted KYC link**

Create a single-use, 7-day hosted KYC link that your end customer
completes WITHOUT a Zernio login. Useful when the person who holds the
ID and address is not your team. They fill the regulated verification on
a Zernio-hosted page; the number provisions under YOUR account once they
submit. Only regulated (KYC) countries are valid: a country that does not
require KYC returns 400.

White-label the page with `branding` (your company name, logo, brand
color). Supply `redirect_url` to send the end customer back to your own
site after a successful submit (completion params are appended; see
below). Listen for the `whatsapp.number.kyc_submitted` webhook to react
when the form is completed.


### Request Body

- **profileId** (required) `string`: No description
- **country** (required) `string`: ISO 3166-1 alpha-2 country code (must be a regulated/KYC country).
- **areaCode** `string`: Area code (NDC) the eventual number must be in. Hard constraint carried by the link; the end customer filling the form makes no area choice. Options come from GET /v1/phone-numbers/availability (areaOptions).
- **branding** `object`: Optional white-label of the hosted page the end customer sees.
- **redirect_url** `string`: Where to send the end customer's browser after a successful
submit. On completion Zernio appends `kyc=submitted` and
`country=<ISO-2>` as query params. When omitted, the hosted
page shows a built-in confirmation screen instead.


### Responses

#### 200: Hosted KYC link created.

**Response Body:**

- **url** `string`: The hosted link to send your end customer.
- **token** `string`: No description
- **expiresAt** `string` (date-time): No description

#### 400: Country does not require KYC (not a regulated country).

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
