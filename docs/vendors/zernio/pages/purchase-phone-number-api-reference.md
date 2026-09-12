# Purchase phone number API Reference

Payment-first: the system provisions a number and auto-assigns it, unless you pass
`phoneNumber` to buy one exact number from `GET /v1/phone-numbers/available`. With
usage-based billing active and a payment method on file, the
number provisions inline and bills per month on your usage-based invoice (there is
no checkout redirect). No payment method on file returns `402 PAYMENT_REQUIRED`;
a regulated country returns `202` with `status: "kyc_required"` and a `kycUrl`.

The monthly price is the one `GET /v1/phone-numbers/countries` quotes for that
country and `numberType` at the time of purchase, and it is stamped on the number:
later rate-card changes never move a number you already own.

Requires usage-based billing (the Usage plan). The maximum number of phone numbers
is determined by the user's plan.


## POST /v1/phone-numbers/purchase

**Purchase phone number**

Payment-first: the system provisions a number and auto-assigns it, unless you pass
`phoneNumber` to buy one exact number from `GET /v1/phone-numbers/available`. With
usage-based billing active and a payment method on file, the
number provisions inline and bills per month on your usage-based invoice (there is
no checkout redirect). No payment method on file returns `402 PAYMENT_REQUIRED`;
a regulated country returns `202` with `status: "kyc_required"` and a `kycUrl`.

The monthly price is the one `GET /v1/phone-numbers/countries` quotes for that
country and `numberType` at the time of purchase, and it is stamped on the number:
later rate-card changes never move a number you already own.

Requires usage-based billing (the Usage plan). The maximum number of phone numbers
is determined by the user's plan.


### Request Body

- **profileId** (required) `string`: Preferred profile for the number. One number = one profile, so when the requested profile already holds a number the API assigns the next free profile instead (or creates one) and returns the actual assignment in `profileId` on the response.

- **country** `string`: ISO 3166-1 alpha-2 country for the number (default US). International numbers require usage-based billing. Tier 3/4 countries return 202 { status: "kyc_required", kycUrl }. The customer must complete KYC at that URL before the number is ordered. See GET /v1/phone-numbers/countries.

- **numberType** `string`: Which of the country's offered number types to order (see `types[]` on GET /v1/phone-numbers/countries). Omitted = the country's default type, which is always the WhatsApp-safe choice. Capabilities, price, and KYC requirements are per (country, type): toll_free can never connect WhatsApp (400 when combined with connectWhatsapp:true), and wantsSms:true requires an SMS-capable type.
 - one of: local, mobile, national, toll_free
- **areaCode** `string`: Area code (national destination code, e.g. 11 for Sao Paulo) the number must be in. Hard constraint: when the area has no deliverable inventory the purchase fails with 409 code AREA_CODE_UNAVAILABLE instead of assigning a number from another area, and later replacements stay in this area too. Omit for any area. Get live options from GET /v1/phone-numbers/availability (areaOptions).

- **phoneNumber** `string`: One exact number to buy, in E.164, taken from GET /v1/phone-numbers/available. Hard constraint: when it is no longer available (bought by someone else, or WhatsApp's buy-time check rejects it) the purchase fails with 409 code PHONE_NUMBER_UNAVAILABLE instead of assigning another number; search again and pick another. Only for countries and types that activate instantly: a regulated one (202 kyc_required) returns 400 when phoneNumber is set.

- **connectWhatsapp** `boolean`: A phone number is the unit; WhatsApp is one optional feature. Pass false to buy a STANDALONE number (Calls/SMS only): provisioning skips the Meta pre-verify/OTP steps and the number activates immediately. Omitted defaults to the WhatsApp provisioning path. WhatsApp can be connected to a standalone number later from the connect flow.

- **wantsSms** `boolean`: SMS capability is per-number, not per-country. Pass true to provision from the SMS-capable inventory pool so the number can actually text (see also GET /v1/phone-numbers/available with sms=true, and smsAvailable on GET /v1/phone-numbers/countries).

- **wantsWhatsapp** `boolean`: Declare WhatsApp intent on a STANDALONE purchase (connectWhatsapp:false). The number still activates and bills immediately, but if WhatsApp's buy-time check rejects the assigned number, it is automatically swapped for a WhatsApp-eligible one during the purchase instead of being delivered with WhatsApp unavailable. Ignored on the WhatsApp provisioning path (connectWhatsapp omitted or true), which always delivers a WhatsApp-verified number.

- **purchaseIntentId** `string`: Optional idempotency key. Send the same value when retrying a purchase: if a number was already bought under this key, the API returns { status: "already_purchased", numberId, phoneNumber, profileId } instead of provisioning a second number. Generate a fresh key for each genuinely new purchase.

- **allowMultiple** `boolean`: Any second purchase within 10 minutes of a previous one is rejected with 409 code PURCHASE_VELOCITY as duplicate protection. Pass true to confirm the additional purchase is intentional (e.g. bulk provisioning).


### Responses

#### 200: Either a checkout URL (first number) or the provisioned phone number (subsequent numbers).


**Response Body:**

*One of the following:*
  - **message** `string`: No description
  - **checkoutUrl** `string` (uri): No description
  - **message** `string`: No description
  - **phoneNumber** `object`: 
    - **id** `string`: No description
    - **phoneNumber** `string`: No description
    - **status** `string`: No description
    - **country** `string`: No description
    - **provisionedAt** `string` (date-time): No description
    - **metaPreverifiedId** `string`: No description
    - **metaVerificationStatus** `string`: No description
    - **profileId** `string`: The profile the number was actually assigned to.
  - **status** `string`: No description - one of: already_purchased
  - **numberId** `string`: No description
  - **phoneNumber** `string`: No description
  - **profileId** `string`: The profile the number was actually assigned to.

#### 202: Country requires end-user KYC before the number can be ordered.

**Response Body:**

- **status** `string`: No description - one of: kyc_required
- **country** `string`: No description
- **numberType** `string`: The type that will be ordered after KYC approval.
- **kycUrl** `string`: No description

#### 400: Plan limit reached, profileId required, or country not available

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Payment method required (usage-based billing account with no card on file). Response body carries code: PAYMENT_REQUIRED; add a card, then retry.

#### 403: A paid plan is required

#### 409: Either duplicate-purchase protection (code PURCHASE_VELOCITY: another number was purchased within the last 10 minutes; retry with allowMultiple: true to confirm), or the requested areaCode has no deliverable inventory right now (code AREA_CODE_UNAVAILABLE: pick another area or omit areaCode; PHONE_NUMBER_UNAVAILABLE: search again and pick another number).


**Response Body:**

- **error** `string`: No description
- **code** `string`: No description - one of: PURCHASE_VELOCITY, AREA_CODE_UNAVAILABLE, PHONE_NUMBER_UNAVAILABLE

#### 422: International numbers require usage-based billing (legacy Stripe users are US-only). Response body code: USAGE_BILLING_REQUIRED.

---

---
