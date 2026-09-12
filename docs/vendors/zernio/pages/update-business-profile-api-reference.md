# Update business profile API Reference

Update the WhatsApp Business profile. All fields are optional; only provided fields will be updated.
Constraints: about max 139 chars, description max 512 chars, max 2 websites.


## GET /v1/whatsapp/business-profile

**Get business profile**

Retrieve the WhatsApp Business profile for the account (about, address, description, email, websites, etc.).


### Parameters

- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Business profile retrieved successfully

**Response Body:**

- **success** `boolean`: No description
- **businessProfile** `object`: 
  - **about** `string`: Short description (max 139 chars)
  - **address** `string`: No description
  - **description** `string`: Full description (max 512 chars)
  - **email** `string`: No description
  - **profilePictureUrl** `string` (uri): No description
  - **websites** `array[string]`: 
  - **vertical** `string`: Business category

#### 400: accountId is required or phone number ID not found

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

## POST /v1/whatsapp/business-profile

**Update business profile**

Update the WhatsApp Business profile. All fields are optional; only provided fields will be updated.
Constraints: about max 139 chars, description max 512 chars, max 2 websites.


### Request Body

- **accountId** (required) `string`: WhatsApp account ID
- **about** `string`: Short business description (max 139 characters)
- **address** `string`: Business address
- **description** `string`: Full business description (max 512 characters)
- **email** `string`: Business email
- **websites** `array`: Business websites (max 2)
- **vertical** `string`: Business category (e.g., RETAIL, ENTERTAINMENT, etc.)
- **profilePictureHandle** `string`: Handle from resumable upload for profile picture

### Responses

#### 200: Business profile updated successfully

**Response Body:**

- **success** `boolean`: No description
- **message** `string`: No description

#### 400: Validation error (field too long, too many websites, etc.)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
