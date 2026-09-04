# BRIEF — Track reviews (`track/reviews`) — REV-9, **route A: merge `origin/main`, one listener, J10 on the gate**

updated: 2026-09-04T12:20Z
push: ⛔ **CLOSED for this run.** Commit; do not push. The push gate opens in
REV-10 after the supervisor's PASS on your REPORT.

Owner's go: `OWNER.md` (12:17Z). The HOLD is over. Read `OWNER.md` before this.

## What changed while we were parked (verified from the tree, not from notes)

- `origin/main` = `79a8b43` (2026-09-04 06:02). `HEAD` = `e340d98`, **76 behind,
  13 ahead**.
- `work_orders.person_id` (`ac89b6a`) and `JobCompleted.personId` (`b9701c4`)
  are on `main` and already ancestors of `HEAD`. `JobStateAction` reads the
  column onto the event. **The X-121 `UNRESOLVED` is stale.**
- `origin/main`'s `JourneyHarness` implements **every** method J10 calls
  (`0583871`). After the merge, J10 reaches our code.
- `0583871` also dropped a second `JobCompleted` listener into **our** module:
  `C-Reviews/Listeners/RequestReviewOnJobCompleted.php`, registered in
  `ModuleServiceProvider::boot()` with a hand-rolled 30-day query. Ours,
  `AskForReviewOnJobCompleted`, delegates cadence to `ReviewRequestAction`
  (tested in `c98a078`). **Ours survives; main's goes.** Ruling 5.

## The wave — in this order, one commit per numbered step where noted

### 1. Fetch and measure (no commit)

```
git fetch --no-write-fetch-head --prune origin
git rev-parse --short origin/main
git rev-list --left-right --count origin/main...HEAD
grep -c "public function test" app/tests/Modules/C-Reviews/CReviewsTest.php
```
Paste all four outputs in REPORT `RAW`. Expected `79a8b43`, `76 13`, `20`. If
`origin/main` has moved past `79a8b43`, proceed anyway and say so — the seam
below was measured against `79a8b43`, so re-check each conflict rather than
assume it.

### 2. Commit the supervisor's working notes — the ONE authorised touch (commit)

The merge cannot start over dirty tracked files, and `main` also carries these
paths. You write **nothing** into them; you commit them as they are:

```
git add .agents/supervisor/launch-coder.sh .agents/supervisor/OWNER.md .agents/supervisor/TICK-ADDENDUM.md
git commit -m "chore(supervisor): track/reviews notes as of REV-9" -- .agents/supervisor/BRIEF.md .agents/supervisor/KICKOFF.md .agents/supervisor/REPORT.md .agents/supervisor/REVIEWS.md .agents/supervisor/launch-coder.sh .agents/supervisor/OWNER.md .agents/supervisor/TICK-ADDENDUM.md CLAUDE.md bin/supervise.sh .claude/settings.json
```
⛔ Do not `git add` the `tick*-gate.txt` / `rev*-gate.txt` / `*.out` /
`*-block.md` files. Do not `-a`, do not `add -A`, do not stash, reset or
checkout anything.

### 3. Merge `origin/main` (commit — the merge commit)

```
git merge --no-ff origin/main
```
Ten conflicts are expected (`git merge-tree` dry run, 12:16Z). Resolve **each**
exactly so, `git add <path>` after each, then `git commit` with no `-a` (a merge
commit takes no pathspec; the staged resolutions are the commit):

