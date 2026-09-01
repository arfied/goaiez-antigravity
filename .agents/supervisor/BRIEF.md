# BRIEF — from the supervisor

updated: 2026-09-01
push: gated        (gated = push only after PASS in REVIEWS.md · free = push as you go)
report: per wave, and on any stop

## Standing orders

1. `DB_DATABASE` stays `goaiez_antig_dev` in `app/.env` and `goaiez_antig_test`
   in `app/phpunit.xml`. `goaiez_antig` is production (anti.goaiez.com); a test
   run from this checkout dropped its schema on 2026-08-31. Never change either
   value. Never point anything at `goaiez_antig`.
2. Commit per module, report per wave — rule 10.
3. Cite nothing `php artisan why <id>` cannot resolve.

## Current task — in this order

**Baseline review of `main` is a BLOCK** (`REVIEWS.md`, 2026-09-01). Items 1–3
come before any wave-12 module. Each is its own commit. Nothing pushes until
the next review says PASS.

### 1. Clean tree — but do NOT commit the 2FA regression

- `app/app/Support/Auth/SecondFactor.php` in the working tree changes
  `required()` from `return $user->role->isPlatformStaff();` to
  `return false;`. That is audit finding **H-1** (mandatory staff 2FA off), not
  the disconnect. **Discard it**: `git checkout -- app/app/Support/Auth/SecondFactor.php`.
  Production already carries the correct predicate plus the enrolment fix —
  see item 1b.
