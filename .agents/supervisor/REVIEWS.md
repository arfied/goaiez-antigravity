# REVIEWS — Track 2 (UI) supervisor verdicts

Append-only. Opened 2026-09-02. Verdicts: PASS · PASS-WITH-NOTES · BLOCK.

## 2026-09-02 — pre-dispatch gate (UI-1, run 1 launched)

Verdict: n/a (no REPORT yet). `bash bin/supervise.sh` read-only, before vendor exists.

- DB guard: `.env` = goaiez_antig_ui ✓. `phpunit.xml` still pins goaiez_antig_test
  (Track 1's) — the gate's `--tests` exports goaiez_antig_ui_test over it; a bare
  `./vendor/bin/pest` in this worktree would hit Track 1's test DB. Brief item 2 uses
  the explicit prefix; hold the coder to it.
- §2 flags `app/tests/Journeys/JourneyHarness.php` in the last commit — that is
  Track 1's commit 7f50138 on main, inherited by track/ui. Not Track 2's doing.
- §2a: post-rewrite hook MISSING in this worktree (fresh checkout, hooks not
  installed). Amends/rebases here would not be recorded. Note for the owner.
- §2c: `dd([` at `app/app/Modules/X-179/Ui/ProspecttenantfacingTop3Preview.php:28`,
  committed on main in 50adae9. In Track 2 scope (`app/Modules/*/Ui`). Not part of
  UI-1; queued as a UI-2 item.
- §6 skipped: no vendor yet (expected, UI-1 item 1 installs it).
- `app/error_log` (untracked) was produced by this gate run's artisan call
  without vendor; harmless, coder may leave or remove it.

Run 1 dispatched 2026-09-02 via launch-coder.sh, pid 2175689,
log /home/goaiez/tmp/agy-grs-antig-ui-run1.log.

## 2026-09-02 — UI-1 run 1 — REPORT 2026-09-02T10:28:22-05:00

Verdict: **PASS-WITH-NOTES** on the commit; the brief is **UNRESOLVED on an owner
dependency**, not on the coder. No BLOCK items.

Commits reviewed: d0ed495 `chore: add Playwright screenshot rig`
(package.json, package-lock.json, scripts/ui-shots.mjs; 3 files, +96).

- One Rule: no forbidden path touched, no test weakened. ✓
- Gate after vendor: pint passed, phpstan 0 errors. ✓  (§2a hook missing and
  §2c `dd([` in X-179 are pre-existing, logged above.)
- REPORT is rule-10 shape, doctor stamp 20260829-0647 matches BUILD-STATE. ✓
- STATUS `stopped: RUNTIME` is the wrong label — nothing in the checker broke.
  Correct label is a brief item stopped `UNRESOLVED`, which the UNRESOLVED
  line does say: `type "vector" does not exist` — goaiez_antig_ui has no
  pgvector extension. Verified: create_knowledge_chunks_table uses
  `$table->vector()`; the extension needs a superuser and rule 05 / the
  bootstrap workflow put it on the owner. Coder was right not to work around.

Screenshot judged: `login.png` (1280×3220). It is a Laravel 500 page —
`SQLSTATE[42501] permission denied for table sessions` as goaiez_app on
goaiez_antig_ui. Same root cause: migrate aborted at the vector step, so
`runtime/goaiez-grants.sql` was never applied (rule 05: permission denied is a
GRANT problem, never RLS). Not a UI finding; no verdict on the login screen yet.

Rig notes for the fix run (carried into BRIEF):
1. Routes: `/builder` does not exist — website builder is
   `/advanced/website-builder` behind `EnsureAdvancedDashboard`. `/account`
   and `/home` and `/memberships` are real.
2. Login uses DatabaseSeeder's `test@example.com`; that user has no business,
   so `/home` will redirect or 403. Rig must seed (or factory) an OWNER with a
   business and log in as that.
3. No post-login assertion: if login fails every later PNG is the login page.
   Assert the URL after submit and exit non-zero.
4. Rig runs with `fullPage:true` at 1280 wide → 3220px tall on an error page.
   Keep fullPage, but the supervisor views a 700px-wide downscale; fine.

Owner dependency (one block, all 14 track databases):
  CREATE EXTENSION IF NOT EXISTS vector  as postgres, then coder re-runs
  `php artisan migrate` and applies runtime/goaiez-grants.sql per the
  bootstrap workflow.

Process note: agy pid 2175692 still alive 49 min after REPORT.md was written,
6 s CPU, futex wait, 0-byte log — print mode buffers until exit. launch-coder
refuses a second run while the pid lives; owner may need to kill it before the
fix dispatch. Dispatch count for UI-1: 1 of 2.

## 2026-09-02 — UI-1 run 2 dispatched (dispatch 2 of 2)

Owner confirmed `vector 0.8.6` on goaiez_antig_ui (and the block covered all
fourteen track databases). Run 2 launched via launch-coder.sh, pid 2368664,
log /home/goaiez/tmp/agy-grs-antig-ui-run2.log, on the BRIEF dated
2026-09-02 (run 2). If the same item fails again, it goes to the owner — no
third dispatch.
