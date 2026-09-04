# BRIEF — Track reviews (`track/reviews`) — REV-11, **merge `origin/main` `cd5a2f7`, untrack the mailbox, `reviewInvitesFor` real**

updated: 2026-09-04T16:22Z
push: ⛔ **CLOSED for this run.** Commit; do not push. The push gate opens in
REV-12 after the supervisor's PASS on your REPORT.

`OWNER.md` (12:17Z) still stands: go, build automatically. This is the second
route-A merge; `main` moved 25 commits past the one you merged in run 11.

## What changed on `main` (verified from the tree, 16:0x–16:15Z)

- `origin/main` = `cd5a2f7`. `HEAD` = `f9f349f`, **25 behind, 17 ahead**.
- `ad95b42` **reverted `JourneyHarness.php` and `TwelveJourneysTest.php`** to
  before `0583871` — the helpers that run 11 inherited hand-wrote rows and were
  judged fake. On `main` J10's `personWithPendingSteps`, `reviewInvitesFor`,
  `completeJob` throw `todo()` again. `tenantWithLiveNumber` is real.
- `7c0da08` **untracked the supervisor mailbox** and `.gitignore` now ignores
  `.agents/supervisor/*` except `launch-coder.sh` (owner ruling 2026-09-04).
- Four commits built **G20-07/11/12/13 inside C-Reviews**: `ReviewRequestAction`
  gained `?int $csatScore, ?int $jobAgeDays`, a `LOW_CSAT_TRIAGE` refusal that
  opens a `QaTicketAction` ticket, and a `CSms\Events\SendRequested` dispatch
  (marketing class) after a request is created; two migrations
  (`add_sla_hours_to_qa_settings`, `add_csat_score_to_review_requests`);
  `CReviewsTest.php` gained `test_g20_11_and_g20_12_triage_mechanism_and_gating`.
  `main` still registers its own `RequestReviewOnJobCompleted`.

## The wave — in this order

### 1. Fetch and measure (no commit)

```
git fetch --no-write-fetch-head --prune origin
git rev-parse --short origin/main
git rev-list --left-right --count origin/main...HEAD
grep -c "public function test" app/tests/Modules/C-Reviews/CReviewsTest.php
```
Expected `cd5a2f7`, `25 17`, `21`. If `main` moved again, proceed and say so.

### 2. Commit the supervisor's notes so the merge can start (commit)

The one authorised touch. You write nothing into them:

