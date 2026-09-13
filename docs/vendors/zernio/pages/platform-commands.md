# Platform commands

Run the WhatsApp, Google Business Profile, Discord, Instagram, Reddit and X commands that exist on one platform only, with one runnable example.

The platform commands run what exists on one platform only: the WhatsApp stack (groups, templates, calling, flows, numbers, sandbox), Google Business Profile management, Discord server tools, Instagram stories, Reddit search, and X (platform value `twitter`) engagement. Install the CLI with `npm install -g @zernio/cli` and log in with `zernio auth:login`, both covered on the [CLI page](/cli).

## First command

List the WhatsApp message templates of one account:

```bash
zernio whatsapp:get-whats-app-templates --accountId 66b2e19d8c3f5a7e9d0b1c2d --pretty
```

Output (the `200` body of [`GET /v1/whatsapp/templates`](/whatsapp/get-whatsapp-templates), trimmed to one template):

```json
{
  "success": true,
  "templates": [
    {
      "id": "1234567890123456",
      "name": "order_shipped",
      "status": "APPROVED",
      "category": "UTILITY",
      "language": "en_US",
      "components": [
        { "type": "BODY", "text": "Your order {{1}} shipped" }
      ]
    }
  ]
}
```

`components` holds one entry per part of the template (`HEADER`, `BODY`, `FOOTER`, `BUTTONS`), trimmed here to the body. `name` is what you send a template by. The command registers `--accountId` only, so the endpoint's `status`, `name` and `language` filters are out of reach and every template comes back.

## Commands

`zernio --help` prints the full command list. Each command maps to one endpoint in the reference:

| Group | Reference |
|---|---|
| `whatsapp:`, `whatsappcalling:`, `whatsappflows:`, `whatsappphonenumbers:`, `whatsappsandbox:`, `whatsapptemplates:` | [WhatsApp](/whatsapp/get-whatsapp-templates) |
| `gmbattributes:`, `gmbfoodmenus:`, `gmblocationdetails:`, `gmbmedia:`, `gmbplaceactions:`, `gmbservices:`, `gmbverifications:` | [Google Business Profile](/google-business/get-gmb-attribute-metadata) |
| `discord:` | [Discord](/discord/get-discord-channels) |
| `instagram:` | [Instagram stories](/instagram/list-instagram-stories) |
| `reddit:` | [Reddit search](/reddit-search/search-reddit) |
| `twitterengagement:` | [X engagement](/twitter-engagement/retweet-post) |

## How it behaves

### Zernio scopes most of these commands to one account

The WhatsApp commands take `--accountId`, because the number, its templates and its groups belong to one connected WhatsApp account. The phone number and sandbox groups are the exception: they belong to the team, so they take no account id. The Google Business Profile commands take the account id as their first argument.

### Zernio passes the platform's own ids through

Beyond the account id, the ids here are the platform's: Discord's `<guildId>`, `<channelId>` and `<messageId>`, a WhatsApp template `<templateName>`, a Google Business Profile `<verificationId>`. Read them from the listing command in the same group.

### Zernio keeps publishing out of these groups

A post reaches every platform through `posts:create`, described on the [posting page](/cli/posting). These groups hold what has no cross-platform equivalent, such as approving a WhatsApp template or pinning a Discord message.

## Related

- [CLI](/cli): install, log in, and the first command
- [WhatsApp](/platforms/whatsapp): templates, groups, calling, flows and numbers
- [Google Business Profile](/platforms/google-business): locations, reviews and attributes
- [Discord](/platforms/discord): channels, roles and scheduled events
- [X](/platforms/twitter): what the engagement commands do

---
