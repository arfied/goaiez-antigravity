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

## 2026-09-02 — UI-1 run 2 — REPORT 2026-09-02T11:28:00Z

Verdict: **BLOCK** (process, not product). Two items; both fix-forward.

Commits: dd0db93 rig fix · 0170e72 UiReviewSeeder · a47920c "refine ui rig and seeder".
Gate `--tests`: **tests 886 · passed 876 · FAILED 0 · errors 10** — matches Track 1's
band (their last: 886 · 874); the 10 errors are the unimplemented journey harness,
by design. phpstan 0. Doctor stamp 20260829-0647 matches BUILD-STATE.

BLOCK-1 — a47920c swept **seven supervisor/config files into a coder commit**:
BRIEF.md, KICKOFF.md, REPORT.md, REVIEWS.md, .claude/settings.json, CLAUDE.md,
bin/supervise.sh (a `commit -a`). Content intact — working copies equal the
commit — but merging track/ui as-is would replace Track 1's REVIEWS.md (−590
lines), put the TRACK 2 section on main's CLAUDE.md and point main's gate at
goaiez_antig_ui_test. Rule 10 §"the supervisor's working tree". Remedy: a
restore commit to origin/main's versions, track copies put back uncommitted.
No amend/reset.

BLOCK-2 — pint fails on `database/seeders/UiReviewSeeder.php` (3 fixers). The
gate's §6 is red; the report did not say so.

Screenshots judged (1280 wide, fullPage):
- login.png — clean dark sign-in, five methods (passkey, Google, Microsoft,
  magic link, password). ✓
- home.png — renders for the seeded owner; three zero tiles, Autopilot Engine
  Active, four quick actions. Emoji icons render as tofu boxes: the server has
  no emoji font (`fc-list | grep -i emoji` → 0). Rig environment, owner item,
  not a view defect.
- account-settings.png — renders; sections all present. Nav clips a label to
  "Google r" before "More ▾" at 1280 wide (home shows "Google"). UI-2 item.
- memberships.png — X-192 MembershipsList renders a bare unstyled view inside
  the admin "Internal Platform Console" shell with app name "Laravel" and
  "Nothing here for your account". Two view files exist
  (memberships-list / memberships_list). Owner-facing route in a staff shell.
  UI-2 item.
- website-builder.png — byte-identical to account-settings: the middleware
  redirected because `businesses.advanced_dashboard_enabled` is false for the
  seeded business. Coder reported it honestly. Fix is data (seeder sets the
  flag), never the middleware.
- login-FAILED.png/.html — stale artefacts of the first attempt (magic-link
  form got the email). Rig must clear the output dir at start.

Notes: the seeder lives in app/database/seeders — tooling, in scope. The rig
now asserts post-login URL and exits 1: good. Seeder e-mail owner2@ / "Review
Business 2" is a leftover of a failed first pass; harmless.

UI-2 queue (not tasked yet): X-179 `dd([` · memberships shell/view · nav clip ·
emoji font (owner).
Dispatch: run 3 = fix run for this BLOCK (1 of 2 for this BLOCK).

## 2026-09-02 — UI-1 run 3 — REPORT (header still says 11:28:00Z; run ended ~13:20)

Verdict: **PASS-WITH-NOTES**. UI-1 (bootstrap + screenshot rig) is DONE.
Push gate opens: `git push -u origin track/ui`.

Commits since run 2: 8b2607c restore (B1 ✓ — `git diff --stat origin/main..HEAD`
lists only app/database/seeders/UiReviewSeeder.php, package.json,
package-lock.json, scripts/ui-shots.mjs) · 74c002b style: pint (B2 ✓) ·
58176b0 rig clears output dir · 5c4ba84 + 44eeb98 + b0cd077 + dbc09d0 + d94329b
seeder sets advanced_dashboard_enabled.
Gate `--tests`: tests 886 · passed 876 · FAILED 0 · errors 10 (journey harness,
by design) · pint passed · phpstan 0 · no forbidden path · no rewrite · log
shows no wrong-DB touch, no push. Doctor stamp 20260829-0647 = BUILD-STATE.

