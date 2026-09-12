# List offerable number countries API Reference

The phone number countries available to purchase, each with its flat
monthly price (cents), regulatory tier, whether it needs end-user KYC
(Tier 3/4), and per-feature availability (PSTN calls, WhatsApp, SMS,
and WhatsApp Business Calling outbound). Drives the country picker.
Tier-4 countries appear only when enabled.


## GET /v1/phone-numbers/countries

**List offerable number countries**

The phone number countries available to purchase, each with its flat
monthly price (cents), regulatory tier, whether it needs end-user KYC
(Tier 3/4), and per-feature availability (PSTN calls, WhatsApp, SMS,
and WhatsApp Business Calling outbound). Drives the country picker.
Tier-4 countries appear only when enabled.


### Responses

#### 200: Offerable countries, cheapest first.

**Response Body:**

- **countries** `array[object]`: 
  - **code** `string`: ISO 3166-1 alpha-2
  - **tier** `integer`: No description - one of: 1, 2, 3, 4
  - **monthlyCents** `integer`: Price a NEW number in this country costs per month, in cents, for the default (first) type. 1.5x the carrier's monthly cost, rounded up to a whole dollar, minimum $3.
  - **needsKyc** `boolean`: No description
  - **callsAvailable** `boolean`: Regular phone (PSTN) calling on the number, inbound + outbound. Available on every offerable country.
  - **whatsappAvailable** `boolean`: WhatsApp can be enabled on numbers from this country.
  - **smsAvailable** `boolean`: Whether this country's number type can do SMS. Use it to filter the picker when the buyer wants SMS (pair with `wantsSms` on purchase).
  - **outboundCallingAvailable** `boolean`: WhatsApp Business Calling (BIC) outbound availability, a Meta feature blocked in some countries. NOT the PSTN Calls feature (`callsAvailable`).
  - **inStock** `boolean`: Live carrier-stock snapshot (refreshed every 6h + on availability checks): false when NO offered type currently has deliverable inventory, so a purchase would fail. Treat as advisory; the purchase itself re-checks.
  - **preOrderable** `boolean`: At least one out-of-stock type here can be pre-ordered (see `types[].preOrderable`).
  - **types** `array[object]`: Every number type offered in this country (default first). Capabilities, KYC tier, monthly price, and stock are per type. The country-level fields above mirror the first (default) entry. Pass the chosen `numberType` to POST /v1/phone-numbers/purchase.

    - **numberType** `string`: No description - one of: local, mobile, national, toll_free
    - **tier** `integer,null`: Null on a `fulfilment: request` type, whose document tier is only known once its requirements are read. - one of: 1, 2, 3, 4, 
    - **needsKyc** `boolean`: No description
    - **monthlyCents** `integer`: Price a NEW number of this type costs per month, in cents.
    - **whatsappAvailable** `boolean`: Always false for toll_free (WhatsApp does not reliably register toll-free numbers).
    - **smsAvailable** `boolean`: No description
    - **callsAvailable** `boolean`: No description
    - **inStock** `boolean`: No description
    - **fulfilment** `string`: `request`: the carrier stocks this type nowhere and only sources it to order, so it is always a pre-order. - one of: instant, request
    - **preOrderable** `boolean`: Out of stock but orderable anyway. Submit KYC as usual (POST /v1/phone-numbers/kyc): we buy regular stock the moment it returns, otherwise the carrier sources the number. Usually 2 to 4 weeks, never guaranteed. Only document tiers (3/4) qualify, and nothing is billed until the number is active.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
