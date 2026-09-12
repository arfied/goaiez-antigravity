# Get CTWA conversions dataset API Reference

Returns the Meta Click-to-WhatsApp conversions dataset currently linked
to the WhatsApp account, if one has been provisioned. Reads only from
the stored `metadata.metaCapiDatasetId`, never hits Meta, never
creates a dataset. Use this to detect whether `POST /v1/whatsapp/conversions`
is configured for an account.


## GET /v1/whatsapp/dataset

**Get CTWA conversions dataset**

Returns the Meta Click-to-WhatsApp conversions dataset currently linked
to the WhatsApp account, if one has been provisioned. Reads only from
the stored `metadata.metaCapiDatasetId`, never hits Meta, never
creates a dataset. Use this to detect whether `POST /v1/whatsapp/conversions`
is configured for an account.


### Parameters

- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Dataset lookup

**Response Body:**

- **datasetId** `string,null`: Meta dataset ID linked to the WABA, or null if not provisioned yet

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

## POST /v1/whatsapp/dataset

**Provision CTWA dataset**

Creates (or fetches, if one already exists) the Meta dataset that
Click-to-WhatsApp ad events are reported against via the Conversions
API, and persists its ID on the account as `metadata.metaCapiDatasetId`.

The call is GET-first idempotent: a WABA can only own one CTWA
dataset, so a second call after a successful provision is a safe no-op
that returns the same ID with `created: false`.

Requires the connected WhatsApp account's token to carry the
`whatsapp_business_manage_events` permission. If the permission is
missing the endpoint returns 422 with a message asking the user to
reconnect the account.


### Request Body

- **accountId** (required) `string`: WhatsApp account ID

### Responses

#### 200: Dataset provisioned (or already present)

**Response Body:**

- **datasetId** `string`: Meta dataset ID linked to the WABA
- **created** `boolean`: True if Meta created a new dataset on this call; false if one already existed

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

#### 422: Account is missing `whatsapp_business_manage_events`. Reconnect required

#### 502: Upstream Meta failure during provisioning

---

---