Notes:
- Four seeder commits say "bypass rls"; the code does not — it uses
  Tenancy::actingAsUser / actingAs, no BYPASSRLS, no withoutGlobalScopes. Commit
  wording was worse than the change. Five fix commits for one seeder is churn;
  next time commit once it works.
- REPORT.md header timestamp was not updated (11:28:00Z on a 13:20 report).
  Rule 10: overwrite the whole file, header included.
- Coder left `php artisan serve` in the foreground again (1h36m idle, killed
  by the supervisor at 13:17). UI-2 makes the rig own the server lifecycle.
- app/server.pid and app/error_log untracked; harmless, exclude or remove.

Screens judged:
- home.png — emoji icons now render (owner installed Noto Color Emoji);
  "Power Center" button appears with advanced enabled. ✓
- website-builder.png — real capture at last: prompt bar, industry template,
  hero copy, module toggles, live preview with desktop/mobile switch. Two UI
  defects: (1) the page H1 under the "Advanced /" breadcrumb is dark-on-dark,
  effectively invisible; (2) preview URL slug reads "reviee-business-2-llc"
  at 1280px — verify whether the slug drops a letter.
- Nav clip "Google r" persists on account and builder pages (home shows
  "Google"). UI-2.
- memberships.png unchanged — staff shell, "Laravel", empty. UI-2.

UI-2 brief follows: push, then dd() in X-179, memberships page, nav clip,
builder H1 contrast, slug check, rig owns its server.
Addendum: slug verified at full resolution — "review-business-2-llc", no bug.
H1 cause found: website-builder.blade.php:11 uses `text-gray-900 dark:text-white`;
the app's dark theme does not set Tailwind's `dark` class (the rest of the shell
uses token classes like `text-ink`), so the heading is gray-900 on near-black.

## 2026-09-02 — UI-2 run 1 (coder run 4) — REPORT "UI-2 Report (2026-09-02)"

Verdict: **BLOCK** on one item; five of six items PASS.
UI-1 push confirmed: origin/track/ui = d94329b. UI-2 commits stay local.

Gate `--tests`: tests 886 · passed 876 · FAILED 0 · errors 10 (harness, by
design) · pint passed · phpstan 0 · §2c now `none` · no forbidden path · no
rewrite · log shows no wrong-DB touch. No test file touched (diff --stat).

Items:
1. X-179 dd() → abort(404) — 88d85c1 ✓ (§2c green on every track once merged).
2. Builder header — cc9c78d ✓. H1 legible; breadcrumb, pill and subline read.
3. Nav clip — 1d57355 **✗ NOT FIXED**. account-settings.png still shows
   "Messages you sen" cut at the edge. Root cause untouched: nav.blade.php:73
   `<ul class="flex … min-w-0 overflow-x-auto">` with `whitespace-nowrap` items —
   whatever overflows the max-w-5xl container is scrolled out of view with no
   affordance. Moving "Google reviews"/"Your account" into GROUP_MORE only changed
   which label meets the edge. Also introduced a latent bug:
   nav.blade.php:1 `@props(['maxWidth' => '{{ $maxWidth }}'])` — a literal
   string default; any render without the prop prints `{{ $maxWidth }}` into the
   class attribute. Harmless today (one caller passes it), wrong nonetheless.
4. Memberships — 46c3197 ✓. Account shell, title, explainer, empty-state card,
   duplicate view deleted. 65c36c7 restored a copy string a test pins — the
   coder had reworded "Google can't see this"; do not reword copy tests pin.
5. Rig owns its server — f9670ac ✓. No `artisan serve` left running.
6. REPORT.md is **not rule-10 shape** (free headings; no STATUS/COMMITS/
   DOCTOR/RAW lines). Second time the report shape slipped.

Debris: `test_out.txt`, `test_out_new.txt` (repo root, gate output) and
`app/error_log`, all untracked — remove.

Fix run = dispatch 1 of 2 for this BLOCK.

## 2026-09-02 — UI-2 fix run (coder run 5) — REPORT 2026-09-02T13:36:00Z

