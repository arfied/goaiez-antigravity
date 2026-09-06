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
