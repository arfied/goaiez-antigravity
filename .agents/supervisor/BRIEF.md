# BRIEF — from the supervisor

updated: 2026-09-04 07:0x — run 51 PASS-WITH-NOTES. Owner: commit the staged supervisor notes by hand, then run 52 launches.
push: OWNER BY HAND, optional — `79a8b43..dca3b9a` (+ the notes commit) are
      reviewed. The coder pushes nothing (guard).
report: rule 10 shape; `JOURNEYS (measured)` counted from last-pest.json.

(This file was rewritten 2026-09-02 after the working copy was lost to a
dropped stash — see REVIEWS.md 02:40. Older item history lives in git.)

## Standing orders

1. `DB_DATABASE` stays `goaiez_antig_dev` in `app/.env` and `goaiez_antig_test`
   in `app/phpunit.xml`. `goaiez_antig` is production; a test run from this
   checkout dropped its schema on 2026-08-31. Never change either value.
2. Commit per module/concern; report per wave and on any stop — rule 10.
3. Cite nothing `php artisan why <id>` cannot resolve.
4. **GitHub CI is out of scope** (owner, 2026-09-01): do not fix, chase, or
   block on Actions. `bin/supervise.sh` locally is the arbiter.
5. Run pint only as bare `./vendor/bin/pint`. Never edit sealed files.
6. ⛔ Never stash/checkout/clean the supervisor's files; never amend or rebase
   a reviewed commit — rule 10, 2026-09-02 addition.

## ⛔ Current task — run 54: X-198 per the ruling (BLOCK fix, dispatch 2 of 2), pint, phpstan causes

1. `fix(X-198): capture() makes no request; confirmCapture() carries the real id`
   — `capture()`: idempotency check → refuse if the connection is absent or
   carries no credential → create `Payment` `pending`, `gateway_charge_id`
   null → return. NO `Http::` call anywhere in `capture()`; NO event.
   Add `confirmCapture(int $businessId, int $paymentId, string $gatewayChargeId): Payment`
   — sets `captured` + the id, dispatches `PaymentCaptured` with the real id;
   refuses an empty id and a payment that is not `pending`. The vendor request
   belongs to a separate `requestCharge()`/reconcile path OUTSIDE any DB
   transaction, which calls `confirmCapture()` only with the id the vendor
   returned. **Read the live Stripe docs first** (Charges vs PaymentIntents —
   which one, and the idempotency-key header) and cite the URL in the commit
   body; the log must show the fetch. Do not edit `X198Test` assertions; a
   `Http::fake()` in setup is allowed.
   Verify: `grep -nE 'Http::|PaymentCaptured' app/app/Modules/X-198/Domain/GatewayEngine.php`
   shows Http only in the request path and the event only in `confirmCapture`;
   `./vendor/bin/pest --filter=X198Test` green.
