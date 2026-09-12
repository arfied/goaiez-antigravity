# Single Sign-On (SSO)

Connect a SAML or OIDC identity provider (Okta, Microsoft Entra, Google Workspace) to a Zernio team, verify the domain over DNS, and enforce SSO for the whole domain.

When you finish this page your team signs in to Zernio through your identity provider, and you can turn off every other sign-in method for your email domain. You need an Enterprise agreement and the owner login of the team. Both **SAML 2.0** and **OIDC** work, which covers Okta, Microsoft Entra ID, Google Workspace, OneLogin, JumpCloud and most others.

If your agreement does not include SSO, the setup panel says so; [contact us](https://zernio.com/enterprise) to enable it.

## How it works

A connection links **one email domain** to **one identity provider**. Anyone who signs in through that connection with an email at your domain lands in your team, as a new member on first sign-in. Sign-in routes by email domain: your team enters their work address on the sign-in page and Zernio sends them to your IdP. There is no team slug to remember.

Three rules keep this safe:

- **Only the team owner can set up SSO.** It decides who can reach the whole team, so it sits with the person who owns the billing relationship, not with admins.
- **The domain must be the owner's own email domain**, and never a public mailbox domain such as `gmail.com`. You prove control of it with a DNS record before the connection routes a single sign-in.
- **One connection per domain across all of Zernio.** The first verified claim wins. If your domain is already connected to another team unexpectedly, contact support.

## Setting it up

Everything happens in the **Single sign-on** section of [Settings](https://zernio.com/dashboard/settings).

### Step 1: Claim your domain and connect the IdP

Enter your company domain (it must match your own email address) and your identity provider's details:

- **SAML**: paste your IdP's federation metadata XML. Zernio reads the sign-on URL and certificate from it, so there is nothing to copy field by field. In Okta, Entra or OneLogin, download the app's federation metadata file and paste the whole thing. If your IdP cannot export metadata, enter the sign-on URL, issuer and certificate individually.
- **OIDC**: enter the issuer URL, client ID and client secret from the app you created in your IdP.

When you create the app on the IdP side, the service-provider endpoints have this shape (the settings panel shows `providerId` once the connection exists):

| Value | URL |
|---|---|
| SAML ACS URL | `https://zernio.com/api/auth-v2/sso/saml2/sp/acs/{providerId}` |
| SAML SP entity ID | `https://zernio.com/api/auth-v2/sso/saml2/sp/metadata` |
| OIDC redirect URI | `https://zernio.com/api/auth-v2/sso/callback/{providerId}` |

Most IdPs let you create the app with a placeholder ACS or redirect URL, export the metadata, connect it to Zernio, and then paste the final URLs back into the IdP.

### Step 2: Verify the domain over DNS

After connecting, Zernio shows a **TXT record** (host and value) to add at your DNS provider. The connection routes **no sign-ins** until this record is verified, which is what stops anyone else from registering an identity provider for a domain they do not own. The record's name is `_better-auth-token-{providerId}`. DNS changes usually propagate within minutes; click **Check DNS record** once it is live. The record stays visible in the panel until verification passes.

### Step 3: Test it

Sign in through the identity provider yourself: on the sign-in page, choose SSO and enter your work email. Team members who sign in for the first time are added to your team with the connection's default role. The `owner` role can never be granted through SSO; ownership stays a deliberate, individual action.

## Enforcing SSO

Once the domain is verified, the owner can turn on **enforcement** for it. From that moment:

- Password, Google and GitHub sign-in are refused for every account at your domain, with a message that points people to SSO.
- Zernio enforces this at session creation on every sign-in path, so picking a different button does not get around it.
- Existing sessions are untouched and run until they expire; nobody is signed out when the switch flips.

One guard: **enforcement stays locked until the owner has signed in through the connection at least once.** Turning off every other door before proving the SSO door opens would lock the whole company out.

## Removing a connection

The owner can disconnect SSO from the same panel. Removing it restores the other sign-in methods for your domain. You are asked to type the domain to confirm: this is the one action that can leave a whole company unable to sign in, and there is no undo.

## Provisioning with SCIM

A verified connection can also drive **SCIM 2.0 directory sync**: your IdP creates accounts for joiners, syncs attribute changes and deactivates leavers, with no invites to accept. See [SCIM provisioning](/security/scim).

## MFA and conditional access

With SSO connected, multi-factor authentication and conditional-access policies are your identity provider's job, and apply on every sign-in as your IT team configured them. Zernio's own [two-step verification](/security/two-factor) covers password sign-in and is not needed for SSO users.

## If it fails

Four failures cover almost every stalled setup. The panel shows the message; the fix is here.

- **"We could not find the TXT record yet."** The record is missing, published at the wrong host, or still propagating. Publish the exact name and value the panel shows, then click **Check DNS record** again; propagation runs from minutes to a few hours. The verification token expires about a week after it is issued, and the panel stops showing a value once it does: remove the unverified connection and add it again for a fresh record.
- **"That file has no IdP descriptor."** You pasted the service-provider metadata. Download the metadata of the app you created inside your identity provider and paste that instead.
- **"The metadata has no HTTP-Redirect or HTTP-POST sign-on URL"** or **"The metadata contains no signing certificate."** The file parsed, but it is missing the sign-on URL or the certificate Zernio validates assertions against. Re-export it from the IdP, or enter the sign-on URL, issuer and certificate individually.
- **The domain is already connected to single sign-on in another team.** Contact support; nobody can claim your domain without passing the same DNS check.

## Related

- [SCIM provisioning](/security/scim): let the verified connection create, update and deactivate accounts.
- [Security overview](/security): sign-in methods, sessions, team roles and the audit log.
- [Two-step verification](/security/two-factor): the second factor for password sign-in, which SSO users do not need.

---
