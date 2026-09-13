# Related Schema Definitions

## ConnectedApp

An OAuth client (AI assistant / MCP connector) authorized by the user and still
holding at least one live token.


### Properties

- **clientId** `string`: No description
- **clientName** `string`: Name the client declared at registration. Registration is open, so this is self-declared and not verified.
- **redirectHost** `string,null`: Host of the client's registered redirect URI (non-http schemes are shown as scheme//host). The destination an impostor cannot fake.
- **scopes** `array`: Scopes granted on the most recent token.
- **authorizedAt** `string,null`: No description
- **lastUsedAt** `string,null`: Last time any of the client's live tokens authenticated a request.
- **tokenCount** `integer`: Live tokens held by the client (an active session is typically one access plus one refresh token).

---
