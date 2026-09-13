# Bulk create contacts API Reference

Import up to 1000 contacts at a time. Skips duplicates, merging any new tags onto the existing contact. accountId is required whenever contacts carry a platformIdentifier (or a row-level accountId); platform is always derived from the resolved account, never used to decide whether channels are created, and a mismatched platform 404s as account not found. When accountId is set, each contact must carry a platformIdentifier; a row missing it is rejected individually (reported in errors[], HTTP 200), not a 400 for the whole import. On phone platforms (whatsapp, sms) the platformIdentifier is normalized to digits and a value that is not phone-shaped is rejected per contact and reported in errors[], not imported.

## POST /v1/contacts/bulk

**Bulk create contacts**

Import up to 1000 contacts at a time. Skips duplicates, merging any new tags onto the existing contact. accountId is required whenever contacts carry a platformIdentifier (or a row-level accountId); platform is always derived from the resolved account, never used to decide whether channels are created, and a mismatched platform 404s as account not found. When accountId is set, each contact must carry a platformIdentifier; a row missing it is rejected individually (reported in errors[], HTTP 200), not a 400 for the whole import. On phone platforms (whatsapp, sms) the platformIdentifier is normalized to digits and a value that is not phone-shaped is rejected per contact and reported in errors[], not imported.

### Request Body

- **profileId** (required) `string`: No description
- **accountId** `string`: Required when contacts carry channel data (platformIdentifier or a row-level accountId). Omit for a plain CRM import with no channels.
- **platform** `string`: Ignored when accountId is set: the platform is derived from the resolved account. Only relevant to disambiguate accountId lookup; a mismatch 404s.
- **contacts** (required) `array`: No description

### Responses

#### 200: Bulk import results

**Response Body:**

- **success** `boolean`: No description
- **created** `integer`: No description
- **skipped** `integer`: No description
- **errors** `array[string]`: Per-contact failures, e.g. an identifier that is not a valid phone number
- **total** `integer`: No description

#### 400: Contact missing required field name, or a row carries platformIdentifier/accountId with no top-level accountId to attach it to. A row missing platformIdentifier while accountId IS set is not a 400: it is reported in errors[] under a 200. An accountId on a platform with no contact channels rejects the whole import (code: platform_not_supported, details.supportedPlatforms lists the valid values).

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
