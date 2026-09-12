# Upload a porting document API Reference

Upload ONE porting document and get back its `documentId`. For the
signed LOA / carrier invoice the id goes to `loaDocumentId` /
`invoiceDocumentId`; for a country-specific document requirement
(international ports) it becomes that requirement's `fieldValue`.
Requirement documents are normalized to PDF automatically (regulators
reject raw images). PDF, JPEG, or PNG, 10MB max. Uploads must be
attached to an order within 30 minutes or the carrier deletes them.


## POST /v1/phone-numbers/port-in/documents

**Upload a porting document**

Upload ONE porting document and get back its `documentId`. For the
signed LOA / carrier invoice the id goes to `loaDocumentId` /
`invoiceDocumentId`; for a country-specific document requirement
(international ports) it becomes that requirement's `fieldValue`.
Requirement documents are normalized to PDF automatically (regulators
reject raw images). PDF, JPEG, or PNG, 10MB max. Uploads must be
attached to an order within 30 minutes or the carrier deletes them.


### Request Body


### Responses

#### 200: Document uploaded.

**Response Body:**

- **documentId** `string`: No description

#### 400: Missing file, file too large, or unsupported type

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
