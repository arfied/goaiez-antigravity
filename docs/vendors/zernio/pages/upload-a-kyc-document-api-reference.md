# Upload a KYC document API Reference

Upload ONE document and get back its provider document id, to reference
from POST /v1/phone-numbers/kyc via `documents[].documentId`.
Send the RAW file bytes as the request body (not base64); put the filename
in the `X-Filename` header. Uploading documents one-per-request keeps each
request under the ~4.5MB body limit. The document streams straight to the
number provider and is not stored by Zernio.


## POST /v1/phone-numbers/kyc/upload-document

**Upload a KYC document**

Upload ONE document and get back its provider document id, to reference
from POST /v1/phone-numbers/kyc via `documents[].documentId`.
Send the RAW file bytes as the request body (not base64); put the filename
in the `X-Filename` header. Uploading documents one-per-request keeps each
request under the ~4.5MB body limit. The document streams straight to the
number provider and is not stored by Zernio.


### Parameters

- **X-Filename** (required) in header: URL-encoded original filename.

### Request Body


### Responses

#### 200: Document uploaded.

**Response Body:**

- **documentId** `string`: Reference this id in the KYC submit's documents[].documentId.

#### 400: Missing X-Filename, empty body, or file too large (over 20MB).

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