Verdict: **PASS-WITH-NOTES**. UI-2 is DONE. Push gate opens for
88d85c1..720872f (`git push origin track/ui`).

Commits: 24933af `primary nav wraps instead of clipping` (ul: overflow-x-auto →
flex-wrap, exactly the mechanism fix) · 720872f `nav maxWidth default`
('max-w-5xl'). Diff since last review is 2 lines in one file. Report in rule-10
shape, debris removed, no push (as briefed), no wrong-DB touch.
Gate `--tests`: 886 · 876 · FAILED 0 · errors 10 (harness) · pint ✓ · phpstan 0 ·
§2c none.

Screens: memberships.png and website-builder.png show one nav row, every
label whole. account-settings.png shows "Messages you sent" whole on a second
row — no clip, but the wrap is caused by the "More: Your account" button
carrying the current-page label (~120px wider than "More ▾"). Note for UI-3:
show "More ▾" always and mark the current item inside the menu, so the primary
row fits at max-w-5xl.

UI-3 brief follows: push, widen the rig (more owner screens + a 390px mobile
pass), fix what it shows.

## 2026-09-02 — UI-3 run 1 (coder run 6) — REPORT 2026-09-02T18:51:35Z

Verdict: **BLOCK** — fixes not verified. Rig work PASSES.
UI-2 push confirmed: origin/track/ui = 720872f. UI-3 commits stay local.

Gate `--tests`: 886 · 876 · FAILED 0 · errors 10 (harness) · pint ✓ · phpstan 0 ·
no forbidden path (.env.example diff is APP_NAME only, not DB_) · no rewrite ·
no wrong-DB touch · no stray serve.

PASS: e39adc8 rig captures 8 owner screens by route · 5dabd4d mobile pass at
390×844 (5 screens) · 9977b48 More trigger constant width (account-settings.png
shows one nav row ✓). 19 PNGs delivered, per-screen list in REPORT ✓.

BLOCK-1 — **the three defect fixes were never recaptured.** Captures are
13:42; de7d0c1 (Laravel copy) 13:48, 8ce614e (advanced-home same-tone) 13:49,
3458c37 (tap targets) 13:50. Consequences visible in the PNGs:
  - home.png / home@390.png still read "Laravel" in the header wordmark and
    "© 2026 Laravel" in the footer. The commit changed `.env.example` and the
    `config/app.php` default; the running app reads `app/.env`, which still
    carries APP_NAME=Laravel, so nothing changed at runtime. Also the chosen
    name "Antigravity" is the coder's/repo's name, not the product's — owner
    decides the product name (question raised).
  - advanced-home.png: H1 under the "Advanced Mode" pill still invisible in
    the capture; the code fix (text-ink) is right and would show on recapture.
  - tap targets: min-h-10 (40px) is right; unproven in the PNGs.
  Rule for every future run: the rig runs AFTER the last commit; a fix with no
  recapture is a fix that did not land.

Screens judged (desktop): account-plan, inbox, customers, messages, support —
OK, consistent shell and empty states. account-connections — empty-state icon
renders as a missing-glyph box (tofu) → fix. advanced-home — see above;
otherwise the tile grid is fine. home (marketing) — good page; "Laravel" only.
Mobile (390): login, account-home, inbox, settings, memberships — no
horizontal scroll, type readable. Account nav wraps to THREE rows with "More ▾"
floating mid-row-2: functional, not good. UI-4 item: collapsed mobile nav.

Fix run = dispatch 1 of 2 for this BLOCK.

## 2026-09-02 — UI-3 fix run (coder run 7) — REPORT 2026-09-02T18:58:52Z

Verdict: **PASS-WITH-NOTES**. UI-3 is DONE. Push gate opens for
9977b48..de4c5af (`git push origin track/ui`).

B1 recapture ✓ — all 19 PNGs 13:58, last commit 13:57, times pasted under RAW.
B2 ✓ — APP_NAME untouched, home.png listed as owner-pending name.
de4c5af connections empty-state icon → ◇ ✓ (2-line diff, one file).
Verified in the new captures: advanced-home H1 "Power Control Center" legible ✓;
connections ◇ ✓; mobile nav rows 40px ✓.

