# Get username suggestions API Reference

Retrieve a list of available WhatsApp Business username suggestions based on the account's
business profile name. Use these to help users discover valid, unclaimed usernames.


## GET /v1/whatsapp/business-profile/username/suggestions

**Get username suggestions**

Retrieve a list of available WhatsApp Business username suggestions based on the account's
business profile name. Use these to help users discover valid, unclaimed usernames.


### Parameters

- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Username suggestions retrieved successfully

**Response Body:**

- **success** `boolean`: No description
- **suggestions** `array[string]`: List of available username suggestions

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
