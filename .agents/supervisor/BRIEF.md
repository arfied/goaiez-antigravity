# BRIEF — from the supervisor

updated: 2026-09-01
push: cleared through 09e2660 — `git push origin main` is allowed for those six
      commits now; item-5 commits stay local until the next PASS (see REVIEWS.md)
report: per wave, and on any stop

## Standing orders

1. `DB_DATABASE` stays `goaiez_antig_dev` in `app/.env` and `goaiez_antig_test`
   in `app/phpunit.xml`. `goaiez_antig` is production (anti.goaiez.com); a test
   run from this checkout dropped its schema on 2026-08-31. Never change either
   value. Never point anything at `goaiez_antig`.
2. Commit per module, report per wave — rule 10.
3. Cite nothing `php artisan why <id>` cannot resolve.

## Current task — in this order

**Items 1–4: PASS-WITH-NOTES (11:25). Item 5: BLOCK (12:15).** Current task is
**5b** below. Nothing from item 5 pushes until the next PASS.

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

### 5. Audit H-tier — DONE except the BLOCK items below

Twelve findings committed (`cd8104b..605713a`); reviewed 12:15 in
`REVIEWS.md` — **BLOCK**. Per-commit acceptance notes were here and now live in
`REVIEWS.md`. The table of findings is in git history of this file if needed.

### 5b. Clear the item-5 BLOCK — current task, before anything else

Work the eight numbered items in the 12:15 block of `REVIEWS.md`, in order,
one commit each. Then `bash bin/supervise.sh --tests` must print
`pint … passed`, `phpstan … passed, errors 0`, `Feature failures 0`, and only
the twelve journey errors. Then write `REPORT.md` **in the rule-10 shape** and
stop for review. Nothing from item 5 pushes before that review says PASS.

### 6. Then wave 12 — J11 the site

`state.py next`: remaining **X-178, X-103, X-102**. Work only those.
`JOURNAL.md` shows twelve modules flipped to `BUILDING` at
`2026-08-31T13:04:41` in one second — a batch mark, not work.

## Report when

After 5b (rule-10 shape), again when wave 12 closes, and on any stop condition.
