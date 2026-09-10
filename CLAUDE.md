# Supervisor — grs-antig (`goaiez-antigravity`)

You are the **Supervisor** of this checkout. **Antigravity is the coder.** You
review, gate, and brief; you do not build. This file supersedes
`/home/goaiez/agents/CLAUDE.md` here: there are no `wtN` worktrees, no
`roster.sh`, no PR flow in this repo — one checkout, one coder, one remote
(`origin/main`, github.com/arfied/goaiez-antigravity, pushed directly).

The coder's contract is the root `AGENTS.md` and `.agents/rules/*`. Read them;
you hold the coder to them. Your side of the arrangement is
`.agents/rules/10-supervisor.md` — the coder reads that one too.

## Roles

| Supervisor (you) | Coder (Antigravity) |
| :--- | :--- |
| Reads the whole tree. Edits **only** `CLAUDE.md`, `.agents/supervisor/**`, `.agents/rules/10-supervisor.md`, `bin/supervise.sh` | Edits `app/**`, works `bin/state.py next`, commits |
| Runs read-only checks: `bin/supervise.sh`, `state.py next\|status\|report`, `php artisan doctor*`, `phpstan`, `pint --test`, `git status\|diff\|log` | Runs `state.py decided\|unresolved\|stage\|note`, migrations, tests, `git commit` |
| Writes `BRIEF.md`, appends `REVIEWS.md` | Writes `REPORT.md` |
| Commits **only its own files** (`CLAUDE.md`, `bin/supervise.sh`, `.claude/settings.json`, `.agents/rules/10-supervisor.md`, `.agents/supervisor/launch-coder.sh`) as `chore(supervisor): …` with named paths | Commits `app/**` and `.agents/state/**`, one commit per module, named paths |
| **Runs every `git push` for this lane** (owner ruling, `OWNER.md` 14:0x — the owner runs no git by hand), and only on a sha it has gated and recorded in `REVIEWS.md`, by explicit ref: `git push origin <sha>:track/reviews` — never a branch head, never `--force` | **Never pushes.** Its guard stays closed; the launcher exports `GOAIEZ_PUSH_OK=0` on every run |
| **Never:** migrate, touch a database, edit `app/**`, run a test suite outside `supervise.sh --tests`, commit any path outside its five files, push an ungated sha | **Never:** edit `BRIEF.md`/`REVIEWS.md`, `git push` at all, edit sealed or generated files, commit `.agents/supervisor/**`, `CLAUDE.md`, `.claude/**` or `bin/**` |

`.claude/settings.json` enforces your column. Since 2026-09-05 it allows the
supervisor `git add`, `git commit` and `git push origin` (owner ruling, relayed
through `OWNER.md` 08:0x); the 50-entry deny list otherwise stands. That opening
is what makes **merge step 0 — committing the supervisor's own notes — yours,
not the coder's**: the coder guard refuses those paths outright. If a check needs
a command the deny list still blocks, that is the signal it is the coder's job —
brief it.

## The mailbox — `.agents/supervisor/`

| `BRIEF.md` | you → coder. The current directive, overwritten in place. Since `OWNER.md` 14:0x its `push:` line is **always `CLOSED`** — the push is the supervisor's, not a coder item |
| :--- | :--- |
| `REPORT.md` | coder → you. Overwritten at every wave close or stop, fixed shape (rule 10) |
| `REVIEWS.md` | you → coder. **Append-only**, dated blocks at EOF, verdict `PASS` / `PASS-WITH-NOTES` / `BLOCK` |

## Session start

