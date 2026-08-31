# `plugins/wordpress/` — the WordPress plugin

**`BUILD-PLAN` §2.11.3 slice F2. Written 2026-08-20, decisions 6080–6099.**

⛔ **THIS DIRECTORY IS NOT LARAVEL AND NOTHING IN IT IS AUTOLOADED.** It is PHP
that runs inside a stranger's website, with write access to it — doc `19` §3.3's
*"treat it as a security product"*, and `BUILD-PLAN`'s own *"the second least
supervised code this company ships"*.

⛔ **THE DIRECTORY NAME IS THE OWNER'S RULING OF 2026-08-20**, answering
`BUILD-PLAN` §2.11.6 Q1, which had been open since 2026-08-19. Decision 6080.

## ⛔ Nothing in `app/` calls this plugin, and that is this slice's shape

**There is no `CmsAdapter` implementation that talks to these routes.** `app/`
does not know this directory exists. That is 272 — a thing with no caller — and
it is recorded here rather than papered over, on the model of slice A's log
driver (*"nothing touches a real site in this slice and that is the
deliverable"*).

It is deliberate. `app/Services/Actuation/WordPress/**` and
`app/Contracts/CmsAdapter.php` were held by another lane on the day this was
built, and two lanes in one seam is how this project got a merge that
auto-resolved wrongly. **What is owed is named at the bottom of this file.**

⚠️ **So every claim in here about what the plugin does is a claim about code no
production request has ever reached.** The tests drive its pure-PHP core
directly; `docs/WORDPRESS-STAGING-CHECKLIST.md` items 19–36 are the rest, and
none of them has been performed.

## What covers this code, given that two of the three usual gates do not

| Gate | Covers `plugins/`? |
| --- | --- |
| **Pint** (`composer lint`) | ⛔ **No, deliberately** — `pint.json` excludes it. Decision 6081: Pint's default discovery *does* reach here (measured, not assumed: nine fixers on a probe file, including `indentation_type` and `yoda_style`), and the Laravel preset **reverses** WordPress Coding Standards on both. A reviewer at wordpress.org reads this against WPCS |
| **Larastan** (`composer stan`) | ⛔ **No** — `phpstan.neon`'s `paths:` are `app`, `config`, `database`, `routes`, so this was already out of scope. Decision 6082: it is the right answer (this code runs against WordPress's globals, which Larastan cannot see) and it means the plugin gets **no static analysis at all** |
| **`php -l`** | ✅ Every file, as a test — the only automatic syntax gate this directory has |
| **`tests/Feature/Architecture/PluginSourceTest.php`** | ✅ **Substantially all of the automatic coverage this directory has, and the file itself is the inventory rather than this cell** — a lint per property, each driven red by a planted file that is deleted again. ⚠️ **This cell listed six and the file has held more than six since; a hand-kept index of another artefact goes stale silently**, so what is stated here is the property. ⛔ **It also holds the two premises the other lints rest on**: that `pint.json` still excludes this directory, and that no file here declares a namespace or imports a function — without which the forbidden-construct lint resolves a name against a namespace that is not the one it assumes |
| **`tests/Feature/Plugin/*`** | ✅ Drives the signature verifier and the capability gate as plain PHP — the halves that need no WordPress |
| **`docs/WORDPRESS-STAGING-CHECKLIST.md`** | ⚠️ Everything else, by a person, against two real installs. **Not yet performed** |
| **wordpress.org plugin review** | ⚠️ Not submitted. See the submission section below |

## The HTTP surface — the contract the Laravel half implements

Namespace `goaiez/v1`. Every route's `permission_callback` is the signature
verifier; there is no public route.

