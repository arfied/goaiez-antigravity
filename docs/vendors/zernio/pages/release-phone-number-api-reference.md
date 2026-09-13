# Release phone number API Reference

Release a purchased phone number. This will:
1. Disconnect any linked WhatsApp account
2. Decrement the Stripe subscription quantity (or cancel if last number)
3. Release the number from Telnyx
4. Mark the number as released


## GET /v1/phone-numbers/{id}

**Get phone number**

Retrieve the current status of a purchased phone number. Poll this to
track Meta pre-verification (US sync path) and, for regulated (Tier 3/4)
numbers, the async lifecycle: pending_regulatory → active (or
regulatory_declined). When a regulated number has an Onfido ID step,
`onfidoVerificationUrl` appears here once the order is placed. Forward
it to the end user. (Or subscribe to the whatsapp.number.* webhooks
instead of polling.)


### Parameters

- **id** (required) in path: Phone number record ID

### Responses

#### 200: Phone number retrieved successfully

**Response Body:**

- **phoneNumber** `object`: 
  - **id** `string`: No description
  - **phoneNumber** `string`: No description
  - **status** `string`: No description - one of: pending_payment, pending_regulatory, regulatory_declined, provisioning, verifying, active, suspended, releasing, released
  - **country** `string`: No description
  - **metaPreverifiedId** `string`: No description
  - **metaVerificationStatus** `string`: No description
  - **onfidoVerificationUrl** `string,null`: For a regulated number with an Onfido ID step: the link to forward to the end user. Appears once the order is placed; null otherwise.
  - **endUserFirstName** `string,null`: No description
  - **endUserLastName** `string,null`: No description
  - **regulatoryDeclineReason** `string,null`: Reviewer rejection reason when status is regulatory_declined.
  - **provisionedAt** `string` (date-time): No description
  - **sipTrunkId** `string,null`: SIP trunk the number is attached to; null when not trunked. While attached, enabling Calls or WhatsApp calling, requesting WhatsApp verification, and releasing the number all return 409.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## DELETE /v1/phone-numbers/{id}

**Release phone number**

Release a purchased phone number. This will:
1. Disconnect any linked WhatsApp account
2. Decrement the Stripe subscription quantity (or cancel if last number)
3. Release the number from Telnyx
4. Mark the number as released


### Parameters

- **id** (required) in path: Phone number record ID

### Responses

#### 200: Phone number released successfully

**Response Body:**

- **message** `string`: No description
- **phoneNumber** `object`: 
  - **id** `string`: No description
  - **phoneNumber** `string`: No description
  - **status** `string`: "released"
  - **releasedAt** `string` (date-time): No description

#### 400: Phone number is already released or being released

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 409: The number is attached to a SIP trunk; detach it first (code invalid_resource_state).

---

---
