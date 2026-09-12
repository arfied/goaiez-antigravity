# Delete business username API Reference

Release the currently claimed WhatsApp Business username from the account.
After deletion the username becomes available for other accounts to claim.


## GET /v1/whatsapp/business-profile/username

**Get business username**

Fetch the current WhatsApp Business username and its approval status.
Username status can be `approved` (active), `reserved` (pending activation), or `none` (no username set).


### Parameters

- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Business username retrieved successfully

**Response Body:**

- **success** `boolean`: No description
- **username** `string,null`: The current username, or null if none is set
- **status** `string`: Approval state of the username - one of: approved, reserved, none

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

## POST /v1/whatsapp/business-profile/username

**Set business username**

Claim or transfer a WhatsApp Business username for the account.

Username rules: 3-35 characters, letters/digits/period/underscore only, must contain at least one letter,
no leading or trailing periods, no consecutive periods, no `www` prefix, no domain TLD suffix (e.g. `.com`).

If the desired username is currently held by another account, pass `transferAction: "force_transfer"` to
request a transfer. On failure the API returns a standard error envelope with one of these codes:
`whatsapp_username_unavailable` (already taken and transfer not requested),
`whatsapp_username_ineligible` (account not eligible to claim a username), or
`whatsapp_username_transfer_required` (username is held elsewhere; retry with `force_transfer`).


### Request Body

- **accountId** (required) `string`: WhatsApp account ID
- **username** (required) `string`: Desired username. Letters, digits, period, and underscore only. Must contain at least one letter. No leading, trailing, or consecutive periods. No www prefix. No domain TLD suffix.

- **transferAction** `string`: Pass `force_transfer` to request a transfer if the username is held by another account - one of: none, force_transfer

### Responses

#### 200: Username claimed successfully

**Response Body:**

- **success** `boolean`: No description
- **username** `string`: No description
- **status** `string`: No description - one of: approved, reserved, none

#### 400: Validation error or username unavailable (see error code in response)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

## DELETE /v1/whatsapp/business-profile/username

**Delete business username**

Release the currently claimed WhatsApp Business username from the account.
After deletion the username becomes available for other accounts to claim.


### Request Body

- **accountId** (required) `string`: WhatsApp account ID

### Responses

#### 200: Username deleted successfully

**Response Body:**

- **success** `boolean`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