| Method | Route | Body | Answers |
| --- | --- | --- | --- |
| `GET` | `/status` | — | plugin/WP/PHP versions, multisite, kill-switch state, acting user + its refusal, writable fields, speed-fix support map, live fixes, IndexNow key state and location, the robots/sitemap report, which SEO plugin is active, `home_url` |
| `POST` | `/page/snapshot` | `url`, `fields[]` | `present` (bool), `post_id`, `url`, `fields{}` |
| `POST` | `/page/write` | `change_id`, `url`, `after{}`, `summary` | `post_id`, `url`, `applied{}` |
| `POST` | `/page/create` | `change_id`, `url`, `after{}`, `summary` | `post_id`, `url`, `created` |
| `POST` | `/page/revert` | `change_id` | `change_id`, `restored[]` or `unpublished` |
| `POST` | `/indexnow-key` | `key` (`''` clears) | `key_present`, `key_location` |
| `POST` | `/sitemap` | `announce` (bool) | the robots report |
| `GET` | `/site-report` | — | `inventory{}` (scripts, styles, third-party origins), `support{}` |
| `POST` | `/speed-fix` | `field`, `payload{}` \| `withdraw` | `live[]` |
| `POST` | `/disconnect` | — | `disconnected` |

**Signing.** Three headers — `X-Goaiez-Signature`, `X-Goaiez-Timestamp`,
`X-Goaiez-Nonce` — over the canonical string

```
GOAIEZ-HMAC-SHA256-v1\n{METHOD}\n{route}\n{timestamp}\n{nonce}\n{sha256(body)}
```

signed with HMAC-SHA256 and the site's secret, hex, lower case. ±300s skew,
single-use nonce. `plugins/wordpress/includes/class-goaiez-signature.php` is the
authority and says at length what is **not** covered.

**Refusal codes** a caller must handle: `not_paired`, `missing_signature`,
`malformed_signature`, `malformed_timestamp`, `malformed_nonce`,
`stale_timestamp`, `replayed`, `bad_signature` (all 401); and, from the
handlers, `goaiez_write_access_off`, `goaiez_too_powerful`, `goaiez_too_weak`,
`goaiez_no_actor`, `goaiez_cannot_edit_this_page`, `goaiez_absent`,
`goaiez_foreign_host`, `goaiez_field_not_writable`, `goaiez_write_did_not_take`,
`goaiez_write_failed_and_not_restored`, `goaiez_already_there`,
`goaiez_nested_address_not_creatable`, `goaiez_landed_elsewhere`,
`goaiez_never_defer`, `goaiez_fix_not_supported`, `goaiez_malformed_key`.

## What this plugin can do that core REST cannot — and what it still cannot

✅ **Serve the IndexNow key file from the site root** — decision 5581's first
un-reachable thing. `App\Contracts\IndexNowKeys` is the seam; the Laravel side of
it is owed.

✅ **Resolve any URL to a post, including the front page.** `url_to_postid()` is
WordPress's own resolver running the site's own rewrite rules, and
`get_option( 'page_on_front' )` needs no capability at all — so decision 5592's
*"the front page is not addressable by a correctly-scoped credential"* and 5593's
slug ambiguity are both facts about core REST rather than about T1.

✅ **Four of `28` §4.1's seven speed fixes**, and — the larger half —
**the input to compute them**, which decision 5852 records as missing
everywhere.

⛔ **Not the meta description.** Decision 5591 stands: it is an SEO plugin's
private post meta and writing another plugin's private meta is owed to a slice
that has read that plugin's documentation. What is new is that `/status` **says
which plugin it is**, so the next slice looks it up instead of guessing.

⛔ **Not `image_formats`, ever, at this tier.** `28` §4.1 specifies *"on-server
convert"*, which is this plugin writing files into somebody's uploads directory.
It never writes a file.

⛔ **Not `lazy_loading`,** and not because it is hard — see
`class-goaiez-speed.php`.

⛔ **Not announcing a sitemap on a site whose owner ticked *discourage search
engines*.** See `class-goaiez-robots.php`. This is half of the exception
decision 5683 hands to F2, and it is the half F2 must refuse.

## wordpress.org submission — prepared, not submitted

⛔ **Nothing here has been submitted and the developer account, the public
listing name and the support inbox it obligates are the owner's**
(`BUILD-PLAN` §2.11.6 Q2). Before a zip goes anywhere:

