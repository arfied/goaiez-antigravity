# REVIEWS — supervisor verdicts

Append-only. Newest block at EOF. Verdicts: `PASS` · `PASS-WITH-NOTES` · `BLOCK`.
A `BLOCK`'s items are also copied to the top of `BRIEF.md`.

---

## 2026-09-01 — arrangement opened

Verdict: — (no report to review)

Baseline at open, from `bin/supervise.sh`: see the supervisor's first session
notes below this line once it has run.

## 2026-09-01 — baseline of `main` at 3c5ed70 (not a report review)

Verdict: **BLOCK** — four findings on `main` itself; wave 12 may proceed only
after items 1–3 of `BRIEF.md` are committed.

1. **Journeys are 0/12, state says 12/12.** `pest --filter=Journey`:
   `tests 12 · passed 0 · errors 12`, every one
   `JOURNEY HARNESS NOT IMPLEMENTED`. `JOURNAL.md` lines 136–147 and 410–421
   mark J1–J12 green at `2026-08-29T16:43` and `2026-08-30T02:12`; the first
   harness that did not throw is `84eb0f5` (2026-08-31 03:39). Those marks
   preceded any test that could pass. The 12 files in
   `app/storage/app/evidence/journeys/` are dated 2026-08-31 08:01 and were
   written by the `84eb0f5`/`9124775` simulation harness — the stub the
   contract forbids. `3c5ed70` restored the throwing harness (correct) but left
   the marks and the evidence, so `doctor --stage=journey` reads clean off
   evidence no real transport produced. That is the fabricated-evidence shape.
2. **CI on `main` is red for two non-code reasons.** Runs 33422297423,
   33420106599, 33394715300, 33391560129 never started:
   *"job was not started because recent account payments have failed or your
   spending limit needs to be increased"* — **owner action, GitHub billing.**
   Run 33469812213 started and died in `Install Dependencies`:
   `symfony/* v8.1 requires php >=8.4.1 -> your php version (8.3.33)`.
   `ci.yml` pins `php-version: '8.3'`; `composer.json` says `"php": "^8.3"`;
   the lock and rule 07 are PHP 8.4; local is 8.4.23.
3. **`pint --test` fails on ~130 files.** ~120 are generated
   `app/Modules/*/manifest.php` (`phpdoc_separation`) — fix belongs in the
   generator, never in the generated file. Hand-written:
   `GoaiezRuntimeServiceProvider.php`, `X-148/Actions/RetrievalSearchAction.php`,
   `ImpactCommand.php`, `ContextCommand.php`, `DbBootstrapCommand.php`,
   `ModuleScaffoldCommand.php`, `MapCommand.php`,
   `Phase3StarterTemplatesSeeder.php`, `tests/Patches/FourPatchesTest.php`,
   `tests/Journeys/JourneyHarness.php` (`phpdoc_align` only).
4. **Dirty tree.** 5 uncommitted files from the 2026-09-01 disconnect.

Green at baseline: `doctor:selftest` sound · `integrity 0` · build stamp
`20260829-0647` matches `BUILD-STATE.runtime_build` · phpstan 0 errors ·
DB guard `goaiez_antig_dev` / `goaiez_antig_test`.

## 2026-09-01 11:25 — review of REPORT 11:22Z (brief items 1, 1b, 2, 3, 4)

Verdict: **PASS-WITH-NOTES** — baseline BLOCK lifted. **Push cleared through
`09e2660`** (the six commits `49ecc14..09e2660`). Item-5 commits stay local
until the next PASS.

Checked, each commit read against its brief item:

- `49ecc14` disconnect — 4 files, `SecondFactor.php` correctly excluded.
- `189b366` supervisor files — added as-is, not edited.
- `f43e1fc` backport — the 11 files named in 1b and nothing from the do-not-copy
  list. `SecondFactor::required()` is `$user->role?->isPlatformStaff() ?? false`
  (H-1 closed in git). `routes/console.php` 52 tasks. `SchedulingTest` 7 tests,
  `TwoFactorChallengeTest` 9 tests, grep counts match the report.
- `cae87f5` journeys — J1–J12 `-> red` in `JOURNAL.md`, `stage journey = 12`,
  `evidence/journeys/` emptied, harness untouched. State now tells the truth.
- `135f091` CI — `php-version: '8.4'`.
- `09e2660` pint — fix is in `ModuleScaffoldCommand` (the template now emits the
  ` *` separator), 124 manifests regenerated from it, `JourneyHarness.php` has
  0 non-whitespace lines changed (`git show -w`). `pint --test` passes.