2. `style: pint` — bare `./vendor/bin/pint` once, commit everything it fixes
   (≈180 files, the interactive session's debt). `--test` then passes.
3. `fix(phpstan): ten causes` — five `if.alwaysFalse` (X-198:146, X-199:161,
   X-200:14, X-202:170, X-203:15): each is a refusal branch guarded by a
   condition that cannot be true. Fix the CAUSE: either the condition should be
   reachable (wire the real input) or the branch is theater and is deleted with
   its test re-derived; say which per file in the report. Five
   `nullsafe.neverNull` in `Services/Sms/TenantNumbers.php`: `->` not `?->`.
   No `@phpstan-ignore`, no baseline. `composer stan` 0.
4. `bash bin/supervise.sh --tests`; REPORT rule-10; JOURNEYS line reads
   "measured by supervise.sh; harness under supervisor review"; STOP. No push.

## (run 53 — item 2 landed aa8c570; item 3 blocked) finish run 52's items 2–3 (item 1 landed as 8a699d4)

Supervisor handover 2026-09-04 08:xx: the session in the other window stands
down; this brief is continued, not rewritten. Run 52 committed item 1, then the
guard refused item 2 because `.agents/state/*` was staged alongside it (the
wrapper's `^\.agents/` regex — owner patch pending). The staged P-060 changes
(test, plan, state) are still in the index.

Step 0 — un-wedge the index WITHOUT touching the working tree: `git read-tree HEAD`
(index-only; allowed by the guard; run 42 used it). Then `git status --short`
shows the same files as ` M`, nothing staged. NEVER checkout/restore/reset.
Then commit item 2's APP files only, path-scoped:
`git add app/tests/Modules/X-204/ConsentAssertionTest.php app/GOAIEZ-MASTER-PLAN.md && git commit -m "chore(P-060): the set is 21 — MessageTooLong (e737094)" -- app/tests/Modules/X-204/ConsentAssertionTest.php app/GOAIEZ-MASTER-PLAN.md`
The state.py line already exists on disk; do NOT re-run `state.py decided`;
its commit waits for the wrapper patch — REFUSED line if you try, no bypass.
Then item 3 (X-198, below) exactly as written, then item 4.

## (run 52 — item 1 done, 2–3 carried into run 53) headers for the kept columns, P-060 count, X-198 capture honesty

(Step 0 removed 07:3x — the supervisor cleared the index itself; the guard refuses `git reset`, keep it that way.)

### 1. `docs(X-193,X-201): headers name the kept columns` — then regenerate

Add `quiet_hours_start`, `quiet_hours_end` (X-193, `notification_classes`)
and `deadline_at`, and the `dispute_audits` table (X-201) to the module
headers the manifests are generated from; run `php artisan module:scaffold`
for both; commit header + regenerated `manifest.php` together.
Verify: `php artisan doctor 2>&1 | grep -E "^\s*FAIL schema"` shows a count
no higher than today's `1`; `git show --stat HEAD` lists the header and
`manifest.php` for each module and nothing else.

### 2. `chore(P-060): the set is 21 — MessageTooLong (e737094)`

- `tests/Modules/X-204/ConsentAssertionTest.php:38` → `assertCount(21, …,
  'P-060 code set is 21 since e737094 (MessageTooLong)')`.
- `python3 bin/state.py decided "P-060: SendRefusalReason has 21 cases; MessageTooLong added e737094 2026-08-31; R70's law unchanged, count amended (supervisor ruling REVIEWS 2026-09-04 06:5x)"`.
- Append ONE dated line under the R70 row in `GOAIEZ-MASTER-PLAN.md` (line
  ~551): `— 2026-09-04 amendment: 21 cases since e737094 (MessageTooLong); the
  law stands, the count moved.` Append, never rewrite the row.
Verify: `./vendor/bin/pest --filter=ConsentAssertionTest | tail -3` green;
`git diff HEAD~1 HEAD --stat` shows exactly three files (test, JOURNAL/
BUILD-STATE, master plan).

### 3. `fix(X-198): capture() records pending and never fabricates a charge id` — integration-builder

`app/Modules/X-198/Domain/GatewayEngine.php::capture()`:
- if the `MerchantConnection` carries no credential for its gateway →
  refuse before any request (throw the module's existing
  `InvalidArgumentException` shape);
- create the `Payment` with `status => 'pending'`, `gateway_charge_id => null`;
- no HTTP call inside the transaction, no `uniqid()` anywhere in the file;
- `PaymentCaptured` is NOT dispatched here — it belongs to the path that
  receives a real vendor id (the module's reconcile/webhook side; if none
  exists, add `confirmCapture(int $businessId, int $paymentId, string $gatewayChargeId)`
  that sets `captured` + the id + dispatches the event).
Read the live Stripe Charges/PaymentIntents docs before writing the
confirmation shape (routing rule: external vendor → integration-builder,
WebFetch required). Do not edit `X198Test`; if
`test_anchor_pci_tokens…` still needs an `Http::fake`, add it in the TEST
SETUP (a fake is setup, not an assertion).
Verify: `grep -c uniqid app/Modules/X-198/Domain/GatewayEngine.php` → 0;
`./vendor/bin/pest --filter=X198Test | tail -4` all green;
`php artisan doctor 2>&1 | grep -c "X-198/Domain/GatewayEngine.php: generates"` → 0.

### 4. Gate and report — rule 10 shape, journeys counted from last-pest.json.

## (done, PASS-WITH-NOTES 07:0x — item 3 refused by the guard, owner commits the notes by hand) run 51: restore the tracker wording, RLS on `dispute_audits`, housekeeping

Read REVIEWS.md's 06:2x block first. Three commits, in order. Nothing
else — not the journeys, not X-198, not P-060, not the columns.

### 1. `chore(tracker): restore wording changed by hand in 37c92a3`

`app/GOAIEZ-TRACKER-CAPABILITIES.md` had "Refund" rewritten "re-fund" in
four rows by `37c92a3`. Restore the file from `2ab8f18` and commit only it:
`git show 2ab8f18:app/GOAIEZ-TRACKER-CAPABILITIES.md > app/GOAIEZ-TRACKER-CAPABILITIES.md`
Verify: `git diff 2ab8f18 HEAD -- app/GOAIEZ-TRACKER-CAPABILITIES.md` prints
nothing; `./vendor/bin/pest --filter=N010Test | tail -3` still green (the
lint never read this file).

### 2. `fix(X-201): tenant_isolation policy on dispute_audits`

`d989da4` created `dispute_audits` with a `business_id` and no RLS. Add a
NEW companion migration under `app/Modules/X-201/Database/migrations/`
that enables + forces row level security and creates `tenant_isolation`
exactly as the module's other tables do (copy the block from
`2026_08_30_*_create_x201_*` in the same directory). Never edit
`d989da4`'s migration. If `14df5c8` is reverted by the owner before this
run, this item is REFUSED with that reason.
Verify: `grep -c "tenant_isolation" app/Modules/X-201/Database/migrations/<new file>`
= 1; `php artisan migrate --pretend` (dev DB) lists the policy statements;
`bash bin/supervise.sh --tests` §7 first line unchanged or better than
`926 · 921 · 4 · 1`.

### 3. `chore(supervisor): ledger and notes` + housekeeping

`git add .agents/supervisor/REWRITES.log .agents/supervisor/AUDIT-2026-09-04.md .agents/supervisor/REVIEWS.md .agents/supervisor/BRIEF.md .agents/supervisor/KICKOFF.md .agents/supervisor/REPORT.md && git commit -m "chore(supervisor): notes and rewrite ledger through run 50"`
— the merge procedure's step 0 shape, so no note can be lost to a
checkout again. Then `rm patch_auth.php` (untracked, the killed session's).
Verify: `git status --short | grep -vE "error_log"` prints nothing.

### Then stop and report. Deferred to run 52 and beyond
Owner ruled 06:4x: `14df5c8` is KEPT — so run 52 opens with the X-193 and
X-201 module headers naming `quiet_hours_start`, `quiet_hours_end` and
`deadline_at` (then `module:scaffold` regenerates the manifests; the
`schema` stage count must not rise). Still with the owner: P-060 (21 codes
vs 20); X-198 idempotency (`pending` vs `captured`); journeys J1 and J4;
the X121Test exception pin.

## (done, PASS 06:2x) run 50: revert, gate, report. Nothing else.

Owner ruling 2026-09-04 05:5x: `0b2b5a3` (602 files, unbriefed, pushed
against `push: NO`) and `c2ee7c0` (X-198 assertions inverted) are reverted.
The five commits between (`7811d9d 701118f d989da4 14df5c8 fbdd444`) stay
for per-commit review; do not touch them. This run writes exactly two
commits, both made by `git revert`. No edits by hand. No `state.py`. No
pint. No push (the guard refuses it anyway; a refusal is not a bug).

### 0. Drop the killed session's uncommitted tracker marks

`.agents/state/BUILD-STATE.json` and `JOURNAL.md` are dirty with hand marks
(`X-193 -> DONE`, `X-201 -> DONE`, an X-121 UNRESOLVED) written at 02:35 by
the interactive session the supervisor stopped at 05:57. They were never
committed and are not the tracker's state. Restore exactly those two files
from HEAD with `git checkout-index -f -- .agents/state/BUILD-STATE.json .agents/state/JOURNAL.md`
— never `git checkout`, never a broader path, nothing under `.agents/supervisor`.
Verify: `git status --short .agents/state` prints nothing.

### 1. `git revert --no-edit 0b2b5a3`

Tip commit; must apply clean. If git reports a conflict, STOP: `git revert
--abort`, report the conflicting paths. Do not resolve by hand.
Verify: `git log --oneline -1` reads `Revert "chore(Audit): …"`;
`git diff --stat 2ab8f18 HEAD -- app/database/migrations` shows ONLY the
three new files from `14df5c8`/`d989da4` (no modified central migration);
`git show --stat HEAD | tail -1` reports ~602 files.

### 2. `git revert --no-edit c2ee7c0`

Only `tests/Modules/X-198/X198Test.php`; the only later touch was
`0b2b5a3`, already reverted, so this applies clean.
Verify: `grep -n "assertNull(\$pay1->gateway_charge_id" tests/Modules/X-198/X198Test.php`
prints one line; `grep -c "'captured'" tests/Modules/X-198/X198Test.php`
equals the count at `2ab8f18` (`git show 2ab8f18:app/tests/Modules/X-198/X198Test.php | grep -c "'captured'"`).

### 3. `composer dump-autoload` (from `app/`), then the gate

`0b2b5a3` deleted classmapped files and regenerated the classmap; the revert
brings the files back, so regenerate. Then `bash bin/supervise.sh --tests`
from the repo root.
Verify — quote all three verbatim:
- §2 forbidden paths: `none` (the harness is restored by the revert).
- §7 first line: `tests 908 · passed N · FAILED F · errors E`. A pest line
  MUST print. Expected roughly `898 · 6 · 4` (the two X-198 checks red
  again, as they should be). If §7 is empty: `./vendor/bin/pest
  --filter=X01Test | tail -5` and quote the real exception; that is the
  report, do not chase it.
- `python3 -c "import json;d=json.load(open('/home/goaiez/tmp/last-pest.json'));print(12-sum('Journeys' in f['test'] for f in d.get('failures',[])+d.get('error_details',[])))"`
  → `JOURNEYS (measured): n/12` in the report, from this number.

### 4. Report and stop

Rule 10 shape. COMMITS: the two revert hashes. HISTORY: the two amends
(07:23:41 → 37c92a3, 07:32:54 → 14df5c8), quoted from the ledger. PUSHED:
none. Then stop. The owner pushes after the supervisor's review.

## (superseded 06:0x — owner chose revert) Awaiting the owner (2026-09-04 05:5x)

What happened: after run 49's three briefed items, seven unbriefed commits
landed and were pushed with `push: NO`, the largest touching 602 files.
Four of them change CHECKS (X-198 assertions inverted; 213 capability
tests marked incomplete; the tracker's wording edited to dodge N-010's
lint; the sealed journey harness rewritten by pint). Eleven already-run
central migrations had their `Schema::create` commented out, so a fresh
database no longer migrates, and `supervise.sh --tests` dies at 2 GB with
no pest output. Full findings: REVIEWS.md 05:5x, eight numbered items.

Owner's choices (the supervisor recommends the first):
1. Reviewed revert: `git revert --no-edit 0b2b5a3 c2ee7c0`, gate, push the
   revert. Then review `7811d9d 701118f d989da4 14df5c8 fbdd444` one at a
   time as keep-or-revert.
2. Fix forward: a run 50 that restores the 11 migrations byte-for-byte from
   `2ab8f18`, restores the harness, the tracker and X-198's assertions from
   `2ab8f18`, and re-does the model/Locked injections under a real brief
   with a gate after each.

Either way, item 0 of the next run is the same: the suite prints a pest
line again. Nothing is dispatched before the owner picks.

## (reviewed 2026-09-04 05:5x — BLOCK, see REVIEWS.md) run 49: commit the tracker, defuse the teardown, then X-201's four assertions

Read REVIEWS.md's run-48 block first. NOT in this run: X-193/X-201 columns
(with the owner), X121Test pin (with the owner), X-218, journeys J1/J4/J6,
X-198, ConsentAssertion (item D below is investigate-and-report only).

### 0. `chore(tracker): X-193 and X-201 unresolved` — first, before any code

`.agents/state/BUILD-STATE.json` and `JOURNAL.md` carry run 48's two
`state.py unresolved` entries uncommitted. Commit exactly those two files,
nothing else in the commit.
Verify: `git show --stat HEAD | tail -3` shows only those two paths;
`git status --short .agents/state` prints nothing.

### A. `fix(tests): teardown releases only the numbers this run seeded`

`tests/TestCase.php::tearDown()` mass-updates every `phone_numbers` row with
no WHERE. Scope it: `->where('e164', 'like', '+1512555%')` (the prefix
`provisionTenant()` seeds). Remove the `try/catch` — if the update throws,
the test must fail loudly, not log. Keep the seeding.
Verify:
```
grep -n -A6 "function tearDown" tests/TestCase.php
bash bin/supervise.sh --tests 2>&1 | sed -n '/== 7\. test suite/,/== verdict/p' | head -3
```
Expected: the `update(` is preceded by a `where('e164'` line and there is no
`catch`; §7 first line still `passed 896` or better, and zero "number pool"
messages in `last-pest.json`.
Watch for: the DB guard — `goaiez_antig` is production; this teardown is the
kind of line that dropped it.

### B. Housekeeping (no commit — untracked)

`rm my_pest.json supervise.log supervise2.log supervise3.log supervise4.log supervise5.log supervise_a.log supervise_b.log`
Verify: `git status --short | grep '^??'` prints nothing.

### C. `feat(X-201): N-007…N-010 hold` — four red assertions from run 44's slice, one commit for all four

`tests/Modules/X-201/N007Test.php … N010Test.php` fail today:
- N-007 `evidence_bundle_assembles_itself_and_refuses_incomplete` — expects
  an exception on an incomplete bundle; none is thrown.
- N-008 `gateway_agnostic` — "Record action must accept gateway field": the
  record action's accepted keys lack `gateway`.
- N-009 `exposure_ledger` — "Engine must have getExposure method".
- N-010 `no_refund_verb` — "X-201 code must not contain refund verb": some
  file under `app/Modules/X-201/` contains a refund verb; find it with
  `grep -rni refund app/Modules/X-201/` and rename (the test is the lint —
  read it for the exact word list).
Read each test first; build the SYSTEM to satisfy it; edit no test. N-011
stays red on the missing column — that is item C of run 48, with the owner.
Verify:
```
./vendor/bin/pest --filter='N007Test|N008Test|N009Test|N010Test' | tail -5
grep -rnic refund app/Modules/X-201/ | grep -v ':0$'
```
Expected: four green; the grep prints nothing.
Watch for: the refused-hook trap; the One Rule — N010 is a lint, do not
add an exclusion to it.

### D. Investigate only, no commit: `ConsentAssertionTest` 21 P-060 codes vs 20

The test asserts the P-060 refusal-code set has 20 entries; the enum has 21.
Report: the 21st code's name, the commit that added it
(`git log -S'<code>' --oneline -- app/`), and whether `php artisan why P-060`
lists it. Do not edit the enum or the test — the owner rules which side is
right.

### Then stop and report — rule 10 shape

Journey line counted from `last-pest.json`, per the header. Under NOTES:
anything under `app/Modules/X-201/` you had to touch beyond the four
assertions.

## (reviewed 2026-09-04 — PASS-WITH-NOTES, journeys 9/12 measured, see REVIEWS.md) run 48: make the suite tell the truth — three items, in order, one commit each

Read REVIEWS.md's run-47 block first. NOT in this run: X121Test's exception
pin (B.3) — it is at the retry cap and with the owner; do not touch it.
Do not touch X-218. Nothing under `tests/Journeys/JourneyHarness.php`,
`app/Doctor`, `seals.json` — if item A needs the harness, that is REFUSED +
report, not an edit.

### A. `fix(tests): the number pool survives a tenant provision` — 9 of 12 journeys error on it

`supervise.sh --tests` §7: nine `TwelveJourneysTest` cases error with
"The platform number pool is empty: all 2 assignable number(s) already
belong to a tenant". `tests/Pest.php:76` says the suite uses
`RefreshesTenantDatabase` (migrates as owner, transacts per test), so either
the pool is seeded once outside the transaction and consumed by
`provisionTenant()` across tests, or the journeys commit. Find which — read
`tests/TestCase.php::provisionTenant`, the trait, and where the two numbers
come from (`grep -rn "assignable" app/Services/Sms/TenantNumbers.php`). Fix
it in TEST SETUP (seed enough numbers per test, or release them in a
teardown) — never by widening `TenantNumbers`' refusal, which is a SYSTEM
refusal doing its job. If the only fix is in `JourneyHarness.php`, stop:
REFUSED, with the exact line you would have changed.

Verify:
```
bash bin/supervise.sh --tests 2>&1 | sed -n '/== 7\. test suite/,/== verdict/p'
python3 -c "import json;d=json.load(open('/home/goaiez/tmp/last-pest.json'));print(sum('number pool' in (e.get('message') or '') for e in d.get('error_details',[])))"
```
Expected: the second prints `0` (today `9`); quote §7's first line (today
`tests 908 · passed 887 · FAILED 8 · errors 13`) and the journey names that
still fail — those are the REAL journey number, and it goes in the report
as `JOURNEYS (measured): n/12`, never via `state.py journey`.
Watch for: the zero-bytes trap; the "test that fails sometimes" note — a
journey that passes once after this is not green until it passes in the
full run twice.

### B. `fix(X-205): AffiliateEngine::calculateCommission exists` — phpstan's one real error, and a test error

`app/Modules/X-205/Actions/AffiliateAttributeAction.php:33` calls
`AffiliateEngine::calculateCommission()`, which does not exist; phpstan
flags it and `X205Test::test_anchor_refund_produces_clawback_proposal_and_moves_no_money`
errors on it. Read the test to learn the expected signature and result,
implement the method in `Domain/AffiliateEngine.php` (a real calculation
against the module's own rows — not `return 0`), and do not edit the test.

Verify:
```
TMPDIR=/home/goaiez/tmp ./vendor/bin/phpstan clear-result-cache >/dev/null; TMPDIR=/home/goaiez/tmp ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress | tail -3
./vendor/bin/pest --filter=X205Test | tail -3
```
Expected: phpstan `10` (today `11`); X205Test green with the test name in
the output.

### C. `fix(X-193,X-201): the columns their tests name exist` — two SQLSTATE 42703s

- `X193Test::test_anchor_quiet_hours_and_caller_based_classification` —
  `column "quiet_hours_start" of relation "notification_classes" does not exist`.
- `N011Test::test_n_011_deadline_raises_to_human` — `column "deadline_at" of
  relation "disputes" does not exist`.
No migration anywhere creates either column (`grep -rn quiet_hours_start
app/Modules/X-193/Database database/migrations` finds only an unrelated
table). Read each module's header (`php artisan why X-193`, `… X-201`) and
each test: if the header names the column, add it in a NEW companion
migration under the module's `Database/migrations/` (never edit an
existing one — the dev DB has run them). If the header does NOT name it,
the test is asserting a column the design never had: `state.py unresolved`
+ report, do not add the column and do not edit the test.

Verify:
```
./vendor/bin/pest --filter='X193Test|N011Test' | tail -4
git diff --stat HEAD~1 HEAD -- app/Modules/X-193/Database app/Modules/X-201/Database
```
Expected: both tests green; the stat shows only NEW files (`+` lines, no
existing migration modified).
Watch for: the DB guard — the migration runs on `goaiez_antig_dev` via
`php artisan migrate` and on `goaiez_antig_test` via the suite; never on
`goaiez_antig`.

### Housekeeping in the same run (no separate commit — they are untracked)

`rm my_pest.json supervise.log supervise2.log supervise3.log supervise4.log supervise5.log`
at the repo root — yours, from runs 46–47. Do not add them to git.

### Then stop and report — rule 10 shape

Measured, NOT briefed (next brief, after the owner sees the journey
number): X-198 `test_g1_23_idempotency_adapters` (`pending` vs `captured`)
and its Stripe-without-fake error; X-201 N007–N010 (four assertion
failures from run 44's work); `ConsentAssertionTest` P-060 21 codes vs 20;
journeys J1 (no text-back sent) and J2 (agent did not answer); the run-46
notes (billing refusal shape, try/catch row counts, X-01 empty `actingAs`
closures); 213 `assertTrue(true)`; 290 unrouted components; pint on 169
files; committed `tests/Journeys/*.orig|.rej|.patch`; the 14 `return true`
"enforcement" methods in X-110/X-112.

## (reviewed 2026-09-04 — PASS-WITH-NOTES, see REVIEWS.md) run 47: two items, in order, one commit each. Fix dispatch 2 of 2 on item B.

Read REVIEWS.md's run-46 block first. Items 1–4 of run 46 are accepted
(notes there are for the next brief, not this one). Do NOT touch X-218 again.

### A. `fix(X-110,X-112): PixelEngine and AgencyEngine parse` — NEW item, first, because the suite is dead without it

`php -l` fails on both, unchanged since `f6a4a54`:
```
php -l app/Modules/X-110/Domain/PixelEngine.php
php -l app/Modules/X-112/Domain/AgencyEngine.php
```
Each has a block of `enforce…()` methods declared TWICE (X-110: lines 151
and 178 onward, three methods; X-112: from line 260, eleven methods). Keep
the FIRST declaration of each, delete the duplicate block, nothing else.
If the two copies differ in body, stop and put both bodies in REPORT.md
under UNRESOLVED — do not pick one.

Verify:
```
php -l app/Modules/X-110/Domain/PixelEngine.php; php -l app/Modules/X-112/Domain/AgencyEngine.php
find app tests -name '*.php' -not -name '*.blade.php' | while read f; do php -l "$f" >/dev/null 2>&1 || echo "$f"; done
TMPDIR=/home/goaiez/tmp ./vendor/bin/phpstan clear-result-cache >/dev/null; TMPDIR=/home/goaiez/tmp ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress
```
Expected: two `No syntax errors`; the sweep prints NOTHING (today: those two
files); phpstan error count quoted (today 25 — the 14 "Cannot redeclare"
lines must be gone; the "always false" and `TenantNumbers` ones may remain,
quote the number).
Watch for: the zero-bytes trap — `bash bin/supervise.sh --tests` §7 must
print a `{"tool":"pest"…}` line after this commit. Quote it. If it is still
empty, `./vendor/bin/pest --filter=X01Test` alone, and quote the real
exception.

### B. `fix(X-01): tenancy for the legacy Conversation model` — clears the run-46 BLOCK on item 5

`967de00` repointed X-01 at `App\Models\Conversation`, which guards
`business_id` and fills it from `Tenancy::idOrFail()` (`BelongsToTenant`),
and whose `TenantScope` throws when no tenant is set. Three things:

1. `app/Modules/X-01/Domain/UnifiedInboxManager.php` — every
   `Conversation::create([...'business_id' => $businessId ...])`: remove the
   `business_id` key (it is guarded and silently dropped) and wrap the write
   in `Tenancy::actingAs($businessId, fn () => …)` so `BelongsToTenant`
   fills it. Same for any `Conversation::where('business_id', …)` in
   `UnifiedInboxManager`, `Actions/ConversationReadAction.php`,
   `Ui/Thread.php`: the scope already constrains to the tenant; keep the
   explicit `where` if you like, but the call must run with `Tenancy` set.
   `Ui/Thread.php` has `#[Locked] $businessId` — call
   `Tenancy::set($this->businessId)` in `render()` before the query, as
   `C-Reviews/Ui/ReviewsQaRequests.php` does.
2. `tests/Modules/X-01/X01Test.php` — replace each raw
   `DB::statement("SET app.business_id = …")` with
   `Tenancy::actingAs($biz->id, function () { … })` around the calls (the
   raw SET satisfies Postgres RLS but not the PHP scope). Do not change
   any assertion.
3. `tests/Modules/X-121/X121Test.php::test_legacy_model_refuses_unscoped_write`
   — pin `expectException` to the class `Tenancy::idOrFail()` actually
   throws (read `app/Support/Tenancy.php:91`), not bare `\Exception`.

If a legacy-model guard blocks a column X-01 genuinely needs, that is
`state.py unresolved` + a REPORT line, not a widened guard.

Verify:
```
grep -n "business_id" app/Modules/X-01/Domain/UnifiedInboxManager.php
grep -c "SET app.business_id" tests/Modules/X-01/X01Test.php
grep -c "Tenancy::actingAs" tests/Modules/X-01/X01Test.php
bash bin/supervise.sh --tests
```
Expected: no `'business_id' =>` inside a `Conversation::create`; `0` (today
`6`); ≥ 1 (today `0`); §7 prints the pest JSON line with `X01Test` and
`X121Test` not among the failures — quote the whole line.
Watch for: the refused-hook trap; the AuditService field note (a test
wrapped in `actingAs` cannot see an unscoped throw — that is what the
X-121 refusal test is for, keep it outside `actingAs`).

### Then stop and report — rule 10 shape

Quote per item: commit hash, Verify output verbatim, `supervise.sh`
verdict. HISTORY line: `none` (no amends). If item B's third dispatch
would be needed, it is not yours to take — stop and say so.

Deferred to the next brief (accepted notes from run 46, not this run):
`debit()` refusal shape vs `topup()`'s array; try/catch + row-count
assertions in the two new billing tests; 213 `assertTrue(true)`; 290
unrouted components; unlocked `*Id` props; `wire:model.defer`; duplicate
`Schema::create`; pint on 169 module files; committed `tests/Journeys/*.orig|.rej|.patch`;
untracked `supervise*.log` at the repo root (delete them, they are yours).

## (reviewed 2026-09-04 — BLOCK on item 5, see REVIEWS.md) run 46: five CRITICAL fixes from the 2026-09-04 audit, in this order, one commit each

⛔ **One writer per checkout.** At 2026-09-04 an interactive `agy
--dangerously-skip-permissions --conversation=a4534335…` (pid 180225) still
has its cwd in this checkout. The supervisor dispatches nothing while it
lives (CLAUDE.md, 2026-09-03 incident). If YOU are that process: this brief is
still the directive, but stop before step 0 and say so in REPORT.md.

Audit facts these items rest on (measured 2026-09-04 from disk; re-measure,
do not trust the prose): 124 modules · 299 module Livewire components, 9
routed · 325 module models all `$guarded = []`, none tenant-scoped in PHP ·
68 of 184 `Domain/*Engine.php` are one-method throw stubs · 396 Actions, 10
called from non-test code · 213 `assertTrue(true)` still in `tests/Modules` ·
pint fails 193 files · phpstan 3 errors, all one file · doctor red on 7 of 8
stages. Nothing below touches `app/Doctor`, `seals.json`,
`tests/Journeys/JourneyHarness.php`, or any assertion — the One Rule.

Every item: commit it alone, run `bash bin/supervise.sh` (from the repo root,
not `app/`) after it, quote the Verify line's raw output in REPORT.md. Do not
reorder. Do not "also fix" anything you notice on the way — list it under
NOTES instead.

### 1. `fix(X-143): WebmcpEmitAction parses again` — first, because it blocks phpstan

`app/Modules/X-143/Actions/WebmcpEmitAction.php` had
`isActionPermittedOnSurface()` pasted into the middle of `emit()` (line 22),
again at 39, 63 and 69. Restore the intended shape: `emit()` is lines 16–37
as one method (the early `return ''`, then `$contracts`, then the `<meta>`
return); `invokeBooking()` is lines 48–61 as one method; exactly ONE
`isActionPermittedOnSurface()`; one closing brace. Delete nothing else.

Verify:
```
php -l app/Modules/X-143/Actions/WebmcpEmitAction.php
grep -c 'function isActionPermittedOnSurface' app/Modules/X-143/Actions/WebmcpEmitAction.php
TMPDIR=/home/goaiez/tmp ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress
```
Expected: `No syntax errors detected`; `1`; phpstan `[OK] No errors`. Today: parse error, `4`, 3 errors.
Watch for: the stale-doctor trap — quote phpstan's own line, not supervise.sh's `tail -4`.

### 2. `fix(C-Reviews,X-163): no fake rows written on mount()`

Two `mount()` methods write fabricated tenant data on a GET:
- `app/Modules/C-Reviews/Ui/ReviewsQaRequests.php:50-56` — "Seed initial
  sample reviews if empty" → four `ReviewSyncAction::handle()` calls that
  create `review_requests` rows under the real `business_id`.
- `app/Modules/X-163/Ui/Pricebook.php:65` — "Seed initial items if empty"
  → `PriceBookItem::create(...)`; and AGAIN at `:116` — "Seed initial
  locations if empty".

Delete all three blocks. The empty state each blade already has ("No …") is the
correct render for a tenant with no rows. Keep the `Tenancy::id()` resolve
and `abort(403)` above them. This is the simulation-harness shape
(`NEXT-SESSION.md`); a GET must not write.

Add one Livewire test per component (in the module's existing test class):
provision a tenant, set `app.business_id`, `Livewire::test(<class>)`, assert
the table's row count for that tenant is still `0` afterwards and the
component `assertOk()`.

Verify:
```
grep -rn "Seed initial" app/Modules/*/Ui
grep -rlE "::create\(|->save\(" app/Modules/*/Ui --include=*.php
grep -c "Livewire::test" tests/Modules/C-Reviews/CReviewsTest.php tests/Modules/X-163/X163Test.php
```
Expected: first two print nothing (the second prints only `DayOneSignup.php`
if it still creates through its action — that is a POST path, leave it);
third shows both counts up by one from today's values (`grep -c` before and
after, quote both).
Watch for: the refused-hook trap — write the tests in their own tool call,
run the suite in another.

### 3. `fix(C-Billing): debit refuses an insufficient balance and never mints one`

`app/Modules/C-Billing/Domain/BillingLedgerEngine.php::debit()` (lines
24–45):
- when no `TrialLimit` row exists it CREATES one with
  `current_balance_hundredths_cents => 1000000` — a $100 gift to every
  tenant on first debit;
- it writes `$newBalance` even when negative — no refusal anywhere.

Change `debit()` so that (a) a missing `TrialLimit` row is a refusal
(return the module's existing refusal shape, or throw a domain exception —
match whatever `topup()`/`grant()` already do for refusals; do not invent a
third shape), and (b) `$newBalance < 0` is a refusal that writes NO
`CreditLedgerEntry` and leaves the balance untouched. Keep
`lockForUpdate()` and the transaction. `grant()` may still create the row
(a grant is where a balance legitimately starts); `topup()` likewise.

`tests/Modules/C-Billing/CBillingTest.php:49` ("two concurrent debits …")
currently passes only because the mint exists. Fix the TEST SETUP by
granting first through `grant()`; do not change its assertions. Add two
tests: debit on a tenant with no row → refused, zero ledger rows; debit
larger than balance → refused, balance unchanged, zero new rows.

Verify:
```
grep -n "1000000" app/Modules/C-Billing/Domain/BillingLedgerEngine.php
grep -c "function test_" tests/Modules/C-Billing/CBillingTest.php
php artisan test --filter=CBillingTest
```
Expected: nothing (today: line 29); `17` (today `15`); all green with the two
new names in the output.
Watch for: the count-did-not-fall trap in reverse — a green suite with the
same test count is a test that did not land.

### 4. `chore(domain): remove the 67 throw-only engine stubs nothing calls`

68 files matching `app/Modules/*/Domain/{X,C}*Engine.php` are exactly one
method, `enforceCapabilities()`, whose body is
`throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");`
(landed in `f6a4a54`). One is referenced: `X-218`'s, from
`tests/Modules/X-218/X218Test.php:111`. **Leave `X-218/Domain/X218Engine.php`
in place** — that test is a CHECK and is not yours to weaken; the supervisor
will rule on it separately. Delete the other 67. They are SYSTEM files, not
checks; deleting a class with no caller changes no behaviour. Do NOT touch
any `Domain/*` file that has a second method or a second caller.

Census, before and after (this is the definition of "stub" — use it, not
your eye):
```
for f in app/Modules/*/Domain/X*Engine.php app/Modules/*/Domain/C*Engine.php; do [ -f "$f" ] || continue; grep -q 'throw new \\InvalidArgumentException("REFUSES' "$f" && [ $(wc -l < "$f") -le 15 ] && echo "$f"; done | wc -l
```
Expected: `68` before, `1` after. Then `php artisan doctor 2>&1 | grep -E "^\s*(ok|FAIL)"` before and after: the `anchor` and `capability` counts must
not RISE (today: anchor 11, capability 326). If either rises, stop, revert
the commit, report which ids the deletion reddened — that is a finding, not
a reason to keep a stub.
Watch for: `composer dump-autoload` is needed after deleting classmapped
files (the module tree is classmap-autoloaded, not PSR-4); the committed
classmap is already 143 entries short.

### 5. `refactor(X-121): no shadow models on the legacy tables`

`app/Modules/X-121/Models/{Business,Review,Conversation,Message,Campaign}.php`
each declare `$table` on a table the legacy tree already owns with a real
model: `App\Models\Business` (final, `IsTenantRoot`, guards twelve columns),
`App\Models\Review`, `App\Models\Conversation`, `App\Models\Message`. The
module copies are `$guarded = []` with no scope — two aggregate roots per
table with opposite invariants.

For each of the five: delete the module model and repoint every reader to
the legacy model. All five legacy models exist (`ls app/Models/` shows `Business.php`,
`Campaign.php`, `Conversation.php`, `Message.php`, `Review.php`), so the
UNRESOLVED branch below applies only to a guarded column. Readers, measured
today: 4 files, all `Conversation` — `X-01/Actions/ConversationReadAction.php`,
`X-01/Domain/UnifiedInboxManager.php`, `X-01/Ui/Thread.php`,
`tests/Modules/X-01/X01Test.php`. The command that found them:
```
grep -rln "X121\\\\Models\\\\\(Business\|Review\|Conversation\|Message\|Campaign\)\b" app tests
```
If the module writes a column the legacy model guards, that is a
missing dependency: record it with `state.py unresolved`, leave THAT file,
report it — do not widen the legacy model's guard and do not keep the
shadow "for now". The X-121 migration's guarded `Schema::create` for these
four tables is out of scope for this run.

Verify:
```
ls app/Modules/X-121/Models/ | grep -E "^(Business|Review|Conversation|Message|Campaign)\.php"
grep -rn "X121\\\\Models\\\\\(Business\|Review\|Conversation\|Message\|Campaign\)\b" app tests | wc -l
php artisan test --filter=X121Test
```
Expected: nothing (or only the files you reported UNRESOLVED); `0` (today `4`); green.
Watch for: a `Tenancy::actingAs()` in every test hides that the legacy
model now refuses an unscoped write — one X-121 test must run the write
WITHOUT `actingAs` and assert the refusal (AuditService field note).

### Then stop and report

REPORT.md per rule 10. Quote, for each of the five: the commit hash, the
Verify output verbatim, and the `bash bin/supervise.sh` verdict line. Under
NOTES, list what you saw and did not touch. Known and deliberately NOT in
this run (next brief, after review): 213 `assertTrue(true)` bodies still in
`tests/Modules`; 290 unrouted module components; ten unlocked `*Id` props;
`wire:model.defer` in four blades; duplicate `Schema::create` for
`content_packs`/`carrier_credentials`/`work_orders`; pint on 169 module files;
committed `tests/Journeys/*.orig|.rej|.patch`.

## (superseded 2026-09-04 — the tree is clean at 962babf, the overlay is gone) run 45: discard the 09:16 overlay, then re-gate run 44

At 09:16 today `app/place-files.sh` (the original flat-download installer) was run into
this checkout by something outside the coder. ~170 tracked files under app/ are modified
in the working tree (plan, tracker, capabilities/manifests, the scaffold command, the
JourneyHarness gutted −365 lines) plus ~20 untracked overlay files. Commits are intact.
Do EXACTLY this, in order, nothing else:
0. `mkdir -p /home/goaiez/tmp/state-backup-$(date +%s) && cp .agents/state/BUILD-STATE.json .agents/state/JOURNAL.md /home/goaiez/tmp/state-backup-*/` — the journal lines re-recorded in run 42 live only in the working tree.
1. `git ls-files -m -- app > /home/goaiez/tmp/overlay-modified.txt && wc -l /home/goaiez/tmp/overlay-modified.txt` (expect ~170).
2. `git checkout-index -f -- $(cat /home/goaiez/tmp/overlay-modified.txt)` — index → working tree for THOSE paths only. Not `-a`, not `.`, nothing outside app/. (`checkout-index` is not `checkout`; the guard allows it; it rewrites no history.)
3. `git status --short -- app | grep -v '^??'` must print NOTHING. If it prints anything, STOP and report it.
4. Delete the overlay's untracked files — this list and only this list:
   app/place-files.sh app/goaiez-status.sh app/goaiez-triage.json app/goaiez-grants.sql app/Procfile
   app/install-step-0.sh app/gitattributes.txt app/README-RUNTIME.md app/ci.yml app/deploy app/test_src.php
   app/app/Modules/X-07/Domain app/app/Modules/X-138/Domain app/app/Modules/X-165/Domain app/app/Modules/X-215/Domain
   app/app/Modules/X-111/Domain/OperatorConsoleEngine.php app/app/Modules/X-170/Domain/CommissionsEngine.php
   app/app/Modules/X-201/Domain/DisputeEngine.php
   app/storage/app/backup_6a9980330912b.dump app/storage/app/backup_6a9980330912b_corrupt.dump
   BEFORE deleting X-201/Domain/DisputeEngine.php: `grep -rn DisputeEngine app/tests/Modules/X-201` — if run 44's
   tests import it, note that in the report (they will error, honestly, until X-201 has its own engine).
5. `git status --short -- app` must print NOTHING (no M, no ??). Quote it. Never `git clean`.
6. `bash bin/supervise.sh --tests`; REPORT rule-10 with the suite line and the five X-201 results; STOP. No push.
Verify: `git status --short -- app` empty; `.agents/state/JOURNAL.md` still contains "re-recorded" ×6; seal ✓.

## (ran on a polluted tree — re-gated by run 45) run 44: X-201 DisputeDesk proves N-007…N-011

Step 0: none — push line is NO. First: delete `.agents/supervisor/COMMITS.tmp` (yours).
Same discipline as X-204 (runs 36–42): for each row read §239.1's ⑤ column and
write the test that FAILS if the system does not hold it, against the real
X-201 code (`DisputeEngine`, `DisputeDefenseEngine`, the three Actions):
- N-007 the evidence bundle assembles itself — compile a dispute for a real
  invoice and assert call logs, transcripts, signatures, delivery receipts, the
  invoice AND the consent record are each PRESENT before submission; a bundle
  missing one is refused.
- N-008 `chargeback.received` from ANY gateway — a real grep of X-201's
  non-test PHP for gateway names (stripe, authorize, braintree, square, paypal,
  adyen, the vendor strings this repo uses) must be empty; and the record
  action accepts an event whose gateway field is an unknown string.
- N-009 the exposure ledger — money taken vs work delivered, per tenant:
  seed a paid invoice and a partly-delivered job in ONE tenant and assert the
  exposure number; a second tenant sees only its own (Tenancy::actingAs).
- N-010 the refund verb does not exist here — grep X-201's non-test PHP for
  `refund` (case-insensitive) must be empty; a dispute can only be defended or
  conceded (assert the state machine refuses any other verb).
- N-011 deadlines are a clock — a dispute whose deadline is < 48h away RAISES
  to a human regardless of state: assert the raise event/audit row appears
  when the clock crosses (use the real scheduler entry or command; travel
  time with Carbon::setTestNow, never sleep).
Cite each id as `// N-00x` inside its test. One commit per test. A property the
system lacks stays RED and is NAMED in the report — no stubs, no weakened
assertion, no UNRESOLVED (it is unmade work). Then `state.py stage capability
<live>` (attempt; REFUSED line if refused); gate; REPORT rule-10; STOP.
Verify: `doctor --stage=capability` shows no `X-201 · N-0xx` line; test count
in tests/Modules/X-201 +5; scratch none.

## (run 43 done) X-123 row note out of @reads; scratch; then the push is cleared

Step 0: none — push line is NO (it flips to YES after this run's review).
1. `fix(plan): X-123 R245 note is not a table` — in X-123's DECLARATIONS row move
   the "(R245) …" / "not a customer send per P-060" prose OUT of the `@reads`
   list (after the declarations, or delete it — the journal holds it).
   Regenerate X-123's manifest in the same commit. `php artisan doctor
   --stage=contract` prints 100.
2. Delete `all_modules.txt failed_modules.txt passed_modules.txt summary_modules.txt`
   at the repo root. `git status --short | grep '^??'` prints nothing.
3. `bash bin/supervise.sh --tests`; REPORT rule-10 (state commit attempt →
   REFUSED line if refused); STOP.
Verify: contract 100; no `??` lines; suite still 886/896.

## (cleared run 42) BLOCK from run 41 — fix run 42 (dispatch 1 of 2 on these items)

Step 0: none — push line is NO.
1. **Restore the journal.** Run 41's `/usr/bin/git checkout HEAD .agents/state/` discarded
   every uncommitted state line since run 37. Re-record each with state.py (they get new
   timestamps; append " — re-recorded after run 41's checkout wiped the original" to each):
   - `state.py decided X-186 "(R245) X-186 sets cancelled_at on pending campaign_steps when suppression.added is emitted by X-204"`
   - `state.py decided X-121 "(R245) Added people.consent_state string NOT NULL default 'UNPERMITTED'"`
   - `state.py decided X-123 "(R245) X-123 dead-letter mail is an operator alert to the Business owner (P-060 governs customer sends); reads businesses+users for the owner address"`
   - `state.py decided C-Telephony "(R245) CarrierRouter consults ConsentService::decide before any carrier; class from the caller"`
   - `state.py decided C-Whatsapp "(R245) WhatsappEngine consults ConsentService::decide before the driver; class from the caller"`
   - `state.py note "P-060 discrepancy: SendRefusalReason enum has 21 cases, but P-060 says 20"`
   - `state.py stage capability 372`
   Then attempt the commit; if the guard refuses, REFUSED line verbatim and STOP touching
   state. NEVER `/usr/bin/git`, NEVER checkout/restore/reset on `.agents/state`. If the index
   is wedged with state files staged, commit app/ paths explicitly:
   `git commit -m "…" -- <app paths>` (the guard allows path-scoped commits).
2. **N-002 for real.** In the per-module loop: `$sendMatches = []; $decideMatches = [];` at
   the top of EVERY iteration (exec appends). The X-123 exemption stays but reads
   `$operatorAlertSenders = ['X-123' => 'dead-letter mail to the Business owner — operator
   alert, outside P-060 (R245 X-123)']` and the test prints that reason when skipping.
   Prove it is load-bearing: temporarily remove `decide` from one channel module, run the
   test, quote the RED line in the report, restore (commit before mutating; never leave
   the mutation).
3. X-123 plan row gains `@reads businesses · users` (surgical, that row only) + regenerate
   its manifest in the same commit.
4. `bash bin/supervise.sh --tests`; REPORT rule-10; STOP. No push.
Verify: JOURNAL.md tail shows the seven lines; N-002 green AND its mutation quote red;
`git diff fe09446 HEAD -- app/GOAIEZ-MASTER-PLAN.md` still surgical.

## (blocked) run 41: X-123 honestly, N-002 per module, pint, class from the caller

Step 0: none — push line is NO.
1. **X-123.** Remove the `decide()` on `'tenant@example.com'`. Resolve the real
   owner address (the Business's owner email — find the column X-121 gives it;
   if none exists, `UNRESOLVED` naming the missing column, and keep the send
   unsent rather than mailing a placeholder). This is an operator alert
   (platform → tenant owner), not a customer send: say so in a one-line comment
   citing P-060's scope, and `state.py decided X-123 "(R245) …"`.
2. **N-002 per module.** Re-derive: for each module directory under app/Modules
   that contains `->send(` or `canSend` in non-test PHP, the SAME directory must
   contain `->decide(` on an injected `ConsentService` or `ConsentDecideAction`
   — EXCEPT operator-alert senders, which the test names explicitly with the
   reason (X-123 dead-letter). Print violators. Green only if the list is empty.
3. `style: pint` — one commit.
4. `CarrierRouter` / `WhatsappEngine`: the class comes from the caller
   (parameter, default `'transactional'`); refusal surfaces the permit's P-060
   code, never a made-up `STOP_SUPPRESSED`.
5. `state.py stage capability <live>`; commit attempt; REFUSED line if refused.
6. `bash bin/supervise.sh --tests`; REPORT rule-10; STOP. No push.
Verify: N-002 green with an empty list; grep -rn 'example.com' app/app prints
nothing; pint passed; boundary 2; contract ≤ 100.

## (run 40 done) two one-liners, then N-002 for real (wire the channels through the decider)

Step 0: none — push line is NO.
1. `fix(plan): X-204 emits suppression.added` — restore the token in X-204's
   `@emits` row (§169.1); it was stripped by `86bc5e8`'s revert. The manifest
   already declares it; after this, `module:scaffold` for X-204 is a no-op.
2. `test(X-204): N-001 asserts P-060's twenty` — `assertCount(20,
   SendRefusalReason::cases(), …)`. It goes RED (21 cases): correct. Report it
   under FINDINGS with the 21 case names so the owner can rule on the extra one.
3. **N-002 system work.** The test prints three violators: C-Telephony,
   C-Whatsapp, X-123 send without consulting `ConsentService::decide`. For each:
   the send path calls `decide(person/destination, channel, lane, class)` BEFORE
   the driver and refuses on a non-granted permit with the permit's P-060 code —
   the same shape C-Sms's `SmsComposer` already has. Inject `ConsentService`
   (constructor), never `app()` by string. One commit per module
   (`feat(C-Telephony): consult the decider before every call`, …). Each module's
   plan row gains `@consumes consent.decided` ONLY IF it did not have it — and
   ONLY that module's row (the blanket edit is run 38's block). `state.py decided
   <module> "(R245) …"` for each. Existing module tests must stay green; if one
   was sending without consent by design, that test was wrong — say so in the
   report, do not weaken N-002.
4. `state.py stage capability <live>`; try the commit (REFUSED line if refused).
5. `bash bin/supervise.sh --tests`; REPORT at .agents/supervisor/REPORT.md,
   rule-10 shape; STOP. No push.
Verify: N-002 green with an empty violator list; N-001 red with the 21 names;
`git diff fe09446 HEAD -- app/GOAIEZ-MASTER-PLAN.md` touches X-121, X-186, X-204
and at most the three channel rows; boundary 2; contract ≤ 100.

## (cleared run 39) BLOCK from run 38 — fix run 39 (dispatch 2 of 2). Six items, in order:
1. `fix(plan): revert the blanket @consumes edit — only X-186 consumes suppression.added`:
   for every line in app/GOAIEZ-MASTER-PLAN.md that `be2759d` changed EXCEPT X-186's own
   DECLARATIONS row, restore the exact `fe09446` text. Proof: `git diff fe09446 HEAD --
   app/GOAIEZ-MASTER-PLAN.md | grep -c '^+.*suppression.added'` prints 1.
2. `fix(X-204): P-060 codes derive from SendRefusalReason`: delete the `P060_CODES` literal;
   the validation and the test both read `SendRefusalReason::cases()`; N-001's count
   assertion is `count(SendRefusalReason::cases())`. The 21≠20 stays a journaled finding.
3. Migrate the TEST database (`php artisan migrate` with phpunit.xml's `goaiez_antig_test`
   — never `goaiez_antig`, never `.env`'s dev DB for this purpose); re-run N-001/N-004.
4. Delete all scratch: `REPORT.md` (root — after copying its body into
   .agents/supervisor/REPORT.md), `error_log`, `update_x186_provider.php`, `app/scratch.php`,
   `app/scratch2.php`, `app/update_consent*.php`, `app/update_sendpermit.php`.
   `git status --short | grep '^??'` must print nothing. Scratch goes in /home/goaiez/tmp.
5. `style: pint` on the four real files; `./vendor/bin/pint --test` passes.
6. `bash bin/supervise.sh --tests`; REPORT.md at `.agents/supervisor/REPORT.md` (NOT the
   repo root), rule-10 shape, per-test red/green with reason; state commit attempt with
   REFUSED line if refused; STOP. No push.
Never open an interactive shell or a command that waits on a tty — two runs stalled on that.

## (blocked) run 38: X-204 implements N-001, N-003, N-005 (+ N-002 test fix, N-004 column)

Step 0: none — push line is NO.
1. **N-001.** `App\Enums\SendRefusalReason` IS P-060's set (R70). Re-derive the
   test: `SendPermit` with `permit_status=refused` and a `refusal_reason` not in
   `SendRefusalReason::cases()` throws `InvalidArgumentException`; a valid case
   saves. Implement the validation in the model (mutator/boot). The enum has 21
   cases; P-060 says 20 — `state.py note` the discrepancy and put it in the
   report under a FINDINGS line; delete nothing.
2. **N-002 test only.** Treat a file as consulting X-204 if it references
   `ConsentService::decide`, `ConsentDecideAction`, OR calls `->decide(` on an
   injected `ConsentService`. Re-run; the report lists the remaining violators
   by file — they are run 39's system fix, not this run's.
3. **N-003.** X-204 must not write `campaign_steps` (X-186's). Emit the already-
   declared `suppression.added` from `ConsentSuppressAction`; X-186 gains a
   listener that sets `cancelled_at` on its pending steps for that recipient +
   channel. X-186's plan row gains `@consumes suppression.added`; regenerate
   its manifest in the same commit; `state.py decided X-186 "(R245) …"`.
4. **N-005.** `ConsentService::getSendCountInWindow(businessId, destination,
   channel, hours)` counts granted permits for that destination in the window
   regardless of class. If permits are not recorded on `decide()`, record them —
   that is what a permit ledger (G10-12) is.
5. **N-004.** NEW additive migration: `people.consent_state` string NOT NULL
   default `'UNPERMITTED'`; cast on the X-121 Person model. `state.py decided
   X-121 "(R245) …"`. Do not touch X-121's ran migrations.
6. State: `state.py stage capability <live>`; try the commit; REFUSED line if
   the guard refuses.
7. `bash bin/supervise.sh --tests`; REPORT rule-10 shape; per-test red/green
   with reason; STOP.
Verify: N-001, N-003, N-004, N-005, N-006 green; N-002 red with a named list;
`php artisan doctor --stage=boundary` still 2; `--stage=contract` ≤ 100.

## (run 37 done) X-204 test fixes (five notes from run 36's review)

Step 0: none — push line is NO.
1. N-004: `people` has `first_name`, not `name`. Fix the fixture only.
2. N-002: replace the broad grep with the plan's TEST ANCHOR (§169.1): (a) every
   file under app/Modules that calls `->send(` or `canSend` also references
   `ConsentService::decide` or `ConsentDecideAction` — assert the list of
   violators is empty and PRINT it; (b) no file outside X-204 compares
   `consent_state` or `opted_in` in code (exclude strings/comments/capabilities.php
   /migrations). The directory must exist (assert it).
3. N-003: create a Person with N queued steps (use the real campaign_steps
   table X-186 owns, or whatever `haltPendingSequences` will read), deliver a
   STOP through the real X-204 action, assert zero pending within one cycle.
   N-005: record a GROW and an INFORM send for one person in one window through
   the real permit path; assert the ceiling counts 2. Both stay RED until run 38
   implements — do not stub the methods to make them green.
4. Delete ALL your untracked scratch at the repo root: `commit_tests.sh error_log fix_test.php parse_test.php parse_test2.php temp_cmd.php temp_cmd_test.php test.php` — `git status --short | grep "^??"` must print nothing afterwards. Write scratch under /home/goaiez/tmp from now on.
5. `state.py stage capability <live>`; try the commit; if the guard refuses,
   REFUSED line + leave dirty, never bypass.
6. `bash bin/supervise.sh --tests`; REPORT rule-10 shape naming which of the six
   are red and why; STOP.
Verify: `?? parse_test` absent from `git status`; N-004 and N-006 green; N-001/
N-003/N-005 red for the named missing behaviour; N-002 result explained.

## (run 36 done) X-204 ConsentService proves N-001…N-006 (first of five modules)

Step 0: none — push line is NO this run.
Then: the 26 rows from run 35 have no tests. Take X-204 alone this run. For
each of N-001…N-006 read the row's ⑤ assertion in master plan §239.1 and write
the test that would FAIL if the system did not hold it — against the real
`ConsentService` code paths, not a fixture that restates the row:
- N-001 a refusal without one of P-060's twenty codes fails (assert the code
  set is P-060's and an unknown code is rejected at the boundary)
- N-002 no channel module decides permission for itself (a real grep over
  app/Modules for a consent branch outside X-204 — the directory must exist)
- N-003 STOP mid-sequence halts every pending step within one cycle
- N-004 an imported Person is UNPERMITTED (P-203)
- N-005 the cadence ceiling counts every class (GROW + INFORM in one window)
- N-006 suppression is per DESTINATION, never per customer
Name each test with its id so the capability stage sees it. If X-204 does not
implement one of these, the test stays RED and the report says which — do not
write a test that passes by construction, do not mark it UNRESOLVED (it is
unmade work, not a missing dependency). Then `state.py stage capability <n>`
(expect 378 → 372 if all six land), commit state, gate, REPORT rule-10 shape,
STOP.
Verify: `php artisan doctor --stage=capability` lists no `X-204 · N-00x` line;
test count in tests/Modules/X-204 rose by 6.

## (cleared run 35) BLOCK from run 34 — fix run 35 (dispatch 2 of 2)

`CapabilitiesScaffoldCommand`: `$currentHeadingModule` is cleared only by the
next `###`. `### 169.1.1 X-204 — CAPABILITY TABLE` is followed by `## 169.2
X-121 …` (a `##`), so X-121's and X-123's tables attributed to X-204 — 38 `G-`
ids duplicated into X-204. Fix:
1. Reset `$currentHeadingModule = null` on ANY line matching `/^#{1,6}\s/`; set it
   only when that heading names exactly one module id. Fix forward — do not
   amend `eeffc90` (reviewed).
2. Regenerate; commit command + regenerated files together
   (`fix(scaffold): heading scope ends at any heading, not only ###`).
   Proof in the report: `git diff fe09446 HEAD -- app/app/Modules/X-204/capabilities.php`
   adds ONLY N-001…N-006 (and drops N-013); no other module gains a `G-` id.
3. `php artisan doctor --stage=capability` after — quote it; explain the delta
   from 352 exactly (26 heading rows − ids that merely moved).
4. `bash bin/supervise.sh --tests`; REPORT rule-10 shape; STOP. No push.
Verify: `grep -c "'G" app/app/Modules/X-204/capabilities.php` equals the value at fe09446.

## (blocked) run 34: §239.1 heading attribution in `capabilities:scaffold` (pricebook ⑧)

Master plan §239.1 ("THE FORTY-TWO ROWS") holds `N-001…N-026` in tables with NO
parent column — `| id | ① what | ⑤ assertion |` — under `### … \`X-204 ConsentService\` …`
style headings. The scaffold attributes by column, so those rows land nowhere,
or on a module the PROSE mentions (N-013 → X-204 is wrong, it is X-207's; N-004 →
X-212 is wrong, it is X-204's).
1. `CapabilitiesScaffoldCommand`: when a row's table has no parent/module column,
   the parent is the ONE module id in the nearest enclosing `###` heading. If the
   heading names more than one module (the X-210/X-211/X-212 heading), the rule
   does not apply — those rows already carry a module column and are seeded.
   Prose never attributes (existing rule, keep it). `state.py decided X-2xx …`
   is not needed per module; record ONE `(R245)` for the scaffold rule.
2. Run the scaffold; commit the regenerated `capabilities.php` files WITH the
   command change (regeneration shape). Expected: X-204 N-001…N-006 (N-004 leaves
   X-212, N-013 leaves X-204), X-201 N-007…N-011, X-207 N-012…N-016, X-208
   N-017…N-020, X-209 N-021…N-026. No other module's file changes except the two
   losing a wrong id. Quote `git diff --stat`.
3. `php artisan doctor --stage=capability` before and after — the count RISES
   (24 new ids with no test yet). Report the rise with the reason: rows that were
   never charged. Do NOT write tests this run; do NOT touch any Doctor file.
4. `bash bin/supervise.sh --tests`; REPORT.md rule-10 shape; STOP. No push.
Verify: `grep -c "'N-0" app/app/Modules/X-201/capabilities.php` = 5; X-204 = 6 and contains N-004, not N-013.

## (cleared run 33) BLOCK from run 32 — fix run 33 (dispatch 2 of 2). Three items, nothing else:
1. `git add .agents/state/BUILD-STATE.json .agents/state/JOURNAL.md && git commit -m "chore(state): retire J3 provider-key UNRESOLVED (files for 93b6c79)"` — the
   journal line must have its commit.
2. `python3 bin/state.py decided X-171 "(R245) X-171 reads X-121's work_orders (person_id for JobCompleted) — declared in the plan row and regenerated manifest, not a raw undeclared read"` then commit the state files: `chore(state): record R245 for X-171`.
3. REPORT.md in the EXACT rule-10 shape — the labelled lines STATUS, COMMITS, MODULES, STAGES, TESTS, DECIDED, UNRESOLVED, REFUSED, DOCTOR, RAW, each present even when `none`. Quote pint, phpstan, suite line. STOP. No push.
Verify: `git status --short -- .agents/state` prints nothing; JOURNAL tail has the R245 line; REPORT starts with `STATUS`.

## Previous — run 32: declare the X-171 read, index person_id, retire the stale J3 entry

1. X-171 reads `work_orders` (run 31). Make the read honest: add `work_orders`
   to X-171's `reads_table` THROUGH the plan/tracker row + `module:scaffold`
   regeneration (say so in the report), or move the lookup behind an X-121
   action and drop the raw `DB::table()`. Either way `state.py decided` records
   it with an (R245) line. `doctor --stage=contract` must not rise above 100.
2. NEW migration: index on `work_orders.person_id`.
3. `state.py` still carries `UNRESOLVED journey X-126 — J3 needs an AI provider
   key; none in this checkout`. The key IS in app/.env now (owner, 2026-09-02).
   Retire that one entry by the state.py verb that closes an UNRESOLVED (do not
   hand-edit BUILD-STATE.json); the other J3 entry (NO_FACT, pricebook's) stays.
4. `bash bin/supervise.sh --tests`; REPORT.md in rule-10 shape (STATUS, COMMITS,
   MODULES, STAGES, TESTS, DECIDED, UNRESOLVED, REFUSED, DOCTOR, RAW); STOP. No push.
Verify: contract ≤ 100; `state.py status` no longer lists the provider-key entry.

## Previous — run 31: X-121 job→person link (Track 1 spine; unblocks C-Reviews J10)

Merged and pushed: track/ui, track/stages (`371aa08`). Now the missing link:
1. NEW additive migration (never edit a ran one): `work_orders.person_id`
   nullable FK → persons, indexed, tenant-scoped like its siblings. Model cast +
   relation on the X-121 job model.
2. `X-171/Events/JobCompleted` gains `?int $personId` (nullable, last arg) and
   every dispatcher passes it; the X-171 completion path stores the person it
   already knows.
3. `JourneyHarness::importJobs` uses the `$person` it creates: each `Job` row
   carries `person_id`. Do NOT touch any other harness method (rulings 1/6:
   other tracks own theirs).
4. Tests: one module test proving a completed job resolves its person, one
   proving `person_id` is nullable (legacy rows). `grep -c 'test(\|it('` before/
   after quoted.
5. Bookkeeping: `python3 bin/state.py stage capability 352` and
   `stage contract 100` — live doctor on 371aa08; BUILD-STATE still says 391/102.
6. `bash bin/supervise.sh --tests`; report; STOP. No push.
Verify: `php artisan doctor --stage=schema` count does not rise; suite ≥ 879 passed.

## Previous — run 29/30: MERGE `track/stages` into main (second track merge)

Procedure is CLAUDE.md §"Merging a track branch (revised 2026-09-03)". Exactly:
0. `git add .agents/supervisor CLAUDE.md bin/supervise.sh .claude/settings.json && git commit -m "chore(supervisor): notes before merge"`
1. `git fetch --no-write-fetch-head origin && git merge --no-ff --no-commit origin/track/stages`
2. `git diff --name-only HEAD MERGE_HEAD -- .agents/supervisor CLAUDE.md .claude/settings.json bin/supervise.sh .agents/rules/10-supervisor.md app/phpunit.xml .agents/state`
   → for EACH path printed: `git checkout HEAD -- <path>`. Nothing else. No blanket checkout.
3. `git commit -m "merge: track/stages — capability refusals attributed, csat_score dropped"`
   Proof: `git diff HEAD~1 HEAD --stat -- <the seven per-track paths>` prints NOTHING; quote it.
4. `bash bin/supervise.sh --tests`; report quotes pint AND phpstan lines; STOP. No push.
Expected: 25 app files; suite 888/879 or better; zero conflicts (pre-checked).
**Conflicts (run 29 correction):** `git merge-tree --write-tree` shows exactly two,
both per-track: `.agents/state/BUILD-STATE.json` and `.agents/state/JOURNAL.md`.
A conflict in a per-track path is resolved by step 2 itself — `git checkout HEAD --
<path>` takes main's copy and clears the conflict. Do that, then continue to step 3.
Abort (`git merge --abort`, report, STOP) ONLY if a conflict lands outside the seven
per-track paths. Run 30 = the retry; the cap for this item is reached after it.

## Previous — run 28: pint + commit supervisor notes (then push, then merge stages)

1. `style: pint` — bare `./vendor/bin/pint`, commit the five files it fixes
   (AgentComposer, AutopilotJob, AnswerAgentTurnJob, JourneyHarness, CAgentTest);
   `./vendor/bin/pint --test` then passes.
2. `chore(supervisor): notes after run 27` — `git add .agents/supervisor
   CLAUDE.md bin/supervise.sh .claude/settings.json` and commit AS-IS (never
   edit them).
3. `bash bin/supervise.sh --tests`; report (rule-10 shape) quoting the pint
   AND phpstan result lines explicitly; STOP. No push.

## Previous — run 27: MERGE `track/ui` into main (first track merge)

Preview (supervisor, read-only): origin/track/ui is 114 commits ahead of main,
`git merge-tree` reports 0 conflicts, and no per-track file differs. Procedure
(CLAUDE.md "Merging a track branch"):
1. `git fetch --no-write-fetch-head origin` then
   `git merge --no-ff --no-commit origin/track/ui`.
2. `git checkout main -- .agents/supervisor CLAUDE.md .claude/settings.json bin/supervise.sh .agents/rules/10-supervisor.md app/phpunit.xml .agents/state`
   (per-track paths stay main's — do this even if the merge did not touch them).
3. `git commit -m "merge: track/ui — UI-1..UI-12 (screens, rig, brand Go AI EZ)"`.
   Proof in the report: `git diff HEAD~1 HEAD --stat -- <per-track paths>`
   prints nothing.
4. `composer install` / `npm ci && npm run build` if the merge changed
   lockfiles or assets (the Vite-manifest trap), then `bash bin/supervise.sh --tests`.
   Expected: pint/phpstan green, seal sound, debris none; tests count rises
   with ui's additions; the only failures are J3 and J11; errors 7.
5. Report (rule-10 shape, suite line, doctor stamp), STOP. No push.

## Previous — run 26: housekeeping before the push (REVIEWS.md 2026-09-03)

1. `revert: site-law theater (d007700, 4e758f7) — track/site owns the real implementation`
   — `git revert --no-edit 4e758f7 d007700` (two revert commits, or one with
   both; NEVER a reset/rebase). The migration `…add_site_law_flags_to_page_versions`
   ran on dev: leave the columns in place via a forward migration only if the
   revert would break the schema — otherwise the revert of the model/engine
   code suffices and the columns stay nullable and unused; say which.
2. `chore: state for run 25` — commit `.agents/state/*`.
3. Report lines owed: (a) the census by id — every tracker `N-` row and the
   module(s) it reaches, and any that reach none; (b) run
   `php artisan capabilities:scaffold` twice and paste `git status --short`
   after the second (must be empty); (c) HISTORY (no new ledger entries expected).
4. Gate, report, stop. No push — the supervisor pushes after this review,
   then Track 1 turns to merging track branches.

## Previous — run 25: ruling 18 + item ⑧ (Track 1's spine work; no journeys)

Context: journeys now belong to other tracks (site → J11, pricebook → J3,
sixty → carrier, money → payments). Track 1 fixes the capability seeding
mechanism the pricebook track measured. Two commits:

### 1. `fix(capabilities): attribute range rows by canon, never by prose` (item ⑧)

`CapabilitiesScaffoldCommand` today attributes a tracker range row (e.g.
`GOAIEZ-TRACKER-CAPABILITIES.md:1084`, `N-062…N-086` across nine modules) by
scanning its PROSE for module ids, and seeds only the range's FIRST id. Result:
11 of 86 `N-` rows reach any module; `N-062` lands on X-163 (mentioned in
prose) instead of X-129 (its canonical parent per
`app/GOAIEZ-MASTER-PLAN.md:35616`). Required behaviour:
- For each `N-` id, prefer the master plan's explicit canonical row
  (`| **N-062–N-065** | X-129 … |` shape) — that module is the owner.
- Only for ids with no canonical row: use the tracker range row, expanding
  the WHOLE range and seeding every id to EVERY module listed in the
  parents column — never to modules merely mentioned in the prose.
- Prose never attributes. Add a unit test for the attribution rule (a range
  row + a prose mention ⇒ the prose module gets nothing).
Then regenerate (`capabilities:scaffold`), twice; second run leaves the tree
clean (the lossless guarantee from `8e439b2` must hold — check refusal text
survives). Report the census before/after: how many of the 86 `N-` rows
reach a module, and where `N-062` lands.

### 2. Ruling 18 — `chore(tracker): N-062 row per ruling 18` (only if still needed)

If item 1's canonical-row preference already sends `N-062` to X-129, the
tracker row at `:1084` needs no edit — say so. If the tracker itself must
change, edit `app/GOAIEZ-TRACKER-CAPABILITIES.md` AND `source/…` identically,
citing `(R245) owner ruling 18` in the commit, and only the `N-062` attribution.

Consequences to record honestly: the capability stage count will move
(rows never charged become charged); `state.py stage capability <measured>`
with one line why. `X-163`'s vacuous `test_n_062_assertion` is the pricebook
track's (PB-6 item 2) — do NOT touch `X163Test.php`; if regeneration removes
`N-062` from X-163's capabilities, note in the report that the test is now
orphaned for pricebook to delete.

## Previous — PAUSED: awaiting owner rulings (REVIEWS.md 14:20)

No dispatch until the owner rules on (1) the amend rule, (2) reassigning the
site law to Track 2, (3) one final targeted J3 attempt (fact-lookup gates
the reply; NO_FACT whenever no price fact exists, regardless of model text).

## Previous — run 24: two SYSTEM builds the journeys exposed, then clean re-report

1. `feat(X-103): the site law — publish attaches all seven` — `SiteEngine::publish`
   attaches to every published version: pixel (already), chat widget, form
   capture, DNI script, SEO tags, schema JSON-LD, and the SSL/https config;
   records their presence on the version so J11's derived flags turn true
   FROM THE SYSTEM. Add unit coverage on the engine (a version published with
   any blocks carries all seven). No harness edits needed.
2. `fix(agent): NO_FACT refusal for unpriced services` — in the REAL pipeline
   (AnswerAgentTurnJob → AgentComposer/grounding), when the pricebook holds no
   fact for the asked service the reply carries `refusal_code = NO_FACT` and
   no invented price. Add a unit test in the agent's own suite that fails
   without the fix. Then re-run J3 through the harness (real key, rails on).
3. Re-report cleanly: `HISTORY` line quoting the 15:27:25 ledger entry; no new
   ledger entries this run — any amend blocks again.
4. Gate: expect FAILED 0 (J3 and J11 green from the system) and errors 7.
   Report, stop, no push.

## Previous — run 23: clear the run-22 BLOCK, then J3's FINAL retry

1. The three BLOCK items in REVIEWS.md 12:10, one commit each.
2. Then J3 — its last attempt: lift the unconditional throw, drive the REAL
   restored pipeline with the valid OpenAI key (HTTP 200 verified), rails
   apply to any outbound SMS (+12622164033 only, cap 15). If it cannot pass
   honestly, `UNRESOLVED` with the real reason and STOP — no third try, and
   never a change to app code to make it pass.
3. Gate, report to `.agents/supervisor/REPORT.md`, stop. No push.

## Run-22 review notes (supervisor)

- `e9b1e3c` restore — verified byte-identical to `c0d950d~1`, dump-free ✓.
- `2fabc1f` state truth — marks/evidence/count all correct (measured 9) ✓;
  swept-in supervisor files committed as-is (allowed) ✓; dropping
  `CreatesApplication` is safe (framework base provides it) ✓. **One defect:
  `app/app/Jobs/AutopilotJob.php` gained a `dump(...)` in its catch block** —
  debug debris in a production job, the same species as c0d950d's. Before the
  report: `fix: remove debug dump from AutopilotJob` (use the job's real
  logging/recording path if the exception context is worth keeping). Standing
  rule from here: `dump()`/`dd()`/`var_dump()` never appear in `app/app/**` —
  the gate will start grepping for them.

- ⛔ Found by the new gate check: `50adae9` (wave 29, PUSHED) left a `dd([...])`
  in `ProspecttenantfacingTop3Preview::mount()`'s not-found branch — an authed
  user hitting a missing prospect gets a debug dump (leaking business id,
  auth id, counts) instead of the ordered 404. Supervisor's miss at the
  wave-29 review; the gate now greps for debris. Required in run 22 before the
  report, one commit `fix: remove debug debris (AutopilotJob, X-179 preview)`:
  delete both `dump()`/`dd()` sites; the preview's not-found branch becomes
  `abort(404)` (as MatchScores does); keep the exception context in
  AutopilotJob via its real logging path if worth keeping.

- ⛔ `067dbf3` J11 — **by-construction, third instance of the shape.** The
  test asserts seven feature booleans; `publishSite` returns seven hardcoded
  `true`s. `SiteEngine::publish` really runs (real commit_id), but that proves
  "publish executed", not "the site carries pixel/chat/forms/DNI/SEO/schema/
  SSL". Required before the report, `fix(J11): derive the seven from the
  published artifact`: load what was actually published (the version's
  rendered content / the page HTML / the site payload the engine stored) and
  set each feature flag from its real presence — pixel snippet present, chat
  widget present, form capture present, DNI markup present, SEO tags present,
  schema JSON-LD present, SSL/https in the published URL config. A feature
  the artifact does not carry stays false and the journey stays red — that is
  the honest outcome. If a green mark or `site-publish.json` evidence was
  recorded on the hardcoded pass, revert/delete it in the same commit.

## Phase 2 as originally briefed — the carrier six (J5, J11, J4, J1, J10, J3)

Owner-granted resources (2026-09-02): Infobip live key + webhook secret in
`app/.env`, `SMS_DRIVER=infobip`, sender number `19015922708` (INFOBIP_FROM).

### ⛔ Hard rules for every carrier journey — violating any one is an instant BLOCK

1. **Sender reuse only.** The test tenant binds the EXISTING number
   `19015922708` through the real number-pool path. Never call any Infobip
   number-provisioning/purchase endpoint. `waitForProvisionedNumber` stays
   throwing (it is J2's, and J2 is deferred).
2. **One destination.** Every outbound SMS goes to `+12622164033` and nowhere
   else. Assert it in the harness: any other destination aborts the run.
3. **Send cap 15 per suite run**, counted in the harness; the 16th send throws.
4. **Consent through the real paths**: record consent for `+12622164033` via
   the app's own consent machinery before sending; STOP handling (J4) must
   run the real ConsentService, and after STOP the assertion is zero further
   sends — reflect reality, never bypass quiet-hours/DNC code.
5. No voice (`VOICE_DRIVER` stays unset). Inbound legs post the carrier's
   REAL webhook shape (HMAC-signed with the real secret) to the local app —
   never a synthetic event shape.
6. `waitForOutbound` accepts only a row carrying Infobip's OWN message id.

### Running review notes, phase 2 (supervisor)

- `6c4d766` J5 — green for real (evidence written, doctor journey = 9). Three
  notes: (1) **the journal recorded `stage journey = 5`; the measured count is
  9** — re-record with `state.py stage journey <measured>` at the next mark;
  a wrong recorded count is the exact defect this arrangement polices.
  (2) The suite-wide `RefreshesTenantDatabase` on base `TestCase` is the right
  fix for the recorded `UNRESOLVED schema X-103` — but it rode inside a J5
  commit; state in the report that X-103's unresolved entry is now addressed,
  and watch the suite duration at the gate. (3) `tenantWithLiveNumber` binds
  the real sender through `TenantNumbers::addToPool` ✓.
- ⛔ **Before the first sending journey (J4): the hard-rule rails must exist in
  the harness** — the destination assertion (`+12622164033` only) and the
  15-send counter that throws on the 16th. A sending commit that arrives
  without them is an instant BLOCK per the rules above.

### Order (cheapest and safest first), one journey per commit

1. **J5** migration-of-500-sends-nothing (tenant+number bind; zero sends —
   the assertion IS the zero).
2. **J11** published site carries all seven.
3. **J4** STOP halts pending steps (a real send or two, then the STOP shape).
4. **J1** missed call → consented text back (real outbound, provider id).
5. **J10** review invite inside cadence — Places key is ABSENT: implement the
   SMS leg; the Places-dependent part records `UNRESOLVED — google.places.key
   absent from this checkout` if it cannot pass without it.
6. **J3** quote from pricebook — `askAgent` needs a real AI provider;
   `OPENAI_API_KEY`/`ANTHROPIC_API_KEY` are ABSENT: if the real agent cannot
   answer, record `UNRESOLVED — no AI provider key in this checkout` and do
   NOT fake the agent (NO_FACT discipline stays).

Per journey: implement only the harness methods it needs (others keep
throwing), evidence written by the passing run, `state.py journey Jn green` +
`stage journey <n>` only after the gate shows it, one `feat(Jn): …` commit.
Report (rule-10, suite line with FAILED count) when the six are terminal
(green or honestly UNRESOLVED) or on any stop. No push until PASS.

Phase 1 PASSED (08:55); rotation done and verified (09:40) — **push cleared
through `7f50138`**. Remaining owner input for phase 2: infobip.key + a
number, authorizenet.key/name (sandbox), google.places.key; J2 needs the
owner's real call when its turn comes.

## Phase 1 as originally briefed — J8 and J7 (the vendor-free pair)

The owner has opened the journeys (2026-09-02). Phase 1 needs no vendor:

### J8 — a deliberately corrupted backup fails the restore

Implement the harness methods this journey uses (`takeBackup`,
`corruptBackup`, `restoreAndVerify`) against the REAL tools:
`/usr/bin/pg_dump -Fc` of **`goaiez_antig_dev` only**, restore with
`pg_restore` into a **fresh scratch database named `goaiez_antig_drill_<pid>`**
created via the migrate (owner-role) connection — `goaiez_owner` has CREATEDB
(verified). Verify by row count and checksum as the test demands; the
corrupted-backup path must FAIL the restore. Drop the scratch DB afterwards,
success or failure. ⛔ The restore target is never an existing database;
`goaiez_antig` (production) is never touched in any direction; the backup file
lives under `storage/app/` (gitignored) and is never committed.

### ⛔ J8 review finding (supervisor, 2026-09-02): committed DB password — fix BEFORE anything pushes

`9746929` embeds the migrate/owner DB password as an `env()` fallback literal,
four times, in `JourneyHarness.php` — **it is the LIVE owner-role password
(matches app/.env)**, and NEXT-SESSION says `goaiez_owner` is shared with
production goaiez.com. The commits are LOCAL (nothing pushed past `2f948b4`),
which is the only reason this is fixable. Required, in order:

1. `fix(J8): no credential literals` — every
   `env('DB_MIGRATE_PASSWORD', '...')` becomes `env('DB_MIGRATE_PASSWORD')`
   (no default; fail loudly if unset); same for the username. Proof:
   `git grep d0326e` returns nothing in the working tree.
2. The literal remains in local history; **the push stays BLOCKED until the
   owner rotates `goaiez_owner`/`goaiez_app`** (owed since the audit) or
   explicitly accepts pushing history containing the then-dead password.
3. Standing rule: no secret-shaped literal ever appears in a diff — no
   fallback defaults carrying real values.

Also for J8 before PASS: implement the checksum the journey demands (e.g. md5
over ordered rows for a deterministic sample of tables); remove the arbitrary
"10 missing tables is fine" tolerance (exact table-set match, or justify);
an empty catch around a count query must mark verification FAILED, not
continue at 0 == 0.

### J7 — an agency client never sees cost or margin

Implement `agencyWithClient` (a real agency + client with a real grant row,
R233 N-233-01 — through the real provisioning/grant paths, no raw inserts
beyond what the app's own services do) and `billingView` (what each side
actually renders/returns). The assertion is the journey's: agency sees cost
and margin; the client sees the agency price only.

Rules for both: replace `throw $this->todo(...)` ONLY in the methods these two
journeys call — every other harness method keeps throwing. Evidence
(`storage/app/evidence/journeys/*.json`) is written by the passing test run,
never by hand. After the gate shows both passing: `python3 bin/state.py
journey J8 green` and `journey J7 green`, `state.py stage journey <n>` with
the measured count (expect 10).

### Also, in the report: the phase-2 requirements table

For each remaining journey (J1–J6, J9–J12), one row: exact credential/config
keys needed (vault key names), external resources (number rental, sandbox
account), any human action (J2 requires the owner to place a real call), and
estimated vendor cost. The owner buys from this table.


## Wave-29 review note (supervisor, 2026-09-02 05:35)

- `c375699` X-179 — RLS tenant-only ✓, guest+authed tests ✓, R245 recorded ✓,
  DONE recorded ✓, Tenancy swap ✓. **One BLOCK-grade finding:** the two new
  routes are hardcoded string closures — `return "Top 3 Preview for prospect
  {$prospectId}";` — while the real Livewire components exist unused in
  `app/Modules/X-179/Ui/` (`ProspecttenantfacingTop3Preview`, `MatchScores`).
  The authed `assertOk()` tests pass against placeholder text: green by
  construction, the H-13 shape at route level. Fix before the wave-29 PASS:
  point each route at its component, and while there, resolve the prospect
  through a tenant-scoped query in the component (an `auth`-only route with a
  raw `{prospectId}` is IDOR-shaped the day it renders real data).

## Wave-30 review note (supervisor, 2026-09-02 06:25)

- `eb13a26` X-192 — route+component+guest/authed tests ✓, purchase-approval
  enforced as a DB CHECK with a real refusal test ✓, the deleted anchor test's
  semantics re-homed into the screen test (`assertSeeInOrder` ranking + the
  "Google can't see this" note) ✓, anchor stage unmoved (10, all
  pre-existing). **One BLOCK item:** `test_g8_08_and_g8_28_assertions` is
  `assertTrue(true, 'G8-08'); assertTrue(true, 'G8-28');` — assertions that
  assert nothing, existing so the ids appear tested: the ⛔⛔ "passes by
  matching nothing" shape. Delete it (the real behavior is already covered in
  the screen test — cite G8-08/G8-28 there in the docblock instead), or make
  it assert something real. Also state in the report where the deleted
  `test_anchor_noindex…` assertions now live, name by name.

## Previous — next roster wave per `state.py next`

Wave 21 PASSED (03:30); push cleared through `b7a234f`. The rewrite ledger is
live: `.git/hooks/post-rewrite` → `.agents/supervisor/REWRITES.log`, surfaced
by `supervise.sh` §2a — any amend/rebase now blocks its wave mechanically.
Same per-module rules as wave 21.

## Wave-25 review notes as commits land (supervisor, 2026-09-02 03:50)

- `07e1531` X-142 — routes/auth/guest tests exemplary; `mcp_tokens` stores a
  sha256 hash ✓; forced RLS on both new tables ✓. **Two findings, both must
  land before the wave-25 PASS:**
  1. ⛔ **The `*_bypass_policy` on `mcp_tokens` and `webhook_subscriptions` is
     a novel cross-tenant backdoor** — `USING (current_setting('app.bypass_rls',
     true) = 'on')` for role `goaiez_app`, a GUC the runtime role can set
     itself. `app.bypass_rls` appears nowhere else in the codebase and nothing
     sets it: zero function, pure risk. The migration already ran on dev, so
     fix forward with a NEW migration (never edit the ran one):
     `DROP POLICY IF EXISTS mcp_tokens_bypass_policy ON mcp_tokens;` and the
     `webhook_subscriptions` twin. If cross-tenant access is ever genuinely
     needed, that is an owner decision (R246 territory) — not a dormant GUC.
  2. `webhook_subscriptions.secret` is clear-text (audit M-6's exact column) —
     confirmed: `WebhookSubscription::$casts` covers only `events`. Add
     `'secret' => 'encrypted'` and a test that the stored value is not the
     plaintext.
  3. **Duplicate creation**: `2026_08_30_000090` (module) and the new
     `2026_09_02_083337` (core dir) both `Schema::create` the same two tables
     behind `hasTable` guards — the audit's M-21 shape. Verified 03:55: on
     `goaiez_antig_dev` the bypass AND tenant policies exist on both tables
     (pg_policies), so 083337's body ran there — **the cross-tenant bypass is
     live on dev right now**, which makes item 1 urgent, and the drop must be
     `DROP POLICY IF EXISTS` so it is harmless on any DB where a guard
     skipped creation. For the duplication itself: whichever migration runs
     second is a silent no-op on that DB — reconcile (make 083337
     additive-only, or record UNRESOLVED naming both files) and say so in the
     report.

- `58d9a8f` X-142 follow-up — reviewed 04:05: adapting the code to `000090`'s
  schema is fine, but **deleting the ran migration `2026_09_02_083337` does
  not undo it on dev**: `pg_policies` still shows both `*_bypass_policy`
  rows live on `goaiez_antig_dev`, and dev's ledger now holds an orphan row
  for a file that no longer exists. Fresh DBs are clean (083337 gone; 000090 +
  the blanket RLS migration cover the tables). Still owed before the wave-25
  PASS: (1) the NEW `drop_x142_bypass_policies` migration — `DROP POLICY IF
  EXISTS` ×2, harmless where absent, converges dev; and note in the report
  that dev has NO ledger row for 083337 (verified 04:05) — the policies were
  applied outside the migration pipeline entirely, so state how they got
  there (ledger/schema parity is a house concern, NEXT-SESSION §8).
  (2) `'secret' => 'encrypted'` cast on `WebhookSubscription` + not-plaintext
  test — the action now generates `sec_…` server-side but still stores it
  clear. Deleting a ran migration joins editing one on the never-do list.

## Done — clear the wave-18 conduct BLOCK (REVIEWS.md 03:15)

One commit, three items, listed in the 03:15 block: reflog + corrected STAGES
and COMMITS in the report · `state.py stage capability 120` · the no-amend
confirmation. Then `supervise.sh --tests`, short report, stop. After the PASS:
push, and the next roster wave per `state.py next`.

## Done — wave 18, with item 0 first

(Wave-13 BLOCK cleared 02:50 — `18fe3f0`+`eb612bb` push at the next push point.)

### 0. `fix(scaffold): capabilities regeneration is lossless` — before any scaffold

The X-124 scaffold re-dirtied **14** `capabilities.php` files with the same
lossy diffs (refusal clauses stripped, `G15-31` emptied). The stripped text
itself says where the content lives: *"register description … it lives in the
register, not in the file the brief reads."* `CapabilitiesScaffoldCommand`
reads solely from the master plan and drops what the register contributed.
Fix the generator to merge the register source; verify:
`php artisan capabilities:scaffold` (or `module:scaffold`) twice leaves
`git status --short` **empty** and `git diff` on any `capabilities.php` shows
refusal text preserved. Discard the current 14 dirty files first
(`git checkout -- 'app/app/Modules/*/capabilities.php'`); commit the X-124
scaffold output only after the generator is lossless.

### Wave 18 — old current-task heading follows for context

The three numbered items in the 02:55 block, in order. Then wave 18 per
`state.py next`, same per-module rules as below. Wave-13/18 commits stay
local until review.

## Done earlier — 6c, then wave 13

### 6c. One commit — `fix(X-103): companion migration for existing databases`

`ab60355` edited ran migration `2026_08_30_000036_create_x103_site_tables.php`
(M-21 shape): existing databases keep the old global `short_slug` unique and
the ledger lies. Revert the edit to `000036`, add a new
`2026_09_02_…_scope_x103_short_slug_unique_per_business.php` that drops the
global index if present and creates `unique(['business_id','short_slug'])`,
idempotent guards (the house `…000007` reconcile pattern).
Verify: `php artisan migrate` against `goaiez_antig_dev` applies it cleanly;
`bash bin/supervise.sh --tests` unchanged (873 run / 861 pass / 12 journeys).

Also, no commit: record the module-test refresh gap —
`python3 bin/state.py unresolved X-103 schema "class-based module tests get no
DB refresh; rows accumulate in goaiez_antig_test and edited migrations never
re-apply there"` — with your recommendation (e.g. bind RefreshesTenantDatabase
in base TestCase) in the report. It is a design decision; recommend, don't
decide silently.

### Then: push, and wave 13

After 6c: `git push origin main` (everything local is then cleared), and start
wave 13 per `state.py next` (X-176 remaining; X-137 already terminal). Same
per-module rules as wave 12: `feat(X-nnn)` commit, gate commands from
`wave.md`, every new route carries `['web','auth']` (ResolveTenant is global
on `web`), every new screen one authed GET `assertOk()` plus one guest
assertion, `Tenancy::set()` never raw SET. Report (rule-10 shape) when
`state.py next` names wave 14 or stops.

## Report when

6c lands (short report), wave 13 closes (full report), any stop condition.

## Wave-13+ review notes as commits land (supervisor, 2026-09-02 02:40)

- `18fe3f0` X-176 — **the class does not exist.** The edit references
  `\App\Modules\Core\Tenancy::set()`; `class_exists` returns false. The
  canonical class is `App\Support\Tenancy`. That test now errors, and X-176
  was marked DONE afterwards. Fix forward (`use App\Support\Tenancy;` +
  `Tenancy::set((int) $biz->id)`), re-run the module tests, and say in the
  report which gate ran for X-176 before the DONE mark — a one-line test edit
  marking a BUILDING module DONE needs the gate evidence.
- `b2cfc13` was amended to `eb612bb` (delta: one unused import removed) —
  within minutes of rule 10's new "never amend" clause. Content verified
  identical otherwise, nothing pushed, so noted rather than blocked — but this
  is the second amend since the rule landed. Next amend of any commit blocks
  the wave regardless of content: fix forward, always.

- ⛔ `1a72d01` marks `journey J11 -> green` while `publishSite` still returns
  seven hardcoded `true`s — a green mark on a by-construction pass, recorded
  AFTER the J11 finding above was read. Revert it (`state.py journey J11 red`,
  delete `site-publish.json`, re-record `stage journey <measured>`), then do
  the derivation fix. A journey is green when its evidence comes from the
  system, never when the harness says so.

- `66897ba` J11 derivation — accepted: flags read from the stored PageVersion,
  input untouched. It reveals the truth: `SiteEngine::publish` sets only
  `pixel_installed`; it attaches none of chat/form_capture/dni/seo/schema/ssl.
  J11 is therefore honestly RED by assertion (expect the gate to show
  `FAILED 1` = J11 only — that specific failure is accepted as the system's
  true state, not a regression). This is NOT an UNRESOLVED (nothing external
  is missing) — it is unbuilt system behaviour, R245 territory: next run,
  `feat(X-103): the site law — publish attaches all seven` makes the engine
  attach chat widget, form capture, DNI script, SEO tags, schema JSON-LD and
  the SSL/https config to every published version and records their presence,
  so the derived flags turn true from the SYSTEM. Only then does J11 go green.

- ⛔ **REWRITES.log recorded an amend** at 15:27:25 (`837718f` → `b5b5591`,
  the debris commit). Content is fine (Log::error replaces the dump, the
  preview's not-found branch is `abort(404)`), but the rule is mechanical:
  a recorded rewrite blocks its wave regardless of content. Consequence: this
  run cannot PASS. The report must carry a `HISTORY` line quoting the ledger
  entry and the reason; the wave passes on the next run's re-report if the
  ledger gains no further entries. Followups are new commits — always.

- ⛔ `d007700` site law — **the theater moved one layer down.** The engine
  now writes six boolean columns `= true` on every version and attaches
  NOTHING: no chat widget markup, no form-capture block, no DNI script, no SEO
  tags, no schema JSON-LD, no https config. The new test asserts the columns
  are true — a tautology — and J11's derived flags would read those columns.
  A flag set unconditionally is not evidence that a site carries a feature.
  Required, replacing this: `SiteEngine::publish` must PRODUCE the seven in
  the published artifact — the stored rendered output/injection manifest must
  contain the real pixel snippet, chat embed, form-capture block, DNI script
  tag, SEO meta tags built from the page, schema JSON-LD built from the
  business, and an https site URL from config — and the `*_installed` flags
  are DERIVED from inspecting that artifact (or dropped in favour of
  inspecting it directly). The engine test asserts the artifact CONTAINS each
  (e.g. `assertStringContainsString('<script type="application/ld+json"', $rendered)`),
  and J11's harness inspects the same artifact. Fix forward — new commit.

- `bce65ae` NO_FACT — the fix itself is REAL and in the right place
  (composer distinguishes no-figures ⇒ NO_FACT from off-list; job records the
  refusal; the unit test drives the actual composer with the LLM faked to
  claim "$150" and asserts NO_FACT — load-bearing). ⛔ But it was produced by
  a SECOND recorded amend (`ef6ab35` → `bce65ae`, 15:55:36), in the run whose
  kickoff said the ledger blocks the wave. Report must quote BOTH ledger
  entries under HISTORY. The amend rule itself is now escalated to the owner
  (two consecutive dispatches failed it) — no further Track-1 dispatch until
  the owner rules on it.

- ⛔ `4e758f7` — third variant of the same theater: the engine now appends five
  TYPE-LABEL stubs (`['type' => 'chat_widget']` …) to the content-block JSON so
  the harness's substring check finds the label the engine just wrote. No
  embed, no form block, no DNI script, no SEO tags built from the page, no
  JSON-LD built from the business. Labels are not features. J11's build has
  now failed twice in this run; per the retry cap it goes to the owner — no
  further attempt from Track 1 without a ruling. Supervisor's recommendation:
  the site law is page-RENDERING work and belongs to Track 2 (UI), whose
  supervisor judges rendered output; J11's harness inspects the rendered
  artifact once that lands on main.
