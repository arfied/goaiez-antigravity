# Pause or resume many campaigns API Reference

Process up to 50 campaigns in one call. Each campaign is updated
concurrently and the response contains a per-campaign result so a
single bad row does not fail the whole batch.


## POST /v1/ads/campaigns/bulk-status

**Pause or resume many campaigns**

Process up to 50 campaigns in one call. Each campaign is updated
concurrently and the response contains a per-campaign result so a
single bad row does not fail the whole batch.


### Request Body

- **status** (required) `string`: No description - one of: active, paused
- **campaigns** (required) `array`: No description

### Responses

#### 200: Per-campaign results

**Response Body:**

- **status** `string`: No description - one of: active, paused
- **totals** `object`: 
  - **updated** `integer`: No description
  - **skipped** `integer`: No description
  - **failed** `integer`: No description
- **results** `array[object]`: 
  - **platformCampaignId** `string`: No description
  - **platform** `string`: No description
  - **updated** `integer`: No description
  - **skipped** `integer`: No description
  - **error** `string`: No description

#### 400: Invalid input

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Returned with code `ads_allowance_exceeded` when the team has no payment method on file and has reached the 500 free live ads: add a card to resume.

---

---
