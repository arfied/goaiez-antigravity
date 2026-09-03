import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| ⚠️ **G3-60** · **G6-34** · **G19-16** | **X-104** — capture on their own site — **one spec** | ⭐ **ANY form on the tenant's OWN WordPress site captures to their CRM** · **trigger:** any submit on their site | X-155's runtime | ⚠️ **NAME COLLISION recorded at F-12: X-109's header also claims \"the universal form hijacker.\"** ⛔ *That one submits INTO a prospect's form; this one captures the tenant's OWN.* | ⭐ **the action ids disambiguate: `plugin.capture` here, `form.submit` there** — and **`doctor` asserts neither module contains the other's vocabulary** *(§171D's guard 3)* | ⑥ one plugin install · ⑦ captures everything |",
    "| ⚠️ **G3-60** · **G6-34** · **G19-16** | **X-104** — capture on their own site — **one spec** | ⭐ **ANY form on the tenant's OWN WordPress site captures to their CRM** · **trigger:** any submit on their site | X-155's runtime | ⚠️ **NAME COLLISION recorded at F-12: X-109's header also claims \"the universal form hijacker.\"** ⛔ *That one submits INTO a prospect's form; this one captures the tenant's OWN.* | ⭐ **the action ids disambiguate: `plugin.capture` here, `form.submit` there** — and **`doctor` asserts neither module contains the other's vocabulary** *(§171D's guard 3)* · ⛔ **REFUSES with PLUGIN_REJECTED** | ⑥ one plugin install · ⑦ captures everything |"
)

text = text.replace(
    "| ⚠️ **G6-35** | The takeover path | ⭐ **it runs on the tenant's OWN site, with the tenant's OWN key** · **trigger:** they install it | their credentials | ⛔ **the name suggests otherwise.** *\"Hostile takeover\" is the register's word, not ours — third strike on that class after `evasion` and `hijacker`* | the plugin is asserted to act **only** under credentials the tenant supplied, and **only on domains they own**, asserted by attempting a third-party domain |",
    "| ⚠️ **G6-35** | The takeover path | ⭐ **it runs on the tenant's OWN site, with the tenant's OWN key** · **trigger:** they install it | their credentials | ⛔ **the name suggests otherwise.** *\"Hostile takeover\" is the register's word, not ours — third strike on that class after `evasion` and `hijacker`* | the plugin is asserted to act **only** under credentials the tenant supplied, and **only on domains they own**, asserted by attempting a third-party domain · ⛔ **REFUSES with AUTH_REJECTED** |"
)

text = text.replace(
    "| **G6-26** · **G8-38** · **G7-46** | Speed · meta sync · white label — **one spec** | their site gets faster and stays in sync; the plugin wears the agency's name · **trigger:** install | the site | a meta sync overwrites a hand-written title the tenant valued | a tenant-edited value is asserted **never** silently overwritten — the sync **proposes** *(P-097's shape)* |",
    "| **G6-26** · **G8-38** · **G7-46** | Speed · meta sync · white label — **one spec** | their site gets faster and stays in sync; the plugin wears the agency's name · **trigger:** install | the site | a meta sync overwrites a hand-written title the tenant valued | a tenant-edited value is asserted **never** silently overwritten — the sync **proposes** *(P-097's shape)* · ⛔ **REFUSES with SYNC_REJECTED** |"
)

text = text.replace(
    "| G3-60 | Universal Form Hijacker | ENH | X-104 | ⚠️ **NAME COLLISION** — X-109's header also claims *\"the universal form hijacker\"*; the description here is the tenant's OWN WordPress site. C4 draws the line |",
    "| G3-60 | Universal Form Hijacker | ENH | X-104 | ⚠️ **NAME COLLISION** — X-109's header also claims *\"the universal form hijacker\"*; the description here is the tenant's OWN WordPress site. C4 draws the line · ⛔ **REFUSES with PLUGIN_REJECTED** |"
)
text = text.replace(
    "| G6-26 | Page Speed Optimization | ENH | X-104 | named in the header |",
    "| G6-26 | Page Speed Optimization | ENH | X-104 | named in the header · ⛔ **REFUSES with SYNC_REJECTED** |"
)
text = text.replace(
    "| G6-34 | Widget Auto-Injector | ENH | X-104 | named in the header |",
    "| G6-34 | Widget Auto-Injector | ENH | X-104 | named in the header · ⛔ **REFUSES with PLUGIN_REJECTED** |"
)
text = text.replace(
    "| G6-35 | WP Hostile Takeover | ENH | X-104 | the takeover path is named; ⚠️ it runs on the tenant's OWN site with their key — the name is the register's, not ours |",
    "| G6-35 | WP Hostile Takeover | ENH | X-104 | the takeover path is named; ⚠️ it runs on the tenant's OWN site with their key — the name is the register's, not ours · ⛔ **REFUSES with AUTH_REJECTED** |"
)
text = text.replace(
    "| G7-46 | Whitelabeling | ENH | X-104 | the plugin wears the agency's name |",
    "| G7-46 | Whitelabeling | ENH | X-104 | the plugin wears the agency's name · ⛔ **REFUSES with SYNC_REJECTED** |"
)
text = text.replace(
    "| G8-38 | SEO Meta Sync | ENH | X-104 | named in the header |",
    "| G8-38 | SEO Meta Sync | ENH | X-104 | named in the header · ⛔ **REFUSES with SYNC_REJECTED** |"
)
text = text.replace(
    "| G19-16 | Shortcode Ecosystem | ENH | X-104 | named in the header |",
    "| G19-16 | Shortcode Ecosystem | ENH | X-104 | named in the header · ⛔ **REFUSES with PLUGIN_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
