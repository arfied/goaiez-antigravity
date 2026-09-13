# Get LinkedIn post reactions API Reference

Returns individual reactions for a specific LinkedIn post, including reactor profiles
(name, headline/job title, profile picture, profile URL, reaction type).
Only works for organization/company page accounts. LinkedIn restricts reaction
data for personal profiles (r_member_social_feed is a closed permission).


## GET /v1/accounts/{accountId}/linkedin-post-reactions

**Get LinkedIn post reactions**

Returns individual reactions for a specific LinkedIn post, including reactor profiles
(name, headline/job title, profile picture, profile URL, reaction type).
Only works for organization/company page accounts. LinkedIn restricts reaction
data for personal profiles (r_member_social_feed is a closed permission).


### Parameters

- **accountId** (required) in path: The ID of the LinkedIn organization account
- **urn** (required) in query: The LinkedIn post URN
- **limit** (optional) in query: Maximum number of reactions to return per page
- **cursor** (optional) in query: Offset-based pagination start index

### Responses

#### 200: Reactions with reactor profiles

**Response Body:**

- **accountId** `string`: No description
- **platform** `string`: No description (example: "linkedin")
- **accountType** `string`: No description (example: "organization")
- **username** `string`: No description
- **postUrn** `string`: No description
- **reactions** `array[object]`: 
  - **reactionType** `string`: LinkedIn reaction enum (LIKE, PRAISE, EMPATHY, INTEREST, APPRECIATION, ENTERTAINMENT)
  - **reactionLabel** `string`: User-friendly label (Like, Celebrate, Love, Insightful, Support, Funny)
  - **reactedAt** `string` (date-time): No description
  - **from** `object`: 
    - **urn** `string`: LinkedIn person or organization URN
    - **name** `string`: Reactor's display name
    - **headline** `string`: Reactor's headline/job title
    - **username** `string`: LinkedIn vanity name
    - **profilePicture** `string`: Profile picture URL
    - **profileUrl** `string`: Direct link to LinkedIn profile
- **pagination** `object`: 
  - **hasMore** `boolean`: No description
  - **cursor** `string`: Offset for next page
  - **total** `integer`: Total number of reactions (when available)
- **lastUpdated** `string` (date-time): No description

#### 400: Invalid request

**Response Body:**

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

#### 403: Missing required LinkedIn scope

#### 404: Account or post not found

---
