# Related Schema Definitions

## WhatsAppSandboxSession

A per-user activation session against the shared WhatsApp sandbox number.
Transitions `pending → active` when the inbound webhook receives a reply
from the matching phone (the reply itself proves ownership).


### Properties

- **id** (required) `string`: Session id. Use this to revoke via DELETE.
- **phoneE164** (required) `string`: Digits-only E.164 form (no +, spaces, or dashes).
- **status** (required) `string`: `pending` until the phone replies to the activation template, then
`active`. Expired sessions are pruned by TTL and never appear in
list responses.
 - one of: pending, active
- **expiresAt** (required) `string`: UTC timestamp at which the session becomes invalid. Pending sessions
get a 24h window; activated sessions get 7 days.

- **activatedAt** `string,null`: When the session transitioned `pending → active`, or null.
- **createdAt** `string,null`: No description

---
