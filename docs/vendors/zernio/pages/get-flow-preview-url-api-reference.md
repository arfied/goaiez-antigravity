# Get flow preview URL API Reference

Get Meta's public web-preview URL for a flow (drafts included), embeddable as an
interactive iframe. The link is reused across calls (valid ~30 days); pass
invalidate=true to mint a fresh one (the previous link stops working).


## GET /v1/whatsapp/flows/{flowId}/preview

**Get flow preview URL**

Get Meta's public web-preview URL for a flow (drafts included), embeddable as an
interactive iframe. The link is reused across calls (valid ~30 days); pass
invalidate=true to mint a fresh one (the previous link stops working).


### Parameters

- **flowId** (required) in path: Flow ID
- **accountId** (required) in query: WhatsApp account ID
- **invalidate** (optional) in query: Mint a fresh preview link (default false)

### Responses

#### 200: Preview URL

**Response Body:**

- **preview_url** `string,null`: No description
- **expires_at** `string,null`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Flow or account not found

---

---