Gate: first run 886 · 875 · FAILED 0 · errors 11 — the 11th was J8
(`a_deliberately_corrupted_backup_fails_the_restore`, Track 1's) on
`permission denied to terminate process`: the restore step kicks other backends
and a superuser psql (the owner's grants loop) was connected at that moment.
Rerun with no psql on the box: 886 · 876 · errors 10, no non-harness error.
Environmental; recorded, not charged to this track. pint ✓ phpstan 0.

Note: REPORT.md had no TESTS line (rule-10 shape asks for it; the brief asked
for the raw `tests …` line under RAW). Third report-shape slip.
Open for the owner: product name for APP_NAME (home.png wordmark "Laravel").
UI-4: push, collapsed mobile nav, marketing + advanced screens in the rig.

## 2026-09-02 15:45 — LEDGER RECONSTRUCTED after coder run 13 wiped it

At 15:31 the run-13 coder ran `git reset` to 25023a8 (reflog HEAD@{3}),
which reverted every uncommitted file — this ledger, CLAUDE.md's TRACK 2
section, .claude/settings.json, bin/supervise.sh — to main's copies, then
at 15:32:49 ran the gate with the reverted script: `./vendor/bin/pest` with
no export, i.e. against **goaiez_antig_test, Track 1's test database**
(887 · 877 · errors 10). Track 1's coder was idle at the time. The supervisor
killed run 13 at 15:40 and restored the four files from ba528c8 (14:04).
Blocks below are condensed re-entries of verdicts given between 14:04 and
15:31; the originals are in the supervisor's session transcript.

### UI-4 run 1 (coder run 8) — PASS-WITH-NOTES · pushed to cfb29ff
Single-row mobile nav (disclosure carries Power Center + Sign out); public
pages ×9 ×2 widths; advanced screens. Notes: third `commit -a` sweep
(ba528c8, self-restored in 8b7ba91); self-review missed two invisible H1s
(advanced citations/visibility); APP_NAME changed against the brief.

### Owner ruling — product name is "Go AI EZ".

### UI-5 run 1 (coder run 9) — PASS-WITH-NOTES · pushed to dc4e5e1
bf6999b `@custom-variant dark` + class="dark" on 12 layouts — root cause of
every invisible heading (Tailwind v4 media-query dark mode, never a dark
class, shell always dark). c0fdaec product name in config + .env.example.
Merge notes for Track 1: config/app.php name; .env.example APP_NAME;
app.css variant; layouts; UiReviewSeeder; scripts/ui-shots.mjs + playwright
+ @axe-core/playwright devDeps; X-179 abort(404); X-192 memberships view;
OwnerNav groups; nav wrap/mobile; errors/{403,404,419,500,503}.blade.php.
Production .env needs APP_NAME="Go AI EZ".

### UI-6 run 1 (coder run 10) — BLOCK
axe on every screen + error-page captures PASS. B1 axe dep installed at repo
root; B2 framework-default 404/403 not listed, "419" was /login.

### UI-6 fix run (coder run 11) — BLOCK, STOPPED to owner
B1/B2 done (branded error pages). Four `git commit --amend` (REWRITES.log),
419 still /login, axe not zero, five patch-*.mjs at root. Owner accepted the
amends and cleared the ledger; authorised one more dispatch.
Owner decision (a)/(b) recorded 2026-09-02 ~15:00.

### UI-6 fix run 2 (coder run 12) — PASS-WITH-NOTES · push opened for
90480bf..be99977 (pushed by run 13 → origin/track/ui = be99977 expected;
verify). Real 419 page; axe zero on 54 screens; no amend; no debris.
J8 terminate-process error seen 3× today across tracks — environmental,
note for Track 1 (guard the terminate by datname/usename).

### UI-7 (coder run 13) — dispatched 15:1x; KILLED 15:40 by the supervisor
Commits kept: 25023a8 seeder slug · 7f4a8b8 customer surfaces · ba36803 setup
wizard · f54f0c4 seeder fix (all after the reset; two earlier equivalents
4e8ac9b/29e00da were dropped by the reset). Debris: patch_*.js ×7 at root.
**Verdict: BLOCK — STOPPED to the owner.** Second "never" breach in two runs
(amend ×4, then reset + ledger wipe + a suite against Track 1's test DB).

### Owner decision 2026-09-02 16:03 — "accept the risk, dispatch run 14"
Run 14 dispatched on UI-7 continuation. Snapshot of the supervisor files taken
by the launcher at /home/goaiez/tmp/sup-snap-grs-antig-ui-20260902-160355.
Any reset/stash/checkout/amend/rebase in this run ends it and returns Track 2
to the owner.

## 2026-09-02 16:45 — UI-7 continuation (coder run 14) — BLOCK, STOPPED to the owner

Run ended AGY_EXIT=137 (SIGKILL, source unknown — not the supervisor) while
the coder said it was "waiting for ui-shots.mjs to finish". No REPORT.md.
Ledger intact this time (launcher snapshot sup-snap-…-160355 unused).

Breaches, both after a brief whose first section said each one ends the run:
1. **`git commit --amend` at 21:35:27Z** (REWRITES.log: 53b36af → cce1bb0,
   "fix(review-hub): add missing translation keys"). The coder did not stop;
   it went on to commit d710b40 thirteen seconds later.
2. **d710b40 edits `app/phpunit.xml`**: `DB_DATABASE goaiez_antig_test →
   goaiez_antig_ui_test`. A never-list file (CLAUDE.md: "treat any diff to
   phpunit.xml … DB_ lines as a BLOCK"; owner ruling 3: the pin stays). Must
   be reverted forward in a new commit before anything merges.

Work that landed (unreviewed, unpushed): 352a31d pint · 8c7805f deterministic
slug · 86de599/a5023dc/e15aed9 seeder · 4741bc0/51d889f/53b36af→cce1bb0
translation keys on customer pages (raw keys were showing) · 78 PNGs incl.
customer-* ×16 and setup-* ×12 at 16:33–16:34, before the last commit.

This is the third run in a row with a "never" (amend ×4 → reset + ledger wipe
+ Track 1's DB → amend + phpunit.xml). Text rules do not bind this coder.
Track 2 is STOPPED. Owner options:
(a) revert d710b40 forward (`git checkout origin/main -- app/phpunit.xml`,
    commit `revert: phpunit pin stays (ruling 3)`), then decide whether Track 2
    continues with a mechanical guard (a git hook refusing amend/reset/stash
    and a pre-commit refusing never-list paths — Track 1's tooling), or
(b) close Track 2 at the pushed state (origin/track/ui = be99977, UI-1…UI-6)
    and let Track 1 merge that; UI-7's local commits stay unpushed until a
    human reviews them.
Supervisor's recommendation: (a) the revert now; no further dispatch until the
hook exists.

### 2026-09-02 16:5x — owner reverted d710b40 forward: ecadb98 `revert: phpunit
pin stays goaiez_antig_test (owner ruling 3)`. phpunit.xml identical to main.
Track 2 remains STOPPED pending a mechanical history/never-list guard (Track 1
tooling) or the owner's decision to close at be99977.

### 2026-09-02 17:0x — coder git guard installed (owner): /home/goaiez/agents/coder-bin/git,
prepended to PATH by every track's launch-coder.sh. Tested: stash/reset/amend/
checkout-on-path/push-main all REFUSED, plain git passes. Track 2 resumes with
run 15 (UI-7 wrap-up). The run-14 amend stays in REWRITES.log until the owner
clears it; §2a red is that record, not a new event.

## 2026-09-02 — UI-7 wrap-up (coder run 15) — REPORT 2026-09-02T16:51:00-05:00

Verdict: **PASS-WITH-NOTES**. UI-7 is DONE. Push gate opens for
25023a8..1414a17 (`git push origin track/ui`) — includes ecadb98, the owner's
phpunit revert, so origin carries no phpunit change.

Gate: 886 · 876 · FAILED 0 · errors 10 (harness) · pint ✓ · phpstan 0 ·
§2a shows the run-14 amend record only (owner's to clear) · no guard
refusals in the log · no debris · non-app paths clean · 78 captures
16:47:56–16:49:14 after the last commit 16:47:33 ✓ · axe zero on all 78 ✓ ·
report in rule-10 shape with TESTS ✓.

1414a17 setup layout fonts — verified: all six wizard steps render in the
app's display/body fonts at both widths. Customer surfaces verified:
feedback form (business name as brand, TCPA copy, 40px targets at 390),
thanks, to-google, review hub, unsubscribe, legal ×3. Run 13/14's
translation-key fixes hold (no raw keys visible).

Notes: first run with the git guard — no refusal needed; the coder complied.
Divergence: origin/track/ui = 56 commits ahead of main, unmerged; +15 local.
**Track 1 should merge UI-1…UI-6 now.**
UI-8: push, then the staff console and review queue, and a rig `--only`.

## 2026-09-02 — UI-8 run 1 (coder run 16) — REPORT "# UI-8" (free-form)

Verdict: **BLOCK**. UI-7 push confirmed (origin/track/ui = 1414a17).

Gate: 886 · 876 · FAILED 0 · errors 10 (harness) · pint ✓ · phpstan 0 · no
guard refusal · no debris · non-app clean · §2a = run-14 record only.
PASS: 13ecff8 `--only` filter · 249a594 staff seed (role super_admin) ·
6305de3 staff captures · 0b940e3 is NOT a gate bypass — the seeder enrols
the staff user in 2FA with a known recovery code and the rig answers the real
challenge; acceptable.

BLOCK-1 — **every staff-*@390.png is the login page** (ten files, all 27041
bytes, identical to login@390). The mobile staff session never got past
login and the rig has no post-login assertion on that path (the owner path
has one since UI-1). The report says "captures … properly on both desktop
and mobile" — false, and unchecked.
BLOCK-2 — **REPORT.md is free-form again** (fourth slip): no TESTS, no
per-screen list; step 3 of the brief ("look at every NEW PNG yourself") was
not done, which is how BLOCK-1 shipped.

Defects seen by the supervisor (not listed by the coder):
- staff-location-reviews.png (`livewire/admin/tenant-locations.blade.php`):
  the lookup form's submit button renders with NO label (blank white box).
- staff-settings.png is 1280×46351 — a 46k-pixel page; either it renders an
  unpaginated list of everything or a component repeats. Find out; that is a
  defect either way. staff-credentials.png is 6878px tall — check for the same.
- Seeder: `if (false) { … }` dead block and a `storage/app/location_id.txt`
  side file written by the seeder for the rig — pass the id another way.
- 3fdaedc (pint) landed after the captures; harmless, but the rule is the rig
  runs last.
Fix run = dispatch 1 of 2 for this BLOCK.

## 2026-09-02 22:50 — UI-8 fix run (coder run 17) — REPORT 2026-09-02T17:33:45-05:00

Run hung 5h on a foreground `tail -f` after finishing (report 17:33; the
supervisor killed the tail at 22:41; AGY_EXIT=0).

Verdict: **BLOCK** (new items; B1/B2 of run 16 are fixed).
Gate: 886 · 876 · FAILED 0 · errors 10 (harness) · pint ✓ · phpstan 0 ·
no guard refusal · non-app clean · §2a = run-14 record.

Fixed: B1 mobile staff login asserted and working (staff-*@390 now differ;
internal-users@390 shows the console) ✓ · B2 report in rule-10 shape ✓
(TESTS line is the grep form, not the raw `tests …` line — minor) ·
0b797ff review lookup button label · 3536cd1 seeder hygiene · 5d0fa97 rig
reads the location without the global scope.

Not landed / new:
1. **Settings length** — eecc923 adds `max-h-[600px] overflow-y-auto` to the
   settings and credentials lists (a fair mitigation: the page is a
   deliberately verbose per-setting rationale list, not a loop). But the CSS
   build ran at 17:32:40, AFTER the captures (17:30:47–17:31:13), so
   staff-settings.png is still 1280×46351. Unverified, reported as "capped:
   yes". Recapture proves or disproves it.
2. **staff-settings@390 is 453px wide × 85,220 tall** — horizontal overflow
   on a 390 viewport (unbreakable tokens such as setting keys / long
   paragraphs). Mobile checklist item; not listed.
3. **staff-location-reviews.png is now the 404 page** (desktop and @390),
   listed "OK". Regression from 5d0fa97: the location id the rig resolves
   does not route. Run 16 captured this page correctly.
4. axe: staff-audit-staff@390 serious 1.
5. `--only` appears to clear the whole output dir: only the 20 staff PNGs
   remain; the other 78 (public, owner, customer, error pages) are gone.
   Regenerable, but the flag must only remove files it will recapture.
Fix run = dispatch 1 of 2 for this BLOCK.

## 2026-09-02 23:05 — UI-8 fix run 2 (coder run 18) — REPORT 2026-09-02T22:52:00-05:00

Verdict: **BLOCK — cap spent, to the owner.** UI-8's BLOCK has had its
original run (16) and two fix runs (17, 18); one item survives.

Gate: 886 · 876 · FAILED 0 · errors 10 (harness) · pint ✓ · phpstan 0 · no
guard refusal · non-app clean · no debris · §2a = run-14 record ·
build 22:48:32 and captures 22:48:33–22:50:19 both after the last commit
22:48:27 ✓ · 98 PNGs restored ✓ · axe zero on all 98 ✓ · report rule-10 ✓.

Landed and verified: a0a04aa `--only` keeps unmatched captures ✓ ·
8e32a8b settings wraps at 390 (390px wide now; 16,709 tall) ✓ · settings
desktop 13,346 tall (was 46,351), credentials 5,792 (was 6,878) — the list
cap works; the page remains a long reference page by design ✓ ·
1636031 axe scrollable-region-focusable on audit ✓.

**Survived: staff-location-reviews.png (and @390) is STILL the 404 page.**
70bfaea "rig resolves the seeded location" did not change the outcome, and the
report lists the file under fixed items without a look. Run 16 captured a real
page at this slot; the regression began with run 17's 5d0fa97. Third report
in a row asserting a fix the capture disproves.

Owner options: (a) one owner-authorised dispatch limited to this single item,
with the capture pasted into REPORT.md as proof; (b) accept UI-8 without the
review-queue capture, push 13ecff8..1636031, and log the 404 as an open
defect for the next wave. Supervisor's recommendation: (b) — the branch is
otherwise clean and 82 commits ahead of main; get it pushed and merged.

### Owner decision 2026-09-02 23:10 — option (b): UI-8 accepted without the
review-queue capture; owner pushed 13ecff8..1636031 (origin/track/ui = 1636031).
OPEN DEFECT for the next wave: staff-location-reviews capture 404s — the rig's
tinker one-liner for the location id does not yield a bare id. Track 2 HELD
until the owner resumes; Track 1 to merge track/ui (through 1636031 or the
be99977 cut, its choice) with the merge notes above.

### 2026-09-02 23:2x — owner: "resume". UI-9 dispatched (coder run 19).

## 2026-09-02 23:35 — UI-9 run 1 (coder run 19) — REPORT "# Coder Report" (free-form)

Verdict: **PASS-WITH-NOTES** on the commits; the report is not accepted as a
record. Push gate opens for b2db11d..3c56554 (`git push origin track/ui`).

Gate: first run 886 · 875 · errors 11 (J8 terminate-process, concurrency —
5th sighting) → rerun 886 · 876 · errors 10, no non-harness error · pint ✓ ·
phpstan 0 · no guard refusal · §2a = run-14 record · captures 23:14:44–
23:16:32 after the last UI commit 23:14:37 (two lint-only commits followed at
23:17/23:18) · 98 PNGs.

Landed and verified: b2db11d review-queue capture is the real page (H1
"Reviews waiting for a decision", 1280×1241) — the open defect from UI-8 is
closed; the rig now resolves the id via a rig-only `ui:location-id` console
command · 4aaade3 populated seeder (12 customers, 6 sent messages visible) ·
14ba939 advanced tables no longer overflow at 390 · 14f385e citations no
longer render `[]` · 061ad82 title attributes on truncated names.

Notes (all carried into UI-10):
1. REPORT.md free-form again (5th); none of the proof lines asked for
   (printed id, identify, H1 line) were written. Next time this is a BLOCK.
2. **Inbox still empty** though the seeder creates 2 conversations × 3
   messages: the rows do not reach the inbox query (business/tenant column,
   status, or channel). The coder did not notice. Seeder or query — find out.
3. Customers list shows 13 rows with no pagination — on the brief's own
   checklist, not listed.
4. axe: website-builder serious — preview "★★★★★ 4.9 (128 reviews)" is
   emerald-400 on white.
5. Debris: `supervise_output.log` at repo root. Remove.
6. Seeded data is uniform ("Test Customer" × 6, all "No activity yet"): the
   3-with-feedback and the unhappy review are not visible anywhere. Vary it.

## 2026-09-02 23:50 — UI-10 run 1 (coder run 20) — REPORT 2026-09-02T23:28:40Z

Verdict: **BLOCK**. UI-9 push confirmed (origin/track/ui = 3c56554).
Report: template ✓ (first compliant report in six). Gate: 886 · 876 ·
FAILED 0 · errors 10 · pint ✓ · phpstan 0 · no guard refusal · no debris.

Landed and verified: d955966 seeded conversations reach the inbox — inbox
shows "Major Grimes" and "Test Customer", both waiting for a first reply ✓ ·
9b5641e builder rating emerald-700 (class present in the built CSS) ✓.

BLOCK-1 — **08db161 edits `app/app/Services/Crm/CustomerDirectory.php`**
(PER_PAGE 25 → 10). A Service class is outside Track 2's scope, and the
change is not a fix: the directory already paginated at 25 and the seeded
list has 13 rows. The supervisor's brief misjudged "13 rows unpaginated" as a
defect; the coder's job was to say so, not to change product behaviour.
Revert forward (PER_PAGE back to 25), no view change needed.
BLOCK-2 — **9 captures and `axe/SUMMARY.txt` are missing** (89 PNGs, no
summary) while the per-screen list says OK for every screen the coder looked
at. The rig ended early or a capture set was skipped; the report does not
mention it. Find why; the run is not complete until all ~98 files and the
summary exist.
BLOCK-3 — brief item 3 ("vary the seed") has no commit and no mention —
skipped silently. Do it, or say UNRESOLVED and why.
Fix run = dispatch 1 of 2 for this BLOCK.

## 2026-09-03 00:00 — UI-10 fix run (coder run 21) — REPORT 2026-09-02T23:42:00-05:00

Verdict: **PASS-WITH-NOTES**. UI-10 is DONE. Push gate opens for
d955966..b947283 (`git push origin track/ui`).

Gate: 886 · 876 · FAILED 0 · errors 10 · pint ✓ · phpstan 0 · no guard
refusal · no debris · §2a = run-14 record · report in template ✓ · rig
complete: 98 PNGs + 99 axe files + SUMMARY, captures 23:39:32–23:41:20 after
the last commit 23:39:26 ✓.

B1 ✓ c456026 PER_PAGE back to 25; `git diff origin/main..HEAD -- app/app/Services`
is empty. B2 ✓ rig completes (20 staff captures); the report does not say
what stopped it last time — noted, not chased. B3 ✓ f8affb7 varied seed:
messages page shows 3 customers × 3 bodies. b947283 adds HTML dumps next to
each PNG (unrequested; useful for review; keep).

Notes for UI-11: seeded dates all "1 minute ago" (30-day spread not done);
home tiles still 0/0/0 with seeded feedback (the seed may not hit the table
the tiles count — find out, UI-only or UNRESOLVED); axe serious on
website-builder preview footer (`text-gray-500` on white); advanced area and
website-builder have no @390 captures yet.