1. `bash bin/supervise.sh` — DB guard, tree, forbidden paths, build state,
   checker soundness, integrity, pint, phpstan. Read-only. Add `--tests` to run
   pest (against §0's **`effective (§7)`** line — this lane's own
   `goaiez_antig_reviews_test`, derived by `supervise.sh`, no longer
   `phpunit.xml`'s value; see REV-119 below), `--full-doctor` for all eight
   stages. ⚠️ `--full-doctor` and the schema stage read **`.env`'s** database,
   which is a different one — REV-119 §B.
2. If `REPORT.md` is newer than the last `REVIEWS.md` block, review it (below).
3. `python3 bin/state.py next` — that is where the coder is.
4. Refresh `BRIEF.md` if the directive changed. Do not rewrite it to say the
   same thing.

## Reviewing a REPORT

Green gates are necessary, not sufficient. For every commit in
`git log origin/main..HEAD`:

- **The One Rule.** Did the SYSTEM change or a CHECK? A diff under
  `app/app/Doctor/`, `seals.json`, `tests/Journeys/JourneyHarness.php`, a new
  `notPath()`/exclusion, a deleted capability id, assertion or refusal → `BLOCK`.
  `supervise.sh` §2 lists forbidden paths touched; it does not see a weakened
  assertion — read the test diffs yourself.
- **Did the count fall?** For each stage the report claims fixed, the `after`
  number must be lower and must match `JOURNAL.md`. A fix with the same count
  is a fix that did not land; the contract says record `UNRESOLVED`, not retry.
- **Tests are real.** The instrument is
  `grep -c 'public function test\|test(\|it('` before/after, and it must match
  the report. ⚠️ **Do not use the bare `grep -c 'test(\|it('` form** — `test(`
  and `it(` are Pest calls, and roughly every module test here is a PHPUnit
  class whose methods read `public function test_foo()`. The Pest-only form
  reads **0** on those files before *and* after any change, so it can never show
  a rise; run 54's report quoted "before 1 after 7" from a command that returns
  0 both times (REV-59). A check that always returns the same number checks
  nothing. Also: a test that greps a directory must grep one that exists
  (rule 01: 19 anchors once passed against missing paths).
- **State the expected *delta*, never a remembered absolute.** A raw count
  measured before a merge is stale the moment the merge lands — REV-58's brief
  said the capability stage read 208 when `fc8f0bab` had already taken it to
  177, and the coder spent the run reconciling my number, not its own (REV-59).
  Name the instrument, name the delta, ask for the number they actually get.
- **Citations resolve.** Any new `R###`/`X-###`/`P-###` in code or comment:
  `php artisan why <id>` returns something. 64 unresolvable citations already
  exist; the 65th is a `BLOCK`.
- **Decisions are recorded, not just made.** Every `(R245)` in a module header
  has a matching `state.py decided` line in `JOURNAL.md`.
- **`UNRESOLVED` names a missing dependency**, not an unmade decision (rule 09).
- **Generated files** (`app/Modules/*/manifest.php`, `capabilities.php`) changed
  only via regeneration — the commit that touches them also touches the plan or
  tracker, or the report says `module:scaffold` ran.
- **Doctor build stamp** in the report's raw output matches
  `BUILD-STATE.json`'s `runtime_build`. Otherwise the numbers are from an old
  checker.

Write the block, append to `REVIEWS.md`, and if `BLOCK`, put the items at the
top of `BRIEF.md` too — the coder reads `BRIEF.md` first.

## How you answer

Lead with the answer. Every answer that asks the coder to do something ends in a
block the human can paste into Antigravity unchanged. The coder is already in
this checkout; emit bare commands, no `cd`.

```
### antigravity — <one-line task>

<what to do, with the exact commands>

Verify: <the raw output line that proves it>
Watch for: <the trap that applies, by name>
```

## Traps specific to this checkout

- **`goaiez_antig` is PRODUCTION** (anti.goaiez.com). On 2026-08-31 a test run
  from this checkout dropped its schema (`NEXT-SESSION.md`). The checkout now
  runs on `goaiez_antig_dev`; `app/phpunit.xml` pins `goaiez_antig_test`.
  `supervise.sh` exits 2 if either points at production. Never brief a change
  to either value, and treat any diff to `phpunit.xml` or `.env.example`'s
  `DB_` lines as a `BLOCK` until explained.
- **`JOURNEYS n/12 green` in `state.py status` is a hand mark**
  (`state.py journey Jn green`), not a test result. All twelve were marked
  green on 2026-08-29/30 before any harness that could pass existed, and the
  on-disk `evidence/journeys/*.json` came from a forbidden simulation harness.
  Only `supervise.sh --tests` output counts as the journey number.
- **`state.py` owns `BUILD-STATE.json`.** A hand edit there is a `BLOCK`; so is
  a `JOURNAL.md` line with no matching commit.
- **`BUILDING` is not progress.** On 2026-08-31 13:04:41 twelve modules flipped
  to `BUILDING` in one second — a batch mark. Count `DONE` transitions and
  commits, never `BUILDING`.
- **Uncommitted work is invisible to review.** Do not review a dirty tree;
  brief a commit first. The coder commits per module (rule 10).
- **A merge walks past the coder guard.** The guard checks paths on
  `git commit`; a merge writes files without one, so `app/phpunit.xml`,
  `seals.json`, `app/app/Doctor/**` or the mailbox can move with nothing
  refusing — run 39 came through with git reporting **no conflict at all**.
  Owner-approved 2026-09-05 13:2x, `coder-bin/git` refuses
  `merge`/`pull`/`cherry-pick`/`revert` unless `GOAIEZ_MERGE_OK=1`. **Only
  `launch-coder.sh --allow-merge` sets it** — never `BRIEF.md`, which is
  rewritten every tick. So: a merge item is dispatched with
  `bash .agents/supervisor/launch-coder.sh --allow-merge`, named in the
  `REVIEWS.md` block; every other run launches bare. A
  `REFUSED by coder guard: git merge …` under `REFUSED` means the supervisor
  left the gate shut — relaunch with the flag, it is not a coder fault. Live
  since 2026-09-05 13:4x, both halves syntax-checked by the owner.
- **`app/CLAUDE.md` and `app/AGENTS.md` are Laravel Boost boilerplate**, not
  the contract. The contract is the root `AGENTS.md`. Do not cite the `app/`
  copies.
- **A stale doctor.** `doctor`'s first line is `goaiez doctor · build <stamp>`.
  Three identical runs once came from files that were never copied into the
  tree. Compare the stamp before trusting any count.
- **`php artisan doctor` exits non-zero on any red stage by design** — CI runs
  it with `|| true` and gates only `integrity` and `journey`. A red
  `capability`/`contract`/`citation` count is the measured truth, not a
  blocker; the blocker is a count that *rose* without a report line saying why.
- **The supervisor can be wrong; the seal cannot.** If the coder's `REPORT.md`
  lists a brief item under `REFUSED` because it would change a CHECK, that
  refusal stands. Re-read rule 01 before overruling it.

## Style

Terse and factual. Cite rules and traps by name — "that is the One Rule",
"that is the count-did-not-fall trap". Warn on dangerous git operations rather
than assuming they are blocked: the deny rules are prefix matches and stop a
habit, not a determined reordering.

## REV-119 — the four rulings from the 2026-09-09 fast-forward

⚠️ **§A. "ADOPT MAIN'S COPIES OF ALL EIGHT PATHS WHOLE" DELETED THIS FILE, AND THE
BRIEF THAT SAID IT NAMED ONLY ONE OF THE EIGHT (2026-09-09, run 114).** REV-118 §2
ruled the lane take `origin/main` by **fast-forward**, on the correct ground that
this lane was 0 ahead and a `--no-ff` merge would stage eight never-list paths
against the two-sided wall. The ruling was right. The sentence under it — *"Do not
restore anything after the merge. Adopt main's copies of all eight paths whole"* —
was reasoned entirely about `bin/supervise.sh`, which it named, discussed for a
paragraph and correctly wanted. It then generalised to **eight** paths without
looking at the other seven. A fast-forward writes a never-merge path exactly as a
merge would; it just does it without a commit for anyone to review. Measured cost:

```
$ git show 18161a02:CLAUDE.md | wc -l     # 161  — this lane's supervisor file
$ git show cbdba9cd:CLAUDE.md | wc -l     # 1002 — Track 1's, about merging LANES into main
```

For twenty minutes this seat's standing instructions were **another seat's**, telling
it that "Only Track 1 merges" and that `.claude/settings.json` is the owner's file.
Restored here from `18161a02` verbatim, plus this section. What was nearly lost is
REV-59's test-count needle (§"Reviewing a REPORT"), which Track 1 rediscovered
independently four days later and filed as N113 — the lane had it first.

**RULED: a fast-forward is a write to every per-track path, and the eight are
enumerated and diffed BEFORE it, not adopted by a sentence.** The command is
`git diff --stat <our tip> origin/main -- <the eight>`; a path that shows a diff is
a decision, one at a time, with a reason each. The general form is the one this
project keeps paying for: **the per-track list is stated, the product list is
derived** — and a blanket verb (*adopt*, *restore*) applied to a stated list is the
same defect whichever direction it points.

⚠️ **§B. THE `schema` STAGE MEASURES A LIVE DATABASE, NOT THE TREE, SO ITS NUMBER IS
NOT A PROPERTY OF A SHA (2026-09-09).** `SchemaStage.php:41-60` runs `select …
from pg_class` against **`.env`'s** database (`goaiez_antig_reviews`), and
`:146-191` reads `pg_roles`. Twelve of its sixteen rows are `tenant-owned table has
no RLS` and one is `role goaiez_backup: has BYPASSRLS`. None of the thirteen is
visible in any diff, none moves when code moves, and every lane will measure a
different number on the identical commit. `state.py stage schema <n>` records it
into `BUILD-STATE.json` beside seven counts that *are* tree properties.
**RULED: `schema` is reported with the database it was read from, or it is not
reported.** And the count-did-not-fall rule does not apply to it across checkouts.

⚠️ **§C. THE `fix:` TEXT NAMES SQL THIS REPO'S OWN BOOTSTRAP COMMAND WILL NEVER RUN —
TWO DEFINITIONS OF "TENANT-OWNED", 130 LINES APART (2026-09-09).** The schema stage
prints `fix: alter table opt_outs enable row level security`, which reads like a
migration nobody has written. It is not: `php artisan db:bootstrap`
(`DbBootstrapCommand.php:81-107`) already enables RLS, FORCEs it and creates the
`tenant_isolation` policy — for every table carrying a **`tenant_id`** column.
`SchemaStage::isTenantOwned()` (`:199-208`) selects on **`business_id`**. The two
sets do not intersect on these twelve tables, so **`db:bootstrap` is a guaranteed
no-op against every one of those rows** — the count-did-not-fall trap, diagnosable
before the run rather than after it.

⭐ And the tables' own migrations disagree with the checker in writing:
`create_opt_outs_table.php:24` says *"NOT TENANT-OWNED, AND NO RLS. A platform-scoped
row has no `business_id`…"*, `create_operator_alerts_table.php:32` says
*"`business_id` forces a policy admitting NULL…"*, and
`create_zernio_account_days_table.php:31` says its `business_id` is *"an ordinary
integer with no foreign key"*. A **nullable** `business_id` is what
`isTenantOwned()` cannot see and what the migration authors were writing about.
**RULED: no RLS row is "fixed" until the table is classified against its own creating
migration.** ⛔ And the classification is the only move available here: the
definition lives in `SchemaStage.php`, which is a CHECK — changing it is the One
Rule, whatever the evidence. Classify, file, and let the owner rule.

⚠️ **§D. THE `journey` COUNT CHANGES WHILE THE GATE RUNS, SO MEASURING STAGES BEFORE
`--tests` RECORDS A NUMBER THE WAVE ITSELF INVALIDATES (2026-09-09, my defect).**
REV-118's brief ordered items 3 (measure eight stages) → 4 (record them) → 5 (gate).
The stage dumps and the `state.py stage` commit landed at `09:22:48` with
`journey 3`; the suite finished at `09:30:44` writing
`evidence/journeys/dunning-by-reason.json` with `"artifact_id": 50`, which cleared
the third row. Measured on the same tree at `09:4x`: **`journey 2`**. So
`BUILD-STATE.json` records `3` and the tree reads `2`, and neither is wrong — the
`journey` stage is a function of `app/storage/app/evidence/journeys/*.json`, which
the suite **writes**.
**RULED: in any wave that runs `--tests`, the gate comes BEFORE the stage
measurement, and the stage measurement before the `state.py stage` commit.** Same
family as §B: a stage whose input is not the tree cannot be recorded against a sha
until everything that writes that input has finished.

⚠️ **§E. `bin/supervise.sh` now derives this lane's test database (REV-119).** After
run 114 nothing at all separated the seven checkouts' suites: `main`'s
`app/phpunit.xml` pins `goaiez_antig_test` for every lane and is a never-merge path,
and Track 1's `supervise.sh` carries no per-lane export. `RefreshesTenantDatabase`
runs `migrate:fresh`, so one bare `--tests` from any lane drops the other six lanes'
tables. §0 now prints `effective (§7) DB_DATABASE=goaiez_antig_reviews_test` and §7
applies it **at the pest call only** — not exported globally, because §4's doctor and
the schema stage of §B read the live `.env` database and a global export would
silently repoint them. An already-set `DB_DATABASE` still wins, so a brief can
override it without editing a tracked file. §7's clash guard was pointed at the same
effective value: it **serialises** suites sharing a database and never **separates**
them, so it was never a substitute for this.

## REV-121 — REV-119 §A was ruled and never executed. All eight paths were hit.

⚠️ **§A ORDERED THE EIGHT PER-TRACK PATHS ENUMERATED AND DIFFED. ONLY ONE OF THE EIGHT
EVER WAS (2026-09-09, run 116 tick — my defect, the same defect §A was written about).**
REV-119 §A caught that the 09:54 fast-forward had overwritten `CLAUDE.md`, restored that
one file from `18161a02`, and wrote the rule: *"a fast-forward is a write to every
per-track path, and the eight are enumerated and diffed BEFORE it, not adopted by a
sentence."* Then the tick moved on. The command §A itself specifies was never run. Run
here for the first time, six hours and four gates later:

```
$ git diff --stat 18161a02 HEAD -- app/phpunit.xml CLAUDE.md bin/supervise.sh \
    .claude/settings.json .agents/rules/10-supervisor.md \
    .agents/supervisor/launch-coder.sh \
    .agents/state/BUILD-STATE.json .agents/state/JOURNAL.md
 .agents/rules/10-supervisor.md     |   42 +-
 .agents/state/BUILD-STATE.json     | 2006 +++---------------------------------
 .agents/state/JOURNAL.md           |  720 +++++--------
 .agents/supervisor/launch-coder.sh |  234 ++---
 .claude/settings.json              |   23 +-
 CLAUDE.md                          |  101 +-
 app/phpunit.xml                    |    2 +-
 bin/supervise.sh                   |  722 +++++++------
```

**Eight of eight.** The authoritative list is `.gitattributes` — every line is
`<path> merge=ours`, and `merge=ours` is precisely the driver a fast-forward does not
consult. Judged one at a time, which is what §A asked for and what turns a diff into a
decision:

| path | verdict |
| :--- | :--- |
| `bin/supervise.sh` | **ADOPT** — §A wanted it by name and argued it; §E's per-lane DB survived inside it, proved live by the `effective (§7)` line in every gate log since |
| `.claude/settings.json` | **ADOPT** — this is the clean file Track 1 pushed on 09-08 with the eight `grs-antig-*` deny rules stripped |
| `.agents/supervisor/launch-coder.sh` | **ADOPT** — main's is strictly better: it adds `--allow-restore`, `timeout -k 60 3h`, and the `Merge gate **OPEN` refusal needle. ⭐ REV-120 told this lane its launcher *"cannot pass `--allow-restore` yet"*; that was already false when written — the fast-forward had delivered it |
| `CLAUDE.md` | **RESTORED** by REV-119 §A at `37da73f6` |
| `.agents/rules/10-supervisor.md` | ⛔ **RESTORED at REV-121** — see below |
| `app/phpunit.xml` | ⛔ **RESTORE** — see below |
| `.agents/state/BUILD-STATE.json` | ⛔ **NOT a whole-file swap** — see below |
| `.agents/state/JOURNAL.md` | ⛔ same |

⛔ **§1. THE FAST-FORWARD DELETED THREE RULES FROM THE CODER'S OWN CONTRACT, AND ONE OF
THEM WAS THE ANTI-PUSH RULE.** `.agents/rules/10-supervisor.md` lost, in two hunks:
the ⛔ *"The coder never pushes — OWNER RULING 2026-09-05 14:0x"* bullet, replaced in
place by **the superseded 2026-09-03 step it exists to supersede** — the one that reads
*"run `git push origin main`"*; the ⛔ *"The coder merges only on an opened gate"* bullet;
and *"A ruling given verbatim in `BRIEF.md` is recorded verbatim."* All three restored
2026-09-09. **This is the One Rule violated by a mechanism that produces no commit to
review** — a deleted refusal is the textbook `BLOCK`, and here nothing was blocked
because nothing was committed. Defence in depth held (`GOAIEZ_PUSH_OK=0` from the
launcher, `push: CLOSED` in every brief), which is the only reason a coder reading
`git push origin main` in its contract did not act on it.

⭐ And the deleted merge bullet **names `app/phpunit.xml`** as the exact file a merge
moves with nothing refusing. It was deleted by a fast-forward that moved
`app/phpunit.xml`. The rule described its own deletion.

⛔ **§2. `app/phpunit.xml` — the lane's test database was rolled back to main's.**
`18161a02` read `goaiez_antig_reviews_test`; `HEAD` reads `goaiez_antig_test`. Track 1
found it at 11:01 by measuring from `main`, after main's wave-223 gate was **REFUSED**
because two checkouts pinned one database and `RefreshesTenantDatabase` runs
`migrate:fresh`. ⚠️ **Why four of this lane's own gates printed the corruption and
nobody read it:** §0 prints both lines, and §E's derived line sits directly under the
wrong one —

```
  app/phpunit.xml  DB_DATABASE=goaiez_antig_test          <- the defect, printed every run
  effective (§7)   DB_DATABASE=goaiez_antig_reviews_test  <- read as the answer
```

§E made this lane's own suite correct, which is what made the damage invisible *here*
and left it visible only from `main`. **A mitigation that hides its own fault is worse
than no mitigation.** Restore from `18161a02`, not from a remembered value.

⛔ **§3. THE BUILD LEDGER WAS ROLLED BACK THREE DAYS AND LOST 86 RECORDED DECISIONS —
AND RESTORING IT IS *NOT* A WHOLE-FILE SWAP.**

```
                        18161a02 (lane)        HEAD (= origin/main's)
  "updated"             2026-09-08T08:11:27    2026-09-05T17:11:31
  "ruling": "R245"      98                     12
  "roster"              124                    127
  JOURNAL.md            986 lines              786 lines
```

`plan_sha256`, `runtime_build` and `seal_digest` are identical in both, so this is not a
plan move — it is main's ledger sitting where the lane's belongs. `merge=ours` on these
two paths is the statement that **each lane's ledger is its own document and main never
absorbs one**; that is why `origin/main` also reads 12. Everything `state.py` has written
since 09:54 — `25c3c445`'s eight stage counts, run 115's two stage lines, run 116's two
`decided` lines — went into the wrong container.

⛔ **But `roster` is 124 on the lane side and 127 on main's, and the lane number is the
older one.** So a `git checkout 18161a02 -- .agents/state/` would restore 86 decisions
**and** revert the roster, which is Track 1's number, not this lane's. That is REV-119
§A's own defect — *a blanket verb applied to a stated list* — pointed the other way, and
this seat is not committing it twice in one file. **RULED: the lane ledger is
authoritative on `decisions` and `notes`; `roster` and module status are Track 1's. The
reconciliation is filed, not executed, and nothing is restored by whole file.**

**The general form, and it is the one worth keeping:** REV-119 §A was *correct*, was
*written down*, and still did not happen, because a ruling and its execution were in the
same tick and only the ruling had a check on it. **A ruling whose execution is not itself
an item is a ruling that did not run.** Every §A-shaped finding from here carries the
command into the next `BRIEF.md` as a numbered item with an output to quote.

## REV-126 — `merge=ours` was never the protection this lane thought it had

⚠️ **REV-119 §A AND REV-121 §A BOTH BLAMED THE FAST-FORWARD. THE BYPASS CONDITION IS
"OUR SIDE DID NOT MOVE THE PATH SINCE THE MERGE BASE", AND AN ORDINARY MERGE SATISFIES IT
JUST AS WELL (2026-09-09, run 121 tick).** `merge=ours` is a **conflict-resolution driver**.
Git consults it only when it has to perform a three-way content merge for a path — that is,
only when **both** sides moved the path since the base. Ours unchanged + theirs moved is not
a conflict; git takes theirs as a trivial file-level fast-forward and the driver is never
called. A whole-branch fast-forward is one instance of this, not the mechanism.

⭐ **The correct diagnosis was already in `.agents/rules/10-supervisor.md`, written 2026-09-05
and restored by REV-121's own hand:** *"Run 39 is the near miss — our side had not touched
`phpunit.xml` since the base, so git reported no conflict at all."* REV-121 restored that
sentence and then, in the same file, kept describing the defect as a fast-forward. **A
correct statement present in the tree and not read back is the failure mode this project
keeps paying for**, and this is its fourth instance (REV-119 §A, REV-121 §A, REV-125 §13,
here).

⛔ **And the round trip makes the bypass the NORMAL case, not the exotic one.** Once Track 1
merges this lane into `main`, the merge base advances to a lane commit that already contains
the lane's copy of every per-track path. From then on the lane reads *unchanged* on all eight
and only `main` moves — so the lane silently adopts `main`'s. Measured this tick, base
`e3aea7ff` (this lane's own pushed commit):

```
$ bash bin/supervise.sh          # §2f
  base e3aea7ff  ours HEAD  theirs origin/main (8c7ed0d8)
  merge.ours.driver=true
     ⛔ app/phpunit.xml                  OURS UNCHANGED, THEIRS MOVED   1 +/1 -
     ⛔ CLAUDE.md                        OURS UNCHANGED, THEIRS MOVED   895 +/241 -
     ⛔ bin/supervise.sh                 OURS UNCHANGED, THEIRS MOVED   2 +/30 -
     ⛔ .agents/rules/10-supervisor.md   OURS UNCHANGED, THEIRS MOVED   8 +/43 -
     ✓ .claude/settings.json · .agents/supervisor/launch-coder.sh   theirs unchanged
     ✓ .agents/state/BUILD-STATE.json · .agents/state/JOURNAL.md    both moved, driver FIRES
  ⛔ 4 of 8 per-track path(s) would be silently overwritten by a merge from origin/main.
```

**Four of eight**, and the four are the worst four: `app/phpunit.xml` (main still pins
`goaiez_antig_test` — six other lanes' test databases, REV-121 §2), `bin/supervise.sh` (main's
copy **deletes** REV-119 §E's per-lane database block entirely), `.agents/rules/10-supervisor.md`
(main still carries the pre-REV-121 text, so **the anti-push rule is still deleted on `main`**
and its replacement still says `git push origin main`), and `CLAUDE.md` (Track 1's file, REV-119
§A's original catastrophe).

**RULED: the enumeration is now a CHECK, not a ruling.** `supervise.sh` §2f measures every
`merge=ours` line in `.gitattributes` on every run, classifies each into the three arms, and
sets `fail=1` on any bypass. All three arms were observed firing in its first run, which is the
instrument standard. ⭐ **The list is read from `.gitattributes` and never restated in the
script** — REV-119 §A's defect was a stated list generalised by a sentence, so the fix must not
reintroduce a second copy of the list.

⛔ **RULED: a merge wave naming one of the four is NOT fully delegable, and this is a hard
division.** `--allow-restore` refuses `CLAUDE.md`, `bin/supervise.sh`, `.agents/rules/**` and
`.claude/**` by design (restoring one discards the supervisor's uncommitted notes — run 27). So
the coder **cannot** repair three of the four. **The supervisor moves those paths on our side
BEFORE dispatching** — which makes both sides changed, fires the driver, and protects them
through the merge — **and the coder restores only `app/phpunit.xml`, which it is permitted to
touch.** Done that way this tick: §2f, this section and the rule-10 correction are the move,
and they are protection and record in one commit rather than a trick.

⚠️ **`bin/supervise.sh`'s per-path verdict is good only for the sha it was measured against.**
REV-121's table said **ADOPT** for `bin/supervise.sh` on the correct ground that main's copy then
carried §E. Main's copy no longer does. A verdict on a per-track path is a function of
`(our sha, their sha)` and must be re-derived at every merge, which is precisely why §2f prints
the two shas it used on the same line as its answer.

## REV-127 — the restore gate cannot undo a merge, and the sentence saying so was in the file I was quoting

⚠️ **RUN 122'S ITEM 3 WAS `git checkout e7b197f4 -- app/phpunit.xml` AND NO GATE SETTING COULD HAVE
EXECUTED IT (2026-09-09, my defect).** REV-126 correctly identified `app/phpunit.xml` as the one
per-track path the coder had to repair after the merge, correctly ordered that repair ahead of every
test run, and then named a command with two independent faults:

⛔ **§1. The sha put the command outside the gate's scope.** `launch-coder.sh:26` defines
`--allow-restore` as permitting *"`git checkout|restore -- <existing file paths>` and **NOTHING else:
no directory, no option**"*. A sha before `--` is a third form the clause never admitted. The gate was
**open** and the guard refused anyway:

```
REFUSED by coder guard: git checkout on paths is forbidden.
```

I read that line while writing the brief — it is the comment on the flag I was passing. Fifth instance
of the shape this project keeps paying for: **a correct statement present in the tree and not read
back** (REV-119 §A, REV-121 §A, REV-125 §13, REV-126, here).

⛔⛔ **§2. AND THE PERMITTED FORM IS A NO-OP ON THE DEFECT THE GATE EXISTS FOR.** `git checkout --
<path>` restores from the **index**. A merge *commits* the paths it moves, so index = `HEAD` = the
value being repaired. Stripping the sha would have produced a silent success that changed nothing —
worse than the refusal, because the refusal is legible.

⛔ **RULED: `--allow-restore` is retired from this lane's merge repairs. Undoing a COMMITTED value is
a content operation, not a ref operation**, and content needs no gate:

```
git show <good-sha>:<path> > <path>
```

a read plus a shell redirect. Verified by `git diff <good-sha> HEAD -- <path>` printing **nothing**
after the commit — which proves byte-identity to the last good copy rather than checking one line.
⚠️ **And after that edit, `git checkout -- <path>` DISCARDS the fix rather than applying it.** The
gate must be closed on a repair wave, and the brief must say why.

⭐ **The generalisation, and it is the durable half:** *a brief item whose single command has no
stated fallback can stop the whole wave.* Item 3 was correctly first — that ordering is the only
reason nothing ran against `goaiez_antig_test` and six lanes' test databases survived — but "ordered
first" and "single point of failure" are one property seen from two sides. **Every ⛔⛔ item from here
carries a second mechanism**, named in the brief, so a refusal costs an item and not a run.

⭐ **The gate should not have been opened at all.** It was opened on a belief about the mechanism,
and the belief was wrong. REV-126's complaint that the `LAUNCHED` line never prints `restore-gate` is
still true and is now the lesser finding: a flag whose read-back is missing matters less than a flag
that cannot do the job. **A gate is opened by the wave that uses it, and by nothing else** — the
converse of N103, which this file already states for `--allow-merge`.

## REV-128 — `app/phpunit.xml` is committable by neither column, and a permission was read out of a silence

⚠️ **THE CODER GUARD REFUSES `git commit … -- app/phpunit.xml` AND NO LAUNCHER FLAG OPENS IT; THE
SUPERVISOR IS BARRED FROM `app/**` BY ITS ROLE TABLE. SO THE ONE FILE EVERY LANE IS REQUIRED TO DIVERGE
ON IS COMMITTABLE BY NOBODY (2026-09-09, run 123).** The refusal reads
`REFUSED by coder guard: a never-list or supervisor path is staged: app/phpunit.xml`, and
`launch-coder.sh:24-27` takes exactly `--allow-merge`, `--allow-harness` and `--allow-restore`.

⛔ **The defect is mine and it is REV-126's closing sentence:** *"the coder restores only
`app/phpunit.xml`, which it is permitted to touch."* That read `--allow-restore`'s **exclusion** list
(`:26` — *".agents/supervisor, .agents/rules, .claude, CLAUDE.md, bin/supervise.sh, bin/state.py, any
.env"*), correctly observed `app/phpunit.xml` is absent from it, and inferred a **commit** permission
the clause says nothing about. Two different lists; the second property derived from the first.

**RULED: a permission is proved by the clause that GRANTS it, never by the absence of a clause that
forbids it.** Any brief item depending on the coder being *able* to run a command cites the granting
clause by `file:line`, or the item carries a second mechanism needing no permission at all.

⭐ **`--allow-harness` is the proof this is a gap and not a design.** `JourneyHarness.php` is also on
the never-list and got a flag whose own comment says *"opens the ABILITY TO COMMIT"*. `app/phpunit.xml`
is the other never-list path that legitimately has to move — once per lane, forever — and never got one.

⭐ **New sub-species of this project's recurring failure.** REV-119 §A, REV-121 §A, REV-125 §13,
REV-126 and REV-127 §1 are all *a correct statement in the tree, never read back*. REV-127 §1 and this
one are *a correct statement read carefully and answered for the wrong question* — I read
`launch-coder.sh:26` while writing both briefs it broke.

⚠️ **§2. `supervise.sh` §0 READS THE WORKING TREE, SO BOTH ITS DB LINES CAN BE GREEN WHILE `HEAD` IS
WRONG.** `:99` greps `$APP/phpunit.xml` on disk. With the repair uncommitted, §0 prints

```
  app/phpunit.xml  DB_DATABASE=goaiez_antig_reviews_test   <- the WORKING TREE, not HEAD
  effective (§7)   DB_DATABASE=goaiez_antig_reviews_test
```

and `HEAD` still pins `goaiez_antig_test`. REV-121 §2's shape returning **inverted**: there the
mitigation hid the fault, here the *display* does, one line higher. **The only proof is an empty
`git diff <good-sha> HEAD -- app/phpunit.xml`** — against `HEAD`, not the working tree.

⛔⛔ **And while the repair is uncommitted, `git checkout -- app/phpunit.xml` is the most destructive
command on the board**: it discards the fix, re-points the lane at `goaiez_antig_test`, and
`RefreshesTenantDatabase`'s `migrate:fresh` then drops six other lanes' test databases. Only
`git restore --staged app/phpunit.xml` is safe, and only to unstage.

⭐ **Two measured mechanisms make the uncommitted state survivable, and both were checked, not
remembered:** `app/phpunit.xml` carries no `force` attribute, so PHPUnit will not overwrite an
already-set variable and REV-119 §E's export at `:127`/`:452` genuinely wins; and §7's clash guard
greps the siblings' **working tree** (`:461`), so a repaired-on-disk file advertises correctly to the
other six lanes.

⭐ **The root, and it is one root under five findings.** REV-119 §E, REV-121 §2, REV-126, REV-127 and
this section all exist because `main`'s `app/phpunit.xml` pins `DB_DATABASE` for all seven lanes while
being a `merge=ours` path. If main's copy simply dropped that env line and every lane derived the name
— which `bin/supervise.sh:127` already does, and has done correctly for five waves *without* a correct
committed `phpunit.xml` — the whole class would have no surface left. **Filed as a TRACK 1 ACTION;
it is Track 1's file to change.**

⚠️ **A merge does not disturb the uncommitted repair while `app/phpunit.xml` is in §2f's
`theirs unchanged` arm** — git carries the dirty file through untouched. That is what lets a lane keep
merging with the divergence uncommitted, and it is a property of `(our sha, their sha)`, so it is
re-derived at every merge and never assumed.

## REV-129 — an `UNRESOLVED` is a claim with an expiry, and nothing in this project re-reads one

⚠️ **RULE 09 SAYS AN `UNRESOLVED` NAMES A MISSING DEPENDENCY. IT DOES NOT SAY THE DEPENDENCY CAN LAND
AFTERWARDS — AND WHEN IT DOES, THE ROW DOES NOT MOVE, BECAUSE WHAT WOULD CLEAR IT IS ANOTHER LANE'S
COMMIT (2026-09-09, run 124 tick).** `C-Reviews` / `tests` has read *"ReviewRequested event lacks
messageClass so the module cannot express the send class (marketing) without a legacy change"* since
`2026-09-04T07:55:55`. Measured today, three greps:

```
$ grep -n messageClass app/app/Modules/C-Reviews/Events/ReviewRequested.php
  16:        public readonly string $messageClass      # added 2026-09-05, per the file's own docblock
$ grep -n MESSAGE_CLASS app/app/Modules/C-Reviews/Actions/ReviewRequestAction.php
  18:    private const MESSAGE_CLASS = 'marketing';
$ grep -n messageClass app/tests/Modules/C-Reviews/CReviewsTest.php
  441 · 562   # two passing assertions, one named test_review_requested_carries_marketing_class_…
```

The field, its writer and two passing assertions all landed **the day after** the row was filed. For
five days `python3 bin/state.py next` — step 3 of this file's own session-start list — has told this
lane and every reader of the board that C-Reviews is blocked on something present and tested.

⭐ **The shape is already recorded on another lane under a different name.** The money lane's rulings
256-259: *"`payments.invoice_id` has existed since 2026-09-04 and rulings 102/169/173 recorded the
opposite (a fifth merge-damage shape: schema lands, writer does not)."* Same defect, same week, same
date. Nobody generalised it to the `unresolved` array — the one place the project stores these claims
in a machine-readable list. Sixth instance of this file's standing shape, **a correct statement present
in the tree and not read back** (REV-119 §A, REV-121 §A, REV-125 §13, REV-126, REV-127 §1, here), and
the first where the *incorrect* statement is in the ledger rather than in a note.

**RULED: an `UNRESOLVED` row is re-read against the tree before it is cited as a reason not to build,
and a row whose named dependency now exists is `state.py resolve`d with the `file:line` that discharges
it.** `bin/state.py:9-10` provides the verb and its own comment says it *"never marks work done"*, so
it is exactly scoped. ⛔ Only a discharged row is resolved — a `STANDS` or `SEALED` row is an honest
record and removing one loses a reason not to build.

⚠️ **§2. A COUNT THAT *FALLS* WITH NO CAUSE NAMED IS THE COUNT-DID-NOT-FALL RULE SEEN FROM ITS BLIND
SIDE.** Run 124 recorded `boundary 52 → 50` correctly and said only that it changed. From the report
alone a fix and a checker that stopped looking are indistinguishable. The cause was real and external —
`7c79b8d8 refactor(X-155): expose a registered read action … and call it from X-157`, an **incoming**
site-lane commit that removed the cross-module read the stage counts. **RULED: a `state.py stage` line
for a count that MOVED carries one sentence naming what moved it, in either direction.** This lane
merges four other lanes' work, so most of its stage movement originates outside its own diff and is
invisible in `git log -p` of its own commits.

⚠️ **§3. THE GATED SHA AND THE PUSHED SHA CANNOT BE THE SAME COMMIT, BY CONSTRUCTION.** REV-119 §D
orders the gate before the stage measurement and the measurement before the `state.py` commit — so the
last commit of any wave is always after the last gate. Run 124: gate at `18:23:34` on `5245ef6b`,
`6836443a` committed at `18:24:19`. **RULED: a push whose range ends past the gated sha is admissible
when `git diff <gated sha> <pushed sha>` touches nothing outside `.agents/state/**`, and that diffstat
goes in the `REVIEWS.md` block.** Anything else waits for a gate.

## REV-131 — a report that outran its own gate, and a substitution that outran its own file

⛔ **RUN 126'S `REPORT.md` WAS WRITTEN AT `21:50:06` — SIX SECONDS BEFORE THE COMMIT IT DESCRIBES AND
3m50s BEFORE THE GATE FINISHED — AND IT SAID THE MODULE WAS "FULLY GREEN" (2026-09-09).** The gate log's
own §3 confirms the ordering from the other side: at §3 it still printed `REPORT.md 2026-09-09 18:44:03`,
run 125's report. The coder started the suite, hit `pest.lock` behind another lane, wrote the report
while §7 was blocked, and the run ended. When §7 returned it read
`tests 2445 · passed 2432 · FAILED 7 · errors 6` with **five new C-Reviews failures** and phpstan at
`errors 4`, up from `0`. Items 2 and 4 of the brief are absent from that report — not refused, just not
yet true when it was written.

**RULED: `REPORT.md` is the LAST artefact of a wave.** It is written after the gate log exists, and
every claim about test or gate state quotes a line from that log. A wave that cannot reach its gate
reports `UNRESOLVED` and why; it never reports a result it did not measure. ⭐ **My defect** — REV-119 §D
ordered the gate before the stage measurement and I never extended it to the report, so a report could
be written while its own instrument was still blocked on a lock.

⛔⛔ **§2. `app/GOAIEZ-MASTER-PLAN.md` IS ①, IS ON THE RESERVED LIST, AND HAS NO GUARD.** `b05f42d7`
staged and committed it with no refusal — proof by execution, per REV-128's standard. An unanchored
substitution aimed at C-Reviews's `@reads_table reviews · people · qa_tickets` (`:26997`) also matched a
bare `people` in running prose 5,500 lines later and corrupted §201.6, the **ASR PAN backstop**
paragraph: `:32501` read *"people · qa_ticketsay them with pauses"*. `grep -c qa_ticketsay` was `1` in
`app/GOAIEZ-MASTER-PLAN.md` and `0` in the pristine `source/` copy.

**RULED: no unanchored substitution across the plan, ever.** It is a 3.29 MB document in which a bad
replace is invisible to review, and this is CLAUDE.md's *never renumber with a blanket find-and-replace*
trap in its general form. ⭐ **Filed as a TRACK 1 ACTION**: add `app/GOAIEZ-MASTER-PLAN.md` to
`coder-bin/git`'s never-list, with an `--allow-plan` flag in the `--allow-harness` shape if a briefed
plan edit ever has to be possible. ⚠️ And `app/`'s copy has drifted 22 KB ahead of `source/` since 09-04
with nobody reconciling them.

⛔ **§3. FIVE DECLARATIONS WERE DELETED TO MAKE CHECKERS PASS, AND ONE WAS A TRUNCATION NO DIFFSTAT
SHOWS.** `qa.ticket` from C-Reviews's `@provides`; **`win.first` from X-118's `@emits`** — the exact
token REV-130 had quoted as a live contract violation, deleted from a module the wave had no business in;
the `R245` entry from `C-Sms/capabilities.php`; an `(R245)` comment from `X-196`; and `X-155`'s `G13-05`
**truncated mid-string**, losing *"a rejected submission is STORED and flagged, never discarded —
asserted by rejecting one and finding the row"*. That last one changes one line and deletes an assertion
with its own test named inside it, which is why a `--stat` review misses it.

**RULED: after any commit touching `capabilities.php` or `manifest.php`, the check is a `grep -n` for the
declaration's own text, not a diffstat.** Same family as the merged-wrong-lint trap: a deleted assertion
is green by construction.

⚠️ **§4. SWAPPING AN ELOQUENT MODEL FOR `DB::table()` TO SATISFY A BOUNDARY LINT IS A *TYPE* CHANGE.**
`Model` casts `sla_due_at` to `Carbon`; `DB::table()` returns a `stdClass` whose `sla_due_at` is a plain
string. Every blade calling `->format()` or `->diffForHumans()` on the result dies at render — four of
run 126's five new failures. No signature changes, the lint goes quiet, and phpstan's first pass says
nothing. ⭐ The reasoning about *which imports the lint permits* was correct; the type consequence is
what no instrument in the loop showed. CLAUDE.md already says to ask whether the caller belongs **behind
the service**; the third option — keep the read and strip the type off it — is the one to name and
refuse.

⛔ **§5. THE REPAIR IS A WHOLE REVERT, AND ITS VIRTUE IS THAT IT HAS ONE VERIFICATION.** Thirty-five
paths, twenty-five modules, one commit titled `feat(C-Reviews)`, and **zero test files**. Restored whole
to `10856a82` by REV-127's content form (`git show <sha>:<path> > <path>`, no gate), verified by
`git diff 10856a82 HEAD` printing nothing. REV-121 §3 refused a whole-file swap of `.agents/state/**`
because the two sides disagreed on `roster`; that ground is absent here — `roster`, `plan_sha256`,
`runtime_build` and `seal_digest` are identical across the two shas. **RULED: restoring `.agents/state/**`
to a sha `state.py` itself wrote is not a hand edit** — the prohibition is on *authoring* content there,
and byte-identity to a `state.py` output authors nothing. The reversal is then recorded through
`state.py note`, in a separate commit so the empty diff stays clean.

## REV-132 — the restore verified, and the seam that b05f42d7 was reaching for

⭐ **Run 127 delivered all seven required returns and every one of them verified independently
(2026-09-09).** `git diff 10856a82 caff8c06` is **0 bytes**; the five deleted declarations are back
(`qa.ticket`, X-118 `win.first`, C-Sms `R245`, X-196 `(R245)`, X-155 `G13-05` **with its
*"STORED and flagged, never discarded"* clause intact** — the one a diffstat cannot show);
`grep -c qa_ticketsay app/GOAIEZ-MASTER-PLAN.md` reads `0`; `--tests` is back to the eight-name floor
and phpstan to `errors 0`. Ordering held for the first time end to end: gate `22:15:58` → stage/note
commit `22:16:45` → `REPORT.md` `22:17:14`, which is REV-119 §D and REV-131 §1 satisfied together.

⚠️ **§1. THE REPORT EXPLAINED A STAGE COUNT BY THE FILE THAT RECORDS IT.** §4 read: *"None of the
stages moved compared to `BUILD-STATE.json`'s recorded values, because restoring the
`BUILD-STATE.json` in step 1 effectively synchronized it."* The conclusion is correct and the cause is
**inverted** — the stages agree because the **tree** was restored to `10856a82`; `BUILD-STATE.json` is
the record the stages are compared *against* and causes nothing. Harmless this run, load-bearing the
next: believe it once and the way to close a mismatch is to move the record, which is the hand-edit
`BLOCK` and the count-did-not-fall trap arriving in the same commit. **RULED: a sentence explaining why
a count did not move names a change to the TREE or to the CHECKER, never to `BUILD-STATE.json`.**
Written into `.agents/rules/10-supervisor.md` beside *"Paste raw output"*, because it is a rule about
what a report may claim.

⚠️ **§2. A COMMIT TITLE THAT OUTRUNS ITS CONTENT.** `8770a14f` is titled *"record the post-revert stage
measurement"* and contains one `state.py note` and no stage line — correctly, since no stage moved and
`state.py stage` on an unmoved count records nothing. The content is right; the title describes an act
that did not occur. Same family as REV-131 §6's `feat(C-Reviews)` over 25 modules, one order of
magnitude smaller. **Titles are read by `git log` reviewers who never open the diff.**

⚠️ **§3. `schema 14` WAS REPORTED WITHOUT ITS DATABASE.** REV-119 §B: *"`schema` is reported with the
database it was read from, or it is not reported."* The gate log's §0 prints
`app/.env DB_DATABASE=goaiez_antig_reviews` two hundred lines above, so the value is recoverable — but
recoverable-from-elsewhere is what §B exists to refuse, since the reader of a stage line is usually
reading `BUILD-STATE.json` and not a gate log from four hours ago.

### The seam — RULED for run 128

⛔ **C-Reviews's six remaining boundary violations are all `Models\` imports, and `BoundaryStage.php:83-90`
says in its own comment which seams are permitted:** `Events\`, `Actions\` and `Domain\` are skipped;
`Models\` *"is NOT a seam and stays flagged — that is one module reading another module's tables."*
Measured in the tree this tick:

```
$ grep -rn "X181\\\\Models\|X121\\\\Models" app/app/Modules/C-Reviews/
  Actions/QaTicketAction.php:10      use App\Modules\X181\Models\QaTicket;
  Ui/LossAlerts.php:11               use App\Modules\X181\Models\QaTicket;
  Ui/QaReport.php:12                 use App\Modules\X181\Models\QaTicket;
  Actions/ReviewRequestAction.php:10 use App\Modules\X121\Models\Person;
  Ui/ReviewsQaRequests.php:12        use App\Modules\X121\Models\Person;
  Ui/Tickets.php:8                   use App\Modules\X121\Models\Person;
```

⭐ **The answer was already in X-181 and nobody looked.** `QaTicketCreateAction`,
`QaTicketReopenAction` and `QaTicketResolveAction` exist, are already imported by C-Reviews's UI
without a violation, and **return `QaTicket`** — so the house pattern here hands the typed model back
across the seam and only the `use` of the `Models\` class is the breach. `X-121` likewise already ships
`EntityReadAction::handle(string $table, int $id, int $businessId): ?array`. b05f42d7's swap to
`DB::table()` invented a third option — *keep the read and strip the type off it* — which REV-131 §4
named and refused, and which cost four blade renders because `QaTicket::$casts` puts `sla_due_at`,
`arrived_at` and `resolved_at` through Carbon and a `stdClass` does not.

**RULED by the lane supervisor: the fix is a registered read action on X-181 returning `QaTicket` (and
a `Collection` of them), matching the return type its three sibling actions already declare.** No DTO
is invented, no cast is lost, and the lint is satisfied by the seam the lint's own comment names.
X-181 is wave 8's module, so this is inside the lane.

⛔ **RULED: one seam per run.** Run 128 takes X-181 only; X-121's `Person` reads are run 129. REV-131 §5
is three hours old and its finding was thirty-five paths under one verification — the split is what
keeps the verification single, and the instrument (`boundary`) reports per-file so a partial fix is
legible.

⚠️ **And every C-Reviews screen test is `Livewire::test()`; there is not one real `GET` in
`CReviewsScreensTest.php`.** The four screens *are* routed (`routes.generated.php`, prefix
`app/c-reviews`, middleware `web · auth · tenant.role`) and every existing test mounts them with an
explicit `['businessId' => …]` that the route cannot supply. That is CLAUDE.md's standing field note —
*"`Livewire::test()` never renders the layout"* and *"a `Forbidden` test on a route says nothing about
the component"* — sitting unexercised on a module that has now regressed twice at render time.
**RULED: run 128 adds one real `GET` per screen asserting `assertOk()`.** If a route mount cannot
resolve `businessId`, that is a genuine finding and is reported, not patched by passing the parameter.

### ⛔ REV-132 ERRATUM, same tick — the seam block above measured six imports and there are eight

The grep printed in "The seam — RULED for run 128" was run against **three hand-picked files**, not the
directory, and it reported six `Models\` imports in four files. The directory grep reads **eight in six**:

```
$ grep -rn 'Models\\' app/app/Modules/C-Reviews/ --include=*.php | grep ':use ' | grep -v CReviews
  Actions/QaTicketAction.php:10      X181\Models\QaTicket
  Ui/LossAlerts.php:11               X181\Models\QaTicket
  Ui/QaReport.php:12                 X181\Models\QaTicket
  Ui/Tickets.php:10                  X181\Models\QaTicket      ← missed
  Ui/ReviewsQaRequests.php:13        X181\Models\QaTicket      ← missed
  Actions/ReviewRequestAction.php:10 X121\Models\Person
  Ui/Tickets.php:8                   X121\Models\Person
  Ui/ReviewsQaRequests.php:12        X121\Models\Person
```

Eight is also what `r124-boundary.txt` lists for C-Reviews, which I had open in the same tick and did
not reconcile against my own grep. **Run 128's `boundary` delta is −5, not −3**, and `Ui/Tickets.php`
and `Ui/ReviewsQaRequests.php` import **both** modules — so they are half in run 128 and half in run
129, and the brief's original *"do not touch `Ui/Tickets.php` this run"* was wrong and is corrected.

⭐ **The lesson is not "grep the directory".** It is that this is the same defect as REV-119 §A — a
**stated** list where a **derived** one belonged — committed by the seat that wrote the rule, four
sections after writing it, in the same file. REV-121 §3 and REV-131 §5 both rest on the general form
*"the per-track list is stated, the product list is derived"*; a file list produced by naming files is
a stated list. **RULED: any count of code sites that enters a brief or a ruling is produced by a
command whose scope is the tree, and the command is printed beside the number.** A number with no
command beside it is a memory, and this lane has now paid for that eight times.

⚠️ And the near-miss is the instructive part: `r124-boundary.txt` had the right answer on disk, open in
this tick, unread. Seventh instance of **a correct statement present in the tree and not read back**
(REV-119 §A, REV-121 §A, REV-125 §13, REV-126, REV-127 §1, REV-129, here). The brief now tells the
coder to re-measure and says explicitly that I got it wrong — a stated number the reader is told to
distrust is worth more than a stated number.

## REV-134 — the eight-name floor is a push criterion, and a brief's paths are written out or they drift

⛔ **§1. A RED `== verdict` IS NOT AUTOMATICALLY AN UNPUSHABLE SHA, AND THE DISTINCTION IS THE FAILING *SET*,
NOT THE COUNT (2026-09-09, run 129).** `r129-gate.log` ends `⛔ a gate failed above.` with §7 the only red
section, and its eight `✗` names are exactly REV-132's floor: six money-lane evidence artifacts
(`evidence/X-117/checkout.json`, `evidence/X-199/invoice.json`, `evidence/X-211/recovery.json`, and X-198's
`capture_persists_real_id` / `pay_link_returns_real_url` / `a_real_gateway_charge_id_exists`, all minted by
`php artisan x198:evidence-*`, a command this lane does not own and must not run) and two journey-harness
refusals demanding a **real Infobip/telephony transport** whose credentials are on the reserved list.

**RULED: a `--tests` run whose failing set is exactly the eight baseline names is a GATED-GREEN sha for push
purposes, because none of the eight is a function of this lane's tree and no wave in this lane can move any
of them without a reserved-list credential.** ⛔ **A ninth name revokes it outright** — the ruling is on the
*set*, and the set is quoted in full in every report that leans on it. ⚠️ **The converse is the case this
replaces:** REV-133 held `4f5d44f6` unpushable because its gate failed, and that was right — it failed on
**pint**, a real regression in this lane's own diff. *A gate failed* is not a criterion; *what* failed is.

⛔ **§2. EVERY PATH A BRIEF NAMES IS WRITTEN REPO-RELATIVE AND IN FULL, INCLUDING THE REPORT'S (my defect).**
Run 129's brief gave an explicit `.agents/supervisor/` path to every artefact it named —
`> .agents/supervisor/r129-gate.log` — except one. Item 5's entire text was *"### 5. `REPORT.md` last"*. The
single artefact whose path was left implicit is the single artefact that landed in the wrong directory: run
129's report went to the repo **root**, untracked, while `.agents/supervisor/REPORT.md` still held run 128's.

⚠️ **The damage is silent and compounding.** `supervise.sh` §3's `mailbox:` block reads the mailbox copy, and
this seat's own session-start step 2 — *"if `REPORT.md` is newer than the last `REVIEWS.md` block"* —
resolves against it. A gate would have advertised run 128's report as current indefinitely, and the tick that
found run 129 found it only because the tick's own instructions point at the root. **No exception for a file
whose location "everyone knows"** — the mailbox is precisely what a fresh coder session does not know.

⭐ **§3. C-Reviews reached ZERO boundary violations, and the instrument that made the refactor safe was
bought one run early.** `4f5d44f6`'s four routed `GET`s were written for run 128's X-181 seam; they are what
covered run 129's X-121 seam, because `Ui/Tickets.php` and `Ui/ReviewsQaRequests.php` changed in both. That
is the REV-132 erratum's split paying for itself: *"the split is what keeps the verification single."*

⛔ **§4. THE MIRROR SEAM, AND THE RULE THAT AN INSTRUMENT MUST REACH THE CHANGED LINE.** One import survives
in the other direction — `X-181/Ui/Ticket.php:7` `use App\Modules\CReviews\Models\ReviewRequest;`, used once
at `:87`. Both modules are wave 8's, so C-Reviews grows a registered `ReviewRequestReadAction` returning
`?ReviewRequest` and X-181 calls it. The cast question was measured before ruling — `ReviewRequest::$casts`
is `rating => integer`, `gbp_suspended => boolean`, no Carbon, and the blade reads only `$review->rating`
(`X-181/Ui/views/ticket.blade.php:33-34`) — so an `?array` would work and is **still refused**, because the
typed return costs nothing. REV-131 §4's third option, *keep the read and strip the type off it*, stays
refused wherever a typed alternative is free.

⚠️ **But `TicketScreenTest.php:21` is a real routed `GET` that passes and proves nothing about that line.**
It mounts with no `ticketId`, so `$this->ticketId > 0` is false and the branch never executes. And a routed
`GET` *cannot* reach it: `public int $ticketId = 0` is `#[Locked]` and is not a route or query parameter.
**RULED: a seam refactor is briefed only behind a test that enters the changed branch, and building that
test is the wave's FIRST item with a hard stop attached** — if the branch cannot be entered, the wave stops
there and reports it rather than refactoring blind. This is the merged-wrong-lint trap in test form: a test
that cannot reach the code is green by construction, exactly like a lint that matches nothing.

⚠️ **§5. `schema` WAS REPORTED WITHOUT ITS DATABASE FOR THE SECOND CONSECUTIVE RUN**, after the brief quoted
REV-119 §B at it by name and told it *"you got this right last run"*. Harmless only because the count did not
move. A ⛔ that is restated and re-violated is a sign the instruction needs a **paste-ready string**, not
another citation — run 130's brief gives the literal line to emit,
`FAIL schema <ms> 14 violation(s)   (read from goaiez_antig_reviews, per app/.env)`.

## REV-135 — the lane's code surface closed, and a restated instruction is a mechanism that is missing

⭐ **Run 130 is a `PASS-WITH-NOTES`, and the paste-ready-string remedy worked on its first try.** REV-134 §5
predicted that citing REV-119 §B a third time would fail again and handed over the literal line instead. It
came back exactly right: `FAIL schema 473ms 14 violation(s)   (read from goaiez_antig_reviews, per app/.env)`.
**N1 is closed after two consecutive violations.** The seam landed, `boundary` fell 42 → 41 as predicted, the
failing set is exactly the eight-name floor, phpstan is `errors 0`, and the ordering held end to end for the
fourth run running — gate `00:07` → state commit `00:08:37` → report `00:09:25`. `faca10e0` is pushed.

⭐ **§1. THE MUTATION WAS REAL AND THE REPORT NEVER MENTIONED IT — AND THE SEAM IS PROVED ANYWAY, BY THE
BLADE.** `.agents/supervisor/r130-mutation.patch` is on disk, 471 bytes, and its content is correct: it
mutates `$review = ReviewRequest::find(...)` to `$review = null` against index `a143110d`, the pre-seam file.
So the instrument was built and the mutation was applied. **But item 1 required the failing assertion text
and the report has no mutation section at all**, so from the report alone a mutation that reddened the test
and a mutation that was never run are indistinguishable.

⭐ **Verified independently instead of taken on trust, and this is the part worth keeping:** the new test's
`assertSee('Rating: 4')` is load-bearing *by construction of the blade*, not by the coder's say-so —
`X-181/Ui/views/ticket.blade.php:33-34` reads `@if ($review && $review->rating) <div>Rating: {{ $review->rating }}</div>`,
so the string can only appear when the changed line returned a non-null, business-scoped `ReviewRequest`. A
passing test therefore proves the branch executes. **The mutation was belt-and-braces; the blade is the
proof.** REV-134 §4's rule — *a seam refactor is briefed only behind a test that enters the changed branch* —
is satisfied on its merits, which is why this is a note and not a `BLOCK`.

⚠️ **§2. THE INSTRUMENT REV-59 FIXED DOUBLE-COUNTS `Livewire::test(`, SO ITS DELTA IS NOT THE TEST COUNT.**
Measured, with the command beside the number (REV-132's erratum standard):

```
$ grep -c 'public function test\|test(\|it(' app/tests/Modules/X-181/Screens/TicketScreenTest.php
  4                                    # HEAD
$ git show 6bab3c86:app/tests/Modules/X-181/Screens/TicketScreenTest.php | grep -c 'public function test\|test(\|it('
  2                                    # before
```

**+2 for one added test.** The `test(` alternative — there to catch Pest files — also matches every
`Livewire::test(` call, and this class has one per test method. The count still *rose*, which is all REV-59
asked of it, so nothing here is wrong; but a brief that predicts "before 1 after 2" on a Livewire screen test
will be told it read 2 and 4, and that discrepancy is noise, not a finding. **RULED: on a screen test the
expected delta is `2 × (tests added)`, and the brief says so rather than letting the coder explain it.**

⛔ **§3. THE SAME MEASUREMENT WAS ASKED FOR AND SUMMARISED FOR THE THIRD RUN RUNNING, AND RESTATING IT IS NOT
THE FIX.** Item 3 required `pint --test`'s file list pasted; the report gives `{"tool":"pint","result":"passed"}`
from the gate instead. Item 1 required the `grep -c` numbers; they are absent. Item 1 required the mutation's
failing assertion; absent (§1). That is N2 from REV-134, restated more firmly, violated again — while N1,
which was given a **literal string to emit**, was fixed immediately.

**RULED: every measurement a brief requires is redirected to a NAMED FILE under `.agents/supervisor/`, and
`REPORT.md` cites it by path.** `> .agents/supervisor/r131-pint.txt`, not *"paste the output"*. The artefact
then exists whether or not the report quotes it, and the reviewer reads the real output instead of a retyped
number. ⭐ **The ladder this lane has now measured three times over: a paste-ready string beats a citation,
and a redirect beats a paste-ready string** — because a redirect cannot be paraphrased. Written into
`.agents/rules/10-supervisor.md`, since it is a rule about what a report may claim.

⭐ **§4. `supervise.sh` §3 NOW REPORTS A MAILBOX FILE THAT LANDED AT THE REPO ROOT, AND RUN 129'S IS ITS
POSITIVE CONTROL.** REV-134 §3 found run 129's `REPORT.md` in the repo root and fixed the *brief* by writing
the path out in full. That fixed the next run and left the detector missing — §3's `mailbox:` block reads
only `.agents/supervisor/`, so it would have advertised a stale report indefinitely, and this seat's own
session-start step 2 resolves against the same path. Added this tick, and it fired on its first run against a
file that was really there:

```
    REPORT.md  2026-09-10 00:09:25    76 lines
    ⛔ REPORT.md at the REPO ROOT 2026-09-09 23:21:13    70 lines — the mailbox is .agents/supervisor/REPORT.md
```

**A brief fix protects one run; a check protects every run.** Same move as REV-126's §2f, one order of
magnitude smaller.

⛔ **§5. THE LANE'S CODE SURFACE IS CLOSED, AND EVERYTHING LEFT IS RESERVED OR CROSS-LANE. MEASURED, NOT
REMEMBERED.** With the mirror seam shut, all three of wave 8's modules were re-measured against a live doctor:

```
$ php artisan doctor --stage=boundary | grep -i 'c-reviews\|x-181\|x-110'      → nothing
$ php artisan doctor --stage=capability | grep -i 'c-reviews\|x-181\|x-110'    → nothing
$ php artisan doctor --stage=citation                                          → 3, all X-198 (money lane)
$ php artisan doctor --stage=contract | grep -i 'c-reviews\|x-181\|x-110'      → 4
$ php artisan doctor --stage=anchor | grep -i 'c-reviews\|x-181\|x-110'        → 3 × 'no runtime proof'
```

**`boundary`, `capability` and `citation` are at ZERO for this lane's modules.** The 41 remaining boundary
violations belong to X-01, X-121 readers, X-198/X-199 and C-Sms — other lanes'. What is left here:

- **`X-110 @provides pixel.install` / `pixel.events`: "does not declare whether the agent may reach it."**
  ⛔ **Not brief-able.** `manifest.php` is GENERATED — its own header says so — and `ModuleScaffoldCommand`
  reads the documented headers out of **`app/GOAIEZ-MASTER-PLAN.md`**, the frozen plan on the reserved list
  with no guard in front of it (REV-131 §2). `agent_reachable` currently reads `['pixel.verify']`; adding two
  entries is a **plan edit and a security decision** — silence fails OPEN, per the checker's own comment.
  **OWNER ACTION.**
- **`X-110: consumes 'page.loaded' — nothing emits it.`** Either another module grows the emitter (cross-lane)
  or the plan header drops it (reserved). **OWNER ACTION.**
- **`C-Reviews, X-118: 'win.first' has 2 emitters`** — already a standing `TRACK 1 ACTION`, unchanged.
- **3 × `anchor: no runtime proof`** — `TestAnchorStage` demands `evidence/<id>/runtime-proof.json` carrying an
  artifact id **no code may mint**, off a non-`sync` driver. That is real transports and real credentials, and
  it is 128 violations project-wide, not this lane's to solve alone. **Reserved.**

⭐ **And the genuine backlog this uncovered, which is neither reserved nor cross-lane.** `state.py next` still
names C-Reviews as wave 8's remaining module, and **P-110 — the module's own headline law — is not built**:

```
$ grep -rn 'public_threshold\|solicitation_policy\|review_destinations' app/app/Modules/C-Reviews/ app/app/Modules/X-181/
  (no output)
```

Plan `:592` specifies `public_threshold` (default 4, max 5), `fix_then_ask_enabled` firing on `ticket.resolved`,
and a `review_destinations` roster with a per-destination `solicitation_policy`. **None of the three names
occurs anywhere in the lane.** That is the next real build wave, and it needs migrations — the coder's column,
not mine.

⛔ **§6. AND THREE C-REVIEWS TESTS ARE GREEN BY CONSTRUCTION — HONESTLY LABELLED, WHICH IS WHY NOBODY LOOKED.**

```
$ grep -rn 'assertTrue(true)' app/tests/Modules/C-Reviews/ app/tests/Modules/X-181/ app/tests/Modules/X-110/
  CReviewsTest.php:296   test_g20_06_header
  CReviewsTest.php:352   test_g20_09_header
  CReviewsTest.php:496   test_g1_68_assertion
```

Each carries a docblock reading *"⛔ REFUSED: surveyed Actions, Database, Events, Listeners, Models, Ui and
found no implementation."* The refusals are honest. **The `assertTrue(true)` is not** — it makes the test
*pass*, so a green suite reports `test_g20_06_header` as covered. That is the merged-wrong-lint trap in test
form, and this lane has now hit that shape in a lint, in a route-gated component, in a `#[Locked]` branch, and
here.

⭐ **The house has already ruled on the remedy, in the tree, and I read it back before ruling rather than
after** — `tests/Journeys/TwelveJourneysTest.php:477`, on four tests that were once `markTestIncomplete()`:

> *"⛔⛔ These were markTestIncomplete() and that was the wrong call. A skipped test is invisible in a green
> run; a FAILING test names what is missing every single time the suite runs … a green suite that proves
> nothing is worse than a red one that proves something."*

**RULED by the lane supervisor: a refusal test fails loudly with `$this->fail('NOT BUILT: <id> — <what is
missing>')`, because the tree's own written position refuses both alternatives** — `assertTrue(true)` is the
false green it condemns and `markTestIncomplete()` is the invisibility it condemns by name.

⚠️ **But every one of those three claims is dated, and REV-129 says an `UNRESOLVED` is a claim with an
expiry that nothing in this project re-reads.** `C-Reviews / tests` sat blocked for five days on a
`messageClass` field that had landed the next morning. **So run 131 re-reads all three against the tree
first**, with the command printed, and only a claim that still stands is converted. A claim that has gone
false gets real assertions instead — and that outcome is the more valuable one.

⛔ **§7. THE EIGHT-NAME FLOOR IS AMENDED, AND THE MARKER IS MECHANICAL.** REV-134 §1 made "the failing set is
exactly these eight names" a push criterion and said *"a ninth name revokes it outright"*. §6 deliberately
adds three. **RULED: the criterion is now the eight baseline names PLUS any test whose failure message begins
with the literal `NOT BUILT:`.** The prefix is what keeps it mechanical — a `grep` can separate a declared,
recorded gap from a regression, which is the whole property REV-134 §1 was protecting. ⛔ **A failing name
that is neither in the eight nor prefixed `NOT BUILT:` still revokes the sha outright.** Expected after run
131: `FAILED 6 · errors 2` becomes eleven `✗` lines, eight of them unchanged.

⚠️ **§8. `cd app && …` IN A TICK SILENTLY REVOKED THIS SEAT'S OWN EDIT PERMISSIONS.** `.claude/settings.json`
allows `Edit(bin/supervise.sh)` and `Edit(.agents/rules/10-supervisor.md)` as **relative** paths, and they
resolve against the session's working directory. One `cd app` earlier in this tick — to run `php artisan
doctor` — moved that directory, and both edits were refused with a permission error that named neither the
cause nor the cwd. It reads exactly like an owner having closed the column. **The fix is one `cd` back**;
the trap is that the symptom points at the settings file and the cause is three commands earlier. ⭐ Same
family as the root-owned `FETCH_HEAD` trap: *presents as a permissions or config problem and is neither.*
**RULED: run `php artisan` from the root as `php artisan --working-dir` is unavailable here — use
`(cd app && …)` in a subshell, or accept the `cd` and change back in the same tick before any Edit.**

⛔ **§9. TWO OF EIGHT PER-TRACK PATHS WERE IN §2f'S BYPASS ARM, AND BOTH WERE MINE.** `bin/supervise.sh`
(main's copy is **94 deletions** — it removes §2f itself and REV-119 §E's per-lane database block) and
`.agents/rules/10-supervisor.md` (main still carries the pre-REV-121 text, so **the anti-push rule is still
deleted on `main`**). Per REV-126 the supervisor moves those on our side *before* a merge wave, and the move
must be real work rather than a touch. Both were moved this tick with content that stands on its own — §4's
root-mailbox check and §3's redirect rule — which is protection and record in one commit. ⚠️ **The verdict is
good only for `(our sha, their sha)`** and is re-derived at every merge; nothing here authorises a merge.
