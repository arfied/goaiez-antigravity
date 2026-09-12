# Two-Step Verification

Protect password sign-in with a TOTP authenticator app and backup codes.

When you finish this page every password sign-in to your account asks for a 6-digit code from an authenticator app. Any TOTP app works: Google Authenticator, 1Password, Authy, Apple Passwords. You need a password on the account.

## Turning it on

In the **Security** tab of [Settings](https://zernio.com/dashboard/settings), under Two-step verification:

1. **Confirm your password.** Enabling, and later disabling, always asks for it again.
2. **Scan the QR code** with your authenticator app and enter the code it shows.
3. **Save your backup codes.** You get 10 single-use codes for signing in if you lose the authenticator device. Store them somewhere safe; Zernio shows them once at setup and stores them encrypted, never in plain text.

If you signed up with Google or GitHub and never set a password, you are asked to create one first. An account with no password has nothing for two-step verification to protect, so enrollment requires one.

## What it protects

- **Protected**: password sign-in. Every credential sign-in is challenged for a TOTP code or a backup code.
- **Not challenged**: Google and GitHub sign-in. Those already carry whatever second factor your Google or GitHub account enforces, so protect them there.
- **SSO users**: your identity provider enforces MFA on every sign-in; see [Single sign-on](/security/sso). Zernio's two-step verification is not needed on top.

## Losing access

- **Lost the authenticator, have backup codes**: sign in with a backup code, then disable and re-enroll two-step verification with the new device.
- **Running low on codes**: Zernio shows the 10 codes once, at setup, and there is no separate regenerate button. Turn two-step verification off and back on for a fresh set of 10; that reissues the shared secret too, so rescan the QR code with your authenticator app.
- **Lost both**: contact support from your account email. Zernio verifies your identity manually before resetting anything.

## Related

- [Security overview](/security): every sign-in method, sessions, team roles and the audit log.
- [Sessions and devices](/security#sessions-and-devices): sign out a lost device, or every device at once.
- [Single sign-on](/security/sso): the identity provider enforces MFA instead, on Enterprise agreements.

---