```
git commit -m "chore(supervisor): track/reviews notes as of REV-11" -- .agents/supervisor/BRIEF.md .agents/supervisor/KICKOFF.md .agents/supervisor/REPORT.md .agents/supervisor/REVIEWS.md .agents/supervisor/REWRITES.log .agents/supervisor/OWNER.md .agents/supervisor/TICK-ADDENDUM.md
```
⛔ Not `app/phpunit.xml` (the owner's pin — leave it modified and uncommitted),
not the `tick*`/`rev*` files, not `-a`, not `add -A`.

### 3. Merge `origin/main` (commit — the merge commit)

```
git merge --no-ff origin/main
```
Nine conflicts expected (`git merge-tree`, 16:15Z). Resolve each, `git add`
(or `git rm`) each, then `git commit` with no `-a`:

| path | resolution |
| :--- | :--- |
| `.agents/supervisor/{BRIEF,KICKOFF,REPORT,REVIEWS}.md`, `.agents/supervisor/REWRITES.log` — *modify/delete* | **untrack, keep on disk:** `git rm --cached -- <path>` for each. Also `git rm --cached -- .agents/supervisor/OWNER.md .agents/supervisor/TICK-ADDENDUM.md` so nothing but `launch-coder.sh` stays tracked, matching `main`'s `.gitignore`. Verify every file is still on disk afterwards: `ls .agents/supervisor/*.md`. |
| `C-Reviews/Actions/ReviewRequestAction.php` — *content* | **both.** Ours: `CUSTOMER_UNKNOWN`, `TWO_PASS_CAP_REACHED`, `CADENCE_WINDOW_ACTIVE` refusals and the `'google'` platform default. Theirs: the two new params, `LOW_CSAT_TRIAGE`, the `SendRequested` dispatch. Order: null-customer refusal first, then the CSAT triage, then cap, then cadence, then create + dispatch. Every refusal code from both sides survives. |
| `C-Reviews/Listeners/RequestReviewOnJobCompleted.php` — *modify/delete* | **delete:** `git rm -- <path>`. Ours (`AskForReviewOnJobCompleted`) is the one listener (R245, journal `07:25:37`). |
| `C-Reviews/ModuleServiceProvider.php` — *content* | **ours** — registers `AskForReviewOnJobCompleted` only. Keep `main`'s other `boot()` additions if any outside the `listen` block. |
| `tests/Modules/C-Reviews/CReviewsTest.php` — *content* | **both, by name.** Union of test names is **22** (`main`'s `test_g20_11_and_g20_12_triage_mechanism_and_gating` plus our 21). No name from either side is dropped. |

`Ui/ReviewsQaRequests.php`, the blade, `QaTicketAction.php`, `manifest.php` and
the two migrations auto-merge to `main`'s. `.agents/state/*` auto-merges;
confirm with `python3 -m json.tool .agents/state/BUILD-STATE.json > /dev/null`.

After the commit:
```
grep -rn "RequestReviewOnJobCompleted" app/            # nothing
grep -c "public function test" app/tests/Modules/C-Reviews/CReviewsTest.php   # 22
git ls-files .agents/supervisor/                        # launch-coder.sh only
```

### 4. `reviewInvitesFor` — real, under ruling 1 (commit)

In `app/tests/Journeys/JourneyHarness.php`, replace **only** the body of
`reviewInvitesFor(array $person): array` — J10's own `todo()`. It returns every
`review_requests` row for that person, as arrays, oldest first:

```php
return \App\Modules\CReviews\Models\ReviewRequest::query()
    ->where('customer_id', $person['id'])
    ->orderBy('id')
    ->get()
    ->map(fn ($r) => $r->toArray())
    ->all();
```
Adjust the model namespace to what `app/app/Modules/C-Reviews/Models/` actually
declares. ⛔ **Do not touch `completeJob`, `personWithPendingSteps`, or any other
line of that file.** `git diff --stat` on it must show one hunk.

```
git commit -m "test(J10): reviewInvitesFor reads review_requests for the person (ruling 1)" -- app/tests/Journeys/JourneyHarness.php
```

### 5. Migrate both databases (no commit)

```
php artisan migrate --force
DB_DATABASE=goaiez_antig_reviews_test php artisan migrate --force
```
`main` added two C-Reviews migrations. Never `goaiez_antig`, `goaiez_antig_dev`,
`goaiez_antig_test`; never `migrate:fresh`.

### 6. Record (commit: `.agents/state/**` only)

```
python3 bin/state.py unresolved X-121 C-Reviews "completeJob: X-121 exposes no create path for work_orders (EntityWriteAction updates by id; X-171 actions create nothing but device-sync rows). A raw insert is what ad95b42 reverted. J10's completeJob stays todo() until X-121 exposes one (ruling 8, option b)."
python3 bin/state.py note C-Reviews "Second route-A merge: origin/main cd5a2f7. Mailbox untracked per main's .gitignore (7c0da08). G20-07/11/12/13 from main kept; one JobCompleted listener kept (ours). reviewInvitesFor implemented under ruling 1. REV-9's J10 green was on 0583871's harness, reverted by ad95b42; does not stand."
git commit -m "chore(state): completeJob UNRESOLVED on X-121 create path; second main merge recorded" -- .agents/state/JOURNAL.md .agents/state/BUILD-STATE.json
```

### 7. Housekeeping (no commit)

Delete the untracked scratch at the repo root: `BUILD-STATE.ours.json`,
`fix_build_state.py`, `generate_report.sh`, `grants_reviews.out`,
`grants_reviews_test.out`. Then, from `app/`:
`./vendor/bin/pint tests/Modules/C-Reviews/CReviewsTest.php` and commit it alone:
`git commit -m "style(C-Reviews): pint CReviewsTest" -- app/tests/Modules/C-Reviews/CReviewsTest.php`.
Pint nothing else — the other pint/phpstan reds are `main`'s (ruling 5).

### 8. The gate

```
bash bin/supervise.sh --tests
```
Paste the raw §7 line and the full failure/error list into REPORT `RAW`.
**Expected: J10 is in the error list, throwing at `personWithPendingSteps`
(sixty's, ruling 6).** That is the honest state; do not make it green. If
anything in `C-Reviews` is red, fix there only, one commit, re-run, report both.

### 9. REPORT.md — rule 10 shape

Carry: step 1's four lines; the merge sha and the resolution actually taken per
file; the three checks after step 3; the one-hunk `git diff --stat` for step 4;
migrate tails; the journal lines; the raw §7 line and list; the doctor stamp;
`git log --oneline origin/main..HEAD | wc -l`. `UNRESOLVED`: the review-platform
grant; `completeJob` on X-121's create path; `personWithPendingSteps` on
track/sixty. `REFUSED`: anything above you would not do, with the rule.

## Never, this run

Push · rebase · amend · `--force` · `add -A` / `-a` · stash · reset ·
`app/phpunit.xml` (leave it modified, uncommitted) · any harness line but
`reviewInvitesFor`'s body · `TwelveJourneysTest.php` · `app/app/Doctor/**` ·
seals · `state.py done|journey|stage` · any database not in step 5 ·
`migrate:fresh`.
