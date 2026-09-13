# Security Overview

How access to a Zernio team is protected, from sign-in methods and two-step verification to SSO, session controls, roles and the audit log.

This page covers access control on a Zernio team: how people sign in, what they can reach once they are in, and what Zernio records. It does not cover certifications or data handling; the SOC 2 Type II report and GDPR documentation come with the [enterprise track](/pricing#enterprise). The calls that scope a key or a profile are in [Related](#related).

## Signing in

A Zernio account signs in 4 ways:

| Method | Notes |
|---|---|
| Email and password | Passwords are screened against public breach corpora (see below) and can be combined with [two-step verification](/security/two-factor). |
| Google | OAuth sign-in keyed on the stable Google account id, so an email change does not break access. |
| GitHub | Same model as Google. |
| Enterprise SSO (SAML or OIDC) | Available on Enterprise agreements. Access follows your identity provider, including its MFA and conditional-access policies. See [Single sign-on](/security/sso). |

### Breached-password screening

Zernio checks every password it accepts (signup, reset, change) against the haveibeenpwned corpus with k-anonymity: only the first 5 characters of a SHA-1 digest leave Zernio's infrastructure, never the password. A password found in a known breach is rejected with a message that says why. The check is advisory: when the third-party service is unreachable, Zernio accepts the password rather than block signups on someone else's outage.

### Two-step verification

A TOTP authenticator app plus encrypted backup codes. The [two-step verification page](/security/two-factor) covers setup and recovery.

## Sessions and devices

The **Security** tab of [Settings](https://zernio.com/dashboard/settings) gives every account:

- **A device list.** Each active session with enough context to recognize it, and a per-device sign-out.
- **Sign out everywhere.** Revokes every session on every device, including sessions that predate the click.

## Team roles

Access inside a team is role-based. A team has exactly one owner at any moment.

| Role | What it can do |
|---|---|
| **Owner** | Everything: billing, ownership transfer, account deletion, API keys, webhooks, team management. |
| **Admin** | Team management (invite, remove, change roles and scope), activity log, and billing. Cannot transfer ownership, delete the account, or manage API keys and webhooks. |
| **Billing Manager** | Payment methods, invoices and billing changes. No team management, no API keys. |
| **Member** | Uses the product (publish, analytics, inbox) within their profile scope. No team-level powers. |
| **Viewer** | Read-only. Same visibility as a member, and the server rejects every write rather than hiding it in the UI. |

Two mechanisms narrow access further:

- **Profile-scoped members.** An invite can grant access to specific profiles instead of the whole team.
- **Profile-scoped API keys.** An API key can be restricted to specific profiles; Zernio rejects requests outside its scope. Only the owner and full-access members manage API keys and webhooks, because both are team-wide credentials.

Invites are single-use tokens with an expiry, and can be bound to one email address so nobody else can claim them.

## Audit log

Every team-management action writes an audit entry: invites created, accepted and revoked, members removed, role and scope changes, ownership transfers. Each entry records who performed the action, who it targeted, and when. The log is append-only and available to the team owner.

## Enterprise controls

On Enterprise agreements, the team owner can also:

- Connect a **SAML or OIDC identity provider**, verified by DNS domain ownership.
- **Enforce SSO** for the whole email domain, which shuts off password and social sign-in for everyone at that domain.
- Sync the employee directory over **SCIM 2.0**, so the identity provider creates, updates and deactivates Zernio accounts.

The [single sign-on page](/security/sso) covers SSO and enforcement, the [SCIM page](/security/scim) covers directory sync.

## Related

- [Single sign-on](/security/sso): connect a SAML or OIDC identity provider and enforce it for your domain.
- [SCIM provisioning](/security/scim): let the identity provider create, update and deactivate accounts.
- [Two-step verification](/security/two-factor): TOTP and backup codes on password sign-in.
- [Scoped API keys](/multi-tenant#scoped-api-keys): restrict a key to one customer's profile, with the fields on [Create API key](/api-keys/create-api-key).
- [Profiles](/guides/profiles): the boundary an account, a key and a member are scoped to.

---