1. **Fill the three `TBD-` markers in `readme.txt`** — `Contributors`,
   `Tested up to`, `Stable tag`. A build-failing test names them.
   ⚠️ *"Tested up to"* must be a version somebody actually tested against, which
   is the staging checklist, which has not been performed.
2. **Choose the slug, knowing it is permanent**: *"Once your plugin is approved,
   this name **cannot** be renamed."* It fixes the URL, the folder name, the SVN
   address and the text domain. The directory here is `goaiez`.
3. **Remove `Update URI: false` from the header**, or set it to
   `https://wordpress.org/plugins/{slug}/`. While it is there, wordpress.org can
   never update the plugin. It is there on purpose until then — see the header
   comment.
4. **Perform `docs/WORDPRESS-STAGING-CHECKLIST.md`**, all of it.
5. Accept that the listing opens a **permanent public support forum**.

### The review clock, verified rather than assumed

⚠️ **§2.11.6 Q2 records that the duration is *"not verified here"*. It is now,
and the three official figures disagree with each other and with the live
queue** — all fetched 2026-08-20:

| Source | Says |
| --- | --- |
| `wordpress.org/plugins/developers/add/` | *"It takes anywhere between 1 and 10 days. We attempt to review all plugins within 5 business days of submission, but the process takes as long as it takes"* |
| Plugin Developer FAQ (`developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/`, page last updated **2026-06-30**) | *"There's no official average… If your plugin is small and all the code is correct, it should be approved within **fourteen** days of *initial review*"* — and *"more than 3500 people mid-review"*. ⚠️ **"of initial review", not of submission** |
| Plugins Team update, **2026-08-17** (`make.wordpress.org/updates/2026/08/17/plugins-team-17-aug-2026/`) | **5,217** submissions in the queue, **4,361** of them older than seven days |
| Plugin Developer FAQ | *"If your plugin review is not complete after three (3) months, we will reject your submission in order to keep the queue maintainable"* — resubmission is allowed and the previous review can be continued |
| Team status post, **2026-06-13** (`make.wordpress.org/plugins/2026/06/13/update-on-the-status-of-the-team-june-2026/`) | Submissions *"climbed again to a new record of around 700 per week"*, 2.7× 2025 and 5× 2024; the April peak of ~1,050 fell *"back to nearly zero"* by June |

**So: the published target is five business days, 84% of the live queue is
already past it, and the vendor's own worst case is three months followed by an
automatic rejection.** §2.11.5 conflict 7's ordering argument — *"a clock of
unknown length in front of row 9 blocks the row on a duration nobody has
measured; the same clock beside E and L costs nothing if it runs long"* — is
**strengthened** by the measurement, not weakened. Decision 6083.

## What is owed, and to whom

| # | Owed | Whose |
| --- | --- | --- |
| 1 | **A `CmsAdapter` implementation that speaks these routes** — the caller that makes this directory not-272. It needs `app/Services/Actuation/WordPress/**`, held by another lane on 2026-08-20 | A follow-on slice |
| 2 | **An `IndexNowKeys` binding** that mints a key, sends it to `/indexnow-key` and answers `IndexNowKeyOutcome::found()`. `ActuationTest`'s *"this build still resolves the IndexNow key provider that cannot answer"* is the lint that has to change with it | The same slice |
| 3 | **`ActuationTiers`, `SpeedFixes` and `fieldSupport()` reading the plugin's answers** — 5851's *"nothing in `SpeedFix` moves when F2 lands"* is only true once something asks | The same slice |
| 4 | **The staging checklist, performed** — items 19–36 are new here | A person, two installs |
| 5 | **The wordpress.org submission** and everything in the list above | The owner |
| 6 | **A ruling on doc `41` Part 1's T1 row**, which promises *"All 7 fixes"*. Four are applied, one is owed, two are refused — one of them permanently, on our own no-filesystem-writes rule | The owner |
