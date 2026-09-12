# Management commands

Create API keys, manage users, invites, account groups, per-account settings, webhook endpoints and usage reads from the terminal, starting with the key value that is returned once.

The management commands cover the team itself: authentication, API keys, users and invites, account groups, per-account platform settings, webhook endpoints and the plan and usage snapshot. Install the CLI with `npm install -g @zernio/cli` and log in with `zernio auth:login`, both covered on the [CLI page](/cli).

## First command

Create a key for another machine. The response is the only place its value appears:

```bash
zernio apikeys:create --name "CI deploy" --pretty
```

Output (the `201` body of [`POST /v1/api-keys`](/api-keys/create-api-key), trimmed):

```json
{
  "message": "API key created successfully",
  "apiKey": {
    "id": "66e1a2b3c4d5e6f7a8b9c0d1",
    "name": "CI deploy",
    "key": "sk_1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef",
    "keyPreview": "sk_12345678...90abcdef",
    "scope": "full",
    "permission": "read-write"
  }
}
```

Save `key` now: every later read returns `keyPreview` and never the value again. `--expiresIn` sets the lifetime in days, `--permission read` limits the key to `GET` requests, and `--scope profiles` with `--profileIds` limits it to named profiles. Resource groups are turned off with `disabledResourceGroups`, which the endpoint takes and the command does not ([Create an API key](/api-keys/create-api-key)).

## Commands

`zernio --help` prints the full command list. Each command maps to one endpoint in the reference:

| Group | Reference |
|---|---|
| `apikeys:` | [API keys](/api-keys/list-api-keys) |
| `users:`, `invites:` | [Get user](/users/get-user), [invites](/invites/create-invite-token) |
| `accountgroups:` | [Account groups](/account-groups/list-account-groups) |
| `accountsettings:` | [Account settings](/account-settings/get-messenger-menu) |
| `webhooks:` | [Webhook endpoints](/webhooks/create-webhook-settings) |
| `usage:` | [Usage stats](/usage/get-usage-stats), [X API pricing](/usage/get-xapi-pricing) |

`auth:` is the exception: `auth:login`, `auth:set` and `auth:check` manage the key on this machine, and only `auth:check` calls the API. It calls [`GET /v1/users`](/users/list-users) and prints the team with `currentUserId` set to the key's own user, and each member's `profileAccess` as `all` or the profile ids they can reach.

## How it behaves

### Zernio never grants these commands to a restricted key

A key created with any resource group disabled carries the `zrk_` prefix and cannot manage API keys, invites, connected apps or member identity, whichever groups it holds:

```json
{
  "error": "Restricted API keys cannot manage API keys, invites, or member identity.",
  "code": "insufficient_permissions"
}
```

Run these commands with a full-access key. `required_group` is absent here, because no group grants them.

## Related

- [CLI](/cli): install, log in, and the first command
- [Create an API key](/api-keys/create-api-key): resource groups and profile scope
- [Webhooks](/webhooks): what `webhooks:create-settings` enables
- [Invite team members](/invites/create-invite-token)

---
