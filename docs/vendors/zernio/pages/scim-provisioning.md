# SCIM Provisioning

Sync your directory with Zernio over SCIM 2.0, so your identity provider creates, updates and deactivates accounts.

When you finish this page your identity provider manages your team's Zernio accounts the way it manages everything else: joiners are provisioned, attribute changes sync, and leavers lose access the moment IT offboards them. There are no invites to send and no manual removals to remember. You need an Enterprise agreement and a verified [single sign-on](/security/sso) connection.

## Connecting your IdP

Point your identity provider's provisioning integration (Okta, Microsoft Entra, and anything else that speaks SCIM 2.0) at:

| Setting | Value |
|---|---|
| SCIM base URL | `https://zernio.com/api/scim/v2` |
| Authentication | Bearer token |

Zernio issues the SCIM token to the **team owner** during Enterprise onboarding, the same person who controls the SSO connection, and rotates it on request. Tokens are stored hashed and scoped to your connection: a token can only provision into the team behind it.

## Users is the only resource

Zernio implements the SCIM 2.0 `User` resource, plus the `ServiceProviderConfig`, `Schemas` and `ResourceTypes` discovery endpoints your IdP reads first. There is no `Group` resource: a role comes from the SSO connection's default role, so group-to-role mapping in the IdP has nothing to bind to.

| Job | Request |
|---|---|
| List, with the IdP's `filter` on `userName` | `GET /Users` |
| Provision | `POST /Users` |
| Read one | `GET /Users/{id}` |
| Replace | `PUT /Users/{id}` |
| Update attributes, or deactivate with `active: false` | `PATCH /Users/{id}` |
| Delete | `DELETE /Users/{id}` |

`DELETE` removes the Zernio account when SCIM is the only thing it signs in with, and unlinks it from the connection when the person also has a password or a social login. Most identity providers deprovision with `active: false` instead, which is the state Zernio refuses every sign-in against.

### Provisioning a user

Your IdP sends this; the same call by hand reproduces a sync problem in one request:

```bash
curl -X POST https://zernio.com/api/scim/v2/Users \
  -H "Authorization: Bearer $ZERNIO_SCIM_TOKEN" \
  -H "Content-Type: application/scim+json" \
  -d '{
    "userName": "employee@acme-corp.com",
    "externalId": "entra-obj-123",
    "name": {"givenName": "Emma", "familyName": "Ployee"},
    "emails": [{"value": "employee@acme-corp.com", "primary": true}]
  }'
```

Response (`201`):

```json
{
  "schemas": ["urn:ietf:params:scim:schemas:core:2.0:User"],
  "id": "66d4f1b3c7a2e9048b5c6d7e",
  "externalId": "entra-obj-123",
  "userName": "employee@acme-corp.com",
  "name": { "formatted": "Emma Ployee" },
  "displayName": "Emma Ployee",
  "active": true,
  "emails": [{ "primary": true, "value": "employee@acme-corp.com" }],
  "meta": { "resourceType": "User" }
}
```

`id` is what your IdP stores and sends back on every later `PATCH` or `DELETE`.

## If it fails

Errors come back in the SCIM error format, not Zernio's own envelope:

```json
{
  "schemas": ["urn:ietf:params:scim:api:messages:2.0:Error"],
  "status": "401",
  "detail": "Invalid SCIM token"
}
```

- **`401` `Invalid SCIM token`**: the token in the `Authorization` header is not the one Zernio issued for this connection. It was rotated, truncated on the way into the IdP, or belongs to another connection. `SCIM token is required` means no `Authorization` header arrived at all.
- **`409` with `"scimType": "uniqueness"`**: the address is already provisioned through this connection, or it belongs to an existing Zernio account outside your DNS-verified domains, where linking is refused rather than allowed.
- **`404` `User not found`**: the `id` is not one this connection provisioned. A connection only ever sees its own users.

## What provisioning does

- **Create**: a person pushed from your directory lands in your team with the SSO connection's default role, covered by your team's agreement. Their email counts as verified, because your IdP owns the address. The `owner` role can never be assigned this way.
- **Update**: attribute changes in your directory sync to the Zernio account.
- **Deactivate**: `active: false` from your IdP shuts the account down. Every sign-in path refuses a deactivated user, so offboarding in your directory is offboarding in Zernio. Reactivating in the IdP restores access.

## Employees who already have a Zernio account

If someone at your domain signed up before SCIM was connected, provisioning **links** their existing account instead of failing the sync. The link is allowed exactly when their email is at your DNS-verified domain, the same ownership proof SSO trusts. After linking, your IdP controls their lifecycle, including deactivation. Their own team, data and billing are left untouched; linking does not move them into your team.

## SCIM and SSO together

SCIM manages the accounts; [SSO](/security/sso) signs them in. The usual Enterprise setup is both: provisioning from the directory, sign-in through the IdP, and [enforcement](/security/sso#enforcing-sso) turned on so no one at the domain can side-step either.

## Related

- [Single sign-on](/security/sso): connect the identity provider and verify the domain SCIM provisions into.
- [Enforcing SSO](/security/sso#enforcing-sso): shut off password and social sign-in for the domain.
- [Team roles](/security#team-roles): what the connection's default role can do once a person is provisioned.
- [Two-step verification](/security/two-factor): the second factor on password sign-in, which provisioned SSO users do not need.

---
