# Validate character count API Reference

Check weighted character count per platform and whether the text is within each platform's limit.

X uses weighted counting (URLs = 23 chars via t.co, emojis = 2 chars). All other platforms use plain character length.

Returns counts and limits for all 15 supported platform variants.

X returns two rows and this endpoint cannot tell you which one applies to you: it takes only `text`, so it never resolves an account. `twitter` (280) is the free tier limit. `twitterPremium` (25000) applies only when the target X account has a paid X subscription, and publishing enforces 280 instead for any post carrying a poll (this endpoint has no poll input, so the `twitterPremium` row always shows 25000). A free account trusting the `twitterPremium` row can pass validation here and still fail at publish time, where the account's real limit is enforced.

To validate against the per-account limit, use `POST /v1/tools/validate/post` instead: it accepts an `accountId` per platform entry, resolves X Premium status, and checks the text against the limit publishing enforces, including the poll cap. A missing, foreign, or invalid `accountId` falls back to the conservative 280.


## POST /v1/tools/validate/post-length

**Validate character count**

Check weighted character count per platform and whether the text is within each platform's limit.

X uses weighted counting (URLs = 23 chars via t.co, emojis = 2 chars). All other platforms use plain character length.

Returns counts and limits for all 15 supported platform variants.

X returns two rows and this endpoint cannot tell you which one applies to you: it takes only `text`, so it never resolves an account. `twitter` (280) is the free tier limit. `twitterPremium` (25000) applies only when the target X account has a paid X subscription, and publishing enforces 280 instead for any post carrying a poll (this endpoint has no poll input, so the `twitterPremium` row always shows 25000). A free account trusting the `twitterPremium` row can pass validation here and still fail at publish time, where the account's real limit is enforced.

To validate against the per-account limit, use `POST /v1/tools/validate/post` instead: it accepts an `accountId` per platform entry, resolves X Premium status, and checks the text against the limit publishing enforces, including the poll cap. A missing, foreign, or invalid `accountId` falls back to the conservative 280.


### Request Body

- **text** (required) `string`: The post text to check

### Responses

#### 200: Character counts per platform

**Response Body:**

- **text** `string`: No description
- **platforms** `object`: No description (example: {"twitter":{"count":51,"limit":280,"valid":true},"twitterPremium":{"count":51,"limit":25000,"valid":true},"instagram":{"count":51,"limit":2200,"valid":true},"bluesky":{"count":51,"limit":300,"valid":true},"snapchat":{"count":51,"limit":160,"valid":true}})

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
