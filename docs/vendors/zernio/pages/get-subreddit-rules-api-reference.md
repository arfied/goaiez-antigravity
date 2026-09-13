# Get subreddit rules API Reference

Returns a subreddit's posting rules plus Reddit's site-wide rules, so you can check
them before submitting and avoid a removal.

Use this alongside `POST /v1/tools/validate/subreddit`, which only confirms that a
subreddit exists and reports its basic posting settings.


## GET /v1/accounts/{accountId}/reddit-subreddits/{subreddit}/rules

**Get subreddit rules**

Returns a subreddit's posting rules plus Reddit's site-wide rules, so you can check
them before submitting and avoid a removal.

Use this alongside `POST /v1/tools/validate/subreddit`, which only confirms that a
subreddit exists and reports its basic posting settings.


### Parameters

- **accountId** (required) in path: The ID of the Reddit account
- **subreddit** (required) in path: Subreddit name (without the "r/" prefix)

### Responses

#### 200: Subreddit and site rules

**Response Body:**

- **rules** `array[object]`: 
  - **kind** `string`: Scope of the rule: 'link', 'comment', or 'all'
  - **shortName** `string`: Short rule title shown in the subreddit sidebar
  - **description** `string`: Full rule text
  - **violationReason** `string`: Reason shown to a user when the rule is enforced
  - **createdUtc** `number`: Unix timestamp when the rule was created
  - **priority** `integer`: Display order of the rule
- **siteRules** `array[string]`: Reddit's site-wide content policy rules

#### 400: Not a Reddit account

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account or subreddit not found

#### 502: Reddit was unreachable or returned an unclassified error. Reddit 4xx statuses are forwarded as-is.

---

---