- The three blade diffs are safe: `account/home` fixes the dead
  `route('account.widget')` → `account.website` (the audit's logged error),
  `account/nav` is an overflow fix, `website-builder` adds an enter-key
  binding. Commit them with `NEXT-SESSION.md` as
  `chore: disconnect checkout from production (2026-09-01)`.
- `chore(supervisor): add supervisor arrangement` — the untracked `CLAUDE.md`,
  `.claude/`, `.agents/rules/10-supervisor.md`, `.agents/supervisor/`,
  `bin/supervise.sh`, plus the `AGENTS.md` edit. Add them as they are.

### 1b. Backport production's in-place fixes into git — `fix: backport 2026-09-01 production fixes (audit C-1, C-3, H-1)`

Production (`/home/goaiez/public_html/anti.goaiez.com`) is a file copy with
**no `.git`** and 157 files newer than this repo (audit H-10). The fixes the
audit marks "Fixed 2026-09-01" exist **only there**; the next deploy from git
would revert them. Copy **into the repo, read-only from production**:

- `routes/console.php` (prod: 52 scheduled tasks, 308 lines; repo: 4, 23 lines) — C-3
- `tests/Feature/Architecture/SchedulingTest.php` (prod only) — C-3
- `database/migrations/2026_09_01_000001_reapply_platform_scope_rls_exemption.php`
  and `2026_09_01_000002_exempt_operator_alerts_from_tenant_rls.php` (prod only) — C-1
- `app/Support/Auth/SecondFactor.php`, `app/Providers/FortifyServiceProvider.php`,
  `app/Http/Controllers/Auth/TwoFactorSetupController.php`,
  `app/Http/Requests/Auth/TwoFactorLoginRequest.php` (prod only),
  `resources/views/auth/two-factor-setup.blade.php`, `tests/Feature/Auth/` (prod only) — H-1
- `resources/views/components/layouts/` (prod only) — the `layouts.app` 500

⛔ Do **not** copy back `tests/Journeys/*` (prod holds the simulation harness
the contract forbids), the 12 `tests/Modules/*Test.php` that differ (each one
must be read: a test edited to match the code is a CHECK change — list any
such under `REFUSED`/`UNRESOLVED`), `phpunit.xml`, or the `manifest.php`
files (regenerate those in item 4 instead). Do not write anything under
`public_html`.

Verify: `bash bin/supervise.sh --tests` — Feature suite has 0 failures (the 4
2FA tests go green), and `php artisan schedule:list | wc -l` ≥ 52.

### 2. Tell the truth about the journeys — `fix(state): journeys are red until a real transport runs them`

- `python3 bin/state.py journey J1 red` … through `J12`. The green marks of
  2026-08-29/30 preceded any harness that could pass.
- Delete the 12 files in `app/storage/app/evidence/journeys/`. They were
  written by the simulation harness of `84eb0f5`, which the contract forbids;
  they are gitignored, nothing in history is lost, and a proof no transport
  produced is the one thing the runtime exists to refuse.
- `php artisan doctor --stage=journey`, then `python3 bin/state.py stage
  journey <n>` with the number it prints. It will be 12. That is the true count.
- ⛔ Keep the throwing harness. Do not implement a journey in this checkout
  unless a real transport and a real credential exist here — they do not, by
  design, since the disconnect. When `state.py next` reaches `JOURNEYS`, the
  honest outcome is `UNRESOLVED — <credential/transport missing>` per journey.

### 3. Make CI able to run — `fix(ci): pin PHP 8.4, the stack rule 07 already pins`

- `.github/workflows/ci.yml`: `php-version: '8.4'`.
- `app/composer.json`: `"php": "^8.4"`. Do not `composer update`; the lock is
  already 8.4-only.
- Verify locally: `cd app && composer check-platform-reqs` exits 0.
- **Owner, not you:** four of the last five runs never started — GitHub
  billing / spending limit on the `arfied` account. Say so in `REPORT.md`; do
  not work around it.

### 4. Pint — `fix(style): generator emits pint-clean manifests; format hand-written files`

- Find where `ModuleScaffoldCommand` writes `manifest.php` and make it emit
  what `phpdoc_separation` wants. Regenerate. Then `./vendor/bin/pint`.
- Verify: `./vendor/bin/pint --test` clean **and** a second
  `php artisan module:scaffold` leaves `git status --short` empty — a
  generator and its output must agree, or the next scaffold reintroduces it.
- `tests/Journeys/JourneyHarness.php` may change only by whitespace:
  `git diff -w HEAD -- app/tests/Journeys/JourneyHarness.php` must print
  nothing. Any other change there is a stub and a BLOCK.

### 5. Audit H-tier — one wave, one commit per finding, before any module

From the 2026-09-01 production audit (57 findings). These are live-customer
harm in code nobody has written in either tree. Order is harm per hour. Each
gets its own commit `fix(audit-H-n): …` and at least one test that fails
without the change — say which test in `REPORT.md`. Do not run
`pixel:publish`, `composer update`, or anything against a database other than
`goaiez_antig_dev` / `goaiez_antig_test`.

| # | Finding | Where | Fix | Verify |
| :--- | :--- | :--- | :--- | :--- |
| H-3 | Every `customer.subscription.*` Stripe webhook violates `subscriptions_gateway_matches_its_ids` | `app/Services/Billing/Subscriptions.php:363-401` `applyStripeSubscription()` | write `gateway => 'stripe'` with `stripe_subscription_id` | a test applying a Stripe subscription event inserts a row (fails today on the CHECK) |
| H-2 | Tenants with no Authorize.Net card are routed to Stripe top-up, and the vault key is `sk_test_` | `app/Livewire/Account/Credit.php:1071-1073` | gate the Stripe path behind a live-mode check (`StripeApi::isLive()` or equivalent: key prefix `sk_live_`), otherwise refuse with a message — the owner swaps the key, not you | test: test-mode key ⇒ no redirect to Stripe checkout |
| H-5 | `Storage::disk('s3')` throws on first use — exports, MMS, voicemail, L0 archive | `composer.json` / `composer.lock` | `composer require league/flysystem-aws-s3-v3` (adds the package only; no `composer update`) | `php -r 'var_dump(class_exists(\Aws\S3\S3Client::class));'` → `true`; `composer check-platform-reqs` still 0 |
| H-4 | Worker heartbeat never written; health can never alert | `README-RUNTIME.md` (the listener text) · `app/Providers/AppServiceProvider.php` · `app/Services/Ops/PlatformHealthChecks.php:334-338` | `queue.looping` listener writes `goaiez:worker:heartbeat` | test: firing `Looping` writes the key |
| H-14 | `app:deploy-check` crashes on `__PHP_Incomplete_Class` Carbon from the DB cache; mail check reads an undefined config key | `app/Console/Commands/DeployCheckCommand.php:84,100,149` · `config/cache.php:134` | store a Unix timestamp, not Carbon; read the ceiling via `MailQuota::ceiling()`; leave `serializable_classes` alone | `php artisan app:deploy-check` runs to completion against `goaiez_antig_dev` |
| H-6 | `/p.js` serves a 0-byte 200 with `max-age=300` when no bundle is published | `app/Http/Controllers/Pixel/PixelBundleController.php:44` `pointer()` | when no `PixelBundleVersion` exists: `Cache-Control: no-store`, and a non-empty body or a 404 — pick one, record it `(R245)` | test: unpublished ⇒ `no-store` header; published ⇒ unchanged |
| H-12 | Setup step 2 "Try a different link" throws on a `#[Locked]` property | `app/Livewire/Setup/FindBusiness.php:79` · `resources/views/livewire/setup/find-business.blade.php:34` | add `discardCandidate()` action; `wire:click="discardCandidate"` | Livewire test clicks it and asserts `candidate` is null |
| H-13 | Advanced "Citations" persists invented NAP rows; 12 placeholder screens toast actions that never ran; any owner can enable the dashboard | `app/Livewire/Advanced/Citations.php:53-77,97-105` · `Posts.php:24` · `Voice.php:26` · `WebsiteBuilder.php:121,126` · `Integrations.php:26-32` · `app/Livewire/Account/Settings.php:255-263` | never persist demo rows; label placeholder screens "Preview — not live" and remove the false toasts; gate `toggleAdvanced()` | test: Citations mount creates zero rows; `toggleAdvanced` refused for non-owner |
| H-7 | OAuth identity merges onto any existing row with the same email; verification is off; signup is open | `app/Http/Controllers/Auth/OauthLoginController.php:264-278` | merge only when `email_verified_at` is set; otherwise send a verification link and stop | test: unverified same-email user ⇒ no link, no login |
| H-8 | Module `Business::provision()` assigns every business to user #1, can insert a plaintext-password user, and runs raw `SET app.business_id` | `app/Modules/X-121/Models/Business.php:23-46` · `X-118/Actions/OnboardingStartAction.php:35-40` · `X-112/Domain/AgencyEngine.php:27` | delete the module provisioner (core `TenantProvisioner` is the one path); add an architecture test forbidding `SET app.business_id` outside `app/Support/Tenancy.php` | the new arch test lists 0 offenders |
| H-15 | Credential rotation killed the worker; no `queue:restart` in any deploy step | `deploy/deploy.sh` | add `php artisan queue:restart` after the code copy | `grep -n queue:restart deploy/deploy.sh` |
| M-19 | CI runs as the Postgres superuser, so RLS is never exercised; suite dies silently at 128 MB | `.github/workflows/ci.yml` · `phpunit.xml` | CI creates `goaiez_app`-shaped role without BYPASSRLS and runs tests as it; `memory_limit=512M` in `phpunit.xml`'s `<php>` — **this is the one legal `phpunit.xml` edit; the `DB_DATABASE` line stays `goaiez_antig_test`** | `supervise.sh` §0 unchanged; CI job runs once GitHub billing is fixed |

⛔ Owner decides, not you — leave `TODO(Q-045)` with a recommendation and move
on: H-9 (112 unwired modules booting per request — architectural), H-11
(`intl` in EasyApache), C-4 (backups), the Stripe live key, credential rotation.

**Regression guard for the whole wave:** `bash bin/supervise.sh --tests` must
show Feature failures 0 and the module/journey numbers **unchanged or better**
than the report after item 1b. A test that went from red to green by an edit to
the test is a CHECK change — `REFUSED`.

### 6. Then wave 12 — J11 the site

`state.py next`: remaining **X-178, X-103, X-102**. Work only those.
`JOURNAL.md` shows twelve modules flipped to `BUILDING` at
`2026-08-31T13:04:41` in one second — a batch mark, not work.

## Report when

After item 4 lands (so the BLOCK can be lifted and a push allowed), again when
item 5 closes, again when wave 12 closes, and on any stop condition.