Gate (`supervise.sh --tests`): DB guard `goaiez_antig_dev`/`goaiez_antig_test`
· selftest sound · integrity 0 · build `20260829-0647` = `runtime_build` ·
tests **856 run, 844 pass, 12 errors = the twelve journeys throwing by
design; Feature failures 0** (the four 2FA tests are green). `supervise.sh` §2
flagged `JourneyHarness.php` — inspected, whitespace only, accepted.

Notes (not blocking):

1. `app/composer.json` still says `"php": "^8.3"`; item 3 asked for `^8.4`.
   Fold into any later commit that touches `composer.json` (H-5 will).
2. The item-4 idempotence proof — a second `php artisan module:scaffold`
   leaving `git status --short` empty — is not in the report. Run it and put
   the line in the next `REPORT.md`.
3. Report shape is right; keep `RAW` for stages you did not fix and paste
   `supervise.sh`'s verdict block as the brief asks.

Owner items carried forward: GitHub billing on `arfied` (CI cannot start),
backups, credential rotation, `intl`, live Stripe key.

## 2026-09-01 12:15 — review of item 5 (REPORT 12:05, run ended AGY_EXIT=0)

Verdict: **BLOCK** on item 5. Nothing from `cd8104b..605713a` pushes.
**Push remains cleared only through `09e2660`.**

Gate (`supervise.sh --tests`): DB guard intact · all files parse · selftest
sound · integrity 0 · build stamp matches · phpstan 0 · **tests 868 run, 856
pass, 12 errors = the journeys; Feature failures 0** · **pint FAILS on 132
files** (measured on HEAD; the report's "pint perfectly green" is not what
the tree says — CI runs `pint --test` as a hard gate).

Accepted as committed: H-3, H-5, H-4, H-14, H-6, H-12, H-13, H-7, H-15, and the
`NoRawSetBusinessIdTest` arch test (per-commit notes in `BRIEF.md`).

BLOCK items, each its own commit, in this order:

1. **M-19 `2fab5b2` — two defects.** (a) `ci.yml:71` `CREATE ROLE goaiez_app
   WITH LOGIN PASSWORD password\ NOBYPASSRLS` — the password is unquoted; the
   step fails with a SQL syntax error. Write `PASSWORD 'password' NOBYPASSRLS`.
   (b) `phpunit.xml` `memory_limit` was **2048M** (`e04b37d`, deliberate) and
   was *lowered* to 512M. The brief's "512M" was a floor against the 128M
   default, not a target. **Restore 2048M.** `DB_DATABASE` line untouched —
   good.
2. **H-8 `d473e75`/`605713a` — the provisioning call is still wrong.**
   `TenantProvisioner::provision(User $user, ?string $auditToken = null)`;
   both `X-118/OnboardingStartAction:44` and `X-112/AgencyEngine:32` pass the
   business *name* as `$auditToken`, and the comment above line 44 asserts a
   four-argument signature that does not exist. Read the method; set the name
   the way core does after provisioning, or record `UNRESOLVED`. Delete the
   false comment. Requiring `User $user` and throwing when absent — correct.
3. **H-2 `5cee4fe` — test still not load-bearing** (asserts `redirect` null on
   a second, fresh component). Same instance: `->call('buy')->assertNoRedirect()`
   + purchase cancelled; positive twin with `sk_live_` ⇒ `assertRedirect()`.
   Prove: revert the guard, test goes red.
4. **Pint.** `./vendor/bin/pint` (fix mode) over `app/` **and** `tests/`, then
   `./vendor/bin/pint --test` prints `passed`. Commit `style: pint (tests)`.
5. **Commit hygiene.** `605713a style: pint after audit wave` also carries the
   parse-error fix for three UIs and a sed rewrite of 121 module tests
   (`replace_script.sh` / `x121_tests.txt`). A fix inside a style commit is
   invisible to review. Do not rewrite history now; going forward, one
   concern per commit and the message says what it is.
6. **Stray files** — delete `replace_script.sh`, `x121_tests.txt`,
   `error_log`, `app/error_log` (untracked scratch; never commit them).
7. **Still owed from the 11:25 review:** `app/composer.json` `"php": "^8.4"`;
   the item-4 idempotence line (second `module:scaffold` ⇒ clean tree).
8. **Report shape.** The item-5 report is prose; rule 10's shape is required:
   `COMMITS` (raw log), `TESTS` with `grep -c` before/after per new test file,
   `STAGES`, `DOCTOR` stamp, `REFUSED`, and `supervise.sh`'s verdict block.

Notes, not blocking: `TestCase::provisionTenant()` falls back to
`User::first()` — tolerable in test infrastructure, not in app code.
`AdvancedDashboardTest` lost its fake-toast assertions because H-13 removed the
toasts — legitimate — but seeds the same four citations twice; tidy when
touched.