| path | resolution |
| :--- | :--- |
| `.agents/supervisor/{BRIEF,KICKOFF,REPORT,REVIEWS}.md`, `.agents/supervisor/launch-coder.sh`, `CLAUDE.md` | **ours** — `git checkout --ours -- <path>`. This track's supervisor files stay this track's. |
| `.agents/state/JOURNAL.md` | **both sides, every line, in timestamp order**, markers removed. No line edited, none dropped. |
| `.agents/state/BUILD-STATE.json` | Start from **theirs** (`main`'s), then carry in this track's own entries so nothing of ours is lost: `C-Reviews` and `X-181` status/decisions/notes, and our three `unresolved` lines. Validate: `python3 -m json.tool .agents/state/BUILD-STATE.json > /dev/null` and `python3 bin/state.py status` shows C-Reviews `DONE`, our UNRESOLVED lines present, and `main`'s stage counts. This is the one place a merge may touch the file by hand; say so in REPORT. |
| `app/app/Modules/C-Reviews/ModuleServiceProvider.php` | **ours** — keep the `AskForReviewOnJobCompleted` registration; drop `main`'s `Event::listen(... RequestReviewOnJobCompleted::class)` block and its `use`. Then `git rm app/app/Modules/C-Reviews/Listeners/RequestReviewOnJobCompleted.php`. |
| `app/tests/Modules/C-Reviews/CReviewsTest.php` | **both** — our 20 plus `main`'s `test_no_fake_rows_written_on_mount`. Count after: **21**. |

`app/app/Modules/C-Reviews/Ui/ReviewsQaRequests.php` auto-merges to `main`'s
version (no seeded fake rows on mount). Leave it.

After the commit: `grep -rn "RequestReviewOnJobCompleted" app/` must print
**nothing**, and `grep -c "public function test" app/tests/Modules/C-Reviews/CReviewsTest.php`
must print **21**.

### 4. Migrate both of this track's databases (no commit)

```
php artisan migrate --force
DB_DATABASE=goaiez_antig_reviews_test php artisan migrate --force
```
`.env` is `goaiez_antig_reviews`. **Never** `goaiez_antig`, `goaiez_antig_dev`,
`goaiez_antig_test`. Never `migrate:fresh`. Never edit `phpunit.xml`.

### 5. Record the decisions (commit: `.agents/state/**` only)

```
python3 bin/state.py decided C-Reviews "One JobCompleted listener: AskForReviewOnJobCompleted; cadence lives in ReviewRequestAction. main's RequestReviewOnJobCompleted (0583871) removed on merge. Owner go 2026-09-04T12:17Z, OWNER.md."
python3 bin/state.py note C-Reviews "X-121 UNRESOLVED (2026-09-02T17:06) closed: work_orders.person_id ac89b6a and JobCompleted.personId b9701c4 are on main; JobStateAction reads the column onto the event."
python3 bin/state.py note C-Reviews "J10 sixty-dependency closed: origin/main 0583871 implements tenantWithLiveNumber, personWithPendingSteps, completeJob, reviewInvitesFor. Review-platform access still ungranted: real send stays UNRESOLVED (ruling 13)."
git commit -m "chore(state): C-Reviews one-listener decision; X-121 and sixty dependencies closed" -- .agents/state/JOURNAL.md .agents/state/BUILD-STATE.json
```
If `state.py` has a verb that closes an `unresolved` entry, use it in place of
the first `note` and say which. If it does not, the note is the record; do not
hand-edit the list.

### 6. The gate

```
bash bin/supervise.sh --tests
```
Paste the raw `§7` line into REPORT `RAW`, and state explicitly whether
`a_completed_job_asks_for_a_review_once_inside_the_cadence` appears in the
failure/error list. Expected: **it does not appear** — J10 green on the gate.

- If J10 fails **inside `app/app/Modules/C-Reviews/**`**: fix there, one commit,
  re-run the gate, report both runs. That is the only fix in scope.
- If J10 fails in `JourneyHarness.php`, `TwelveJourneysTest.php`, another
  module, or the DB: **stop**, `python3 bin/state.py unresolved J10 C-Reviews "<the raw line, and the path it names>"`,
  commit that, report. ⛔ Ruling 1: not one line of the harness is ours to
  edit now — every method J10 calls is implemented.

### 7. REPORT.md — rule 10 shape

Must carry: the four measurements from step 1; the merge commit sha and the
resolution actually taken per file; the two grep results after step 3; the
migrate output tails; the `state.py` lines as journaled; the raw `§7` line;
the doctor build stamp; `git log --oneline origin/main..HEAD | wc -l`.
`UNRESOLVED:` lists the vendor grant only (plus anything step 6 added).
`REFUSED:` anything above you would not do, with the rule.

## Never, this run

Push · rebase · amend · `--force` · `git add -A` / `-a` · stash · reset ·
checkout of any supervisor file except the `--ours` resolutions in step 3 ·
`JourneyHarness.php` · `TwelveJourneysTest.php` · `app/app/Doctor/**` ·
`seals.json` · `phpunit.xml` · any `DB_` line · `state.py done|journey|stage` ·
any database not named in step 4 · `migrate:fresh` on anything.
