# Resolve LinkedIn mention API Reference

Converts a LinkedIn profile or company URL to a URN for @mentions in posts.

How to use LinkedIn @mentions (2-step workflow):

1. Call this endpoint with the LinkedIn profile/company URL to get the mention URN and format.
2. Embed the returned mentionFormat (e.g. @[Vincent Jong](urn:li:person:xxx)) directly in your post's content field.

Example:
- Resolve: GET /v1/accounts/{id}/linkedin-mentions?url=linkedin.com/in/vincentjong&displayName=Vincent Jong
- Returns: mentionFormat: "@[Vincent Jong](urn:li:person:xxx)"
- Use in post content: "Great talk with @[Vincent Jong](urn:li:person:xxx) today!"

Important: The mentions array field in POST /v1/posts is stored for reference only and does NOT trigger @mentions on LinkedIn. You must embed the mention format directly in the content text.

Requirements:
- Person mentions require the LinkedIn account to be admin of at least one organization: both endpoints that resolve a profile URL to a member URN take an organization you administer. Organization mentions (e.g. @Microsoft) have no such requirement.
- The two are not scoped alike. peopleTypeahead?q=organizationFollowers only returns that organization's followers; vanityUrl?q=vanityUrlAsOrganization does not, and its resolvable set is opaque. A 404 here does not mean the person is unknown to you or unknown to LinkedIn: a live public profile can 404 with no reason given.
- For person mentions to be clickable, the displayName parameter must exactly match the name shown on their LinkedIn profile.
- Person mentions DO work when published from personal profiles (the URN only needs to be valid). The limitation is only in the resolution step (URL to URN), not in publishing.


## GET /v1/accounts/{accountId}/linkedin-mentions

**Resolve LinkedIn mention**

Converts a LinkedIn profile or company URL to a URN for @mentions in posts.

How to use LinkedIn @mentions (2-step workflow):

1. Call this endpoint with the LinkedIn profile/company URL to get the mention URN and format.
2. Embed the returned mentionFormat (e.g. @[Vincent Jong](urn:li:person:xxx)) directly in your post's content field.

Example:
- Resolve: GET /v1/accounts/{id}/linkedin-mentions?url=linkedin.com/in/vincentjong&displayName=Vincent Jong
- Returns: mentionFormat: "@[Vincent Jong](urn:li:person:xxx)"
- Use in post content: "Great talk with @[Vincent Jong](urn:li:person:xxx) today!"

Important: The mentions array field in POST /v1/posts is stored for reference only and does NOT trigger @mentions on LinkedIn. You must embed the mention format directly in the content text.

Requirements:
- Person mentions require the LinkedIn account to be admin of at least one organization: both endpoints that resolve a profile URL to a member URN take an organization you administer. Organization mentions (e.g. @Microsoft) have no such requirement.
- The two are not scoped alike. peopleTypeahead?q=organizationFollowers only returns that organization's followers; vanityUrl?q=vanityUrlAsOrganization does not, and its resolvable set is opaque. A 404 here does not mean the person is unknown to you or unknown to LinkedIn: a live public profile can 404 with no reason given.
- For person mentions to be clickable, the displayName parameter must exactly match the name shown on their LinkedIn profile.
- Person mentions DO work when published from personal profiles (the URN only needs to be valid). The limitation is only in the resolution step (URL to URN), not in publishing.


### Parameters

- **accountId** (required) in path: The LinkedIn account ID
- **url** (required) in query: LinkedIn profile URL, company URL, or vanity name.
- **displayName** (optional) in query: Exact display name as shown on LinkedIn. Required for person mentions to be clickable. Optional for org mentions.

### Responses

#### 200: URN resolved successfully

**Response Body:**

- **urn** `string`: The LinkedIn URN (person or organization) (example: "urn:li:person:4qj5ox-agD")
- **type** `string`: The type of entity (person or organization) - one of: person, organization (example: "person")
- **displayName** `string`: Display name (provided, from API, or derived from vanity URL) (example: "Miquel Palet")
- **mentionFormat** `string`: Ready-to-use mention format for post content (example: "@[Miquel Palet](urn:li:person:4qj5ox-agD)")
- **vanityName** `string`: The vanity name/slug (only for organization mentions) (example: "microsoft")
- **warning** `string`: Warning about clickable mentions (only present for person mentions if displayName was not provided) (example: "For clickable person mentions, provide the displayName parameter with the exact name as shown on their LinkedIn profile.")

#### 400: Invalid request or no organization found (for person mentions)

**Response Body:**

- **error** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: The account holds no organization role that can resolve person mentions

**Response Body:**

- **error** `string`: No description

#### 404: Person or organization not found

**Response Body:**

- **error** `string`: No description

---

---
