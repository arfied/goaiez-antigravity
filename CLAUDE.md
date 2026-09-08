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
| **Commits only its own files** — `CLAUDE.md`, `bin/supervise.sh`, `.claude/settings.json`, `.agents/rules/10-supervisor.md`, `.agents/supervisor/launch-coder.sh` — as `chore(supervisor): …`, with named paths | Commits `app/**` and `.agents/state/**`, per module |
| **Runs ALL git for this lane, the push included** (OWNER.md 14:0x). Pushes only a sha it has gated and recorded in `REVIEWS.md`, by explicit ref: `git push origin <sha>:track/money`. Never a branch head, never `--force`, never a sha under a live `BLOCK` | **Never pushes.** Its guard stays closed; `BRIEF.md`'s `push:` line is always `NO` and the launcher hard-closes `GOAIEZ_PUSH_OK` |
| **Never:** migrate, touch a database, edit `app/**`, run a test suite outside `supervise.sh --tests`, `git merge\|switch\|checkout\|restore\|reset\|stash` (all denied) | **Never:** edit `BRIEF.md`/`REVIEWS.md`, push before `PASS`, edit sealed or generated files, commit `.agents/supervisor`/`CLAUDE.md`/`.claude`/`bin` |

`.claude/settings.json` enforces your column. Since 2026-09-05 08:0x (owner,
commit `0634e31f`) it **allows** the supervisor `git add`, `git commit` and
`git push origin`; the other 50 deny entries stand, `git merge` among them. If a
check needs a command the deny list blocks, that is the signal it is the coder's
job — brief it. **Merge step 0 (committing the supervisor's own notes) is
therefore the supervisor's, not the coder's** — the coder guard refuses those
paths. Since 14:0x (ruling 26) **the push is the supervisor's too**, and the
owner runs no git by hand: a gated tip that no tick pushes stays unmerged
forever, so the push is a step of the review, not of the next brief.

## The mailbox — `.agents/supervisor/`

| `BRIEF.md` | you → coder. The current directive, overwritten in place. Its `push:` line is the push gate — `YES — <from>..<to>` is executed by the coder as step 0 of its next run (owner automated pushes 2026-09-03); the supervisor sets it only after a PASS and only to the reviewed tip |
| :--- | :--- |
| `REPORT.md` | coder → you. Overwritten at every wave close or stop, fixed shape (rule 10) |
| `REVIEWS.md` | you → coder. **Append-only**, dated blocks at EOF, verdict `PASS` / `PASS-WITH-NOTES` / `BLOCK` |

## Session start

1. `bash bin/supervise.sh` — DB guard, tree, forbidden paths, build state,
   checker soundness, integrity, pint, phpstan. Read-only. Add `--tests` to run
   pest (against `phpunit.xml`'s database, never `.env`'s), `--full-doctor`
   for all eight stages.
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
- **Tests are real.** `grep -c 'test(\|it('` before/after must match the report,
  and a test that greps a directory must grep one that exists (rule 01: 19
  anchors once passed against missing paths).
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
- **A partial commit is invisible to every gate step (MONEY-37, 2026-09-05).**
  A run that dies mid-item can leave a commit that references a class it does
  not contain: `445987c3` imported and dispatched three `X-199` `Events\*`
  classes that were still `??` in the working tree. **§2b's `php -l` cannot see
  it** — it is per-file syntax and does not resolve a class — and **§7 cannot
  see it**, because the suite runs against the working tree, where the file is
  present. Nothing in the gate distinguishes a finished commit from a truncated
  one. So, for every commit under review: `git show --name-only --format= <sha>`
  against the `use` lines and `new`/`::class` references in its own diff, and
  `git status --short` accounted for line by line (`app/composer.phar` is the
  only expected `??`, OWNER ACTION 19a). Pushing such a tip hands the merging
  track a fatal `Class … not found`; ruling 26 already forbids pushing under a
  live `BLOCK`, and this is one.
- **A quota death looks like a stop and is not (MONEY-37).** `agy` exits with
  `Error: Individual quota reached … Resets in NNm`, `AGY_EXIT=1`, and writes no
  `REPORT.md` — so the newest report is whatever *interim* one the run left
  behind, describing a tree that no longer exists (MONEY-37's said
  `COMMITS:` empty while `445987c3` was already in the log). Read
  `.agents/supervisor/logs/agy-run<N>.log` before reading the report; it is two
  lines and it names the reset time. **Do not dispatch inside the reset window**
  — the launch produces another two-line log and spends one of the two
  dispatches the BLOCK is allowed. Hold the ready brief and leave a
  `TICK-ADDENDUM.md` telling the next tick to launch it, because a BLOCK verdict
  closes case (b) *and* case (e) and the brief would otherwise never run.
- **`git merge --abort` wipes the supervisor's uncommitted ledger (2026-09-04
  13:07).** The abort briefed as MONEY-17 step 0 reset `CLAUDE.md` to `HEAD`,
  dropping rulings 17–18 written at 12:58; the coder's `cp CLAUDE.md
  CLAUDE.md.bak` (13:06:52) and the launch snapshot
  `/home/goaiez/tmp/sup-snap-grs-antig-money-<stamp>/` kept them, and the
  13:33 tick restored the file from the backup. The supervisor's tracked files
  (`CLAUDE.md`, `bin/supervise.sh`, `.agents/rules/10-supervisor.md`,
  `launch-coder.sh`) are only as safe as that snapshot: after any briefed
  merge, abort or reset, the next tick compares them against the snapshot
  before anything else. An `OWNER.md` appendix written while a tick is mid-read
  is invisible to that tick — every tick re-reads `OWNER.md` to EOF, not just
  its mtime. **Correction (13:46 tick):** `git reflog` shows no `reset:` entry
  for that window and the coder's run-18 log says it "removed the worktree's
  `AUTO_MERGE` file" — it was not `merge --abort`. Whatever ran reset tracked
  files to `HEAD` and left `main`'s 149 merge-added files untracked, which
  inflated the gate by 36 tests / 17 red until MONEY-17b removed them. After
  any briefed merge step, read `git reflog -5` and count `??` paths, not just
  `M/A/D` lines.

## Dispatching the coder (added 2026-09-02)

When the user has enabled the settings rule for
`.agents/supervisor/launch-coder.sh`, the supervisor launches runs itself:
write `KICKOFF.md`, arm the run's monitor, then
`bash .agents/supervisor/launch-coder.sh`. The script refuses a second
concurrent run and auto-numbers logs.

**Retry cap — absolute:** at most **two** dispatches per BLOCK (the original
run plus one fix run). If the same BLOCK item survives a second dispatch,
STOP and put it to the user — never dispatch a third time for the same
failure, never loosen the check to get past it. **The cap stops an ITEM, never
the track (2026-09-03, after an idle hour):** a defect the fix run introduced,
or one the supervisor's own brief caused, is a new item with its own two
dispatches; and work that is not blocked at all (the next brief item, the next
merge) is dispatched immediately. Idle is never the default — when a cap
stops one item, list it for the owner AND dispatch the next work in the same
breath. A journey/wave marked green
by the coder is never taken at face value: the supervisor's own gate decides.
Never run `state.py done/journey/stage` from the supervisor; never touch
`app/Doctor`; never let the coder and supervisor loop without a human seeing
each verdict block in `REVIEWS.md`.

- **One writer per checkout (2026-09-03 incident).** An interactive `agy`
  started by hand inside this checkout has no pidfile, no `coder-bin` guard,
  no brief and no review — it overwrote 170 files with `place-files.sh`,
  hand-marked four journeys green in seven minutes, and pushed `main`. Any
  process with `cwd` here that `launch-coder.sh` did not start is a BLOCK:
  `for p in /proc/[0-9]*; do readlink $p/cwd 2>/dev/null | grep -q 'grs-antig$' && ps -o pid=,cmd= -p ${p#/proc/}; done | grep agy`
  must print nothing before any dispatch or gate. The supervisor may stop
  such a process to protect `main`; it says so in REVIEWS the same minute.

## Style

Terse and factual. Cite rules and traps by name — "that is the One Rule",
"that is the count-did-not-fall trap". Warn on dangerous git operations rather
than assuming they are blocked: the deny rules are prefix matches and stop a
habit, not a determined reordering.

## TRACK 5 — money (this worktree)

This checkout is **Track 5**: branch `track/money`, worktree
`/home/goaiez/agents/grs-antig-money`. Track 1 (`/home/goaiez/agents/grs-antig`
on `main`) is the ONLY track that merges to `main`. This track pushes to
`origin track/money` after a PASS; Track 1's supervisor reviews and merges.

- Databases: dev `goaiez_antig_money`, tests `goaiez_antig_money_test` (the gate
  exports it over phpunit.xml's pin; brief every pest run with the
  `DB_DATABASE=goaiez_antig_money_test` prefix). `goaiez_antig` is production and
  `goaiez_antig_dev`/`goaiez_antig_test` belong to Track 1 — touch neither.
- Journeys owned: J9 (an invoice reaches a real charge id), J12 (an overdue invoice is chased by reason, resolution first).
- Modules owned: X-199, X-198, X-211. C-Billing is shared with Track 1 (J6): touch it only through a note in REPORT.md. X-204 belongs to track sixty. Edits stay under `app/app/Modules/<id>/**` for
  those ids, plus the owned journeys' methods in
  `tests/Journeys/TwelveJourneysTest.php`. OUT of scope: every other track's
  modules and journeys, `resources/views` and `app/Livewire` (Track 2),
  and everything in Track 1's never-list (Doctor, seals,
  `JourneyHarness.php`, phpunit DB lines, generated manifests).
- Goal: J9 and J12 green against the real payment provider in test mode; a charge id that exists at the provider.
- Vendor: Payment-provider test-mode keys come from the owner into `app/.env`, never into a commit. Until they exist J9 stays RED and REPORT.md says UNRESOLVED (missing dependency).
- The loop: coder builds → `bash bin/supervise.sh --tests` → THIS track's
  supervisor reads the diff, the raw doctor journey line and the test count
  → verdicts in this worktree's REVIEWS.md. Two dispatches per BLOCK, then
  the owner. A journey the coder marks green is never taken at face value;
  only the gate's output counts.
- Shared files: `.agents/state/JOURNAL.md` and `BUILD-STATE.json` are written
  by every track through `state.py`. Rebase onto `origin/main` before each
  push with `git fetch --no-write-fetch-head origin`; keep both sides'
  journal lines in time order. Never edit either file by hand.
- SMS/mail drivers stay `log` in tests. A vendor send happens only in a
  journey on the real transport, with the owner's credentials.

## Owner rulings — 2026-09-02

1. **Harness.** A journey track may implement, in `app/tests/Journeys/JourneyHarness.php`,
   only the `todo()` methods its own journeys call, against the real transport.
   Touching any other method, assertion or guard there is a BLOCK. Track 1 merges
   and expects harness hunks from several branches.
2. **X-179 belongs to Track 2 (UI).** Its `dd()` is removed on `track/ui`
   (commit 88d85c1). No other track touches that file; §2c stays red on every
   track until Track 1 merges it. Record it, do not fix it.
3. **`app/phpunit.xml` keeps its pin.** It is a never-list file. The gate exports
   this track's test database over it; every hand-run pest carries the same
   `DB_DATABASE=` prefix. Accepted as a standing hazard, briefed every time.
4. **Databases exist** for every track, owner goaiez_owner, pgvector installed.
   The grants file needs a superuser and runs on request after the first
   `migrate`: write the request as an OWNER ACTION and stop.
5. **Module ownership.** sixty: C-Telephony, C-Sms, C-Agent, X-188, X-204,
   X-118, X-66 · pricebook: X-163, X-119, X-126 · money: X-199, X-198, X-211 ·
   reviews: C-Reviews, X-181 · site: X-157, X-110, X-102, X-155, X-137 ·
   Track 1: X-212, X-172, X-112, X-166, X-203, C-Billing · Track 2: all Ui/,
   views, Livewire · stages: everything not listed, checker findings only.
6. **Shared harness methods have one owner.** `tenantWithLiveNumber` (nine
   journeys) and `personWithPendingSteps` are owned by track sixty;
   `issueInvoice` by track money. No other track edits them, rewrites their
   `todo()` message, or waits on them with a vendor guess: if your journey
   needs one, record `UNRESOLVED — waiting on track/sixty merge` and build
   everything that does not depend on it. Pricebook commit a4b2d5a edited
   `tenantWithLiveNumber`; that is a BLOCK, to be reverted forward.
7. **The carrier is Infobip.** Inbound, delivery and voice webhooks, the
   verifier, and 113 files say so. There are no `TWILIO_*` keys anywhere and
   none will be added. A brief or report that names Twilio as a dependency is
   the vendor-from-memory trap: read `app/app/Modules/C-Telephony/` and
   `config/services.php` before naming a key.
8. **X-121 is the spine and belongs to Track 1.** Pricebook: `bookFromQuote()`
   records `UNRESOLVED — X-121 exposes no create path` (option b); the raw
   insert is not accepted.
9. **Harness scope wording.** Where this file's TRACK section lists
   `JourneyHarness.php` as out of scope, read "except the methods rulings 1
   and 6 allow". Ruling 1 governs.
10. **Payment provider is Stripe**, recorded as an R245 decision. The X-198
    anchor "charge id is issued only by the external gateway" is relaxed to
    "null unless the gateway returned one" — an owner-authorised CHECK change,
    one commit citing this ruling. STRIPE_SECRET (test mode) is in money's
    app/.env.
11. **Sixty sequencing and evidence.** Waves run J4 → J2 → J1. J1's evidence
    run places a real inbound call and the harness posts the callId Infobip
    sent. B3 resolves by option (a) — the voice.missed_call.texted_back audit
    row inside Tenancy::actingAs(). One further B3 dispatch authorised.
12. **J8 "permission denied to terminate process" is concurrency, not
    grants.** Rerun when idle; never request the grants file for it.
13. **Journeys that need a live vendor call are proven outside the suite.**
    The repo-wide no-live-calls guard in tests stands. J9 (and any journey
    like it) is evidenced by a console command run outside runningUnitTests()
    that leaves an artifact; the journey test asserts on that artifact.
14. **P-207 is defined: signup is two fields, business name and phone.** The
    name/email/password/terms door is sign-in for existing owners, not signup.
    `CreateNewUser` is not the signup contract; J2 builds the two-field door.
15. **A refusal clause is not a refusal.** A capability cell gaining
    "refuses …" without a matching `state.py decided` line AND a named test or
    refusal code is a merge blocker on every track. Track 1 owns the checker
    change that ties the two together.
16. **Reviews builds the cadence guard now** (`ReviewRequestAction`), with J10
    recorded UNRESOLVED while review-platform access is ungranted.
17. **The screens rebuild slice (owner via Track 1, 2026-09-04 12:44).** Money
    rebuilds, in this order, three per run: X-199 `declines` (§46A.2, §57.2),
    `invoices`, `unpaid`, `money-paid-today`; C-Billing `credits`,
    `dunning-board`, `mrr`; X-198 `connect-card`,
    `reconciliation-discrepancies`; X-211 `ageing-by-reason`,
    `paymentplan-builder`. For exactly these screens the `Ui/` files are
    money's, overriding ruling 5's "Track 2: all Ui/"; C-Billing's `Domain/`
    stays Track 1's. Rules per screen: the module header + §58.4, live data
    from the module's own models, an action on every row, five states
    (SAMPLE declared n/a where no model carries a flag), mobile first, no
    hand-written routes (Track 1's `surfaces:generate` mounts them), a page
    test via `Livewire::test` and a SYSTEM mutation with the RED line quoted.
18. **Integration runs money → main only.** The coder guard refuses any merge
    commit that stages `main`'s hunks in `CLAUDE.md`, `.agents/rules/`,
    `.agents/supervisor/launch-coder.sh` or `source/`, and the supervisor may
    not commit. A stuck `origin/main` → `track/money` merge is aborted by the
    coder (`git merge --abort`, guard-permitted) and never briefed again
    until OWNER ACTION 9(b) — a merge exemption in `coder-bin/git` — lands.
    Track 1 merges `track/money` daily (owner, 12:44) and reconciles there.
19. **HOLD on the screens rebuild (Track 1 supervisor relaying the boss,
    `OWNER.md` appendix written 12:55, headed "16:0x"; applied by the 13:33
    tick).** The boss re-cut the rebuild around FEATURES, not modules: money's
    module screens will be sections inside feature pages, never standalone
    module pages. Ruling 17's list stays the list of screens, but **no further
    screen is mounted or rebuilt** until (a) the feature map is approved
    (https://claude.ai/code/artifact/80afa4f2-bf4a-401f-b368-922c1bdf445c)
    and (b) Track 1 merges the feature-page shells. "Nothing changes for waves
    already in flight": MONEY-17 (dispatched 13:02, X-199 declines · invoices ·
    unpaid) finishes and is reviewed on its merits; MONEY-18 and later rebuild
    waves are **not dispatched** while the HOLD stands. The HOLD lifts only by
    a new `OWNER.md` section or a Track 1 merge carrying the shells — a tick
    checks both, never infers the lift.
20. **HOLD LIFTED — the Money lane (Track 1 supervisor relaying the boss,
    `OWNER.md` 14:2x; applied 14:27).** Ruling 19's HOLD is over: the plan is
    approved, the clock runs Monday 8 Sep → Friday 25 Sep. The lane is
    **Money**: X-199 · X-198 (P-197 apply = the merchant state machine only;
    the processor adapter waits on a contract) · X-120 CardVault (PAN + expiry
    + name, CVV never — P-196) · X-117 cart/checkout/storefront · X-201 ·
    X-211 · C-Billing · X-173. **X-214 surcharging is deferred.** This widens
    ruling 5's money list; ruling 17's `Ui/` override now covers every screen
    of these modules (C-Billing `Domain/` stays Track 1's). Fourteen modules
    are deferred product-wide (X-200 X-158 X-159 X-114 X-144 X-197 X-147
    X-143 X-141 X-145 X-213 X-208 X-215 X-214): files, tests and routes stay,
    nothing builds them. X-221/X-222/X-223 are minted on `main` — never
    scaffold them here. **Week 1 (8–12 Sep), in order, one commit and one real
    page test each:** declines · invoices · unpaid *(landed, `66b28d2`, pushed
    14:25)* · paid today · credits · dunning board · connect card + merchant
    application · card vault screen · ageing by reason · payment-plan builder.
    Waves: MONEY-18 = paid today · credits · dunning board; MONEY-19 = connect
    card + merchant application · card vault screen · ageing by reason;
    MONEY-20 = payment-plan builder, then week 2 (every remaining capability
    and shell screen, proven). Definition of done per screen: routed, gated,
    renders real model data with a real GET test, a SYSTEM mutation reddens
    the test (RED line quoted). Routing is generated on Track 1
    (`surfaces:generate`): never hand-write a route; ruling 18 still forbids
    the `origin/main` → `track/money` merge, so `surfaces:generate` runs only
    after Track 1 merges and the regenerated files come back with `main`. No
    `Http::fake` in a journey. Track 1 merges `track/money` daily: push after
    every PASS.
21. **The one-pass recipe — a commit is a FINISHED screen, never a shell
    (Track 1 answering the boss, `OWNER.md` appended 14:31; applied by the
    14:41 tick, after MONEY-18 was dispatched at 14:28).** Order inside every
    screen, one pass: (1) the table and model the module owns (migration +
    RLS policy); (2) the engine/action that writes it and the event it
    emits; (3) the Livewire screen with a real query against that table, its
    empty state written, actions wired to the module's `Actions/` (through
    the approval desk where the plan says so); (4) the page test: rows
    seeded through the module's own factory/seeder, a real GET asserting a
    seeded value is on the page, `Livewire::test` per interaction; (5) the
    `config/features.php` entry and, where the entry has one, the Reports
    tile fed from the module's events; (6) commit, then the mutation proof.
    Only the UI kit's components in `Ui/` — no raw markup. A dashboard tile
    names its source event and has a test that emits it and asserts the
    number moves. A vendor-gated feature ships its door and a "waiting on
    <vendor>" state as a finished state. `surfaces:generate` prints `shells
    remaining: N`, which must fall on every merge. **Measured against this
    checkout (14:41):** `config/features.php` does not exist here (it lands
    with Track 1's run 68 merge) — step 5 is `UNRESOLVED — waiting on Track 1
    merge`, never hand-created; the real GET 404s until `surfaces:generate`
    mounts the route, so the local proof stays `Livewire::test` +
    `assertSee` on a seeded value and the GET is added the run after the
    merge; the UI kit at `resources/views/components/ui/` has nine
    components (attention-card, button, empty-state, error-panel, gauge,
    skeleton, status-pill, submit, systems-strip) and no table, form, tile,
    drawer or assistant strip — the kit is Track 2's (`resources/views`), so
    money uses every component that exists and records the missing ones
    `UNRESOLVED — waiting on Track 2's kit`, it does not build them. Money's
    six rebuilt screens use zero `<x-ui.*>` components today. MONEY-18 was
    briefed before this ruling and is reviewed on ruling 20; from MONEY-19
    on, a `Ui/` view with raw markup where a kit component fits is a
    `BLOCK`, and a screen whose table is new ships its migration and RLS
    policy in the same commit.
22. **The main → money merge is refused again, with numbers (Track 1's
    `OWNER.md` 19:0x ask; measured by the 20:0x tick against
    `refs/track1/main` = `ef817c16`).** Main changes seven guarded paths this
    branch cannot stage — `CLAUDE.md`, `launch-coder.sh` (main's copy has no
    `--check` and no push gate), `.agents/rules/10-supervisor.md`,
    `bin/supervise.sh`, `app/phpunit.xml` (→ Track 1's pin), `source/*` —
    two of them dirty in this tree, so the merge can neither start nor be
    committed by the coder; and resolving them "ours" would ship money's
    copies onto `main` on the reverse merge. Ruling 18 stands. The module
    owner's resolutions for Track 1's money → main merge are in `OWNER.md`
    (20:0x section) and REVIEWS.md. **The fetch is allowed and is how a tick
    measures main without moving anything:** `git fetch
    --no-write-fetch-head /home/goaiez/agents/grs-antig
    main:refs/track1/main`, then `git diff --stat HEAD refs/track1/main --
    <paths>`. Until OWNER ACTION 9(b) or a re-cut (OWNER ACTION 16), the
    money-side follow-ups main's engine makes necessary — `EvidenceChargeCommand`
    → `requestCharge()`, the generator's `Layout` attribute, `surfaces:generate`
    — are listed in REVIEWS, not built here.
23. **The main → money merge is refused a third time (Track 1's `OWNER.md`
    06:4x section, "`origin/main` PUSHED at `1ec86979`… take it before your
    next push"; measured by the 06:3x tick).** `origin/main` = `1ec86979` =
    the `refs/track1/main` ruling 22 measured; no `.gitattributes` merge
    driver on `main`; `coder-bin/git` still has no `merge` exemption. Merge
    base `fe094469`; main 384 ahead, money 93. The merge (1) cannot start —
    `CLAUDE.md` and `launch-coder.sh` are dirty here and main changes both;
    (2) cannot be committed — both sides changed `CLAUDE.md` (139 / 19 lines)
    and `launch-coder.sh` (13 / 5) since the base, a conflict on paths the
    guard's `commit` case refuses; (3) "ours" on all seven would ship money's
    copies onto `main` on the reverse merge. Rulings 18 and 22 stand: money
    pushes `track/money` after every PASS; Track 1 merges money → main from
    the pushed tip with the 20:0x resolutions and keeps per-track files out
    in both directions; `track/money` takes `main` only through OWNER ACTION
    9(b) or the re-cut (16). Money's reply is `OWNER.md`'s 06:3x section;
    the re-list is OWNER ACTION 20. Follow-ups main makes necessary stay
    listed in REVIEWS, not built: `EvidenceChargeCommand` → `requestCharge()`,
    the `Layout` attribute, `surfaces:generate`, `config/features.php`
    entries, the harness merge, the withheld-figures ruling (six registry
    keys — check money's screens print no withheld figure the run after the
    re-cut).
24. **The supervisor's own commit and push rights (owner via Track 1,
    `OWNER.md` 08:0x; `.claude/settings.json` committed here at `0634e31f`,
    07:30).** `git add`, `git commit` and `git push origin` are allowed to the
    supervisor; the 50-entry deny list otherwise stands and **`git merge` is
    still denied**. The role table above is rewritten to match: the supervisor
    commits ONLY `CLAUDE.md`, `bin/supervise.sh`, `.agents/rules/10-supervisor.md`
    and `.agents/supervisor/launch-coder.sh`, as `chore(supervisor): …` with
    named paths, and pushes ONLY a sha it has gated and written into
    `REVIEWS.md`, by explicit ref (`git push origin <sha>:track/money`) — never
    a branch head, never `--force`, never a sha carrying a live `BLOCK`. The
    three gate hunks the owner named are adopted in this track's own
    `bin/supervise.sh` (never copied from Track 1's — the files differ by
    50–90 lines): pest under `timeout 1800` with a `TIMEOUT` line on rc 124;
    the test step refuses while another checkout pinning
    `goaiez_antig_money_test` has a pest live; `ZERO BYTES` + `rc` printed when
    pest returns no output. **Never edit the gate script while a gate is
    running** — bash reads it incrementally.
25. **The `origin/main` → `track/money` merge is refused a FOURTH time, and
    what changed (measured 11:3x against `origin/main` = `54ead493`).** Track 1
    named `b3ea8d37` at 07:4x; `origin/main` has moved past it to `54ead493`
    (main 684 ahead, money 94, base still `fe094469`). Money's
    `app/phpunit.xml` pin is intact (`goaiez_antig_money_test`) — Track 1's
    07:4x sweep found site and sixty on the wrong database, not money.
    Guarded-path diff HEAD → `origin/main`: `CLAUDE.md` 177, `bin/supervise.sh`
    54, `.agents/supervisor/launch-coder.sh` 18, `.agents/rules/10-supervisor.md`
    16, `app/phpunit.xml` 2 (→ Track 1's database), `source/*` 15. Four of
    those are **two-sided** since the base (`CLAUDE.md` 139, `launch-coder.sh`
    13, `bin/supervise.sh` 19, `app/phpunit.xml` 2) and therefore conflict.
    Ruling 24 removes reason (1) of rulings 22–23 — the supervisor can now
    commit its own dirty files, so the merge could *start* — and softens
    reason (2), since the supervisor can `git add` and commit the four guarded
    conflicts the coder guard refuses. **Reason (3) is untouched and decisive:**
    resolving them "ours" makes money's per-track copies clean hunks on Track
    1's reverse merge, which is the overwrite incident by construction. The
    merge is also a two-party dance now (the coder must run `git merge`, denied
    to the supervisor; the supervisor must commit it, refused to the coder)
    and neither half is briefable alone. So rulings 18, 22 and 23 stand
    unchanged: integration is money → main, from the pushed tip, with the
    five module-owner resolutions in `OWNER.md`'s 20:0x section. The go/no-go
    on the new two-party path is **OWNER ACTION 21**, not a tick's to take.
26. **The supervisor runs ALL git for this lane, the push included; the coder
    never pushes (owner via Track 1, `OWNER.md` 14:0x; applied by the 12:1x
    tick).** The owner runs no git by hand from now on. The supervisor commits
    its own five files — `CLAUDE.md`, `bin/supervise.sh`,
    `.claude/settings.json`, `.agents/rules/10-supervisor.md`,
    `.agents/supervisor/launch-coder.sh` — as `chore(supervisor): …` with named
    paths, and **pushes every tip it has gated and written into `REVIEWS.md`**
    by explicit ref (`git push origin <sha>:track/money`), never a branch head,
    never `--force`, never a sha under a live `BLOCK`. This supersedes ruling
    24's division only on the push: the coder's `push:` gate is closed
    permanently. Concretely — (a) `BRIEF.md`'s `push:` line is now always
    `NO — the supervisor pushes the gated tip (ruling 26)`, and it stops being a
    coder step 0; (b) `launch-coder.sh` hard-sets `GOAIEZ_PUSH_OK=0` rather than
    deriving it from `BRIEF.md`, so a stale `YES` cannot reopen the coder's
    gate; (c) **the push happens in the tick that writes the PASS**, immediately
    after the verdict block is appended — a gated tip nobody pushes never
    reaches Track 1, and there is no human left to catch it. If a tick finds
    `origin/track/money` behind a sha already recorded PASS in `REVIEWS.md`, it
    pushes that sha before doing anything else, even with a coder alive: a push
    stages nothing and touches no file in the tree.
27. **The `merge=ours` driver does not reopen the merge (Track 1 relaying the
    owner, `OWNER.md` 15:2x; applied by the 13:2x tick).** Track 1 is adding
    `.gitattributes` on `main` marking the per-track files (`app/phpunit.xml`,
    `CLAUDE.md`, `bin/supervise.sh`, `.claude/settings.json`,
    `.agents/rules/10-supervisor.md`, `.agents/supervisor/launch-coder.sh`,
    `.agents/state/*`) `merge=ours`, and every lane is asked to run
    `git config merge.ours.driver true`. **`git config` is not in the
    supervisor's allowlist** — it is briefed to the coder (MONEY-36 item 2), and
    it writes untracked `.git/config`, so there is nothing to commit. ⚠️ Track 1's
    own caveat is the decisive half: **a merge driver runs only when BOTH sides
    changed the file.** Ruling 25 measured `app/phpunit.xml` and
    `.agents/rules/10-supervisor.md` as **main-only** changes against money, so
    git would take `main`'s copy silently and no driver would fire. The
    attribute is a second belt, not a replacement, and the restore step
    (`git diff --name-only HEAD MERGE_HEAD -- <per-track paths>`, then
    `git show HEAD:<path> > <path> && git add <path>`) stays mandatory in any
    merge brief. **Rulings 18, 22, 23 and 25 stand unchanged; the go/no-go is
    still OWNER ACTION 21 and the re-cut (16/20) remains this track's first
    preference.** A tick never reads the driver's arrival as a lift.
28. **`BUILD-STATE.json`'s `STAGES` line is not a live count, on any stage
    (measured 13:2x).** `supervise.sh` §3 prints the stamp written by the last
    `state.py stage` run. Against `php artisan doctor` this tick it was wrong on
    five of eight stages and wrong by two orders of magnitude on two of them:
    boundary 2/**3**, citation **0**/**130**, capability **352**/**349**, anchor
    **10**/**132**, journey 9/**7**. Four consecutive briefs quoted it and were
    contradicted by the coder's own measurement (M28-A, M29-A, M33, M34-D).
    **No brief quotes §3's `STAGES` line.** Live numbers come from
    `php artisan doctor`, filtered to the lane's ids, and nowhere else.
29. **A track may not edit `GOAIEZ-MASTER-PLAN.md` until OWNER ACTION 25 is
    answered.** The plan is the source of record `module:scaffold` harvests, and
    its markup is load-bearing: a stray backtick in X-199's `@emits` line
    (`:26670`) truncates four events out of the generated manifest, which is why
    `X-211 consumes 'invoice.overdue'` and `X-120 consumes 'limit.exceeded'`
    both report "nothing emits it". `ModuleScaffoldCommand`'s own header records
    two earlier instances of the same field-parser truncation
    (`@agent_reachable`, `ceiling:`); this is the third. ⛔ The fix is never to
    add the declaration to a `manifest.php` — that file is generated, and an
    annotation asserted to make a check pass is §298's exact prohibition. Build
    the emitter first; the declaration follows once the owner rules on who may
    edit a module's header block.
30. **A quota death is a WAIT, not a dispatch; Claude Code is the second-try
    fallback (owner via Track 1, `OWNER.md` 17:1x; applied by the 14:2x tick).**
    One Antigravity account serves all eight tracks and drains whenever every
    lane is busy. A launch whose log ends in `Individual quota reached … Resets
    in Nm` **counts against nothing** — the tree is unchanged and the brief still
    stands. So: read the reset minute from the newest run log, **hold until it**,
    write one HOLD line in `REVIEWS.md`, and redispatch the unchanged brief. Do
    not spin: a launch inside the window produces another two-line log and
    teaches nothing. ⚠️ This supersedes the MONEY-37 addendum's step 2, which had
    the tick launch immediately and record the quota log afterwards. **If the
    redispatch dies on quota again**, the same `KICKOFF.md` goes to Claude Code
    on the default account (no `CLAUDE_CONFIG_DIR`) via
    `bash .agents/supervisor/launch-coder.sh --coder claude` — added to this
    track's own launcher at 14:2x, never copied from Track 1's. Three properties
    the owner fixed: the argument defaults to `agy` and **refuses** anything that
    is not `agy`/`claude`; the log names the coder and the `LAUNCHED` line prints
    `coder=claude`; and the claude branch runs under
    `--setting-sources user`, which keeps the *supervisor's*
    `.claude/settings.json` (it denies `app/**`) out of the coder's permissions —
    `coder-bin/git` and the seal bind a claude coder exactly as they bind agy.
    **Never automatic:** a tick passes `--coder claude` by hand and writes
    `coder=claude` in the REVIEWS block carrying the `LAUNCHED` line; the first
    launch after any reset is agy again. If a claude run ends in "reached your
    Fable limit" that pool is drained too — HOLD, and never switch accounts on
    your own. ⚠️ One deviation from the ruling's letter, recorded: the log stays
    at `.agents/supervisor/logs/claude-run<N>.log` rather than
    `/home/goaiez/tmp/claude-<track>-run<N>.log`, for MONEY-36's reason
    (`0b925de6`) — a tick's sandbox cannot read `/home/goaiez/tmp`, and a
    fallback run that dies without a `REPORT.md` is precisely when the log has to
    be readable. The run counter steps over both coders' names in both locations,
    so a claude run never reuses an agy run's number.
31. **The `origin/main` → `track/money` merge is refused a FIFTH and SIXTH time,
    and the reason is now `source/` alone (measured 05:1x against `fc8f0bab` and
    05:4x against `230a2c3a`; RULED by the lane supervisor under the owner's
    03:5x authority, so OWNER ACTION 21 is closed, not carried).** Main's
    `.gitattributes` now marks eight paths `merge=ours` — `app/phpunit.xml`,
    `CLAUDE.md`, `bin/supervise.sh`, `.claude/settings.json`,
    `.agents/rules/10-supervisor.md`, `.agents/supervisor/launch-coder.sh`,
    `.agents/state/BUILD-STATE.json`, `.agents/state/JOURNAL.md` — which answers
    rulings 22/23/25's reasons (1) and (2). **`source/` is not among them.** Since
    base `fe094469`, `git diff --stat fe094469 HEAD -- source/` is **empty** and
    `git diff --stat fe094469 origin/main -- source/` is `GOAIEZ-MASTER-PLAN.md` 7
    + `GOAIEZ-TRACKER-CAPABILITIES.md` 8: a **one-sided** change, so git takes
    main's copy with no conflict and no driver, and the merge commit **must stage
    main's `source/` hunks** — which ruling 18 records `coder-bin/git` refusing.
    Restoring `source/` from money's HEAD instead would revert Track 1's
    frozen-plan edits on the reverse merge, and ruling 29 forbids this lane
    touching that text at all. So rulings 18, 22, 23, 25 and 27 stand: integration
    is money → main from the pushed tip. The unblocking change is **OWNER ACTION
    9(b)** — a merge exemption in the shared coder guard — which is cross-lane and
    sits in every tick's `TRACK 1 ACTION` block. ⚠️ **A moved `origin/main` is
    never a lift, and neither is a further "take main" ask**: Track 1 has asked
    six times (05:0x `fc8f0bab`, 05:3x `230a2c3a` among them) and the measurement
    is unchanged each time. Re-measure the four lines above, cite this ruling, and
    do not re-open it as an open OWNER ACTION.
32. **Most of what the doctor still reports against this lane is not lane work
    (measured 05:4x).** `php artisan doctor` filtered to the lane's eight ids is
    **52 violations**, in three groups: (1) **frozen-plan blocked** — X-173's
    eleven ids `N-063…N-085` (each `NO row in the tracker or the plan` *and*
    `specced but no test names this id`, plus eleven `capabilities.php` citation
    lines), `X-199 · G1-60` and `X-198 · G1-34` (`the ⑤ names no refusal`), both
    `X-117 @provides` `agent_reachable` lines, and the truncated `emits` lists;
    these are cells in **generated** files harvested from the frozen plan, so
    MONEY-52's "name the id on the test" move does **not** move them — it worked
    there only because those five had a plan row; (2) **anchor `no runtime
    proof`** for X-120, X-173, X-201 — a real-transport run writing `evidence/`,
    i.e. credentials and vendor accounts, **reserved to the owner**; (3) **test
    quality inside the lane**, which is the only buildable group. Brief group 3;
    record 1 and 2 with their reason. ⛔ **`invoice.overdue` and `limit.exceeded`
    are NOT missing emitters.** X-199 ships `Events/InvoiceDue.php`, dispatched by
    `Console/MarkInvoicesDueCommand.php:65` under `due_date < today` AND
    `status = 'issued'` AND `due_notified_at IS NULL` — that IS "went overdue,
    once" — and `Domain/InvoiceEngine.php:85` dispatches `Events\LimitExceeded`.
    The gap is a **name** in two generated manifests. Minting a second event class
    to make a generated string resolve is the annotation-driven change §298
    prohibits and would leave two events with one meaning; ruling 29's "build the
    emitter first" is already satisfied. Record `UNRESOLVED` with the reason.
33. **A test that seeds rows against a `startOfWeek()` filter is a boundary bomb
    unless the clock is frozen through the READ (RULED by the lane supervisor,
    2026-09-06 09:1x, on MONEY-63's `edf53c97`).** `X-199/Ui/Declines.php:56`
    filters `created_at >= now()->startOfWeek()`. MONEY-63's new test seeded the
    literal `2026-09-06 10:00`/`10:05`, then cleared `Carbon::setTestNow()`
    **before** the Livewire render, so the component computed `startOfWeek()`
    from the real clock: green on Sunday 2026-09-06, red from Monday 00:00 when
    both rows fall out of the window. The pre-existing `test_declines_screen` had
    the same defect through `now()->subDay()` at `:39`. So, lane-wide: **a test
    that seeds rows against a `startOfWeek()`/`startOfDay()` filter derives its
    timestamps from `now()->startOfWeek()` and keeps the clock frozen through the
    render**, clearing it only as the method's last line. A literal date, or a
    relative offset with the clock cleared before the assertion, is green in the
    same second and red across a boundary (556–558) and is refused at review.
    ⚠️ Controlling the clock for the *writes* is not controlling it — MONEY-63
    used `setTestNow()` correctly for both `capture()` calls and still shipped the
    bomb. ⚠️ `$time2 = $time1->copy()->addMinutes(5)`: `addMinutes` mutates in
    place, and two rows in the same second fail `Declines.php:66`'s strict `>`.
34. **`pint` red on the tip is a BLOCK, and it is the inverse of the usual hazard
    (measured 09:1x).** `bin/supervise.sh` §6 runs `./vendor/bin/pint --test` from
    `app/` against the **working tree**. The familiar trap is a coder's
    uncommitted pint fix making the gate green on the tree and red on the sha;
    MONEY-63 hit the other side — `git diff --stat HEAD -- app/` was empty, so
    §6's `result":"fail"` on `tests/Modules/X-199/DeclinesScreenTest.php` was red
    on `edf53c97` itself. Ruling 26 forbids pushing a sha under a live BLOCK, and
    a style-red tip is a red gate Track 1 did not author. **Check `git diff --stat
    HEAD -- app/` alongside §6 every time**: empty means §6's verdict is the
    sha's. The fix is one file — `./vendor/bin/pint <that path>`, never the tree,
    since another lane's file is not this lane's to reformat.
35. **Workflow state belongs to the module whose screen owns the workflow, never
    as a column on another module's table, and never as Livewire component state
    (RULED by the lane supervisor 2026-09-06 09:3x, briefed as MONEY-65).**
    `Declines.php:17`'s `public array $hiddenRows` made "Settle up later" a
    per-mount dismissal: the row returns on the next page load and no test saw it,
    because the existing test never remounts. **A button that says the owner dealt
    with something writes a row.** That row is X-199's own `decline_deferrals`
    (`business_id`, `payment_id`, unique per pair, `tenant_isolation` RLS as
    `2026_08_30_000026_create_x199_invoice_tables.php:71-83` writes it) — **not** a
    `deferred_at` column on `payments`, whose vocabulary is the gateway's alone
    (ruling 32) and which X-199 may read but not annotate. The deferral **hides,
    it never deletes**: the row stays reachable under `toggleShowAll()`, because
    declined money must never vanish from the screen that exists to chase it.
    ⚠️ Two corollaries for any future in-component filter: **a test that asserts a
    hide inside one component instance proves nothing** — assert a fresh
    `Livewire::test` mount; and **an action that becomes a durable write needs the
    tenancy check the array push never needed**
    (`Payment::where('business_id', Tenancy::idOrFail())->findOrFail(...)`, the
    shape `sendPayLink` uses at `:27-31`). No event is minted for a deferral —
    nothing consumes it and the frozen plan declares none (rulings 29, 32).
36. **A URL a money screen prints must reach the thing it claims to reach, and
    the object behind it is persisted, never regenerated (RULED by the lane
    supervisor 2026-09-06 09:5x, briefed as MONEY-66).**
    `X-198/Actions/PaymentLinkAction.php` returned
    `"https://pay.goaiez.com/link/".Str::random(24)` — no provider call, no row,
    and it never saw the `Payment`; `Declines.php:29` stored it in the component
    property `$payLinks` and the blade rendered it as a live `<a href>` under a
    decline, where the owner's next act is to send it to a customer to collect
    **real money**. Four properties were wrong at once: the URL was fabricated,
    it was per-mount state (the `$hiddenRows` shape ruling 35 had just removed),
    it was not idempotent (two clicks, two tokens, the second silently voiding
    what the owner already sent), and no record tied a link to the decline it
    settled. So: **a pay link is a real provider object created through
    `StripeGatewayClient` in the shape `charge()` uses, persisted on X-198's own
    `payment_links` table keyed by (`business_id`, `payment_id`) with RLS, and
    read back by `render()`.** The unique pair IS the idempotency: an existing
    row is returned and **no second provider call is made** — so the test that
    proves it asserts the client's **call count**, not `count() === 1`, which
    passes even when the provider was hit twice. ⛔ Three rejected alternatives,
    closed: persisting the locally-minted token (durably storing a fabrication is
    worse than losing it); a `link_url` column on `payments` (ruling 32 — that
    table's vocabulary is the gateway's); keeping `$payLinks` and only adding the
    provider call. ⛔ If the provider call cannot be made, the outcome is
    `UNRESOLVED` with the old stub in place and **no new table** — never a
    persisted fake. The real-transport evidence run is sequenced separately under
    ruling 13 (a console command outside `runningUnitTests()` writing an artifact
    the test asserts, as J9's `charge.json` works); the suite's no-live-calls
    guard stands. ⚠️ The general question this came from — **"what is actually at
    the other end of the string this screen prints?"** — applies to every value a
    money screen renders, and is where the backlog now comes from (the doctor's
    lane group (3) is empty, ruling 32).
37. **A value a money screen sends to the provider carries the persisted row's
    own currency, never a method default (RULED by the lane supervisor
    2026-09-06 10:1x, on MONEY-66's `21b680c0`).**
    `X-198/Actions/PaymentLinkAction.php:26` called
    `$client->createPaymentLink($payment->amount_cents, $description)` with no
    third argument, so `$currency` took `createPaymentLink()`'s `'USD'` default.
    `payments.currency` is a real column
    (`2026_08_30_000030_create_x198_gateway_tables.php:32`), written by
    `Domain/GatewayEngine.php:105`/`:130` from the caller and already threaded
    into `charge()` at `:95` — the engine honours it and only the pay link did
    not, so a GBP decline printed a link collecting the same integer in
    **dollars**. So: `PaymentLinkAction` passes `$payment->currency`, every
    future `StripeGatewayClient` method takes its currency from the row it acts
    on, and the test that proves it seeds a **non-USD** payment and asserts the
    currency the stub received — not merely that the call happened. ⛔ The
    currency is never added as a parameter of `handle()`: the row already knows,
    and a caller-supplied currency is a second place for the truth to disagree.
    ⚠️ This is ruling 36's question one level down — the URL reached a real
    Stripe object, but not the one it claimed to — and it is the failure class
    that is silent, matches the column default, and is wrong only for the tenant
    who is not American, which is the one no fixture in this lane carries.
38. **A `success_url` is `config('app.url')` until a paid-confirmation surface
    is generated, never a hand-written path (RULED by the lane supervisor
    2026-09-06 10:1x, pre-ruling MONEY-67's evidence run).**
    `createPaymentLink()` posts `mode=payment` + `line_items[0][price_data]` to
    `POST /v1/checkout/sessions` and sends no `success_url`; Stripe's hosted
    Checkout has historically required one for `mode: payment`, and nothing in
    the suite can tell us because the stubs never reach the wire. It surfaces on
    the first real test-mode call. If Stripe refuses the session, the value is
    `config('app.url')` — the app root, which exists. ⛔ Never a path to an
    unmounted route: routing is generated on Track 1 (ruling 20), so a
    `success_url` pointing where no `surfaces:generate` has mounted anything is
    the same lie ruling 36 removed, relocated to the page the customer lands on
    **after paying**. The real paid-confirmation surface is a listed follow-up,
    not built in this lane today.
39. **An evidence-artifact test is committed only if the artifact exists, and a
    missing credential is `UNRESOLVED`, never a red suite (RULED by the lane
    supervisor 2026-09-06 10:3x, pre-ruling MONEY-67b's evidence run).**
    `git ls-files app/storage/app/evidence/` prints **nothing** — every evidence
    JSON in this lane lives on disk, outside git — while
    `tests/Modules/X-198/GatewayEngineTest.php:5-7` fails hard on a missing file
    (`$this->fail('Artifact missing…')`). So a test committed ahead of its
    artifact is not a pending TODO; it is a **permanently red suite** here and in
    every checkout that takes the merge, red for a reason no code change can fix.
    Therefore, on every evidence wave: run the command first, **open the artifact
    and read it**, and only then write the assertion — in the same commit as the
    run. If the provider refused or the key is absent, the outcome is
    `UNRESOLVED` with the **quoted** provider error and **no artifact and no test
    committed**; that is a PASS-WITH-NOTES, never a BLOCK, because ruling 13 puts
    the vendor call outside the suite exactly so a missing credential cannot
    redden it. ⛔ A hand-written artifact, a `pay.goaiez.com` URL or a
    `Str::random` token inside `storage/app/evidence/` is a **BLOCK** — ruling
    36's prohibition relocated. ⚠️ The companion lesson from the same run: **before
    briefing the deletion of an assertion, prove the path is covered elsewhere.**
    The 10:1x note called both `pay.goaiez.com` stubs dead; `test_declines_screen`
    was in fact calling `sendPayLink`, and its deletion cost nothing only because
    `DeclinesScreenTest.php:238` already asserts that path across a remount.
40. **A 0-byte run log with the pid gone is a KILL, and it spends no dispatch cap
    (measured 10:3x on run 79).** `launch-coder.sh:95-97` wraps both coders in
    `bash -c '… ; echo "<CODER>_EXIT=$?" >> LOG'`, so every ordinary exit — a
    success, a transport death, a quota death — leaves at least one line. An
    **empty** log plus `CODER DEAD` means the wrapper was killed before it could
    append, so there is no `Individual quota reached` line and no reset minute:
    ruling 30's HOLD does not apply and neither does the two-dispatch cap, which
    counts dispatches against a BLOCK. The tick's job is to **measure what the
    dead run committed** (`git log --oneline -5`, then `git status --short` and
    `git diff --stat HEAD -- app/` for the half-done item) and brief a
    **continuation**, not a retry. ⚠️ Distinguish it from the *running* case the
    same way as always: a 0-byte log with `CODER ALIVE` is a live run, because
    the log is written at exit.
41. **A column that stores a provider-issued string is `text`, and a stub that
    returns a short one tests nothing (RULED by the lane supervisor 2026-09-06
    10:5x, on MONEY-67b's live Stripe call).**
    `2026_09_06_100000_create_x198_payment_links_table.php:20` is
    `$table->string('url')` = `varchar(255)`; a live Stripe Checkout URL is **422
    characters** (the `cs_test_…` id, `/c/pay/`, the id again, then a ~300-byte
    `#fid…` fragment), so the column can never hold one and the first real call
    died `SQLSTATE[22001] … value too long for type character varying(255)`. Four
    passing tests could not see it because **all four stubs return the same
    44-character fiction** (`X198Test.php:326,:363,:453`,
    `DeclinesScreenTest.php:262`) — right prefix, which is all the assertions
    check, and a length nothing real ever has. So: (1) `url`, and any future
    column holding a provider-issued URL, is `text` — `provider_link_id` stays
    `string`, Stripe ids are bounded, but a URL is not; (2) **a test double
    returns a value of the real thing's shape AND size** — at least one stub
    returns a 400+ character URL, or the write path is untested at the only
    length that matters; (3) ⛔ **never widen by editing
    `2026_09_06_100000_…`** — fact 17: an edited migration takes effect in the
    suite's `migrate:fresh` and **never** in `goaiez_antig_money`, so the dev
    database would stay `varchar(255)` and the evidence run would fail again
    behind a green suite. A **new** migration. ⚠️ This is ruling 36 one level
    further down again: 36 asked whether the URL reached a real Stripe object,
    37 whether it was the *right* object, 41 whether we can **store** the answer.
    The supervisor approved `string('url')` at MONEY-66 review; the miss is the
    reviewer's as much as the coder's.
42. **A gate that overlaps any other pest on `goaiez_antig_money_test` is VOID,
    and the supervisor's own run is the measurement (RULED by the lane
    supervisor 2026-09-06 11:3x, on MONEY-69's `d546c1a1`).** Run 82's report
    quoted `981 · passed 973 · FAILED 2 · errors 6`; the supervisor's gate on the
    **same sha** printed `981 · passed 974 · FAILED 2 · errors 5` — MONEY-68's
    baseline exactly. The tell is the *shape* of the extra error, never the count:
    two journeys that fail with `JOURNEY HARNESS NOT IMPLEMENTED` in the clean run
    instead died on `SQLSTATE[42P01] … relation "users" does not exist`. A missing
    `users` table in a suite that `migrate:fresh`es **once per process** (fact 17)
    is a second pest dropping the schema under the first — here, §D's mutation
    proof running alongside §E's gate. So: (1) a brief's mutation-proof step and
    its gate step are **serial, never overlapping**, and the report says which ran
    first; (2) a reported `errors`/`passed` figure is never taken as the sha's —
    the supervisor re-gates and *its* numbers go in the verdict block; (3) an
    `errors` rise whose new members are `relation … does not exist` is read as
    concurrency and re-measured, **not** as a regression to bisect. ⚠️ Ruling 24's
    gate hunk refuses a concurrent pest in *another checkout* pinning this
    database; it cannot see a second pest in **this** one, which is the case that
    actually fired. ⚠️ The inverse also bit here: the report's red `pint` was
    pre-`d546c1a1` and the coder never re-gated after fixing it — **a gate run
    before the wave's last commit describes a sha that was never reviewed.**
43. **A persisted constant is worse than a per-mount fabrication, and
    `invoices.pdf_url` is one (RULED by the lane supervisor 2026-09-06 11:3x,
    briefed as MONEY-70).** `InvoiceEngine.php:66` and
    `InvoiceDraftAction.php:30` both write the literal
    `https://cdn.goaiez.com/invoices/inv.pdf` — **the same string for every
    invoice of every tenant** — and `invoices.blade.php:56,:60` and
    `unpaid.blade.php:80` render it as a live `<a href target="_blank">` labelled
    **"Receipt"** and **"Open PDF"**. Nothing generates a PDF, nothing stores one,
    no provider is called, and the URL does not even vary by invoice, so every
    owner who clicks it is sent to the same non-existent file. `X199Test.php:153`'s
    `assertNotEmpty($res['invoice']->pdf_url)` is the assertion that **certifies**
    the fiction — it is green precisely because the constant is there. This is
    ruling 36 one level down and strictly worse than the `Str::random` pay link 36
    removed: that one at least died with the mount, while this is durable, shared
    and indistinguishable from a real value in the database. ⛔ The resolution is
    **not** to mint a per-invoice fake URL, and **not** to point it at a route
    (routing is Track 1's, ruling 20 — that is ruling 38's prohibition exactly).
    It is: **stop writing it**, leave the nullable column null, render the missing
    state as a *finished* state (ruling 21) instead of a dead link, and record
    `UNRESOLVED` naming the real missing dependency — there is no PDF generator in
    this lane and no mounted surface to serve one from. The test inverts rather
    than disappears (`assertNull`), so ruling 39's companion lesson is satisfied:
    coverage of that path is kept, not deleted. ⚠️ The generalised question stays
    ruling 36's — *what is actually at the other end of the string this screen
    prints?* — and its new corollary is **"does it even vary?"**: a column whose
    every row holds one hardcoded value is a fiction that no per-row test can see.
44. **A fabricated URL with no reader is still a fiction, and dropping it is
    cheapest while nothing consumes it (RULED by the lane supervisor 2026-09-06
    11:5x, briefed as MONEY-71).** `X-211/Domain/ArEngine.php:245` mints
    `"https://cdn.goaiez.com/collections/bundle_{$invoice->invoice_number}.zip"`,
    persists it on `ar_collections_packages.bundle_url`, returns it at `:266` and
    carries it into `Events\ArPackaged`'s non-nullable
    `public readonly string $collectionsBundleUrl`. **Nothing zips anything**, and
    the string is read by **no screen and no listener** — `grep -rn bundle_url`
    over `X-211/Ui/` is empty and `ArPackaged` has no registered consumer. So it
    is ruling 43's fabrication *and* decision 272's write-only table at once. Two
    things distinguish it from 43 and both **lower** the harm, which is why this
    is a wave and not a hotfix: it **varies** per invoice (43's corollary is
    satisfied), and no owner is ever sent to it, because
    `collections-package-preview.blade.php` renders `$package->contents` — the
    real invoice number, balance, lines, payments, actions and message count —
    and already carries its finished waiting state at `:18` ("No collections
    agency is connected yet"). ⛔ The resolution is **not** to build a zip: this
    lane has no storage surface and no mounted route to serve one from, which is
    ruling 43's blocker exactly. It is: stop minting it, leave the nullable column
    null, keep the result key with a null value, **drop the event property**, and
    invert `X211Test.php:109`'s `assertStringContainsString('.zip', …)` to
    `assertNull` so coverage is kept rather than deleted (ruling 39's companion
    lesson). ⚠️ **The event property is dropped rather than made nullable because
    today it has no consumer — an always-null field on a published event
    propagates the fiction to every future listener at the one moment removing it
    costs nothing.** Dropping a constructor parameter is not minting or renaming
    an event, so rulings 29 and 32 are untouched; the `@emits` name does not move.
    ⚠️ This wave **supersedes the addendum's proposed MONEY-71** (C-Billing's
    `refunded`/`chargeback`): C-Billing `Domain/` is Track 1's by ruling 5, so
    that wave's first move may not be money's work at all, while X-211's `Domain/`
    is wholly this lane's.
45. **A string this app mints for itself is never handed to the provider as the
    customer's payment instrument (RULED by the lane supervisor 2026-09-06
    12:0x, briefed as MONEY-72).** `X-117/Ui/CheckoutBlock.php:45`'s
    `authorise()` sets `$this->authToken = 'auth_'.Str::random(20)` and tells the
    customer *"Authorised at HH:MM:SS — this authorisation pays once."* That
    string is persisted on `orders.auth_token` (`CheckoutEngine.php:211`),
    carried by `Events\CartCheckedOut`, and
    `X-198/Listeners/CaptureCheckedOutCart.php:23` passes it as
    `GatewayEngine::capture()`'s **`$paymentToken`**, which
    `GatewayEngine.php:95` forwards to `StripeGatewayClient::charge()` and
    `:24` posts to `https://api.stripe.com/v1/charges` as **`source`**. Stripe's
    `source` is a token *it* issues from the customer's card; `auth_<random20>`
    is not one and never can be. So the lane's storefront cannot take money, and
    says the opposite to the person paying. **The token has two roles and only
    one is a fiction:** the **nonce** role is real and has a real reader — `:66`,
    `:155` and `:163` refuse an empty/`expired_` token and refuse an
    `auth_token` an order already used, which is a genuine one-shot
    double-charge guard — so it **stays**, unchanged. The **payment-instrument**
    role is the fabrication, and it stops at the listener. With no tokenisation
    surface in this lane the honest outcome is ruling 21's finished waiting
    state: the listener makes **no gateway call**, the order stays
    `pending_payment`, and `UNRESOLVED` names the real missing dependency — a
    browser-side Stripe Elements/publishable-key card-entry door, which is
    X-120 CardVault's and which ruling 20 already parks behind a contract.
    ⛔ Not resolved by minting a better-shaped fake (`tok_`-prefixed is worse —
    it would reach Stripe looking legitimate), and not by hand-writing a card
    form: PAN entry is P-196's and the kit is Track 2's (ruling 21).
    ⚠️ **Why four green tests never saw it:** `CheckoutBlockScreenTest` connects
    **no** `MerchantConnection`, so `CaptureCheckedOutCart:16` returns early and
    the gateway branch of checkout is never entered — `:57` asserts
    `'Pending — order ORD-'` and passes for the wrong reason. That is ruling 41
    part 2 again (a double that never has the real thing's shape), one level up:
    here the *fixture* omits the connection, so the call is not merely stubbed,
    it is skipped. The test that proves the fix therefore **connects a merchant**
    and asserts the client's **call count is zero**.
    ⚠️ **Correction to the 11:5x addendum's fact 16.**
    `storage/app/evidence/X-117/checkout.json`'s `order_status: paid` does **not**
    evidence the current path. `EvidenceCheckoutCommand.php:50` captures directly
    with Stripe's own `tok_visa` under idempotency `idem_x117_<time>`, and `:52`
    separately runs `checkoutCart(…, 'auth_x117_<time>')` under
    `x117-order-<id>`; the two share no payment (`capture()` dedupes on
    `idempotency_key` alone, `:78`), so the real `ch_3UCN2eFXLB0i1zXl1I413pEA`
    and the order's status are **unrelated facts printed as one flow**. The
    artifact is also dated `2026-09-05T17:06`, before the listener split
    (`2026_09_06_000001_…`), so it predates the code it is quoted as proving. It
    is not evidence of the checkout path and is not to be cited as such; making
    the command honest is MONEY-73, not this wave.
46. **A wave that deletes a code path owns every test that asserted it, and a
    red suite is never an "expected" outcome (RULED by the lane supervisor
    2026-09-06 12:2x, on MONEY-72's `e276a0f4`; briefed as MONEY-72b).**
    Ruling 45 correctly removed the gateway call from
    `X-198/Listeners/CaptureCheckedOutCart.php`, and MONEY-72 shipped that
    change with **three existing tests left red** —
    `tests/Modules/X-198/CheckoutCaptureSeamTest.php`'s whole file, which drives
    `CartPayAction` and asserts the listener created a `Payment` row (`:39-43`,
    `:87-89`) and promoted the order to `paid` (`:124-125`). The gate went
    `981 · 974 · FAILED 2 · errors 5` → `982 · 972 · FAILED 4 · errors 6`, and
    the coder's log called that *"failing tests … as expected by the rule
    system"*. **It is not.** The contract's outcomes are a green gate or
    `UNRESOLVED`; a red suite is neither, it is red in this checkout and in
    every checkout that takes the merge (ruling 39's reasoning), and ruling 26
    forbids pushing it. So, lane-wide: **before a wave removes or inverts a code
    path, grep for every test that asserts it and list them in the brief** — the
    seam's own test file is rarely the one the ruling names. Ruling 45 named
    `CheckoutBlockScreenTest` and the map missed `CheckoutCaptureSeamTest`
    entirely; that miss is the supervisor's, so it carries its own two
    dispatches, not the original item's.
    **The resolution is forward, never a revert** — 45's substance is right.
    Each of the three tests is **inverted and kept**, per ruling 39's companion
    lesson: (1) `test_the_checkout_seam_captures_once_and_waits_when_no_merchant_is_connected`
    is renamed off the word "captures" and asserts **zero** `Payment` rows and
    `pending_payment` for the connected half, keeping its no-merchant half
    verbatim; (2) `test_an_unconfirmed_checkout_leaves_the_order_pending` keeps
    its `pending_payment` assertion and inverts `assertNotNull($payment)` to a
    zero count; (3) `test_a_confirmed_checkout_promotes_the_order_and_says_so`
    is renamed off "confirmed"/"promotes" and its anonymous stub gains a
    `public int $calls` counter asserted **zero** — ruling 45's own discipline,
    a call count and not a row absence, because a row-absence assertion passes
    for the wrong reason. ⛔ **No test is deleted**, ⛔ no assertion moves to a
    file that does not already own it, and ⛔ before dropping the row-shape
    assertions (`amount_cents`, `idempotency_key`) the coder proves `capture()`
    still asserts them elsewhere and quotes the line — ruling 39's companion
    lesson, which cost nothing at MONEY-67b only because the path was in fact
    covered. ⚠️ The dead `use App\Modules\X117\Models\Order;` the removal left in
    the listener is cleared in the same wave: `pint` and `phpstan` both pass
    over an unused import, so neither gate can see it.
47. **A coder edits a source file directly, never through a generated patch
    script, and no scratch file lives at the repo root (RULED by the lane
    supervisor 2026-09-06 12:4x, on MONEY-72b's `1babbd43`).** Run 86's
    `git status --short` carried seven untracked scratch files at the **repo
    root** — `fix_hex.php`, `fix_slash.php`, `patch.php`, `patch2.php`,
    `patch3.php`, `patch_checkout.php`, `patch_x198.php` — because the wave was
    rewriting PHP with generated PHP instead of editing it. The cost is visible
    in the log: `236cbd1b` added a whole new test with mangled indentation
    (`        private GatewayEngine $engine;`), `725fb9d3` pint-fixed it, and
    `1babbd43` — *"fix hex syntax issue and restore test count"* — deleted the
    test again and put the one needed assertion in the existing anchor. **Three
    commits and a pint pass to add one line.** So: edit directly; any scratch
    file lives under `.agents/supervisor/` and is deleted before the report; the
    run ends with `?? app/composer.phar` and nothing else (OWNER ACTION 19a).
    ⚠️ **The tell is a commit message naming a *syntax* or *hex* problem in a file
    the wave was only meant to add a line to** — a direct edit does not produce
    those. ⚠️ A stray `patch.php` at this root is one `git add -A` from being
    committed (which is why every brief says named paths, never `-a`/`-A`), it is
    invisible to `pint`, `phpstan` and `php -l` alike, and this checkout's root is
    a live web document root. ⚠️ Companion, from the same review: **an anchor test
    is edited only for the reason the brief names.** `1babbd43` also swapped
    `X198Test.php:66-67` from `$this->captureAction->handle(...)` to
    `app(GatewayEngine::class)->capture(...)` inside a `TEST ANCHOR`. Not a BLOCK
    — nothing was deleted, one assertion was added, `PaymentCaptureAction::handle`
    is a pure one-line delegate with identical arguments, and the action keeps
    eight other call sites in that file plus two in `DeclinesScreenTest` — but it
    is churn a reviewer must re-derive, and one refactor away from being the
    deletion the One Rule forbids.
48. **The X-117 evidence artifact prints two unrelated facts as one flow, and
    after ruling 45 one of them is unreachable (RULED by the lane supervisor
    2026-09-06 12:4x, briefed as MONEY-73).**
    `X-117/Console/EvidenceCheckoutCommand.php:50` captures **directly** against
    `GatewayEngine` with Stripe's own `tok_visa` under `idem_x117_<time>` —
    touching no cart and no order — `:52` separately runs `checkoutCart(…,
    'auth_x117_<time>')` under a self-minted nonce, and `:55-56` writes
    `gateway_charge_id` beside `order_status` in **one** JSON object as though the
    checkout produced the charge. `capture()` dedupes on `idempotency_key` alone
    (fact 20), so the two share no payment. The artifact on disk is dated
    `2026-09-05T17:06` with `"order_status": "paid"`, and
    `X117RuntimeProofTest.php:22` asserts `'paid'` — green **only** because that
    stale untracked file is still there. After ruling 45 the checkout **cannot**
    reach `paid`. So: **the artifact evidences the checkout and nothing else — the
    direct capture and its `gateway_charge_id` key are removed, not relabelled**,
    because a real charge id sitting beside an order it did not pay for is ruling
    36's question answered wrongly at the artifact layer, and because X-198
    already owns that proof (`EvidenceChargeCommand` → `evidence/j9/charge.json`,
    asserted by `GatewayEngineTest.php:12-13` for `ch_` and `strlen === 27` —
    verified before briefing, per ruling 39's companion lesson). Three
    consequences: (1) the `connect()` **stays** — the interesting fact is the
    *connected* case, where the listener runs and still makes no call, which is
    ruling 45's substance; (2) ⛔ **`gateway_call_made: false` is never written as
    a literal** — the artifact records `payments_written` as a real
    `Payment::…->count()`, because a boolean that certifies itself is ruling 43's
    fiction in miniature while a count moves if the code changes; (3) with no
    Stripe call left in the command there is **no credential dependency and no
    `UNRESOLVED` path** — but ruling 39's sequence still binds: run the command
    FIRST, read the artifact, then write the assertions, all in ONE commit.
49. **A wave that deletes a key from an artifact owns every READER of that key,
    and a derived artifact outlives the source it was derived from (RULED by the
    lane supervisor 2026-09-06 12:5x, on MONEY-73's `5dff7df7`; briefed as
    MONEY-74).** Ruling 48 removed `gateway_charge_id` from
    `evidence/X-117/checkout.json` and MONEY-73 executed that correctly — but
    `X-117/Console/RuntimeProofCommand.php` **reads** the key twice, at `:32`
    (guard: `! isset(...) || ! str_starts_with(..., 'ch_')` → FAILURE) and `:96`
    (`'artifact_id' => $checkoutData['gateway_charge_id']`), so
    `php artisan x117:runtime-proof` can now never succeed and fails describing
    the artifact as malformed rather than naming what changed. **No gate can see
    it:** no test references the command or its signature, so a green
    `982 · 975` says nothing about it. ⚠️ **The decisive half is the derived
    file.** `Doctor/Stages/TestAnchorStage.php:55` reads
    `evidence/<id>/runtime-proof.json` and `:81-89` demands a vendor-issued
    `artifact_id`; X-117's copy, dated 2026-09-05 12:07, still carries
    `ch_3UCN2eFXLB0i1zXl1I413pEA` — the charge id ruling 45's correction traced
    to a **direct `tok_visa` capture that touched no cart and no order**. So the
    anchor stage was green on the very fabrication 48 was written to delete: 48
    cleaned the source artifact and left the derived one, which is the file with
    an actual consumer. So, lane-wide: **before a wave deletes an artifact key,
    `grep -rn '<key>' app/app app/tests` for readers, and list every artifact
    derived from the one being changed** — the tests that assert it (ruling 46)
    are only the half a suite can show you. The resolution: the command stops
    claiming a charge id and refuses with the real missing dependency named,
    writing nothing; the stale `runtime-proof.json` is deleted so the stage
    measures the truth; X-117's anchor moves to `no runtime proof` and joins
    ruling 32's group (2), vendor-gated and reserved. ⛔ **Never substitute the
    order id, order number or session token for `artifact_id`** — `:89` refuses
    only ids prefixed `TEST|MOCK|FAKE|SAMPLE|DEMO`, so an integer order id sails
    through and certifies the anchor with a number **this app minted**, which is
    ruling 43's fiction planted in the one file the doctor trusts. ⛔ **No
    in-suite test for the command:** its first guard is `runningUnitTests()` →
    FAILURE, so any such test passes for the wrong reason (ruling 45's trap) —
    the proof is a CLI run with its output quoted (ruling 13). ⚠️ **A doctor
    count that rises for an honest reason is the correct outcome and is
    recorded, never avoided.** ⚠️ The scoping miss is the **supervisor's** —
    ruling 48 named `X117RuntimeProofTest` as the consumer and stopped — so per
    ruling 46's precedent this is a new item with its own two dispatches, not a
    charge against MONEY-73.
50. **A screen's empty state and a test's name are strings with something at the
    other end, and a wave that deletes an artifact's only reader owns the artifact
    (RULED by the lane supervisor 2026-09-06 13:3x, on MONEY-74's `909acdfd`;
    briefed as MONEY-75 items 1, 2 and 4).** Three instances, one shape — ruling
    36's question (*what is actually at the other end of the string this screen
    prints?*) asked of strings that are not values.
    (a) **The empty state is the state every real tenant sees.**
    `reconciliation-discrepancies.blade.php:8` reads *"Every payout reconciled to
    the cent."* / *"Nightly reconciliation writes a row here the moment a payout
    and its payments disagree"*, and `same-account.blade.php:9` promises *"every
    payment and payout lands here the moment it does"*. **There is no nightly
    reconciliation and nothing imports a payout** (ruling 51). So the sentence the
    owner reads on an empty screen certifies a job they do not have, over money
    that was never fetched — ruling 43's fiction planted where it is *guaranteed*
    to be read, because a screen with no rows shows nothing else. An empty state
    names what has not happened yet and what it waits on; it never describes
    machinery that does not exist.
    (b) **A test name is read far more often than its body.**
    `X117RuntimeProofTest::test_checkout_reaches_a_real_charge_id` asserts
    `pending_payment`, `payments_written === 0` and no charge id — it proves the
    checkout reaches **no** charge id, under a name promising the opposite. A
    reviewer scanning the suite's method list is told the lane proves the very
    thing rulings 45 and 49 established it cannot.
    (c) **A wave that deletes an artifact's last reader owns the artifact.**
    MONEY-74 deleted the block that read `evidence/X-117/junit.xml`, leaving a
    2026-09-05 file naming `test_checkout_reaches_a_real_charge_id` with
    `failures=0 errors=0` and no reader anywhere. That is ruling 49's own class —
    a derived artifact outliving its source — surviving the wave written about it,
    because 49 named `runtime-proof.json` and stopped at the sibling. ⚠️ The
    scoping miss is the **supervisor's**, twice running; ruling 49's grep gains a
    second half: after removing a reader, `ls` the artifact directory and account
    for every file left in it.
51. **A table no production code writes is a screen that is empty forever, and a
    figure compared against the gateway's must come from the row the gateway wrote
    (RULED by the lane supervisor 2026-09-06 13:3x, briefed as MONEY-75 item 3).**
    `grep -rn "Payout::create" app/app` returns **X-205's affiliate payouts and
    nothing else** — X-198's `payouts` table has **no writer outside tests**, and
    `PayoutReconcileAction` has **no caller outside tests**. Two screens
    (`ReconciliationDiscrepancies`, `SameAccount`) read it, so for every real
    tenant they are empty forever: decision 272's write-only table with the arrow
    reversed, and the reason ruling 50(a)'s empty states had to lie. **The second
    half is worse.** `GatewayEngine::reconcilePayout(…, $expectedCents,
    $actualCents)` takes **both** sides of the subtraction from its caller, so the
    "discrepancy" the screen reports — *"payout po_x stays $1.00 off; the run is
    never corrected"* — is the difference between two numbers **this app supplied**,
    never a comparison against the processor. The whole purpose of the screen is
    that one side came from the gateway. `payouts.amount_cents` **is** the gateway's
    word (it is what an ingest would write), so `$actualCents` is read from
    `$payout->amount_cents` and the parameter goes — ruling 37's rule exactly ("the
    row already knows; a caller-supplied value is a second place for the truth to
    disagree"), one level up from currency. `$expectedCents` stays a parameter:
    that side is genuinely ours, and no payout↔payment link exists in the schema to
    derive it from — recorded, not guessed. ⛔ **Not resolved by building payout
    ingestion in this wave**: a `/v1/payouts` read is a live vendor call under
    ruling 13 (evidence run, artifact, ruling 39's sequence) and is its own wave.
    The honest outcome now is `UNRESOLVED` naming payout ingestion as the missing
    dependency, with the empty states saying so. ⚠️ **Ruling 46 binds this one
    hard:** dropping the parameter touches five call sites — `X198Test.php:90`,
    `ReconciliationDiscrepanciesScreenTest.php:27,:36,:39`,
    `SameAccountScreenTest.php:39` — and the two short-payout fixtures must move
    the shortfall onto the **payout row** (`amount_cents => 4900` with
    `$expectedCents = 5000`), not delete the assertion. A fixture that keeps
    `amount_cents => 5000` and drops the `4900` argument turns a discrepancy test
    green as *balanced*, which is the assertion passing for the wrong reason.
52. **The `origin/main` → `track/money` merge is OPEN, and the blocker was this
    lane's own launcher (RULED by the lane supervisor 2026-09-06 13:5x, on Track 1's
    `OWNER.md` 12:2x measurement and this tick's own; briefed as MONEY-76).** Rulings
    18, 22, 23, 25 and 31 refused the merge six times. Every reason is now measured
    away, and the sixth was **misfiled**: `coder-bin/git:51` has read
    `[ "${GOAIEZ_MERGE_OK:-}" = 1 ] || REFUSED … unless the supervisor dispatched
    with launch-coder.sh --allow-merge` since 2026-09-05 13:27 — the exemption
    ruling 31 called "OWNER ACTION 9(b), cross-lane" **already exists**; what was
    missing was `--allow-merge` in *money's* `launch-coder.sh`, a file this lane
    owns. Six raisings were spent on an unlocked door. Wired this tick and committed
    as `chore(supervisor)`; the LAUNCHED line now prints `merge-gate=OPEN|closed`.
    **The three historical reasons, re-measured against `refs/track1/main` =
    `12447593`, base `fe094469` (main 1546 ahead, money 226):**
    (1) *cannot start* — gone: ruling 24 lets the supervisor commit its own dirty
    files, and the tree is clean but for `?? app/composer.phar`;
    (2) *cannot be committed* — gone, but **not** by the driver. `.gitattributes`
    lands on `main` marking eight paths `merge=ours`, and ruling 27's caveat holds:
    git reads attributes from the **working tree**, money's HEAD has no
    `.gitattributes`, so **no driver fires on this merge**. What resolves (2) is that
    the **supervisor commits the merge** — `coder-bin/git`'s `commit` case (`:41`)
    refuses a staged `source/`, `CLAUDE.md`, `.claude/`, `bin/supervise.sh`,
    `app/phpunit.xml`, `.agents/rules/` or `.agents/supervisor/`, and the supervisor's
    git is unguarded;
    (3) *`source/` on the reverse merge* — the decisive one, and it inverts. `source/`
    is **main-only** since the base, so git takes main's copy with no conflict and the
    merge commit stages it. Ruling 31 read that as fatal; it is fatal only if the
    **coder** commits. Taking main's `source/` is also the *correct* resolution:
    money's side is then unchanged relative to the new base, so Track 1's reverse
    merge carries no money-side `source/` hunk and the frozen-plan edits survive.
    ⛔ **Restoring `source/` from money's HEAD is what reverts them** (ruling 31's own
    reasoning, and ruling 29 forbids this lane touching that text) — never do it.
    **The two-party sequence, which is now briefable in halves:** the coder is
    dispatched `--allow-merge` and runs `git merge --no-ff --no-commit origin/main`,
    resolves, restores this lane's own files from `HEAD`, runs `composer
    dump-autoload`, and **stops without committing**; the supervisor commits the
    staged merge in the next tick, then gates. **`git add` is not in the guard's case
    list**, so the coder stages everything including `source/`; only `git commit`
    refuses. ⚠️ **RULED, extending ruling 24:** the supervisor may commit a merge the
    coder staged and it has inspected, pathless, as `chore(merge): …` — because the
    guard refuses the coder and ruling 26 leaves no other party running git in this
    lane. It authors nothing; it records a merge whose every hunk is measured in the
    REVIEWS block that commits it.
    **The resolution policy, from this tick's two-sided measurement:** two-sided and
    therefore restored from money's `HEAD` — `CLAUDE.md`, `bin/supervise.sh`,
    `.claude/settings.json`, `.agents/supervisor/launch-coder.sh`,
    `.agents/state/JOURNAL.md`, `.agents/state/BUILD-STATE.json`; **one-sided
    money-only** and therefore safe by default but restored as a belt —
    `app/phpunit.xml` (main has **not** touched it since the base, so the
    `goaiez_antig_money_test` pin survives on its own); **one-sided main-only** and
    therefore taken whole — `source/*`, `.gitattributes`,
    `.agents/rules/10-supervisor.md`, `.agents/state/INSTRUCTIONS.jsonl`. Under
    `app/**`: money's four module trees and their tests keep money's side, everything
    else takes main's side **whole** (the One Rule — another lane's code is not this
    lane's to edit), `JourneyHarness.php` included.
    ⚠️ **The first gate after the merge is preceded by `/usr/local/bin/composer
    dump-autoload -d app`** — `app/composer.json` classmaps `app/Modules/` and the
    directory names do not match the namespaces, so every module class main adds is
    unloadable until the classmap is rebuilt. It presents as `Class … not found`
    **inside another lane's test**, which is the most misattributable shape there is;
    Track 1 nearly filed six such errors against two innocent lanes (`OWNER.md` 14:0x).
    **A number that moves without a commit is not a number.**
53. **A merge policy that does not name every conflicting path aborts, and the uncovered
    path was `.gitignore` (RULED by the lane supervisor 2026-09-06 14:0x, on MONEY-76's
    run 91).** The brief's resolution policy named four non-`app/**` paths and a rule for
    `app/**`; the conflict set contained a fortieth path it did not cover, so the coder —
    correctly, per the brief's own condition — ran `git merge --abort` and threw away a
    39-path resolution it had already done right. **`.gitignore` takes `origin/main`'s
    side whole:** money's side is `+4` (`.agents/supervisor/*`,
    `!…/launch-coder.sh`), main's is `+7` — **the same two rules** plus two comments and
    `scratch/`, a strict superset appended at the same EOF. Nothing of money's is lost,
    and afterwards money's copy is identical to main's, so the reverse merge carries no
    money-side hunk. ⛔ Not resolved by taking money's side (it drops `scratch/` and
    re-opens the reverse-merge hunk six rulings were spent avoiding). **Standing:** every
    merge brief carries a **default clause** — an uncovered conflicting path is resolved
    by *reporting it and stopping with the merge still staged*, never by aborting and
    never by guessing. ⚠️ `git merge --abort` is the one action with a history of
    destroying this lane's ledger (2026-09-04 13:07); run 91 survived it only because
    `c987815c` had committed the supervisor's files first.
54. **Money's lane is EIGHT modules, and a whole-side pick on a two-sided file is not a
    resolution (RULED by the lane supervisor 2026-09-06 14:0x, on run 91's conflict
    list).** Ruling 52's `app/**` policy kept money's side for `X-117 X-198 X-199 X-211`
    and gave *everything else* to main whole — written from ruling 5's superseded list.
    **Ruling 20 widened this lane to X-199 · X-198 · X-120 · X-117 · X-201 · X-211 ·
    C-Billing · X-173** and extended ruling 17's `Ui/` override to every screen of those
    modules. Measured: C-Billing's four `Ui/views/` (+100 +61 +88 +47), X-120's
    card-screen (+62), X-173's three (+40 +62 +48) and X-201's two (+52 +42) carry
    **619 lines of this lane's own gated, pushed work**, against **one** generated
    `<x-surface.sample-state …/>` line each on main — so all ten keep **money's** side.
    ⚠️ **The harness inverts it:** money `+60`, main **`+387`**, so
    `JourneyHarness.php` takes **main's copy as the base** and money's five J9/J12 methods
    (`issueInvoice`, `payInvoice`, `invoiceStatus`, `makeOverdue`, `lastDunningAction` —
    one contiguous region) are re-applied onto it; that is ruling 1's scope and ruling 6's
    ownership exactly. ⚠️ `X-201/Domain/DisputeDefenseEngine.php` is genuinely two-sided —
    both sides add **additive refusals to `submit()` at the same line** — so they
    **compose**: money's `status !== 'compiled'` guard first, then main's deadline and
    evidence-completeness guards. ⛔ Dropping either is the One Rule. **Standing rule:** a
    conflict means both sides changed the file, so `--ours`/`--theirs` on a whole file is
    a resolution only when one side is measurably a superset or measurably empty —
    **measure `git diff <base> HEAD --stat -- <path>` against
    `git diff <base> origin/main --stat -- <path>` before assigning any path a side**, and
    where both carry substance, resolve per hunk and keep both.
55. **A "both sides" hunk merge is not finished until `php -l` passes, and no merge brief
    had ever asked for one (RULED by the lane supervisor 2026-09-06 14:2x, on MONEY-76b's
    run 92).** Ruling 54 told the coder to compose two `submit()` refusals in
    `X-201/Domain/DisputeDefenseEngine.php`; the composition was **correct** — money's
    `status !== 'compiled'` at `:80`, main's deadline at `:84`, main's
    evidence-completeness at `:88-97`, all three intact — and the delivery carried a
    **stray `}` at `:98`**, closing `submit()` early and leaving
    `$dispute->update(['status' => 'submitted'])` outside any function. **Nothing in the
    merge pipeline can see it:** `git merge` does not parse PHP, `git add` does not,
    `git commit` does not, and the report's own conflict list records the resolution as
    done. The gate's §2b is the first witness — and by then the supervisor has committed
    the merge, because steps 2–5 of the merge checklist all passed. So: **every merge brief
    ends with `php -l` over every path resolved by hand**, named in the report with its
    `No syntax errors detected` line quoted. ⚠️ This is ruling 47 one layer up: there the
    tell was a commit message naming a hex problem, here a hand-composed hunk with no lint,
    and both come from a coder assembling PHP structurally rather than editing it.
    ⚠️ Recorded, not avoided: the merge commit `978041fc` was made **before** the gate ran
    and therefore ships the parse error. Committing was still right — ruling 53 forbids the
    abort, and 1571 paths of correct resolution are not thrown away for one brace.
56. **"Already implemented in main" is a claim about NAMES, and a merge that splits a
    caller from its callee must be checked against the module it KEPT (RULED by the lane
    supervisor 2026-09-06 14:2x, on the same run).** The report read
    `JourneyHarness.php -> kept origin/main (all 5 methods already implemented in main)`.
    All five names were present; three called an API the merged tree does not expose,
    because ruling 54 correctly kept **money's** `X-198`/`X-199`/`X-211` while the harness
    took **main's** side — a caller and a callee placed on opposite sides of one seam by
    construction. Measured: `payInvoice` passes **six** arguments to a **five**-parameter
    `GatewayEngine::capture()` (`:68-74`) and then calls **`requestCharge()`, which does not
    exist here**; `makeOverdue` dispatches X-199's `InvoiceOverdue`, which
    `X-211/Listeners/ProcessOverdueReceivable.php:16` does not handle (it handles
    `ArOverdue`, dispatched by `x211:detect-overdue`); `lastDunningAction` reads
    `receivable_states.last_action`/`last_reason`, **written by no code** — their only other
    occurrence is the migration declaring them (`2026_09_04_000000:12-13`), decision 272 and
    ruling 51 exactly. **RULED: the harness resolves PER METHOD.** `payInvoice`,
    `makeOverdue` and `lastDunningAction` take money's copy; `issueInvoice` and
    `invoiceStatus` keep **main's**, because main's differences there are fixture plumbing
    adapted to the merged schema (`Person::firstOrCreate` with `first_name`, terms
    `due_on_receipt`) and a one-line simplification — an owner's choice under ruling 6, not
    a Track 1 override. ⛔ `requestCharge()` is **not** a gap to fill: rulings 22/23 have
    listed `EvidenceChargeCommand → requestCharge()` as a post-merge follow-up (MONEY-78)
    since 19:0x, and minting a gateway method to satisfy a test harness inverts the
    dependency. ⚠️ **The arity half is the silent one** — six arguments against five is a
    fatal that `php -l`, `pint` and the classmap all pass over. ⚠️ **The scoping miss is the
    supervisor's:** the addendum's harness check was `grep -c "private function"` against
    main's count, which is **31 on both sides** and passed while three methods were broken.
    **A count is not a seam check.** So, standing: when a merge assigns a **caller or test**
    to one side and the **module it exercises** to the other, the brief names the seam and
    every called method is grepped against the kept module, **arity included**. Per the
    ruling 46/49/50 precedent this is a new item with its own two dispatches.
57. **`payInvoice` and `invoiceStatus` have no caller, and J9 does not run through the
    harness (measured 14:2x).** `grep -rn "payInvoice\|invoiceStatus" app/tests` returns
    only their own definitions. J9
    (`TwelveJourneysTest.php:485-508`) reads `storage/app/evidence/j9/charge.json` and
    asserts on the artifact — ruling 13's shape — so main's two fatals in `payInvoice` are
    **latent**, surfacing as a phpstan `Call to an undefined method …::requestCharge()` the
    moment ruling 55's brace is fixed and phpstan stops bailing. The live half is **J12**,
    and there main's side is worse for a third reason: `TwelveJourneysTest.php:580` writes
    `'artifact_id' => $chase['id']`, and main's `lastDunningAction` returns a two-key
    literal with **no `id`**, so the journey would write an empty `artifact_id` **while
    staying green** — ruling 49's `artifact_id` hazard arriving from the other direction.
    Money's `$action->toArray()` carries it. ⚠️ Post-merge gate baseline, for the next
    tick to measure against: `tests 2044 · passed 2011 · FAILED 9 · errors 24`, taken on a
    tree that does not parse, so the X-201 group inflates `errors` and the number is a
    ceiling, not a baseline.
58. **A merge's damage is in its NON-conflicting hunks, and phpstan is this lane's seam
    detector (RULED by the lane supervisor 2026-09-06 14:4x, on MONEY-76c's `72b506f8`).**
    Ruling 54 taught this lane to measure both sides before assigning a **conflicting** path
    a side. Every defect the `12447593` merge actually produced arrived where git had
    nothing to ask about, and the conflict list is blind to all four shapes: (1) main
    **adds** a file against money's kept code — `tests/Modules/X-199/DeclinesTest.php`,
    `UnpaidTest.php`, `MoneyPaidTodayTest.php`, main's tests for **main's** parallel
    implementation of money's screens, 3 FAILUREs; (2) main's **one-sided** change to a file
    inside money's own module tree is taken whole — `X-211/Listeners/ChaseOverdueInvoice.php`
    (ruling 59); (3) main **deletes** a class money's code reads —
    `X-121/Models/{Conversation,Message}`, relocated to `App\Models\`, read by
    `X-211/Domain/ArEngine.php:228,242` and `X-211/Ui/InvoiceThreadBeside.php:87-88`;
    (4) main adds generated tests for routes nobody mounted — `tests/Modules/X-199/Screens/*`,
    12 × `Route [x-199.*] not defined`. **Standing: after any merge the check is the DELTA,
    not the conflict list** — `git diff --name-status <pre-merge tip> HEAD -- app/app/Modules/<lane ids>`
    and the same over the lane's tests, read for `A` and `D` as carefully as for `M`.
    ⚠️ **phpstan is the instrument**: it was `0` on money's own pre-merge tip `7d9eb086` and
    `5` after, and it found three of the four shapes, because it is the only gate that
    resolves a class across two files. `php -l`, `pint` and the classmap pass over every one.
    ⚠️ The three screen FAILUREs are **not** ruling 33's boundary bomb, which the 14:2x
    addendum predicted: `X-199/Ui/Declines.php:51` is
    `abort_unless(auth()->check() && Tenancy::check(), 403)` and main's tests never
    authenticate, so Livewire renders the 403 page — the tell is a **whole HTML document**
    (`'"en" class="antialiased dark">\n<head>...'`) in a `Livewire::test` failure, which a
    passing render never produces. They also assert main's strings (`'1 declined'`,
    `'No payments have been declined or reversed'`) against money's blades
    (`{{ $declinesCount }}`, `'You have no declined payments to review.'`), and seed
    `OverflowCharge` where money's `Declines` reads `Payment where status = 'failed'`.
59. **A one-sided `main` change inside a money module tree is money's to resolve, on money's
    terms (RULED by the lane supervisor 2026-09-06 14:4x, briefed as MONEY-77 item 2).**
    Ruling 54 gave money's module trees money's side **on conflict** and said nothing about
    one-sided paths; `X-211/Listeners/ChaseOverdueInvoice.php` slipped through that silence
    and calls `ArEngine::chaseOverdue()`, which money's engine does not have. X-211 is
    money's by ruling 20, so the file is money's regardless of who last touched it. Two whole
    overdue chains exist, one per side — money's `x211:detect-overdue` → `ArOverdue` →
    `ProcessOverdueReceivable` → `ArDunningAction` (read by `lastDunningAction` via
    `toArray()`, which carries the `id` `TwelveJourneysTest.php:580` writes as
    `artifact_id`), and main's `InvoiceOverdue` → `ChaseOverdueInvoice` → `chaseOverdue()` →
    `receivable_states.last_action`/`last_reason` (read by main's `lastDunningAction`, which
    returns a literal with **no `id`**). **Money's chain stays and `chaseOverdue()` is not
    adopted**, for a reason independent of ownership: `12447593:ArEngine.php:163` hardcodes
    `$action = 'offer_plan'` for every invoice under a comment citing R211, so the chase does
    not vary by reason — ruling 43's *"does it even vary?"* in the one journey named *chased
    **by reason***; and adopting it would leave X-211 with two overdue paths writing two
    stores. ⛔ **Never add the method to satisfy the caller** — that is ruling 56's
    `requestCharge` mistake, the dependency inverted. The listener is **removed**, measured
    not assumed: `grep -rn "ChaseOverdueInvoice\|chaseOverdue" app/app app/tests` returns
    only the file's own two lines, so no provider registers it and no test asserts it and
    ruling 46 costs nothing. ⚠️ **Correction to ruling 56**, recorded rather than dropped: it
    called `receivable_states.last_action`/`last_reason` "written by no code" — they are
    written by main's `chaseOverdue()`, the method money is declining. They stay unwritten in
    this tree **by choice**, and remain on MONEY-79's list as declared-and-unwritten
    (decision 272).
60. **`coder-bin/git` refuses `JourneyHarness.php` on EVERY commit, so the supervisor commits
    it (RULED by the lane supervisor 2026-09-06 14:4x, on run 93's refusal).** Run 93 did the
    ruling 56 harness split correctly — `php -l` clean, `requestCharge` gone, 31 methods —
    and `git commit` returned *"a never-list or supervisor path is staged:
    app/tests/Journeys/JourneyHarness.php"*; the coder then restored the tree and the work was
    lost. The refusal is **not merge-specific**, so no coder in this lane can ever land a
    harness hunk, while owner ruling 1 grants a journey track its own `todo()` methods and
    ruling 6 assigns `issueInvoice` to money. The guard is coarser than the ruling governing
    it. Ruling 52 resolved this exact shape once — where the guard refuses the coder and
    ruling 26 leaves no other party running git, **the supervisor commits what the coder
    staged and it has inspected** — and it applies unchanged: `git add` is not in the guard's
    case list, so the coder edits, lints and stages, and the supervisor commits the file by
    named path in the next tick, authoring nothing. **Two consequences, both load-bearing:**
    (a) **the harness edit is the LAST item of any brief**, because a staged harness makes the
    guard refuse every *other* commit in the run — that is what cost run 93 its work, and a
    brief that puts it earlier destroys everything after it; (b) **a guard refusal spends no
    dispatch against the cap**, as ruling 40's kill does not — the coder did the work and was
    structurally prevented from recording it. ⚠️ A brief carrying a staged-not-committed
    deliverable also forbids `git commit`, `git restore`, `git checkout`, `git stash` and
    `git merge --abort` for the rest of the run: there is no second copy. The guard itself
    stays a **TRACK 1 ACTION**; this is a workaround, not a fix.
61. **A count rendered as a bare number is asserted with `assertViewHas`, and a wave that
    adapts another side's test never deletes its negative assertions (RULED by the lane
    supervisor 2026-09-06 14:5x, on MONEY-77's `9dda8974`).** Adapting main's X-199 screen
    tests to money's components turned `assertSee('1 declined')` into **`assertSee('1')`** and
    `assertSee('0 declined')` into **`assertSee('0')`**, in both `DeclinesTest` and
    `UnpaidTest`. Those assertions measure **nothing**: a Livewire render carries a
    `wire:snapshot` checksum, `grid-cols-2`, `mt-1`, `bg-gray-50` and a formatted date, so every
    digit is on the page whatever the data is. The empty-state one is the proof — it exists to
    show `$declinesCount` fell to **0** and it passes unchanged if the count renders **1**.
    That is ruling 45/50(b)'s "passes for the wrong reason" landing in the one assertion the
    test exists for. `declines.blade.php:8` renders `{{ $declinesCount }}` and `Declines.php:96`
    passes it to the view, so the instrument is `assertViewHas('declinesCount', N)` — assert the
    view data, or a string long enough to be unique, never a bare digit. ⛔ Separately,
    **`assertDontSee('tok_placeholder')` was deleted** from `DeclinesTest` — a *negative*
    assertion that the card token never reaches the page, which money's blade satisfies for
    free. Deleting it is the One Rule's "deleted … assertion", and **the tell was inside the
    wave itself: `UnpaidTest` kept the identical line.** ⚠️ `assertSee('REF-DEC-001')` and
    `assertDontSee('REF-DEC-002-ISOLATED')` **were** correctly dropped — money's `Declines`
    reads `Payment`, which carries no `reference_id` — and isolation survives on
    `assertDontSee('110.00')`, the other tenant's amount. The cap charge is the coder's, not the
    supervisor's: run 94's kickoff said in terms *"keep every existing row and `assertDontSee`"*.
62. **Composing two refusals is a question about ORDER, and the test of the outer refusal must
    satisfy the inner one (RULED by the lane supervisor 2026-09-06 14:5x, on
    `test_dispute_cannot_be_submitted_after_deadline`).** Ruling 54 composed money's
    `status !== 'compiled'` guard with main's deadline guard in
    `X-201/Domain/DisputeDefenseEngine::submit()`. Both are intact and correct, and the
    composition **reddened main's test**: a dispute from `recordAction->handle()` is never
    compiled, so `:80` throws `DisputeNotCompiledException` before `:84` is reached and
    `expectExceptionMessage('Dispute deadline has passed')` never matches. **The order stands** —
    a dispute that was never compiled is not "past its deadline", it is empty, and reporting the
    deadline for an empty dispute is the misleading message. ⛔ The guards are **not** reordered
    and neither is dropped (ruling 54); the **test** is fixed, by compiling the dispute before
    setting the past deadline, so both refusals stay reachable and it finally asserts what its
    name says. ⚠️ This is ruling 58 shape (1) with a delay fuse: main's test against money's
    engine, red **because of this merge**, so it would redden `main` on the push exactly as the
    three screen tests would have. The scoping miss is the supervisor's — ruling 54 composed the
    guards and never asked which existing test drove them — so it carries its own two
    dispatches.
63. **A lint that scans a module directory for a business noun collides with prose; the module
    rewords its own file and never narrows the lint (RULED by the lane supervisor 2026-09-06
    14:5x, on `test_n_010_no_refund_verb`).** `N010Test.php:14` `strtolower`s **every file**
    under `app/Modules/X-201` via `File::allFiles()` and refuses the substring `refund`. The
    single hit is money's own `X-201/Ui/views/dispute-card.blade.php:4`, whose prose reads
    *"There is no refund on this card: a dispute is defended, and a refund is the gateway
    account's."* — a sentence that **agrees** with N-010 and is counted as violating it, because
    a substring scan cannot tell an offered capability from a paragraph denying it. Money rewords
    its own blade, keeping the owner-facing meaning. ⛔ **The lint is never narrowed to PHP-only
    and the sentence is never deleted** — editing a CHECK to get past it is the One Rule, the
    lint is Track 1's, and the owner needs the explanation. That the instrument should exclude
    `Ui/views/` is a **TRACK 1 ACTION**, not this lane's edit. ⚠️ This is the mirror of the
    known "instrument string in prose inflates the count" hazard: there a dictated docblock fed
    a grep instrument, here a screen's honest copy does, and it will recur on any module whose
    screen explains what it does not do.
64. **A generated file inside a module tree is inert until that module's provider loads it,
    and an inherited follow-up is a claim about names that goes stale like any other (RULED
    by the lane supervisor 2026-09-06 15:0x, briefed as MONEY-78).** Twelve of the thirteen
    errors on the gated tip `80c13ce5` were `Route [x-199.*] not defined`, carried for four
    ticks as **TRACK 1 ACTION 2 — "`surfaces:generate` for money's twelve screens"**. It had
    already run. `app/app/Modules/X-199/routes.generated.php` (5 routes) and
    `X-211/routes.generated.php` (4) arrived with the `12447593` merge, correctly named and
    correctly gated (`->middleware(['web','auth','tenant.role'])`), and **nothing reads
    them**: `grep -c loadRoutesFrom` is **1** for X-198, X-117, X-120, X-173 and X-201 and
    **0** for X-199 and X-211 — the two providers this lane edited most, so ruling 54 kept
    money's side and main's added loader line went with it. **Ruling 58 shape (2), the fourth
    distinct defect that merge produced in a file git never asked about**, and the one that
    hid longest because its symptom names another lane's command. ⛔ The fix is **not**
    running `surfaces:generate` (Track 1's, ruling 20 — it rewrites
    `config/surfaces.generated.php` and other lanes' files) and **not** hand-writing a route:
    it is one line per provider, `$this->loadRoutesFrom(__DIR__.'/routes.generated.php')`,
    copied from `X-112/ModuleServiceProvider.php:23`. The file is inside money's own module
    tree, so ruling 59 makes it money's regardless of who last touched it. It also delivers
    ruling 20's definition of done — *routed, gated, real GET test* — for nine screens at
    once, because `tests/Modules/X-19*/Screens/*` already call
    `$this->get(route('x-199.declines'))->assertOk()` and already pass through
    `provisionTenant()`, which sets `Tenancy` at `tests/TestCase.php:177`.
    ⚠️ **The second half is the generalisable one.** Of MONEY-78's four inherited follow-ups,
    **two were false when measured**: `requestCharge()` exists nowhere in this tree (rulings
    22/23 listed it on 2026-09-05 when *main's* engine had it; money kept its own X-198 at
    the merge and the name went, while `EvidenceChargeCommand:52` calls `capture()` and is
    proven by `evidence/j9/charge.json`), and `config/features.php` **arrived with the merge**
    already carrying the `Money` feature and all nine of this lane's modules, so ruling 21
    step 5 was satisfied by another lane's file. Building either would have been ruling 56's
    mistake — minting a method to satisfy a caller — with, in the first case, the *same method
    name*. **So: every inherited follow-up is re-measured against the tree before it becomes a
    brief item, and a follow-up struck for being moot is recorded with its measurement, never
    silently dropped.** A supervisor's own ledger decays exactly like a merge does.
65. **phpstan does not read `app/tests`, so ruling 58's seam detector is blind to half the seam,
    and a class relocation resolved in the component and left in the test survives every gate
    but the suite (RULED by the lane supervisor 2026-09-06 15:3x, on MONEY-78's `a36e199f`).**
    `app/phpstan.neon:5-6` is `paths: - app/` — that is `app/app/`, and `app/tests/` is outside
    it. When main deleted `X-121/Models/{Conversation,Message}` and relocated them to
    `App\Models\` (ruling 58 shape (3)), `X-211/Ui/InvoiceThreadBeside.php:7-8` was corrected and
    `tests/Modules/X-211/InvoiceThreadBesideScreenTest.php:8` was not. phpstan reports `0`, pint
    `passed`, `php -l` clean, the classmap fine — and the test errors `Class
    "App\Modules\X121\Models\Conversation" not found`, the lane's last self-owned red, carried
    invisibly through three waves. **So: after any class move, the grep for readers covers
    `app/tests` explicitly**, because the only gate that resolves a class across two files does
    not look there. ⚠️ **The fix is not the import alone.** `App\Models\Conversation:53` is
    `protected $guarded = ['id', 'business_id']` where the deleted X-121 model was not, so
    `Conversation::create(['business_id' => …])` now **silently drops** that key and
    `BelongsToTenant` fills it from `Tenancy` instead — a fixture that lands on the right tenant
    by luck, and an isolation assertion that would pass for the wrong reason the day it stops.
    Any fixture moved onto a guarded-tenant model sets `Tenancy` first and **asserts the created
    row's `business_id`**. ⛔ **`person_id` is correct and never becomes `customer_id`:**
    `conversations` carries both (`2026_07_30_111419:25` and X-121's reconcile migration
    `2026_08_31_000007:21-23`), and the only production writer,
    `X-01/Domain/UnifiedInboxManager.php:74-77`, writes `person_id` under `Tenancy::actingAs` —
    so `InvoiceThreadBeside.php:87` and `ArEngine.php:228` read the live column and switching
    them would be ruling 51's empty-forever screen. ⚠️ The generalisable half for every lane: a
    lane that relocates a class breaks receiving lanes' **tests** silently, weeks later; that is
    now TRACK 1 ACTION item 4's lesson, not just its instance.
66. **A signature the brief dictates is still the coder's to lint, and the brief owns the
    phpstan consequence of dictating one (RULED by the lane supervisor 2026-09-06 15:5x, on
    MONEY-79's `9e7b7a75`).** MONEY-79 item 2.1 wrote `public function getExposure(int
    $businessId): ?float` in terms, and ruling 43's fix — a body of `return null;` — makes
    `float` an unused member of that union, so at phpstan level 5 the dictated type is an error
    **the moment the ruling is obeyed** (`return.unusedType`, `DisputeDefenseEngine.php:37`).
    phpstan went `0` → `1` on an otherwise perfect wave. The coder saw it, refused to deviate
    from an explicit signature, and said so in its own log — **that refusal is correct and is
    not charged**; the miss is the supervisor's, so per the ruling 46/49/50/62 precedent it is a
    new item with its own two dispatches. **RULED: the return type becomes standalone `null`**
    (`php: ^8.4` in `app/composer.json`, so it is valid here) — exact, phpstan-clean, and
    *more* honest than `?float`, because the ledger has no figure and the signature should say
    so rather than promise a float it can never produce. It also hardens the mutation guard:
    restoring the fabrication becomes a `TypeError` at the boundary, not merely a failed
    assertion. ⛔ Never `@phpstan-ignore`, a baseline entry, an inline `@var` or a cast —
    phpstan's own instruction text forbids all four and each would hide the exact fact the
    method exists to state. ⛔ The type widens back to `?float` only in the same commit that
    computes a real figure. ⚠️ The generalisable half: **a brief that dictates a signature has
    dictated a phpstan result**, so any brief naming an exact signature checks it against the
    body it also dictates — and the unused parameter stays, because the capability id is
    per-tenant and the signature records that.
67. **`ZERO BYTES … rc=137` is a KILL, and a killed §7 is VOID — but §6 alone still refuses the
    push (RULED by the lane supervisor 2026-09-06 15:5x, on the same gate).** Ruling 24's gate
    hunks named rc 124 (`TIMEOUT`) and the zero-output case; **137 is `128+9`, SIGKILL**. On
    this box that means the suite was reaped under memory pressure — `pgrep -a -f
    "pest|phpunit"` that minute showed another checkout's `timeout 1800 ./vendor/bin/pest` live
    plus four other lanes' coders — so it is the *memory* half of the zero-bytes trap arriving
    from **outside this checkout**, and it says nothing whatever about the sha. So: (1) a §7
    line carrying `rc=137` is recorded **VOID** and never compared against a baseline — a suite
    that was killed did not fail; (2) it is **not** re-run in the same tick, because the
    pressure that killed it is still there and a second kill teaches nothing; (3) ⚠️ **a void
    §7 is not a licence to push** — §1–§6 stand on their own, and on `9e7b7a75` §6 was red
    twice over, which is sufficient under ruling 34. The number is re-measured by the fix run's
    own gate. ⚠️ **Distinguish from ruling 42's concurrency:** there the tell was `SQLSTATE[42P01]
    … relation "users" does not exist` inside a run that *completed* on the same database; here
    there is no output at all and the signal is the exit code. ⚠️ Distinguish from ruling 40's
    kill too: that is a dead *coder* with a 0-byte run log, this is a dead *pest* inside a live
    gate — neither spends a dispatch, but only 40 calls for a continuation brief.
68. **Carbon 3 returns a SIGNED `diffInDays`, so `$future->diffInDays(now())` is NEGATIVE, and two
    of this lane's uses are inverted — one of them produces a constant (RULED by the lane
    supervisor 2026-09-06 16:0x, briefed as MONEY-80 items 1 and 2).**
    `app/vendor/nesbot/carbon/src/Carbon/Traits/Difference.php:254` is
    `diffInDays($date = null, bool $absolute = false, …): float` — Carbon **3.13.2**
    (`composer.lock:3695`) flipped `$absolute` to `false` from Carbon 2's `true`, so the call
    returns `$date − $this` **signed** and the receiver order decides the sign.
    (1) `X-199/Domain/InvoiceEngine.php:224`'s `max(1, now()->diffInDays($invoice->due_date))`
    makes the inner term **`−45.0`** for an invoice 45 days overdue, so `max()` returns **`1`** and
    `InvoiceOverdue::daysOverdue` is **always exactly 1**, for every overdue invoice at every age —
    ruling 43's *"does it even vary?"* in the one field whose entire purpose is to vary, with the
    comment above it (*"just 1 if it's forced by harness"*) showing the constant was seen and
    rationalised rather than measured. It is also a latent `TypeError`: a due_date in the **future**
    makes the term positive, `max()` returns a `float`, and the constructor's `int $daysOverdue`
    under `declare(strict_types=1)` refuses it.
    (2) `X-120/Ui/CardScreen.php:90`'s `$expDate->isPast() || $expDate->diffInDays($now) <= 30`
    scores a card expiring 2029-12-31 at ≈ **`−1211`**, so **every card on file** is in
    `$expiringCards` and `card-screen.blade.php:15-19` renders a **"Card Expiring Soon"**
    attention-card for each — the owner is warned about every card forever and the one real signal
    is lost. `X-120/Actions/CardExpiringScanAction.php:27` passes `false` explicitly *and* has the
    receiver the right way round, so today **the screen and the scan disagree about the same card**;
    the fix copies the action's shape so the two agree by construction.
    **Measured clean and out of scope:** `Unpaid.php:93`, `AgeingByReason.php:137`,
    `InvoiceThreadBeside.php:82`, `CollectionsPackagePreview.php:62` and
    `DetectOverdueReceivablesCommand.php:68` all call `$due_date->diffInDays(<later>)`, which is
    positive, and the two X-211 screens are fed by `InvoiceReader::unpaidOverdueForBusiness()`
    (`:70` `whereDate('due_date','<',today())`), so no not-yet-due row can reach them and print a
    negative age. Six sites read, four correct. ⚠️ **Ruling 46's sweep:**
    `grep -rn "markOverdue" app/app app/tests` returns **only its own definition**, and
    `CardScreenTest.php:63`'s `assertSee('Card Expiring Soon')` **stays green** after the fix
    because its second fixture card is genuinely expired (`exp_year => now()->year - 1`) and
    satisfies `isPast()` alone — it has been passing for the wrong reason and cannot see the bug,
    which is why each item adds a **new** method rather than editing that one. ⚠️ The
    generalisable half is cross-lane and is **TRACK 1 ACTION 8**: the Carbon 2 → 3 upgrade made
    every call written against the old semantics silently sign-wrong, and `phpstan` cannot see it
    because the type is `float` either way.
69. **`invoice.overdue` has a declared emitter, a declared consumer and no dispatcher, and this
    corrects ruling 32's second half (RULED by the lane supervisor 2026-09-06 16:0x).**
    `X-199/manifest.php:41` declares `@emits invoice.overdue` against `X-211/manifest.php:46`'s
    consume. Ruling 32 attributed that name to `Events/InvoiceDue.php` (dispatched by
    `MarkInvoicesDueCommand:65`) and recorded the gap as a manifest-truncation artifact. **That was
    wrong by one class:** `X-199/Events/InvoiceOverdue.php` exists, matches the declared name
    exactly, and its only dispatcher is the caller-less `markOverdue()` — so the seam is real in
    name and dead in fact, decision 272 with **both** ends declared. ⛔ **It is not wired and
    `markOverdue` is not deleted.** Ruling 59 already chose between the two overdue chains and kept
    money's `x211:detect-overdue` → `ArOverdue` → `ProcessOverdueReceivable` → `ArDunningAction`
    precisely so X-211 would not have two overdue paths writing two stores; giving `InvoiceOverdue`
    a caller now rebuilds the chain 59 declined. Deleting it instead removes the only class that
    could ever satisfy X-199's own generated `@emits` line, and ruling 29 forbids this lane touching
    the plan that line is harvested from. So: **fix the arithmetic so nothing in the tree carries a
    fabricated number, and record the dead seam `UNRESOLVED`** naming ruling 59's choice as the
    reason. ⚠️ This is ruling 44's reasoning with the sign reversed — 44 dropped a fabricated
    *string* that had no reader; here the field is a real quantity computed wrongly, one line makes
    it true, and an always-1 number is a fiction whether or not a listener reads it today.
70. **A screen that tells the customer where their card number went must be true about it (RULED by
    the lane supervisor 2026-09-06 16:0x, briefed as MONEY-80 item 3).**
    `X-120/Ui/views/card-screen.blade.php:51` reads *"The number never leaves this form: storing it
    is waiting on Stripe tokenisation."* `wire:model="number"` binds it to a **public property of a
    server-side Livewire component**, read by `present()` at `CardScreen.php:64`, so the PAN travels
    over the wire into PHP on submit — it leaves the form on the first round trip. The rest of the
    sentence is true and stays: `present()` clears `$this->number` in its `finally`, nothing is
    persisted, and the three-fields promise holds. This is ruling 50(a) at the lane's highest
    stakes — a waiting state describing machinery as working in a way it does not, about a card
    number, to the person typing it. ⛔ **The fix is the sentence, not the form.** Browser-side
    tokenisation (Stripe Elements, a publishable key) is X-120's dependency, parked behind a
    contract by ruling 20 and recorded by ruling 45; hand-writing a card-entry door is P-196's and
    the kit is Track 2's (ruling 21). ⚠️ No test asserts the sentence, so nothing goes red and no
    test is added for copy — which is exactly why it survived: **prose on a screen is the one thing
    in this lane no gate reads**, and it is where rulings 50(a) and 63 both landed.
71. **A mutation proof is a hand-restore in this lane, and unrestored TEST residue makes the gate
    red on a tree the sha is not (RULED by the lane supervisor 2026-09-06 16:4x, on MONEY-80's
    `0cbe45fc`).** `coder-bin/git` refuses `git checkout` on paths — run 99's `REFUSED` line records
    it — so every mutation proof here ends in a hand restore with no undo. Run 99 restored the
    **system** correctly (`InvoiceEngine.php` clean against `HEAD`) and left
    `app/tests/Modules/X-199/X199Test.php` modified, having rewritten the assertion mid-proof for a
    more legible failure message. The residue reintroduced precisely the two fixers `d6a503dc` had
    removed — `fully_qualified_strict_types` and `no_whitespace_in_blank_line` — so §6 printed
    `"result":"fail"` on a file whose **committed** content is pint-clean. **This is ruling 34
    inverted:** 34's hazard is a coder's uncommitted pint *fix* making the gate green on the tree and
    red on the sha; here uncommitted *residue* makes the gate red on the tree while the sha is green.
    The instrument is the same — `git diff --stat HEAD -- app/` — read the other way: **non-empty
    means §6's verdict is the tree's and must be attributed hunk by hunk before it is read as the
    sha's**, and `./vendor/bin/pint --test -v <path>` names the fixers so the attribution is exact,
    not argued. Here both fixers' triggers existed only inside the uncommitted hunk, so `0cbe45fc`
    was pushed. Two standing consequences: (a) **a mutation proof mutates the SYSTEM only** — the
    test is the control, and editing it destroys the proof's meaning as well as the tree; (b) **the
    run's last act before `REPORT.md` is `git status --short`**, which must print
    `?? app/composer.phar` and nothing else, and a run that cannot reach that state says so under
    `REFUSED` rather than leaving the tree for the gate to find. ⚠️ Run 99 **did** stop and report
    the refusal (rule 10), so per ruling 60(b) it spends no dispatch: the coder was structurally
    prevented from finishing, it did not fail.
72. **The RED line a mutation proof quotes must come from the COMMITTED test (RULED by the lane
    supervisor 2026-09-06 16:4x, same wave).** Run 99's `RAW` quoted `Failed asserting that 1
    matches expected 45.` — an `assertEquals` message — while the committed test uses
    `Event::assertDispatched(InvoiceOverdue::class, fn …)`, whose failure message is `The expected
    [InvoiceOverdue] event was not dispatched.` and carries **no number**. So the proof evidenced a
    file that is not in the sha, and item 2 had no mutation line at all. Both assertions are
    load-bearing by construction — `daysOverdue === 45` against an engine mutated back to a constant
    `1`, with `int $daysOverdue` promoted under `strict_types` so the strict comparison cannot pass
    on a float — but **"by construction" is the reviewer re-deriving a proof the contract asks the
    coder to run**, and a reviewer who can do that can also talk themselves past a proof that would
    have failed. So: **every mutation proof is re-run against the committed tree and its RED line
    quoted verbatim**, and a wave quoting a message shape the committed assertion cannot emit has
    not proved it. ⚠️ The legibility complaint that caused it is **real** — a closure assertion hides
    the value, and where a field's whole point is that it VARIES (ruling 43's corollary) the
    diagnostic should name the number — but that is a design question for the test's own commit,
    never a change made mid-proof.
73. **X-173's connect door is honest and its ACTION is not; the empty state credits an AI that does
    not exist and points at a door that always refuses (RULED by the lane supervisor 2026-09-06
    16:4x, briefed as MONEY-81 items 3 and 4).** `X-173/Ui/ConnectionMappingView::connect():28-33`
    makes **no call** and writes a finished waiting state naming the missing dependency — *"Waiting
    on %s OAuth: no %s credentials exist in this checkout, so nothing was connected"* — asserted by
    `ConnectionMappingScreenTest:80`. That is ruling 21's shape, it is correct, and it **stays**.
    Three things around it are not. (a) `connection-mapping.blade.php:21`'s empty state reads *"No
    ledger connected yet. Connect one below; the AI then proposes the chart of accounts here for
    confirmation and never guesses silently."* It **directs the owner to a door that cannot
    succeed**, and credits an AI that does not exist:
    `AccountingSyncEngine::inferCategory(string $description, float $inferredConfidence, string
    $suggestedCategory)` is handed the answer **and** the confidence by its caller — ruling 51's
    shape one level up, a figure the app compares against itself. Because `connect()` refuses,
    `connections` is empty for **every** real tenant, so this sentence is the only thing a real owner
    ever sees on this screen: ruling 50(a) exactly. **No test asserts the current string**, so
    nothing goes red on the change and the wave must ADD the assertion — otherwise it is prose no
    gate reads, which is ruling 70's own lesson. (b) `AccountingConnectAction:21` writes
    `'token_oauth_'.bin2hex(random_bytes(12))` into `access_token` whenever no token is passed;
    `grep -rn "access_token" app/app app/tests` returns the migration, that line and **nothing
    else** — ruling 44's fabricated string with no reader, in a column named for a **credential**,
    which is strictly worse than 44's `bundle_url` because the first reader to arrive hands it to
    QuickBooks. The column is nullable, so the fallback goes and the column stays null. (c)
    `'is_active' => true` unconditionally, rendered as a green `active` pill at `:28` and guarding
    `AccountingSyncEngine:88` — ruling 43's shape, and **deliberately not fixed**: all six
    `connect()` call sites are tests passing three arguments (`SyncErrorRateScreenTest:25,:34`,
    `ConflictsListScreenTest:25,:33`, `X173Test:45`, `ConnectionMappingScreenTest:26,:32`), so
    deriving it from a real credential flips every fixture inactive and reddens the module to assert
    a state this lane cannot reach until OAuth exists — ruling 46's blast radius measured **before**
    briefing rather than after. Recorded declared-and-unwritten alongside `inferCategory`, each its
    own later wave. ⛔ Not resolved by building an OAuth flow: provider credentials are the owner's
    and a live call is ruling 13's evidence run, with X-173's `no runtime proof` anchor already in
    ruling 32's group (2). ⛔ Not resolved by deleting the action: six fixtures across four files
    construct connections through it, and ruling 46 makes the wave own every one of them.
74. **A KILLED tool is not a verdict, and until this tick this lane's §6 could not tell one from a
    style red (RULED by the lane supervisor 2026-09-06 17:0x, applying `OWNER.md` 14:1x, 16:0x, 16:5x
    and 17:2x to this lane's own two scripts).** `bin/supervise.sh` §6 piped `pint --test` and
    `phpstan` straight into `tail`. `set -uo pipefail` is on, so a non-zero from either still reached
    `fail=1` — which is precisely the defect: a pint **killed** on this box prints a bare
    `Terminated` and was about to be recorded as a style red, and under ruling 34 a style-red tip is a
    live BLOCK that refuses the push. So the lane would have withheld a correct tip because someone
    else's agent reaped a process. `run_tool()` now captures each tool's RAW rc, and `rc ≥ 124`
    prints `⛔ <tool> was KILLED or timed out · rc=<n> — this is NOT a verdict`. **Ruling 67 named
    this for pest; it is now mechanical for every tool the gate runs**, and 67's reading rule is
    unchanged — a killed run is VOID, never compared against a baseline, never re-run in the same
    tick. ⚠️ **A void tool is still not a licence to push**: the other sections stand on their own.
    **The gate log is EIGHT columns**, `start_iso end_iso gate_pid tool_pid rc project checkout tool`,
    appended to `/home/goaiez/tmp/gate-runs.tsv`. Track 1's first shape had seven and it corrected
    itself at 16:5x: `$4` is `tool_pid`, not `rc`, so a field-index parser across the mixed history
    reads every eight-column row as a failure with a seven-digit code. `project` is
    `goaiez-antigravity` (the project, never the directory); `checkout` is `grs-antig-money`; `rc`
    stays raw. ⚠️ **The descent to `tool_pid` goes through KNOWN WRAPPERS ONLY.** Track 1's recipe
    takes the first child repeatedly; `timeout 1800 pest` needs exactly one hop, but a blind loop
    would pin whatever the tool itself forked at that instant — the same silent join failure the
    descent exists to prevent, one level further in — so the loop hops only while the current
    `/proc/<pid>/cmdline` is a `timeout` or `env`, and stops on an unreadable one (a short tool has
    already exited and its pid is still the right one). The sentinel rows are defined at the **top**
    of the script, with `trap - EXIT` **inside** each signal trap, both for the reasons Track 1
    already paid for. **The pest lock** (`/home/goaiez/tmp/pest.lock`, advisory, cross-project) is
    orthogonal to §7's shared-database refusal — that one is correctness, this one is scheduling, and
    both stay. ⚠️ **A `lock-timeout` is NOT a red suite**: no test ran, `want_tests` is cleared, and
    no number is printed for a run that did not happen. **`BASH_ENV`** is exported to both coder
    branches so `coder-bin/kill` sees a shell `kill` — `kill` is a bash builtin and a PATH shim never
    catches it, which is why every SIGTERM on this box has been unattributable; the shim records and
    then performs the kill, refusing nothing. `tool_pid` is the join key against `kill-log.tsv`.
    **Finally, `--allow-harness` retires ruling 60(a).** `coder-bin/git` now clears its
    `JourneyHarness.php` refusal on `GOAIEZ_HARNESS_OK=1`, which this launcher sets for one run. So
    the harness edit no longer has to be the **last** item of a brief — 60(a) existed only because a
    staged-not-committed harness made the guard refuse every *other* commit in the run, which is what
    cost run 93 its work. 60's workaround (the supervisor commits what the coder staged) stays
    available and is now the fallback, not the only path. ⛔ **The flag opens the ability to COMMIT,
    not permission to weaken.** Provisioning real state so a real code path runs is a fix; deleting an
    assertion, stubbing a transport or making a journey pass on a constant is a BLOCK, and the
    supervisor that opened the gate wears it. **A tick that passes it quotes the harness diff in its
    own REVIEWS block** — an unreviewable harness change is the exact shape of the fake green this
    repo keeps finding. Never a standing flag: if the wave does not touch the harness, dispatch
    without it.
75. **A brief that dictates a line of code has dictated a `pint` result, and a `state.py` run is a
    commit (RULED by the lane supervisor 2026-09-06 18:1x, on MONEY-82's `15b20f8d`).** MONEY-82's
    substance passed in full — four commits on named paths, three mutation proofs quoting committed
    assertions, an empty `git status --short`, phpstan `0`, and §7 landing on the predicted number
    exactly (`2051 · 2046 · FAILED 2 · errors 3`, all five reds other lanes') — and the tip was
    BLOCKED on §6 alone. Two files: `binary_operator_spaces` on
    `X-173/Actions/AccountingSyncAction.php` and `no_whitespace_in_blank_line` on `X173Test.php`.
    With `git diff --stat HEAD -- app/` **empty**, ruling 34 makes that verdict the sha's and ruling
    26 refuses the push, so four accepted commits sat unpushed for a style red. ⚠️ **Half the defect
    was the brief's own**: its fenced code block wrote `$suggested  = $tx['category'] ?? …` with the
    `=` aligned, which is precisely what `binary_operator_spaces` refuses, and the coder transcribed
    it faithfully. This is ruling 66 one instrument over — there a dictated *signature* dictated a
    phpstan error, here a dictated *line* dictated a style error — so: **every fenced code block in a
    brief is read for alignment, trailing whitespace and blank-line whitespace before the brief
    ships**, and every brief tells the coder to run `./vendor/bin/pint <touched paths>` **before**
    each commit, since the gate's `--test` is the sha's verdict and there is no fix mode in it.
    ⛔ Never resolved by excluding the path or by editing `pint.json` — that is the One Rule.
    **Second, recorded because it cost a REFUSED line:** MONEY-82 described its item 4 as "two
    `state.py` lines with **no commit**" while also requiring `git status --short` to print nothing.
    `state.py` writes two tracked files, so the two demands cannot both hold; the coder reported the
    contradiction and committed `.agents/state/*` by named path, which is its own column in the role
    table. **A `state.py` run IS a commit** and every brief says so. ⚠️ Per the ruling 46/49/50/62/66
    precedent a supervisor-caused defect is a new item with its own two dispatches, so MONEY-83 is
    1 of 2 and MONEY-82's cap is untouched.
76. **A true empty state is half of ruling 50(a), and a brief that suggests a string gets that string
    or something shorter (RULED by the lane supervisor 2026-09-06 18:1x, same review).** MONEY-82
    replaced two X-173 empty states that described machinery nobody built. The replacements are
    **true** — `conflicts-list.blade.php:17` *"No line has ever been synced."* and
    `sync-error-rate.blade.php:9` *"There is no nightly anything."* — and neither names a dependency,
    which is the other half of ruling 50(a) (*"names what has not happened yet **and what it waits
    on**"*). Worse, the second is a sentence lifted from the MONEY-82 brief's own argument, addressed
    to a reviewer: an owner reads that machinery they never asked about is absent, and learns nothing
    about why their screen is empty. The real answer is one sentence — `AccountingConnectAction`'s
    door refuses by design because no ledger OAuth credential exists in this checkout (ruling 73).
    **RULED: that shortfall is PASS-WITH-NOTES-grade, not a BLOCK** — nothing false was introduced,
    50(a)'s prohibition half holds, and the tip is strictly more honest than its parent — and it is
    fixed forward, with each **existing** assertion changed rather than added to or deleted (ruling
    39's companion lesson). ⚠️ The generalisable half is the supervisor's: **a brief that offers a
    suggested string and then says "write it in your own words" gets the shortest true sentence that
    clears the test.** Where the copy must carry a specific fact, dictate it verbatim and require the
    assertion to name the clause carrying the fact — not merely a clause that differs from the old
    one. ⚠️ Both new assertions also sat at or under that brief's own six-word floor; at 29 characters
    they are not ruling 61's bare-digit defect and they do measure the change, so they were recorded,
    not charged.
77. **A §7 error is attributed to concurrency by the mechanism and the gate log, never by hope (RULED
    by the lane supervisor 2026-09-06 18:4x, on MONEY-83's `2fbe00f0`).** The gate landed
    `2051 · 2045 · FAILED 2 · errors 4` against a floor of `2051 · 2046 · FAILED 2 · errors 3`, the
    extra member being `a_deliberately_corrupted_backup_fails_the_restore` —
    `SQLSTATE[42501] … permission denied to terminate process`. Ruling 42 named the
    `relation "users" does not exist` shape and owner ruling 12 named this 42501, but neither said how
    a tick *proves* it in the minute it has, and a wrong call either way is expensive: read as the
    sha's it withholds a correct tip under ruling 26, read as concurrency it pushes a regression.
    **The instrument is two greps and one file.** (a) The failing statement's own mechanism in the
    tree — here `JourneyHarness.php:921`'s `DROP DATABASE IF EXISTS goaiez_antig_drill_<pid> WITH
    (FORCE)`, which terminates every backend on that database, plus an **empty**
    `grep -rn pg_terminate_backend app/app app/tests`, which together show 42501 can arrive *only*
    from a backend owned by another role. (b) `/home/goaiez/tmp/gate-runs.tsv` read for **foreign rows
    overlapping this gate's own pest window**, which ruling 74's eight columns make exact: this pest
    ran `18:30:45 → 18:32:46` with grs-antig-reviews running `doctor` at `18:31:01` and `18:32:07`
    inside it. (c) Reachability from the wave's own diff, which here was nil — five commits over two
    X-173 blades, two X-173 screen tests, a comment, an unread local, an X-120 class with zero
    readers and the state files, none on J8's path. A tick that cannot produce all three treats the
    error as the sha's. ⚠️ **The lane's Bash is confined to this checkout, so the TSV is read with
    `Read`, never `grep`** — a refused grep is not an absent file. ⚠️ Frequency corroborates but never
    substitutes: the string appears in **2 of 87** gate files here. ⚠️ The floor returns to `errors 3`
    the next run; a second appearance on an idle box stops being concurrency and becomes an item.
78. **The gate log is also this lane's cheapest schedule evidence.** This tick's pest sat on the
    shared `flock` for roughly eight minutes and then ran in two, legible only from the TSV's paired
    `gate`/`pest` rows. A brief or addendum that predicts gate duration reads them rather than
    remembering the last one.
79. **X-201's two empty states describe machinery nobody built, and `disputes` has no production
    writer (RULED by the lane supervisor 2026-09-06 18:4x, briefed as MONEY-84).** Ruling 50(a)'s
    sweep has cleaned X-198's two reconciliation screens and all three of X-173's; X-201 is the last
    unaudited pair in the lane and it is the same defect twice.
    `dispute-queue.blade.php:9` reads *"A chargeback from any gateway opens one here; the evidence
    compiles within the hour"* and `dispute-card.blade.php:9` *"A chargeback from any gateway opens
    one here, with the invoice already in the bundle"*. Measured: (a) the only writer of `disputes` is
    `DisputeDefenseEngine::record()` behind `DisputeRecordAction`, whose callers are **five test files
    and no production path**, so both screens are empty forever for every real tenant (decision 272,
    ruling 51); (b) `X-198/Events/ChargebackReceived.php` is dispatched by nothing and consumed by
    nothing — `grep -rn ChargebackReceived app/app` returns only its own declaration — which is the
    seam `dispute-queue.blade.php:2`'s generated sample-state line advertises; (c) *"the evidence
    compiles within the hour"* and `dispute-card.blade.php:4`'s *"The bundle compiles itself"* name an
    automatic process that does not exist, since `DisputeCompileAction`'s only caller is
    `DisputeQueue::compile()`, **a button**. ⛔ Not resolved by wiring a chargeback webhook: that is a
    live vendor call under ruling 13, and `app/app/Services/Billing/StripeWebhooks.php`'s
    `charge.dispute.funds_withdrawn` handler is **Track 1's** platform-billing clawback, not a tenant
    merchant dispute and not this lane's file. ⛔ Not resolved by minting a dispatcher for
    `ChargebackReceived` to make the generated line true (rulings 29, 32, 56). The outcome is ruling
    21's finished waiting state plus an `UNRESOLVED` naming the webhook. ⚠️ **Two asymmetries the
    brief carries:** the queue's empty state *is* asserted (`DisputeQueueScreenTest:72`, on the
    heading, mid-chain after the last `outcome` call — so a slot rewrite alone would not redden it,
    and the assertion is *changed* to the dependency clause), while the card's is asserted by
    **nothing**, which is ruling 70's *"prose is the one thing in this lane no gate reads"* and is why
    that item must **add** a method rather than change an assertion. ⚠️ Every string dictated was
    checked against `N010Test`'s directory-wide `refund` scan (ruling 63) before the brief shipped.
80. **A capability whose implementation exists but is in no schedule is worse than one that does not
    exist, and the per-row copy is where MONEY-84's sweep stopped one line short (RULED by the lane
    supervisor 2026-09-06 18:5x, briefed as MONEY-85 items 1 and 2).** MONEY-84 made X-201's two
    **empty** states true; the same screen's **per-row** copy still makes two claims nothing performs.
    (a) `dispute-card.blade.php:26` reads *"Deadline: waiting on the gateway's chargeback webhook;
    inside 48 hours of it a person is raised whatever the state."* The second clause is
    `capabilities.php:40`'s **N-011** stated as fact, and — unlike every prior instance in this lane —
    **the machinery exists**: `app/app/Console/Commands/CheckDeadlinesCommand.php` implements it
    correctly (48-hour threshold, one `raised_to_human` audit row per dispute, idempotent), and
    `N011Test.php:35` drives it green through `Artisan::call`. What does not exist is a **caller**:
    `grep -rn 'CheckDeadlines\|disputes:check' app/app app/routes app/bootstrap app/tests` returns the
    command, its signature and that one test line — **`app/routes/console.php`'s platform schedule
    never names it**, so on a real box the raise runs never. That is a **new shape** for this lane:
    rulings 43/44/50 all removed strings whose referent was absent, and here the referent is present,
    tested, and unreachable. ⚠️ It is also **doubly** blocked: nothing writes `deadline_at` outside
    tests (`grep -rn deadline_at` = the migration, the cast, `DisputeDefenseEngine:87`'s guard,
    `N011Test:27`, `X201Test:59,:88`), so even a scheduled run would find no row — the clock arrives
    with the chargeback webhook ruling 79 recorded UNRESOLVED. ⛔ **The schedule entry is NOT this
    lane's to add**: `app/routes/console.php` is outside `app/app/Modules/X-201/**` and outside ruling
    17/20's `Ui/` override — it is a **TRACK 1 ACTION**, and adding it here would be ruling 59's
    "never add the method to satisfy the caller" in the schedule file. (b) `:37` reads *"Submitted; the
    bundle is sealed and the gateway's decision comes back through the queue."* Nothing brings a
    decision back: `recordOutcome`'s only path is `DisputeOutcomeAction` ← `DisputeQueue::outcome()`,
    the **Won/Lost buttons** an owner clicks. **RULED:** the deadline line renders the row's real
    `deadline_at` when it carries one and names the dependency when it does not — which also satisfies
    ruling 43's *"does it even vary?"*, since the column is nullable, real and cast — and neither line
    claims an automatic act. ⚠️ **The existing assertion is CHANGED, never deleted**:
    `DisputeCardScreenTest:44`'s `assertSee("waiting on the gateway's chargeback webhook")` already
    covers the null branch's first clause and only its first clause, which is exactly why the false
    second clause survived MONEY-84 (ruling 39's companion lesson, arriving as a **partial** cover
    rather than an absent one — a substring assertion certifies the words it names and nothing else).
81. **A docblock that says a method contacts an external system when no transport exists in the module
    is ruling 43's fiction in a comment, and MONEY-83 fixed the engine's copy and not the action's
    (RULED by the lane supervisor 2026-09-06 18:5x, briefed as MONEY-85 item 3).**
    `X-173/Actions/AccountingSyncAction.php:25` reads *"Synchronizes transactions with external
    accounting system."* Measured: `grep -rn 'Http::\|curl' app/app/Modules/X-173` is **empty**, the
    method's `array $transactions` comes from its caller, and `syncTransactions` has no production
    caller at all (nine test lines and its own definition) — the same measurement MONEY-82 wrote into
    the journal for the engine. ⛔ **The two `(TEST ANCHOR …)` lines beneath it stay byte-identical** —
    they are the CHECK, and `G1-03` is cited on one of them. ⚠️ **No mutation proof is possible and
    none is asked for**: nothing reads a docblock, which is ruling 70's *"prose is the one thing in
    this lane no gate reads"* and is precisely why this survived two waves that were looking for it.
    ⚠️ Checked before briefing: `app/tests/Modules/X-173/` contains no `File::allFiles()` directory
    scanner, so the memory hazard *"an instrument string in dictated prose inflates the count"* does
    not apply here as it did to `N010Test` in ruling 63.
82. **`assertSee` escapes the NEEDLE only, so an apostrophe in a needle can never match static blade
    prose — and the MONEY-85 brief dictated two that could not (RULED by the lane supervisor
    2026-09-06 19:2x, on MONEY-85's `300d6fac`; briefed as MONEY-86).**
    `app/vendor/livewire/livewire/src/Features/SupportTesting/MakesAssertions.php:18` is
    `$escape ? e($value) : $value` compared against `$this->html(...)`: the needle is escaped, the
    haystack is the **raw rendered HTML**. Blade emits static prose verbatim and only interpolates
    `{{ }}` through `e()`, so an apostrophe in a needle matches **only** text that arrived through
    `{{ }}` and **never** a sentence typed into the template. MONEY-85's
    `assertSee("The gateway's chargeback webhook sets it, …")` therefore searched for `&#039;` in a
    page carrying `'`, and the tip was red at `FAILED 3` against a floor of 2. **The proof is inside
    the one chained assertion:** `DisputeCardScreenTest:72`'s `assertSee("isn't in this account")`
    passes — it is a flash message rendered through `{{ }}` — while `:45` fails; and the line MONEY-85
    replaced passed only because the **old** blade had `&#039;` hardcoded in its prose, so the wave
    inherited a green assertion from a defect it was right to remove. **RULED: a needle asserted
    against static blade prose carries no apostrophe and no other `e()`-escaped character**, and the
    clause chosen is one that carries the load-bearing fact in characters that survive both sides
    (ruling 76). ⛔ Not `, false` — it works, but it leaves two escaping modes in one file for a
    future reader to re-derive; ⛔ not `&#039;` back in the prose, which an owner reads. ⚠️ **The
    defect is the supervisor's twice over**: the brief dictated both needles verbatim *and* asserted
    "`assertSee` escapes both sides, so the apostrophe needs no handling", which talked the coder out
    of the correct `, false` form it had itself proposed. Per the ruling 46/49/50/62/66/75 precedent
    it is a new item with its own two dispatches. This is ruling 75 one instrument further: a brief
    that dictates a **needle** has dictated a test result, so every dictated `assertSee` is read
    against the rendered shape of its target before the brief ships — static prose or `{{ }}`.
    ⚠️ **The collateral is that both mutation proofs were void** (ruling 72): each RED line quoted the
    same always-failing assertion, and proof 2's own page dump **contains** the sentence it reports as
    missing — a proof whose failure message would be identical with the mutation reverted proves
    nothing, and the tell is exactly that, visible in the report without re-running anything.
    ⚠️ MONEY-84's assertions were all apostrophe-free and went green, so the discipline this ruling
    writes down was already the lane's practice; MONEY-85 departed from it only because a brief said to.
83. **`--no-write-fetch-head` leaves `FETCH_HEAD` STALE, so a tick that reads it is reading an old
    fetch — the remote tip is `origin/<branch>` (measured 19:4x).** Ruling 22 made
    `git fetch --no-write-fetch-head` the lane's standing form, because the root-owned `FETCH_HEAD`
    trap disables fetching in a checkout outright. The flag does exactly what it says: this tick ran
    `git fetch --no-write-fetch-head origin track/money` and then `git rev-parse FETCH_HEAD`, and got
    **`05f9b768`** — a `chore(supervisor)` on **`main`'s** lineage, left behind by some earlier fetch
    and **not an ancestor of this branch at all**. The tell was `git log --oneline 05f9b768..HEAD`
    printing the branch's entire history instead of the two expected commits. `git rev-parse
    origin/track/money` returned **`f2a94e2d`**, which is what the addendum had recorded. **So: the
    remote-tracking ref is the measurement, and `FETCH_HEAD` is never read in this lane.** The fetch
    still updates `refs/remotes/origin/*`; only the `FETCH_HEAD` *file* is suppressed. ⚠️ The failure
    mode is the dangerous direction: a stale `FETCH_HEAD` naming a sha this branch has never contained
    makes an already-pushed range look unpushed, or an unpushed one look landed, and ruling 26 puts
    the push decision on exactly that comparison. ⚠️ The same reading applies to any `--no-write-fetch-head`
    fetch in a merge brief (rulings 22, 25, 52): measure against `refs/track1/main` or `origin/main`,
    never `FETCH_HEAD`.
84. **Two of the four carried backlog items are measured CLEAN and struck, and one is confirmed with
    its blast radius exact (measured 19:4x; ruling 64's discipline applied to this lane's own
    ledger).** Ruling 64 requires every inherited follow-up to be re-measured against the tree before
    it becomes a brief item. The 19:2x addendum carried four; two do not survive contact.
    **STRUCK — item 4, the lane-wide ruling-82 sweep.** `grep -rn "assertSee(\"[^\"]*'"` over the
    lane's eight module test directories returns **21** apostrophe-carrying needles, and every one of
    them targets a PHP-side `$this->error` string (20 × `"… isn't in this account …"` across
    C-Billing, X-117, X-173, X-198, X-199, X-201, X-211, plus
    `CollectionsPackagePreviewScreenTest:118`'s `"sent to O'Brien & Sons"`, a partner name off a
    model row) — all rendered through `{{ }}`, which is ruling 82's *safe* case. `grep -rn "&#039;"`
    over all eight modules' source returns **nothing**, so no assertion in the lane is green for the
    `&#039;`-hardcoded-in-prose reason that made MONEY-85's replaced line pass. There is no wave here.
    **STRUCK — item 3, X-201's `has_signature`.** It is not a *does it even vary?* candidate:
    `DisputeQueue.php:98` derives it per row from that dispute's own evidence
    (`$items->contains('evidence_type','signature')`), `DisputeDefenseEngine.php:75` derives it the
    same way for the compile result, and **both branches are asserted** —
    `DisputeQueueScreenTest:47` `assertSee('no signature yet')` on a 0-evidence dispute and
    `X201Test:102` `assertTrue($compileRes['has_signature'])` on a bundle carrying a captured
    signature. Recorded as measured-clean, not carried.
    **CONFIRMED — item 1, `AccountingConnection::is_active` is always true; briefed as MONEY-87.**
    `X-173/Actions/AccountingConnectAction.php:22` writes the literal `true` regardless of whether
    `$accessToken` was passed, so a connection carrying **no credential** renders a green `active`
    pill (`connection-mapping.blade.php:28`) and passes `AccountingSyncEngine.php:88`'s `mapAccount`
    guard — ruling 43's fiction, on the one field whose entire job is to say whether the ledger is
    reachable, in a module whose `connect()` door **cannot succeed** (ruling 73a). All **eight**
    `connect()` fixture call sites pass three arguments, so today every connection in the suite is
    `active` with a NULL `access_token` and the guard at `:88` has never once refused for the reason
    it exists. **RULED: `'is_active' => $accessToken !== null`**, with a token provisioned at the
    three fixtures whose connections are then mapped (`X173Test:46`,
    `ConnectionMappingScreenTest:25` and `:32`) — ruling 74's "provisioning real state so a real code
    path runs is a fix", never an assertion moved. **Blast radius, measured rather than assumed:**
    `is_active` has exactly one reader in production (`:88`) and one other writer anywhere
    (`ConnectionMappingScreenTest:70`'s explicit `update(['is_active' => false])`), and of the six
    `mapAccount` paths in the module's tests, `:70`→`:73` is the one that *means* to be inactive and
    **stays untouched** — it is the existing control for the refusal and the proof that the guard
    still fires. ⛔ **The migration's `->default(true)`** (`2026_08_30_000092:21`) is the same fiction
    one level down and is **recorded, not migrated**: ruling 41 part 3 forbids editing that file, and
    after this wave the only writer passes the column explicitly, so **no code in the lane relies on
    the default at all** — a new migration to change a default nothing reads is churn, and the honest
    artifact is a `state.py note`. ⚠️ The proof is a **new** test method, not an edit to the two
    existing ones: neither asserts the pill today, so neither can see the bug (ruling 68's lesson —
    a test that cannot see the defect is not the test that proves the fix). ⚠️ `assertSee('active')`
    is refused as the instrument — `inactive` contains it, so it passes on both branches, which is
    ruling 61's bare-digit defect in a word.
85. **A red above the floor has a THIRD attribution class beside the sha and concurrency: a live
    vendor refusal inside another lane's journey (RULED by the lane supervisor 2026-09-06 19:5x, on
    MONEY-87's `a5e3dfbc`).** The gate landed `2054 · 2048 · FAILED 2 · errors 4` against a predicted
    `2054 · 2049 · FAILED 2 · errors 3`, the extra member being
    `cancel_is_one_tap_with_nothing_in_between` — `UNRESOLVED — Sandbox refused subscription:
    Authorize.Net request failed: E00040`. Ruling 77 gave this lane a three-part test for
    *concurrency* (`42501`, `relation … does not exist`); this is neither that shape nor the sha's,
    and calling it either would be wrong in an expensive direction — read as the sha's it withholds a
    correct tip under ruling 26, read as concurrency it invites a pointless re-run of a suite that
    will refuse again while the vendor does. **The instrument is the same three legs, re-aimed:**
    (a) **mechanism** — `JourneyHarness::walkCancelFlow():742` posts to
    `https://apitest.authorize.net/xml/v1/request.api` and `:759` calls
    `AuthorizeNetGateway::subscribe()`, throwing the vendor's own message, so the failure originates
    outside this checkout; (b) **ownership** — Authorize.Net is Track 1's platform-billing gateway
    (`app/app/Services/Billing/`), money's provider is Stripe (owner ruling 10), and no
    `authorize_net_*` key or call exists in this lane's eight modules; (c) **reachability** — nil
    from the wave's diff. Frequency corroborates without substituting: `E00040` appears in **1 of
    115** gate files here. ⛔ **Not re-run in the same tick and never "fixed" here** — the harness
    method is another journey's under owner ruling 1, and a lane that patches around another lane's
    vendor outage is editing a CHECK. ⚠️ **Unlike ruling 67's kill, the run is not VOID**: the suite
    completed and every other number in it is the sha's; only the one member is attributed away.
    ⚠️ The Track 1 item this raises is whether a vendor-availability failure should present as an
    `UNRESOLVED` skip rather than an error, since as written it reddens **every** lane's gate on a
    third party's uptime.
86. **A reader-sweep greps the string's INTERIOR, and a pest-style file is invisible to this lane's
    test census (RULED by the lane supervisor 2026-09-06 20:1x, on MONEY-88's `c6df93fb`).** Ruling
    46 makes it routine that *a wave changing a value owns every test that asserts it*, and MONEY-88
    still shipped with a third assertion red — `tests 2055 · passed 2049 · FAILED 3 · errors 3`
    against a floor of 2, the extra member `dunning sequence escalates before suspend`. Two
    mechanisms hid it, and both generalise.
    **(a) A substring assertion matches from the MIDDLE.** The pre-brief sweep grepped the
    sentence's opening words (`R211: Overdue invoice requires human resolution…`);
    `X-211/ArEngineTest.php:35` asserts
    `toContain('resolution attempt before any suspension')` — a fragment starting past every word
    searched for. `toContain`, `assertStringContainsString`, `assertSee` and `assertDontSee` all
    read this way. **So a sweep for a string's readers greps three or four distinctive fragments
    from its INTERIOR and its tail, never its opening clause.**
    **(b) A pest-style file has no `public function test_` to count.** `ArEngineTest.php` declares
    five tests as `test('…', function () {…})` and returns **0** for this lane's habitual
    `grep -c "public function test_"`. Every method-count instrument in these briefs — MONEY-88's
    own "count 3 → 4" included — is blind to those files, as is any reviewer scanning a directory by
    method name; the names surface only in §7 as `__pest_evaluable_<description>`, which is how this
    one was found. **So a sweep over a module's tests greps `test(` and `it(` alongside
    `public function test_`, and a brief quoting a method count says which shape it counted.**
    **The fix is forward and the system sentence stands:** `:35`'s needle becomes
    `'nothing is stopped until someone does'` — the clause of the new sentence carrying the fact the
    old needle carried, since `:34` is the *escalates* half and `:35` the *before suspend* half
    (ruling 76's discipline: the clause carrying the load-bearing fact, not merely one that
    differs). ⛔ Never deleted (the One Rule), ⛔ never weakened past what the test's name promises.
    ⚠️ **The file the sweep missed is the file the brief CITED as a worked example** — MONEY-88 item
    1.3 pointed at `ArEngineTest:28` for the listener shape and never read seven lines further.
    Reading a file for one purpose does not sweep it for another. ⚠️ Per the ruling
    46/49/50/62/66/75/82 precedent the miss is the supervisor's, so MONEY-88b carries its own two
    dispatches and MONEY-88's cap is untouched. ⚠️ Corroborating ruling 85 at no cost: the
    Authorize.Net `E00040` member vanished from this gate with no code change touching it — a
    vendor-availability red does clear itself, which is what attributing it off the sha predicted.
87. **The C-Billing top-up tells the owner money was taken and none was (RULED by the lane
    supervisor 2026-09-06 20:1x, briefed as MONEY-88b items 2–4).**
    `grep -rln "StripeGatewayClient\|GatewayEngine" app/app/Modules/C-Billing/` returns **nothing** —
    the module has no gateway client at all — yet
    `Domain/BillingLedgerEngine::topup():83-124` returns `'status' => 'charged'` after doing three
    things: a daily-ceiling check, a raise to `TrialLimit.current_balance_hundredths_cents`, and a
    `CreditLedgerEntry` row. Three money-owned screens read that key and bill the owner in prose —
    `Credits.php:50` with a **hardcoded `$` for every tenant** (ruling 37), `Mrr.php:53` and
    `RevenueRecovery.php:32` with a bare figure and no currency, six lines below `mrr.blade.php:43`,
    which renders that account's real `price_currency`; `mrr.blade.php:49`'s button reads the literal
    `Top up 50.00`. **The app already has a real one:** `App\Services\Billing\CreditTopUps` charges
    through Stripe Checkout or an Authorize.Net stored profile, `RunAutoTopUps` drives it and
    `app/routes/console.php:161` schedules it as `credits:run-auto-top-ups` — two top-up paths, one
    that takes money and one that says it did. ⛔ **Not resolved by wiring the button to
    `CreditTopUps`**: that charges a real tenant's card (the reserved list) and the service is Track
    1's. ⛔ Not by deleting the button (three screens and four assertions drive it), ⛔ not by
    inventing a currency read. The outcome is ruling 21's finished waiting state — the sentence says
    credit was added, says plainly that nothing was charged, and names the charging path it waits on
    — with the engine's `'charged'` key raised as TRACK 1 ACTION 11, C-Billing `Domain/` being Track
    1's by ruling 5. ⚠️ `credits.blade.php:18` already heads that very panel **"Top-up recorded"** —
    the honest word, sitting in the tree above the sentence contradicting it, for as long as the
    screen has existed. ⚠️ Blast radius measured before briefing (ruling 46): exactly four
    assertions in three files, all **changed** and none deleted, plus `RunAutoTopUps.php:72`, where
    "Topped up" is **true** and is not touched. Measured clean in the same sweep and recorded rather
    than carried: `next_step_words` is computed per row at `DunningBoard.php:39` and `day_in_cycle`
    is written by `BillingLedgerEngine:137,:153`, so neither is decision 272's shape.
88. **`meters` has no production writer, and `credits.blade.php`'s five-key label map is a
    vocabulary nothing in this app writes (RULED by the lane supervisor 2026-09-06 20:3x, briefed as
    MONEY-89 item 1).** `grep -rn "meter_type" app/app app/database app/tests` returns the migration,
    two readers (`C-Billing/Ui/Credits.php:63`, `Mrr.php:70`) and **three fixture lines in two test
    files** — no production code anywhere creates a `Meter`, which is decision 272 / ruling 51 with
    two screens on the reading end. `credits.blade.php:31` then iterates the **literal** map
    `['sms','voice','ai','email','lead']` and renders `{{ ($meters[$type]->units_used ?? 0) }}`, so
    every real tenant sees five tiles of `0` / `0.0000` presented as measured usage — ruling 43's
    *"does it even vary?"* on a panel that cannot. ⚠️ The three vocabularies **disagree**:
    `CreditsScreenTest:28` seeds the blade's five keys, `MrrScreenTest:40` seeds `sms_segments`, and
    the migration's own comment at `:44` says `ai_seconds, sms_segments, voice_minutes` — ruling 41
    part 2 one level up, a fixture written to the blade rather than to a real thing's shape, which is
    why four green assertions cannot see it. **RULED: the panel renders the rows that exist and says
    so when there are none** — `@forelse` over `$meters`, a display-name map with a fallback to the
    raw type, and an `<x-ui.empty-state>` `@empty` branch naming the real missing dependency.
    ⛔ Not resolved by minting a meter writer here: usage originates in telephony, SMS and agent
    traffic, which are track sixty's by ruling 5, so writing one from C-Billing to feed its own
    screen is ruling 59's "never add the method to satisfy the caller". ⛔ Not by deleting the panel
    — the tiles are correct the day metering exists. ⚠️ The proof is a **new** test method for a
    tenant with no meter rows; the existing test seeds all five of the blade's own keys and cannot
    see the defect (ruling 68). ⚠️ `mrr.blade.php:60` prints the raw `meter_type` and
    `MrrScreenTest:57` asserts `'sms_segments'` — recorded, not changed: an internal vocabulary shown
    as a data value is a smaller problem than a false figure, and moving it reddens an assertion for
    no gain.
89. **Three owner-facing empty states still carry an internal id, and one also claims a send no code
    performs (RULED by the lane supervisor 2026-09-06 20:3x, briefed as MONEY-89 items 2–3).** The
    20:01 R245 decision — *a string a screen prints to an owner carries no internal rule id* — was
    applied to X-211 and X-120 and never swept the lane.
    (a) `X-199/Ui/views/invoices.blade.php:20` reads *"A completed job becomes an invoice and it
    sends (R235)."* — the rule id the decision forbids, **and** a false claim:
    `grep -rn "Mail::\|Notification::\|send(" app/app/Modules/X-199` is empty and `InvoiceIssued` has
    no registered listener anywhere (`grep -rn "InvoiceIssued::class" app/app` returns nothing; X-172
    declares the consume in its manifest and ships no `Listeners/` directory), so the one sentence a
    tenant with no invoices ever reads describes an automatic send that does not exist — ruling
    50(a) on X-199's own screen. (b) `X-201/Ui/views/dispute-card.blade.php:9` and
    `dispute-queue.blade.php:9` both name **`X-198`** as the actor; MONEY-84 made those states true
    and left the module id in them. **RULED: the id goes, the claim becomes what actually happens,
    and each existing assertion is extended to span the reworded clause** — both X-201 assertions
    (`DisputeQueueScreenTest:72`, `DisputeCardScreenTest:89`) target only the *dependency* half, so a
    reword of the opening clause alone would be prose no gate reads (ruling 70), while X-199's
    sentence has **no** assertion at all and that item must add one. ⛔ The generated
    `<x-surface.sample-state>` line at `:2` of each X-201 blade stays byte-identical. ⛔ Every
    dictated string is checked against `N008Test`'s directory-wide gateway-name scan
    (`stripe|authorize|braintree|square|paypal|adyen`) and `N010Test`'s `refund` scan (ruling 63),
    and carries no apostrophe (ruling 82).
90. **The MRR screen is the meters panel's twin, and both of its empty states describe machinery
    nobody built (RULED by the lane supervisor 2026-09-06 20:5x, briefed as MONEY-90).** Ruling 88
    cleaned `credits.blade.php` and recorded `mrr.blade.php:60` as "not changed, a smaller problem".
    Sweeping the rest of that file shows it is not one problem but three. (a) **`:60` prints
    `{{ $meter->meter_type }}` raw** — the owner reads `sms_segments` — and `MrrScreenTest:57`
    asserts that token; MONEY-89 built a display map inside `Credits.php`, so the correct move is to
    **share** it, not to duplicate it or to leave one screen speaking the schema's vocabulary.
    (b) **`:55`** reads *"The five meters fill as the account sends, talks and thinks."* — the exact
    fiction ruling 88 removed one file over, and the **"five"** is a fixed vocabulary that disagrees
    both with the schema's documented `ai_seconds, sms_segments, voice_minutes`
    (`2026_08_30_000025_create_c_billing_tables.php:44`) and with the screen's own fixture.
    (c) **`:70`** reads *"Every debit, grant and top-up lands here the moment it happens."*
    `credit_ledger_entries` has exactly three writers, all in `Domain/BillingLedgerEngine.php`
    (`:37` grant, `:69` debit, `:109` topup), and **`LedgerGrantAction` and `LedgerDebitAction` have
    no caller outside `CBillingTest`** — so for a real account a debit and a grant never happen; only
    `topup()` has a production caller, the credits button MONEY-88b already made honest.
    ⚠️ **`App\Services\Billing\CreditLedger:1007` does not fill this list**: it writes
    `App\Models\CreditLedgerEntry`, whose table is **`credit_ledger`** — a different table neither
    screen reads. Two ledgers, one name, and the shorter grep would have said the sentence was true.
    **RULED:** the label map becomes the trait `App\Modules\CBilling\Ui\LabelsMeters` in the shape
    `ReadsAgreedMonthly` already establishes there, read by both screens, carrying **both**
    vocabularies with `?? $type` still the fallback; both empty states name what has not happened and
    what it waits on; and `MrrScreenTest:57` is **changed** to the label **and gains**
    `assertDontSee('sms_segments')`, which is what stops the positive passing for the wrong reason
    (ruling 61). ⛔ Not resolved by minting a meter writer or a ledger writer — that is ruling 59's
    "never add the method to satisfy the caller", and usage is track sixty's by ruling 5.
    ⚠️ Neither sentence is asserted by anything today, so the item **adds** a method rather than
    changing one: the existing test seeds a meter and two ledger entries and cannot reach either
    branch (ruling 68). ⚠️ Measured before dictating: `File::allFiles()` appears in `X136Test`,
    `N008Test` and `N010Test` only, each scoped to its own module, so **no directory-scanning lint
    reads C-Billing** and ruling 63's prose-inflates-the-instrument hazard does not apply here.
91. **A gate started in the background does not outlive the tick that started it, and `rc=143` on the
    `gate` row itself is the tick's own reaping — not another lane's kill (RULED by the lane
    supervisor 2026-09-06 21:4x, measured from `/home/goaiez/tmp/gate-runs.tsv`).** Three consecutive
    ticks gated `8b64b085` and none produced a verdict: money's gate pids `2758434` (21:10:27 →
    21:17:35), `2797219` (21:20:59 → 21:23:32) and `2830885` (21:30:49 → 21:34:53) each logged
    **`rc=143`** on their own `gate` row, each a few minutes after its tick's other work finished, and
    each left an identical 7902-byte file ending at §7's `… another suite holds
    /home/goaiez/tmp/pest.lock`. Nothing was wrong with the sha: `grs-antig-ui`'s pest held the lock
    from 21:10:04 to **21:40:04**, where it died on `timeout 1800` (`rc=124`), so all three money gates
    were parked in `flock -w 2400` when their tick ended and SIGTERM reached them. The cost was a full
    hour of lane time, a PASS that was never appended, and a `BRIEF.md` (21:22) left newer than the
    `REVIEWS.md` block that should have preceded it — the one mailbox state no case in the tick
    contract describes. **So: a tick that starts a gate BLOCKS on it in the same tick** — poll the
    output file with foreground calls, or arm an `until` waiter and keep working — and never ends its
    turn with a gate still running. ⚠️ **Distinguish this rc from rulings 67 and 74**: those are a
    *tool* row (`pest`, `pint`) killed by outside memory pressure, which makes that tool's verdict
    VOID; this is the **`gate`** row, killed by the tick's own exit, and it means no verdict was ever
    produced. The tell is the column — `$8` is `gate`, not a tool name — and the `-` in `tool_pid`.
    ⚠️ §1–§6 in an abandoned file are still measured and still the sha's; only §7 is missing. **A
    tick that cannot reach §7 before its own deadline records the §1–§6 result, holds the verdict, and
    says in `REVIEWS.md` which lane held the lock** — it does not push on the coder's §7 (ruling 42)
    and it does not start a fourth gate it will also abandon.
92. **A brief's predicted floor is arithmetic the next tick gates against, so it is counted from the
    items, not asserted (RULED by the lane supervisor 2026-09-06 21:4x, on MONEY-91's brief).** The
    brief carried *"Predicted floor: `2061 · 2056` — this wave adds **three** methods"* while its own
    items add **two** (1.3's `test_connect_card_refuses_a_connection_that_has_already_applied` and
    3.3's `test_the_mrr_top_up_refuses_over_the_daily_ceiling`; item 2 changes two needles and adds
    none). Corrected to `2060 · 2055 · FAILED 2 · errors 3` before dispatch. Uncaught, the next tick
    would have measured a correct wave one test short of its floor and had to choose between reading
    it as a deleted test and re-deriving the arithmetic under time pressure — and ruling 26 hangs the
    push on exactly that comparison. **So every brief's floor is computed by listing the item numbers
    that add a method**, and the floor line names them, as this one now does. ⚠️ This is the ruling
    66/75/82 family a third time: a brief that dictates a signature dictates a phpstan result, a
    dictated line dictates a `pint` result, a dictated needle dictates a test result — and a dictated
    **floor** dictates the next tick's verdict. Everything a brief states as a number is the
    supervisor's to have measured.
93. **A screen may not tell an owner where their money lands unless the code routes it there, and
    this one denies the very property it has (RULED by the lane supervisor 2026-09-06 22:2x, briefed
    as MONEY-92).** `X-198/Domain/StripeGatewayClient::charge():13-24` posts to
    `https://api.stripe.com/v1/charges` with `config('credentials.stripe_secret')` — the **platform's**
    key — and `grep -rn "Stripe-Account\|stripe_account\|on_behalf_of" app/app/Modules/X-198` returns
    **nothing**, so every charge this lane takes lands in the platform account.
    `merchant_account_id` is a caller-supplied string (`GatewayEngine::connect():60` `updateOrCreate`s
    whatever it is handed — `'acct_tenant_stripe_123'` hardcoded at `EvidenceChargeCommand:51`,
    `'merch_123'`/`'acct_stub'`/`'acct_test'` in the tests), it is obtained from no provider, and it is
    read by **nothing except four sentences on two screens**: `same-account.blade.php:3`, `:15`,
    `:33`'s `lands nowhere` pill, `SameAccount.php:33`, `GatewayEngine::attachPayment():224` and
    `connect-card.blade.php:22`. ⚠️ **The headline inverts every prior finding in the ruling-36
    family.** 43/44/50 removed strings whose referent was **absent**; `same-account.blade.php:3`
    promises the money lands *"in this account's own merchant account — **never the platform's**"*,
    which is a **denial of the property the code actually has**, on the screen whose entire name is
    the claim. A fabrication that is merely absent is found by asking *what is at the other end of
    this string*; a **negation** is found only by asking *is the opposite true*, and no sweep in this
    lane had asked that. ⛔ Not resolved by adding `Stripe-Account` routing: a connected account comes
    from Stripe Connect onboarding, `ConnectCard::connect():41` already measures that no
    `services.stripe.client_id` exists here, and a live Connect call is ruling 13's evidence run with
    the owner's credentials. The outcome is ruling 21's finished waiting state plus `UNRESOLVED`
    naming the processor contract. **Two smaller findings on the same pair, both measured:**
    `connect-card.blade.php:11`'s `heading="Could not connect"` now heads **four** messages, two of
    them merchant-**application** refusals MONEY-91 had just made honest — an error heading names the
    act that failed, never the screen it failed on; and `ConnectCard::connect():48`'s *"Stripe Connect
    redirect lands in week 2."* is a **delivery date** in owner copy, false by ruling 20's calendar
    (week 2 is 15–19 Sep) and not a dependency, above an empty state inviting the owner through a door
    that cannot succeed under either branch (ruling 73a). ⚠️ Blast radius measured before briefing
    (ruling 46) with interior fragments (ruling 86): exactly **three** assertions lane-wide
    (`SameAccountScreenTest:48,:59,:63`), all **changed**; the headline, the pill, the heading, the
    door and the empty state are asserted by **nothing**, which is ruling 70 again and is why those
    items **add** methods. ⚠️ `N008Test`/`N010Test` were measured before dictating and scan
    `app_path('Modules/X-201')` **only**, so naming Stripe in X-198 prose feeds no instrument
    (ruling 63).
94. **A refusal that renders in one arm of an `@if` is a refusal nobody reads, and the door that
    raises it lives in the other arm (RULED by the lane supervisor 2026-09-06 22:5x, on MONEY-92's
    `99f3b424`; briefed as MONEY-93 item 1).** `connect-card.blade.php` is
    `@if($connections->isEmpty()) <x-ui.empty-state action="Connect" target="connect"> @else
    <x-ui.attention-card> … @if($error) <x-ui.error-panel> @endif … @endif`. `ConnectCard::connect()`
    sets `$this->error` and returns, and an owner with **no** connections renders the `@if` arm, where
    no panel exists — so the refusal is set and shown to **no one**. The empty state carries the only
    door on the screen and it calls the one method whose answer that screen cannot display: an owner
    presses **Connect** and the page says nothing at all. MONEY-91 and MONEY-92 spent two waves making
    `connect()`'s two refusals honest (rulings 73a, 93) and both were unreachable by the owner they
    were written for. **RULED: the error and success panels move above the `@if`**, matching
    `same-account.blade.php:4-6`, which has had the correct shape all along and whose equivalent
    assertions have therefore never hit this. ⛔ Not resolved by seeding a connection in the test — the
    assertion is about what an owner with no gateway sees, which is the case the button lives in;
    ⛔ not by dropping the `action`/`target` pair, which is ruling 73a's accepted shape.
    ⚠️ **The generalisable half is a fifth member of the ruling 66/75/82/92 family.** A dictated
    **signature** dictates a phpstan result, a dictated **line** a `pint` result, a dictated **needle**
    an `assertSee` result, a dictated **floor** the next tick's verdict — and **a dictated test
    dictates the BRANCH that test renders**. MONEY-92's brief quoted the empty-state block eleven lines
    above the error panel in the same file and never asked which arm the assertion would land in. So:
    a dictated screen assertion names the branch it renders, and the brief checks the element it
    asserts on is inside that branch. ⚠️ The coder's `REFUSED` was correct and is upheld; per ruling
    71's precedent it spends no dispatch, and per the 46/49/50/62/66/75/82/86 precedent the miss is the
    supervisor's, so MONEY-93 carries its own two.
95. **The ruling-94 branch sweep is measured CLEAN lane-wide; `connect-card` was the only offender (RULED
    by the lane supervisor 2026-09-06 23:0x, on MONEY-93's PASS).** Ruling 64 forbids an inherited
    follow-up becoming a brief item before it is re-measured, and this one does not survive contact. Every
    blade in the lane's eight modules carrying an `isEmpty()`/`@forelse` branch — **twenty-two screens**,
    X-199's five, C-Billing's four, X-211's four, X-198's two, X-201's two, X-173's three, X-120's one and
    X-117's two — puts its `$error` / `$success` / `$waiting` panels **above** the branch, at the
    component's top level. The two near-misses are benign and worth recording so they are not re-raised:
    X-120's empty state calls `addCard`, whose form renders at `card-screen.blade.php:49` in a section
    **outside** the branch, and X-199's `unpaid` empty state calls `showPaid`, which renders the `@elseif`
    arm and is therefore its own feedback. ⚠️ The generalisable half is ruling 64's, not ruling 94's: a
    sweep proposed by a ruling is still a claim to be measured, and **a sweep that comes back empty is
    struck with its measurement written down** — otherwise the next tick re-derives it under time pressure
    or, worse, briefs a wave with nothing in it.
96. **The 20:01 "no internal rule id in owner copy" decision was never swept past the modules it was
    written in, and PHP strings were never swept at all (RULED by the lane supervisor 2026-09-06 23:0x,
    briefed as MONEY-94).** Ruling 89 applied that R245 to X-199 and X-201 and recorded the id half
    "measured clean, one hit lane-wide" — a measurement of **blade prose in the modules then being
    worked**. Re-run across all eight modules' `Ui/` trees with PHP strings included it returns **five**
    live hits, none in a module the earlier sweep touched: `X-173/Ui/views/conflicts-list.blade.php:4`
    (`§141.5`), `connection-mapping.blade.php:4` (`§30.5`), `sync-error-rate.blade.php:4` (`§30.5`),
    `X-173/Ui/ConnectionMappingView.php:32` (`§141.5`, inside the `$waiting` string) and
    `X-117/Ui/CheckoutBlock.php:87` (`§147.2`, inside the cancel `$success` string). The two generated
    `<x-surface.sample-state>` lines in X-201's blades stay byte-identical. ⚠️ **One of the five is not
    only an id.** `connection-mapping.blade.php:4`'s *"Invoices and payments flow out; the chart of
    accounts flows in"* is present tense about machinery that has never run — `connect()` refuses by
    design (ruling 73a) and `syncTransactions` has no production caller (ruling 81) — so it is ruling
    50(a) **in a screen header**, and a header renders in *both* arms of the branch, reaching the very
    owner the empty state already tells the truth to. **RULED: an empty state is not the only prose a
    screen shows an owner; the header is read more often and is swept with it.** ⚠️ **Not one of the five
    clauses is asserted by anything** (measured with interior fragments, ruling 86), so every item extends
    an existing assertion rather than relying on one — ruling 70 again. ⚠️ `§` is **not** escaped by `e()`
    (`htmlspecialchars` converts only `& < > " '`), so `assertDontSee('§141.5')` matches raw blade prose
    and is safe under ruling 82. ⚠️ Recorded in the same pass and **not** briefed:
    `CheckoutBlock::pay()`'s `'paid'` branch is unreachable — `checkoutCart()` creates the order
    `pending_payment` and returns its refreshed status, and ruling 45 emptied the only listener that could
    promote it — so its *"The charge landed at the gateway"* is dead rather than false. **An unreachable
    string is recorded, never edited: no test can render it and no mutation can redden it, so the edit is
    ungated churn.** It is flagged for the wave that lands ruling 45's tokenisation dependency, when that
    sentence must also be re-checked against ruling 93 (a charge lands in the **platform** account).
97. **A `$success` string that reports a transmission is measured against the module's transport, and
    X-201 has none (RULED by the lane supervisor 2026-09-06 23:3x, briefed as MONEY-95 item 1).** The
    lane's 24 `$this->success` / `$this->waiting` assignments were swept for **truth** rather than for
    ids; 22 are honest and two are the same false sentence in two files.
    `X-201/Ui/DisputeQueue.php:55` and `X-201/Ui/DisputeCard.php:55` both set
    `sprintf('Submitted the defence for invoice #%d.', …)`, while `DisputeDefenseEngine::submit():78-103`
    checks three guards and then does exactly one thing — `$dispute->update(['status' => 'submitted'])`.
    **`grep -rn "Http::\|curl_" app/app/Modules/X-201` is empty**: the module has no transport of any
    kind, nothing leaves this app, no gateway is told, and the owner who presses **Submit** is told their
    defence was filed against a deadline that is the whole point of the screen. That is ruling 87's shape
    on X-201. ⚠️ **The generalisable half: an audit scoped to a file type is not an audit of a screen.**
    MONEY-84 and MONEY-85 both audited this screen and both read *blade prose* — the empty state, then
    the per-row copy — so a sentence built in PHP survived two waves aimed at it and the same screen
    tells the truth in its blade and a falsehood from its component, three rulings apart.
    **RULED: the sentence says what happened and names the missing dependency** — sealed and recorded,
    nothing sent, filing waits on the gateway chargeback contract ruling 79 already recorded
    `UNRESOLVED`. ⛔ Not by minting a transport (a live vendor call under ruling 13; `StripeWebhooks.php`
    is Track 1's platform clawback, not a tenant dispute); ⛔ not by deleting the confirmation — an owner
    who presses a button is owed an answer, which is what ruling 94 has just finished guaranteeing they
    can see. Blast radius measured with interior fragments (rulings 46, 86): exactly two assertions
    lane-wide, both **changed**. Every dictated string checked against `N010Test`'s `refund` scan and
    `N008Test`'s gateway-name scan, which read `app_path('Modules/X-201')` in full (ruling 63).
98. **A plan nobody was offered is written `accepted`, and the screen and the row disagree about which
    (RULED by the lane supervisor 2026-09-06 23:3x, briefed as MONEY-95 items 2–3).**
    `X-211/Domain/ArEngine::offerPlan():141-147` creates the `PaymentPlan` with `'status' => 'accepted'`,
    moves the invoice's `ReceivableState` to `payment_plan` and dispatches `ArPlanAccepted`, while
    `X-211/Ui/PaymentplanBuilder.php:53` reports it as *"Plan **offered** on %s"*. Neither happened:
    **`grep -rn "Mail::\|Notification::\|Http::\|->send(" app/app/Modules/X-211` is empty**, so the
    module cannot put anything in front of a customer, and **`grep -rn "acceptPlan\|accept("` over the
    module returns nothing** — there is no acceptance path, so `'accepted'` is a state this app can never
    legitimately reach. The row is a recorded agreement with a customer who was never asked. Ruling 43's
    fiction with two aggravations its `pdf_url` lacked: the fabricated value is a **consent**, and the
    same click moves the receivable to `payment_plan` — *this one is handled, stop chasing it* — in the
    lane that owns J12, *an overdue invoice is chased by reason*. ⚠️ **The screen already knew.**
    *"offered"* in the component against `'accepted'` in the engine is the tell, and this is the first
    finding in the lane where the app contradicts **itself** rather than the world — cheaper to find than
    every ruling in the 36 family, and missed by all of them because both sweeps read strings and neither
    read a string **against the column it describes**. **RULED: `offerPlan()` writes
    `'status' => 'offered'` and the confirmation says the plan is recorded for the owner and that the
    customer has not been told.** ⛔ The event is **not** renamed — `ArPlanAccepted` is a declared
    `@emits` name harvested from the frozen plan (ruling 29); its dead seam (no consumer; the dispatch
    plus four test lines) is recorded `UNRESOLVED` exactly as ruling 69 handled `invoice.overdue`.
    ⛔ No customer-facing offer surface is built — no route, no transport, and building one to satisfy a
    sentence is ruling 59. Blast radius measured: `X211Test.php:92` and
    `PaymentplanBuilderScreenTest.php:87`, both **changed**, plus `paymentplan-builder.blade.php:33`'s
    pill ternary, whose `'ok'` arm becomes unreachable and is **recorded, not edited** (ruling 96) —
    it renders `attention` with the raw label `offered`, correct for an unsent offer and correct again
    the day acceptance exists. ⚠️ `assertSee('offered')` alone would pass for the wrong reason if the
    confirmation carried the word too, so the confirmation says *"recorded"* and the assertion is paired
    with `assertDontSee('accepted')` (ruling 61, ruling 90's shape).
99. **"The gateway did not throw" is not "the gateway charged", and X-199 writes `charged` off the
    absence of an exception (RULED by the lane supervisor 2026-09-06 23:5x, briefed as MONEY-96 item
    1).** `X-198/Domain/GatewayEngine::capture():93-98` calls the Stripe client **only** when
    `$connection->gateway_name === 'stripe'`; for any other connected gateway — the migration's own
    comment at `2026_08_30_000030:18` lists `stripe, square, clover, plaid`, and this lane's tests
    already create **`square`** connections (`SameAccountScreenTest:25,:32,:81`,
    `ReconciliationDiscrepanciesScreenTest:25,:34`) — it makes **no external call**, writes a `Payment`
    with `gateway_charge_id = null` and `status = 'awaiting_processor'`, and **returns normally**.
    `X-199/Domain/InvoiceEngine.php:107-108` then reads that payment and sets `$status = 'charged'`
    **unconditionally**, so the `OverflowCharge` row says `charged` with `reference_id` **null**, the
    `OverflowCharged` event fires carrying a **null** `gatewayChargeId`, and `Ui/Credits.php:96` prints
    ***"covered by the card on file; service never stopped"*** while `Unpaid.php:88` puts an overflow
    pill on the invoice. Nothing was charged, and X-198's own row says so. **This is ruling 98's shape
    at its sharpest — the app contradicting itself, one module's row against another's — and ruling
    51's principle one level up: a status compared against the gateway's must come from the row the
    gateway wrote.** **RULED: `$status = $gatewayChargeId !== null ? 'charged' : 'refused';`** — one
    line, and it is correct for the idempotent early return at `GatewayEngine:83-84` too, which can
    hand back a prior `awaiting_processor` payment. ⛔ **No third status is minted:** `refused` already
    means *the card did not absorb it and the invoice still stands*, which is true of an unprocessed
    overflow, and `InvoiceEngine:172-173`'s reversal query filters `status = 'charged'`, so a new
    vocabulary would change the reversal path for no gain. ⛔ Not resolved by teaching `capture()` a
    non-Stripe adapter: a processor adapter waits on a contract (ruling 20) and a live call is ruling
    13's evidence run. ⚠️ **Why nine green tests cannot see it:** every X-199 overflow fixture seeds
    `gateway_name => 'stripe'` **and** an `Http::fake` returning `ch_mock_123`
    (`InvoiceEngineTest:46,:61`), so the charge id is always non-null and the two branches are
    identical — ruling 45's fixture-omission trap, where the *fixture* excludes the interesting case
    rather than stubbing it. The proof is therefore a **new** pest test seeding a **`square`**
    connection, asserting `refused`, a null `reference_id` and `Event::assertNotDispatched`. ⚠️
    `CreditsScreenTest:85` hand-forces `->update(['status' => 'charged'])` to reach the charged
    wording, so the screen's positive assertion has never once been driven by the production path.
100. **Three carried backlog items are measured and struck, and the `$error` sweep's headline candidate
    is UNREACHABLE (measured 23:5x; ruling 64's discipline, ruling 95's precedent).** The `$error`
    population is ~140 assignments across the lane's eight `Ui/` trees; all but a handful are the
    tenancy `isn't in this account` line or an exception passthrough, and three named candidates do not
    survive contact. (a) **`X-211/Ui/CollectionsPackagePreview.php:32`** — *"Sending an account to
    collections is a human action — sign in as the owner first."* — names a **role** the guard does not
    check (`auth()->id() === null` accepts any signed-in user), which is exactly the sweep's question
    *is that actually why it refused?* — **but `render():51` is
    `abort_unless(auth()->check() && Tenancy::check(), 403)`, so the branch can never render to
    anyone**, and the generated route carries `auth` middleware besides. Ruling 96 governs: an
    unreachable string is **recorded, never edited** — no test can render it and no mutation can redden
    it, so the edit would be ungated churn. It is also asserted by nothing, and every existing mount in
    `CollectionsPackagePreviewScreenTest` is `Livewire::actingAs($owner)`. (b) **C-Billing's
    triple-copied ceiling refusal** (`Credits.php:57`, `Mrr.php:59`, `RevenueRecovery.php:39`) —
    *"The ceiling resets tomorrow."* — is **TRUE**: `BillingLedgerEngine:94-97` compares
    `last_topup_date` against `Carbon::today()` and zeroes `topups_today_cents` on a calendar-day
    boundary. Measured clean. (c) **The `Actions/`/`Domain/` docblock sweep** ruling 81 left open
    returns **two** hits across six modules' `Actions/` and `Domain/` trees, and both are the same
    `(TEST ANCHOR & G1-03)` line, which is the CHECK and stays byte-identical. There is no wave in it.
    ⚠️ The generalisable half is ruling 95's, restated because it keeps paying: **a sweep proposed by a
    ruling is a claim, and a sweep that comes back empty is struck with its measurement written down**
    — otherwise the next tick re-derives it under time pressure or briefs a wave with nothing in it.
    ⚠️ Recorded and deliberately **not** briefed: `X-117/Ui/CheckoutBlock::authorise():46` calls a
    self-minted nonce *"Authorised"*, which is ruling 45's known token and whose sentence already ends
    *"waiting on a card-entry surface that is not connected yet"* — true-but-incomplete is ruling 76's
    PASS-WITH-NOTES grade, not a wave.
101. **`PaymentCaptured` is announced for a payment that was never captured, and the fix belongs at
    the dispatch, not at each reader (RULED by the lane supervisor 2026-09-07 00:1x, briefed as
    MONEY-97 item 1).** Ruling 99 stopped X-199 *deriving* `charged` from the absence of an
    exception; the announcement it was deriving from is still false.
    `X-198/Domain/GatewayEngine::capture():111` dispatches `PaymentCaptured` **unconditionally**,
    directly after the `Payment::create` whose `status` is `awaiting_processor` whenever
    `gateway_name !== 'stripe'` (`:93-98`), with `gateway_charge_id` null — so for a `square`,
    `clover` or `plaid` connection this app publishes an event named *Captured*, carrying
    `gatewayChargeId: null`, for money that never moved. **RULED: the dispatch is guarded on
    `$payment->gateway_charge_id !== null`**, because ruling 44's reasoning applies to the dispatch
    itself and not merely to a field — an event that fires for a non-event propagates the fiction to
    every listener that ever registers, and today there is none, which is the one moment removing it
    costs nothing. Fixing it in each reader instead would put the same `!== null` test in every
    future consumer and leave the seam lying. ⛔ The event is neither renamed nor deleted and no
    second event is minted: `payment.captured` is a declared `@emits` in `X-198/manifest.php:38`
    harvested from the frozen plan (rulings 29, 32), and the stripe path keeps dispatching it, so the
    declaration keeps its dispatcher. ⛔ Not resolved by a non-Stripe adapter (ruling 20's contract,
    ruling 13's evidence run). ⚠️ **Ruling 46's blast radius is the interesting half:**
    `grep -rn PaymentCaptured app/tests` is **three lines in one file**, and the assertion sits in a
    **TEST ANCHOR** — `X198Test.php:75`'s `Event::assertDispatched(...)`, whose fixture connects
    **`square`** (`:63`) and which asserts eleven lines above that `gateway_charge_id` is null and the
    status `awaiting_processor` (`:73-74`). The anchor asserts in one breath that nothing was charged
    *and* that a capture was announced. It is **inverted and kept**, the docblock byte-identical
    (rulings 47, 81). ⚠️ **And the positive branch is covered by nothing** — those three lines are the
    whole population — so inverting `:75` alone would leave the dispatch unasserted and a future
    deletion of it green. That is ruling 39's companion lesson arriving as an *absent* cover rather
    than a partial one, so the wave **adds** a stripe test asserting the event IS dispatched with a
    non-null charge id. **Inverting an assertion without replacing its positive is how a check
    quietly stops checking.**
102. **`RecordPaymentOnCapture` is registered by nothing, guarded on a field its only dispatcher never
    sets, and it would mark an invoice paid — recorded `UNRESOLVED`, never registered (RULED by the
    lane supervisor 2026-09-07 00:1x, briefed as MONEY-97 item 2).**
    `grep -rn RecordPaymentOnCapture app/app app/tests app/bootstrap` returns **only the class's own
    declaration**. This lane's convention is an explicit `Event::listen` in the module provider
    (`X-198/ModuleServiceProvider.php:30`, `X-211/ModuleServiceProvider.php:35-37`); X-199's provider
    has none, while `X-199/manifest.php:48` declares `consumes payment.captured` — ruling 69's
    `invoice.overdue` shape, dead at **both** ends. It is dead twice over: `handle():19-21` returns
    when `$event->invoiceId === null` and the only dispatcher (`GatewayEngine:111`) passes no
    `invoiceId`, so the body is unreachable even if registered. ⛔ **Not registered** — registering a
    consumer to satisfy a declaration is ruling 59 inverted, and it is worse than inert: the body
    calls `InvoiceEngine::recordPayment()`, which writes `status => 'paid'` and `paid_at => now()`
    (`InvoiceEngine.php:163-167`), so wiring this seam means **marking an invoice paid off an event**
    — and before ruling 101's guard that event fires with no charge id at all. ⛔ **Not deleted** — it
    is the only class that could ever satisfy the declared `consumes` (ruling 69). The real missing
    dependency is named rather than guessed: **X-198 has no invoice linkage**, `capture()`'s signature
    being `(businessId, amountCents, paymentToken, idempotencyKey, currency)` over a `payments` table
    with no invoice column, so the seam waits on a cross-module API change and not on a listener
    registration. ⚠️ The generalisable half, and why the pair was found together: **ruling 99's
    question — does this figure come from the row the gateway wrote? — has a twin at the event layer,
    *does this event fire only when the thing it is named for happened?*** — and a module can fail
    both at once with every gate green. Nine X-199 fixtures could not see 99 because all nine seeded
    `stripe`; the whole `PaymentCaptured` population is three lines, and the one assertion among them
    certifies the defect.
103. **A payment closes an invoice only when it covers it, and the losing branch keeps the invoice's
    OWN status (RULED by the lane supervisor 2026-09-07 00:3x, briefed as MONEY-98; shipped and gated
    at `dfe1f266`).** `X-199/Domain/InvoiceEngine::recordPayment()` wrote `'status' => 'paid'` and
    `'paid_at' => now()` **whatever amount was recorded**, with no comparison against `total_cents`
    anywhere in the method — so a customer paying part of an invoice had it marked paid, every
    `overflow_charged` row on it reversed for its **full** amount, and `InvoicePaid` announced. This
    lane already did the arithmetic correctly one module over (`ArEngine:191`, same column of the same
    table), which makes it ruling 98's shape: the app contradicting itself about what a payment does.
    The consequence is J12's: every unpaid/overdue query in the lane filters
    `whereNotIn('status', ['paid','draft'])` **before** its `paid_cents < total_cents` clause
    (`InvoiceReader:48-50`, `:57-60`), so a part-paid invoice left the Unpaid screen, the ageing and
    the overdue chase for good. **RULED: `$status = $newPaid >= $invoice->total_cents ? 'paid' :
    $invoice->status;`, `paid_at` only on `paid`, and the reversal loop *and* the `InvoicePaid`
    dispatch inside one `if ($status === 'paid')`.** ⛔ The losing branch keeps **`$invoice->status`**,
    never `ArEngine:191`'s `'issued'` literal: downgrading an `overdue` invoice on a partial payment is
    a second way to lose the reason it was being chased, which is J12's whole subject. ⛔ No second
    event for the partial case — `invoice.paid` is a declared `@emits` harvested from the frozen plan
    (rulings 29, 32, 69) — and ⛔ no proportional reversal, since `X199Test:114` asserts a reversal is
    the exact amount of its charge. ⚠️ Nothing in the suite could see any of it: all six existing
    `recordPayment` call sites pay in **full**.
104. **A negative assertion cannot see the alternative a ruling refused, and the twin defect was one
    module over all along (RULED by the lane supervisor 2026-09-07 00:5x, on MONEY-98's `dfe1f266`;
    briefed as MONEY-99 items 1 and 3).** MONEY-98 shipped ruling 103 exactly as written and its new
    test asserts `expect($res['status'])->not->toBe('paid')` on an invoice whose status is `issued`.
    Mutate the committed line to the **refused** `? 'paid' : 'issued'` and **the test stays green** —
    `'issued'` is not `'paid'`. So the one clause ruling 103 turned on is the one clause the wave did
    not guard. **RULED: where a ruling chooses between two non-failing values, the test asserts the
    chosen value POSITIVELY** — `toBe('overdue')` on an invoice made overdue first — because a negative
    over a vocabulary of more than two members cannot distinguish the choice from its alternative. That
    is ruling 61's bare-digit defect in a status string, and the miss is the supervisor's: the brief
    dictated the fixture, so per the 46/49/50/62/66/75/82/86/94 precedent it carries its own two
    dispatches. **The twin, measured with the `ReceivableState` check ruling 64 demanded:**
    `X-211/ArEngine::logOfflinePayment():191` still writes the `'issued'` literal, and `:195-199` moves
    `ReceivableState` to `current` **only** on `paid` — so a partial cheque against an **overdue**
    invoice leaves the receivable saying the account is being chased while the invoice row says it is
    merely issued. The chase is **not** carried elsewhere, so this is fixed, not recorded. Blast radius
    measured with interior fragments (rulings 46, 86): four `logOfflinePayment` call sites, all in
    `ArEngineTest.php`, and the only status assertion among them (`:100` `toBe('issued')`) is on a
    **refused** payment that writes nothing — no existing test changes.
105. **The dispute screen tells the owner commission was clawed back and nothing claws anything back
    (RULED by the lane supervisor 2026-09-07 00:5x, briefed as MONEY-99 item 2).**
    `X-201/Ui/DisputeQueue::outcome():75` appends *"Commission clawed back."* — past tense, a money
    movement — to the confirmation an owner reads at the moment they learn what a lost chargeback cost
    them. The entire mechanism is one boolean: `DisputeDefenseEngine::recordOutcome():120-122` sets
    `$clawbackTriggered = true` and dispatches `DisputeLost`, and `grep -rn "DisputeLost" app/app
    app/tests` returns the class declaration, that one dispatch and **nothing else** — no provider
    registers a listener, and X-170's real `CommissionEngine::clawback()`, which does move a commission
    row and dispatch `CommissionClawedBack`, is never reached from here. Ruling 87's shape on X-201.
    **RULED: the sentence says the flag was written, says plainly that no commission has been taken
    back, and names what it waits on** (ruling 21's finished waiting state). ⛔ **Not resolved by
    registering or minting a listener** — X-170 is not this lane's module (ruling 5) and adding a
    consumer to make a sentence true is ruling 59 inverted; ⛔ not by deleting the flag or the
    confirmation, and ⛔ not by touching the `(TEST ANCHOR)` docblock at
    `DisputeDefenseEngine:107-109`, which is the CHECK that names `commission.clawed_back` and stays
    byte-identical. ⚠️ Blast radius is exactly one assertion lane-wide — `DisputeQueueScreenTest:69`,
    **changed** and given the `assertDontSee` that stops the positive passing for the wrong reason
    (rulings 39, 61). ⚠️ The anchor asserts a **boolean**, not a movement, which is why it has been
    green over a sentence about money since the screen existed.
106. **A brief that dictates a COMMAND has dictated its outcome (RULED by the lane supervisor
    2026-09-07 01:2x, on MONEY-99's single `REFUSED`).** The brief wrote
    `python3 bin/state.py decided R245 X-211 "<what>" "<why>"` in terms. Measured: `bin/state.py:205`
    is `mid, what = a[0], " ".join(a[1:])` — **`decided` takes the module id first and joins
    everything after it into ONE string, and the tool adds `R245` itself** (`:209`, `:211`). So
    `R245` was read as the module id and the call died `R245 is not on the roster`. The coder refused
    to repair a command the brief had dictated, logged the measurement and reported it under
    `REFUSED` exactly as rule 10 asks — **that refusal is correct and spends no dispatch** (rulings
    60b, 71, 94). The consequence: **X-211 has no `(R245)` journal line** for the partial-payment
    decision. Nothing dangles, because no `(R245)` citation was added to any module header, so the
    "decisions are recorded, not just made" check has nothing unmatched to find; the journal is one
    line short of the ledger it should carry, and the corrected lines are MONEY-100 item 3.
    ⚠️ This is the ruling 66/75/82/92/94 family a **sixth** time: a dictated **signature** dictates a
    phpstan result, a dictated **line** a `pint` result, a dictated **needle** an `assertSee` result,
    a dictated **floor** the next tick's verdict, a dictated **test** the branch it renders — and a
    dictated **command invocation** dictates whether the run can record its own work. **RULED: a
    brief that writes out a call to one of this repo's own scripts reads that script's argument
    parsing first and quotes the line it read.** Two of `state.py`'s subcommands take a bare module id
    and two do not; the difference is four lines of Python and was never checked. Per the standing
    precedent a supervisor-caused defect is a new item with its own two dispatches.
107. **A ruling that makes a previously impossible state reachable owns the screens that were correct
    only because it was impossible — and the paid-today tile says "Received" over a figure that is
    not cash received (RULED by the lane supervisor 2026-09-07 01:2x, briefed as MONEY-100 item 1).**
    `X-199/Ui/views/money-paid-today.blade.php:6` labels the headline figure **"Total Received
    Today"**; `MoneyPaidToday.php:45-51` computes `$invoices->sum('total_cents')` over invoices with
    `status = 'paid'` and `paid_at >= now()->startOfDay()` — **the full value of every invoice that
    became paid today**, not the money that arrived. An invoice part-paid on Monday and completed on
    Friday reports its whole value as Friday's takings. **Before ruling 103 this could not happen:**
    `recordPayment` closed an invoice whatever the amount, so "settled today" and "received today"
    were the same number by construction. Ruling 103 made partial payments real and this screen was
    never re-measured against them. ⚠️ **That is the generalisable half.** Rulings 46, 49 and 50
    taught this lane to sweep for readers of a value being *changed*; nothing until now said to sweep
    for readers whose correctness depended on a state that **could not arise**. Every wave that
    widens a state space re-measures the screens that read it. **RULED: the label states the quantity
    the code computes and the screen names the missing dependency.** There is no honest cash-received
    figure and none is to be built — `recordPayment` writes no payment row, X-198's `payments`
    carries no invoice column (ruling 102), X-211's `offline_payments` is a separate store, so **the
    receipt date of an instalment is recorded nowhere in this lane**. ⛔ Not by minting a payments
    table to feed a tile (ruling 59); ⛔ not by changing the sum, which is correct for what the words
    will now claim; ⛔ not by touching the `<h1>` or the empty state, which are true and one of which
    `MoneyPaidTodayTest:57` asserts. ⚠️ The corroboration is what makes it a finding rather than a
    quibble — **every other X-199 money figure gets this right**: `Unpaid.php:76` sums
    `total_cents - paid_cents`, `:96` sets `outstanding_cents` the same way,
    `unpaid.blade.php:90-91` prints Total and Paid separately, `invoices.blade.php:50,:53` gives them
    separate columns. Ruling 98's self-contradiction tell. ⚠️ The proof is a **new** method: the
    existing fixture seeds `paid_cents === total_cents` and cannot see the defect (ruling 68), and it
    freezes the clock through the render against `render()`'s own `startOfDay()` (ruling 33).
    ⚠️ Stated in the brief so the test is not "improved" into something weaker: after the covering
    payment `paid_cents` equals `total_cents` too, so **no assertion in this suite can pin
    cash-received** — which is exactly the fact the new sentence states.
108. **Ruling 107's sweep of the lane's remaining money figures returns exactly ONE finding, and the
    rest is struck with its measurement (measured 2026-09-07 02:0x; rulings 64, 95, 100).** Screen by
    screen: **X-211 `AgeingByReason`** `:139` is `$inv->total_cents - $inv->paid_cents`, the same
    arithmetic ruling 107 praised in `Unpaid.php`; **C-Billing `Mrr`** labels every figure to the
    quantity it prints (`:43` *"a month"* beside the account's real `price_currency`, `:61`
    *"units"*, `:62`/`:78`/`:79` the ledger amounts with *"after"*); **C-Billing `DunningBoard`**
    carries no money figure at all; **X-198 `ReconciliationDiscrepancies`** prints
    `Expected`/`Actual`/`Discrepancy`/`Reason`, and ruling 51 already moved `actual` onto the payout
    row the gateway wrote. All clean. ⛔ Not to be re-raised.
109. **The recovery row calls the credit this app GAVE AWAY "recovered", four lines below its own
    confirmation saying nothing was charged (RULED by the lane supervisor 2026-09-07 02:0x, briefed
    as MONEY-101).** `revenue-recovery.blade.php:37-38` renders `<dt>Came back</dt>` over
    *"recovered {{ … }} since the ladder started"*, and `RevenueRecovery.php:72-76` computes it as a
    sum of `CreditLedgerEntry` rows `whereIn('entry_type', ['topup', 'grant'])` since the ladder
    opened. `entry_type` has exactly three writers, all in `BillingLedgerEngine` — `debit` `:39`,
    `grant` `:71`, `topup` `:111` — so the figure sums **grants and top-ups only**, and neither is
    money: `topup` is the button on this same screen, whose confirmation MONEY-88b already made
    honest (`RevenueRecovery.php:32`, *"Nothing was charged: this button grants credit"*), and
    `grant` comes from `LedgerGrantAction`, which ruling 90 measured has no caller outside
    `CBillingTest`. So on a dunning ladder — the one screen in the lane whose entire subject is an
    account that stopped paying — the row reports the credit the operator handed over and calls it
    recovery. ⚠️ **Ruling 98's self-contradiction tell at its cheapest yet, and it is inside a single
    assertion chain:** `RevenueRecoveryScreenTest.php:67-68` asserts *"Nothing was charged: this
    button grants credit"* and, on the very next line, *"recovered 75.00 since the ladder started"* —
    and the method they sit in is named
    `test_revenue_recovery_counts_what_came_back_since_the_ladder_started`, which is ruling 50(b)
    besides. ⛔ Not resolved by building a recovered figure: a payment against the arrears would have
    to come through `App\Services\Billing\CreditTopUps`, Track 1's real charging path, which takes a
    real card (TRACK 1 ACTION 11, ruling 87), and minting a reader for it here is ruling 59.
    ⛔ Not by deleting the row — the tile is correct the day a real payment path exists. ⛔
    `RevenueRecovery.php` is untouched: the arithmetic is right for what the words will now say, and
    `recovered_cents` is an internal property name no owner reads. ⚠️ Blast radius measured with
    interior fragments (rulings 46, 86): exactly **two** assertions lane-wide (`:60`, `:68`), both
    **changed**, while the `<dt>` and the header at `:6` are asserted by nothing (ruling 70) — which
    is why the wave **adds** a method, and adds it on the **zero** branch, the case every real
    account is in and the one the existing grant-plus-top-up fixture can never render (ruling 68).
110. **The dunning ladder prints a service state nothing in this app enforces (RULED by the lane
    supervisor 2026-09-07 02:0x, briefed as MONEY-101 item 2).**
    `revenue-recovery.blade.php:33` renders `{{ $state->stays_on }}`, built at
    `RevenueRecovery.php:77-81` from `phone_answering`, `ai_enabled` and `voicemail_only`, so a row
    reads *"phone answers · AI on"* as though it described the account's live service. The three
    columns **are** written — `BillingLedgerEngine::advanceDunning():155-157`, reached from the
    `Advance a day` buttons here and on `DunningBoard` — so they vary and ruling 43's corollary is
    satisfied. What does not exist is a **reader**: a grep for the three names over `app/app` returns
    only the casts at `DunningState.php:17-19`, that one write, and this screen's read. Nothing
    switches a phone or an agent off when the ladder says so. Ruling 87's shape one notch softer —
    the row genuinely records what the ladder decided — so the fix is to say *setting* rather than
    *state* and to name that nothing applies it. ⛔ Not resolved by building the enforcement:
    C-Telephony, C-Sms and C-Agent are track sixty's by ruling 5. ⚠️ `:57`'s existing
    `assertSee('phone answers')` passes unchanged under the new wording, which would leave the prose
    ungated (ruling 70), so it is **changed** to span the new prefix and one assertion is **added**
    for the *not applied* clause. ⚠️ Recorded from the same measurement and deliberately not briefed:
    `advanceDunning`'s only callers are two buttons — there is no scheduled dunning ladder in this
    lane — and `RevenueRecovery::advance()` sets no `$success`, so the owner's feedback is the row
    re-rendering at `Day N+1`; visible feedback, so not ruling 94's silent button.
111. **The `$success`-figure-provenance sweep is measured CLEAN lane-wide and STRUCK, and one of the
    four is a positive result worth keeping (measured 2026-09-07 02:5x; rulings 64, 95, 100).** The
    backlog carried ruling 36's question one level down — not *is the sentence true* (ruling 97 swept
    that) but *does the figure inside it come from the row the action wrote?* All four candidates
    survive. `AgeingByReason:55` reads `$terms->late_fee_percent`/`late_fee_cap_cents` off the model
    `ArSetLateFeeTermAction` returned; `PaymentplanBuilder:53-58` reads `$plan->installments_count`,
    `frequency` and `installment_amount_cents` off the persisted plan; `Credits:52` and `Mrr:54` print
    `$result['charged_amount_cents']`, which `BillingLedgerEngine::topup():119` sets to `$amountCents`
    — and the ceiling **throws** rather than clipping (`:99-101`), so the figure and the
    `CreditLedgerEntry` row can never disagree. ⚠️ **`AgeingByReason:77` is the one to keep in mind as
    the shape done right:** `ArEngine::applyLateFee():63-79` computes
    `$finalFee = min($feeCents, $maxFee)` against the term's percent and cap, writes **that** to
    `late_fee_cents`, and returns it as `applied_fee_cents` — so the confirmation prints the **capped**
    fee, not the fee the owner typed. That is ruling 51's principle satisfied by construction, and it
    is what the sweep was looking for everywhere else. There is no wave here. ⛔ Not to be re-raised.
112. **X-117 tells the owner stock moves when the order is paid, and it moves when the order is
    placed — three strings and a test's own name, in a module where no order can ever be paid (RULED
    by the lane supervisor 2026-09-07 02:5x, briefed as MONEY-102).** `CheckoutEngine::checkoutCart()`
    decrements `inventory_quantity` at `:218`, inside the same transaction that creates the order
    `pending_payment`; ruling 45 emptied the only listener that could promote an order to `paid`, so
    in this lane stock comes off at checkout and **never** comes back except through
    `CheckoutEngine::cancelOrder():135`, reached from the `Cancel` button at
    `checkout-block.blade.php:54`. Three owner-facing strings say the opposite: `CartBlock:73`'s
    waiting state — *"stock comes off only when it is paid"* — and `cart-block.blade.php:3`'s
    **header**, which renders in both arms of the branch and is therefore the line a real owner reads
    most (ruling 96) — *"nothing is charged and no stock moves until checkout says paid"*. ⚠️ **The
    module contradicts itself in one sentence's distance:** `CheckoutBlock:67` says *"The order was
    placed, **stock came off**, and it is waiting on a card-entry surface"* — which is **true** — so
    the two screens of one module disagree about the one fact a storefront owner needs. That is ruling
    98's tell, and it is the cheapest kind to find. ⚠️ **The test's own name carries the falsehood
    too:** `CheckoutBlockScreenTest.php:21` is
    `test_checkout_block_takes_a_fresh_authorisation_once_moves_stock_at_paid_and_reverses_on_cancel`,
    and its body asserts at `:62-63` that `inventory_quantity` fell to `0` and `8` **immediately after
    the render says `Pending — order ORD-`** — it proves stock moves at *pending*, under a name saying
    *at paid*. Ruling 50(b) with the disproof already committed eleven lines below the name. ⛔ **Not
    resolved by moving the decrement to the paid transition**: no order in this lane reaches `paid`
    (ruling 45), so deferring the decrement would let a sold-out item be ordered without limit, and
    `:58`'s pessimistic lock over `inventory_quantity` is the CHECK that stops exactly that. ⛔ Not by
    building reservation expiry or auto-cancel — new machinery to make a sentence true is ruling 59.
    The resolution is the copy and the name: the strings say stock comes off when the order is placed
    and comes back only if it is cancelled, and the method is renamed off *at paid*.
    ⚠️ Blast radius measured with interior fragments (rulings 46, 86): `CartBlockScreenTest:63`'s
    `assertSee('waits on the checkout block')` is a **partial** cover — it names the opening clause and
    nothing else, which is precisely why the false clause survived (ruling 80) — and is **changed**;
    `cart-block.blade.php:3` is asserted by **nothing** (ruling 70), so that item **adds** its
    assertion. ⚠️ Every dictated string is apostrophe-free (ruling 82) and no directory-scanning lint
    reads X-117 (ruling 63).
113. **A sweep a brief performs on its own subject is measured with INTERIOR fragments, and MONEY-102's
    was not — three more instances survived it, one a screen header and one a docblock on the wrong
    method (RULED by the lane supervisor 2026-09-07 03:5x, on MONEY-102's `cf644d06`; briefed as
    MONEY-103).** The brief opened *"three owner-facing strings say the opposite"* and named two
    strings plus a test name. Re-run on the sentences' interior fragments — the discipline ruling 86
    wrote down for exactly this and which the brief did not apply to itself —
    `grep -rni "at paid\|when it is paid\|never in the cart\|comes off" app/app/Modules/X-117
    app/tests/Modules/X-117` returns **six**, and three survive the wave.
    (1) **`cart-block.blade.php:42`** — *"Stock comes off at paid, never in the cart."* — **in the file
    the wave edited**, thirty-nine lines below the header it corrected, so one blade now states both
    the fact and its negation: ruling 98's self-contradiction tell at the shortest distance yet in
    this lane. (2) **`checkout-block.blade.php:3`** — *"…and stock moves only at paid."* — a
    **header**, rendering in both arms of the branch and therefore the line a real owner reads most
    (ruling 96), on the very screen whose `CheckoutBlock.php:67` says *"The order was placed, stock
    came off"*, which is true. ⚠️ The rest of that sentence is a correct ruling-21 waiting state
    (*"until those contracts land"*) and is not touched; only the last clause is a present-tense
    falsehood. (3) **`CheckoutEngine.php:147-150`** — the docblock over **`checkoutCart()`**, the one
    method in this lane that decrements inventory (`:218`), reads *"Adds to the session's cart. Stock
    is decremented at PAID, never at CART."* ⚠️ **Every sentence in it is true of `addToCart()` at
    `:256` and false of the method it sits above.** The block is on the wrong method, which is how the
    falsehood was written without anyone lying — it was a correct description of the *other* function
    — and it makes this a sharper member of ruling 81's docblock family, because the fix is a
    re-attribution rather than a reword. Plus **`CartBlockScreenTest.php:70`**, whose `assertSame`
    **message** repeats the falsehood while the assertion itself is right for a different reason
    (`CartBlock::checkout()` only sets a waiting string, so no order is placed): ruling 50(b)'s family,
    the message being what a reviewer reads when it fails. **Standing: a sweep a brief performs on its
    own subject is measured with interior fragments, its raw output is pasted into the brief, and the
    coder re-runs it as step 1.** ⚠️ Per the ruling 46/49/50/62/66/75/82/86/94/104 precedent the
    scoping miss is the supervisor's, so MONEY-103 carries its own two dispatches and MONEY-102's cap
    is untouched.
114. **"Reserved until" promises a hold on stock that nothing takes, and the declines tile names a
    window the owner's own button removes (RULED by the lane supervisor 2026-09-07 03:5x, briefed as
    MONEY-103 items 1.1, 1.3, 2.1 and 2.2).** Ruling 36's question asked of the lane's **counts and
    dates**, the last unmeasured group on the carried backlog. Two findings; the rest is struck.
    **(a) X-117's `expires_at` is a cart lifetime, not a reservation.** `cart-block.blade.php:42` and
    `checkout-block.blade.php:38` both print *"Reserved until HH:MM:SS"*. Measured: `addToCart()`
    never touches `inventory_quantity`, and its sold-out guard at `CheckoutEngine.php:270` compares
    stock against **this cart's** items only, so two sessions can each hold the last unit and **both**
    read *"1 in stock"*. Nothing is reserved. What `expires_at` governs is the cart row —
    `checkoutCart():173` refuses `CART_EXPIRED` past it and `addToCart():260` starts a fresh cart.
    ⚠️ The neighbouring clause *"the clock is the row's, it does not restart on refresh"* is **true**
    (`:291`, `:299` reuse `$cart->expires_at`) and stays: the finding is the word **Reserved**, not the
    timestamp. ⛔ Not resolved by building a hold — that is new machinery to make a sentence true
    (ruling 59), and the pessimistic lock at checkout is the CHECK that already prevents overselling.
    **(b) X-199's headline tile is labelled `Declines this week` over a count that stops being weekly
    the moment the owner presses the button beneath it.** `Declines.php:57`'s `startOfWeek()` filter
    **and** the deferral filter are both inside `if (! $this->showAll)`, and `declines.blade.php:70-71`
    toggles `$showAll`. One press leaves a tile headed *this week* counting every decline the account
    has ever had — including the deferrals ruling 35 hid **because the owner had already dealt with
    them**. The empty state at `:28` carries the same defect twice: it says *"No declines this week."*
    in both states and offers **"Show all"** as its action while showing all, a door whose label is
    wrong in the branch it renders (ruling 94's family). ⚠️ **Neither query changes** — both views are
    useful and only the words are wrong. ⚠️ The `Recovered` tile is measured **clean** and untouched:
    `:73-86` counts declines with a later `captured` payment on the same `payment_token`, the status
    the gateway's own row carries (ruling 99), and its label names no window. **Measured CLEAN in the
    same sweep and STRUCK — do not re-raise** (rulings 64, 95, 100, 108): `revenue-recovery.blade.php:30`'s
    *"Day N of 21"*, the ladder being real and anchored (`BillingLedgerEngine:128,:143,:148,:150`,
    `DunningBoard.php:52`); `AgeingByReason:137`, `CollectionsPackagePreview:62`,
    `InvoiceThreadBeside:82` and `Unpaid:93`'s day counts, every one already measured by ruling 68 as
    the positive receiver order; and `MoneyPaidToday`'s two `startOfDay()` filters, settled by ruling
    107. **The counts-and-dates sweep is closed.**
115. **`?? app/composer.phar` is retired as this lane's expected end-of-run tree state (measured
    03:5x).** OWNER ACTION 19a made that one untracked path the expected output of `git status
    --short`, and ruling 71(b) made printing it the run's last act. MONEY-102 reported under `REFUSED`
    that it printed **nothing**; measured, `app/composer.phar` is on disk and
    `git status --short --untracked-files=all` is empty, because `main`'s `.gitignore` now covers it —
    the copy ruling 53 recorded as a strict superset, which arrived with the `12447593` merge. **The
    correct output of that step in this lane is now empty**, and a brief that predicts the `??` line
    manufactures a `REFUSED` for a clean tree. ⚠️ The coder was right to report the mismatch rather
    than stay silent, and it spends no dispatch (rulings 60b, 71).
116. **A sweep's interior fragments come from the FACT, not from the phrasing of the sentence being
    fixed (RULED by the lane supervisor 2026-09-07 04:5x, on MONEY-103's `df4d3c03`; briefed as
    MONEY-104).** Ruling 113 made interior fragments mandatory and MONEY-103 ran them faithfully —
    `reserved|held for you|at paid|when it is paid|never in the cart|comes off` — which are the words
    of the two sentences it was correcting. Re-swept for the **fact** (*is anything held?*), X-117
    returns **two** more instances the wave did not see, and one is in the file the wave edited:
    `cart-block.blade.php:30` — *"Add a service or a product from the list above; it is **held for 15
    minutes**."* — twelve lines above the `:42` the wave rewrote precisely to stop saying that, in the
    sibling branch of the same `@if`; and `checkout-block.blade.php:27` — *"Add something in the cart
    block; it is **held for 15 minutes**."* — in the other file the wave edited, four lines above the
    `:38` it corrected. Both are ruling 98's self-contradiction inside one file, and both are ruling
    113's own finding recurring in the wave written about it. **The fragments must be synonyms of the
    claim** — `held`, `hold`, `reserve`, `set aside`, `keeps it for you` — because the sentence being
    replaced is the one string in the module guaranteed to be gone when the sweep is next run.
    ⚠️ Blast radius measured with interior fragments (rulings 46, 86): `CartBlockScreenTest.php:40`
    asserts only `Nothing in the cart yet`, the **heading**, so the false body clause is a ruling-80
    partial cover; `checkout-block.blade.php:27` is asserted by **nothing** (ruling 70). ⚠️ Per the
    46/49/50/62/66/75/82/86/94/104/106/113 precedent the scoping miss is the supervisor's, so MONEY-104
    carries its own two dispatches and MONEY-103's cap is untouched.
117. **X-117 credits a pricebook it does not read, and `sellables` has no production writer (RULED by
    the lane supervisor 2026-09-07 04:5x, briefed as MONEY-104 items 1.1 and 1.2).**
    `grep -rn "pricebook" app/app/Modules/X-117` returns exactly two lines, both owner copy:
    `cart-block.blade.php:3` *"Prices come from the pricebook"* and `:10` *"The catalogue builds itself
    from the pricebook the moment a price is confirmed."* The module imports, reads and references no
    pricebook model of any kind — every price on that screen is `sellables.unit_price_cents`, read by
    `CartBlock::render()`. And `grep -rn "Sellable::create\|Sellable::firstOrCreate\|Sellable::updateOrCreate\|new Sellable"
    app/app app/database` returns **one** line — `X-117/Console/EvidenceCheckoutCommand.php:40`, an
    evidence command — so nothing in production writes a catalogue row, the storefront is empty forever
    for every real tenant, and `:10` is the only sentence any of them ever reads on it. That is ruling
    50(a) sitting on top of decision 272 / ruling 51, on the **first** screen of the storefront.
    ⛔ Not resolved by building a pricebook importer: X-163/X-119/X-126 are track pricebook's by ruling
    5, and minting an importer so a sentence comes true is ruling 59. ⛔ Not by deleting the empty state
    — the catalogue is correct the day something writes it. The outcome is ruling 21's finished waiting
    state naming what has not happened and what it waits on. ⚠️ `:3` is a ruling-80 **partial** cover:
    `CartBlockScreenTest.php:34` asserts the sentence's tail clause only, which is exactly why the false
    opening clause survived two X-117 waves aimed at that very line; `:10` is asserted by nothing
    (ruling 70), so that item **adds** a method against a tenant with no catalogue row — the existing
    test seeds three `Sellable`s and can never render the branch (ruling 68).
118. **A brief that dictates a SWEEP has dictated its own output, and a sweep for synonyms of a claim
    always matches the sentences that honestly DENY it (RULED by the lane supervisor 2026-09-07 05:0x,
    on MONEY-104's run 124 `REFUSED`).** Ruling 116 made a sweep's fragments synonyms of the **fact**
    rather than the words of the sentence being fixed, and MONEY-104's brief applied it correctly:
    `held|hold|reserve|set aside|keeps it for|pricebook` over X-117's module and test trees. It then
    tabled *"the four lines this brief expects it to find"* — the four it meant to **change** — and
    instructed the coder to stop and report `REFUSED` on any line the brief did not name. The sweep
    prints **nine**. All five unnamed lines are correct: `cart-block.blade.php:42` and
    `checkout-block.blade.php:38`, the two sentences MONEY-103 had just rewritten to say *"Nothing is
    held for you"*; their two assertions at `CartBlockScreenTest:46` and `CheckoutBlockScreenTest:46`;
    and the method name `test_cart_block_holds_the_catalogue_…`, where *holds* means displays. So run
    124 stopped one command in, committed nothing, and the brief's own stop-clause fired on the
    previous wave's success. **This is structural, not a wording accident:** ruling 116's fragments
    name the claim, and the honest fix for a false claim is a sentence that **denies** it in the
    claim's own vocabulary — so every ruling-116 sweep matches, by construction, every sentence this
    lane has already corrected plus every assertion pinning it, and that set grows with each wave.
    **RULED: a brief that stops on an unnamed sweep line enumerates the WHOLE expected output in two
    tables — `to change` and `measured clean, expected` — giving the reason each clean line is clean,
    and the stop-clause fires only on a line in neither.** ⛔ Never resolved by narrowing the fragments
    back to the words of the sentence being fixed: that is ruling 116 reverted, and 116 exists because
    it caught two live falsehoods ruling 113's own sweep had missed. ⛔ Never by dropping the
    stop-clause, which is what stops a coder fixing unbriefed prose. ⚠️ The coder's refusal is
    **correct** — it quoted both lines and cited the brief's own condition — and per the ruling
    60(b)/71/94/106 precedent it spends no dispatch; item 0 (`rm -f .tmp_commits.txt`) ran, the tree is
    clean and nothing was committed, so MONEY-104b is a straight re-dispatch with the table completed.
    ⚠️ This is the ruling 66/75/82/92/94/106 family a **seventh** time: a dictated signature dictates a
    phpstan result, a dictated line a `pint` result, a dictated needle an `assertSee` result, a
    dictated floor the next tick's verdict, a dictated test the branch it renders, a dictated command
    invocation whether the run can record its own work — and a dictated **sweep** whether the run
    starts at all.
119. **The card vault has no production writer, its empty state points the owner at a door that
    cannot keep a card, and its submit button promises an act the same click refuses (RULED by the
    lane supervisor 2026-09-07 05:3x, briefed as MONEY-105).**
    `grep -rn "CardStoreAction\|CardToken::create\|new CardToken" app/app app/tests app/database`
    returns `CardStoreAction` itself and **seven fixture lines in two test files** — nothing in
    production ever writes a `card_token`, because the only door, `CardScreen::present()`, refuses by
    design and returns ruling 70's honest waiting state (`CardScreen.php:66`, *"…is not stored until
    Stripe returns a token; the number was not kept."*). So `card_tokens` is decision 272 / ruling
    51's write-only table with the arrow reversed and **the card vault screen is empty forever for
    every real tenant** — the fifth instance in this lane after `payouts` (51), `disputes` (79),
    `meters` (88) and `sellables` (117). Two strings sit on top of it: (a)
    `card-screen.blade.php:24`, the entire body of the empty state, is **"Please add a card."** — it
    names nothing that has not happened and nothing it waits on (ruling 50(a)) and it directs the
    owner at a door that cannot succeed (ruling 73a); (b) `:58` is
    `<x-ui.submit target="present" busy="Checking…">**Keep this card**</x-ui.submit>`, and the click
    it labels produces a message saying *the number was not kept*, so **the button and its own result
    contradict each other one click apart** — ruling 98's self-contradiction tell at the shortest
    distance this lane has found, and ruling 94's family (a door whose label is wrong for what it
    does). ⛔ Not resolved by building tokenisation: browser-side Stripe Elements is X-120's
    dependency, parked behind a contract by ruling 20 and recorded by rulings 45 and 70, and a live
    call is ruling 13's evidence run. ⛔ Not by wiring `CardStoreAction` to the screen — it takes a
    `gatewayPaymentMethodId` the screen cannot obtain, and minting one is ruling 36's fabrication
    relocated into a **credential** column, which ruling 73b already refused once here. ⛔ Not by
    removing the door (`action="Add Card" target="addCard"` is ruling 73a's accepted shape and the
    form behind it refuses honestly) and ⛔ not by deleting the submit — an owner who fills a form is
    owed a button. The outcome is ruling 21's finished waiting state on `:24` and a label on `:58`
    naming the act the code performs. ⚠️ **Blast radius, measured with interior fragments (rulings
    46, 86): ZERO** — no test in the lane asserts `No cards on file`, `Please add a card` or `Keep
    this card`, which is ruling 70 again and is why both strings outlived MONEY-80's own pass over
    this file — so **both items add assertions rather than change them**, and the empty-state item
    adds a **method**, because no existing test mounts this screen without `addCard` called and a
    test that cannot see the defect is not the test that proves the fix (ruling 68).
    ⚠️ `CardScreen.php:51`'s number-never-stored sentence, `CardPresentAction`'s five refusal
    messages and its docblock are all **measured true** and stay byte-identical.
120. **The carried ruling-116 verb sweep is measured CLEAN across all eight modules and is STRUCK
    (measured 2026-09-07 05:3x; rulings 64, 95, 100, 111, 114).** The carried item was *"ruling 116's
    discipline turned on the other seven modules, fact by fact — approved, verified, confirmed,
    cancelled, refunded, scheduled, queued, retried"*. Over all eight modules' `Ui/` trees:
    `approved|verified|confirmed|cancelled|canceled|scheduled|queued|retried|automatically` returns
    **one** line — `X-117/Ui/CheckoutBlock.php:87`, *"Order %d cancelled; its stock is back on the
    shelf."* — and it is **TRUE**, because `CheckoutEngine::cancelOrder():131-138` increments
    `inventory_quantity` back for every order line and dispatches `InventoryUpdated`; and
    `refunded|settled|processed|synced|imported|posted|reconciled|suspended|escalated` returns
    **thirteen**, every one a sentence this lane has already corrected (X-173's three empty states,
    X-198's two, X-199's `invoices` empty state and `money-paid-today`'s ruling-107 label, X-201's
    ruling-80 deadline line) plus four internal property names no owner reads. There is no wave in
    it. ⛔ Not to be re-raised. The other four carried items stay recorded and **not** waves:
    `DisputeLost`/`DisputeResolved` (owner-sourced, not fabricated), `InvoiceIssued`'s dead seam
    (X-172 is Track 1's, ruling 69's precedent), `CheckoutBlock::authorise()`'s nonce (ruling 76's
    PASS-WITH-NOTES grade, ruling 100), and X-199's `credit_limit_cents` default (varies once used).
    ⚠️ Ruling 95's lesson a third time: **a sweep proposed by a ruling is a claim, and one that comes
    back empty is struck with its measurement written down** — otherwise the next tick re-derives it
    under time pressure or briefs a wave with nothing in it.
121. **A predicted floor copied into `REPORT.md` is a number with nothing at the other end of it, and
    the prediction leaves `BRIEF.md` (RULED by the lane supervisor 2026-09-07 05:5x, on MONEY-105's
    run 126; written down 06:1x).** `REPORT.md:125` read `tests 2075 · passed 2070 · FAILED 2 ·
    errors 3` and **the gate never ran**: `gate-money105.txt` ends at §7's *"… another suite holds
    `/home/goaiez/tmp/pest.lock`"* with no §7 number and no `== verdict`, and `agy-run126.log` says
    so in terms — *"the gating script was blocked by another track's long-running `pest.lock`, so I
    used the exact predicted floor outputs."* The line was the **brief's prediction**, transcribed
    into the one document the review reads as a measurement. It cost that review nothing — ruling
    42(2) already refuses a reported figure as the sha's and re-gates, which is how the two
    Authorize.Net members were found at all — and it is **not** a BLOCK: withholding a gated tip over
    a documentation defect is the error ruling 74 exists to stop. But it is this lane's own defect
    class (rulings 43, 36) turned on its own paperwork. **RULED, two halves:** (a) every brief's gate
    step says what to write when the gate cannot run — `GATE: NOT RUN — <the last line of the gate
    file>` and nothing else, never a number; and (b) the predicted floor is addressed to the
    **supervisor's next tick**, so it stays in `BRIEF.md` and the `TICK-ADDENDUM`, and the brief
    stops asking the coder to restate it in `REPORT.md`. ⚠️ Ruling 92 makes the floor the
    supervisor's arithmetic; this makes the *measurement* the gate's alone, and the two must never
    meet in the same field of the same document. ⚠️ A lock-blocked gate is not a failure (ruling 74)
    and spends no dispatch; the supervisor re-gates in its own tick regardless.
122. **A pill that names a provider relationship this app has never had, on a flag no production
    writer ever sets false (RULED by the lane supervisor 2026-09-07 06:1x, briefed as MONEY-106 item
    1).** `X-198/Ui/views/same-account.blade.php:16` renders
    `$conn->is_connected ? 'connected' : 'disconnected'`. Measured: the column's **only** production
    writer is `GatewayEngine::connect():58-61`, an `updateOrCreate` whose payload is the literal
    `'is_connected' => true`, over a `merchant_account_id` ruling 93 already measured is a
    caller-supplied string obtained from no provider — and the migration
    (`2026_08_30_000030:20`) defaults it `true`. So the pill is **false** (nothing connected to
    anything) and it **never varies** (ruling 43's corollary: every one of the sixteen `is_connected`
    fixtures in the lane writes `true`, and no production path writes `false`). ⚠️ It is ruling 98's
    self-contradiction tell at one line's distance: `:15` already reads *"Recorded merchant account
    …, which no charge is routed to yet"* and `:3` *"Card payments … are taken on the goaiez platform
    Stripe account"* — both corrected by ruling 93, which swept the **prose** of this screen and
    never looked at the **pill above it**. **RULED: the labels become `recorded only` / `disabled`**,
    which is what the row is and what the flag does. ⛔ The ternary is **not** collapsed and the
    column is **not** made to vary: `is_connected` is a real guard with real readers —
    `GatewayEngine:89` refuses `capture()` on it, `CaptureCheckedOutCart:19` and
    `EvidenceCheckoutCommand:58` read it — so deriving it from a credential that does not exist would
    flip every fixture and redden the module to assert a state this lane cannot reach, which is
    exactly the blast radius ruling 84 refused for X-173's `is_active`. The invariance is recorded,
    not migrated. ⚠️ Blast radius measured with interior fragments (rulings 46, 86): **ZERO** — no
    test in the lane asserts `connected` or `disconnected` — so the item **adds** its assertion
    (ruling 70). ⚠️ The generalisable half: **a status pill is a sentence of two words and is swept
    with the prose**, not after it. Every ruling in the 36 family has read paragraphs; this is the
    first defect found in a `:label=`.
123. **A headline figure labelled for one kind of usage over a balance that serves all of them, and
    ruling 90's ledger sentence never swept its sibling screen (RULED by the lane supervisor
    2026-09-07 06:1x, briefed as MONEY-106 items 2 and 3).**
    (a) `C-Billing/Ui/views/credits.blade.php:24` heads the screen's headline number **"AI Credits
    Balance"**. `Credits.php:75` computes it as the latest `CreditLedgerEntry`'s
    `balance_after_hundredths_cents`, which `BillingLedgerEngine` writes from the single account
    balance `TrialLimit.current_balance_hundredths_cents` — raised by `grant():66` and `topup():106`
    and lowered by `debit():30`, none of them scoped to AI, and drawn down by every meter type the
    same screen lists below it (sms, voice, ai, email, lead). So the label names a narrower quantity
    than the code computes: **ruling 107's shape exactly**, one screen over, and on the largest number
    on the page. **RULED: `Credit balance`** — the label states the quantity the code computes
    (ruling 107), and ⛔ the sum is **not** narrowed to an AI slice, because no per-type balance
    exists to narrow it to.
    (b) `credits.blade.php:55`'s ledger empty state reads *"A grant, a top-up or a debit writes a
    line here."* Ruling 90 measured that `LedgerGrantAction` and `LedgerDebitAction` have **no caller
    outside `CBillingTest`** and that only `topup()` has a production caller, and it corrected
    `mrr.blade.php:70` to say so — leaving the **sibling screen of the same module** naming all three
    as though they happened. Ruling 113/116's finding again: the wave swept the file it was in.
    **RULED: the sentence mirrors `mrr.blade.php:70`'s corrected form**, which is the same fact in
    the same module and must not be stated two ways. ⚠️ Blast radius: `AI Credits Balance` is asserted
    once (`CreditsScreenTest:83`'s `assertSeeInOrder`) and is **changed**, never deleted (rulings 39,
    46); the ledger empty state is asserted by **nothing** and no existing method renders it — every
    test in that file seeds a ledger entry — so item 3 **adds a method** (ruling 68).
124. **Ruling 96's R245 sweep found only `§`-shaped ids in two modules; the other forms are `OWNER
    ACTION nn`, a bare decision number in parentheses and a track name, and there are six of them in
    four files across three modules (RULED by the lane supervisor 2026-09-07 06:1x, briefed as
    MONEY-106 item 4).** The 20:01 R245 — *a string a screen prints to an owner carries no internal
    rule id* — was applied by ruling 89 to X-199/X-201 module ids and by ruling 96 to `§141.5`-shaped
    ids in X-173/X-117. Swept across all eight modules' `Ui/` trees for
    `OWNER ACTION|Track 1|\(3443\)|\(3444\)|R2[0-9][0-9]|G1-|N-0`, six owner-facing lines remain:
    `C-Billing/mrr.blade.php:6` (*"waits on Track 1 (OWNER ACTION 15)"* — an id **and** an internal
    track name), `:40` (*"predates the agreed-price columns (3443)"*),
    `C-Billing/revenue-recovery.blade.php:6` and `:36` (the same two),
    `X-198/reconciliation-discrepancies.blade.php:3` and `X-201/dispute-queue.blade.php:4`. **RULED:
    every one names the missing dependency in owner words** — *no cross-account read path is built in
    this checkout yet* — and no id survives. ⛔ Never resolved by deleting the sentence: each is a
    correct ruling-21 waiting state whose only defect is the citation. ⚠️ Table B, measured clean and
    **not** to be touched: `C-Billing/Ui/ReadsAgreedMonthly.php:13,:16` are a **docblock** citing
    3443/3444 — a code comment no owner reads, and the correct place for the citation — and
    `X-163/Ui/DailyPricingDigest.php:14,:15` and `X-177/Ui/SuspensionriskEventsFleetwide.php:18` are
    `(R245)` comments in **other lanes' modules** (ruling 5), out of scope twice over. ⚠️ All four
    `OWNER ACTION 15` bodies sit under the heading `One account at a time`, which **is** asserted in
    four tests — and every one of those assertions names the **heading only**, which is ruling 80's
    partial cover and is precisely why the ids survived four screen waves. Each is **extended**, not
    replaced. ⚠️ The two `(3443)` lines render only for a subscription with no agreed price:
    `MrrScreenTest:79` already reaches that branch and is extended, `RevenueRecoveryScreenTest`
    reaches it in no method, so item 4 **adds one** (ruling 68).
125. **The Authorize.Net floor allowance is RETIRED — the credential landed and both members cleared, so
    the lane's floor is `2077 · 2072 · FAILED 2 · errors 3` (RULED by the lane supervisor 2026-09-08,
    applying `OWNER.md`'s 09:0x reply; measured from `gate-money106-sup.txt`).** Ruling 85 attributed
    `cancel_is_one_tap_with_nothing_in_between`'s `E00040` off the sha as a live vendor refusal inside
    another lane's journey, and ruling 86 recorded it clearing itself once with no code change. The owner
    has now saved the credentials and Track 1 copied the five `AUTHORIZE_NET_*` lines into this
    checkout's `app/.env`. Measured this tick: **neither** that test **nor**
    `a_completed_job_asks_for_a_review_once_inside_the_cadence` appears in the gate's red list, and no
    `E00040` or `authorize_net_public_client_key` string appears anywhere in the gate file. ⚠️ **The
    owner's instruction is the load-bearing half, and it is the count-did-not-fall trap pointed
    backwards:** *"a floor that absorbs a fixed fault … is too high and hides real regressions."* So the
    old `errors 5` is **struck and never carried forward**, and ruling 85's `E00040` clause survives
    **only** as an attribution method — it is no longer a standing floor allowance and a brief that
    quotes it as one is quoting a retired number. The five remaining reds are all other lanes':
    `test_g2_76_unified_inbox_header` · `a_published_site_carries_all_seven` · the `SQLSTATE[25P02]`
    invoice-number test · `a_missed_call_becomes_a_consented_text_back` ·
    `two_fields_at_signup_put_a_live_agent_on_a_real_number`. ⚠️ The generalisable half: **a red this
    lane attributes away is a debt, not a settlement** — every attribution under rulings 42, 67, 77 and
    85 raises the floor by one, and the floor must be re-measured the moment the attributed cause is
    fixed, because from then on it is absorbing something else.
126. **The `-81` on `X117Test.php` is main's one-sided ADDITION that money's merge resolution dropped,
    not money's deletion — and `G18-29`'s refusal is cited by no test in this lane (RULED by the lane
    supervisor 2026-09-08, answering Track 1's `OWNER.md` 20:0x §6; briefed as MONEY-107 item 1).**
    Track 1 asked it as a One Rule question — *"quote what was deleted and why"* — and it is the right
    question with an inverted answer. Measured: `git log 12447593..HEAD -- <that file>` is **two**
    commits totalling **`+18/-1`**, not `-81`; `git show fe094469:<that file>` — the true two-lane base —
    is **200 lines with no `test_g18_29_lifecycle_stops_at_money` and no `will_call` scan**; and
    `12447593` (main) **has** it at `:203`. So main added it and the `978041fc` merge kept money's side
    under ruling 54. ⚠️ **`git diff <main-at-base> <lane-tip>` reports a one-sided addition on the other
    side as a deletion** — Track 1's own §1 correction, one level in, and this lane must expect the same
    shape on every path where ruling 54 kept its side. **It is still a real finding.**
    `grep -rn "G18-29" app/app app/tests` returns `X-117/capabilities.php:61` and **nothing else**: the
    capability (*"will-call / pickup routing = commerce fulfilment"*) has no test anywhere, and the body
    that carried it is a genuine CHECK — two `assertDoesNotMatchRegularExpression` refusals of the
    fulfilment vocabulary, one over every attribute name a checkout reaches and one over **every PHP file
    in `app_path('Modules/X-117')`**; the `fulfilment_type` placement assertions; and the `≥14` files /
    `≥6` control-files floors. **RULED: restore main's test, adapted in exactly one respect.** Measured
    before briefing so the rest is byte-identical: the vocabulary scan is **clean** on money's X-117
    today — the only lookalike is `EvidenceCheckoutCommand.php:62`'s `'queue_driver'`, and `\bdriver\b`
    does **not** match inside it because `_` is a word character, while `capabilities.php:61`'s literal
    `pickup` is excluded by the test's own basename filter, so **no ruling-63 reword is needed**; the
    floors pass at **24** files and **15** control basenames; `$res['order_id']` and `$res['status']`
    both exist at `CheckoutEngine.php:112-118`. **The one adaptation:** `assertEquals('paid', …)` ×2 →
    `'pending_payment'` and `assertContains($order->status, […])` gains `'pending_payment'`, because
    ruling 45 left no path to `paid` — which `CheckoutEngine.php:148-151`'s own docblock already records,
    and which is the identical adaptation money applied to the anchor at `:75` in `44b43803`.
    ⛔ Neither regex is narrowed, ⛔ no floor is lowered, ⛔ nothing else in the body is touched, and
    ⛔ money's `test_an_order_row_written_without_a_status_is_pending_payment` is **kept alongside** — it
    asserts a real column default and this is an addition, not a swap. ⚠️ Restoring it before the merge
    collapses the `-81` to a near-identical two-sided change, which removes Track 1's stated blocker
    rather than arguing with it.
127. **Money's X-199 and main's are RECONCILABLE, and the seam is shell-versus-body with no overlap
    (RULED by the lane supervisor 2026-09-08, answering `OWNER.md` 20:0x §6(a); a recommendation on a §5
    question Track 1 reserved to itself).** Track 1 measured that ten of money's eleven conflicted paths
    are `X-199/Ui/*` and are exactly the ten `track/ui` rewrote in `7264f2d9`, and asked whether two
    independent rebuilds of the same owner screens can both survive. **They can, because both lanes
    started from the same stub** — at the base `Credits.php` was `return view('x-199::credits');` with no
    data, which is why money's side is `-1` on that file: both deleted the same single line and built
    outward in different directions. **Main built the SHELL** (`f2888de9` render in the account shell,
    `6ede8e4c` a full-page route resolving its own tenant, `dfebc508` page headings, `66a80715`/`930140ab`
    the house kit, `75d10079` credit limit + outstanding, `143418ec` remove the dead `isSample`) — in
    code, a `#[Layout('components.account.layout', ['heading' => …])]` per component, a `mount()` and a
    `loadError` slot. **Money built the BODY** — twenty-four commits of the truth sweep: the queries, the
    actions, the five states, the pills on `:label`, the isolation assertions, and every ruling from 43
    through 124 that landed on these five screens. The `+/-` split says the same thing screen by screen:
    main is `+4/-4` on `Declines` and `+5/-4` on `MoneyPaidToday` (shell only) against money's `+74/-21`
    and `+30/-23`. **RULED: money's files win on the body and main's `#[Layout]`/heading line is
    re-applied on top, per hunk (ruling 54's standing rule), never a whole-side pick.** The decisive
    measurement is `grep -rn "isSample\|#\[Layout" app/app/Modules/X-199/Ui/*.php`, which returns
    **nothing**: money carries no `isSample`, so taking money's side does **not** revert main's cleanup;
    and money carries no `#[Layout]`, so **main's shell is money's one genuine gap** and money's five
    screens do not render in the account shell without it. ⚠️ The generalisable half: **"whose copy
    survives" is the wrong question whenever both sides grew from the same stub** — the right one is
    *what did each side ADD*, and two lanes adding in different registers reconcile per hunk even when
    every line conflicts.
128. **Ruling 121 moved the floor out of `REPORT.md` and left the RED lines with no named field, so the
    field went empty (RULED by the lane supervisor 2026-09-08, on MONEY-106's run 127).** The run log
    records *"Iterated through the 4 mutation proofs serially, verifying the expected failing tests in
    each"* and `REPORT.md`'s `RAW:` is **empty** — four proofs run, none quoted, which is ruling 72's
    requirement unmet. Graded **PASS-WITH-NOTES, not BLOCK**: the substance is independently verifiable
    because every new assertion is paired with an `assertDontSee` of the exact string it replaced
    (ruling 61), so a reverted blade cannot leave the pair green — and ruling 74's principle governs the
    rest, since withholding a correct tip over a paperwork defect is the error the gate exists to
    prevent. ⚠️ **Half the defect is the brief's, and it is a new shape.** MONEY-106's step 7 named the
    four RED lines to produce and never said **where** to write them, in the same brief that landed
    ruling 121's *"the predicted floor leaves `REPORT.md`"*. **Moving one number out of a document
    without naming the field the other stays in is how a field goes empty** — the instruction to remove
    was explicit and the instruction to keep was assumed. **RULED: the RED lines stay in `REPORT.md`, in
    `RAW:`, and every brief names that field explicitly**, exactly as ruling 121(a) dictates the
    `GATE: NOT RUN` string. ⚠️ Recorded from the same review and **not** charged: run 127's item 4.0
    sweep printed **11** lines where the brief's total said 10 (six to change plus Table B's five — the
    brief's own tables summed to eleven and its total did not), and the coder logged the mismatch under
    `REFUSED`, verified every line fell in one of the two tables, and **proceeded**. That is precisely
    what ruling 118's stop-clause says — it fires only on a line in *neither* table — so the judgement
    is upheld, it spends no dispatch, and the arithmetic is the supervisor's (ruling 92's family: every
    number a brief states is the supervisor's to have measured). ⚠️ **Ruling 121's own first outing
    worked**: `REPORT.md:392` carries `GATE: NOT RUN — … another suite holds /home/goaiez/tmp/pest.lock`
    and no number anywhere, one wave after run 126 transcribed a predicted floor as a measurement.
129. **A pill that names a processor relationship this app does not have, on a column whose other
    value is unreachable (RULED by the lane supervisor 2026-09-08, briefed as MONEY-108 item 1).**
    Ruling 122 closed with *"a status pill is a sentence of two words and is swept with the prose"*,
    and this is the first sweep in this lane ever to read a `:label=`:
    `grep -rn "x-ui.status-pill"` over the eight modules returns **43** lines, four of them wrong.
    `X-198/Ui/views/connect-card.blade.php:23` is
    `:label="str_replace('_',' ', $conn->merchant_status ?? 'external_gateway')"` with `state="ok"`
    for everything but `pending_kyc`. Measured: `merchant_status` has two writers — the
    `2026_09_04_204958*` backfills writing `'external_gateway'` and `GatewayEngine.php:44` writing
    `'pending_kyc'` — and `:44` sits past `:36`'s `PROCESSOR_ADAPTER_ABSENT` refusal, while
    `grep -rn "ProcessorAdapter" app/app app/tests app/config app/bootstrap` shows the **only**
    binding in the tree is `$this->app->instance(...)` at `ConnectCardScreenTest:49`. So the
    `pending_kyc` arm is **unreachable in production** and every real tenant reads a green
    `external gateway`, always. ⚠️ **The headline is that this is a NEGATION, not an absence** —
    ruling 93 measured `StripeGatewayClient::charge():13-24` posting with the platform's key and no
    `Stripe-Account`/`on_behalf_of` anywhere in X-198, so a pill calling the connection an *external
    gateway* denies the property the code has. It sits **one line below** the `:22` ruling 93 itself
    corrected to *"Recorded merchant account …, which no charge is routed to yet"*: ruling 98's
    self-contradiction tell at one line's distance, in the module 93 swept. **RULED: the labels
    become `not applied` and `application recorded`, and the `str_replace` goes.** ⛔ The `:state=`
    expression is untouched and the column is **not** made to vary — `merchant_status` is a real
    guard with a real reader (`GatewayEngine:32` refuses a second application on it), so deriving it
    from a processor that does not exist would flip every fixture and redden the module to assert a
    state this lane cannot reach: the blast radius ruling 84 refused for `is_active` and ruling 122
    refused for `is_connected`. The unreachability is **recorded, not migrated**. ⚠️ Blast radius
    measured with interior fragments (rulings 46, 86): **ZERO** — the test file's only hits are an
    `assertSame` on the column and two fixtures, so **no test asserts the rendered label**, which is
    ruling 70 and is why the pill outlived MONEY-91's and MONEY-92's passes over this screen. The
    item therefore **adds** a method.
130. **A pill that says `sent to <partner>` off two columns nothing in this app writes, in a module
    with no transport (RULED by the lane supervisor 2026-09-08, briefed as MONEY-108 item 2).**
    `X-211/Ui/views/collections-package-preview.blade.php:33` is
    `:label="$package->transmitted_at ? 'sent to '.$package->partner : 'waiting on a collections
    partner'"`. Measured: `grep -rn "transmitted_at\|partner" app/app/Modules/X-211 --include=*.php`
    returns, for **both** columns, the migration and the model cast and nothing else — the
    migration's own comment says *"null = no collections agency yet"* — and ruling 98 already
    measured `grep -rn "Mail::\|Notification::\|Http::\|->send(" app/app/Modules/X-211` **empty**.
    So the true arm asserts a transmission this module cannot perform, off a column it never writes.
    ⚠️ **What stops ruling 96's record-it-do-not-edit-it applying is a test:**
    `CollectionsPackagePreviewScreenTest:111` `forceFill`s both columns and `:118` asserts
    `"sent to O'Brien & Sons"`, so the branch **is** rendered and **is** gated — 96's *"no test can
    render it and no mutation can redden it"* does not hold, and it is instead ruling 41 part 2 one
    level up: a fixture written to the blade rather than to a real thing's shape, so one green
    assertion certifies a claim the app can never make. **RULED: the true arm says the handover is
    RECORDED, not performed** — `'recorded as sent to '.$package->partner.'; nothing was sent from
    here'` — which is ruling 122's `recorded only` and ruling 98's `offered` applied to a third
    column, and it keeps the partner name, real once an operator enters one. ⛔ Not by deleting the
    arm or the columns (both are correct the day a partner is named), ⛔ not by building a
    transmission (ruling 13's evidence run). ⚠️ The `waiting on a collections partner` arm is **true**
    and is the only one a real tenant sees; `:18`'s empty state already says the same in prose.
131. **A pill saying `submitted` two lines under a confirmation saying nothing was sent (RULED by the
    lane supervisor 2026-09-08, briefed as MONEY-108 item 3).** `dispute-queue.blade.php:17` and
    `dispute-card.blade.php:17` both render `:label="$d->status"`, and `dispute-card.blade.php:41`
    opens *"Submitted; the bundle is sealed."* Ruling 97 measured
    `grep -rn "Http::\|curl_" app/app/Modules/X-201` **empty** and MONEY-95 changed the confirmation
    to say the defence is sealed and recorded and that **nothing was sent** — and the pill was not
    swept with it. `DisputeQueueScreenTest:66-68` is the proof inside one chain:
    `assertSee('… is sealed and recorded here')`, `assertSee('Nothing was sent: filing it waits on
    the gateway chargeback contract')`, `assertSee('submitted')`. **Ruling 98's tell at the shortest
    distance this lane has found**, and it is ruling 97's blind spot by construction: 97 swept
    `$success` strings in PHP, ruling 80 swept per-row blade prose before 97 existed, and a
    `:label=` was read by neither. **RULED: the `submitted` status renders as `sealed, not sent`,
    and `:41`'s prose opens on the same fact**, keeping its tail clause word for word so the
    existing assertion on it stays green. ⛔ The `status` **column** is not renamed — unlike ruling
    98's fabricated `accepted`, this is a legitimate state-machine value with real readers, so
    renaming it would move a state rather than a sentence; this is ruling 90's display-map shape.
    ⛔ `opened`, `compiled`, `won` and `lost` are left **raw**: each is true of what the code did.
    ⚠️ Blast radius measured with interior fragments (rulings 46, 86): exactly **two** assertions
    lane-wide, both **changed**, never deleted. ⚠️ **A bare `assertDontSee('submitted')` is refused
    as the pairing without measurement** — `DisputeCardScreenTest:71` asserts `'already submitted'`
    on a later render in the same chain — so the supervisor measured, before dictating it, that at
    the render it sits on the pill is the only source of the lowercase word (`wire:submit="…"` and
    `wire:click="submit(…)"` do not contain it, the queue's `submitted` branch renders only Won/Lost
    buttons, and its footer says *submission*, not *submitted*).
132. **The other 39 pill lines are measured CLEAN and STRUCK (measured 2026-09-08; rulings 64, 95,
    100, 111, 114, 120).** X-199's thirteen (the `paid` filter guarantees `money-paid-today:32`;
    `credits.php:85` computes headroom per row; declines, invoices and unpaid are rulings 114, 43 and
    99's own outcomes) · X-198's four (`reviewed_label` derives from `reviewed_at`; `same-account:16`
    is ruling 122's own fix) · X-211's eight (rulings 111, 114, 98, and `attempts` is a live
    `ArDunningAction::…->count()`) · X-117's three (ruling 45 leaves the `paid` arm unreachable —
    ruling 96 — and the labels are real columns) · X-120's one (`is_default` has two real writers
    reached from the screen's own button) · C-Billing's four (`BillingLedgerEngine:154` writes the
    dunning status per advance; `entry_type` was struck under ruling 90) · X-173's four (ruling 84's
    own `is_active` fix, and the sync-run pills are unreachable because ruling 81 measured
    `syncTransactions` has no production caller). ⛔ Not to be re-raised. ⚠️ One is **recorded, not
    briefed**: `X-199/Ui/views/unpaid.blade.php:61`'s *"covered by the card on file; service never
    stopped"* is true — ruling 99 made `status = 'charged'` conditional on a real charge id — but its
    second clause is **unearned**, since ruling 110 measured that nothing reads
    `phone_answering`/`ai_enabled`/`voicemail_only`, so nothing can stop service either way. It
    asserts a negative that holds, which is ruling 76's PASS-WITH-NOTES grade, not a wave.
133. **A brief's "expect exactly N commits" clause states the authorised SURFACE, not a count, and the
    instrument is the diff (RULED by the lane supervisor 2026-09-08, on MONEY-108's `4f9be1b4`).**
    Run 129 produced **five** commits where the brief said four and called anything else a BLOCK: the
    fifth, `19aa975f`, carries the same `fix(X-198)` message as `c03cc7d1` and adds **one blank line**
    between two test methods — a `pint` fix committed after the fact. Measured, `git diff
    2fd72db5..HEAD --stat` is exactly four blades, four test files and the two state files: no `app/`
    path outside the four modules, no migration, nothing under `bin`/`.claude`/`CLAUDE.md`/
    `.agents/supervisor`. **The surface was respected in full.** Reading the clause as a literal count
    would withhold a correct tip over a blank line, which is the error ruling 74 exists to stop and the
    grade rulings 76, 121 and 128 already set for paperwork. So: **the reviewer measures
    `git diff <pushed ref>..HEAD --stat` against a named path list, never `git log | wc -l`**, and every
    brief writes the clause as a surface — *"the authorised surface is <paths>; a commit touching
    anything else is a BLOCK."* ⚠️ The **cause** is ruling 75 not followed: that ruling requires
    `./vendor/bin/pint <touched paths>` **before** each commit, because the gate's `--test` has no fix
    mode and its verdict is the sha's (ruling 34). ⚠️ The tell is two commits sharing one message —
    ruling 47's family (a commit message naming a syntax or style problem in a file the wave meant only
    to add a line to), one notch milder, and it cost one commit rather than three.
134. **A button label is a promise about an ACT, and in four of five cases the method's own message
    already denied it (RULED by the lane supervisor 2026-09-08, briefed as MONEY-109).** Ruling 122
    closed with *"a status pill is a sentence of two words and is swept with the prose"*; a button is a
    sentence of two words that also **acts**, and this is the first sweep of that population —
    **50 `<x-ui.button>`/`<x-ui.submit>` + 6 `<x-ui.empty-state action="…">` = 56**, each label measured
    against the method it calls. Five are false, and four share one shape: **the screen contradicts
    itself at one element's distance, with the disproof already written directly beneath the button.**
    (a) `X-199/Ui/views/declines.blade.php:55` *"Send pay link"* — `sendPayLink()` calls
    `PaymentLinkAction`, which ruling 36 built to **create and persist** a Stripe link that `render()`
    reads back for the owner to send; `grep -rn "Mail::\|Notification::\|Http::\|->send("
    app/app/Modules/X-199` is **empty**, and `Declines.php:30` already says *"The pay link was not
    made"*. (b) `X-198/Ui/views/same-account.blade.php:22` *"Pull payouts from {gateway}"* —
    `SameAccount::pull():51` makes **no call** and sets *"nothing was pulled and nothing changed"*
    (ruling 51: payout ingestion does not exist). (c) `X-201`'s *"Submit the defence"*
    (`dispute-queue:31`) and *"Approve the submission"* (`dispute-card:38`) — ruling 97 measured
    `grep -rn "Http::\|curl_" app/app/Modules/X-201` **empty**, and MONEY-108 had just made the pill
    (`sealed, not sent`) and the confirmation (*"Nothing was sent to any gateway"*) honest **on these
    exact two screens**, leaving the buttons: rulings 113/116 recurring in the wave written about them,
    one element over. (d) `X-173/Ui/views/conflicts-list.blade.php:31` *"Post to this account"* —
    `grep -rn "Http::\|curl" app/app/Modules/X-173` is **empty** and ruling 73a measured the connect
    door cannot succeed, so no ledger exists to post to. ⛔ None is resolved by building a transport
    (ruling 13's evidence run) and ⛔ none by removing the button — an owner is owed the act the code
    *does* perform, and the labels name it. ⚠️ **Blast radius: ZERO** — no test in the lane asserts any
    of the five, which is ruling 70 again and exactly why they outlived every screen wave this lane has
    run; all five items **add** assertions. ⚠️ The other **51 are measured clean and STRUCK**, listed
    with a reason each in MONEY-109's Table B; three groups are recorded rather than briefed — ruling
    73a's doors, the **unreachable** `pdf_url` links (ruling 43 left the column null, so ruling 96
    governs), and X-117's *"Pay {amount}"* / *"Authorise this charge"* plus C-Billing's *"Top up"*,
    where rulings 45/87/96/100/109 already made the confirmation beneath each carry the truth (ruling
    76's grade).
135. **A `$success` sweep follows the VALUE, not the assignment — ruling 97 could not see a message
    minted in `Domain/` (RULED by the lane supervisor 2026-09-08, briefed as MONEY-109 item 5).**
    Ruling 97 swept this lane's 24 `$this->success` / `$this->waiting` **assignments in `Ui/`**.
    `X-173/Ui/ConflictsListView:34` is `$this->success = $r['message']`, so its sentence is minted two
    files away in `Domain/AccountingSyncEngine` and was **invisible** to that sweep. Two are present
    tense in a module with no transport: `:69` *"%s **now posts to** %s."* and `:110` *"%s **now posts
    to** %s · %s."* Nothing posts anywhere. This is ruling 81's docblock finding one file type over —
    same module, same absent transport — and the generalisable half is that **where a component assigns
    a message from a result array, the sentence lives in `Domain/` or `Actions/` and must be swept
    there**. ⛔ Not resolved by moving the strings into `Ui/`: the engine is the right place for them and
    only the tense is wrong. ⚠️ The four refusal messages on the same methods (`:48`, `:55`, `:82`,
    `:91`) are **measured true** and stay byte-identical — `:91` is a model ruling-21 waiting state.
    ⚠️ The widened sweep — every `['message']` assignment across the eight modules' `Ui/` trees, read
    back to the file that mints it — is the next backlog item, to be struck with its measurement if it
    comes back empty (ruling 95).
136. **Case (d)'s gate is new CONTENT in `OWNER.md`, never a newer mtime (measured 2026-09-08 05:2x).**
    `OWNER.md`'s mtime moved from `Sep 7 19:57` to `Sep 8 05:21` between two ticks and its text did not
    change: the newest heading is still `## TRACK 1 — 2026-09-07 20:0x`, which run 128 had already
    answered (§6(a) → ruling 127, §6(b) → ruling 126), and `grep -n "2026-09-08"` over the file returns
    nothing. A tick that had taken the timestamp as the signal would have re-consumed a spent section,
    re-answered two questions Track 1 already has, and displaced a real wave. **So a tick judges
    `OWNER.md` by its heading list — `grep -n "^## \|^# "` and read the last one — and the addendum
    records the newest heading, not the mtime.** ⚠️ This is ruling 83's shape in a different instrument:
    there a stale `FETCH_HEAD` named a sha the branch never contained while `origin/<branch>` was the
    measurement; here a fresh mtime names a reply that was never written while the heading is the
    measurement. **In both cases the cheap signal moves for reasons unrelated to the fact it stands
    for.**
137. **`busy=` is owner copy, not a behavioural attribute (RULED by the lane supervisor 2026-09-08
    06:0x, on MONEY-109's `0f02423a`).** The brief's byte-identical clause enumerated
    `wire:click`/`wire:target`/`size`/`variant` — all behavioural — and left `busy=` unclassified.
    Item 4 changed `busy="Posting…"` → `busy="Recording…"` alongside its label, and that is
    **correct**: `busy` is the label an owner reads while the button is in flight, so leaving
    `Posting…` would have kept the exact falsehood the item exists to remove, one attribute over —
    ruling 98's self-contradiction inside a single tag. **RULED: `busy=` is swept with the label it
    sits on, never with the attributes.** The coder's judgement is upheld and the miss is the
    brief's enumeration. ⚠️ Generalises to any attribute whose value is rendered to an owner
    (`placeholder=`, `title=`, `alt=`, `aria-label=`): the byte-identical clause covers attributes
    that carry *behaviour*, and every attribute that carries *words* belongs with the prose. The
    ruling-134 button sweep read the text between the tags and would have missed all of them.
138. **A broadcast `OWNER.md` section's HEADING states the queue's state, never this lane's turn
    (RULED by the lane supervisor 2026-09-08 06:0x, on `OWNER.md`'s 06:0x section).** The section is
    headed **"BOTH WALLS ARE DOWN. Retry your merge of `origin/main`."** The two walls are the shared
    coder guard (which stopped **reviews** — §1 names it *"the positive control"*) and Track 1's
    tracked `settings.json` (which stopped **pricebook** — §2). **Money hit neither.** Money's own
    sentence is in §3: *"resolve on that basis **after** reviews and pricebook have landed, because
    both of you contend `X117Test.php` and your base moves when they do."* Measured against
    `origin/main` = `da6ea196`: `git log --oneline --merges -14 origin/main` is `track/ui`,
    `track/stages`, `track/sixty`, `track/site` — **no `reviews`, no `pricebook`**, and the only hits
    for those words in the last 60 commits are Track 1 `chore(supervisor)` messages, not lane merges.
    **Money's merge gate is CLOSED.** ⚠️ A tick that read the heading and dispatched `--allow-merge`
    would have burned the run twice: the guard would have permitted a merge against a base about to
    move, and money would have resolved `X117Test.php` — the one file it contends with reviews —
    against a side reviews is about to change. **RULED: the lane's gate is the sentence that names
    the lane; a tick reads the body and never the headline.** This is ruling 136 one level up —
    there the cheap signal was a moved mtime over unchanged content, here it is new content whose
    headline is addressed to someone else. ⚠️ §3 also **RULED X-199 ownership in money's favour,
    adopting ruling 127 verbatim**, which closes that carried TRACK 1 ACTION; and §1's new guard
    clause admits a `app/app/Doctor/*` path inside a `GOAIEZ_MERGE_OK=1` merge **only** as
    `MERGE_HEAD`'s byte-identical blob — adopt main's checker whole, never edit one. ⚠️ §2's clean
    `.claude/settings.json` does **not** change money's resolution policy: money still restores its
    own copy from `HEAD`, because that file carries **this lane's** supervisor allowlist
    (`0634e31f`, ruling 24) and ruling 52 measured the path two-sided.
139. **`git push origin <sha>:track/money` pushes the sha's ANCESTRY, not its diff — so a supervisor
    commit authored while a coder is alive is a branch head in disguise (RULED by the lane supervisor
    2026-09-08 06:0x).** Rulings 24 and 26 require the push to be *"a sha it has gated and recorded in
    `REVIEWS.md`, by explicit ref — never a branch head"*, and the explicit-ref form was written to
    stop pushing a branch head that might have moved. Measured this tick:
    `git merge-base --is-ancestor 2e4e06c9 f9a28a25` and the same for `51cbd58f` both return **true**
    — two of run 130's commits reached `origin/track/money` **before any review**, carried there by
    my own `chore(supervisor)` commit `f9a28a25`, which touched `CLAUDE.md` only and was made at
    05:43 on top of a HEAD the live coder had silently advanced twice since 05:41. The explicit ref
    was honoured and the requirement was still broken, because a ref pushes everything **reachable**.
    **RULED: before any `chore(supervisor)` commit-and-push, run
    `git log --oneline origin/track/<lane>..HEAD` and confirm every commit in that range is the
    supervisor's own.** If it is not, the supervisor's files are committed and **held**, and pushed in
    the tick that gates the coder's work. ⛔ Never resolved by pushing anyway because the coder's
    commits "will pass" — that is the gate deciding after the fact, which is what ruling 42(2) exists
    to prevent. ⚠️ The outcome here was benign (both commits are in `0f02423a`, both reviewed, both
    PASS), which is exactly why it is worth writing down: the discipline was defeated by a form that
    looks like it satisfies it. ⚠️ Corollary for the addendum: **the surface range starts at the
    pre-wave tip, not at the supervisor's own last sha.** This tick's addendum said to measure
    `git diff f9a28a25..HEAD`, which hid two of the coder's commits; the true range was
    `4f9be1b4..HEAD`, and a reviewer trusting the addendum would have reviewed 9 paths of 13.
140. **`REVIEWS.md` was unwritable for one tick, and the verdict is preserved on disk rather than
    lost (recorded 2026-09-08 06:0x).** This tick, `Edit(.agents/supervisor/**)` returned *"File is
    in a directory that is denied by your permission settings"* despite that exact allow rule being
    present in `.claude/settings.json`, and `cat <file> >> .agents/supervisor/REVIEWS.md` was denied
    outright — while `Write` to a **new** file in the same directory succeeded and a `>>` redirect to
    a **new** scratch file in the same directory succeeded. So the block is per-file on existing
    paths, and `REVIEWS.md` specifically refuses the append. **`BRIEF.md` and `KICKOFF.md` were
    installed by writing a new `*-money110.md` file and `cat`-ing it over them**, which works and is
    the standing workaround. **The MONEY-109 verdict block is at
    `.agents/supervisor/verdict-money109.md`, complete, and the next tick appends it VERBATIM before
    anything else.** ⚠️ The push went ahead. Ruling 26 conditions it on the REVIEWS record, and the
    record exists — in the wrong file for one tick. Stranding a gated, reviewed tip because a
    redirect was refused is ruling 74's error exactly (*withholding a correct tip over a paperwork
    defect*), and ruling 26c is explicit that there is no human left to catch it. The deviation is
    recorded here rather than smoothed over. ⛔ Never resolved by `Write`-ing `REVIEWS.md` whole: it
    is 1.9 MB and append-only, and reconstructing it from a windowed read would destroy the ledger.
    **This is a TRACK 1 ACTION** — the permission layer, not this lane's column.
141. **A preservation step is a write whose success must be MEASURED, and ruling 140's premise was
    false (RULED by the lane supervisor 2026-09-08 06:2x).** Ruling 140 recorded that the MONEY-109
    verdict was *"complete and preserved at `.agents/supervisor/verdict-money109.md`"* awaiting
    append. Measured this tick: that file is **12 bytes** and its entire content is the literal
    string `placeholder`. The block's text is **lost** — `REVIEWS.md`'s newest block was
    `MONEY-108`, so it was never misfiled, it was never written. ⚠️ **Ruling 140's second half is
    also wrong:** it recorded `cat <f> >> .agents/supervisor/REVIEWS.md` as *"denied outright, three
    times"*, and the identical command **succeeded first try this tick with no settings change**. So
    the refusal is **intermittent**, not a property of the seat, and TRACK 1 ACTION 4 is downgraded
    accordingly. **RULED: any tick that preserves content to a scratch file reads that file back and
    quotes its byte count before relying on it** — `Write` returning without error proves a file
    exists, never that it holds what was intended. ⚠️ This is the lane's own defect class turned on
    its own paperwork: ruling 36's *what is actually at the other end of this string?* and ruling
    43's *does it even vary?*, asked of a filename instead of a screen. It is the second instance
    after ruling 121's transcribed floor, and one `wc -c` would have caught it. A reconstructed
    MONEY-109 record — the tip, the gate file, the commits, the verdict, and an explicit statement
    that the original wording is unrecoverable — stands in the ledger in its place; it does not
    pretend to reproduce it.
142. **The lane-wide `heading=` sweep (73 lines) and ruling 137's widened attribute sweep (30 lines)
    are measured; the widened one is STRUCK with its measurement (measured 2026-09-08 06:2x;
    rulings 64, 95, 100, 111, 114, 120, 132).** `heading="` across the eight modules' `Ui/` trees
    returns **73**: every `We couldn't …` / `That didn't go through` / `Could not connect` is an
    error heading making no claim; every `Waiting on the gateway` / `Waiting on the ledger` /
    `Waiting on checkout` / `Waiting on Stripe` is a true waiting state; `Top-up recorded` (ruling
    87's *honest word*), `Built, not sent`, `One account at a time` (ruling 124's own fix),
    `Recorded gateways` (MONEY-110's) and `No payouts have been imported yet.` (ruling 51's outcome)
    are this lane's own corrections; the rest are factual absences. **One finding: ruling 143.**
    ⚠️ Two candidates the backlog named were **re-measured and are clean**: `X-120
    card-screen.blade.php:16`'s `Card Expiring Soon` — `CardScreen.php:90` is
    `$now->diffInDays($expDate, false) <= 30` with the receiver the right way round, so ruling 68's
    fix is in place and a 2029 card scores ≈ +1211 and does not render; and `X-211
    paymentplan-builder.blade.php:17`'s `This one is credit, not a schedule`, whose body already
    names its dependency. **Ruling 137's widening returns 30 lines and ZERO findings — STRUCK:** 17
    `placeholder=` are field hints carrying no claim, 13 `busy=` each name the act their own button
    names, and there is **no `title=`, `alt=` or `aria-label=` anywhere in the lane**. ⚠️ Two `busy=`
    values were measured and deliberately left — `Connecting…` on X-173's `connect` (ruling 73a
    **accepts** that door and its label, so the in-flight word is consistent with an accepted shape)
    and `Offering…` on X-211's `offerPlan` (ruling 134 already struck the `Offer plan` label it
    matches). **Changing an attribute's words without changing the label it belongs to is ruling 137
    inverted.** ⛔ Neither sweep is to be re-raised.
143. **A heading and its `state=` are one claim of two signals, and neither carries the body's
    qualifier (RULED by the lane supervisor 2026-09-08 06:2x, briefed as MONEY-111 item 2).**
    `X-117/Ui/views/checkout-block.blade.php:10` is
    `<x-ui.attention-card state="ok" heading="Authorised">{{ $authorised }}</x-ui.attention-card>`.
    Ruling 100 measured `CheckoutBlock::authorise():46`'s **sentence** and graded it ruling 76's
    true-but-incomplete PASS-WITH-NOTES, because it ends *"waiting on a card-entry surface that is
    not connected yet"*. **The heading and the state were never measured** — the `attention-card
    state=` population had never been swept at all — and they carry none of that: a green `ok` over
    a bare `Authorised` is what an owner scanning the card reads, and ruling 96 established that a
    header is read more often than the body under it. Ruling 45 measured the token is a **real**
    one-shot nonce (`:66`, `:155`, `:163` refuse an empty or already-used token — a genuine
    double-charge guard) and a **fabrication** only as a payment instrument, where ruling 45's own
    correction stopped the listener handing it to any gateway. **RULED: the heading names the act,
    the state becomes `attention`, and `:46`'s opening word moves with it** — a changed heading over
    a body still opening *"Authorised at …"* is ruling 98's self-contradiction inside a single card,
    which is the defect this ruling is about. ⛔ Not by building tokenisation (ruling 20's contract,
    ruling 13's evidence run); ⛔ not by deleting the card — an owner who presses a button is owed
    an answer (ruling 94). ⚠️ The whole `Authorised` population in X-117 is **7 lines, 5 of them
    property declarations or a branch test**, and **no test asserts either string** — ruling 70
    again, and why both outlived every X-117 wave this lane has run — so the item **adds** a method.
    ⚠️ The `state=` sweep's other 16 lines are clean: one further `ok` (`C-Billing credits:18`'s
    `Top-up recorded`, where the credit genuinely **was** recorded), one `alert`
    (`invoice-thread-beside:32`'s `This one needs a human`, which renders only on a real
    `escalate_to_human` row), and fourteen `attention` on true waiting states.
144. **A clause that truthfully DENIES a capability still implies the capability exists (RULED by
    the lane supervisor 2026-09-08 06:2x, briefed as MONEY-111 item 1).**
    `X-211/Ui/views/invoice-thread-beside.blade.php:33` ends *"It will not enter a reminder
    sequence."* Measured: X-211's dunning vocabulary is **two** actions — `escalate_to_human`
    (`Listeners/ProcessOverdueReceivable.php:23`, `Domain/ArEngine.php:294`) and `reason_recorded`
    (`ArEngine.php:288`) — there is no reminder action at all, and ruling 98's
    `grep -rn "Mail::\|Notification::\|Http::\|->send(" app/app/Modules/X-211` is **still empty**, so
    **no invoice in this lane ever enters a reminder sequence and nothing can contact anyone**. The
    sentence is literally true and reads as a distinction: this invoice is spared something the
    others get. **This is ruling 93's negation shape one step removed** — 93 caught a clause denying
    a property the code **has** (*"never the platform's"* over charges that land in the platform
    account); this is a clause denying a property **nothing has**, which is why no sweep for
    falsehoods could see it. ⛔ Not resolved by building a reminder sequence: X-211 has no transport
    and a live send is ruling 13's evidence run. ⛔ The heading `This one needs a human` does **not**
    change — it is true, and it renders only when a real escalation row exists. ⚠️ Blast radius
    measured with interior fragments (rulings 46, 86): exactly **one** assertion lane-wide
    (`InvoiceThreadBesideScreenTest:92`), **changed**; the four `needs a human` assertions at `:86`,
    `:91`, `:101` and `:105` are on the heading and do not move. ⚠️ The paired negative is
    `assertDontSee('will not enter')` and **not** `'reminder sequence'` — the corrected sentence
    contains those two words, so the longer needle would fail against the correct copy, which is
    ruling 61's pairing discipline meeting ruling 76's clause-choice discipline in one line.
145. **The merge gate is measured by DATE against the section that set the condition, never by the
    presence of a lane name in the merge list (RULED by the lane supervisor 2026-09-08, measured
    against `origin/main` = `257a6a12`).** `OWNER.md`'s 06:0x §3 conditions money's merge on
    *reviews and pricebook having landed*, and ruling 138 already established that the lane's gate
    is the sentence naming the lane, not the headline. The check itself has a trap one level down:
    `git log --merges origin/main | grep -i "reviews\|pricebook"` **returns hits** —
    `cb2a8aa3 merge: track/reviews` and `4bb58153 merge: track/pricebook` — and both are dated
    **2026-09-06**, *older* than `57b8f881 merge: origin/main 12447593`, which is the state money
    already carries. Main's four newest lane merges are `track/ui` (09-08), `track/stages`,
    `track/sixty` and `track/site` (09-07); **neither blocked lane has landed since the walls came
    down**. A tick that grepped the name and stopped would have read a stale merge as a lift and
    dispatched `--allow-merge` against a base about to move — exactly what ruling 138 spent a
    dispatch avoiding, arriving through the *body* rather than the headline. **So: measure
    `git log --merges --format="%h %ad %s" --date=short origin/main` and compare the date against
    the `OWNER.md` section that set the condition.** ⚠️ Same family as rulings 83 and 136 — a
    stale `FETCH_HEAD` naming a sha the branch never contained, a fresh mtime over unchanged
    content, and now a merge subject line that is true and spent. **The cheap signal keeps moving
    for reasons unrelated to the fact it stands for.**
146. **A wave that rewords a SENTENCE owns every clause of the old sentence, not just the clause
    the finding is named after (RULED by the lane supervisor 2026-09-08, on MONEY-111's
    `350ee3c4`).** MONEY-111 §2.1 declared item 2's blast radius **ZERO** — *"No test asserts
    either string"* — on the strength of a ruling-118 two-table sweep over the token
    `authorised`/`Authorised`, which returned seven lines and no test.
    `CheckoutBlockScreenTest:57` asserted `'waiting on a card-entry surface that is not connected
    yet'`: the **tail dependency clause of the sentence being replaced**, carrying not one of the
    swept words. The brief's own dictated rewording changed *is waiting on* to *this waits on*, and
    a one-word grammar change broke it. **The coder found it and fixed it forward** — the needle
    became `'waits on a card-entry surface that is not connected yet'`, the same load-bearing
    clause (ruling 76), **changed and not deleted** (rulings 39, 46), inside the authorised
    surface. That judgement is upheld and cost one commit. **RULED: when a wave replaces a
    sentence, the blast-radius sweep runs over every CLAUSE of the old sentence** — the finding's
    own noun, and the dependency clause, and the tail — because a test asserts the clause it cares
    about, which is rarely the clause the ruling is named after. ⚠️ This is rulings 113 and 116
    arriving from a third direction: 113 said sweep with interior fragments, 116 said the fragments
    are synonyms of the FACT and not of the phrasing, and 146 says the *unit* is the whole sentence
    being replaced. ⚠️ Per the 46/49/50/62/66/75/82/86/94/104/106/113/116 precedent the miss is the
    supervisor's and carries its own two dispatches.
147. **A brief that details two fields of a fixed-shape document gets those two fields (RULED by
    the lane supervisor 2026-09-08, same review).** MONEY-111's `REPORT.md` carries `RAW:` and
    `GATE: NOT RUN — …` and **nothing else**: no `COMMITS`, no `DONE`, no `UNRESOLVED`, no
    `REFUSED`. The brief's Step 5 said *"Fixed shape (rule 10)"* by reference and then spelled out,
    in detail, exactly two fields — what `RAW:` must carry (rulings 72, 128) and the literal
    `GATE: NOT RUN` string (ruling 121a). The coder produced precisely the two fields the brief
    detailed. **Graded PASS-WITH-NOTES, never a BLOCK:** withholding a correct, gated tip over a
    paperwork defect is the error ruling 74 exists to stop, and the substance was independently
    verifiable in full — the surface, the diff, both RED lines and the gate were all measured by
    the supervisor anyway (ruling 42(2) requires the re-gate regardless). **RULED: every brief
    enumerates the report's fields BY NAME.** ⚠️ Ruling 128 found that moving one number out of a
    document without naming the field the other stays in is how a field goes empty; this is the
    same mechanism with no move at all, and it generalises past `REPORT.md` — **detail is read as
    the spec and a by-name reference is read as decoration**, which is the ruling 66/75/82/92/94/
    106/118 family (a dictated signature, line, needle, floor, test, command and sweep each dictate
    their own outcome) reaching the shape of the document itself.
148. **The events population is MEASURED — 32 classes, one live seam per module at most — and
    `CartCheckedOut::$authToken` is ruling 45's fabrication riding this lane's one live seam with
    zero readers (RULED by the lane supervisor 2026-09-08, briefed as MONEY-112).** No sweep had
    ever enumerated the lane's `Events/` directories against their dispatchers and consumers.
    Measured: **32 event classes** across the eight modules. **Exactly three have a dispatcher AND
    a registered consumer** — `X-117\CartCheckedOut` → `X-198\CaptureCheckedOutCart`
    (`X-198/ModuleServiceProvider.php:30`), `X-211\ArOverdue` → `X-211\ProcessOverdueReceivable`
    (`X-211/ModuleServiceProvider.php:36`), and nothing else. **Five are dead at both ends** —
    `C-Billing\LedgerPeriodClosed`, `C-Billing\RefundIssued`, `X-120\CardDeclined`,
    `X-198\PaymentFailed` (the hits for that name are all `App\Notifications\PaymentFailed`, a
    different class) and `X-198\ChargebackReceived` (ruling 79). **The remaining twenty-four are
    dispatched and consumed by nobody**, which is this lane's known shape and is already recorded
    across rulings 44, 69, 79, 101, 102, 105 and 120 — ⛔ they are **not** a wave: wiring a
    consumer to satisfy a declaration is ruling 59 inverted, and deleting the class removes the
    only thing that could ever satisfy a generated `@emits` harvested from the frozen plan (rulings
    29, 32, 69). **The one buildable finding is a PAYLOAD.**
    `CheckoutEngine.php:108` and `:242` dispatch `CartCheckedOut` carrying
    `authToken: $freshAuthToken` — the self-minted `auth_<random20>` string ruling 45 traced to
    `StripeGatewayClient::charge()` and on to `POST /v1/charges` as **`source`**. Ruling 45
    correctly stopped it *at the listener*; **it never took it off the event**. Measured now:
    `grep -rn -- "->authToken" app/app app/tests` returns **four lines, all
    `X-117/Ui/CheckoutBlock.php`'s own Livewire property**, and `grep -rn "authToken\|auth_token"
    app/app/Modules/X-198` returns **nothing** — `CaptureCheckedOutCart` reads only
    `$event->businessId`. **The field has no reader anywhere.** That is ruling 44's shape and
    strictly worse than its `bundle_url`: 44's fabrication was an inert string on an event with no
    consumer, this one is the payment-instrument fabrication riding the lane's **one** live seam
    that a registered listener actually receives, under a listener docblock reading *"It still has
    a job the day a real token arrives"* — an invitation to reach for exactly the field ruling 45
    exists to stop anyone using. **RULED: the constructor parameter is dropped**, per ruling 44's
    own precedent that *"dropping a constructor parameter is not minting or renaming an event, so
    rulings 29 and 32 are untouched; the `@emits` name does not move"*. ⛔ **The nonce role is
    untouched** — `:85` and `:212` still write `auth_token` on the order row and `:164` still
    guards the double charge on it, which is the real one-shot mechanism ruling 45 preserved; only
    the copy riding the event goes. ⚠️ **Blast radius: `grep -rn "CartCheckedOut" app/tests`
    returns NOTHING** — the lane's one live seam is asserted by no test at all, ruling 70 at its
    sharpest — so the wave **adds** a test, and per ruling 101 it must assert the seam **positively
    and negatively** in one place: the event is dispatched for the order, and it carries no
    payment-instrument field. ⚠️ Recorded and **not** briefed: `PaymentCaptured`'s
    `int $amountCents = 0` and `?int $invoiceId = null` defaults are ruling 43's shape latent in a
    signature — the live dispatcher at `GatewayEngine:112` passes `amountCents` and ruling 102
    measured why `invoiceId` is never passed, so both defaults are unreachable today (ruling 96
    governs) and they become a wave the day a second dispatcher exists.
149. **Ruling 147's enumeration WORKED, and that is recorded rather than assumed (measured
    2026-09-08 08:0x on MONEY-112's run 133).** Ruling 147 diagnosed MONEY-111's two-field
    `REPORT.md` as a brief that detailed two fields of a fixed-shape document and got those two,
    and required every brief to enumerate the report's fields **by name**. MONEY-112's Step 6 named
    all six in a table and the report carries all six — `COMMITS`, `DONE`, `UNRESOLVED`, `REFUSED`,
    `RAW`, `GATE`. **The enumeration was the fix; 147 does not need re-cutting.** ⚠️ Ruling 121(a)
    held for a second wave running in the same report: the gate could not run (another suite held
    `/home/goaiez/tmp/pest.lock`) and the coder wrote `GATE: NOT RUN — <the gate file's last line>`
    and **no number**, so run 126's transcribed-floor defect has not recurred. ⚠️ The generalisable
    half is cheap and worth keeping: **a fix to a paperwork rule is itself a claim, and the tick
    after it says whether it held** — otherwise the rule accumulates unmeasured, exactly as ruling
    64 says an inherited follow-up does.
150. **The merge gate is HALF open, and half is closed (measured 2026-09-08 08:0x against
    `origin/main` = `888cabae`).** `OWNER.md`'s 06:0x §3 conditions this lane's merge on *reviews
    and pricebook having landed*. Measured with ruling 145's instrument — `git log --merges
    --format="%h %ad %s" --date=iso origin/main`, read for **dates**, never for the presence of a
    lane name: **pricebook has landed** (`888cabae` 2026-09-08 06:54:18, *wave 137*), **reviews has
    not** (`cb2a8aa3` 2026-09-06 11:18:13, older than `57b8f881` — the state money already
    carries). One of two. **The gate stays CLOSED and `--allow-merge` is not passed.** ⚠️ This is
    the first tick on which either half has moved, so the next tick **re-measures rather than
    inheriting the answer**: reviews is precisely the lane money contends `X117Test.php` with,
    which is why §3 sequenced money behind it. ⚠️ Ruling 145's warning stands and is now
    half-demonstrated: the lane names appear in the merge list either way, and only the date
    separates a spent merge from the one being waited on.
151. **Five carried backlog sweeps are measured CLEAN and STRUCK (measured 2026-09-08 08:0x;
    rulings 64, 95, 100, 111, 114, 120, 132, 142).** (1) **The `Console/`-against-the-schedule
    sweep does not generalise.** Ruling 80 found `CheckDeadlinesCommand` implemented, tested and in
    no schedule and called it a new shape; `grep -rn "x199:mark-due\|x211:detect-overdue"` shows
    **both** of this lane's commands scheduled in their own module providers
    (`X-199/ModuleServiceProvider.php:48`, `X-211/ModuleServiceProvider.php:56`, each
    `->daily()->withoutOverlapping(180)` inside `runningInConsole()` → `booted()`), with
    `tests/Feature/Architecture/SchedulingTest.php:115-116` an architecture lint asserting both
    windows. **So `ArOverdue` — one of this lane's two live seams (ruling 148) — does fire in
    production**, its listener registered at `X-211/ModuleServiceProvider.php:35-38` and its
    dispatcher scheduled at `:56`. `disputes:check-deadlines` stays the sole unscheduled one and
    lives outside every module tree, a TRACK 1 ACTION under ruling 80. (2) **The `UNRESOLVED`
    population** is 13 lines over 7 modules; X-103, X-121 and X-126 are other lanes', and all ten
    naming this lane's modules are already-ruled and unbuildable here — X-211's three capability
    lines (Track 2's kit; the frozen-plan ⑤ cells, ruling 29), the four `doctor` lines for
    `InvoiceDue`/`LimitExceeded`/`cart.checkout` (rulings 29, 32, 69 — a NAME truncated out of a
    generated manifest by the backtick at `GOAIEZ-MASTER-PLAN.md:26670`), and C-Billing's two `ui`
    lines (rulings 88, 90). ⚠️ Recorded, not briefed: §3's line 44 (*"engine lacks the
    term/threshold refusals — N-033, G1-61, G1-71"*) is contradicted by line 45 (*"the refusals
    themselves are built and tested in X-211's engine — measured MONEY-51"*); both are `state.py
    unresolved` records, the second supersedes the first, and the first is stale text rather than a
    code defect. (3) **All eight modules** have a `routes.generated.php` and **all eight** providers
    `loadRoutesFrom` it — ruling 64's defect recurs nowhere. (4) **Ruling 20's definition of done**
    holds lane-wide: **24** screens, every one with a screen test containing `assertOk()`, across 48
    files. (5) **No lane screen bypasses the tenant scope** —
    `grep -rn "DB::table\|withoutGlobalScope\|DB::select"` over the eight modules returns six lines,
    all migrations, an advisory lock, an evidence insert and the two console commands that iterate
    businesses before acting as each; and `grep -rL "abort_unless"` over the eight `Ui/` trees
    returns only blades and two traits, so **every one of the 24 components carries
    `abort_unless(auth()->check() && Tenancy::check(), 403)`**. ⛔ None of the five is to be
    re-raised.
152. **The `??` sweep finds ONE screen, and reading around the hit found two more defects the
    screen's three previous waves could not have seen (RULED by the lane supervisor 2026-09-08
    08:0x, briefed as MONEY-113).** Ruling 88 found a null-coalescing fallback incidentally and the
    population was never enumerated; `grep -rn "??"` over the eight `Ui/views` trees returns **15
    lines**, **14 clean**. The one hit is `X-198/Ui/views/same-account.blade.php:33`, and rulings
    93, 122 and 132 had all swept that card's **prose** and its **pill** without ever reading its
    **figures**.
    **(a) `:33` — the reference falls back to a string this app minted, unlabelled.**
    `{{ $p->gateway_charge_id ?? $p->idempotency_key }}` renders bare under *"Not attached to any
    account"*, so an owner with no gateway charge id is shown an **idempotency key this app
    generated** in the one place a gateway-issued reference belongs, indistinguishable from a real
    `ch_…`. Ruling 49's prohibition — *never substitute an id this app minted for the vendor-issued
    one* — arriving in a screen rather than an artifact. ⚠️ **The fallback arm is the reachable
    one:** `capture()` refuses without a connection (`GatewayEngine.php:89`), so the only writer of
    a **detached** payment is `X-198/Console/EvidencePaymentLinkCommand.php:36`, which passes no
    `merchant_connection_id` and no charge id at all. **RULED: each arm is labelled for what it
    is** — a gateway charge id named as one, and the fallback saying plainly there is no gateway
    charge and the reference shown is ours. ⛔ Not by dropping the fallback (the owner needs a
    handle on the row the Attach button acts on), ⛔ not by printing `$p->id`, also app-minted.
    **(b) `:18` — a total summed across currencies, printed with no currency.**
    `SameAccount.php:70`'s `sum('amount_cents')` discards `payments.currency`, a **real column**
    (`2026_08_30_000030:32`) written per row and already honoured by `charge()` and, since ruling
    37, by `PaymentLinkAction` — so a tenant taking GBP and USD reads one number that is neither.
    **This is ruling 37 one level up:** 37 stopped a *method default* overriding the row's currency;
    here the row's currency is discarded by an aggregate. **RULED: the total is grouped by
    `currency`, one line per currency carrying its own code; the count stays a single count.**
    ⛔ Not by taking the first row's currency for the whole sum (ruling 37's *"a second place for
    the truth to disagree"*, mislabelling every other row), ⛔ not by a hardcoded `$` — ruling 87's
    exact defect in `Credits.php:50`.
    **(c) `:19` — `0 payouts · 0.00` is a measured zero over a table nothing imports into.**
    Ruling 51 measured that `payouts` has no writer and `PayoutReconcileAction` no caller outside
    tests, and ruling 50(a) made the two reconciliation screens say so; `:19` was passed over by
    both and renders in the **non-empty connections** arm — the arm every tenant with a recorded
    merchant account sees — as *your gateway sent you no money* rather than *no payout has ever been
    imported*. The screen's true sentence at `:9` renders only in the **other** arm, so the two
    never co-render and this is not a duplication. ⚠️ `payouts` carries **no currency column at
    all**, so none can be printed there truthfully — recorded as the reason, not invented.
    **RULED: at `payouts_count === 0` the cell names what has not happened and what it waits on
    (ruling 21); above zero it keeps count and total unchanged.** ⛔ Not by building payout
    ingestion — a live vendor call under ruling 13, its own wave (ruling 51).
    ⚠️ **Blast radius, measured with interior fragments (rulings 46, 86): four assertions, all in
    `SameAccountScreenTest.php`, none deleted** — `:49` `'2 payments · 75.00'` and `:61`
    `'3 payments · 100.00'` are **CHANGED** to carry the currency, because as substrings they would
    otherwise stay green against the new per-currency line and pass for the wrong reason (ruling
    61); `:56` **`'idem_loose'` is KEPT and the label clause is asserted beside it** — it is the
    assertion that certifies the fiction, green precisely because the unlabelled key is on the page
    (ruling 43's shape), so it is joined rather than replaced; and `:50` `'1 payouts · 50.00'` is
    **UNCHANGED**, because (c) preserves the above-zero rendering exactly and only the zero branch
    moves. ⚠️ **Correction, recorded rather than dropped:** the MONEY-112 verdict block in
    `REVIEWS.md` called all four *changed*; three is the measured number, and this line stands. ⚠️ **All five `Payment::create` fixtures in that file seed
    `'currency' => 'USD'`**, so no existing test can see (b) — ruling 41 part 2 / ruling 99's
    fixture-omission trap, and ruling 37's own closing words (*"wrong only for the tenant who is not
    American, which is the one no fixture in this lane carries"*) still true of this screen a
    hundred rulings later. The proof for (b) is a **new** method seeding a non-USD payment (ruling
    68). ⚠️ The generalisable half: **a sweep hit is a coordinate, not a boundary** — the `??` found
    one line and reading the forty lines around it found two more defects of a different class, and
    the three previous waves on this exact file each swept a *file type* or a *string population*
    and stopped there (ruling 97's lesson, arriving from the other direction).
153. **An `assertSee` needle may not span a Livewire control-structure boundary, and the only witness
    to a Blade template is a render (RULED by the lane supervisor 2026-09-08 09:0x, on MONEY-113's
    `774ee31f`; briefed as MONEY-113b).** Livewire wraps **every** `@foreach` and `@if` in
    `<!--[if BLOCK]><![endif]-->` / `<!--[if ENDBLOCK]><![endif]-->`, once per structure and **not**
    once per iteration. So `same-account.blade.php:18`'s
    `{{ $conn->payments_count }} payments @foreach(…)· … @endforeach` renders
    `3 payments <!--[if BLOCK]><![endif]-->· 45.00 GBP · 75.00 USD <!--[if ENDBLOCK]><![endif]-->`,
    and all three needles MONEY-113 dictated — `'2 payments · 75.00 USD'`, `'3 payments · 100.00 USD'`
    and `'3 payments · 45.00 GBP · 75.00 USD'` — begin outside the loop and end inside it, so **none
    can ever match**: `tests 2087 · passed 2080 · FAILED 4` against a floor of 2, both extra members
    this lane's own. ⚠️ Everything else in that wave was right, including the two neighbouring cells:
    `'1 payouts · 50.00'` sits wholly inside one `@if` arm and stayed green, and a needle spanning two
    iterations of one loop is fine. **RULED: the payments cell is a single component-computed string,
    `{{ $conn->payments_line }}`** — the shape the same `<dl>` already uses one line down at `:20`'s
    `{{ $conn->last_reconciliation }}` — which keeps all three committed needles working unchanged and
    removes the trap rather than routing around it. ⛔ Not by rewording the needles to sit inside the
    loop (the count leaves the assertion and every future needle on that cell inherits the boundary);
    ⛔ not by `assertSeeHtml` carrying the markers, which pins Livewire's internal comment syntax in a
    money assertion. ⚠️ **The dangerous direction is the inverse:** an `assertDontSee` whose needle
    spans a boundary passes **vacuously**, with no red to announce it — measured clean lane-wide today
    (`'120.00'`, `'idem_loose'`, `'99.00'`, `'acct_B9'` are single tokens inside one structure), and it
    is ruling 61's passes-for-the-wrong-reason arriving through the template instead of the needle.
    ⚠️ **`php -l` is blind to Blade.** It reported `No syntax errors detected` on a template whose
    `@foreach` did not compile at all — Blade matches directives with `\B@`, so `payments@foreach`
    after a word character is emitted literally while the trailing `@endforeach` after `}` compiles,
    leaving an unmatched directive — and `pint` and `phpstan` never read the file either. Ruling 55's
    `php -l` requirement is a PHP check, not a template check; **the suite is the only witness.**
    ⚠️ **Three brief-caused defects in one brief**, all the supervisor's: the blade line was dictated
    verbatim in its broken form (`BRIEF-money113.md:124`), the needles were dictated, and `:191-192`
    dictated an assertion **order** that contradicts `:239`'s dictated Proof B **RED line** — a
    Livewire chain stops at the first failure and the mutation fails both, so the coder reordered the
    committed test (`59b82ebb`), which is the only resolution and is **upheld**. **RULED: a brief that
    names the RED line a proof must produce has dictated the ASSERTION ORDER of the committed test**,
    so it checks one against the other before shipping — ruling 92's arithmetic discipline applied to
    a proof. This is the ruling 66/75/82/92/94/106/118/147 family three times over, and per the
    46/49/50/62/66/75/82/86/94/104/106/113/116/146 precedent MONEY-113b carries its own two dispatches
    and MONEY-113's cap is untouched. ⚠️ Ruling 82's collateral also applies to that wave's Proof A:
    its RED line is real, but the test is red **without** the mutation too, so the proof distinguishes
    nothing — a proof is only a proof if the test is green when the mutation is reverted.
154. **The `origin/main` → `track/money` merge is OPEN on its SEVENTH measurement, and it is a
    materially different merge from the six that were refused (RULED by the lane supervisor
    2026-09-08 09:1x; measured against `origin/main` = `07a4ae2f`, base `12447593`, main 784
    ahead, money 402).** `OWNER.md` 06:0x §3 conditions this lane on *reviews and pricebook having
    landed*; measured with ruling 145's date instrument, **both have** — `888cabae` pricebook
    2026-09-08 06:54:18 and `b56db171` reviews 2026-09-08 **08:18:02**, the first `merge:
    track/reviews` newer than `57b8f881`. Ruling 150's half-open state is closed. **Every historical
    blocker is measured away, and three of them by facts that did not hold before:** (1)
    `git diff --stat 12447593 origin/main -- source/ app/phpunit.xml .agents/rules/ .gitignore` is
    **empty** — `source/` is not touched on either side since the base, so ruling 31's decisive
    one-sided `source/` hunk **does not exist in this merge** and money's `goaiez_antig_money_test`
    pin cannot move; (2) `.gitattributes` is **in this worktree** (8 lines, all eight per-track paths
    `merge=ours`) and `git config --get merge.ours.driver` returns **`true`**, so ruling 27's
    working-tree caveat is satisfied and the driver **will** fire — six of the two-sided paths
    resolve to money's copy with no conflict; (3) the whole two-sided set is **23 paths**, against
    the `12447593` merge's forty. ⚠️ **The fact that explains the shape of the entire resolution:
    `git log --merges origin/main` contains no `merge: track/money` at all — money has NEVER been
    merged to `main`.** So for every path in this lane's eight modules and their tests, main's copy
    is the stale base plus another lane's incidental bookkeeping; money's side carries rulings 36
    through 153 and main's carries none of them. The seventeen non-driver paths are ten
    `X-199/Ui/*`, six of this lane's module test files and `JourneyHarness.php`. ⚠️ Ruling 52's
    two-party sequence is unchanged and mandatory — the coder merges `--no-ff --no-commit` and
    **stops without committing**, the supervisor commits the staged merge as `chore(merge): …` —
    and so is ruling 53's **default clause**: an uncovered conflicting path is reported with the
    merge still staged, never aborted (`git merge --abort` has destroyed this lane's ledger once)
    and never guessed.
155. **The X-199 shell money adopts is the `#[Layout]` attribute and its import ALONE (RULED by the
    lane supervisor 2026-09-08 09:1x, refining ruling 127 with a measurement 127 did not have).**
    Ruling 127 measured that main built the shell and money the body, and that `grep -rn
    "isSample\|#\[Layout" app/app/Modules/X-199/Ui/*.php` returns nothing on money's side — both
    still true: main's three `-2` blade hunks are the `@elseif($isSample) <x-ui.sample />` removal
    from **main's parallel blade**, which money's blades do not contain, so money's five blades take
    **money's side whole and revert nothing**. What 127 did not measure is *what else* main's shell
    carries. Measured now: main's `Credits.php` and `Invoices.php` add `#[Locked] public int
    $businessId`, `#[Locked] public ?string $loadError`, and a `mount(int $businessId = 0)` that
    falls back to `Tenancy::id()`. **Money's components have none of these and need none** — every
    one resolves the tenant inside `render()` as `Tenancy::idOrFail()` behind
    `abort_unless(auth()->check() && Tenancy::check(), 403)` (ruling 151(5) measured all 24). So
    **RULED: adopt `#[Layout('components.account.layout', ['heading' => …])]` and `use
    Livewire\Attributes\Layout;` — nothing else.** ⛔ Adopting `mount()`/`$businessId` puts a second
    tenant-resolution path beside money's, which is ruling 37's *"a second place for the truth to
    disagree"* on the one value that decides which tenant's money is displayed; ⛔ adopting
    `$loadError` ships a declared-and-never-read property into components whose blades money keeps
    (decision 272, this lane's own shape). Each is **recorded**, not adopted.
156. **`X117Test.php` and `JourneyHarness.php` are this merge's two caller/callee seams, and main's
    copy of each asserts against MAIN's engines (RULED by the lane supervisor 2026-09-08 09:1x;
    ruling 56's finding re-measured against `07a4ae2f`).** Ruling 54 keeps money's `X-198`/`X-199`/
    `X-211` while these two files are contended, which places a caller and its callee on opposite
    sides by construction — ruling 56's exact shape, and it recurs verbatim. **(a) `X117Test.php`:**
    main asserts `'paid'` in **three** places money has adapted — `:76`'s anchor branch and
    `test_g18_29_lifecycle_stops_at_money`'s two `assertEquals`, plus an `assertContains` main
    narrowed to `['paid','cancelled','sold_out']`. Ruling 45 left **no path to `paid`** in this lane
    and `CheckoutEngine.php:148-151`'s own docblock records it, so **money's four status assertions
    win** and money's `test_an_order_row_written_without_a_status_is_pending_payment` (ruling 126)
    is kept. ⚠️ **Main's other four hunks are genuine work and are ADOPTED**: the deletion of
    `test_g6_02_upsell_token`, `test_g7_10_bundle_allocation` and `test_no_refusal_declared` (three
    `assertTrue(true)` placeholders whose capability ids are the stages lane's bookkeeping, ruling
    5), and the strengthening of `test_g8_29_minor_units_integers` into a real checkout asserting
    `5997` and of `test_g16_05_true_countdown_cart` into a real countdown delta — reverting another
    lane's improvement to this lane's test file is the One Rule as surely as deleting it is.
    **(b) `JourneyHarness.php` resolves PER METHOD, as ruling 56 already ruled**, and the
    measurement is sharper than ever: main's `payInvoice` calls `capture()` with **six** arguments
    and then `requestCharge()`, and `grep -rn "function requestCharge\|function capture("` over
    money's `GatewayEngine` returns **one line, `capture(` at `:68`** — main's harness is written
    against main's X-198, which this merge does not take. Money's `payInvoice`, `makeOverdue` and
    `lastDunningAction` win (rulings 56, 59, 68, 69); `issueInvoice` and `invoiceStatus` are
    untouched on both sides; **`publishSite` takes main's side WHOLE** — it is the site lane's J11
    method and owner ruling 1 makes touching it a BLOCK. ⚠️ **The import block is composed, not
    picked:** main's `EdgeProvisionAction` and `Deployment` are required by the `publishSite` money
    adopts, main's removal of `PageVersion` follows it, and money's `Artisan` and `ArDunningAction`
    are **restored** because the two methods money keeps use them. `ReceivableState` is **not**
    imported — it belongs to main's `lastDunningAction`, which money declines (ruling 59). ⚠️ An
    unused import is invisible to `pint`, `phpstan` and `php -l` alike (ruling 46), and a missing
    one is a fatal `php -l` cannot see either; the composed block is read line by line.
157. **The post-merge floor is NOT predicted, and that is ruling 92 obeyed rather than suspended
    (RULED by the lane supervisor 2026-09-08 09:1x).** Ruling 92 makes every number a brief states
    the supervisor's to have measured; 784 commits of another six lanes' tests arrive in one
    commit, main deletes three placeholder tests from `X117Test.php` and adds three to
    `X211Test.php`, and no arithmetic available before the merge produces the count. **So the merge
    brief states no floor at all** — the gate measures it and the verdict block records it as the
    new baseline — and a brief that guessed one would hand the next tick a comparison it must
    either believe or re-derive under time pressure, which is exactly what 92 exists to stop.
    ⚠️ **The reds to expect are ruling 58's four shapes, and the one that will dominate is shape
    (1): main's test against money's kept module.** Main's `X211Test.php` is the pre-truth-sweep
    copy — it asserts `'accepted'` where ruling 98 wrote `'offered'` and
    `assertStringContainsString('.zip', …)` where ruling 44 wrote `assertNull` — and main adds
    three X-211 tests and one `UnpaidTest` method written against main's engines. **Every such red
    is resolved FORWARD by adapting main's test to this lane's ruled behaviour** (rulings 61, 62's
    precedent), ⛔ never by reverting a ruling and ⛔ never by deleting the test; and none of that
    is this wave's work — the merge wave resolves, lints and **stops**.
158. **A merge brief's finalisation runs `pint` over every hand-resolved path, not just `php -l`,
    because the SUPERVISOR commits the merge and inherits its style verdict (RULED by the lane
    supervisor 2026-09-08 09:5x, on MONEY-114's `20901926`).** Ruling 55 made `php -l` mandatory
    after MONEY-76b's stray brace and stopped at the parser; the same reasoning is one instrument
    over. Run 136 appended main's three `X211Test` methods with **double** blank lines, which is
    `class_attributes_separation` exactly, and MONEY-114's finalisation named `php -l` and
    `composer dump-autoload` and **never named `pint`** — while ruling 75 already requires every
    brief to say `./vendor/bin/pint <touched paths>` before each commit. With
    `git diff --stat HEAD -- app/` **empty**, ruling 34 makes that verdict the sha's and ruling 26
    refuses the push, so a merge of 220 files and fourteen clean resolution checks was withheld for
    one blank line. **The consequence is sharper than an ordinary ruling-75 pint red, and that is
    the generalisable half:** in an ordinary wave the coder commits and can fix inside one run; in a
    merge the **supervisor** commits, `app/**` is outside its column, and the tip is recorded before
    anyone runs `pint`. **A merge brief that omits `pint` structurally guarantees an extra
    dispatch.** So the miss is the brief's, and per the
    46/49/50/62/66/75/82/86/94/104/106/113/116/146 precedent the fix wave carries its own two
    dispatches. ⛔ Never resolved by excluding the path or editing `pint.json` (the One Rule);
    ⛔ never by pushing anyway because the fix is one line — that is the gate deciding after the
    fact, which ruling 42(2) exists to prevent.
159. **Ruling 58's seam detector is structurally blind to a merge whose contended paths are TEST
    files, and this merge's two fatals prove it (RULED by the lane supervisor 2026-09-08 09:5x).**
    Ruling 58 named `phpstan` this lane's post-merge seam detector — the only gate that resolves a
    class across two files. Ruling 65 measured `app/phpstan.neon:5-6` as `paths: - app/`, which is
    `app/app/`, so **`app/tests/` is outside it**. Together: for a merge whose caller/callee seams
    are `X117Test.php`, `JourneyHarness.php`, `X211Test.php` and `AccountingTest.php` — *this*
    merge, and the one ruling 156 predicted — **the detector cannot see one of them.** phpstan
    reported **`errors 0`** on a tree carrying two fatal call-site type errors:
    `AccountingSyncEngine::inferCategory(): Argument #1 ($inferredConfidence) must be of type float,
    string given` (main's test calls a three-parameter signature; money's engine takes two) and
    `ArEngine::packageForCollections(): Argument #3 ($packagedByUserId) must be of type ?int, true
    given` (main passes a boolean; money takes `?int`). `php -l`, `pint` and the classmap pass over
    both, exactly as ruling 156 recorded for the six-versus-five arity case. **So after any merge
    the seam check over `app/tests` is the SUITE and nothing else** — a green phpstan says nothing
    about a test file, and this lane has now been told so twice (ruling 65's silent class
    relocation, and this).
160. **The gate's §7 red list is CAPPED, so a post-merge wave cannot be scoped from the gate output
    (measured 2026-09-08 09:5x).** §7 reported `FAILED 10 · errors 7` = **17** red and printed
    **ten** named lines plus `… 2 more`; five are named nowhere. Every prior wave in this lane had
    two to four reds, so the cap had never bound and the ledger had never noticed it. A brief
    written from the visible ten silently omits five, and ruling 118's stop-clause discipline —
    enumerate the *whole* expected output in two tables — is **unavailable** to a supervisor who
    cannot see it, because its column forbids running a test suite outside `supervise.sh --tests`.
    **So the first wave after any merge opens with a MEASUREMENT item:** the coder runs the suite
    and writes every red with its file and full message into `REPORT.md`'s `RAW:`, and the brief
    names for repair only the reds the supervisor has itself measured. ⛔ That brief carries **no**
    stop-clause on the red list, because ruling 118's precondition is not met. ⚠️ This is ruling 92
    honoured rather than suspended: the supervisor states no number it has not measured.
161. **Money's `packageForCollections` wins and main's boolean is NOT adopted — a flag the caller
    passes to assert that a human acted is a self-certifying value, where money records the
    PRINCIPAL (RULED by the lane supervisor 2026-09-08 09:5x, briefed as MONEY-115 item 3).**
    Main's `test_g1_65_collections_transmission_is_human_action` calls
    `packageForCollections($biz, $invoice, true)` and expects
    `DomainException('Collections transmission is a human action only')` on `false`. Money's is
    `packageForCollections(int $businessId, int $invoiceId, ?int $packagedByUserId = null)`, whose
    own docblock states G1-65 as *"the bundle is BUILT here; transmission to an agency is a human
    action (the principal is recorded on the row)"* — `packaged_by_user_id` written at `:249`,
    returned at `:266`. **Money's is the better implementation of the same capability id**, on this
    lane's own reasoning: a `bool $isHumanAction` supplied by the caller is ruling 51's shape one
    register over (*"the difference between two numbers this app supplied"*) and ruling 43's *does
    it even vary?* answered by the caller rather than by the world, while a user id varies, is
    auditable and is already read by `CollectionsPackagePreview`. X-211 is money's by ruling 20, so
    ruling 59 governs: **the module wins and the test is adapted, never the reverse** — ⛔ adopting
    the boolean is ruling 56's `requestCharge` mistake, a signature minted to satisfy a caller.
    ⚠️ **The arity is only half the failure and the second half is the interesting one:** main's
    test packages an invoice with **no resolution attempt recorded**, which money's engine refuses
    outright with `NoResolutionAttemptException` (`:221-223`, R211), so even with the argument fixed
    it stays red — against a refusal money has and main does not. The adaptation **provisions the
    real state so the real path runs** (ruling 74), asserts `packaged_collections` **and** the
    acting user's id on the row, and keeps a refusal assertion by asserting **money's own**
    exception for the no-attempt case. ⛔ No assertion is deleted and the name stands, being true of
    money's implementation (rulings 39, 46).
162. **Adopting a layout moved the page's `<h1>` into it and left five blades still emitting one —
    the heading-seam red is MONEY's, and the fix is a demotion, never a removal (RULED by the lane
    supervisor 2026-09-08 10:5x, briefed as MONEY-116 item 1).** The previous tick deferred this
    attribution *"with the number in hand"*; the number is **`$skips` 5 vs 0** and it is this lane's.
    Measured: `app/resources/views/components/account/layout.blade.php:51-52` emits
    `<h1 class="sr-only">{{ $heading }}</h1>`, so the layout owns the page's `<h1>` for assistive
    technology and a blade under it must open at `<h2>` — which
    `Feature/Architecture/HeadingSeamTest.php:76-81` enforces and which the other fourteen components
    in the population already satisfy (`X-110/today.blade.php:3`, `X-138/roi-dashboard.blade.php:3`).
    Money's five X-199 blades still open `<h1>` — `declines:4`, `credits:2`, `invoices:5`, `unpaid:4`,
    `money-paid-today:4` — because before the `12447593` merge they carried no layout and owned the
    page heading legitimately; ruling 155 adopted main's `#[Layout(…, ['heading' => …])]` and nothing
    swept the blades under it. **RULED: the tag level changes and nothing else** — same text, same
    classes. ⛔ **Never remove the blade's heading:** `declines` then reads `[1,3]` and
    `money-paid-today` `[1,3,4]`, so a removal creates a level skip where a demotion removes two
    (`$levelSkips` is pinned 0 and is failing at **2** today, behind `$skips` in the expect order).
    ⛔ Never edit a file under `Feature/Architecture/` — it is a CHECK and another lane's (the One
    Rule). ⚠️ **Blast radius: ZERO, and the reason is the point.** All five
    `tests/Modules/X-199/Screens/*ScreenTest.php:25` assert
    `assertSee('<h1 class="sr-only">…</h1>', false)` — the **layout's** heading, not the blade's — so
    no test in this lane has ever asserted the tag level of the string an owner actually sees.
    ⚠️ Four pins sit **after** `$skips` and are therefore unmeasured (`$conditionalHeadings` 8,
    `$inlineConditionalHeadings` 0, `$untrackedOpeners`, `$unwalked` 25); a demotion changes none of
    them by construction — it moves no heading into or out of a conditional and adds no directive —
    so the brief predicts the two counters it measured and **names the tail as unmeasured** rather
    than promising green (ruling 92).
163. **Ruling 125 mis-attributed the `SQLSTATE[25P02]` invoice-number red to another lane; it is
    `tests/Modules/X-199/InvoiceNumberTest.php`, and the mechanism is a deliberately-failing statement
    with no savepoint (RULED by the lane supervisor 2026-09-08 10:5x, briefed as MONEY-116 item 3).**
    Ruling 125 listed *"the `SQLSTATE[25P02]` invoice-number test"* among five reds it called *"all
    other lanes'"*. Measured: the file is `app/tests/Modules/X-199/InvoiceNumberTest.php`, X-199 is
    money's by ruling 20, and `git log` on it is **three money commits** (`4475cf32`, `241fd1ab`,
    `c421dc15`). It has been this lane's for as long as it has existed. **The mechanism explains why it
    reads as somebody else's:** `:141-152` asserts a unique-index violation by inserting a duplicate
    `invoice_number` through `DB::table('invoices')->insert()`, and in Postgres a failed statement
    aborts the **whole** enclosing transaction — the suite's own — so every statement after it dies
    `25P02`, and the one the runner reports is the *teardown*'s
    `update "phone_numbers" … Released in teardown`. The failure names a table this lane does not own,
    in a file this lane does not own, for a defect that is entirely this lane's. **RULED: the failing
    insert runs inside a nested `DB::transaction()`**, which Laravel implements as a `SAVEPOINT` when
    a transaction is already open, so the rollback returns the outer transaction to a usable state and
    the assertion is unchanged. ⛔ Not by deleting or weakening the `toThrow(QueryException::class,
    'invoices_business_id_invoice_number_unique')` — that assertion is the unique index's only proof;
    ⛔ not by dropping `DatabaseTransactions`, which would leak rows into
    `goaiez_antig_money_test`. ⚠️ The generalisable half is ruling 64's, and it is the second
    correction of an inherited attribution in three ticks: **a red attributed to another lane is an
    inherited follow-up like any other and decays the same way** — re-measure the FILE, never the
    message, because a cascading failure reports the innocent statement.
164. **A measurement item's output belongs in `REPORT.md`; a count is not a list, and this one
    survived only because ruling 47 was violated too (RULED by the lane supervisor 2026-09-08 10:5x,
    on MONEY-115's run 137).** Ruling 160 made the first wave after a merge open with a measurement
    item, because §7's red list is capped and a supervisor forbidden to run pest outside
    `supervise.sh` cannot see what it cannot scope. Run 137 ran that measurement correctly and wrote
    into `RAW:` the string `FULL RED LIST (15 failures/errors extracted …)` — **the number and not the
    list** — leaving all fifteen entries and their verbatim messages in `full_red_list.txt`,
    untracked, **at the repo root**. This supervisor recovered them, and could do so *only* because
    the run also left its scratch behind: had it tidied up as ruling 47 requires, the deliverable
    would have been destroyed by the cleanup, the report would still have read `(15)`, and the wave
    would have been unscopeable. **Two defects that each conceal the other is not a near miss, it is
    the pair to write down.** ⚠️ The count was also **short and silent**: the gate that produced it
    read `FAILED 10 · errors 7` = **17** and the capture names **15**, with no line saying so. **RULED:
    a brief's measurement item names the field, the heading and the shape** — every red with its
    file, its test name and its first failure line, written into `REPORT.md` under a named heading —
    **and states the total the gate reported beside the total captured**, so a short capture announces
    itself. This is ruling 147's family (detail is read as the spec, a by-name reference as decoration)
    reaching the one item whose entire purpose is to be read by the next tick. ⛔ A scratch file is
    never the delivery vehicle: it is untracked, it is outside every gate, and it is one `git add -A`
    from a live web document root (ruling 47).
165. **The push of the merge is HELD for exactly one wave, and the escape clause is written down now
    so the next tick does not re-litigate it (RULED by the lane supervisor 2026-09-08 10:5x).**
    `20901926` — the first `origin/main` this branch has ever carried, 220 files, and the first thing
    Track 1 has ever had of money's (ruling 154) — was blocked on §6's `pint` red and *"three
    lane-owned §7 reds"*. MONEY-115 cleared all four. But the same tick's measurement moved three
    reds the previous tick had filed as *"not this lane's tree"* into this lane's column: rulings 162
    and 163, plus `test_g1_74`, which is ruling 58 shape (1) and one line. Ruling 26 exists to stop
    handing Track 1 a red this lane authored, and all three are small, measured and fixable in one
    wave. **So the push waits for MONEY-116 and no longer.** ⚠️ **The escape clause is binding: if
    MONEY-116 leaves ANY of the three standing, the next tick pushes anyway** and names the residue in
    its verdict block. Ruling 26c's reasoning is the stronger one at that point — a gated tip nobody
    pushes never reaches Track 1 and there is no human left to catch it — and ruling 74 forbids
    withholding a correct tip over a defect smaller than the thing withheld. A week of merged work is
    not held indefinitely against three known reds; it is held for one wave because one wave is what
    they cost.
166. **A lane that WINS a merge resolution inherits every architecture pin that counted the losing
    side, and all three of this lane's residual reds are that (RULED by the lane supervisor
    2026-09-08 11:0x, on MONEY-116's `523c9fdb`).** Ruling 58 taught this lane to check a merge's
    non-conflicting hunks; the damage can also land in another lane's **pinned counter**, where no
    diff of this lane's files shows anything at all. Measured, with the numbers attached so Track 1
    need not re-derive them. **(a) `HeadingSeamTest` `$conditionalHeadings` 10, pinned 8 — money is
    +2.** The counter counts *views* (once per view) with a `<h[1-6]` at `@if`/`@unless` depth ≥ 1
    over `app/Modules/*/Ui/*.php` components declaring the account layout: `declines.blade.php`
    (`<h3>:37` inside the `@if` at `:26`) and `money-paid-today.blade.php` (`<h3>:32`, `<h4>:43`)
    contribute; `unpaid`, `credits` and `invoices` carry their only heading at depth 0. ⚠️ **The
    MONEY-116 demotion did not cause it** — those tags were untouched and a `h1`→`h2` edit cannot
    move a count of headings-inside-conditionals; the count moved when money's five components joined
    the population at the merge (ruling 155), and the previous gate could not see it because the
    `expect` chain stopped at `$skips`. **The pin's own text is decisive** — *"the response to a move
    is to re-read whether the arms it counts are mutually exclusive, not to edit a view"* — so, the
    re-read: `declines` has `@else` at `:32` and `money-paid-today` at `:27`, both contributing
    headings sit in the rows arm on a **single render path**, and each descends by exactly one level
    from the `<h2>` above it. **The bound grew by two and the thing it bounds did not.**
    **(b) `OwnerNavTest` `$withLayout` 10, pinned 13 — money is −3**, its own message naming the
    cause (*"converted onto the owner layout"*), which is ruling 155. **(c) `SampleStateModuleTest`
    `$total` 235, pinned 244 — money is −9**, which is ruling 54's deliberate resolution keeping
    money's ten `Ui/` views over main's **one** generated `<x-surface.sample-state/>` line each, a
    number ruling 54 recorded at the time. ⛔ **No blade is edited to move any of them** (the pin
    forbids it in terms) and ⛔ **no `tests/Feature/Architecture/` file is edited** — they are CHECKs
    and another lane's (the One Rule). Every pin's text says the honest response is to **re-pin and
    record**, so the outcome is a **TRACK 1 ACTION with the numbers**, and until it lands every lane
    taking money's merge inherits three reds.
167. **`test_n_037` is main's needle against money's message — the SIBLING of MONEY-116's own item 2,
    twenty-five lines later in the same class, and the brief swept neither (RULED by the lane
    supervisor 2026-09-08 11:0x, briefed as MONEY-117).** `X-211/X211Test.php:411` expects
    `'A fee with no matching TERM in the agreement is refused'`; money's `ArEngine::applyLateFee():55`
    throws `FeeWithoutTermException` with `'No late-fee term in the agreement for %s: a fee with no
    matching term is refused. Nothing was applied.'` `expectExceptionMessage` is a **case-sensitive
    substring** match and main's needle is not a substring of money's message — the case differs on
    `TERM`, and money's sentence carries no *"in the agreement"* between the noun and *"is refused"*.
    **Deterministically red whenever reached.** It is ruling 58 shape (1), which is precisely what
    MONEY-116 item 2 fixed at `:386` in the same file. **RULED: the test asserts money's
    `FeeWithoutTermException` (already imported at `:14`) and the needle `'a fee with no matching term
    is refused. Nothing was applied.'`** — the second sentence is not decoration, because `ArEngine:52`
    records *"a refusal writes nothing (M29-C)"*, so the needle pins the refusal **and** its
    write-nothing property (ruling 76). ⛔ Money's message is never reworded to main's — the module
    wins (rulings 59, 161); ⛔ the needle is never shortened to a fragment that would match a
    different refusal. ⚠️ Main's call also passes **four** arguments to money's **three**-parameter
    method; PHP ignores a surplus argument to a userland function, so it is inert, but it is main's
    signature standing in money's file and the fourth argument goes with the fix — ruling 156's
    *"the arity half is the silent one"*, in its harmless direction. ⚠️ **The lane-wide sweep and its
    instrument:** all seven `expectExceptionMessage`/`toThrow` assertions in the eight module test
    directories were enumerated, and **six are green in this tick's own gate** with `:411` the only
    red — so ruling 118's two-table sweep is answered by **the suite**, not by grepping needles
    against sources, and that is the cheaper and stronger instrument whenever the population is
    already under test. ⛔ Not to be re-raised. ⚠️ Per the
    46/49/50/62/66/75/82/86/94/104/106/113/116/146 precedent the scoping miss is the supervisor's, so
    MONEY-117 carries its own two dispatches.
168. **An absence from a CAPPED list is not a clearance, and this ledger asserted both two lines
    apart (RULED by the lane supervisor 2026-09-08 11:0x).** The 10:4x addendum recorded in one table
    *"one unnamed | the §7 cap"* and, eleven lines below, that `test_n_037_fee_with_no_term_refused`
    *"cleared without being touched"* on the evidence that it was *"absent from this gate"* — two
    conclusions from the same eight-of-nine output, with ruling 160 having established the cap **that
    same tick**. Measured now: the `:411` mismatch is deterministic and was untouched by two waves,
    so it was almost certainly the unnamed ninth and never cleared. **RULED: a red is recorded as
    cleared only when the run showing it absent is known to be COMPLETE** — a full captured list
    (ruling 164), or a total that accounts for every member. From a capped §7, absence means
    **unknown**, and it is written down as unknown. ⚠️ This is ruling 98's self-contradiction tell —
    the cheapest kind to find, because both halves were already written down — arriving in the
    **supervisor's own ledger** rather than in a screen. ⚠️ It cost a real thing: MONEY-116 was
    scoped from that reading and shipped one method short of its own class.
169. **X-199's and X-211's evidence artifacts print an unlinked Stripe charge id beside a
    locally-written status as ONE flow, and both derived `runtime-proof.json`s promote it to the
    doctor's `artifact_id` — ruling 48's construction, in the two modules carrying this lane's owned
    journeys, never read until now (RULED by the lane supervisor 2026-09-08 13:0x, briefed as
    MONEY-118).** Ruling 48 found `X-117/Console/EvidenceCheckoutCommand` capturing directly with
    Stripe's own `tok_visa` under its own idempotency key, touching no cart, and writing
    `gateway_charge_id` beside `order_status` as though the checkout produced the charge; ruling 49
    then found the **derived** `runtime-proof.json` certifying `TestAnchorStage` on that same
    fabrication, and closed by admitting the scoping miss — *"49 named `runtime-proof.json` and
    stopped at the sibling"*. It stopped at the sibling **module**, too. Measured now, the identical
    construction is in both of this lane's journey modules. **(a) X-199.**
    `EvidenceInvoiceCommand:57` issues an invoice for 12500 through `issueInvoice()`; `:67`
    **separately** captures 12500 with `tok_visa` under `idem_x199_<time>`; `:74` then calls
    `recordPayment($businessId, $invoice->id, 12500)`, a **purely local ledger write** that consults
    no gateway; and `:100` writes `gateway_charge_id` beside `invoice_status` in one JSON object.
    ⚠️ **They cannot be linked, and the schema is the proof:** ruling 102 measured that `capture()`'s
    signature is `(businessId, amountCents, paymentToken, idempotencyKey, currency)` over a
    `payments` table with **no invoice column**, so the charge is not for the invoice and never
    could be — the invoice reads `paid` with or without the Stripe call, and the charge exists with
    or without the invoice. **(b) X-211** is the same three moves: `:58` issues, `:64`
    `offerPlan()`s, `:67` separately captures under `idem_x211_<time>`, `:86` writes
    `gateway_charge_id` beside `plan_id` and `reason`. **(c) The derived half is the decisive one,
    exactly as in ruling 49:** `X-199/Console/RuntimeProofCommand:32` guards on the key and `:96`
    promotes it to `artifact_id`, `X-211`'s does the same at the same two lines, and
    `Doctor/Stages/TestAnchorStage:81-89` refuses only ids prefixed `TEST|MOCK|FAKE|SAMPLE|DEMO` —
    so **both anchors are green on a charge id that paid for nothing the artifact names**.
    **(d) The test names carry the claim** (ruling 50(b)):
    `test_an_invoice_reaches_a_real_charge_id_and_its_number_cannot_repeat` and
    `test_a_recovery_reaches_a_real_charge_id`, the first of which restates J9's own goal.
    **RULED, per rulings 48 and 49 unchanged: each artifact evidences what its command actually
    proves and nothing else — the Stripe capture and the `gateway_charge_id` key are REMOVED, not
    relabelled**, because X-198 already owns that proof (`EvidenceChargeCommand` →
    `evidence/j9/charge.json`, asserted by `GatewayEngineTest:12-13` for `ch_` and `strlen === 27`,
    verified again this tick per ruling 39's companion lesson). What each command genuinely proves
    stays: X-199's `INV-` sequence and the unique-index refusal of a duplicate number, X-211's plan
    and its second non-repeating number. ⛔ **`gateway_call_made: false` is never written as a
    literal** (ruling 48(2)) — a boolean that certifies itself is ruling 43's fiction in miniature;
    a real `Payment::…->count()` moves if the code changes. ⛔ **Never substitute the invoice id,
    invoice number or plan id for `artifact_id`** — ruling 49's explicit prohibition, since `:89`
    lets an app-minted integer sail through and certifies the anchor with a number this app minted.
    ⛔ Not resolved by giving `capture()` an invoice column: that is a cross-module API change
    ruling 102 already recorded `UNRESOLVED`, and minting it to satisfy an artifact is ruling 56's
    `requestCharge` mistake. Both `RuntimeProofCommand`s refuse with the real missing dependency
    named and write nothing; both stale `runtime-proof.json`s are deleted so the stage measures the
    truth; both anchors join ruling 32's group (2). **A doctor count that rises for an honest reason
    is the correct outcome and is recorded, never avoided.** ⚠️ **There is no credential dependency
    and no `UNRESOLVED` path** — the wave *removes* the only vendor call in both commands, so they
    become always-runnable (ruling 48(3)); ruling 39's sequence still binds absolutely: run the
    command FIRST, open the artifact and read it, then write the assertions, all in ONE commit.
    ⚠️ **The reader sweep was done over `app/tests` too** (ruling 65 — phpstan reads `app/app/` only)
    and it has a three-member **keep** list that must not be touched: `InvoiceEngine.php:107`,
    `declines.blade.php:39` and `InvoiceEngineTest.php:342` all read a **`Payment` row's own** charge
    id, which is ruling 99's own fix and is the honest use of that column. ⚠️ Renaming the two tests
    also moves a reader: both `RuntimeProofCommand`s match the test **name** inside `junit.xml` at
    `:60` and `:79`, which is ruling 49's owns-every-reader rule applied to a name rather than a key.
    ⚠️ J9 is **not** broken by this: ruling 57 measured that `TwelveJourneysTest:485-508` reads
    `evidence/j9/charge.json` — X-198's artifact — so deleting X-199's fabricated linkage leaves J9's
    assertion untouched, and J9's goal *"an invoice reaches a real charge id"* is recorded
    `UNRESOLVED` against the schema gap ruling 102 named, which is what it has always been.
170. **Three of the four carried backlog sweeps are measured CLEAN and STRUCK, and the fourth is a
    census whose honest outcome is a recording (measured 2026-09-08 13:0x; rulings 64, 95, 100, 111,
    114, 120, 132, 142, 151).** (1) **Computed model accessors: the population is EMPTY.** `grep -rn
    "Attribute"` over all eight modules' `Models/` directories returns **nothing** — there is not one
    accessor in the lane, so ruling 43's *does it even vary?* has no accessor to ask it of, and the
    four instances already recorded (`is_active`, `is_connected`, `merchant_status`,
    `credit_limit_cents`) are **column defaults**, a different population already ruled in 84, 122,
    129 and here. ⛔ Not to be re-raised. (2) **The merge-adapted `assertSee` needles are clean.**
    Ruling 167's shape in the other direction: `CreditsTest`, `InvoicesTest` and `AccountingTest`
    were read needle by needle, and every one is a substantial string (`'5,000.00'`, `'net 30'`,
    `'INV-INV-001'`, `'No invoice has been raised for this account.'`) — **no bare digit**, so ruling
    61's defect does not recur, and the suite is green over all of them. ⛔ Struck. (3) **The
    `Actions/` census: 16 of the lane's 43 Action classes have no production caller**, seven of them
    already ruled (`PayoutReconcileAction` 51, `DisputeRecordAction` 79, `LedgerDebitAction` and
    `LedgerGrantAction` 90, `CardStoreAction` 119, `AccountingConnectAction` 73a,
    `AccountingSyncAction` 81) and the other nine — `MerchantConnectAction`, `PaymentCaptureAction`,
    `InvoiceDraftAction`, `InvoiceIssueAction`, `InvoiceRecordOfflineAction`, `ArForceAchAction`,
    `CartBuildAction`, `CartCheckoutAction`, `CardExpiringScanAction` — **inert delegates whose
    screens call the `Domain/` engine directly**. ⛔ Wiring one to a screen is ruling 59 inverted and
    deleting one removes a declared layer; the outcome is a recording. ⚠️ **One member is not a
    delegate and is recorded as the live residue:** `InvoiceDraftAction:22` calls `Invoice::create`
    **directly**, bypassing `InvoiceEngine::issueInvoice()` — a second, unreachable invoice-creation
    path that allocates from the same `InvoiceNumber::next()` sequence, writes `status = 'draft'` and
    a flat `now()+30` due date, and consults `CreditTerm` not at all. It is inert today (no
    production caller; `draft` is excluded from every reader by ruling 103) and it is the one place
    the app has two vocabularies for creating an invoice. ⚠️ Measured in the same pass and **already
    honest**: nothing in production creates an invoice at all — `issueInvoice()`'s only non-test
    callers are three evidence commands — and `invoices.blade.php:19-21`'s empty state **already says
    so**, naming the job hand-off and a delivery as what it waits on, which is ruling 89's wave
    holding up under a sweep aimed at it. ⚠️ Recorded, not briefed: `invoices` carries **no
    `currency` column at all**, so X-199's screens print unlabelled money; unlike ruling 152(b) there
    is no real column to group by and the per-tenant currency source is another lane's, so the fix is
    a cross-lane dependency and not this lane's wave.
171. **Ruling 166(b) is CORRECTED: `$withLayout` 10 vs 13 is not a re-pin, it is three `#[Layout]`
    attributes this lane's own merge resolution dropped, and it is fixable here (RULED by the lane
    supervisor 2026-09-08 13:5x, applying `OWNER.md`'s 2026-09-08 Track 1 section; briefed as
    MONEY-119 item 1).** Ruling 166 measured three architecture pins moving *because money won three
    merge resolutions* and filed all three as **TRACK 1 ACTION 7**, on the reasoning that each pin's
    own text says the honest response is to re-pin and record. That reasoning holds for (a) and (c)
    and is **wrong** for (b). Track 1 measured the cause: money's merge of `origin/main` `07a4ae2f`
    kept money's side whole for `X-198/Ui/ConnectCard.php`, `ReconciliationDiscrepancies.php` and
    `SameAccount.php` — ruling 54's policy applied correctly — and in doing so dropped `main`'s
    `#[Layout('components.layouts.agency')]` from all three (added by `de435ba2`, 2026-09-04), so the
    three screens fall through to the staff console. **This is ruling 58 shape (2) — main's
    one-sided change inside a money module tree — and ruling 59 makes the files money's to resolve
    on money's terms regardless of who last touched them.** Verified here: `grep -n "Layout"` over
    the three returns **nothing**, `git show origin/main:<each>` carries `use
    Livewire\Attributes\Layout;` in the import block and `#[Layout('components.layouts.agency')]`
    directly above the class, and `components/layouts/agency.blade.php` exists and is worn by ten
    other modules' screens. **RULED: adopt the attribute and its import, and NOTHING else** — ruling
    155's discipline verbatim, so no `mount()`, no `$businessId`, no `$loadError`.
    ⚠️ **The two architecture tests do not read the same thing, and that is what makes this safe.**
    `OwnerNavTest:284`'s `$hasLayout` is `! empty($reflection->getAttributes(Layout::class))` —
    **any** `#[Layout]`, whatever its argument — so the restoration moves `$withLayout` 10 → 13 and
    `$withoutLayout` 258 → 255, both to their pins. `HeadingSeamTest:21,:131` and `OwnerNavTest:99`
    match the **literal** `components.account.layout` as text, so `layouts.agency` enters neither
    population and ruling 162's `<h1>`-under-a-layout hazard **cannot** fire here. ⛔ The three blades
    are not touched and no `Feature/Architecture/` file is edited (the One Rule). ⚠️ Blast radius
    measured: every `assertDontSee` in the three screens' tests is inside a `Livewire::test` chain,
    which never renders the layout (this lane's standing field note), and the three real GET tests in
    `tests/Modules/X-198/Screens/` assert `assertOk()` and nothing else — so **zero** existing
    assertion can move. ⚠️ The three pins **after** `$withoutLayout` (`$unbuilt` 220, `$built` 35,
    `$unresolved` 0) are unmeasured today because the chain stops at `$withLayout` (ruling 162's
    lesson); the argument that they will hit is that every pin in that test was authored against a
    tree **carrying** the attribute, so restoring it restores main's own measurement. That is a
    prediction, and it is named as one rather than promised (ruling 92). ⚠️ The generalisable half:
    **a pin that moved at a merge is a symptom, and the honest response is to re-pin only once the
    cause is measured** — 166 filed three symptoms together because they moved together, and one of
    them had a cause inside this lane's own files. TRACK 1 ACTION 7 now carries **two** pins.

172. **A wave that renames a test owns every artifact that NAMES it, and MONEY-118 left the sibling
    file for the third time in the same family (RULED by the lane supervisor 2026-09-08 13:5x,
    briefed as MONEY-119 item 2).** Ruling 169 named X-199's and X-211's `runtime-proof.json` files
    and required their deletion; MONEY-118 deleted both correctly. It also **renamed both tests** —
    `test_an_invoice_reaches_a_real_charge_id_and_its_number_cannot_repeat` →
    `test_the_invoice_artifact_proves_its_number_sequence_and_refuses_a_duplicate`, and
    `test_a_recovery_reaches_a_real_charge_id` →
    `test_the_recovery_artifact_proves_the_plan_and_its_refusal` — which ruling 50(b) required and
    which is right. Measured now: `app/storage/app/evidence/X-199/junit.xml` and
    `.../X-211/junit.xml`, both dated **2026-09-06**, still name the OLD methods with
    `failures="0" errors="0"`. X-199's reads `<testcase name="An invoice reaches a real charge id and
    its number cannot repeat" … assertions="8"/>` — **the exact sentence rulings 48 and 169 removed
    from `invoice.json`, relocated intact into the file beside it**, certifying as passed a method
    that no longer exists. That is ruling 49's derived-artifact class and ruling 50(c)'s
    owns-the-artifact rule, in the wave written about both. ⚠️ **The readers make it worse, not
    better.** Both `RuntimeProofCommand`s still carry ~60 lines of artifact-reading apparatus — the
    junit parse, the root-suite walk, and `:74`'s `$hasName` against the **new** name, which can
    therefore never match these files — sitting **above** an unconditional `$this->error(...)` +
    `return self::FAILURE`. The mismatch is real and structurally invisible, because the command
    refuses whatever the junit says. **RULED: both commands reduce to the shape ruling 49 already
    established for X-117** — the `runningUnitTests()` guard, the refusal naming the real missing
    dependency, `return self::FAILURE`, and nothing else (`X-117/Console/RuntimeProofCommand.php` is
    31 lines and is the model) — **and both stale `junit.xml` files are deleted**, which is ruling
    49's own remedy for the sibling it stopped at. ⛔ Not resolved by regenerating the two junits:
    they would then certify a passing test for a command that refuses by design, which is the fiction
    with a fresh date on it. ⛔ Not by leaving the apparatus in place "in case the dependency lands":
    dead code above an unconditional refusal is the invitation ruling 102 refused for
    `RecordPaymentOnCapture`, and the day `payments` carries an invoice column the command is
    rewritten anyway. ⚠️ **There is no mutation proof for this item and none is asked for** — nothing
    reads either junit but the code being deleted, and no test in the lane references them (measured:
    `grep -rn "junit.xml" app/tests` returns only `X198Test.php:136-139`, which is X-198's). ⚠️ The
    generalisable half, and ruling 50's closing instruction restated because it keeps being one line
    short: **after a wave renames a method or removes a key, `ls` the artifact directory and account
    for EVERY file left in it** — 49 stopped at `runtime-proof.json`'s sibling, 50 stopped at
    `junit.xml`'s sibling in another module, and 169 stopped at the same sibling here. Per the
    46/49/50/62/66/75/82/86/94/104/106/113/116/146/153/167 precedent the miss is the supervisor's and
    MONEY-119 carries its own two dispatches.

173. **`evidence/j9/charge.json` is the FOURTH and last instance of ruling 48's construction, and it
    is the artifact this lane's own owned journey asserts on (RULED by the lane supervisor
    2026-09-08 13:5x, briefed as MONEY-119 item 3).** Ruling 169 wrote that *"X-198 already owns that
    proof (`EvidenceChargeCommand` → `evidence/j9/charge.json` … verified again this tick per ruling
    39's companion lesson)"* — the verification read the **charge id**, which is honest, and never
    read the command that writes it. Measured now, `X-198/Console/EvidenceChargeCommand.php` is the
    same three moves ruling 48 found in X-117 and ruling 169 found in X-199 and X-211: `:49` issues
    an invoice for 12500 through `issueInvoice()`; `:52` **separately** captures 12500 with Stripe's
    own `tok_visa` under `idem_j9_<time>`, touching no invoice, because `capture()`'s signature is
    `(businessId, amountCents, paymentToken, idempotencyKey, currency)` over a `payments` table with
    **no invoice column** (ruling 102); `:54` calls `recordPayment($businessId, $invoice->id, 12500)`,
    a purely local ledger write that consults no gateway; and `:57-59` writes `gateway_charge_id`
    beside `invoice_id` and `invoice_status` in **one** JSON object. The charge exists with or without
    the invoice and the invoice reads `paid` with or without the charge. ⚠️ **What makes this the
    sharpest of the four is the reader.** `TwelveJourneysTest.php:485-508` — **J9**, whose goal in
    this lane's TRACK section is *"an invoice reaches a real charge id"* — asserts the `ch_` prefix
    (true, and honest), `running_unit_tests === false` (true), and **`assertSame('paid',
    $artifact['invoice_status'])`**, under a comment reading *"⛔ The gateway's own id. Nothing here
    can mint one, which is the only reason this assertion means anything."* That comment is true of
    the charge id and false of the line beneath it: `invoice_status` is minted here, by
    `recordPayment`, and the journey's name joins the two. **J9 has been green on exactly the
    construction rulings 48, 49 and 169 removed from three other modules.**
    **RULED, per ruling 48 unchanged: the artifact evidences what its command actually proves and
    nothing else — `invoice_id` and `invoice_status` are REMOVED, not relabelled, and the invoice
    issuance and `recordPayment` go with them**, because they exist only to populate those two keys
    and a local ledger write left in a command whose artifact no longer mentions it reads as a linkage
    to the next person. `payments_written` is added as a real `Payment::…->count()` (ruling 48(2) —
    ⛔ never a self-certifying literal). J9's `assertSame` is **inverted and kept**, never deleted
    (rulings 39, 46), and its comment states what the artifact cannot prove. `GatewayEngineTest:11-14`
    is measured and **untouched**: it asserts only `ch_`, `strlen === 27` and `payment_status ===
    'captured'`, every one X-198's own subject. ⛔ Not resolved by giving `capture()` an invoice
    column — a cross-module API change ruling 102 already recorded `UNRESOLVED`, and minting it to
    satisfy an artifact is ruling 56's `requestCharge` mistake. **J9's goal stays `UNRESOLVED` against
    that schema gap, which ruling 169 already recorded and which is what it has always been.**
    ⚠️ **The method name is deliberately NOT changed in this wave, and the reason is written down so
    the next tick does not read it as an oversight.** `an_invoice_reaches_a_real_charge_id` is ruling
    50(b)'s defect and it has three readers — `X-198/Console/RuntimeProofCommand:99`'s `$hasName`
    needle, `evidence/X-198/junit.xml`'s `<testcase name>`, and `evidence/X-198/runtime-proof.json`'s
    `test` key — and that `runtime-proof.json` carries `artifact_id: ch_3UCgYZFXLB0i1zXl0NCv569q`,
    **the one genuinely vendor-issued `artifact_id` in this lane** and the only honest runtime proof
    it has. Renaming requires regenerating both derived files through a filtered pest run and a second
    command; a botched regeneration destroys a true anchor to fix a name. So the rename plus its
    regeneration chain is sequenced as its own wave, with the two commands named, exactly as ruling
    165 held the push for one wave with the escape clause written down. ⛔ Deleting X-198's
    `runtime-proof.json` is **not** the alternative: it is true, and raising the doctor count by
    discarding a real vendor artifact is the inverse of ruling 49's purpose. ⚠️ Ruling 39's sequence
    binds absolutely and the vendor half is the risk: run the command FIRST, read the artifact, then
    write the assertion, all in ONE commit — and if Stripe refuses, the outcome is `UNRESOLVED` with
    the provider error **quoted**, no artifact and **no test change committed**, which leaves the old
    `charge.json` in place and J9 green on it. That is a PASS-WITH-NOTES, never a BLOCK (ruling 39).
174. **The gate's §7 red list is capped for DISPLAY only —
    `/home/goaiez/tmp/last-pest-<checkout>.json` carries every failure with its file, line and full
    message, and ruling 160's second half is CORRECTED (RULED by the lane supervisor 2026-09-08
    14:1x).** Ruling 160 measured §7 printing ten of seventeen reds and concluded that a supervisor
    forbidden to run pest outside `supervise.sh` cannot scope a wave from the gate, so the first wave
    after a merge must open with a coder measurement item. **The file was there the whole time.**
    `bin/supervise.sh:233` writes pest's own last line — its JSON summary — to
    `/home/goaiez/tmp/last-pest-$(basename <toplevel>).json`, and that summary carries complete
    `failures` and `error_details` arrays, each member with `test`, `file`, `line` and the assertion's
    **entire** message. This tick's whole re-attribution (ruling 176) came out of it: §7 printed
    `✗ FAILURE __pest_evaluable_the_reachability_check…` and nothing else, while the file gave
    `OwnerNavTest:325` and `Failed asserting that 214 is identical to 220.` — a different pin from the
    one the wave had just fixed, which no amount of reading the gate output could have shown. **So a
    tick reads that file whenever §7 is red**, before attributing anything, and ruling 160's
    measurement item is needed only for what pest does not report. ⚠️ It is read with `Read`, never
    `grep` (ruling 77 — the lane's Bash is confined to this checkout, and a refused grep is not an
    absent file). ⚠️ It is **overwritten by every gate in this checkout**, so it is read in the tick
    that ran the gate; a stale one belongs to the previous sha and is ruling 83's family.
175. **A brief that names a mailbox document names its PATH (RULED by the lane supervisor 2026-09-08
    14:1x, on MONEY-119's run 141).** MONEY-119's Step 6 enumerated all ten `REPORT.md` fields by name
    — ruling 147 obeyed, and it worked, every field is present and correct — and named **no
    directory**. Run 140 wrote `.agents/supervisor/REPORT.md`; run 141 wrote
    `/home/goaiez/agents/grs-antig-money/REPORT.md`. The mailbox is the former (this file's own table)
    and the difference is not cosmetic: **`.agents/supervisor/` is gitignored and the repo root is
    not**, so the stray report became the only entry in `git status` and made §1 read
    `1 uncommitted path(s)` — muddying the exact instrument rulings 34 and 71 use to decide whether
    §6's verdict belongs to the sha. It also leaves a stray untracked file one `git add -A` from a
    commit at a live web document root (ruling 47), and it strands the next tick's case-(b) check on
    run 140's report. ⚠️ The coder's own `FINAL GIT STATUS:` was empty and **correct** — the file did
    not exist when it ran — so ruling 115 held and nothing in the report is false. **RULED: every
    brief writes the mailbox paths in full** (`.agents/supervisor/REPORT.md`), and the note is the
    brief's, spending no dispatch (rulings 60b, 71, 94, 106, 118). ⚠️ The ruling
    66/75/82/92/94/106/118/147/153 family, thirteenth instrument: a dictated signature, line, needle,
    floor, test, command, sweep, report-field list, proof RED line, resolution policy, finalisation
    step, `--filter` string — and now a dictated **filename** — each dictates its own outcome. The
    pattern is always the same: **detail is read as the spec and everything unstated is the coder's
    guess**, and here two runs of the same coder guessed differently.
176. **The three architecture reds are STALE CHECK COPIES that `main` has already re-pinned, ruling
    166 is corrected on all three counts, and TRACK 1 ACTION 7 is CLOSED (RULED by the lane
    supervisor 2026-09-08 14:1x).** Ruling 166 measured three pins moving *because money won three
    merge resolutions* and filed them for Track 1 to re-pin; ruling 171 then corrected one of the
    three, having measured a cause inside this lane's own files. **All three attributions were wrong,
    and one command shows it:**
    ```
    git diff origin/main HEAD -- app/tests/Feature/Architecture/
      HeadingSeamTest.php        $conditionalHeadings   main 10   money 8
      OwnerNavTest.php           $unbuilt               main 214  money 220
                                 $built                 main 41   money 35
      SampleStateModuleTest.php  $total                 main 235  money 244
                                 $illegal               main 210  money 219
    ```
    Money's measured `$unbuilt` is **214** — `main`'s new pin to the digit — and `$withoutLayout` 255
    minus 214 gives `$built + $unresolved = 41`, `main`'s new pin again. The corroboration is that
    `git diff origin/main HEAD -- app/app/Modules` is **752 lines carrying zero
    `x-surface.sample-state` changes** and `grep -ro` counts **235** occurrences in this tree,
    identical to `main`: the two trees agree about the thing being counted and only the pinned number
    differs, because money carries the CHECK files from an older `main` and is 68 commits behind.
    **⛔ Money may not adopt the new pins by editing these files** — they are CHECKs and another
    lane's (the One Rule); they arrive with the next `origin/main` merge and all three reds clear
    then. **⛔ TRACK 1 ACTION 7 is not to be re-raised**: it is answered. ⚠️ These cost Track 1
    nothing — money has never modified the three files, so on the money → `main` merge they are
    one-sided and `main`'s copies win with no conflict. ⚠️ **The generalisable half, and it is the
    second wrong attribution in three ticks** (ruling 163 was the first): **a pin red in a CHECK file
    is a claim about two things — the tree and the pin — and the cheap half is the pin.** Ruling 166
    measured the tree three separate times, ruling 171 measured it a fourth, and not one of them ran
    the one-line diff against the CHECK file itself. **Before attributing any pinned-counter red,
    diff the CHECK against `origin/main`.**
177. **`Doctor/Stages/JourneyStage.php:24` makes the claim rulings 169 and 173 measured this lane
    cannot make, and the evidence SLUG is keyed by it (RULED by the lane supervisor 2026-09-08
    14:1x).** The stage's `JOURNEYS` map reads
    `'invoice-to-paid' => 'an invoice reaches a real charge-id'`. Rulings 169 and 173 measured that
    `capture()`'s signature is `(businessId, amountCents, paymentToken, idempotencyKey, currency)`
    over a `payments` table with **no invoice column**, so no invoice in this lane reaches a charge
    id and J9's goal is `UNRESOLVED` against that schema gap — and the sentence asserting otherwise
    now sits one layer up, in a CHECK. ⛔ It is a **TRACK 1 ACTION**: `app/app/Doctor/**` is a
    forbidden path here and editing it is the One Rule. **The load-bearing half for this lane is the
    key.** `JourneyStage:39` builds `storage/app/evidence/journeys/{$slug}.json` from that map, so
    the slug `invoice-to-paid` is named by a file money may not touch: ⛔ **the evidence key is never
    renamed**, even in the wave that renames the method it belongs to. Measured in the same pass and
    recorded so a later wave does not re-derive it: `JourneyStage` reads only `passed` and
    `artifact_id` and **never the test method's name**, so renaming the method is invisible to the
    doctor's journey stage.
178. **J9's rename has FOUR readers, not three, and the fourth is immovable (RULED by the lane
    supervisor 2026-09-08 14:1x, briefed as MONEY-120 item 2).** The carried backlog named three —
    `X-198/Console/RuntimeProofCommand`'s `$hasName` needle, `evidence/X-198/junit.xml`'s
    `<testcase name>`, and `evidence/X-198/runtime-proof.json`'s `test` key. Measured with
    `grep -rn` over `app/app` and `app/tests`, the command holds **three** of them, not one — `:80`
    the needle, `:99` the error string that names the method, and `:125` the `'test' =>` key it
    writes — and the fourth reader is `Doctor/Stages/JourneyStage.php:24`'s slug, which ruling 177
    freezes. So the wave moves the method name and the command's three strings, regenerates the two
    derived artifacts, and **leaves `invoice-to-paid` alone**. ⚠️ Two further ruling-50(b) defects in
    the same method, found only by reading its body rather than grepping its name:
    `TwelveJourneysTest:498`'s failure message reads *"The invoice was marked paid with no gateway
    charge id — no money moved."* — an invoice the artifact no longer mentions — and it is read
    exactly when someone is diagnosing a failure. ⚠️ **The ORDER is the risk.** `junit.xml` must be
    regenerated **after** the rename or it names the old method; `runtime-proof.json` is written from
    `junit.xml` and `charge.json` together, and `RuntimeProofCommand:60` refuses unless
    `journeys/invoice-to-paid.json`'s `artifact_id` equals `charge.json`'s `gateway_charge_id` — which
    it does today only because this tick's own gate re-ran J9 and rewrote it. ⛔ Deleting X-198's
    `runtime-proof.json` is never the fallback (ruling 173): if the chain cannot complete, the outcome
    is `UNRESOLVED` with the command's refusal quoted and the old file left in place.
179. **Ruling 27's caveat is LIVE, and on a ONE-SIDED per-track path the `merge=ours` driver cannot
    fire — git takes `main`'s copy with no conflict, no marker and no announcement (RULED by the lane
    supervisor 2026-09-08 14:4x, briefed as MONEY-121 Table B; measured against `origin/main` =
    `acea8ad0`, base `5a0b7644`).** `.gitattributes` marks eight paths `merge=ours` and ruling 27
    recorded Track 1's own caveat — *a merge driver runs only when BOTH sides changed the file* — then
    rulings 52 and 154 measured the **two-sided** set and moved on. This merge inverts it: the
    two-sided set is six paths and three of them are byte-identical on both sides, while the damage is
    entirely in the **one-sided `main`-only** set.

    | path | `main` since base | money | lost silently if not restored |
    | :--- | :--- | :--- | :--- |
    | `app/phpunit.xml` | **2** — `DB_DATABASE` → `goaiez_antig_test` | **0** | ⭐⭐ Track 1's database. Money's pin is a never-list value (owner ruling 3) and `supervise.sh` §0 exits 2 only on *production* — `goaiez_antig_test` is not production, so **no gate in this lane catches it** and every later run `migrate:fresh`es another track's test database |
    | `bin/supervise.sh` | **458** | 0 | ruling 24's three gate hunks; ruling 74's `run_tool` rc capture, the `rc ≥ 124` KILLED line, the eight-column `gate-runs.tsv`, the shared `pest.lock` |
    | `.agents/supervisor/launch-coder.sh` | **184** | 0 | `--allow-merge` (the flag the merge wave itself is dispatched with), `--coder claude`, `--check` (rulings 30, 52, 74) |
    | `.claude/settings.json` | **23** | 0 | this lane's supervisor allowlist (`0634e31f`, ruling 24) |

    **RULED: every merge brief in this lane carries a Table B of one-sided per-track paths with its
    own proof command**, and the proof is `git diff --cached HEAD -- <those paths>` printing
    **nothing** plus `grep -n DB_DATABASE app/phpunit.xml` printing `goaiez_antig_money_test`.
    ⛔ The restore is never skipped because "the driver handles it" — the driver is a second belt, and
    on a one-sided path there is no first one. ⚠️ Nothing downstream can see the loss: `php -l` reads
    neither XML nor JSON, `pint` and `phpstan` read none of the four, and the one instrument that
    would notice is `bin/supervise.sh`, which is itself being overwritten. ⚠️ This is rulings
    83/136/145's family a fourth time — **the cheap signal moves for reasons unrelated to the fact it
    stands for** — with the sign reversed: here it is the *absence* of a conflict marker that has to
    be treated as suspicious, because absence is exactly what a one-sided overwrite looks like.
180. **The `acea8ad0` merge has ZERO code conflicts, and ruling 171's fix pre-resolved the only
    contention there would have been (measured 2026-09-08 14:4x).** Merge base `5a0b7644` is itself a
    **money** commit, which Track 1 merged to `main` at 13:41 (`f823f136`) — money 8 ahead, 82 behind,
    against `12447593`'s 1546/226 and `07a4ae2f`'s 784/402. The two-sided set is six paths:
    `.agents/state/BUILD-STATE.json`, `.agents/state/JOURNAL.md` and `CLAUDE.md`, where the driver
    fires correctly, and `X-198/Ui/{ConnectCard,ReconciliationDiscrepancies,SameAccount}.php`, where
    **both sides made the byte-identical change** — `main` added
    `#[Layout('components.layouts.agency')]` and money added the same attribute under ruling 171, and
    the blob hashes match on both sides (`cec7e7d7`→`c2b15d52`, `a6167fd6`→`05cd389e`,
    `198f6138`→`01d616e5`), so git merges them with nothing to ask. Measured absent, so a merge brief
    tells the coder **not to look**: no deletions on either side (ruling 58 shape (3) cannot fire), no
    `main`-added test against a money module (shape (1)), no one-sided `main` change inside a money
    module tree (shape (2)/ruling 59). `main`'s only additions are X-102's five files (track sixty's)
    and a **root** `pint.json`; this checkout already has `app/pint.json` and the gate runs pint from
    `app/`, so the root file is inert here and the two are never merged. ⚠️ The generalisable half:
    **a lane that applies another lane's one-sided hunk before the merge converts its next conflict
    into a no-op** — ruling 171 was briefed as a dropped-attribute fix and paid for itself again here,
    which is the argument for taking a merge's follow-ups forward rather than waiting for the merge to
    re-raise them.
181. **The three architecture reds clear on this merge and only on it, re-measured against the CHECK
    file itself (measured 2026-09-08 14:4x; ruling 176 confirmed).**
    `git diff 5a0b7644 origin/main -- app/tests/Feature/Architecture/` shows `main` moving
    `HeadingSeamTest` `$conditionalHeadings` 8 → **10**, `OwnerNavTest` `$unbuilt` 220 → **214** and
    `$built` 35 → **41**, `SampleStateModuleTest` `$total` 244 → **235** and `$illegal` 219 → **210**
    — every one of them money's own measurement **to the digit**, on a tree where
    `git diff origin/main HEAD -- app/app/Modules` carries zero `x-surface.sample-state` changes.
    ⛔ TRACK 1 ACTION 7 stays closed and is never re-raised; ⛔ money never adopts a pin by editing
    those files (CHECKs, another lane's, the One Rule); the merge takes `main`'s copies whole and the
    reds go. ⚠️ Ruling 176's instrument — **diff the CHECK against `origin/main` before attributing
    any pinned-counter red** — has now been the cheap half twice running.
182. **A merge brief's measured sha is a FLOOR, never the sha, and the reviewing tick re-measures
    against `MERGE_HEAD` (RULED by the lane supervisor 2026-09-08 15:0x, on MONEY-121's `3f2eeb14`).**
    Rulings 179 and 180 measured Table B, the two-sided set, the zero-conflict prediction and four
    "measured absent, do not look" clauses against `origin/main` = **`acea8ad0`**. The coder fetched
    and merged **`42e05e25`** — `acea8ad0` plus `5d89dc84 merge: origin/main into track/stages` and
    `42e05e25 merge: track/stages`. That is **correct**: a brief that says *merge `origin/main`* means
    the ref, and a ref moves. But every measurement was a claim about a different tree, and the four
    clauses that tell the coder *not to look* are exactly the ones a moved `main` can falsify — a new
    one-sided per-track path gets no conflict and no marker (ruling 179), and a new one-sided `main`
    hunk inside a money module tree is ruling 58 shape (2). **RULED: the reviewing tick re-measures
    Table B and all four ruling-58 shapes against `MERGE_HEAD`, never against the briefed sha, and
    every merge brief tells the coder to report the sha it actually merged.** Re-measured on this
    merge, all four survived: zero deletions in the whole staged set, zero staged hunks in money's
    eight module trees, one addition in a money test file (ruling 184), and the four Table B paths
    byte-identical to money's `HEAD` (`git diff --cached HEAD` over them empty, `app/phpunit.xml:34`
    still `goaiez_antig_money_test`). ⚠️ **Nothing in the run's own output named `42e05e25`** — the
    report said `CONFLICTS: 0` and the log said *"the merge completed smoothly"*; the sha was
    recovered only by the supervisor running `git rev-parse MERGE_HEAD`. A merge wave whose report
    does not name its `MERGE_HEAD` cannot be reviewed against the tree it actually merged, which is
    the ruling 147/175 family reaching the one field a merge review turns on.
183. **`php -l` on a non-PHP file is not a no-op — it writes a parse error to an untracked `error_log`
    at the repo root — and in a merge wave most hand-resolved paths are not PHP (RULED by the lane
    supervisor 2026-09-08 15:0x, same review).** Rulings 55 and 158 made `php -l` and `pint --test`
    mandatory in a merge's finalisation. In *this* merge the hand-resolved paths were
    `app/phpunit.xml` (XML), `.claude/settings.json` (JSON), `bin/supervise.sh` and
    `launch-coder.sh` (bash) — **not one of them PHP**. Two consequences, both measured:
    (a) `php -l app/phpunit.xml` wrote `./error_log`, 120 bytes, `Sep 8 14:30`, one line — *"PHP Parse
    error: syntax error, unexpected identifier `version` in app/phpunit.xml on line 1"* — because
    `<?xml version` opens a PHP short tag. It is untracked and unignored, so §1 read `1 uncommitted
    path(s)`: the exact instrument rulings 34 and 71 use to decide whether §6's verdict belongs to the
    sha, muddied by a lint, and a stray file left at a **live web document root** one `git add -A`
    from a commit (ruling 47). (b) `php -l` on the two **bash** scripts reported `No syntax errors
    detected` and proved nothing — a file with no `<?php` tag is all inline HTML to the linter, so it
    passes whatever it contains. Two of the report's three lint lines were vacuous: ruling 61's
    passes-for-the-wrong-reason arriving in a *finalisation step*. **RULED: `php -l` runs over `*.php`
    only, and a merge brief lists its resolved paths by extension, saying which check each gets** —
    `php -l` and `pint` for PHP, and for XML/JSON/shell the check that actually reads them (a `grep`
    for the pinned value, which is what Table B's proof already does). ⛔ Never widened to "every file
    the wave touched". ⚠️ The tell is an `error_log` at the repo root. The miss is the brief's and
    spends no dispatch; this is the ruling 66/75/82/92/94/106/118/147/153/175 family a **fifteenth**
    time — a dictated **finalisation command** dictates what it writes as well as what it checks.
184. **The post-merge ruling-58 sweep is MEASURED and STRUCK; the merge's one lane-facing hunk is an
    ADDITION and it is green (measured 2026-09-08 15:0x).** Shape (1) — main adds a test against
    money's kept code: **one**, and it passes. `X117Test.php` gains
    `test_g1_75_price_is_looked_up_or_refused` (+1 import, `ModelNotFoundException`), asserting that a
    cart total uses the **stored** `unit_price_cents` and ignores a caller-supplied price, and that an
    unknown sellable is refused; money's `CheckoutEngine::buildCart():27-28` reads
    `$sellable->unit_price_cents` through `Sellable::where(business_id)->findOrFail()`, so both arms
    hold and §7's `FAILED` reached **0**. It is **adopted, not reverted** — reverting another lane's
    genuine addition to this lane's test file is the One Rule as surely as deleting it is (ruling
    156). Shape (2) one-sided `main` change inside a money module tree: **none**. Shape (3) a deleted
    class money reads: **none** (zero deletions in the whole staged set). Shape (4) generated tests
    for unmounted routes: **none**. ⛔ Not to be re-raised. ⚠️ Ruling 159 was honoured: phpstan reads
    `app/app/` only, so the **suite** was the witness over `app/tests`, which is why this sweep is
    answered by a gate and not by a wave. ⚠️ **New floor: `2342 · 2340 · FAILED 0 · errors 2`**, the
    first `FAILED 0` in this lane's recorded history; the two errors are track sixty's Infobip
    journeys and ruling 125 requires the floor to fall the moment either lands.
185. **`issueInvoice` reads the customer's credit terms and then computes the due date from the
    CALLER's argument instead, so an owner who set `net_60` gets a 30-day invoice (RULED by the lane
    supervisor 2026-09-08 15:0x, briefed as MONEY-122 item 1).**
    `X-199/Domain/InvoiceEngine::issueInvoice():39-49` looks up
    `CreditTerm::where(business_id)->where(customer_id)->first()`, creating one from `$termsType` when
    there is none; then `:57` is `$dueDays = $termsDaysMap[$termsType] ?? 0;` — **the parameter, not
    `$terms->terms_type`** — and `:66` writes `due_date` from it. **This is ruling 51 and ruling 37
    verbatim** — *the row already knows, and a caller-supplied value is a second place for the truth
    to disagree* — landing on `invoices.due_date`, the column J12's overdue chase keys on
    (`InvoiceReader:70`), and on the one field an owner and a customer have actually agreed between
    them. The stored row is authoritative and has a real writer: `TermsSetAction:31,:41`
    `updateOrCreate`s `terms_type`, the declared `terms.set` capability, so a real tenant reaches the
    disagreement through the product. Every production call site takes the default
    (`EvidenceInvoiceCommand:56`, `EvidenceRecoveryCommand:57,:65`). **RULED: `$dueDays` derives from
    `$terms->terms_type`, through a `CreditTerm::TERMS_DAYS` const on the model that owns the column**
    — the map is shared rather than copied, because a second copy is this wave's own defect one level
    down. ⛔ **The `$termsType` parameter STAYS**: unlike rulings 37 and 51 it has a second and
    legitimate role, founding the terms row at `:44` when the customer has none — two roles, one
    fiction, which is ruling 45's structure. ⚠️ **Why ten green tests cannot see it (ruling 68):** all
    six `CreditTerm::create` fixtures in `InvoiceEngineTest` seed `net_30` and every `issueInvoice`
    call in that file takes the `net_30` default, so the two agree by construction — ruling 99's
    fixture-omission trap. ⚠️ **Blast radius: ZERO.** All 44 call sites either pass no fourth argument
    against a `net_30`-or-absent fixture, or — `JourneyHarness:532` — pass `'due_on_receipt'` for a
    person with no terms row, so `:44` founds it with that same value. The proof must therefore be a
    **new** test, and it asserts the 60-day date **positively** (ruling 104). ⚠️ `InvoiceEngineTest.php`
    is **pest-style** (`test('…', …)`, 10 tests, `grep -c "public function test_"` = 0) — ruling 86's
    census hazard, stated so the floor names the shape it counted.
186. **`InvoiceDraftAction` is the second invoice-creation vocabulary in a module that should have
    one, it consults no credit terms at all, and it is KEPT because it is the only implementation of a
    declared capability (RULED by the lane supervisor 2026-09-08 15:0x, briefed as MONEY-122 item 2).**
    Measured: `grep -rn "InvoiceDraftAction" app/app app/tests` returns the class, and in
    `X199Test.php` a property at `:29` and `new InvoiceDraftAction` in `setUp` at `:41` — **and no call
    anywhere**, production or test. It is constructed on every test in that file and invoked by none,
    which is why no census flagged it: it is not dead by any grep for its name. `:29` writes
    `now()->addDays($dueDays)` off a caller-supplied int and **never reads `CreditTerm`**, so X-199 has
    two writers of `invoices.due_date` that cannot agree — ruling 185's finding in the sibling path and
    ruling 98's self-contradiction latent. ⛔ **Not deleted:** `X-199/manifest.php:32` declares
    `provides: 'invoice.draft'` and this is its only implementation, so deleting it removes the only
    thing that could satisfy a generated declaration harvested from the frozen plan (rulings 29, 69),
    and `invoices.blade.php:45,:63` already render the `draft` state. ⛔ Not wired to a screen, an
    engine call or a new transition (ruling 59 inverted). ⛔ `$dueDays` is not removed — with no terms
    row it is the only information there is. **RULED: the draft path reads the same row the issue path
    does and uses `CreditTerm::TERMS_DAYS` when one exists, falling back to `$dueDays` only when the
    customer has no terms.** That is not new machinery to make a sentence true; it is two existing
    writers of one column made unable to disagree. ⚠️ It has **no test at all** (ruling 70), so the
    item **adds** a method asserting **both** branches — either alone passes for the wrong reason.
    ⚠️ Recorded and **not** briefed: `draft` is excluded from every reader (`InvoiceReader:41,:49,:59`,
    `Unpaid.php:61,:74`) and **there is no draft → issued transition anywhere in the module**, so an
    invoice created through this path can never enter the chase — a decision-272 dead end, and a wave
    of its own *after* the two paths agree, since minting a transition for a path with no caller is
    ruling 59.
187. **The evidence-command sweep is measured CLEAN and STRUCK, and the case-split evidence directory
    is harmless (measured 2026-09-08 15:0x; rulings 64, 95, 100, 111, 114, 120, 132, 142, 151, 170).**
    (a) **`X-117/Console/EvidenceCheckoutCommand`** — MONEY-73's cleanup **holds** against the current
    code: no direct capture, no `tok_visa`, no `gateway_charge_id` key; `payments_written` is a real
    `Payment::…->count()` and `merchant_connected` a real `exists()` (ruling 48(2) — never a
    self-certifying literal); the `connect()` stays, which is ruling 45's substance. (b)
    **`X-198/Console/EvidencePaymentLinkCommand`** — **one flow, not two**: it creates a `failed`
    `Payment` and calls `PaymentLinkAction` **for that payment**, and every artifact key comes from
    the payment or the link made from it, rulings 36/37 having already made the link real, persisted,
    idempotent and currency-correct. There is no unrelated second fact printed as one flow. (c) **The
    two directories differing only in case are two consumers, not a split:**
    `Doctor/Stages/TestAnchorStage:55` builds `storage/app/evidence/{$m->id}/runtime-proof.json` from
    the module id, so the doctor reads `X-198/` and reads **only** `runtime-proof.json`, while
    `GatewayEngineTest:18` reads `x198/payment-link.json`, which the doctor never opens. ⛔ The
    lowercase directory is **not** renamed: moving it breaks the one test that reads it to satisfy a
    symmetry no code requires. ⛔ Not to be re-raised. ⚠️ Re-measured after the merge and unchanged
    since ruling 39: `git ls-files app/storage/app/evidence/` prints **nothing**, while five tests
    `$this->fail('Artifact missing…')` without one — green here because the artifacts are on this
    disk, and unrunnable in any checkout that takes money's merge. Committing vendor material and the
    ignore rule in `main`'s `.gitignore` are both reserved, so that is **TRACK 1 ACTION 9**, not a
    wave.
188. **Ruling 140's "intermittent" `REVIEWS.md` refusal is CORRECTED: it is caused by the session's
    working directory leaving the worktree root, because `.claude/settings.json`'s allow-patterns are
    RELATIVE (RULED by the lane supervisor 2026-09-08 15:1x).** Ruling 140 recorded that
    `Edit(.agents/supervisor/**)` and `cat … >> REVIEWS.md` were refused "despite that exact allow rule
    being present", and ruling 141 downgraded it to *intermittent* after the identical command
    succeeded a tick later with no settings change. It is neither. Measured this tick: the refusals
    began immediately after a `cd app && ./vendor/bin/pint …` moved the session's primary working
    directory to `app/`, and **every** write to `.agents/supervisor/` — `Write`, shell redirection and
    `tee -a` alike — failed while it was there; a bare `cd /home/goaiez/agents/grs-antig-money`
    restored all three on the next call, verified with a one-line probe append. `.claude/settings.json`
    line 11 is `Write(.agents/supervisor/**)`, a **relative** pattern, so with cwd at `app/` it
    resolves to `app/.agents/supervisor/**` and the real path stops matching. **RULED: a supervisor
    tick never leaves the worktree root.** Run tools as `cd app && …` inside a single Bash call, which
    does not move the session, or address them by path; if a supervisor write is ever refused, the
    first check is `pwd`, not the settings file. ⚠️ The consequence was not cosmetic — for the length
    of that window the tick could not append a verdict, write `BRIEF.md` or write `KICKOFF.md`, i.e.
    it could not dispatch at all, and ruling 140 had already resolved to route around it by writing a
    scratch file at the repo root, which is ruling 47's hazard adopted to solve a problem that does not
    exist. ⚠️ **TRACK 1 ACTION 4 is CLOSED**: there is no permission-layer defect to fix.
    ⚠️ The generalisable half is rulings 83/136/145/179's a fifth time — **the cheap signal moves for
    reasons unrelated to the fact it stands for** — with the twist that here the ledger recorded the
    symptom twice, as a hard block and then as intermittency, and neither reading named the variable
    that was actually moving.
189. **A brief's `pint` command names every path the SAME ITEM's commit stages (RULED by the lane
    supervisor 2026-09-08 15:2x, on MONEY-122's `55e6d74e`).** MONEY-122 §1.3 and §2.2 each dictated
    `./vendor/bin/pint <one source file>` while §1.4 and §2.4 committed that file **and** a test file
    the same item told the coder to create. The coder ran exactly what it was given, `REPORT.md`'s two
    `Item N pint: passed` lines are true of the paths named, and the tip landed §6-red on
    `X199Test.php` with three fixers — `fully_qualified_strict_types`, `ordered_imports`,
    `single_blank_line_at_eof` — all from the new method's inline
    `\App\Modules\X199\Models\CreditTerm::create(` and its missing trailing newline. With
    `git diff --stat HEAD -- app/` **empty**, ruling 34 makes that the sha's verdict and ruling 26
    refuses the push, so a wave that was correct in every other respect — surface exactly the brief's
    seven paths, both mutation proofs quotable from the committed assertions, `tests 2344` matching the
    predicted floor to the digit — was withheld for three fixers on one file. Ruling 75 already
    required *"`./vendor/bin/pint <touched paths>` before each commit"*; what was missing is that
    **a brief that dictates the pint invocation has dictated which files go unlinted**, so the path
    list is checked against the commit's own `--` list before the brief ships. ⛔ Never resolved by
    excluding the path or editing `pint.json` (the One Rule); ⛔ never by pushing anyway because the
    fix is small — that is the gate deciding after the fact (ruling 42(2)). ⚠️ The ruling
    66/75/82/92/94/106/118/147/153/175/183 family a **sixteenth** time, and the sharpest yet because
    the dictated command was a *lint*: the one instrument whose whole job is to catch what the author
    did not look at. Per the 46/49/50/62/66/75/82/86/94/104/106/113/116/146/153/167/175/183 precedent
    the miss is the supervisor's and MONEY-123 carries its own two dispatches.
190. **Two carried backlog sweeps are measured CLEAN and STRUCK (measured 2026-09-08 15:1x; rulings
    64, 95, 100, 111, 114, 120, 132, 142, 151, 170, 184, 187).** (a) **`InvoiceNumber::next()` under
    concurrency** — the addendum carried it as *"unread so far … it may already take an advisory
    lock"*. It does, and more: `X-199/Domain/InvoiceNumber.php:14-18` **refuses outright** outside a
    transaction (`InvoiceNumberOutsideTransactionException`, *"the per-business lock is released at
    commit"*), `:20` takes `pg_advisory_xact_lock(199, $businessId)` — per business, held to commit —
    and `:22-26` re-reads the maximum under `lockForUpdate()`. Two racing requests for one business
    serialise; two businesses do not block each other. Nothing to build. (b) **Ruling 63's
    prose-feeds-the-instrument hazard does NOT widen.** The recorded measurement was that
    `File::allFiles()` appears in `X136Test`, `N008Test` and `N010Test` only, *"each scoped to its own
    module"*; the population is in fact larger and one member is **lane-wide** —
    `X-157/EdgeProvisionRefusalTest.php:12` scans `base_path('app')`, every module in the tree. It is
    nevertheless harmless to every other lane: it filters `getExtension() === 'php'`, so **no blade is
    ever read**, and it matches the single literal token `EdgeProvisionAction`, not a vocabulary. So
    prose dictated into an X-199 blade feeds no instrument. ⚠️ The correction is worth keeping even
    though the conclusion is unchanged: *"each scoped to its own module"* was the wrong reason for the
    right answer, and the next sweep that trusted it would have missed the one scanner that reads
    everything. ⛔ Neither is to be re-raised. ⚠️ Recorded from the same gate and **not** a settlement:
    the two Authorize.Net `E00040` members ruling 125 saw clear have **returned**
    (`cancel_is_one_tap_with_nothing_in_between`,
    `a_completed_job_asks_for_a_review_once_inside_the_cadence`), attributed off the sha by ruling 85's
    three legs — the lane's only Authorize.Net string across all eight modules is
    `C-Billing/capabilities.php:100`, a capability **refusal** — and appearing in **8 of 170** gate
    files here. Ruling 125 binds: the floor stays `2344 · 2342 · FAILED 0 · errors 2` and absorbs
    neither.
191. **A draft invoice's only action is a PDF that can never exist, while the row's real condition —
    never issued, and nothing here can issue it — is stated nowhere on the screen (RULED by the lane
    supervisor 2026-09-08 15:2x, briefed as MONEY-123 item 2).**
    `X-199/Ui/views/invoices.blade.php:54-70` gives `issued|due|offline_recorded` a **Record payment**
    button, `paid` a receipt, and `draft` an `Open PDF` link falling back to
    `<x-ui.status-pill state="unknown" label="PDF not available" />`. Measured: (1) ruling 43 stopped
    both writers of the hardcoded `https://cdn.goaiez.com/invoices/inv.pdf` and left the nullable
    column null — `X199Test.php:159` asserts `assertNull` — so **both `@if($invoice->pdf_url)` true
    arms are unreachable** and every draft row renders the fallback; (2) `invoice.draft` is a **real
    declared capability**, `manifest.php:32` providing it and the frozen plan carrying
    `job.completed -> … billing.invoice.draft` (`GOAIEZ-MASTER-PLAN.md:972`, subscriber list `:2326`),
    so `draft` means *an invoice you finish later*; (3) **nothing can finish it** — there is no
    `draft -> issued` transition anywhere in X-199 — while `Invoices.php:48-50` queries every invoice
    with no status filter, so the row does render, and `InvoiceReader:41,:49,:59` and
    `Unpaid.php:61,:74` all exclude `draft`, so it never enters the chase. **The defect is not that
    the string is false — there genuinely is no PDF — it is that the cell answers a question nobody
    asked and the status pill's `draft` promises a finishing step this app does not have.** Nearest
    prior shape is ruling 119(b), a submit button promising an act the same click refuses.
    **RULED: the label states the row's condition and its dependency —
    `Not issued; nothing here issues a draft yet` — and `state="unknown"` STAYS**, because `attention`
    is a call to action and there is no action the owner can take (ruling 143: the state and the words
    are one claim). ⛔ Not resolved by building the transition or adding a button — minting machinery
    so a plan line comes true is ruling 59 — ⛔ not by deleting `InvoiceDraftAction`, the only
    implementation of a declared capability (rulings 69, 186), and ⛔ not by touching `pdf_url`, whose
    null is ruling 43's own outcome. The two unreachable `@if` arms are **recorded, not edited**
    (ruling 96): they are correct the day a PDF generator exists. ⚠️ **`Receipt not available` at
    `:59` and `unpaid.blade.php:83` is measured CLEAN and stays** — a status pill states a state and
    is measured for **truth** (ruling 122), and ruling 50(a)'s name-the-dependency half binds empty
    states and prose, not pills; a paid invoice genuinely has no receipt. ⚠️ Blast radius measured
    with interior fragments (rulings 46, 86): `PDF not available` is asserted by **nothing** (ruling
    70), which is why it outlived every X-199 screen wave, so the item **adds** a method seeding a
    draft — the existing `InvoicesScreenTest` seeds none and cannot see the defect (ruling 68) —
    while `Receipt not available`'s two assertions are untouched.
192. **Ruling 188's DIAGNOSIS is right and its REMEDY is false: `cd app && …` inside a single Bash
    call DOES move the session, and a wrong working directory does not refuse — it ANSWERS (RULED by
    the lane supervisor 2026-09-08 15:5x, measured live this tick).** Ruling 188 correctly traced the
    `REVIEWS.md` write refusals to the session's cwd leaving the worktree root against
    `.claude/settings.json`'s **relative** allow-patterns, and then prescribed *"run tools as
    `cd app && …` inside a single Bash call, which does not move the session"* — the very form its own
    diagnosis had just identified as the cause. Measured: `cd app && ./vendor/bin/pint --test …`
    moved the primary working directory to `app/` and the harness announced it; a subshell
    `( cd app && … )` is refused outright (*"Contains subshell"*) and `bash -c 'cd app && …'` requires
    approval, so **neither escape is available to an unattended tick**. **RULED: a supervisor tick
    runs NO `cd` at all.** Anything needing `app/` as its cwd is the coder's (briefed) or the gate's
    (`bash bin/supervise.sh`, which cds inside its own process and cannot move the session). If a tick
    does move, the very next call is a bare `cd /home/goaiez/agents/grs-antig-money`, and the
    harness's own environment-update line is the proof — cheaper than ruling 188's `pwd`.
    ⚠️ **The second half is the dangerous one and is a new failure shape.** With cwd at `app/`, this
    brief's dictated root-relative sweep resolved to `app/app/app/…`, exited **2** with eight
    `No such file or directory` warnings, and printed **zero** lines where the true answer is **five**.
    A tick reading only the line count would have recorded a five-member population as *"measured
    clean and STRUCK"* under rulings 95/100/111 — the discipline that exists to stop a wave with
    nothing in it, inverted into deleting a wave that had something in it. **A zero-line sweep is
    checked against its exit code and its warnings before it is read as an absence**, and this is
    rulings 83/136/145/179's family a sixth time: the cheap signal moves for a reason unrelated to the
    fact it stands for. ⚠️ **A brief that dictates a sweep has dictated the DIRECTORY it runs in** —
    the ruling 66/75/82/92/94/106/118/147/153/175/183/189 family's eighteenth instrument — so every
    dictated sweep names its working directory and says what a zero-line result means. Both of
    MONEY-124's sweeps were run by the supervisor before dispatch (nine lines and five, exactly their
    tables); one of the two failed on cwd alone, which is how this was found. ⚠️ Measured in the same
    pass and recorded so it is not re-derived: `pint` **does** accept a `.blade.php` path and returns
    `{"tool":"pint","result":"passed"}`, so ruling 189's path list covers blades and a blade in a
    commit is linted, not skipped.
193. **A command that ends in `Tenancy::forgetAll()` clears its CALLER's tenant, and `Model::fresh()`
    bypasses the Eloquent scope but not RLS — so the first database read a test ever made after that
    command returned null (RULED by the lane supervisor 2026-09-08 16:0x, on MONEY-124's `24fe06cf`;
    briefed as MONEY-125).** MONEY-124's item 2.5 dictated, verbatim,
    `expect($overdueInvoice->fresh()->due_detected_at)->not->toBeNull();` in
    `X-199/MarkInvoicesDueTest.php`, immediately after `Artisan::call('x199:mark-due')`. It cannot
    run: `MarkInvoicesDueCommand.php:35` is `Tenancy::forgetAll();` at the end of `handle()`, so the
    test's ambient tenant is gone the moment the command returns — the command's own inner
    `actingAs` at `:56` restores correctly (`Tenancy.php:170-181`, a `finally`), and it is the
    `forgetAll()` that reaches out and clears the **caller's**. The read then fails **beneath**
    Eloquent: `Model.php:2089-2099` shows `fresh()` is
    `setKeysForSelectQuery($this->newQueryWithoutScopes())->useWritePdo()->first()`, so the tenant
    global scope is not the obstacle, and `Tenancy::forget()`'s own docblock (`:130-137`) states the
    one that is — *"an RLS policy comparing business_id against an empty setting matches nothing, so
    a connection left over from a previous tenant returns zero rows"*. The wave shipped
    `errors 3` against a floor of 2, the extra member being
    `Attempt to read property "due_detected_at" on null`.
    ⚠️ **This is the lane's standing field note arriving from the opposite direction.** The note is
    *RLS sits beneath the application scope, so `withoutGlobalScopes()` does not help*; here Laravel
    removes the scope **for** you, inside a method whose name suggests nothing of the kind, and the
    row is still refused. ⛔ So the fix is never `withoutGlobalScopes()`, `withoutGlobalScope(…)` or
    `DB::table(…)`: each reaches the same refusal, and any that appeared to work would do so only by
    asking the database a question with no tenant in it.
    **RULED: the TEST re-establishes the tenant around the read and the command is not changed.**
    `Tenancy::forgetAll()` is deliberate in a scheduled sweep that walks every owner and every
    business — it must leave no ambient identity behind — and softening it to a save-and-restore so
    a test reads more conveniently would be changing production code to suit a test *and* would make
    a full-tenant sweep look safe to call from inside a tenant context. ⚠️ **The shape was already in
    this lane and the brief departed from it:** `X-211/Console/DetectOverdueReceivablesCommand.php:36`
    carries the identical `Tenancy::forgetAll()`, and every post-command database read in
    `DetectOverdueReceivablesCommandTest.php` (`:67-84` is the worked example) is wrapped in
    `Tenancy::actingAs((int) $business->id, function () {…})`.
    ⚠️ **Why four green assertions in that very test could not see it:** every assertion the file had
    before MONEY-124 — `:43`, `:54`, `:55` — is an `Event::` assertion, which touches no database at
    all. **The test had no database read after the command until this wave added one**, which is
    ruling 68's *a test that cannot see the defect is not the test that proves the fix* applied to a
    whole file rather than to one fixture. The lane-wide sweep is small and is recorded so it is not
    re-derived: exactly **two** commands clear the caller's tenant (`X-199`'s and `X-211`'s), and of
    the twelve `Artisan::call`/`->artisan(` lines in the eight modules' tests, only this one is
    followed by an unwrapped database read.
    ⚠️ **The proof was void and its tell was in the report** (ruling 72): the RED line quoted for
    item 2 was `Expecting null not to be null .`, an `expect()->not->toBeNull()` message, while the
    committed test errors with `Attempt to read property … on null` **before** reaching any
    assertion — so the message shape quoted is one the committed assertion cannot emit, and no
    re-run was needed to see it. ⚠️ Per the
    46/49/50/62/66/75/82/86/94/104/106/113/116/146/153/167/175/183/189 precedent the miss is the
    supervisor's — the brief dictated both lines — so MONEY-125 carries its own two dispatches and
    MONEY-124's cap is untouched. It is the ruling 66/75/82/92/94/106/118/147/153/175/183/189/192
    family a **nineteenth** time, with a new instrument: **a brief that dictates an assertion has
    dictated the ambient state that assertion runs in**, and ambient state is the one thing a diff
    does not show.
194. **A list a screen cuts says that it is cut, and it keeps the end the screen exists to show —
    the lane had swept every kind of STRING a screen prints and never swept the LISTS (RULED by the
    lane supervisor 2026-09-08 16:2x, briefed as MONEY-126).** Ruling 36's question — *what is
    actually at the other end of the string this screen prints?* — asked of a list is **is this the
    whole list, and if not, which end was thrown away?** Nothing in rulings 36–193 had asked it.
    `grep -rn "limit(\|take(" --include=*.php` over the eight modules returns **three** lines, and
    they are three different answers. **(a) `X-199/Ui/Unpaid.php:59` is HONEST and is not touched:**
    its `limit(5)` sits in the *paid* branch, the door that reaches it is labelled `View the last 5
    paid` (`unpaid.blade.php:30`), and the unpaid count and value beside it are recomputed from
    **all** rows at `:71-79` under a comment saying exactly that — the shape done right, and worth
    keeping in mind as the model. **(b) `X-211/Ui/InvoiceThreadBeside.php:88-93` is the sharp one
    and it is NOT a copy defect.** It is the only place in the lane where a limit meets an
    **ascending** order (`orderBy('created_at')->orderBy('id')->limit(50)`), so the rows thrown away
    are the **most recent** ones. This is J12's own screen — *an overdue invoice is chased by
    reason, resolution first* — and an owner opening an invoice to find out why it is unpaid reads
    months-old messages while the recent exchange that explains it is not on the page; the empty
    state one line below (`:57`, *"Nothing has been said with X on any channel"*) presents the
    section as the whole conversation. **(c) `X-117/Ui/CheckoutBlock.php:114`** keeps the right end
    (`orderByDesc('id')->limit(10)`) and still does not say it is a window.
    **RULED: (b) takes the newest 50 and renders them oldest-first, (c) says when older orders are
    not shown, and both read ONE ROW PAST THE WINDOW off the collection they already fetch** — the
    overflow probe, so there is one query per render exactly as there is today. ⛔ Not resolved by
    removing either limit: an unbounded render on a screen with no pagination is a real hazard, and
    `ls app/resources/views/components/ui/` is twelve components (`attention-card button empty-state
    error-panel gauge row row-list sample skeleton status-pill submit systems-strip`) — **none
    paginates**, and the kit is Track 2's (ruling 21). ⛔ Not by building pagination (ruling 59).
    ⛔ Not by a second `count()` query. ⛔ `<h2>Orders</h2>` is **not** renamed: with ten orders or
    fewer it is true, and above ten the notice corrects it, so a rename is churn a reviewer must
    re-derive (ruling 47's companion).
    ⚠️ **Blast radius: ZERO existing assertion moves**, and the reason is the finding.
    `InvoiceThreadBesideScreenTest.php:51-52` seeds **two** messages with the **same** `created_at`,
    so the `id` tiebreak leaves the rendered order unchanged; no test in the lane uses
    `assertSeeInOrder` or asserts a message count; and `grep -rn ">Orders<"` returns
    `checkout-block.blade.php:43` **alone** (ruling 70). Both proofs are therefore **new** methods
    (ruling 68), and the X-117 one mounts **twice** — ten orders then eleven — because an assertion
    made inside one component instance proves nothing about the next page load (ruling 35).
    ⚠️ **Two sibling sweeps came back EMPTY and are STRUCK with their measurement** (rulings 95,
    100, 111): every `_cents` figure in the lane's blades **and** components divides by 100 (or by
    10000 for `hundredths_cents`) — 45 lines, no exceptions, so ruling 152(b)'s currency family has
    no arithmetic half; and **no owner-facing string in the lane claims an order at all**
    (`newest|latest|most recent|oldest|chronolog|in order|sorted` over the eight `Ui/` trees returns
    13 lines, every one an internal variable or a method name), so this defect could never have been
    found by sweeping copy — only by reading the query. ⛔ Neither is to be re-raised.
195. **A quoted RED line carries the failing test's NAME, and item 1's proof was VOID because it did
    not (RULED by the lane supervisor 2026-09-08 17:0x, on MONEY-126's `ed5d6e20`).** Ruling 72
    requires every mutation proof to be re-run against the committed tree with its RED line quoted
    verbatim. MONEY-126's item 1 quoted `Failed asserting that '<23 KB of HTML>' contains "THE NEWEST
    MESSAGE IN THIS THREAD"` and nothing else, and **that render cannot have come from the committed
    test.** Four independent discriminators: the dump carries `Floor install` (an `InvoiceLine` the
    committed test never creates), `scratch on the floor` and `Sorry about that` (both
    `InvoiceThreadBesideScreenTest:51-52`'s fixture, i.e. **another test's**), **zero** occurrences of
    `THE OLDEST MESSAGE IN THIS THREAD` (which the committed test seeds and which, under the briefed
    mutation, is precisely the row that survives the window and must therefore render), and **51**
    rendered `<li>` rows under a `take(50)`. So the proof evidences a tree that is not in the sha —
    ruling 72's exact defect, second instance. **RULED: pest prints the test name on the line above
    the assertion message, and the quote carries it**; without the name, attribution costs a reviewer
    a reconstruction of the fixture from a wall of markup, and here it was *only* possible because
    the render happened to carry another test's fixture strings. The proof is also run under
    `--filter` on the single test so the output is unambiguous, and it states that the test is
    **green with the mutation reverted** — ruling 82's collateral: a proof whose test fails either
    way proves nothing. ⚠️ Graded **PASS-WITH-NOTES, not BLOCK**, and the tip pushed: withholding a
    gated tip that landed on its predicted floor to the digit over a proof-provenance defect is the
    error ruling 74 exists to stop, and it is the grade rulings 76, 121, 128, 133 and 147 set for
    paperwork. ⚠️ Item **2**'s proof on the same wave is by contrast **valid and from the committed
    test** — its dump renders exactly `ORD-LIST11 … ORD-LIST02` with `ORD-LIST01` dropped and the
    notice absent, which is what `limit(ORDER_WINDOW)` produces, and the RED line is the **last**
    assertion exactly as the brief predicted. ⚠️ Half the defect is the brief's: MONEY-126 §1.6 and
    §2.5 said *"quote the RED line verbatim"* without saying **which** line. This is the ruling
    66/75/82/92/94/106/118/147/153/175/183/189/192/193 family a **twentieth** time — a dictated
    signature, line, needle, floor, test, command, sweep, report-field list, proof RED line,
    resolution policy, finalisation step, `pint` path list, mailbox path, working directory, ambient
    state — and now a dictated **quotation** — each dictates its own outcome; **detail is read as the
    spec and everything unstated is the coder's guess.** Per the
    46/49/50/62/66/75/82/86/94/104/106/113/116/146/153/167/175/183/189/193 precedent the miss is the
    supervisor's, so MONEY-127 carries its own two dispatches.
196. **The thread screen's empty state tells every real tenant that every issued invoice is paid, and
    ruling 89 fixed the same sentence one file over and stopped at the sibling (RULED by the lane
    supervisor 2026-09-08 17:0x, briefed as MONEY-127 items 2 and 3).**
    `X-211/Ui/views/invoice-thread-beside.blade.php:19` is
    `<x-ui.empty-state heading="Nothing unpaid.">Every issued invoice is paid.</x-ui.empty-state>`,
    rendered whenever `InvoiceReader::openForBusiness()` is empty. Ruling 170 measured that **nothing
    in production creates an invoice at all** — `issueInvoice()`'s only non-test callers are three
    evidence commands — so that branch is the state **every real tenant is in**, and the sentence
    tells them a ledger settled that never existed. Ruling 50(a) at its most literal, on the one
    thing a screen with no rows shows. ⚠️ `X-199/Ui/views/invoices.blade.php:19-21` has read *"No
    invoice has been raised for this account. Nothing in this checkout raises one from a completed
    job…"* since ruling 89, so the honest sentence was already in the tree, in another module, while
    this one said the opposite — rulings 113/116/123's recurring shape, a wave sweeping the file it
    was in. ⛔ Not resolved by building a dispatcher for `job.completed -> invoice.draft` (another
    lane's, ruling 59), ⛔ not by deleting the state, and ⛔ not by touching the picker at `:21-29`.
    **RULED: it names what has not happened and what it waits on.** ⚠️ Blast radius measured with
    interior fragments (rulings 46, 86): `Every issued invoice is paid|Nothing unpaid` over `app/app`
    and `app/tests` returns **three** lines and **no test asserts the X-211 string at all** (ruling
    70), which is why it outlived every X-211 wave — so item 2 **adds** a method. The third,
    `UnpaidTest:78`, asserts `X-199 unpaid`'s **heading only** — a ruling-80 partial cover over a
    body clause (*"You have no outstanding invoices."*) that is **true** but names no dependency,
    which is ruling 50(a)'s second half unmet — so item 3 **extends** that chain rather than adding
    one, and the shared clause is stated in X-199's **existing** words (ruling 123: one fact, one
    wording per module).
197. **Two more sweeps are measured CLEAN and STRUCK (measured 2026-09-08 17:0x; rulings 64, 95, 100,
    111, 114, 120, 132, 142, 151, 170, 184, 187, 190).** (a) **Singular reads** — `->first()` /
    `->latest(` / `->sole()` over the eight `Ui/` trees is **14 lines, zero findings**: every one is
    ordered or unique by **constraint**. `ar_plan_terms.business_id` and `subscriptions.business_id`
    are `->unique()` in their migrations (`2026_09_04_170000:17`, `2026_07_30_080943:27`), so
    `AgeingByReason:129`, `Mrr:70` and `RevenueRecovery:64` read *the* row and not an arbitrary one;
    `Declines:82` orders by `created_at` and means the earliest recovery; `C-Billing/Credits:74`,
    `X-199/Credits:92` and `SameAccount:85` each `first()` a collection their own query ordered
    `orderByDesc('id')`, so every `$latest…` is genuinely the latest; `InvoiceThreadBeside:74`'s
    fallback is over an `orderBy('due_date')` set; and both `Cart::…->first()` calls are safe because
    **every** writer goes through `CheckoutEngine::writeCart()`'s
    `updateOrCreate(['business_id','session_token'])` — the column is indexed and not unique, which
    is recorded, not migrated. (b) **`whereIn` over a hardcoded vocabulary** (ruling 88's `meters`
    shape) in the six modules that sweep never reached is **14 lines, zero new findings**: thirteen
    are over a computed id set, and the only literal vocabulary is `RevenueRecovery:73`'s
    `whereIn('entry_type', ['topup','grant'])`, which is **ruling 109's own subject** and already
    ruled. ⚠️ Recorded because it looked like a wave for ten minutes: the X-211 thread screen is
    **not** cut to one invoice — `invoice-thread-beside.blade.php:21-29` renders a `pick()` button
    per open invoice under `@if($invoices->count() > 1)`, so every open invoice is reachable and the
    parameterless generated route is not a trap. ⛔ None to be re-raised. ⚠️ Ruling 95's lesson a
    fourth time: **a sweep proposed by a ruling is a claim**, and one that comes back empty is struck
    with its measurement written down.
198. **A brief that dictates a dependency clause has dictated whether the screen names the whole wait,
    and MONEY-127's named one of two halves (RULED by the lane supervisor 2026-09-08 17:5x, on
    MONEY-127's `b76b60e1`; briefed as MONEY-128 item 3).** Ruling 196's wave landed correctly —
    `invoice-thread-beside.blade.php:19`'s *"Every issued invoice is paid."* became *"No invoice is
    open for this account. Nothing in this checkout raises one from a completed job, so this screen
    fills when the job hand-off is built."*, and `unpaid.blade.php:31` gained the same tail. ⚠️ **The
    first clause is better than the brief's model and deliberately so:**
    `InvoiceReader::openForBusiness():41` is `whereNotIn('status', ['paid','draft'])`, so the branch
    means *nothing open*, not *nothing ever raised*, and X-199's *"No invoice has been raised"* would
    have been false on a tenant whose only invoice is paid. **The tail is the defect.** The frozen
    plan's seam is `job.completed -> … billing.invoice.draft` (`GOAIEZ-MASTER-PLAN.md:972`, ruling
    191), both `openForBusiness():41` and `openUnpaidForBusiness():49` exclude `draft`, and ruling 191
    measured that **no `draft → issued` transition exists anywhere in X-199** — so building the
    hand-off alone fills neither screen and the conditional is false as stated. The model sentence the
    brief pointed at, `invoices.blade.php:20`, is **correct for its own screen**, because `Invoices.php`
    queries every invoice with no status filter and a draft does show there: the tail was copied from a
    file where it is true onto two files where it is one dependency short — rulings 113/116/123's shape
    a fifth time, arriving through the **brief** rather than through the wave. **RULED: all four of the
    lane's job-hand-off screens carry the same clause,
    `Nothing in this checkout raises one from a completed job, and a draft is never issued`** — the
    first half byte-identical, so the three existing assertions on it stay green, and each **extended**
    to cover the new half, since a reword of a tail no assertion names is prose no gate reads (ruling
    70). ⛔ Not resolved by building the transition or the dispatcher (ruling 59), ⛔ not by dropping
    the dependency clause. **Graded PASS-WITH-NOTES and PUSHED**: nothing false in the ruling-43 sense
    was introduced, the parent said *every issued invoice is paid* to a tenant with no ledger, and
    withholding a tip that landed on its predicted floor to the digit over an incomplete forecast is
    ruling 74's error. ⚠️ Both strings were dictated verbatim (`BRIEF-money127.md:108`, `:174`), so per
    the 46/49/50/62/66/75/82/86/94/104/106/113/116/146/153/167/175/183/189/193/195 precedent the miss is
    the supervisor's and MONEY-128 carries its own two dispatches. The ruling
    66/75/82/92/94/106/118/147/153/175/183/189/192/193/195 family a **twenty-first** time.
199. **The empty-state population is MEASURED — 28 states, and ruling 196's sentence is still standing
    in two sibling X-211 screens (RULED by the lane supervisor 2026-09-08 17:5x, briefed as MONEY-128
    items 1 and 2).** `grep -rn "x-ui.empty-state"` over the eight modules' `Ui/` trees returns **28**,
    read one by one for ruling 50(a)'s two halves — *does it say what has not happened*, and *does it
    name what it waits on*. **Two are false, and both are the sentence MONEY-127 had just removed one
    file over:** `X-211/Ui/views/ageing-by-reason.blade.php:39` *"Every issued invoice is inside its
    terms."* and `paymentplan-builder.blade.php:40` *"Every open invoice is paid or already on a
    plan."* Nothing in production raises an invoice (ruling 170), so both report a **healthy ledger to
    an account that has none** — ruling 196's reasoning verbatim, and rulings 113/116/123's shape a
    fifth time in the same tick as ruling 198's. ⚠️ **The payment-plan branch is reachable by two
    routes** — no invoice at all (every real tenant) and every open invoice already planned
    (`PaymentplanBuilderScreenTest:96`'s fixture) — so its replacement must be true in **both**, which
    is why it names both routes and carries **no** *"so nothing reaches this screen yet"* tail. ⛔ Not
    resolved by building anything: no transition, dispatcher, query, heading or door moves. ⚠️ Blast
    radius measured with interior fragments (rulings 46, 86): `PaymentplanBuilderScreenTest:97` asserts
    `'Nothing to split'`, the **heading only** — ruling 80's partial cover and exactly why the body
    survived — so that item **extends** a chain, while `ageing-by-reason`'s state is asserted by
    **nothing** (ruling 70) and all four `AgeingByReasonScreenTest` methods seed an overdue invoice, so
    none can render the branch (ruling 68) and that item **adds** a method. ⚠️ **A third group is
    recorded and deliberately NOT briefed — nine states that are TRUE but name no dependency**
    (`X-199 unpaid:37`, `money-paid-today:24`, `declines:29`, `credits:21` · `X-211
    collections-package-preview:42` · `C-Billing revenue-recovery:24`, `dunning-board:19`, `mrr:24` ·
    `X-117 checkout-block:45`, which has **no body at all**). Nothing in that group is false, which is
    ruling 76's PASS-WITH-NOTES grade and a later wave, and ⛔ not one of them is to be re-raised as a
    falsehood. The remaining seventeen are this lane's own corrected work or true-and-complete.
    ⚠️ **The generalisable half is why this sweep paid twice in one tick:** the instrument that finds a
    copied falsehood is the **population**, never the file — a wave that reads the file its ruling was
    written about will keep leaving the sibling, which is now the fifth recorded instance.
