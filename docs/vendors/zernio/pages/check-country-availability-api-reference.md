# Check country availability API Reference

Pre-purchase check, so you can warn BEFORE a customer invests in KYC
(regulated review is async, 1-3 days). Tells you whether we have
deliverable inventory, and what address the customer needs:
  - `addressConstraint: geo`  → the registered address MUST be in one of
    the returned `areas` (the only place we have stock). A different-area
    address passes pre-approval but the number can never be assigned.
  - `addressConstraint: country` → any in-country address works.
  - `addressConstraint: none` → field-only / instant country, no address.
Call this before starting the KYC form for regulated countries.


## GET /v1/phone-numbers/availability

**Check country availability**

Pre-purchase check, so you can warn BEFORE a customer invests in KYC
(regulated review is async, 1-3 days). Tells you whether we have
deliverable inventory, and what address the customer needs:
  - `addressConstraint: geo`  → the registered address MUST be in one of
    the returned `areas` (the only place we have stock). A different-area
    address passes pre-approval but the number can never be assigned.
  - `addressConstraint: country` → any in-country address works.
  - `addressConstraint: none` → field-only / instant country, no address.
Call this before starting the KYC form for regulated countries.


### Parameters

- **country** (required) in query: ISO-2 country code.
- **numberType** (optional) in query: Check a specific offered type (stock and address constraints are per type). Omitted = the country's default type.
- **sms** (optional) in query: Pass true when the buyer wants SMS: availability, areas, and areaOptions then describe the SMS-capable pool (an SMS purchase orders from it), not the wider voice-only pool.

### Responses

#### 200: Availability + address constraint.

**Response Body:**

- **country** `string`: No description
- **numberType** `string`: No description
- **available** `boolean`: Whether deliverable voice inventory exists right now.
- **preOrderable** `boolean`: Nothing deliverable now, but this pair can be pre-ordered: submit KYC as usual and we buy regular stock the moment it returns, otherwise the carrier sources the number (usually 2 to 4 weeks, never guaranteed). Only document tiers (3/4) qualify.
- **addressConstraint** `string`: No description - one of: geo, country, none
- **areas** `array[string]`: For `geo` only: the area(s) the registered address must be in.
- **areaOptions** `array[object]`: Live inventory grouped by area code. For US and CA this is the full country inventory (every area code with stock, recognizable metros listed first, then alphabetical); other countries are ordered largest stock first; they list the areas in the latest inventory page (up to 500 numbers, which for most countries is the entire pool). Empty when out of stock (or the area lookup failed). Pass a chosen `ndc` as `areaCode` on POST /v1/phone-numbers/purchase (or on the KYC submit for regulated countries) to require that area.

  - **ndc** `string`: Area code (national destination code), e.g. "11".
  - **name** `string`: Area name: "City, ST" for US/CA (e.g. "Miami, FL"), city otherwise (e.g. "Sao Paulo").
  - **count** `integer`: Numbers available in this area: country-wide count for US/CA, numbers seen on the latest inventory page otherwise.

#### 400: Country not offerable, or the inventory provider rejected the lookup (its 4xx status is forwarded as-is).

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 502: The inventory provider was unreachable or returned an unclassified error.

---

---
