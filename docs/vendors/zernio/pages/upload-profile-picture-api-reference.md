# Upload profile picture API Reference

Upload a new profile picture for the WhatsApp Business Profile.
Uses Meta's resumable upload API under the hood: creates an upload session,
uploads the image bytes, then updates the business profile with the resulting handle.

Provide the image either as a binary upload (`multipart/form-data` with `file`)
or as a download URL (`application/json` with `url`). With a URL we fetch the
image server-side and upload the bytes for you. Meta's profile-photo API is
bytes-only, so there is no direct URL passthrough. JPEG/PNG, max 5MB either way.


## POST /v1/whatsapp/business-profile/photo

**Upload profile picture**

Upload a new profile picture for the WhatsApp Business Profile.
Uses Meta's resumable upload API under the hood: creates an upload session,
uploads the image bytes, then updates the business profile with the resulting handle.

Provide the image either as a binary upload (`multipart/form-data` with `file`)
or as a download URL (`application/json` with `url`). With a URL we fetch the
image server-side and upload the bytes for you. Meta's profile-photo API is
bytes-only, so there is no direct URL passthrough. JPEG/PNG, max 5MB either way.


### Request Body

- **accountId** (required) `string`: WhatsApp account ID
- **url** (required) `string`: Publicly reachable https URL of the image (JPEG or PNG, max 5MB, recommended 640x640). Fetched server-side; must resolve directly without redirects.

### Responses

#### 200: Profile picture updated successfully

**Response Body:**

- **success** `boolean`: No description
- **message** `string`: No description

#### 400: Invalid file type/URL, file too large, or missing parameters

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

#### 422: Profile photo is locked for WhatsApp coexistence numbers (manage it in the WhatsApp Business app)

---

---
