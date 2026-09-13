# Vote on a Reddit post or comment API Reference

Cast, change, or clear the connected account's vote on a Reddit post or comment.

**Reddit requires that votes be cast by humans.** Reddit's API terms permit a client
to proxy a human's action one-for-one, and prohibit a bot from deciding how to vote
or from amplifying a human's vote. Call this endpoint only in direct response to an
explicit action by the account owner. Automated or agent-decided voting is
vote manipulation and puts API access at risk.


## POST /v1/accounts/{accountId}/reddit-vote

**Vote on a Reddit post or comment**

Cast, change, or clear the connected account's vote on a Reddit post or comment.

**Reddit requires that votes be cast by humans.** Reddit's API terms permit a client
to proxy a human's action one-for-one, and prohibit a bot from deciding how to vote
or from amplifying a human's vote. Call this endpoint only in direct response to an
explicit action by the account owner. Automated or agent-decided voting is
vote manipulation and puts API access at risk.


### Parameters

- **accountId** (required) in path: The ID of the Reddit account casting the vote

### Request Body

- **thingId** (required) `string`: Reddit fullname of the target. Prefix "t3_" for a post and "t1_" for a comment. A bare id with no prefix is treated as a post ("t3_").

- **direction** (required) `integer`: 1 to upvote, -1 to downvote, 0 to clear an existing vote - one of: 1, 0, -1

### Responses

#### 200: Vote registered

**Response Body:**

- **success** `boolean`: No description

#### 400: Not a Reddit account, or invalid thingId/direction

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

#### 502: Reddit was unreachable or returned an unclassified error. Reddit 4xx statuses are forwarded as-is.

---

---
