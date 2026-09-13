# List phone numbers API Reference

List all phone numbers purchased by the authenticated user.
By default, released numbers are excluded. Connected (bring-your-own)
WhatsApp numbers are returned in the separate `connected` array; they
are not billed and have no provisioning lifecycle.


## GET /v1/phone-numbers

**List phone numbers**

List all phone numbers purchased by the authenticated user.
By default, released numbers are excluded. Connected (bring-your-own)
WhatsApp numbers are returned in the separate `connected` array; they
are not billed and have no provisioning lifecycle.


### Parameters

- **status** (optional) in query: Filter by status (by default excludes released numbers). NOTE:
`status=pending_regulatory` returns the "provisioning" view: numbers
still in review PLUS recently-declined (last 30 days) ones, so a
failed registration surfaces (with `regulatoryDeclineReason`) instead
of silently disappearing. Declined numbers can be re-submitted via
POST /v1/phone-numbers/{id}/remediate. `verifying` is the
short-lived state after the number is provisioned on our side while
WhatsApp confirms the activation code; the number is not billed until
it reaches `active`.

- **profileId** (optional) in query: Filter by profile

### Responses

#### 200: Phone numbers retrieved successfully

**Response Body:**

- **numbers** `array[object]`: 
  - **_id** `string`: No description
  - **phoneNumber** `string`: No description
  - **country** `string`: No description
  - **status** `string`: No description - one of: pending_payment, pending_regulatory, regulatory_declined, provisioning, verifying, active, suspended, releasing, released
  - **registrantName** `string,null`: For regulated numbers, who it's registered for (company or person), set from the submitted KYC.
  - **telnyxOrderId** `string,null`: Present once the number order has been placed (i.e. the requirement group was approved). Absent while still in identity review.
  - **monthlyCents** `integer`: What this number bills each month, in cents. Stamped when the number was bought, so an existing number keeps its price when the rate card changes.
  - **hostedByZernio** `boolean`: False for numbers you brought yourself (connected via Meta embedded signup). They live on your own carrier, so SMS/Calls can't be enabled on them.
  - **sipTrunkId** `string,null`: SIP trunk the number is attached to; null when not trunked. While attached, enabling Calls or WhatsApp calling, requesting WhatsApp verification, and releasing the number all return 409.
  - **profileId** `object`: No description
  - **provisionedAt** `string` (date-time): No description
  - **metaPreverifiedId** `string`: No description
  - **metaVerificationStatus** `string`: No description
  - **onfidoVerificationUrl** `string,null`: For regulated (Tier 3/4) numbers with an Onfido ID-verification step: the link to forward to the end user. Set once the order is placed; null otherwise. Poll this field after submitting KYC.
  - **endUserFirstName** `string,null`: No description
  - **endUserLastName** `string,null`: No description
  - **regulatoryDeclineReason** `string,null`: Reviewer rejection reason when status is regulatory_declined.
  - **callingEnabled** `boolean`: Whether WhatsApp Business Calling is enabled on this number (manage via /v1/whatsapp/phone-numbers/{id}/calling).
  - **createdAt** `string` (date-time): No description
- **connected** `array[object]`: Connected (bring-your-own) WhatsApp numbers: your own WABA
numbers linked via Embedded Signup. Not provisioned or billed
by Zernio, so they are not in `numbers`; `accountId` is the
social-account id used by the messaging and inbox endpoints.
Included only on the default and `status=active` views.

  - **accountId** `string`: No description
  - **phoneNumber** `string,null`: No description
  - **displayName** `string,null`: No description
  - **profileId** `string,null`: No description
  - **connectedAt** `string,null` (date-time): No description
  - **callingEnabled** `boolean`: Whether WhatsApp Business Calling is enabled on this number.
- **sandbox** `object,null`: The shared WhatsApp sandbox (one Zernio-owned number, all users test
against it). Present when the sandbox is configured; null otherwise.
The `accountId` lets you address the sandbox in compose endpoints.
`template` is the only template a sandbox send is allowed to use.


#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
