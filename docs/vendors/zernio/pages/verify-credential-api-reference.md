# Verify credential API Reference

Checks whether the bearer credential on this request is valid, without reading any data. Accepts an API key or an OAuth access token. Intended for clients that must validate a credential before use (for example an MCP server verifying an incoming token) so they do not have to call a data endpoint to do it.

## GET /v1/auth/verify

**Verify credential**

Checks whether the bearer credential on this request is valid, without reading any data. Accepts an API key or an OAuth access token. Intended for clients that must validate a credential before use (for example an MCP server verifying an incoming token) so they do not have to call a data endpoint to do it.

### Responses

#### 200: Credential is valid

**Response Body:**

- **valid** `boolean`: No description
- **userId** `string`: No description
- **authType** `string`: No description - one of: api_key, oauth, session
- **scope** `string,null`: Granted OAuth scopes, space-separated. Null for API keys.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
