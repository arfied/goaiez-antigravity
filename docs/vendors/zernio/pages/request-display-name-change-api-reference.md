# Request display name change API Reference

Submit a display name change request for the WhatsApp Business account.
The new name must follow WhatsApp naming guidelines (3-512 characters, must represent your business).
Changes require Meta review and approval, which typically takes 1-3 business days.


## GET /v1/whatsapp/business-profile/display-name

**Get display name status**

Fetch the current display name and its Meta review status for a WhatsApp Business account.
Display name changes require Meta approval and can take 1-3 business days.


### Parameters

- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Display name info retrieved

**Response Body:**

- **success** `boolean`: No description
- **displayName** `object`: 
  - **name** `string`: Current verified display name
  - **status** `string`: Meta review status for the display name - one of: APPROVED, PENDING_REVIEW, DECLINED, NONE
  - **phoneNumber** `string`: Display phone number

#### 400: accountId is required

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found or accountId is not a valid ObjectId

---

## POST /v1/whatsapp/business-profile/display-name

**Request display name change**

Submit a display name change request for the WhatsApp Business account.
The new name must follow WhatsApp naming guidelines (3-512 characters, must represent your business).
Changes require Meta review and approval, which typically takes 1-3 business days.


### Request Body

- **accountId** (required) `string`: WhatsApp account ID
- **displayName** (required) `string`: New display name (must follow WhatsApp naming guidelines)

### Responses

#### 200: Display name change submitted for review

**Response Body:**

- **success** `boolean`: No description
- **message** `string`: No description
- **displayName** `object`: 
  - **name** `string`: No description
  - **status** `string`: No description - one of: PENDING_REVIEW

#### 400: Invalid display name (too short, too long, or missing)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
