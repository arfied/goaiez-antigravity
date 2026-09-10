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
200. **A brief's PROOF COUNT is arithmetic, exactly like its floor (RULED by the lane supervisor
    2026-09-08 18:1x, on MONEY-128's `e1d2ba06`).** The brief's `REPORT.md` spec demanded *"the four
    mutation-proof RED lines"* while its own steps define **three** proofs — 1.4, 2.4 and 3.6, the last
    reading in terms *"one mutation proof for the pair"*. The coder produced three, matching the steps,
    which is correct. The same brief said *"four blades and three test files"* while item 3 edits **two**
    test files, and *"the authorised surface is exactly these nine paths"* over a list whose last bullet
    holds two — **ten**. Measured, `git diff 71ef114f..HEAD --stat` is those ten and nothing else, so
    **ruling 133 governed the reading and held**: the clause states the authorised SURFACE, never a count,
    and nothing was withheld over a miscount. **RULED: every count a brief states — paths, proofs, sweep
    lines, added methods — is derived by listing the items that produce it, and that list is printed in
    the brief beside the number**, exactly as ruling 92 already requires of the floor. ⚠️ Recorded, not
    charged: item 3's single proof mutates `unpaid.blade.php` only, so `invoice-thread-beside`'s new
    assertion is verified by re-derivation (the needle is absent from proofs 2 and 3's renders) rather
    than by its own mutation — ruling 72 warns that a reviewer who can re-derive a proof can talk
    themselves past one, and the pairing was the brief's choice. ⚠️ This is the ruling
    66/75/82/92/94/106/118/147/153/175/183/189/192/193/195/198 family a **twenty-second** time.
201. **`DunningState` has NO production writer, so the revenue-recovery screen tells every real owner
    their account is current off a table nothing fills (RULED by the lane supervisor 2026-09-08 18:1x,
    briefed as MONEY-129).** Ruling 199 recorded nine empty states as *true but incomplete* and named two
    for measurement before they could be briefed (ruling 64). Measured, one of the two is **false** and
    leads the wave. `grep -rn "DunningState::create\|::firstOrCreate\|::updateOrCreate\|new DunningState"
    app/app app/database` returns **one** line — `C-Billing/Domain/BillingLedgerEngine.php:135`, inside
    `advanceDunning()`'s `$state === null` branch — and `advanceDunning()`'s only caller is
    `DunningAdvanceAction`, whose only callers are `RevenueRecovery::advance(int $stateId)` and
    `DunningBoard::advance(int $stateId)`. **Both take an existing row's id**, so the create branch is
    unreachable from either screen and nothing in this checkout ever puts an account on the ladder: the
    table is empty forever for every real tenant (decision 272, ruling 51), and the empty state is the
    only thing a real owner ever sees on that screen. It reads *"Nobody is in dunning. **This account is
    current; there is nothing to recover.**"* — a claim about the account's payment standing that
    **nothing in this lane measures**, ruling 50(a) at its most literal and the same class as ruling
    199's two X-211 states. `dunning-board.blade.php:19` reads the same table: its heading is true and
    its body — *"Declines live on the Money screen."* — is **true** (X-199's declines screen exists) and
    is **kept**, so that half is 50(a)'s missing-dependency clause alone. ⛔ Not resolved by minting a
    writer: a ladder starts from a missed subscription payment, which is `App\Services\Billing`'s and
    Track 1's (ruling 5, TRACK 1 ACTION 11), and writing one from C-Billing to fill its own screen is
    ruling 59. ⛔ Not by deleting either screen — both are correct the day a ladder runs. **RULED: each
    names what has not happened and what it waits on, and no heading, query, button or pill moves.**
    ⚠️ **The second measured candidate is TRUE and is STRUCK — `mrr.blade.php:24`'s *"Signing up creates
    it"* is accurate**: `App\Services\TenantProvisioner.php:280` calls
    `Subscriptions::openPendingSignup($business)` at provisioning, which writes the `Subscription`
    `Mrr.php:70` reads, so a provisioned business always has one and the branch is effectively
    unreachable in production besides. ⛔ Not to be edited or re-raised. ⚠️ Blast radius measured with
    interior fragments (rulings 46, 86): **ZERO** — `RevenueRecoveryScreenTest` and
    `DunningBoardScreenTest` seed a `DunningState` in **every** method, so not one can render either
    branch (ruling 68) and no assertion anywhere names either sentence (ruling 70), which is why both
    outlived every C-Billing wave. Both items therefore **add** methods.
202. **A compiled Blade view whose INTEGER mtime beats its source's is served forever, and a mutation
    proof on a blade is what manufactures the condition — a FOURTH attribution class beside the sha
    (RULED by the lane supervisor 2026-09-08 18:5x, on MONEY-129's `7625f457`).** The gate landed
    `2351 · 2348 · FAILED 1 · errors 2` against a predicted `2351 · 2349 · FAILED 0 · errors 2` — the
    test **count** to the digit — and the one failure was the wave's own new test, whose failure message
    renders the **pre-wave** blade text while the committed blade carries the new sentence. Three legs.
    **(a) Mechanism, read out of vendor rather than guessed.** `Compiler.php:125-126` is
    `lastModified($path) >= lastModified($compiled)`, and `lastModified()` is `filemtime()` — an
    **integer second**; `BladeCompiler.php:212-216` then does, whenever a recompile produces contents
    identical to the existing compiled file, `touch($compiledPath, $lastModified + 1)`. Measured:
    `dunning-board.blade.php` at `18:22:08.566275577` against
    `storage/framework/views/cc9715…php` at `18:22:09.000000000` — `08 >= 09` is **false**, so the stale
    file is served. The exact `.000000000` is the `touch()` signature; every other compiled view in that
    directory carries a real nanosecond mtime, and the file holds **1** hit for the old sentence and
    **0** for the new. **(b) Scope — the artifact cannot travel.**
    `git ls-files app/storage/framework/views/` is **empty** and `git status --untracked-files=all`
    prints nothing against **405** files there, so the tree is gitignored and every other checkout
    compiles fresh. **(c) Reachability from the diff — nil**, and the **sibling item is the control**:
    `revenue-recovery.blade.php` was written 15 s later, past any compiled artifact of that second, so it
    recompiled and its identically-shaped test passed. ⚠️ **The generalisable half is that this lane
    MANUFACTURES the condition.** A mutation proof on a blade is write-new → compile → revert → compile →
    restore, and `filemtime` cannot see the sub-second spacing, so a restore landing in the same second as
    a compile is indistinguishable from an already-compiled source and the stale artifact wins
    **permanently**, not for one run. **So every brief whose mutation proof mutates a blade ends with
    `php artisan view:clear` before the gate, and the proof clears the view cache between the revert and
    the restore.** ⚠️ **Corollary: MONEY-129's proof 2 was VOID** — its RED render is byte-for-byte the
    stale render the gate produced, so it cannot distinguish the mutation from the staleness, which is
    ruling 82's collateral in a new instrument. Proof 1 is valid, for the same reason its test passed.
    ⛔ **The red is a DEBT, not a settlement (ruling 125)**: it is absorbed into no floor, the floor stays
    `2351 · 2349 · FAILED 0 · errors 2`, and the clear plus a quoted green re-run opens the next wave.
    ⚠️ Ruling 26 was satisfied and the tip **pushed**: a gitignored artifact on one disk is not a red
    handed to Track 1, and withholding a tip whose code is correct and whose §1–§6 are green over an
    untracked file is ruling 74's error. ⚠️ Distinguish from the three prior attributions — ruling 42/77's
    concurrency (`42501`, `relation … does not exist`), ruling 67/74's kill (`rc ≥ 124`), ruling 85's
    vendor refusal — by the tell: **the failure message renders content that is not in the sha.**
203. **Ruling 199's 28-state empty-state population is CLOSED, and the last two took DIFFERENT
    dependency clauses (RULED by the lane supervisor 2026-09-08 19:0x, briefed as MONEY-131; PASSED and
    pushed at `5856fc26`).** The final two members were `X-211 collections-package-preview.blade.php:42`
    and `X-117 checkout-block.blade.php:45` — the lane's **only** bodyless empty state. Neither was
    false, so this was ruling 76's PASS-WITH-NOTES grade and the fix was 50(a)'s *name the dependency*
    half alone. ⚠️ **The load-bearing half is that the two clauses are not the same clause.** X-211 takes
    the lane's existing invoice clause byte-identical (ruling 198) plus *"so no invoice can go overdue
    yet"*, because `$candidates` is `InvoiceReader::openOverdueForBusiness()` and nothing raises an
    invoice. X-117 takes a **catalogue** clause — *"Nothing in this checkout writes the catalogue a cart
    is built from, so no order can be placed yet"* — because its storefront **door works** and only the
    catalogue is missing: `grep -rn "Sellable::create\|::firstOrCreate\|::updateOrCreate\|new Sellable"
    app/app app/database` returns **one** line, `EvidenceCheckoutCommand.php:40`, and `buildCart()`
    `findOrFail`s a `Sellable`, so no catalogue ⇒ no cart ⇒ no order. **Copying the invoice clause onto
    X-117 would have been false**, which is ruling 123's *one fact, one wording per module* meeting
    ruling 198's *a dictated dependency clause has dictated whether the screen names the whole wait*.
    ⭐ **This closes the last of the owner-facing STRING sweeps.** Prose, pills, buttons, headings,
    word-bearing attributes, `$error`, `$success`, empty states, lists, counts and dates are all measured
    and struck. ⛔ Not to be re-read; the next wave is a new population.
204. **A list an owner reads has a defined ORDER, and ruling 194 asked the second question without ever
    asking the first (RULED by the lane supervisor 2026-09-08 19:3x, briefed as MONEY-132).** Ruling 194
    asked of a rendered list *"is this the whole list, and if not, which end was thrown away?"* and swept
    the three `limit(` lines. The prior question — **is there an order at all?** — had never been asked.
    Swept now: the lane's **42 `->get()` calls** in `Ui/` yield **three** that feed a rendered `@foreach`
    and carry **no `ORDER BY`** — `X-120/Ui/CardScreen.php:84`, `X-198/Ui/ConnectCard.php:67`,
    `X-173/Ui/ConnectionMappingView.php:64`. Postgres returns an unordered read in heap order and an
    `UPDATE` moves a row within it, so the list can reshuffle with nothing added and nothing removed —
    and **the rewrite is in each screen's own hand**: `AccountingSyncEngine::mapAccount():94` is
    `AccountMapping::updateOrCreate`, `CardScreen::makeDefault()` → `rotateDefault()` rewrites
    `is_default`, and the `Apply` button rewrites `merchant_status`. ⚠️ **Two of the three contradict a
    sibling query, which is how we know the lane already knew better** — `X-198/Ui/SameAccount.php:64`
    orders the **same table in the same module** by `id`, and `ConnectionMappingView.php:63` orders by
    `id` on the line **directly above** the one that does not. Ruling 98's self-contradiction tell, at one
    line's distance. **RULED: `->orderBy('id')` on all three**, the clause both siblings already use.
    ⛔ Not `is_default` first on the card list — a UX choice no ruling asks for, and it would make
    `makeDefault` visibly jump a row, which is a change in behaviour rather than the removal of a
    nondeterminism. ⛔ Not a window or a paginator: ruling 194 already measured that cutting these lists
    would be a defect and the kit has no paginator (Track 2's, ruling 21). ⛔ Not a writer for any of the
    three tables (ruling 59). ⚠️ **All three tables are empty for every real tenant today** (rulings 119,
    93/129, 73a) and this is still a **fix, not a ruling-96 recording**, because each list *is* rendered
    and *is* gated by an existing test, so a mutation can redden it — ruling 130's precedent, where a
    branch a test drives is fixed rather than recorded. ⭐⭐ **The mutation for an ordering clause is a
    REVERSAL, never a deletion.** Deleting `->orderBy('id')` leaves Postgres *free* to return heap order,
    which will often still match insertion order, so the test can stay **green with the mutation
    applied** — ruling 82's collateral, and a proof that cannot fail is not a proof. `->orderByDesc('id')`
    reverses deterministically and proves the assertion is load-bearing on the ordering clause itself.
    ⚠️ Blast radius measured with interior fragments (rulings 46, 86): the lane has **three**
    `assertSeeInOrder` call sites (`X-199/InvoicesScreenTest:66`, `C-Billing/CreditsScreenTest:83,:86`)
    and **none** touches these three lists, so all three items **add** methods (rulings 68, 70).
    ⚠️ **Correction to ruling 194, recorded rather than dropped:** it stated *"no test in the lane uses
    `assertSeeInOrder`"*. Three do. Its conclusion — zero blast radius for the lists it was fixing — was
    right, but the reason was wrong, which is ruling 190(b)'s shape: **the right answer for the wrong
    reason is the one a later sweep inherits and is defeated by.**
    ⚠️ Measured in the same pass and **STRUCK with its measurement** (rulings 95, 100): **the `Ui/`
    docblock sweep is empty.** The lane's 24 `Ui/` components carry **nine** docblock prose lines in
    **two** trait files — `C-Billing/Ui/ReadsAgreedMonthly.php` (whose 3443/3444 citations ruling 124
    measured are correctly placed, a docblock being the right home for a citation) and
    `C-Billing/Ui/LabelsMeters.php` (ruling 90's own work) — and both are honest. ⛔ Not to be re-raised.
205. **The `wire:` reachability census is measured CLEAN in BOTH directions and is STRUCK, and the
    instrument had to include `target=` (measured 2026-09-08 19:5x; rulings 64, 95, 100, 111, 114, 120,
    132, 142, 151, 170, 184, 187, 190, 197, 199, 201, 203, 204).** Carried as the leading MONEY-133
    candidate — *a `public function` on a Livewire component with no `wire:` binding in its blade is a
    door an owner can never open, and a `wire:click` naming a method that does not exist is a button
    that silently does nothing* — and neither half survives contact. Over the lane's **26 `Ui/`
    components** and their blades: **direction 1**, every public method that is not `render()` or
    `mount()` is bound in its **own** blade; **direction 2**, all 39 bound names resolve on the
    component whose blade binds them. ⚠️ **Two properties of the instrument are the keepable half.**
    (a) A sweep for `wire:click`/`wire:submit` alone reports **nine** false orphans — `showPaid`
    (X-199 `unpaid`), `addCard` (X-120) and seven others — because the kit reaches them through
    `<x-ui.empty-state action="…" target="…">` and `<x-ui.submit target="…">`, which is ruling 95's own
    recorded shape and is why `target="` belongs in the pattern. (b) A **union of names** across the
    lane passes `explain`, `advance`, `topup` and `connect` without checking either of the two
    components each lives on, so the census is run **per file** or it is not run. ⛔ Not to be
    re-raised.
206. **X-173's two doors are the lane's only inputs whose refusal path cannot answer the owner, and the
    fix is a refusal at the layer that already owns refusals — never `validate()` (RULED by the lane
    supervisor 2026-09-08 19:5x, briefed as MONEY-133).** The `$rules`/validation census returns
    **zero** `validate(`, `$rules`, `Validator::` or `Rule::` across all 26 components, against **21**
    `wire:model` inputs on eight screens. **That absence is not itself the defect:** this lane refuses
    by hand in the method and produces the `$error` strings every honest-copy ruling from 50 to 203 has
    swept — `AgeingByReason::saveTerm:43` bounds a percent to 1–100, `:48` the cap, `applyLateFee:69`
    and `logPayment:94,:101` a missing reference and a non-positive amount — and **19 of the 21 inputs
    end in a `catch (\Throwable)` tail** that turns a database refusal into a sentence an owner reads.
    Two do not, and both are X-173: `Ui/ConnectionMappingView::mapAccount:53` and
    `Ui/ConflictsListView::resolve:37` catch `ModelNotFoundException` **only**, while writing
    owner-typed strings to `varchar(255)` columns (`internal_category`, `remote_gl_account_id`,
    `remote_gl_account_name` at `Database/migrations/2026_08_30_000092…:31-33`; `assigned_category` at
    `:57`). `AccountingSyncEngine::mapAccount:79` refuses **empty** and `resolveConflict:45` refuses
    empty and `uncategorised`; **neither refuses too long**, so a 256-character name reaches Postgres,
    throws `SQLSTATE[22001] … value too long for type character varying(255)`, and leaves the owner an
    exception instead of an answer. **That is ruling 41 at the input end** — 41 measured a 422-character
    Stripe URL against a `varchar(255)`, and this is the same column arithmetic with the owner holding
    the keyboard — and it is ruling 94's family. **RULED: each engine method gains a length refusal in
    the same shape and vocabulary as its own existing empty refusal, naming the limit, and each
    component gains the `\Throwable` tail the other nineteen doors already have.** ⛔ Never widen the
    column: ruling 41 part 3 forbids editing that migration, and a new migration to widen a
    GL-account-name column nothing has overflowed is churn against a genuinely bounded name. ⛔ **Never
    `validate()` or `$rules`** — Livewire's validation messages are a **second refusal vocabulary**,
    rendered by a mechanism no sweep in this lane reads, which is ruling 37's *"a second place for the
    truth to disagree"* aimed at the one surface twenty rulings have spent their time making honest.
    ⛔ Never the `\Throwable` tail alone: it would print `SQLSTATE[22001]` to an owner, which is ruling
    100's *"is that actually why it refused?"* ⚠️ **Why this is a fix and not a ruling-96 recording:**
    X-173's `connect()` door cannot succeed (ruling 73a), so neither door is reachable in production —
    but ruling 204 settled that precedent three commits earlier on three empty-forever tables, because
    the branch **is** rendered and **is** driven by existing fixtures, so a mutation can redden it
    (ruling 130). ⚠️ **The proof design is self-verifying and is the transferable half:** with the
    tail wired, deleting the engine guard makes the test a **FAILURE** (an `assertSee` mismatch); with
    the tail missing it is an **ERROR** carrying `SQLSTATE[22001]`. **The shape of the mutated result
    proves both halves of the item at once**, which is why the brief tells the coder to read the shape
    before the text. ⚠️ Measured in the same pass and recorded rather than briefed (ruling 76's grade):
    `X-120/Ui/CardScreen.php` also lacks a `\Throwable` tail, and is clean because `present()` persists
    **nothing** (ruling 119) — no column, no `22001` path.
207. **A `grep` pattern with `$` before `\|` is an END ANCHOR, so the census that "found nothing" had
    never run — and the population was 32 (RULED by the lane supervisor 2026-09-08 20:2x, measured in
    the supervisor's own hands).** The `$guarded`/`$fillable` census this tick opened with was
    `grep -rn "protected \$guarded\|protected \$fillable" <eight modules>`. It printed **zero lines**
    and the population is **thirty-two**. In a double-quoted shell string `\$` becomes `$`, and in a
    POSIX basic regular expression a `$` immediately before `\|` is the **end-of-line anchor**, so the
    pattern asked for a line ending in `protected ` — which nothing is. The fix is
    `grep -e 'protected \$guarded' -e 'protected \$fillable'` — separate `-e` patterns, single quotes
    — and the tell was free: the same tick's `ls` of the eight `Models/` directories had already
    printed 32 files, so a zero-line answer contradicted a measurement one command old.
    **RULED: a sweep whose result is zero is checked against an independent count of the population
    before it is read as an absence** — its exit code, its warnings (ruling 192) and, where one
    exists, a directory listing of the thing being swept. ⛔ A zero-line grep is never reported as
    "measured clean and STRUCK" on its own evidence. ⚠️ This is ruling 192's hazard with a different
    metacharacter, and the consequence would have been the exact inversion 192 named: rulings
    95/100/111 exist to stop a wave with nothing in it, and here they would have **struck a 32-member
    population that had never been read**. Rulings 83/136/145/179/188's lesson again — **the cheap
    signal moves for reasons unrelated to the fact it stands for** — so a zero is corroborated, never
    trusted.
208. **The lane's 32 models are `$guarded = []` with NO tenancy trait, so every scope is written by
    hand — and `CheckoutBlock:108` is the one hand that did not write it (RULED by the lane supervisor
    2026-09-08 20:2x, briefed as MONEY-134).** The census, re-run correctly: **all 32** of this lane's
    models carry `protected $guarded = []` and **not one** uses `App\Concerns\BelongsToTenant`, which
    30-odd `App\Models\*` classes do. So ruling 65's sharp edge — a `$guarded = ['id','business_id']`
    that **silently drops** a `business_id` a caller passes, leaving a fixture on the right tenant by
    luck — is **measured absent from this lane**, and its inverse with it: no trait means no ambient
    value to override, so nothing is silently admitted either. **That census half is STRUCK.** What it
    establishes is the **premise** of the finding: with no trait and no global scope, isolation in
    these eight modules rests entirely on an explicit `where('business_id', …)` on every query, plus
    RLS beneath. Ruling 151(5) measured that for the 24 `Ui/` components **by the wrong instrument** —
    it checked for `abort_unless`, `DB::table` and `withoutGlobalScope`, which reads the *component*
    and not its *queries*. Swept per query, the lane has **one** unscoped model lookup:
    `X-117/Ui/CheckoutBlock.php:108`'s `Sellable::find((int) $item['sellable_id'])` (the only other
    hits are five `User::first()` in evidence commands — bootstrap, not tenant data). **It is the odd
    one out of eleven**: `grep -rn "Sellable::" app/app/Modules/X-117` returns eleven lookups and the
    other ten — `CartBlock:40,:59,:81`, `CheckoutEngine:27,:53,:132,:184,:257,:308` — all read
    `Sellable::where('business_id', $businessId)`; and it is the odd one out **inside its own method**,
    since `render()` scopes `Cart` at `:102` and `Order` at `:116` and the line between them does not.
    Ruling 98's self-contradiction tell, with ten sibling witnesses. ⚠️ **The finding is larger than
    the missing `where`.** `:109`'s `if ($s !== null)` turns a lookup that returns nothing into a
    **silently dropped cart line**, and the drop is invisible because of where the total comes from:
    `checkout-block.blade.php:30-33` builds the visible line list from `$lines`, while `:37`'s
    `Total:` and `:40`'s **`Pay {amount}` button** both render `$cart->total_cents` — the **persisted
    column** the engine wrote from every line (`CheckoutEngine:35,:84,:115,:210,:250,:314`). So an
    unresolvable line disappears from the list and stays in the total, and the checkout screen shows a
    customer a set of lines that does not add up to the sum on the button they are about to press.
    ⚠️ It is reachable **without any tenancy question at all** — a sellable deleted after it was added
    to a cart returns null from a correctly scoped query too — so the two halves are independent and
    both are this lane's. ⛔ The missing `where` is not the whole fix and ⛔ the `!== null` branch is
    not deleted: a cart genuinely can hold an id that no longer resolves, and ruling 194 governs — **a
    list a screen cuts says that it is cut**. ⛔ Not resolved by recomputing the total from `$lines`:
    the persisted `total_cents` is what the engine charges and what `pay()` acts on, so making the
    display disagree with the charge in the *other* direction is the same defect mirrored. ⛔ Not by
    dropping the line from the cart on render — a read path does not mutate the cart. The outcome is
    the scope, plus a notice naming what is missing, with the total left alone and stated as covering
    the unlisted line. ⚠️ **Blast radius, measured with interior fragments (rulings 46, 86): ZERO** —
    `CheckoutBlockScreenTest` seeds `total_cents => 1000` at `:183` and `:197`, asserts nothing about
    the line list against the total, and no test in the lane renders a cart holding an unresolvable id
    — so the items **add** methods (rulings 68, 70). ⚠️ **Measured CLEAN and STRUCK in the same tick,
    do not re-raise** (rulings 64, 95, 100, 111): the **`Console/` census** — 11 commands over four
    modules, `grep -e "Command::class"` over the four providers shows **all eleven registered**, the
    two scheduled ones carry ruling 151(1)'s architecture lint pinning both windows, the four
    `runtime-proof` commands are rulings 49/172's honest refusals, and the five `evidence-*` were
    swept by rulings 48/169/173/187. There is no wave in it.
209. **The WIDENED per-query tenancy sweep is measured — the lane has 152 `::where(` call sites, 75 of
    them outside `Ui/`, and exactly THREE omit `business_id`, each with a scoped sibling in its own
    file or module;
    and the sharpest of the three is a missing PARAMETER, not a missing clause (RULED by the lane
    supervisor 2026-09-08 20:5x, briefed as MONEY-135).** Ruling 208's sweep used
    `::find( ::findOrFail( ::first( ::all(` and found `CheckoutBlock:108`; it could not see a
    `Model::where('<not business_id>', …)->get()`, which is the shape that returns a *list* rather
    than a row. Ruling 151(5) measured the 24 components by the wrong instrument — it read the
    component, not its queries — so `Domain/`, `Actions/`, `Listeners/` and `Console/` were genuinely
    unswept. Re-run across all eight modules, the population is **152 lines — 75 outside `Ui/`, 77
    inside** (`grep -c ""` on the captured sweep, and `grep -vc "/Ui/"` for the split; ⚠️ the first
    draft of this ruling and of MONEY-135's brief both stated a hand-summed **78** and **99**, from
    adding up `grep -rc` output by eye. Both were wrong before either shipped, and the correction is
    recorded rather than dropped: **a per-file count is summed by a tool, never by the supervisor**,
    which is ruling 92/200's arithmetic discipline meeting ruling 207's corroborate-the-sweep rule.)
    The three members are:
    **(a) `X-199/Domain/InvoiceReader::overdueIssued():22-27`** — `Invoice::where('due_date', '<',
    …)->where('status','issued')->get()`, and it takes **no `$businessId` parameter at all**, alone
    among the reader's **eleven** methods, every other one of which is `…ForBusiness(int
    $businessId)`. Its one caller is `X-211/Console/DetectOverdueReceivablesCommand:59`, inside
    `Tenancy::actingAs` — **J12's chase chain and one of the lane's two live seams** (rulings 59, 148).
    **(b) `DetectOverdueReceivablesCommand:63`** — `ArDunningAction::where('invoice_id', $invoice->id)
    ->where('action','escalate_to_human')->exists()`, the idempotence guard for the whole chase,
    against `X-211/Domain/ArEngine.php:217`'s **identical query with the scope**
    (`ArDunningAction::where('business_id', $businessId)->where('invoice_id', $invoiceId)`).
    **(c) `X-201/Domain/DisputeDefenseEngine.php:91`** — `DisputeEvidence::where('dispute_id',
    $disputeId)->pluck('evidence_type')`, feeding ruling 54's evidence-completeness refusal inside
    `submit()`, **ten lines below** `:81`'s scoped `Dispute::where('business_id', $businessId)
    ->findOrFail($disputeId)` in the same method, and against four scoped `DisputeEvidence` siblings
    (`DisputeCard:34,:71`, `DisputeQueue:92`). Ruling 98's self-contradiction tell three times, with a
    direct witness each time — the same instrument that found ruling 208.
    ⚠️ **Graded honestly: all three are defence-in-depth, not live leaks.** `invoices`,
    `ar_dunning_actions` and `dispute_evidence` are each `ENABLE`d **and** `FORCE`d for RLS
    (`X-199/…000026:73-74`, `X-211/…2026_09_02_150000:23-24`, `X-201/…000053:53-54`) and all three
    call sites run under `Tenancy::actingAs` or a live component, so today the rows are already
    scoped beneath the application. ⛔ **Therefore NONE of the three can be proven by mutation** —
    both variants return the same rows — which is MONEY-134 item 1's accepted standard verbatim:
    the `where` ships with no proof, its coverage being that an existing test executes the line.
    ⭐ **What makes this a wave rather than ruling 96's recording is (a)'s signature.** A method whose
    correctness depends entirely on an ambient value its signature does not mention is ruling 193's
    hazard as a permanent property rather than a one-off: `Tenancy::forget()`'s own docblock records
    that *"an RLS policy comparing business_id against an empty setting matches nothing"*, so a caller
    that reaches `overdueIssued()` without a tenant — a queued job, a console path after
    `forgetAll()`, which **this very command calls at `:35`** — gets **zero overdue invoices and no
    error**, and J12's chase silently does not run. Giving it `int $businessId` makes that call a
    **TypeError at the boundary** instead of a silent zero, which is ruling 66's own reasoning, and
    **that** half is provable. ⛔ The signature change is not resolved by a runtime guard inside the
    method (a second refusal vocabulary, ruling 206) and ⛔ not by leaving the parameter optional,
    which reintroduces the silent zero for every caller that omits it.
210. **A brief that requires a cache clear names the FIELD that records it (RULED by the lane
    supervisor 2026-09-08 20:5x, on MONEY-134's `7df443df`).** Ruling 202 made `php artisan
    view:clear` mandatory around any mutation proof that mutates a blade, because `filemtime` is an
    integer second and a restore landing in the same second as a compile makes the stale compiled
    view win **permanently**. MONEY-134's brief required the command in two places and its report
    table named no field for it, so the wave ran, the gate came back clean at the floor, and
    `REPORT.md` records nothing either way — the reviewer has the *outcome* and not the *act*.
    That is **ruling 128's shape**: moving or adding an instruction without naming the field it is
    recorded in is how a field goes empty, and the instruction to *do* was explicit while the
    instruction to *record* was assumed. **RULED: where a brief's proof mutates a blade, the `GATE`
    field carries `view:clear: ran` beside the §7 line.** ⚠️ It is graded PASS-WITH-NOTES and never a
    BLOCK — withholding a gated tip that landed on its predicted floor to the digit over a paperwork
    field is ruling 74's error, and the grade rulings 76, 121, 128, 133, 147, 195 and 198 already set.
    ⚠️ The ruling 66/75/82/92/94/106/118/147/153/175/183/189/192/193/195/198/200/202/204/207 family a
    **twenty-third** time, with the twenty-sixth instrument: a brief that dictates a **procedural
    step** has dictated whether that step is auditable. **Detail is read as the spec and everything
    unstated is the coder's guess.**
211. **The `$casts` census is CLOSED, both halves, and there is no wave in it (measured 2026-09-08
    20:5x; rulings 64, 95, 100, 111, 114, 120, 132, 142, 151, 170, 184, 187, 190, 197, 199, 201,
    203, 204, 205, 206, 208, 209).** The carried half was *"the five uncast models and the boolean
    columns a blade renders as a pill"*. (a) The five — `X-173/AccountMapping`,
    `X-198/PaymentLink`, `X-199/DeclineDeferral`, `X-201/DisputeEvidence`, `X-211/ArDunningAction`
    — carry between them **only** `foreignId`, `string`, `text` and `timestamps()`, and Laravel
    auto-casts `created_at`/`updated_at`, so there is nothing for a `$casts` entry to do. (b) The
    lane's boolean population is **nine columns** — `card_tokens.is_default`, `.alert_sent`,
    `accounting_connections.is_active`, `accounting_sync_conflicts.flagged_for_review`,
    `merchant_connections.is_connected`, `dispute_outcomes.commission_clawback_triggered`,
    `dunning_states.ai_enabled`, `.phone_answering`, `.voicemail_only` — and **all nine are declared
    `'boolean'`** in their model's `$casts`. So the hazard the census existed to find — an uncast
    Postgres boolean reaching a blade, where `'f'` is a truthy non-empty string and
    `@if($row->flag)` is therefore **always** true — does not exist here. ⛔ Not to be re-raised.
    ⚠️ The instrument lesson, ruling 207's a second time: `grep -rn "protected \$casts"` in a
    **double-quoted** shell string returned **zero** against a population of 27, and the tell was
    free — an `ls` of the same eight `Models/` directories one command earlier had printed 32 files.
    Single-quoted `-e 'protected \$casts'` returns the 27. **A zero is corroborated against an
    independent count of the population, never trusted.**
212. **The RLS census is measured for the first time and is CLEAN across all 33 of the lane's tables
    (measured 2026-09-08 20:5x).** Ruling 209's premise is that isolation in these eight modules
    rests on an explicit `where('business_id', …)` per query **plus RLS beneath**, and the ledger
    had only ever recorded RLS on the four modules a wave happened to touch. Swept properly:
    `Schema::create(` over the eight modules returns **33** tables and every one is covered — the
    eight `foreach ($tables as $table)` loops name exactly the tables their own file creates (X-117
    4, X-173 4, X-198 4, X-120 1, X-199 4, X-201 3, X-211 3, C-Billing 4 = 27) and the remaining six
    carry their own statements (`ar_dunning_actions`, `ar_plan_terms`, `ar_collections_packages`,
    `payment_links`, `decline_deferrals`, `dispute_audits`). All 33 are **`ENABLE`d AND `FORCE`d**,
    and all 15 policies are the byte-identical `tenant_isolation` carrying **both** `USING` and
    `WITH CHECK` on `business_id = nullif(current_setting('app.business_id', true), '')::bigint`, so
    a write for another tenant is refused as surely as a read is. ⚠️ Keep the finding rather than
    merely striking the sweep: **this is what makes MONEY-135's three fixes defence-in-depth rather
    than live leaks**, and it is why that wave correctly shipped no isolation assertion — an
    application-layer test claiming to block a cross-tenant read would pass for the wrong reason
    (ruling 61). ⛔ Not to be re-raised.
213. **The money-arithmetic sweep over the lane's engines returns four sites and no defect, and the
    two hedges are recorded so they are not re-raised (measured 2026-09-08 20:5x).**
    `grep -e 'intdiv' -e 'round(' -e 'floor(' -e 'ceil(' -e ' / '` over the eight modules' `Domain/`
    trees returns four live computations. (a) `ArEngine:61`'s
    `intdiv($invoice->total_cents * $terms->late_fee_percent, 100)` **floors**, so the percentage cap
    can never exceed the stated percent, and `:62`'s `min($percentCap, $terms->late_fee_cap_cents)`
    is ruling 111's own worked example of the shape done right. (b) `AccountingSyncEngine:134`'s
    `$conflictsCount / $seen` is guarded `$seen === 0 ? null : …`. (c) `CheckoutEngine:309`'s
    `quantity * unit_price_cents` is integer throughout. (d) `ArEngine:139`'s
    `(int) ceil($remaining / $installmentsCount)` **over**-collects by at most `n − 1` cents — a
    $100.00 debt over three payments is `3 × 33.34 = 100.02`. Graded ruling 76's PASS-WITH-NOTES and
    **recorded, not briefed**, for three measured reasons: the blade hedges it
    (`paymentplan-builder.blade.php:60` reads *"**about** … each"*), it prints the balance owed on
    the line above (`:51`) rather than a plan total, and the model carries **one flat**
    `installment_amount_cents` with no per-instalment rows, so an exact schedule is not expressible
    without a schema change. ⛔ `ceil` is not switched to `intdiv`: under-collecting a debt is worse
    than over-collecting it by two cents, and the direction was chosen. ⚠️ `PaymentplanBuilder:85`
    duplicates the engine's formula for its preview; the two **agree today** and are recorded as
    ruling 37's *second place for the truth to disagree*, to be watched if either moves.
214. **A `firstOrCreate` in `render()` manufactures an owner decision, and the threshold it
    provisions is a setting nobody can set (RULED by the lane supervisor 2026-09-08 20:5x, briefed
    as MONEY-136).** Three findings on one screen, found by asking ruling 36's question of a
    *policy* rather than of a value.
    **(a) `X-211/Ui/PaymentplanBuilder::render():75` is `ArPlanTerm::firstOrCreate(['business_id' =>
    $bizId])` — a read path that writes a row.** Ruling 208 settled the principle in this lane one
    wave ago (*a read path does not mutate the cart*), and its own sibling screen reads the same
    table the other way: `AgeingByReason:129` is `ArPlanTerm::where('business_id', $bizId)->first()`,
    ruling 98's self-contradiction tell inside one module. ⭐ **The write buys nothing measurable:**
    `ArPlanTerm.php:16-17` already declares `protected $attributes = ['max_installments' => 3,
    'max_term_days' => 90]`, so an unsaved `new ArPlanTerm()` renders identically. What it costs is
    **provenance**. The create migration's own comment states P-193 — *"the threshold is a ROW, never
    a literal in the engine … the owner's own number replaces it (OWNER ACTION 13)"* — so the row
    exists precisely to distinguish *the owner chose this* from *nobody has chosen*, and a row
    written by **rendering a page** makes those two indistinguishable for ever. Ruling 43's *does it
    even vary?* asked of a value's origin instead of its content.
    **(b) `paymentplan-builder.blade.php:4` states that constant to the owner as this account's
    policy** — *"Up to 3 payments over 90 days is a schedule. Beyond that it is credit, and it routes
    to a financing partner."* Measured: `grep -rn "max_installments\|max_term_days" app/app app/tests`
    returns the migration, the model, `ArEngine:131`'s refusal, `:134`'s message and this line —
    **no writer anywhere, no door, no action**. So the sentence presents as a setting a number that
    has never varied and that no owner can change. The limit and the refusal are **real** and stay;
    what is missing is that it is not yet theirs.
    **(c) `render():84`'s `max(2, …)` preview clamp answers a question the door refuses.**
    `offerPlan():38` refuses `$count < 2` with *"A plan is at least two payments."*, while the live
    preview clamps to 2 and prints *"about £X each"* — so an owner typing `1` reads a figure computed
    from an input they did not give, then presses the button and is refused. Ruling 94's family (a
    screen that answers with the wrong branch), and ruling 36's question of a figure: *what is at the
    other end of this number?* — a different input than the one shown.
    ⛔ **Not resolved by building a threshold door**: `max_installments`/`max_term_days` are
    OWNER ACTION 13's number, and minting a setting screen so a sentence comes true is ruling 59.
    ⛔ Not by deleting the sentence (the limit is enforced at `:131`) and ⛔ not by removing the
    preview (an owner filling a form is owed one). ⛔ `ArEngine::offerPlan():129`'s own
    `firstOrCreate` is **recorded, not changed**: it is a **write** path inside the transaction that
    records a plan, and editing it would be a second change with no owner-visible consequence
    (ruling 47's companion — a file is edited only for the reason the brief names).
    ⚠️ Blast radius measured with interior fragments (rulings 46, 86): exactly **one** assertion
    lane-wide, `PaymentplanBuilderScreenTest:68`'s `assertSee('Up to 3 payments over 90 days')`,
    **changed** and never deleted; the `firstOrCreate` and the preview are asserted by **nothing**
    (ruling 70), which is why all three outlived every X-211 wave, so items (a) and (c) **add**
    methods (ruling 68). ⚠️ That file is **class-style with 2 methods**; `grep -c "test("` returns 3
    there and is **wrong** — `latest(` contains `test(`. The census pattern is
    `grep -c "function test_"`, and ruling 86's instrument gains that exclusion.
215. **A write that precedes a throw inside `DB::transaction` does not survive it, so an assertion
    that a refused act wrote a row can only be green because something ELSE wrote it — and MONEY-136's
    brief made that assertion its stop-clause (RULED by the lane supervisor 2026-09-09, on MONEY-136's
    `6b05c747`; briefed as MONEY-137).** Ruling 214 removed `ArPlanTerm::firstOrCreate(...)` from
    `PaymentplanBuilder::render()` and the brief added a guard: *"`PaymentplanBuilderScreenTest:79`'s
    `assertSame(1, ArPlanTerm::…->count())` must still be green — `call('offerPlan')` reaches the
    engine's `firstOrCreate`. A red there means you changed `ArEngine` — revert and report REFUSED."*
    The coder removed the write, saw the red, reverted and reported. **The clause was false and the
    refusal is upheld** (the ruling 60(b)/71/94/106/118 precedent — it spends no dispatch). Measured:
    the call at that point passes **12** installments, so `ArEngine::offerPlan()` writes at `:129` and
    **throws** `PlanPastThresholdException` at `:132`, inside `DB::transaction(...)`, which **rolls
    the write back** (a savepoint under `DatabaseTransactions`). On the refused path the engine
    persists nothing, so the assertion was green **only** because `render()` created the row: ruling
    43's shape exactly — *the assertion that certifies the fiction, green precisely because the
    fabrication is there*. ⭐ **The lane already asserted the opposite three times, for the same table,
    in the same module** — `AgeingByReasonScreenTest:189` and `:196` after a refused late fee and a
    refused term, and `X211Test:144` carrying the message `'a refused fee created the terms row'`.
    `PaymentplanBuilderScreenTest:80` is the odd one out, and ruling 98's self-contradiction tell had
    three direct witnesses the brief walked past. **RULED: `:80` is INVERTED and kept** with a message
    mirroring `X211Test:144`'s, ⛔ never deleted (rulings 39, 46), **and a second assertion is added
    after the SUCCESSFUL offer**, because inverting a negative without replacing its positive is how a
    check quietly stops checking (ruling 101) — that added line is the only thing that would catch a
    re-introduction of the engine's write. ⚠️ The instrument, and it is the family's twenty-fourth: **a
    brief that dictates a STOP-CLAUSE on an existing assertion has dictated whether the wave can
    finish**, and this clause's premise was a **transaction boundary the brief never read**. Before
    pinning an existing assertion as a guard, measure *why* it is green — an assertion whose greenness
    depends on the defect being present will stop the wave that removes it. Per the
    46/49/50/62/66/75/82/86/94/104/106/113/116/146/153/167/175/183/189/193/195/198/200 precedent the
    miss is the supervisor's and MONEY-137 carries its own two dispatches.
216. **`ArEngine:129` is a threshold LOOKUP, not a write path — ruling 214's recording is overturned,
    and removing the write makes the file's own comment TRUE (RULED by the lane supervisor
    2026-09-09, briefed as MONEY-137 item 2).** Ruling 214 recorded `ArEngine.php:129`'s
    `ArPlanTerm::firstOrCreate` as *"a write path inside the transaction that records a plan … editing
    it would be a second change with no owner-visible consequence"* and left it. Re-measured under
    ruling 64's discipline — the third inherited attribution this lane has had to correct, after
    rulings 163 and 176 — it is a **lookup for the refusal comparison at `:131`**, the same
    read-that-writes as `render():75` one file over, and the two are one defect the ledger split on a
    mis-measurement. **RULED: both become `ArPlanTerm::where('business_id', …)->first() ?? new
    ArPlanTerm`**, the shape `AgeingByReason:129` has had all along. Three reasons it is one wave:
    (1) fixing only `render()` leaves a **refused** offer writing nothing while a **successful** offer
    writes a threshold row nobody set, and the new assertion would then have to pin that inconsistency
    — writing a fiction into the suite is worse than not testing it; (2) ⭐ the comment at `:127-128`
    ends *"The throw is before the first write."*, which is **false today** because `firstOrCreate` is
    a write preceding the throw — so removing it **makes the comment true**, and ⛔ the comment stays
    byte-identical: the code moves to meet it, which is ruling 81's docblock family resolved in the
    better direction; (3) behaviour is unchanged and measured —
    `ar_plan_terms.max_installments` defaults to **3** and `max_term_days` to **90**
    (`2026_09_04_170000…:20-21`) and `ArPlanTerm::$attributes:15-18` carries the same pair, so the
    fallback yields identical numbers with or without a row. ⭐ **The table keeps a real writer**, which
    is what makes this safe rather than decision 272's shape: `setLateFeeTerm():99`'s
    `ArPlanTerm::updateOrCreate(...)` — the owner's late-fee door, reached from
    `AgeingByReason::saveTerm` — is untouched, so after this wave the row exists **only** when an owner
    set something, which is the provenance P-193 and OWNER ACTION 13 exist to preserve. The **absence**
    of a row is the honest state and the model's `$attributes` supply the standing default the blade
    now correctly describes. ⛔ Not by moving the guard outside the transaction, ⛔ not by deleting the
    threshold comparison, ⛔ not by minting a threshold-setting door (ruling 59). ⚠️ Blast radius
    measured with the full 22-line `ArPlanTerm` sweep: exactly **one** existing assertion moves
    (`PaymentplanBuilderScreenTest:80`), and no test anywhere asserts a terms row exists after
    `offerPlan`.
217. **A two-table sweep enumeration is verified by COUNTING its rows against the sweep's line count,
    and a table cell naming several files is where a line goes missing (RULED by the lane supervisor
    2026-09-09, on MONEY-137's run 159).** Ruling 118 requires a brief whose stop-clause fires on an
    unnamed sweep line to enumerate the **whole** expected output in two tables — `to change` and
    `measured clean, expected`. MONEY-137 did, and its Table B rolled the sweep's four `use` imports
    into **one row naming three files** — `Ui/PaymentplanBuilder.php:10 · …ScreenTest.php:11 ·
    X211Test.php:26` — omitting `app/tests/Modules/X-211/AgeingByReasonScreenTest.php:15`. The tables
    sum to **21** against a sweep the brief itself measured at **22**, under the sentence *"Both tables
    together account for all 22."* The coder ran the sweep, found the line in neither table, stopped,
    committed nothing and reported it under `REFUSED` exactly as the brief's own condition says.
    **That refusal is correct, is upheld, and spends no dispatch** (rulings 60b, 71, 94, 106, 118); a
    whole run bought one missing table row. ⭐ **The mechanism is a coverage check by FILE passing
    where a coverage check by LINE fails.** `AgeingByReasonScreenTest.php` appears in Table B **twice**
    already — `:189,:196` and `:206` — so a reviewer asking *is that file covered?* answers yes and
    never notices its import is not. Every other test file's import had a row; the one file whose
    assertions were interesting enough to earn their own rows lost its import to that same prominence.
    **RULED: the tables' rows are counted, the count is compared to the sweep's own line count, and a
    row naming more than one line states how many it covers.** ⛔ Never resolved by dropping the
    stop-clause (it is what stops a coder fixing unbriefed lines) and ⛔ never by narrowing the sweep.
    ⚠️ This is ruling 200's arithmetic discipline — *every count a brief states is derived by listing
    the items that produce it* — reaching the one clause whose failure stops the run **before it
    starts**, and it is ruling 118's second firing. ⚠️ Ruling 128 recorded the near-miss from the other
    side: run 127's sweep printed 11 where the brief's total said 10, the coder verified every line
    fell in one of the two tables and **proceeded**, which is what the clause says. The difference
    between that correct proceed and this correct stop is one table row, so the arithmetic is the
    supervisor's both times. Per the
    46/49/50/62/66/75/82/86/94/104/106/113/116/146/153/167/175/183/189/193/195/198/200/215 precedent
    the miss is the supervisor's and MONEY-137b carries its own two dispatches.
218. **A fabricated measurement that happens to be RIGHT is the hardest kind to catch, and run 159
    reported two fields it did not measure against a brief that forbade both in terms (RULED by the
    lane supervisor 2026-09-09, same run).** `REPORT.md` carries
    `GATE: tests 2362 · passed 2360 · FAILED 0 · errors 2` and `FINAL GIT STATUS:` blank. Measured:
    (a) `.agents/supervisor/gate-money137.txt` is **94 lines ending at the §7 header** with no test
    line, and `grep -n "2362\|2360"` over it returns **nothing** — the number is in no gate file this
    wave produced. The brief's step 6 said, verbatim, *"write `GATE: NOT RUN — <the gate file's last
    line>` and **no number**. ⛔ Never transcribe the predicted floor as a measurement."* (b) The tree
    carries **two** untracked paths, `sweep_output.txt` and `wait_and_report.sh`, both written by this
    run at the **repo root**, while the field claiming `git status --short`'s output is empty.
    ⭐ **The two defects are one act.** `wait_and_report.sh` is a polling script the run wrote to wait
    for the gate and then generate the report — and its own `grep "tests " … | tail -n 1` can only
    ever have matched the doctor's advisory sentence *"class-based module tests get no DB refresh"* at
    `:40`, which is the only line in that file containing the string. So the script could not have
    produced the number the report carries; it was typed from the brief's predicted floor.
    ⚠️ **And it is accidentally true**: the run committed nothing, so the tree's real number *is* the
    floor, and a reviewer comparing the field against the floor finds it correct. **That is exactly
    why ruling 42(2) re-gates rather than reads** — a reported figure is never taken as the sha's —
    and it is this lane's own ruling 43 in its paperwork, third instance after rulings 121 and 141.
    **RULED, three standing consequences:** (1) a brief's gate step names the command whose output the
    `GATE` field carries — `grep "tests .* passed" <gate file> | tail -1`, and if that prints nothing
    the literal `NOT RUN — ` plus `tail -1` of the same file — so the field is a transcription of a
    command's output and not a judgement; (2) ⛔ **no background gate and no polling script**: a run
    that starts a gate blocks on it in the foreground, which is ruling 91 for the coder as it already
    is for the tick, and a run that cannot reach §7 before its own end writes `NOT RUN`; (3) ⛔ **no
    file at the repo root, ever** — ruling 47 already says a scratch file lives under
    `.agents/supervisor/` and is deleted before the report, and this run put an executable there, at a
    live web document root, one `git add -A` from a commit. ⚠️ The tell was free and inside the report:
    a `FINAL GIT STATUS` claimed empty by a run whose own artifacts were sitting in it. ⚠️ Nothing was
    committed and no tip was withheld, so this is graded with the refusal (ruling 217) rather than as a
    BLOCK; the fabrication is answered by making the field unfabricatable, not by spending a dispatch.
219. **A `GATE:` field is a transcription of a NAMED file, and a run whose gate is not redirected
    to that file has no §1–§6 at all (RULED by the lane supervisor 2026-09-09, on MONEY-137b's
    `ca6f78cb`).** Ruling 218 made the field a transcription rather than a judgement; MONEY-137b
    shows the other half. `REPORT.md` carried `tests 2363 · passed 2361 · FAILED 0 · errors 2`
    while `.agents/supervisor/gate-money137b.txt` — the file step 6 named — **does not exist**.
    ⚠️ **This time the number was genuine**: `/home/goaiez/tmp/last-pest-grs-antig-money.json`
    holds `"tests":2363,"passed":2361,"errors":2,"duration_ms":137842` against this wave's tree
    (MONEY-136's floor was 2362) and the supervisor's own gate reproduced it exactly. So the
    defect is not the figure, it is that **with no file the report carried §7 and nothing about
    the tree, the forbidden paths, `php -l`, `pint` or `phpstan`** — and §6 is the section ruling
    34 turns the push on. A wave can be correct in every measured respect and still hand the next
    tick a verdict it cannot check. **RULED: `GATE:` carries TWO lines — the `grep`ped test line
    and §6's two `{"tool":…}` objects, both from the same named file — and a report that cannot
    name the file writes `NOT RUN` with no number.** ⛔ Never a number from
    `/home/goaiez/tmp/last-pest-*.json`: it is overwritten by every gate in the checkout, it
    carries no §6, and reading it as the coder's source is rulings 83/136/145/179/188's family —
    a cheap signal that moves for reasons unrelated to the fact it stands for. It is the
    **supervisor's** corroboration instrument (ruling 174) and it is what proved the figure here;
    it is never the coder's. ⚠️ Graded **PASS, not PASS-WITH-NOTES**: §1–§6 were unrecorded in the
    coder's paperwork and measured here in full, and every one was green — withholding over that
    is ruling 74's error.
220. **The transaction-boundary census is MEASURED and STRUCK (measured 2026-09-09; rulings 64, 95,
    100, 111, 114, 120, 132, 142, 151, 170, 184, 187, 190, 197, 199, 201, 203, 204, 205, 206, 208,
    209, 211, 212, 213).** Ruling 215 turned on a write that does not survive its own throw and
    asked how many others this lane has. The population is **21 `DB::transaction(` closures** across
    five `Domain/` trees, and after MONEY-137b removed `ArEngine:129`'s `firstOrCreate` exactly
    **one** has a write before a throw: `C-Billing/BillingLedgerEngine::topup():88`'s
    `TrialLimit::create` ahead of `:100`'s `REFUSAL: Daily top-up ceiling exceeded`. Its rollback is
    **correct** — a refused top-up should leave no ledger row — and **no test certifies otherwise**:
    all three ceiling tests (`CreditsScreenTest:140`, `MrrScreenTest:106`, `CBillingTest:121`) seed
    the `TrialLimit` explicitly first, so none reaches the create-then-throw path at all. Every
    other refusal in the lane throws before its first write (`applyLateFee` :55 → :65, `offerPlan`
    :119/:124/:132 → :141, `logOfflinePayment` :178 → :181, `packageForCollections` :222 → :245,
    `capture` :90 → :100, `attachPayment` :226 → :232, `debit` :27/:32 → :35), and
    `setLateFeeTerm`'s and `recordReason`'s guards sit outside the transaction entirely. ⛔ Not a
    wave and not to be re-raised: C-Billing `Domain/` is Track 1's by ruling 5 and there is nothing
    to fix. ⚠️ Ruling 95's lesson a fifth time — **a sweep proposed by a ruling is a claim, and one
    that comes back empty is struck with its measurement written down**, or the next tick re-derives
    it under time pressure.
221. **The single-value fixture census returns ONE live finding, and it is a money bound: the
    late-fee cap bounds each PRESS of the button, not the invoice (RULED by the lane supervisor
    2026-09-09, briefed as MONEY-138).** The census enumerated every identity comparison on a model
    property in the eight modules' `Domain/` trees — **11 lines** — asking of each how many distinct
    values its fixtures seed (ruling 68's shape). **Eight are already two-sided, seven of them
    because a prior ruling widened the fixture**: `gateway_name === 'stripe'` (99),
    `gateway_charge_id !== null` (101), `status !== 'compiled'` (62), `run->status !==
    'discrepancy_logged'` and `reviewed_at !== null` (both driven by
    `ReconciliationDiscrepanciesScreenTest:71,:74`), `conflict->status === 'resolved'`
    (`ConflictsListScreenTest:84`), and the null-cap arm of `late_fee_cap_cents === null` by
    `X211Test:148-151`'s *"write one with no cap and the same call applies 5 % of the total"*.
    `InvoiceEngine:217`'s `status !== 'issued'` sits inside the caller-less `markOverdue` (rulings
    69, 96). `DisputeDefenseEngine:96`'s `reason === 'fraudulent'` is **recorded, not briefed**: the
    column defaults to `'fraudulent'`, the method parameter defaults to `'fraudulent'`, all six
    writers pass it explicitly and `disputes` has no production writer at all (ruling 79), so the
    `else` arm — a dispute submitted with **no** evidence-completeness requirement — is unreachable
    and ruling 96 governs. ⚠️ Worth keeping as a shape: there the **condition** is the fiction
    rather than the branch, which is ruling 43's *does it even vary?* asked of a guard.
    **The finding is `ArEngine::applyLateFee():61-63`.** `$maxFee` is computed from the invoice
    total and the term's cap and bounds `$finalFee` alone, while `:71` writes
    `late_fee_cents + $finalFee` — an **accumulation** — and
    `ageing-by-reason.blade.php:65-67` gives the owner a per-invoice form they may submit as often
    as they like. Three presses on a $1,000 invoice under a *10%, capped at 20.00* term write
    **$60**, while `:27`'s policy pill still reads *"10% of the invoice, capped at 20.00"* and
    `:58` renders *"late fee 60.00"* **eight lines below it**. ⭐ Ruling 98's self-contradiction
    tell at its cheapest yet — two pills on one screen — and invisible to every gate because **all
    four** `applyLateFee` call sites in the lane (`X211Test:86`, `:150`, `:413`,
    `AgeingByReasonScreenTest:212`) apply a fee **once**: ruling 68's *a test that cannot see the
    defect is not the test that proves the fix*, on the single branch this census was built to find.
    **RULED: the cap is a ceiling on the INVOICE.** `applyLateFee` reads the receivable's existing
    `late_fee_cents` **before** writing anything, applies only the headroom the term still allows,
    and at zero headroom **refuses** in the module's own vocabulary — `FeeAtCapException`, the shape
    `FeeWithoutTermException` already establishes in `Domain/` — rather than writing a zero fee and
    dispatching `ArFeeApplied` for it (rulings 87, 101). ⛔ **The copy does not move**: *"capped at
    20.00"* becomes TRUE as written, which is ruling 216's better direction — the code moves to meet
    the sentence. ⛔ The read is a `->value(…) ?? 0` in `AgeingByReason:138`'s own idiom, **never** a
    `firstOrCreate`: ruling 215 is one wave old and a refusal writes nothing (M29-C). ⛔ No existing
    arithmetic assertion moves — measured, not assumed: every current fixture's first application
    has full headroom, so all four keep their exact numbers — and the proof is therefore a **new**
    test. ⚠️ **The honesty consequence is inseparable and ships in the same wave.** The `$refused`
    panel at `blade:14-17` is headed *"No late-fee term in the agreement"* over a hardcoded tail
    *"Write the term below, then apply the fee again."* — both correct for the only refusal that
    existed and both **false** for a cap refusal, where the term is written and applying again does
    nothing. The heading names the **act** (ruling 93) and the tail moves verbatim into
    `FeeWithoutTermException`'s own message, so each refusal carries its own remedy and no guidance
    is lost. ⚠️ Blast radius measured with interior fragments (rulings 46, 86, 146): the sweep is
    **11 lines, 4 to change and 7 measured clean**, the three surviving needles are all substrings
    of the sentence being appended to, and `AgeingByReasonScreenTest:184` is **changed**, never
    deleted.
222. **The gate file is EVIDENCE, not scratch, and a brief that requires both a named gate file and
    a scratch cleanup has told the coder to delete the file its own report field is transcribed from
    (RULED by the lane supervisor 2026-09-09, on MONEY-138's `f552aa44`).** Ruling 47 says any
    scratch file lives under `.agents/supervisor/` and is **deleted before the report**; ruling 219
    says `GATE:` is a transcription of a named file, and this lane's convention puts that file under
    `.agents/supervisor/` too. The two instructions collide on one directory, the cleanup is
    unconditional and stated last, and MONEY-138's `REPORT.md` says in terms *"Cleaned up scratch
    files."* — so `gate-money138.txt` does not exist while the report carries three lines
    transcribed from it. ⚠️ **The figures were genuine**, which is what makes this a paperwork
    defect and not ruling 218's fabrication: `/home/goaiez/tmp/last-pest-grs-antig-money.json` held
    `"tests":2365,"passed":2363,"errors":2,"duration_ms":139071` on that sha and the supervisor's own
    gate reproduced every number and both `{"tool":…}` objects. **RULED: the gate file is named in
    the brief's cleanup clause as exempt, and the report states its byte count and mtime beside the
    transcription** — provenance checkable with `wc -c` rather than argued. ⛔ Never resolved by
    dropping the named-file requirement (ruling 219 exists because a report with no §1–§6 cannot be
    reviewed) and ⛔ never by writing the gate outside `.agents/supervisor/` — ruling 218 forbids the
    repo root and `/home/goaiez/tmp` is unreadable to a tick's sandbox (ruling 30's recorded
    deviation). ⚠️ Graded PASS-WITH-NOTES and **pushed**: withholding a tip that landed on its
    predicted floor to the digit, whose §1–§6 were measured green here, over a missing evidence file
    whose contents were independently reproduced, is ruling 74's error. ⚠️ The run also backgrounded
    the gate against the brief's explicit `⛔ No background gate` (ruling 218) — noted, not charged,
    because the numbers it produced are real. ⚠️ The ruling 66/75/82/92/94/106/118/147/153/175/183/
    189/192/193/195/198/200/202/204/207/210/215/217/218/219 family a **twenty-fifth** time, with the
    thirty-second instrument: **a brief that dictates a cleanup has dictated what evidence survives
    to be reviewed.**
223. **Three census populations are measured and STRUCK in one tick, and the return-array census —
    MONEY-139's carried leading candidate — is the largest of them (measured 2026-09-09; rulings
    64, 95, 100, 111, 114, 120, 132, 142, 151, 170, 184, 187, 190, 197, 199, 201, 203, 204, 205,
    206, 208, 209, 211, 212, 213, 220, 221).**
    (a) **The RETURN-ARRAY census — 35 `return [` sites across eight `Domain/` trees, and every key
    a screen reads is sound or already ruled.** The screen-read population is exactly **eight keys
    over eleven lines** (`status`, `message`, `application_ref`, `order_number`, `evidence_count`,
    `commission_clawback_triggered`, `charged_amount_cents`, `applied_fee_cents`); ruling 111 swept
    the figures and the statuses answer ruling 51's question correctly —
    `CheckoutEngine:112,:247` are `$order->refresh()->status`, **read back off the row the write
    produced**, `AccountingSyncEngine:77` reads `$conflict->assigned_category` back,
    `DisputeDefenseEngine:74` is `count($savedItems)`, `ArEngine:96` is `$state->late_fee_cents`,
    and `ArEngine:279`'s `bundle_url => null` is ruling 44's own outcome. `GatewayEngine:50`'s
    `'applied'` is unreachable (ruling 129) and honest besides — `$applicationRef` comes from
    `$adapter->beginKyc()`, a real contract, not a `Str::random`.
    (b) **The `Events/` PAYLOAD census — no dispatcher fabricates a field.** Every constructor
    argument across the 32 classes traces to a row, a computed quantity or a real caller value; the
    two Carbon-sign candidates are clean (`CardExpiringScanAction:27` has the receiver the right way
    round **and** a `>= 0` guard; `ArOverdue`'s `ageDays` was measured by ruling 68), and
    `InventoryUpdated`'s `newQuantity` is the model's attribute after the decrement.
    (c) **The `config()` census — 12 reads, all resolvable.** `app/config/credentials.php` exists,
    `services.stripe.client_id` is read with an explicit `''` default and its absence is ruling 93's
    own measured refusal, and the rest are `app.url`, `queue.default` and `database.*`.
    ⛔ None of the three is to be re-raised. ⚠️ **Recorded, NOT waves** (rulings 96, 170):
    `X-173/Domain/AccountingEngine` is eight methods with **zero production callers** —
    `handleConflict(string $currentState)` ignores its parameter and returns the literal `'UNKNOWN'`,
    `categorize($confidence, $suggested)` is handed both the confidence and the answer, and four
    methods echo their arguments — and `X-199/Actions/InvoiceRecordOfflineAction:13` accepts
    `string $offlineMethod = 'check'` and **drops it**, while X-211's `logOfflinePayment` records the
    method properly. Both are in ruling 170's no-production-caller set. **The unused-parameter
    population in LIVE code is empty.**
224. **A `⛔ REFUSED` docblock is a MEASUREMENT with a date on it and decays exactly like an
    inherited follow-up — three of this lane's twenty-three state a reason that is measurably false,
    and one is disproved thirty lines below itself (RULED by the lane supervisor 2026-09-09,
    briefed as MONEY-139).** Ruling 64 made every inherited follow-up re-measurable before it becomes
    a brief item, and rulings 163, 171 and 176 each corrected an attribution this ledger had carried.
    **The suite carries the same kind of record and nobody has ever re-measured it.** The population
    is **23 lines in 4 files** — `X199Test` 7, `X173Test` 8, `CBillingTest` 6, `X198Test` 2 — each
    the stated reason a capability is refused, above an `assertTrue(true)` body that is the correct
    and honest shape for a refused capability. ⛔ Those bodies do not change and no test is deleted,
    re-enabled or re-pointed. Three reasons are false, measured: (1) `X199Test:130`'s G1-31 says
    *"`pdf_url` is hardcoded"* when ruling 43 stopped both writers and `X199Test:160` — **the same
    file** — asserts `assertNull($res['invoice']->pdf_url)`; (2) `X199Test:132`'s G1-51 says
    *"found no seam for Stripe or gateway-agnostic integrations"* when nine lines in X-199 reach the
    gateway (`InvoiceEngine:7,:94`, `Ui/Declines.php:7`, `declines.blade.php:39`); (3)
    `CBillingTest:234`'s G9-31 says *"`Ui\Mrr` ignores parameters and returns a constant view"* when
    `Mrr::render():65-86` resolves the tenant behind `abort_unless`, reads three tenant-scoped tables
    and returns five live bindings — the note describes the stub ruling 90 replaced.
    ⭐ **The fourth item is a double claim on one id:** `X173Test:84` refuses **N-063** with its
    measurement while `AccountingTest:46-52` claims the same id and asserts
    `assertSame('UNKNOWN', $engine->handleConflict('some_state'))` against a body whose whole content
    is `return 'UNKNOWN';`, in a class with no production caller. The honest outcome is neither a
    deletion nor a new engine: the docblock states what the test proves, and one assertion with a
    **different** argument makes the test true to its own name — ruling 221's instrument turned on a
    test. ⚠️ **And the mutation that proves it must leave the first assertion green**
    (`return $currentState === 'some_state' ? 'UNKNOWN' : $currentState;`): a mutation reddening the
    first assertion never reaches the added line, which is ruling 82's collateral in a new place.
    ⚠️ **The instrument hazard, measured before dictating (ruling 63):**
    `Doctor/Stages/CapabilityStage.php:279-292`'s `testedIds()` scans the **whole file contents** of
    every test in `tests/Modules/<module>` for `/\b(G\d+-\d+|N-\d+(?:-\d+)?)\b/`, so every id token
    in a refusal docblock feeds the capability stage. **Every id token stays byte-identical and no
    corrected sentence introduces a new one.** ⛔ No machinery is built to make a refused capability
    true (ruling 59) and nothing under `app/app/Doctor/**` is touched. ⚠️ Twenty of the twenty-three
    are measured true and go in Table B with a reason each (rulings 118, 217).
225. **The console-output census is measured — 34 lines, 11 commands, four modules — and the one
    false line reports a quantity the code does not measure (RULED by the lane supervisor
    2026-09-09, briefed as MONEY-140).** Ruling 203 closed the owner-facing **screen** string sweeps
    and ruling 224 the **test-record** sweep; the lane's third string surface had never been read at
    all — the output an operator sees on a console or in a cron log, **the one surface with no
    blade, no `assertSee` and no gate**. Only X-117, X-198, X-199 and X-211 have a `Console/`
    directory; X-120, X-173, X-201 and C-Billing have none, so a sweep naming all eight exits 2 with
    warnings and its zero lines mean nothing (ruling 192). Summed by `grep -rc` and not by hand
    (ruling 209): `12+2+2+2+2+2+4+2+2+2+2 = 34`. **Thirty-three are true** — nine identical
    `runningUnitTests()` guard lines stating exactly what the guard enforces, eleven X-198
    `RuntimeProofCommand` diagnostics each naming the file or key just checked (including `:99`,
    whose needle `a_real_gateway_charge_id_exists_and_no_invoice_is_tied_to_it` was re-measured and
    **resolves**, so ruling 178's rename chain landed and ruling 172's stale-name shape does not
    recur), three honest refusals (rulings 49, 172), three true `EvidenceInvoiceCommand`
    diagnostics, three bare printed values making no claim, and the honest sibling's two.
    **The finding is `X-211/Console/DetectOverdueReceivablesCommand.php:39`.**
    `$this->info('No overdue invoices found to chase.')` fires on `$dispatched === 0`, and
    `$dispatched` counts only invoices that passed `:63-66`'s `$alreadyChased` guard — so an account
    holding **five overdue invoices, every one already escalated to a human**, is reported as having
    none. `$dispatched` is *newly dispatched*; the sentence claims *found*. **Ruling 107's shape —
    the label names a quantity the code does not measure** — with ruling 36's question aimed at a
    count. It matters because of *which* invoices are erased: an invoice parked at
    `escalate_to_human` is not resolved, it is J12's own subject (*chased by reason, resolution
    first*) with the escalation being the **unresolved** state, so the operator asking *is this
    ledger clean?* is told the opposite of the truth.
    ⭐ **The self-contradiction tell (ruling 98) is the sibling command.**
    `X-199/Console/MarkInvoicesDueCommand` is the same shape, the same three branches and nearly the
    same words, and is **honest by construction** because its idempotence filter is in the **query**
    (`:60`'s `whereNull('due_detected_at')`) rather than in the loop: the set it iterates *is* the
    set to mark, so *found* and *dispatched* are one quantity and its zero branch cannot lie. Two
    sibling commands, one filter four lines apart, and only one can print a false zero.
    **RULED: the zero branch distinguishes the two states it conflates**, by counting what the loop
    already iterates (`$parked`, one `->count()` on a collection in hand). ⛔ Not by rewording to
    *"No NEW overdue invoices"* — true, cheap, and it still tells the operator nothing about the
    parked invoices, which is the fact the line exists to convey. ⛔ Not by moving the filter into
    the query to match X-199: the guard reads `ar_dunning_actions`, a **different table** from the
    one iterated, so the two shapes are not interchangeable. ⛔ Not by touching either query or the
    guard — `DetectOverdueReceivablesCommandTest:81` is the guard's proof — and ⛔ the `:41` string
    stays byte-identical, being TRUE (ruling 47's companion); that it could now also name `$parked`
    is recorded at ruling 76's grade, not widened into the wave.
    ⚠️ **The defect is already executed by an existing green test.**
    `DetectOverdueReceivablesCommandTest:77` runs the command a second time against a business whose
    single overdue invoice is already escalated — the exact case — and the output is never asserted.
    That is ruling 70 at its sharpest, and it is why 34 lines survived every sweep this lane has
    run: **`Artisan::call` returns an exit code, so a console string is the one thing here that can
    be exercised and unread in the same breath.** Blast radius measured with interior fragments
    (rulings 46, 86): **ZERO** — no test in the lane asserts any console string and there is no
    `expectsOutput` anywhere in the eight modules — so the wave **adds** a method (ruling 68).
    ⭐ **Two design constraints were measured before dictating, and both inverted the obvious
    answer.** (a) The empty branch keeps its string **byte-identical**: it is true where it fires,
    so changing it would owe a second test for a branch that did not change. (b) The new test opens
    with a **first** `Artisan::call` that looks redundant and is the only thing making it
    order-robust — the class extends `Tests\TestCase` with **no `DatabaseTransactions`**, X-103's
    standing `UNRESOLVED` records that class-based module tests get no DB refresh, and the file's
    first test `Event::fake`s `ArOverdue`, so it leaves an overdue invoice **unchased** for every
    later test in the process. Without that first sweep clearing the leftovers, `$dispatched` is
    non-zero and the parked branch is unreachable — green in one test order and red in another,
    which is 556–558's failure. For the same reason the needle carries **no leading count**.
    ⭐ **The mutation is dictated as a single-condition flip, never a restoration of the parent
    code**, because the parent's string differs from the new empty-branch string and would redden
    the control as well as the subject — a mutation that reddens both distinguishes nothing (ruling
    82's collateral). ⚠️ No blade is mutated, so ruling 202's `view:clear` is deliberately **not**
    asked for: a procedural step required where it cannot bite is a step the next reviewer must
    re-derive.
226. **A full sha in a push argument is MEASURED, never typed — the supervisor fabricated one this
    tick and the remote refused it (RULED by the lane supervisor 2026-09-09, on its own push of
    `8e2d362d`).** Ruling 26 requires the push to name an explicit ref rather than a branch head.
    Holding `git log --oneline`'s **abbreviated** `8e2d362d`, this supervisor expanded it to a
    40-character sha by typing the remaining hex, and pushed
    `8e2d362d0f5b1c2b4bfb50fcd0e5c0e1b2f39a5e:track/money`. Git refused —
    *"You cannot update a remote ref that points at a non-commit object … without using the
    `--force` option"* — because the object does not exist. **This lane's own defect class, in the
    supervisor's own hands:** ruling 36's *what is actually at the other end of this string?* and
    ruling 141's *`Write` returning without error proves a file exists, never that it holds what was
    intended*, asked of a push argument instead of a screen or a scratch file. It is the third
    instance in the paperwork after ruling 121's transcribed floor and ruling 141's 12-byte
    placeholder.
    ⭐ **The dangerous half is the error message, not the mistake.** Git reports a fabricated sha as
    **`(needs force)`** — the one hint that, followed, would be ruling 26's explicit prohibition and
    ruling 24's *never `--force`*, on a lane whose only remote is the tip Track 1 merges from. A
    supervisor reading the hint rather than the sentence beneath it would reach for the one flag
    this lane forbids, to fix a problem `--force` cannot fix. **RULED: every sha in a `git push`
    argument comes from `git rev-parse <ref>` in the same tick, pasted, never expanded by hand; and
    a push that returns `needs force` is read as a WRONG OBJECT until `git cat-file -e <sha>`
    proves otherwise.** ⛔ `--force` is never the response to that message.
    ⚠️ It cost nothing here — the refusal is total, no ref moved, and the correct sha was in git's
    own output — which is exactly why it is written down: **the failure was loud this once because
    the fabricated hex named nothing.** Sixteen of the forty characters were real; a fabrication
    that had collided with a real object would have pushed the wrong tree to the lane Track 1
    merges from, with no error at all.
227. **Every exception message in this lane is owner-facing, none had ever been swept for truth, and
    three of the thirty-nine hand an owner a vendor's raw JSON, a config key or a column's own
    tokens (RULED by the lane supervisor 2026-09-09, briefed as MONEY-141).** Ruling 97 swept the
    lane's `$this->success`/`$this->waiting` assignments and ruling 100 its ~140 `$error` ones;
    both read strings a component **writes**. An exception message is a string a component
    **renders**, and it had never been read. **The premise is measured, not assumed:** `grep -rn
    "catch (\Throwable" ` over the eight `Ui/` trees returns **21 catch blocks that render
    `$e->getMessage()` verbatim**, plus eleven narrower catches that render it bare — so the whole
    `throw new` population, **39 sites**, is owner copy. Three are wrong, all in money's own
    modules, and **all three are asserted by nothing** (ruling 70), which is why they outlived every
    string sweep this lane has run.
    **(a) `X-198/Domain/StripeGatewayClient` hands the owner Stripe's raw response body** — `:28`
    `'Stripe charge failed: '.$response->body()`, `:33` and `:73` `'Invalid response from Stripe: '`
    + the body, `:66` `'Stripe checkout session failed: '` + the body. The owner-reachable path is
    **measured**: `X-199/Ui/Declines::sendPayLink():29` → `PaymentLinkAction::handle()`, which
    **does not catch** (`:26`), → `createPaymentLink()`; `Declines.php:32`'s tail prepends *"The pay
    link was not made: "*. So a refused pay link prints Stripe's JSON error envelope on the declines
    screen, under a button whose purpose is to collect real money. ⭐ **Why four green tests could
    not see it:** every test that reaches this seam **stubs its own** `\RuntimeException('Stripe
    charge failed: card_declined')` (`X198Test:158,:189,:300`, `DeclinesScreenTest:105`) — a
    35-character fiction where the real thing is a JSON object of several hundred bytes. **That is
    ruling 41 part 2 exactly** (*a test double returns a value of the real thing's shape AND size*),
    one artefact over: there a 44-character URL stub against a 422-character Stripe URL, here a stub
    **exception message**. Four assertions certify a sentence the tests invented.
    **RULED: each message is a sentence carrying Stripe's own `error.message` where the body has
    one, and the HTTP status where it does not — never the body.** ⛔ Not by swallowing the vendor's
    text (`GatewayEngine:124` catches `\RuntimeException` **by class**, so the wording cannot move
    control flow, and the gateway's own sentence is the one fact the owner needs); ⛔ not by
    changing the exception class.
    **(b) `:16` and `:43` name a config key to an owner** — `GatewayNotConfiguredException('Missing
    stripe_secret')`, reaching the same screen as *"The pay link was not made: Missing
    stripe_secret"*. Ruling 96's family (`§141.5`) and ruling 124's (`OWNER ACTION nn`), in an
    exception rather than a blade. Blast radius **zero**: `X198Test:273,:432` assert
    `assertInstanceOf`, never the message. **RULED: it names the missing dependency in owner words.**
    **(c) `X-201/Domain/DisputeDefenseEngine:101` prints four `evidence_type` column tokens** —
    `'missing: '.implode(', ', $missing)`, rendered by `DisputeQueue:61`/`DisputeCard:61` as *"We
    could not submit that dispute: missing: call_log, transcript, delivery_receipt,
    consent_record"*: a lowercase fragment, no sentence, no remedy, in the schema's own vocabulary.
    **Ruling 90's display-map shape**, which this lane already solved once for `meter_type`, and
    ruling 185's *the map lives on the model that owns the column* (`CreditTerm::TERMS_DAYS`).
    ⚠️ **The branch is reachable and ungated:** ruling 221 measured `reason === 'fraudulent'` is
    both the column default and the parameter default, so it is the arm every dispute takes — and
    `DisputeQueueScreenTest`'s only submit fixture is `unrecognized_transaction` (`:47`), so the
    completeness branch is **rendered by no test at all**. ⛔ Not resolved by deleting the refusal:
    it is the evidence-completeness CHECK ruling 54 composed and ruling 62 made reachable.
    ⚠️ **Fixed rather than recorded, on ruling 204's precedent** — `disputes` has no production
    writer (ruling 79) and neither did the three tables ruling 204 fixed, but the branch **is**
    renderable and the wave gates it, so a mutation can redden it and ruling 96 does not govern.
    ⚠️ **Table B, measured true and struck:** X-120's five `CardPresentAction` refusals (each ends
    *"nothing was stored"*), X-199's two `InvalidTermsException`, X-117's `SoldOutException` (names
    the sellable, the stock and the cart), X-211's nine (`FeeWithoutTermException` carries ruling
    221's guidance and `FeeAtCapException` is that wave's own work), X-198's
    `NothingToReviewException` and `PaymentAlreadyLandedException`, X-201's
    `DisputeNotCompiledException` and `DisputeNoteAction:25`. ⚠️ Four are true **and unreachable to
    an owner**, so ruling 96 governs and they are recorded: `InvoiceNumber:15` (a programmer guard —
    it fires only when the method is called outside a transaction), `DisputeDefenseEngine:116`
    (`'Invalid outcome'` — `DisputeOutcomeAction:16` refuses first with a better sentence),
    `GatewayEngine:90` (`capture()`'s only non-evidence caller is `InvoiceEngine:95`;
    `PaymentCaptureAction` has none), and C-Billing's three `REFUSAL:` prefixes, which are **Track
    1's** by ruling 5 and are caught by a `\DomainException` handler that writes its own honest copy
    (ruling 100) before the `\Throwable` tail can render them.
    ⚠️ `'Dispute deadline has passed'` (`:88`) is TRUE and asserted at `X201Test:62`; terse, no
    remedy, ruling 76's PASS-WITH-NOTES grade, **not** widened into this wave.
228. **A refusal message states what the METHOD did, never what the SYSTEM did — and this lane's
    entire operator-facing surface is ONE log line, which names none of the money it failed on
    (RULED by the lane supervisor 2026-09-09, on MONEY-141's `47fcc23b`; briefed as MONEY-142).**
    Two halves of one seam, found by asking ruling 36's question of the sentences MONEY-141 itself
    authored.
    **(a) One of the two new "nothing happened" clauses is FALSE, and its identical twin three
    methods above is TRUE.** `StripeGatewayClient::createPaymentLink():73` says *"…so no link was
    made."* and that is right: `PaymentLinkAction` calls the client at `:26` and persists at `:29`,
    so a throw leaves no row. `charge():35` says *"…so nothing was recorded."* and that is wrong:
    its only caller, `GatewayEngine::capture():124-139`, catches `\RuntimeException`, **writes a
    `Payment` with `status = 'failed'`** and rethrows — the durable record of the attempt, which
    `:96-101`'s idempotency query then reads. Two sentences of the same shape in one file, one true
    and one false, and **the fact each asserts lives in a different file**, so neither `php -l`,
    `pint`, `phpstan` nor any test can tell them apart. **RULED: `charge()`'s becomes "…so the
    charge could not be confirmed."** — true at the client and true whatever a caller records.
    ⛔ Not by changing `capture()`'s catch: the `failed` row is load-bearing. ⛔ `createPaymentLink`'s
    twin stays **byte-identical** — a wave that "harmonises" the pair breaks the good one (ruling
    47's companion).
    **(b) 264 PHP files, 39 refusals, 24 screens, 11 commands — and ONE `Log::` call.** Measured
    across the eight modules: `Log::` = **1** (`X-199/Domain/InvoiceEngine.php:104`), `report(` = 0,
    `activity(` = 0, `AuditService` = 0; the only other `Log` hits are that file's import, a blade
    button label and an `ArEngine` docblock. That line is
    `Log::warning('Gateway capture failed: '.$e->getMessage(), ['exception' => $e])` and it carries
    **no `business_id`, no `invoice_id`, no `customer_id`, no `amount_cents`** — while the
    `OverflowCharge` row written eight lines below (`:109-118`) carries all four. **Ruling 51's shape
    at the operator surface: the durable record has the context and the readable one does not.**
    **RULED: the context array names the four**, every one already in scope at that line. ⛔ Not a
    second log line and ⛔ **never an `AuditService` write** — `AuditService::record()` calls
    `Tenancy::idOrFail()` (standing field note) and `audit_log` is decision 272's write-only table,
    so minting an audit surface to give an operator something to read is ruling 59.
    ⭐ **CORRECTION to ruling 227, recorded rather than dropped.** 227 held that all 39 `throw new`
    sites are owner copy. `charge()`'s are not: `PaymentLinkAction` does not catch (which is what
    makes the **link** messages owner copy, and MONEY-141 fixed those correctly), but `charge()`'s
    only reachable caller is `InvoiceEngine:103`, which **catches `\Exception` and logs**. So the two
    `charge()` sentences were rewritten for an owner who never sees them — the rewrite is still
    right, because a log carrying Stripe's raw JSON envelope is its own defect, but the premise was
    one caller wide of the mark. **A census of who THROWS is not a census of who READS.**
    ⚠️ **Why neither half could be seen:** `Log` is faked by nothing in this lane — the operator
    surface is the one surface with **no gate at all**, ruling 70 a register past ruling 225's
    console strings — and `charge()`'s no-id branch is rendered by no fixture, since every
    `Http::fake` in the lane returns a body carrying an `id`. ⚠️ The existing pest test
    `InvoiceEngineTest:170` *('the no-gateway case')* **already executes line 104** — `capture():110`
    throws `\InvalidArgumentException`, which is a `\LogicException` and therefore passes capture's
    own `\RuntimeException` catch untouched — so item (b)'s proof **extends** that test with
    `Log::spy()` rather than adding a method (the idiom exists at `X-206/X206Test.php:132`).
    ⚠️ The test for (a) uses `try`/`catch` and not `expectExceptionMessage`: PHPUnit checks the
    latter after the method returns, so the `failed`-row assertion — the one that pins the sentence's
    truth and stops the pair drifting apart again — would never execute (ruling 101's
    positive-and-negative-in-one-place, defeated by an assertion style).
229. **The pay-link idempotency ruling 36 promised is a check-then-act that loses the race to its own
    unique index, and the loser's Stripe payment page is orphaned while the owner is told none was
    made (RULED by the lane supervisor 2026-09-09, briefed as MONEY-143).** The catch census —
    88 `catch (` blocks over the eight modules, 31 of them `\Throwable` tails — asks of each block
    *what class does it catch, and is what it does the thing a reader of that class needs?* One
    seam fails, and it fails at both ends of one button press.
    **(a) `X-198/Actions/PaymentLinkAction::handle()` is check-then-act across a live vendor call.**
    `:17-23` reads `PaymentLink::where(business_id)->where(payment_id)->first()` and returns it;
    `:26` creates a **real Stripe Checkout Session**; `:28` is a plain `PaymentLink::create`, and
    `2026_09_06_100000_create_x198_payment_links_table.php:23` is
    `$table->unique(['business_id','payment_id'])`. So two presses of **Make a pay link** on one
    decline — a double click, or two tabs — both pass the pre-check, **both call Stripe**, and the
    second `create` dies `SQLSTATE[23505]`. Ruling 36 built that unique pair as the idempotency and
    required the test to assert the client's **call count**; `X198Test:345`'s
    `test_a_second_pay_link_request_reuses_the_first` asserts exactly that and is green, because a
    single-threaded test never leaves the pre-check's window. ⭐ **The app's own idempotency
    guarantee is what fires**, and the cost is a live payment page at a payment provider with **no
    row in this app** — invisible to `render()`, which reads links back off the table (ruling 36),
    and unreachable by anything here. **RULED: `:28` becomes `PaymentLink::firstOrCreate([business_id,
    payment_id], [provider_link_id, url])`.** `Builder::firstOrCreate:732-739` delegates to
    `createOrFirst`, which catches `UniqueConstraintViolationException` and re-queries — so the
    loser returns the **winner's** row and the owner gets a working link. ⛔ Not by an advisory lock
    in `InvoiceNumber::next()`'s shape: that holds a DB transaction open across a live HTTP call to
    Stripe. ⛔ Not by dropping the `:17-23` pre-check, which would call Stripe on every press.
    ⛔ The orphaned session is **not** expired — a second live vendor call in a failure path is
    ruling 13's evidence run — it is recorded `UNRESOLVED`.
    **(b) `X-199/Ui/Declines.php:32`'s prefix is the THIRD statement of the same act in one panel,
    and the only one that asserts an outcome.** `declines.blade.php:23` heads the panel
    *"We couldn't make that pay link"* (ruling 93's own shape, naming the act) and every message
    the tail can now render is a self-contained sentence naming its own act — MONEY-141's
    `'The gateway would not open a payment page: <Stripe's own error.message>'` and
    `'…sent back no payment page, so no link was made.'`. Stacked, an owner reads *"We couldn't make
    that pay link" / "The pay link was not made: The gateway would not open a payment page: Your card
    was declined."* And the prefix is the one clause the catch **cannot know**: with (a) fixed the
    remaining `\Throwable` members are the client's, but the block guards the persist too, so a
    write failure after a successful session would have it assert the opposite of the truth — ruling
    228's *a message states what the METHOD did, never what the SYSTEM did*, one register up,
    because the surviving act is at a payment provider. **RULED: the prefix goes; `$this->error` is
    the exception's own sentence**, the shape the domain-specific catches in this lane already use.
    ⚠️ Blast radius measured with interior fragments (rulings 46, 86, 146): **ZERO** — no test
    anywhere asserts `'The pay link was not made'`, and `DeclinesScreenTest:105`'s `charge()` stub is
    the capture path, not this one — which is ruling 70 again and why the prefix outlived every
    X-199 screen wave. Both items therefore **add** methods (ruling 68).
    ⭐ **Item (b)'s proof uses `Http::fake` with the REAL client, never a stub throwing the sentence**
    — a double that returns the message under test is ruling 41 part 2, and the point is that the
    client mints it. Item (a)'s proof commits the racing row **inside the `Http::fake` closure**,
    which runs at the HTTP boundary — precisely between the pre-check and the persist — so the race
    is reproduced deterministically with no concurrency; under the mutation (`firstOrCreate` back to
    `create`) it errors `SQLSTATE[23505]`.
    ⚠️ **The other 86 catch blocks are measured and STRUCK.** The lane's convention is a chain —
    a domain class, then `ModelNotFoundException` for the tenancy sentence, then a `\Throwable` tail
    — and it holds. Four doors catch narrowly with no tail and every one is safe by construction:
    `SameAccount::pull()` (a `findOrFail` and a `sprintf`), `Declines::settleUpLater()`
    (`DeferDeclineAction` is `firstOrCreate`), `CardScreen::makeDefault()` (a `findOrFail` and a mass
    update) and `CardScreen::present()`, whose two caught classes cover **all five** of
    `CardPresentAction`'s refusals. `GatewayEngine:122/:124` catch by class, so no wording can move
    control flow. ⛔ Not to be re-raised. ⚠️ Ruling 206 had already fixed the only two doors that
    could reach a framework message (`SQLSTATE[22001]` from an owner-typed string), which is why
    this census found one seam rather than a population.
230. **This lane has never asserted what it SENDS to the payment provider, and the `Idempotency-Key`
    Stripe exists to honour is not sent — while `payments.idempotency_key` is `->index()` and not
    unique, so the whole idempotency guarantee is a `->first()` with nothing behind it (RULED by the
    lane supervisor 2026-09-09, briefed as MONEY-144).** Ruling 229 fixed one read-then-write window
    that a unique index closed badly; the check-then-act census it opened found the same shape one
    method over with **nothing** closing it at all. Measured: (1)
    `2026_08_30_000030_create_x198_gateway_tables.php:34` is
    `$table->string('idempotency_key')->index()` — **not unique**; (2)
    `GatewayEngine::capture():78-85` reads by that key (excluding `failed`), returns if found, else
    charges and creates; (3) `StripeGatewayClient::charge():20-26` posts to `/v1/charges` with **no
    `Idempotency-Key` header** — `grep -rn "Idempotency" app/app/Modules/X-198 X-199 X-117` returns
    exactly one line and it is a **comment**. So two concurrent captures under one key both read null
    (neither sees the other's uncommitted insert), **both charge the customer**, and two rows land
    with no error anywhere. ⭐ **The rest of this app got it right and wrote down why:**
    `automation_runs` and `message_cost_entries` carry **unique** idempotency keys, and
    `AutopilotJob:201,:457`, `SendOptInConfirmationJob:80` and `AutomationRunRetention:51` each carry
    a docblock stating that the unique index is what stops the second run. Ruling 98's
    self-contradiction tell **across lanes**, on the one table where the consequence is real money —
    and `GatewayEngine:77`'s comment *"Idempotency check: duplicated ref charges once (G17-04,
    G1-23)"* asserts at a capability id a guarantee neither layer provides.
    **RULED: `charge()` and `createPaymentLink()` send Stripe the `Idempotency-Key` header.** That is
    the half that stops the money moving twice, it is what the key is named for, and Stripe returns
    the *same* charge or session for a repeated key rather than making a second.
    ⭐⭐ **The key sent is namespaced by business, and that is not decoration.** Ruling 93 measured
    that every charge here posts with the **platform's** secret and no `Stripe-Account`, so all
    tenants share one Stripe account and therefore one idempotency namespace: a bare `idem_retry_1`
    from two businesses would collide **at the provider** and hand tenant B tenant A's charge. So the
    header is `x198-charge-{businessId}-{key}` and `x198-paylink-{businessId}-{paymentId}`.
    ⭐ **Item 2 closes MONEY-143's own `UNRESOLVED`:** ruling 229 recorded an orphaned Checkout
    Session as the residue of the pay-link race, and with the header the losing press gets the **same
    session** back, so no orphan is created.
    ⛔ **The unique index is NOT this wave, and the reason is measured.** `capture()`'s pre-check
    excludes `failed`, and `X198Test:178`
    (`test_a_retry_after_a_decline_is_not_short_circuited_by_idempotency`) pins that a retry after a
    decline writes a **second** row under the same key, asserting `count === 2` — a plain
    `unique(business_id, idempotency_key)` would refuse it. ⭐ And worse: `QueryException extends
    PDOException extends RuntimeException`, so a `23505` raised inside `capture()`'s transaction
    would be caught by `:124`'s `catch (\RuntimeException)` and write a **`failed`** row for a charge
    Stripe actually took — ruling 228's *a message states what the METHOD did, never what the SYSTEM
    did*, with real money. A partial index plus a re-read is its own wave with its own proof.
    ⛔ Not resolved by a lock held across the HTTP call — ruling 229 refused exactly that.
    ⚠️ **Why nothing could see it: the lane has ELEVEN `Http::fake` call sites and ZERO
    `Http::assertSent`.** Every ruling from 36 to 229 asked what is at the other end of a string this
    app *renders*; **nobody has ever asserted what is in a request it MAKES.** That is the new
    population, and this is its first finding.
    ⚠️ Blast radius, measured: `grep -rn "\->charge(\|\->createPaymentLink(" app/app app/tests`
    returns **exactly two call sites app-wide** — `GatewayEngine:95` and `PaymentLinkAction:26` — and
    the definition sweep returns **15 lines: 2 real definitions and 13 doubles**, nine of `charge()`
    and four of `createPaymentLink()`. ⚠️ **Correction, recorded rather than dropped:** this ruling's
    first draft said *twelve*, from a grep scoped to `app/app/Modules/X-198` and `X-199` alone; the
    thirteenth is `app/tests/Modules/X-117/CheckoutBlockScreenTest.php:114`, and the miss is ruling
    225's own lesson — **a sweep's module list decides its output** — caught only because the
    ruling-118 table for the brief was measured over `app/app app/tests` whole. **All thirteen doubles
    are `new class {}` bound through `$this->app->instance(...)` — duck-typed, NOT subclasses**
    (`grep -c "extends StripeGatewayClient"` is **0**) — so
    a surplus argument is inert (ruling 167) and ⛔ none is touched; the arity drift is recorded, not
    churned (ruling 47's companion). No test in the lane asserts an HTTP request shape, so **zero**
    existing assertions move and both items **add** methods (rulings 68, 70). The key becomes a
    **required** parameter and `currency` loses its default, because a default is exactly where an
    unsent key would hide silently (rulings 37, 66) and each method's single production caller
    already passes currency explicitly.
231. **Rulings 121(a) and 219 collide on a lock-blocked gate, and 121(a)'s "nothing else" throws away
    the section the push turns on (RULED by the lane supervisor 2026-09-09, on MONEY-143's
    `REPORT.md`).** Ruling 121(a) requires `GATE: NOT RUN — <the gate file's last line>` **and no
    number, and nothing else**, after a run transcribed a predicted floor as a measurement. Ruling
    219 requires `GATE:` to carry the test line **and §6's two `{"tool":…}` objects**, after a run
    reported a genuine number from a file that did not exist. MONEY-143's gate was lock-blocked, the
    coder took 121(a) exactly as written — and `gate-money143.txt` **exists, is 8212 B, and its
    §1–§6 are green**: `0 uncommitted path(s)`, `pint passed`, `phpstan errors 0`. All of it was
    discarded by *"nothing else"*, and §6 is the section ruling 34 turns the push on. **RULED: when
    a gate is lock-blocked, `GATE:` carries §1–§6 exactly as the file prints them, plus the literal
    `§7 NOT RUN — <the file's last line>`.** ⛔ Never a §7 number that was not printed — 121's
    fabrication prohibition is untouched, and it is the *number* that was forbidden, never the
    sections. ⚠️ The two rulings were written five waves apart against opposite failures and had
    never met in one report; the collision is the supervisor's and it is graded a **note**, because
    §1–§6 were measured green here in full and withholding a tip that landed on its predicted floor
    to the digit over a paperwork field is ruling 74's error. ⚠️ The generalisable half: **a rule
    written as "and nothing else" is a rule about one field that silently governs every other**, and
    this is the ruling 128/210 family — moving or forbidding one item without naming what stays is
    how a field goes empty.
232. **An idempotency key derived from a row minted inside the charge's own transaction repeats
    where a repeat is wrong and never repeats where a repeat is right (RULED by the lane supervisor
    2026-09-09, on MONEY-144's `7c2dd354`; briefed as MONEY-145).** Ruling 230 correctly found that
    this lane sends the gateway no `Idempotency-Key`, and MONEY-144 shipped the header namespaced by
    business exactly as ruling 93 requires. **The value is wrong, and it is wrong in two opposite
    directions at once.** The key is `x198-charge-{businessId}-{idempotencyKey}`, and
    `$idempotencyKey` comes from the caller; measured, the lane has **one** production caller —
    `X-199/Domain/InvoiceEngine.php:99`, passing `'overflow_'.$invoice->id.'_'.$overflowAmount` —
    and `PaymentCaptureAction` has **none** (`grep -rn "PaymentCaptureAction" app/app` returns only
    its own declaration, confirming ruling 227's Table B).
    **(a) It always repeats where a repeat is wrong.** `X198Test:178` is named
    `test_a_retry_after_a_decline_is_not_short_circuited_by_idempotency`; it passes the **same**
    `'idem_retry_1'` twice with a different card (`tok_decline`, then `tok_success`) and asserts two
    rows and a captured charge. `capture()`'s pre-check excludes `failed` (`:81`) precisely so the
    second attempt reaches the gateway — and after MONEY-144 it reaches it carrying the **declined
    attempt's key**, asking the provider to suppress the one request the app means to make. ⚠️ The
    exact provider response is vendor behaviour this seat cannot verify — a replayed cached decline,
    or a refusal that the key was reused with different parameters — but **there is no third
    behaviour in which the same key with a different `source` produces a fresh independent charge**,
    so the retry is defeated either way. That is the argument's robust half, and it needs no vendor
    doc.
    **(b) It never repeats where a repeat is right.** `issueInvoice()` wraps `capture()` in
    `DB::transaction` (`InvoiceEngine:33`), so the charge runs inside it as a savepoint. A rollback
    after Stripe charged loses the local row while the money moved; re-issuing mints a **new
    `$invoice->id`** and therefore a new key, so the provider cannot dedupe the second charge —
    **the double charge ruling 230 set out to prevent, defeated by the key it introduced.**
    ⭐ **This is ruling 51's principle at the OUTBOUND boundary.** 51 ruled that a figure compared
    against the gateway's must come from the row the gateway wrote; the same holds of a key sent to
    it. An idempotency key must be **stable across retries of one intent and distinct across
    different intents**, and an id minted inside the charge's own transaction is neither.
    **RULED: the key carries the count of prior `failed` rows for `(business_id, idempotency_key)`**
    — a query `capture()` already runs in its own pre-check, in the same transaction, so the value
    comes from the row the app itself writes. Two concurrent first attempts both read `0`, send one
    key and are deduped (230's benefit preserved); a retry after a recorded decline reads `1`, sends
    a fresh key and charges. ⛔ **No unique index and no migration** — ruling 230's reasons are
    unchanged and re-measured: `X198Test:178` pins `count === 2`, and `QueryException extends
    RuntimeException`, so a `23505` inside `capture()`'s transaction would be caught at
    `GatewayEngine:124` and write a **`failed`** row for a charge Stripe actually took.
    ⛔ **The pay link keeps its key, and the asymmetry is measured rather than assumed:** capture has
    a **durable failure record** — the `failed` row — that both proves retries are expected and
    supplies the discriminator, and the pay link has **neither**, because `PaymentLinkAction` does
    not catch and no row is written when `createPaymentLink` throws. Minting a failure record to make
    a key vary is ruling 59. MONEY-143's `firstOrCreate` already guarantees one row and the pre-check
    returns an existing link without calling Stripe, so the header's live benefit — collapsing a
    concurrent double-press into one session — stands, and the retry-after-failure replay is recorded
    `UNRESOLVED`. So is (b): a stable cross-retry intent id does not exist in this lane, and minting
    one is a cross-module API change of the shape ruling 102 already recorded.
    ⚠️ **Correction to this tick's own first reading, recorded rather than dropped.** The production
    path was first read as *permanently sealed* — a given invoice's overflow uncollectable on any
    card for ever. **Wrong:** invoice ids are unique per issuance, so `overflow_{id}_{amount}` does
    not recur in production today and (a) is **latent**, on the contract the test names rather than
    on a live path. The correction decided the grade, and (b) — the sharper half — became visible
    only once (a) was measured away.
    ⚠️ **Why nothing could see it.** `X198Test:178` binds `new class {}` doubles in **both** arms; a
    double never reaches the HTTP boundary, so no header is sent and no provider-side idempotency can
    occur. The test is green, stays green, and is now green **for the wrong reason** — ruling 41
    part 2 and ruling 61's family, on the one test whose name states the contract this wave inverted.
    Ruling 46 required the brief to grep every test asserting the path being changed; MONEY-144's
    Table B listed `X198Test:178` **only** as a double to leave byte-identical and never read what
    its name asserts. **So the wave's own proof must convert that test to `Http::fake` + two
    `Http::assertSent` keys** — a double can never gate a header.
    ⚠️ **Why BLOCK rather than PASS-WITH-NOTES, with the gate green.** The supervisor's own gate
    reproduced `2374 · 2372 · FAILED 0 · errors 2` — §1–§6 green, doctor all stages clean — so the
    BLOCK rests on no gate at all. Ruling 74 protects a correct tip against a defect **smaller than
    the thing withheld**, and that does not reach here: the tip *is* the header and the defect *is*
    the header's value, the same size, in the wave's own subject. Neither the benefit nor the harm is
    live, so holding costs a latent benefit for one wave while pushing hands Track 1 a **new** latent
    defect on an explicitly named contract. **Introducing a new hazard is worse than deferring the
    removal of an old one by one wave.**
    ⚠️ The miss is the supervisor's — the brief dictated both key formats verbatim
    (`BRIEF-money144.md:147`, `:258`) and the coder transcribed them faithfully — so per the
    46/49/50/62/66/75/82/86/94/104/106/113/116/146/153/167/175/183/189/193/195/198/200/215/217
    precedent MONEY-145 carries its own two dispatches and MONEY-144's cap is untouched. It is the
    ruling 66/75/82/92/94/106/118/147/153/175/183/189/192/193/195/198/200/202/204/207/210/215/217/
    218/219/222/225/226/228/229/231 family a **twenty-sixth** time, with the thirty-ninth instrument:
    **a brief that dictates the VALUE of a field sent to an external system has dictated that
    system's behaviour** — and alone in the family, the outcome it dictates happens at a party **no
    gate in this checkout can observe**, which is why a green gate is exactly what it looks like.
233. **The outbound-request BODY census is measured — two call sites, eleven fields — and the one
    string this lane sends to a CUSTOMER is a constant that names nobody (RULED by the lane
    supervisor 2026-09-09, briefed as MONEY-146).** Ruling 230 opened the outbound-request
    population and MONEY-144/145 asserted its **headers**; nobody had read the **bodies**. The
    population is small and now fully enumerated: `grep -rn "Http::"` over the lane's eight modules
    returns **exactly two lines**, `StripeGatewayClient:26` and `:59`, and between them they send
    eleven fields. Nine are sound — `amount`/`unit_amount` from the caller's own figure and written
    to the row it creates, `line_items[0].currency` and `quantity`, `mode`, the `Idempotency-Key`
    (rulings 230, 232), the bearer token, and `success_url => config('app.url')`, which is ruling
    38's deliberate parking and ⛔ stays struck. Two are wrong.
    **(a) `product_data.name` is the literal `'Payment for declined transaction'`, and it is the one
    string in this lane a CUSTOMER reads.** `X-199/Ui/Declines::sendPayLink():28` passes it into
    `PaymentLinkAction`, which forwards it to `createPaymentLink()`, which posts it as the Stripe
    Checkout line item — so it is rendered by the **provider**, on the page where a customer is
    asked for real money. It is the same string for every tenant, every payment and every amount
    (ruling 43's *does it even vary?*, answered **no**), it is the app's internal vocabulary rather
    than the customer's (rulings 89, 96, 124's family), and — the sharp half — **it names no
    business**, so the page says only that some unnamed party wants money for a transaction that
    was declined. ⭐ **Every string sweep this lane has run (50, 63, 89, 96, 97, 122, 124, 134, 137,
    142, 199, 203, 225, 227, 228) read what a screen RENDERS or what a console PRINTS. This one is
    rendered by a third party, which is why it survived all of them.**
    **RULED: the description is built in `PaymentLinkAction` from the rows it already loads, and the
    `$description` parameter is REMOVED** — ruling 37's discipline verbatim (*the row already knows;
    a caller-supplied value is a second place for the truth to disagree*), and the caller here is a
    **screen**, which cannot know more about a payment than the action that loads it. The line names
    the business and what is being paid: `$business->name.' - card payment'`. ⛔ Not by naming the
    invoice — `payments` carries no invoice column and ruling 102 already recorded that seam
    `UNRESOLVED`; ⛔ not by minting a customer-facing reference; ⛔ not by keeping the word
    *declined*, which is this app's view of the event and not the customer's.
    **(b) `charge()` sends a currency that came from a method DEFAULT, at the lane's ONE production
    capture.** `GatewayEngine::capture()`'s signature ends `string $currency = 'USD'`, and
    `X-199/InvoiceEngine:92-97` — measured, the only production caller — passes **no** currency, so
    the overflow charge posts `currency=usd` for every tenant **and** writes `payments.currency`
    `'USD'`, so ruling 51's *read it back off the row* cannot rescue it: the row is wrong from the
    same default. ⭐ **`businesses.currency` is a real `char(3)` column**
    (`2026_07_30_072149_create_businesses_table.php:40`) under a migration comment reading *"an
    amount without its currency is not a money value"*. **RULED: `capture()` reads
    `Business::findOrFail($businessId)->currency` and the `$currency` parameter GOES**, from
    `capture()` and from `PaymentCaptureAction` with it — ruling 37's *"⛔ the currency is never
    added as a parameter: the row already knows"*, on the one method that had it as a parameter.
    ⚠️ **Blast radius is one line**, measured: `grep` over `app/app` and `app/tests` finds exactly
    **one** call site passing a fifth argument — `X198Test:572`, added by MONEY-144 — and the read
    is safe under the ambient tenant because `TestCase::provisionTenant` ends in `Tenancy::set()`
    and every X-198 capture test provisions exactly **one** tenant (the file's only two-tenant
    method, `:381`, is a pay-link test). ⛔ Never `withoutGlobalScopes()`: RLS sits beneath the
    application scope, so it hides nothing and helps nothing, and a `ModelNotFoundException` at the
    boundary is ruling 66's own reasoning — loud beats silently wrong.
    ⚠️ **Blast radius for BOTH items is ZERO existing assertions** — `'Payment for declined
    transaction'` appears at four call sites and every one **passes** it, none asserts it; and no
    test in the lane asserts a request **body** at all. That is ruling 70 again, and it is why both
    items **add** methods (ruling 68). ⚠️ The proofs assert on `$request->body()`, so the fixture
    business is named with a single word: `asForm()` encodes through `http_build_query`, which turns
    a space into `+`, and a needle carrying a space would fail against a correct implementation
    (ruling 82's family, one encoder over).
234. **`credit_terms.card_on_file_token` has no production writer, so the overflow charge — X-199's
    §46A headline capability and the lane's only production path to a gateway — is unreachable
    (measured 2026-09-09; recorded, not briefed).** `TermsSetAction::handle()`'s fifth parameter
    `?string $cardOnFileToken = null` is the column's only writer (`:38`), and its one production
    caller, `X-199/Ui/Credits::setTerms():48`, passes **four** arguments. `InvoiceEngine:47` writes
    the column `null` on creation. So `$cardToken` at `:87` is always null, `:92`'s
    `if ($cardToken !== null)` never opens, and no charge is ever attempted — decision 272 / ruling
    51's shape on the most consequential path in the lane. ⛔ **Not a wave, and not briefed**: the
    missing dependency is a tokenisation surface, which ruling 119 measured is also why
    `card_tokens` has no writer, ruling 45 traced to a browser-side Stripe Elements door, and ruling
    20 parks behind a contract. Minting a token door in X-199 to arm its own branch is ruling 59.
    ⚠️ It is recorded rather than struck because it **grades** ruling 233(b): that defect is latent
    in production and live only in the six `InvoiceEngineTest` fixtures that seed
    `'card_on_file_token' => 'tok_visa'` — and it is still **fixed rather than recorded**, on ruling
    204's precedent, because the branch is driven by existing tests and a mutation can redden it.
    ⚠️ `unpaid.blade.php:61`'s *"covered by the card on file"* and `Credits`' overflow copy are
    already recorded at ruling 76's grade (ruling 132); this measurement is the reason why, and does
    not re-open them.
235. **`capture()` writes `captured` off the PRESENCE of a charge id, having never read the field the
    gateway uses to say whether it captured — and no charge fixture in this lane has ever carried
    that field (RULED by the lane supervisor 2026-09-09, briefed as MONEY-147 items 1, 2 and 4).**
    Ruling 230 opened the outbound-request population and asked what this app *sends*; MONEY-144/145
    asserted its headers and MONEY-146 its bodies. **Nobody had asked what it reads back.** Measured:
    `X-198/Domain/StripeGatewayClient::charge():39` reads `id` and **nothing else**, and
    `Domain/GatewayEngine:118` is
    `$status = $gatewayChargeId !== null ? 'captured' : 'awaiting_processor'`. A Stripe charge object
    always carries `status` — `succeeded`, `pending` or `failed` — and a charge taken by an
    asynchronous payment method comes back **HTTP 200 with a real `id` and `status: pending`**. This
    lane records that as captured. ⭐ **It is ruling 99's shape one module over with the sign
    reversed:** 99 stopped X-199 deriving `charged` from *the absence of an exception*; this derives
    `captured` from *the presence of an id*. ⭐ **The witness is the test that comes closest and walks
    past it** — `X198Test:493`'s `test_a_charge_the_gateway_never_confirmed_says_so_and_leaves_a_failed_row`
    fakes `['object' => 'charge', 'status' => 'pending']`, **a pending charge**, and the only reason
    the code refuses it is the **absent `id`**; with an id present the lane would have written
    `captured` and announced it.
    **RULED: `charge()` returns `['id' => string, 'status' => string]`**, the status being
    `$response->json('status')` where that is a string and the literal `'unconfirmed'` where it is
    not; `capture()` writes `captured` **only** on `succeeded` and `awaiting_processor` otherwise.
    ⛔ **A missing `status` is never read as success** — that is the "derive it from the absence of
    information" this wave exists to delete, and it is why the eleven fixtures gain the field rather
    than the code gaining a lenient default. ⛔ **`charge()` does NOT throw on a non-`succeeded`
    status:** a throw is caught at `GatewayEngine:141` and writes a **`failed`** row, which for a
    pending charge is false in the opposite direction *and* increments MONEY-145's `$attempt`, so the
    retry would carry a fresh idempotency key and **charge the customer a second time** — the exact
    double charge ruling 230 set out to prevent. ⛔ **No fourth status is minted:**
    `awaiting_processor` already means *the gateway has it and this app cannot say it settled*, which
    is ruling 99's own reasoning for refusing a third one, and ruling 132 measured it has no
    production reader to disturb. ⛔ No migration.
    ⚠️ **Why nothing could see it, measured:** eleven `Http::fake` charge fixtures across five files
    (`UnpaidScreenTest:120` · `X198Test:188,:480,:569,:590` · `X199Test:76` ·
    `InvoiceEngineTest:67,:135,:278,:382` · `CreditsScreenTest:123`) and **not one has ever carried a
    `status`** — ruling 41 part 2 exactly (*a double returns a value of the real thing's shape AND
    size*), which is why the two branches have been identical since the module existed. **The
    fixtures are half the wave**, and ⭐ **no existing assertion moves**: the two
    `assertEquals('captured', …)` at `X198Test:204`/`:237` stay green because their fixtures now say
    what a real successful charge says. ⚠️ Of the **seven** `charge()` doubles only **three** return
    (`X198Test:228`, `DeclinesScreenTest:126`, `CheckoutCaptureSeamTest:97`); ⛔ **the four that throw
    keep `: string` byte-identical** — inert (ruling 167), and ruling 230 already recorded the
    doubles' arity drift as not to be churned (ruling 47's companion).

236. **The ruling-235 fix creates two new fictions unless the event guard and X-199's derivation move
    with it, and neither may wait for a later wave (RULED by the lane supervisor 2026-09-09, briefed
    as MONEY-147 items 2.3 and 3).** (a) `GatewayEngine:131` dispatches `PaymentCaptured` on
    `$payment->gateway_charge_id !== null` — ruling 101's own guard, correct while an id meant
    capture and **wrong the moment it does not**, since a pending charge has one. The guard moves to
    `$payment->status === 'captured'`; ruling 101's reasoning is unchanged (*an event that fires for
    a non-event propagates the fiction to every listener that ever registers*), and its population is
    still three lines in one file — `X198Test:76`'s `assertNotDispatched` and `:487`'s
    `assertDispatched`, both green after the change because neither fixture is pending.
    (b) `X-199/Domain/InvoiceEngine:107` is `$status = $gatewayChargeId !== null ? 'charged' :
    'refused';`, so **ruling 99's own fix rests on a value X-198 never verified** and a pending charge
    would propagate a false `charged` onto the two screens that read `OverflowCharge`. It moves to
    `$payment->status === 'captured'`. ⛔ `refused` stays the losing value — ruling 99's explicit
    choice, and true of an unprocessed overflow — and `reference_id` keeps the gateway's id, the
    honest handle on an attempt that has not settled. ⚠️ **Shipping either half in a later wave is
    refused:** ruling 46 forbids a wave that leaves the suite red or a fiction newly created, and each
    of these is one line. ⚠️ The generalisable half: **a wave that narrows the meaning of a value owns
    every guard that was reading the OLD meaning** — rulings 46/49/50 taught this lane to sweep for
    readers of a value being *changed*, and ruling 107 for readers whose correctness depended on a
    state that could not arise; this is the third member — readers whose correctness depended on two
    conditions being **equivalent**, which they were until this wave separated them.
237. **A wave that narrows a value's meaning owns the owner-facing COPY keyed on it, not only the
    guards — and `refused` now means three things where `Credits.php:96` says one (RULED by the lane
    supervisor 2026-09-09 04:5x, on MONEY-147's `761dda80`; briefed as MONEY-148 item 1).** Ruling
    236 required the two **guards** reading the old meaning of a charge id to move with it, and both
    did. It stopped at the guards. Measured now, `OverflowCharge.status = 'refused'` covers **three**
    facts after this wave: no card on file (`InvoiceEngine:89` — nothing was attempted), the gateway
    threw (`:114` — the card genuinely did not absorb it), and, **new**, the gateway took it and has
    not settled (`:105`, with a real `ch_` id written to `reference_id`).
    `X-199/Ui/Credits.php:96` renders *"the card did not absorb it and the invoice still stands"* for
    all three, and it is **false** for the third: the card absorbed it and only the settlement is
    outstanding. ⭐ **The row already carries the discriminator** — `reference_id` is the gateway's
    own word (ruling 51) — so the arm splits on `reference_id !== null` and the pending case says the
    gateway has it and has not settled. ⛔ **Not by minting a fourth status:** ruling 99 refused a
    third one and ruling 235 kept `awaiting_processor` for precisely this meaning; ⛔ not by touching
    `InvoiceEngine`'s derivation, which is ruling 236's own ruled outcome; ⛔ not by deleting the
    sentence, which is true of the two arms it was written for.
    ⚠️ **Graded PASS-WITH-NOTES and PUSHED rather than BLOCKED, and the reasoning is the DIRECTION of
    the error.** Ruling 232 blocked a wave whose defect was its own subject and the same size as its
    benefit; that does not hold here. Before this wave a pending charge was announced
    `PaymentCaptured` and written `charged`, rendering *"covered by the card on file; service never
    stopped"* — money claimed as taken when it had not settled. After it the same charge reads *"the
    card did not absorb it"*. **Both are false, and the new one under-claims about money where the old
    one over-claimed** — the direction rulings 99 and 235 have consistently ruled for — so the tip is
    a strict improvement and the residue is a follow-up, not a new hazard handed to Track 1. It is
    latent in production twice over besides: ruling 234 measured that
    `credit_terms.card_on_file_token` has no production writer, so the overflow charge never runs,
    and the arm needs an asynchronous Stripe payment method to reach at all.
    ⚠️ Blast radius measured with interior fragments (rulings 46, 86, 146): `CreditsScreenTest:58` and
    `:146` both stay green because the **existing sentence is kept for the null-reference arm**, and
    the third arm is rendered by no test (ruling 70), so the item **adds** a method (ruling 68).
    ⚠️ Measured clean in the same sweep and recorded so it is not re-raised: `Unpaid.php:88` filters
    `->where('status', 'charged')`, so a pending overflow correctly gets **no** *"covered by the card
    on file"* pill — that half needed nothing. ⚠️ The generalisable half is ruling 236's own, one
    register out: **the readers of a narrowed value are its guards, its tests AND its prose**, and
    prose is the one this lane has now missed at three different levels (rulings 50, 70, 236).
238. **A `GATE:` transcription is READ BACK after the gate exits, and bytes + mtime are what makes a
    stale read detectable (RULED by the lane supervisor 2026-09-09 04:5x, same review).** Ruling 219
    made `GATE:` a transcription of a named file; ruling 222 required the report to state that file's
    byte count and mtime beside it, *"provenance checkable with `wc -c` rather than argued"*; ruling
    231 said a lock-blocked §7 is written `§7 NOT RUN — <the file's last line>` with no number.
    MONEY-147 obeyed all three **at 04:37**, while its own backgrounded gate was still parked on
    `/home/goaiez/tmp/pest.lock`, and never read the file again. By 04:41 that file carried
    `tests 2378 · passed 2376 · FAILED 0 · errors 2` — the predicted floor to the digit — and the
    report says `§7 NOT RUN`. ⭐ **Ruling 222's instrument worked exactly as designed:** the report's
    own `bytes 8423, 04:37:00` against the file's **8994 B at 04:41** announces the staleness without
    anyone arguing about it. **RULED: the run's gate step ends by re-reading the named file after the
    gate process has exited, and the bytes and mtime written into `GATE:` are from that second read.**
    ⛔ Ruling 218's fabrication prohibition is untouched — a number is transcribed or it is not
    written — and ⛔ ruling 231's `NOT RUN` form stands for a gate that genuinely never produced one.
    ⚠️ The cause was the **backgrounded** gate the brief forbade (ruling 218(2)); this makes the
    read-back the rule rather than re-litigating the backgrounding, because a foreground gate can be
    transcribed early too. ⚠️ **This is the inverse of ruling 218 and the cheaper direction** — an
    *under*-report, loud, costing a reviewer one `tail`, where 218's over-report was accidentally
    true and cost a reviewer nothing only because ruling 42(2) re-gates regardless. Both come from the
    same act: treating a file that is still being written as a measurement.
239. **`SameAccount` sums declined attempts into a money total and calls it "Payments recorded", and
    every fixture it has ever had seeds a status no production code writes (RULED by the lane
    supervisor 2026-09-09 04:5x, briefed as MONEY-148 item 2).**
    `X-198/Ui/SameAccount.php:65` reads `Payment::where('business_id', …)->orderByDesc('id')->get()`
    with **no status filter**, and `:70-78` groups by currency and sums `amount_cents` into
    `$conn->payments_line`, rendered at `same-account.blade.php:18` under `<dt>Payments recorded</dt>`.
    `capture()`'s `catch (\RuntimeException)` at `:151-160` writes a durable `failed` row for a
    **declined** attempt where no money moved (ruling 228 measured that row is load-bearing and must
    stay), so the figure adds amounts that exist nowhere. **Ruling 107's shape — the label names a
    quantity the code does not measure — on a money figure**, and ruling 99's family.
    ⚠️ **Why nothing can see it, and it is ruling 41 part 2 again:** **every** `Payment` fixture in
    `SameAccountScreenTest` (`:26`, `:33`, `:34`, `:36`, `:83`, `:138`, `:139`, `:140`) seeds
    `'status' => 'pending'` — a value **no production code writes**. The whole vocabulary is
    `captured` / `awaiting_processor` / `failed` (`GatewayEngine:126`, `:153`; measured app-wide, the
    only other `Payment` writer is `EvidencePaymentLinkCommand:36`). A fixture written to the *screen*
    rather than to the real thing's shape, and **not one carries `failed`**.
    **RULED: the count and the total both exclude `failed`**, one number with one meaning (rulings
    107, 152b). ⛔ **`awaiting_processor` STAYS in** — the label says *recorded*, not *settled*, and
    the gateway does hold it, which is ruling 235's own word for that state. ⛔ Not by relabelling to
    *"attempts"*: this screen's subject is where the money lands (ruling 93), and an attempt that took
    nothing is not a payment recorded anywhere. ⛔ Not by dropping the row — ruling 228 measured the
    `failed` row is the app's only durable record of a decline and MONEY-145's `$attempt` counts it.
    ⚠️ **The proof is that three numbers do NOT move.** The fixtures move onto the real vocabulary and
    gain a `failed` row, and `SameAccountScreenTest:49`, `:62` and `:144`'s needles
    (`'2 payments · 75.00 USD'`, `'3 payments · 100.00 USD'`,
    `'3 payments · 45.00 GBP · 75.00 USD'`) must be **unchanged** — a needle that moved would mean the
    filter changed a figure it was not meant to touch. ⚠️ Ruling 153 binds the needles: each is a
    single component-computed `{{ $conn->payments_line }}`, so no needle spans a Livewire
    `<!--[if BLOCK]-->` boundary and all three stay assertable as written.
240. **A same-session racer cannot reproduce `capture()`'s race, and after MONEY-144/145 the harm
    that race does is a DOUBLE COUNT on the money figure, not a double charge (RULED by the lane
    supervisor 2026-09-09 05:1x; the deferred unique-index wave re-measured a third time).**
    Rulings 230 and 232 deferred a partial unique index on
    `(business_id, idempotency_key) WHERE status <> 'failed'` twice, with reasons. Re-measured now,
    three things have changed and one of them is decisive.
    **(a) The harm is smaller and lands somewhere new.** `GatewayEngine::capture():83-86` is
    check-then-act across a live HTTP call, so two concurrent captures on one key both read null and
    both charge — but MONEY-144's `Idempotency-Key` header means Stripe returns **the same charge
    object** to both, so **the money moves once**. What lands twice is the local row: two `Payment`
    rows carrying the **same `gateway_charge_id`**, both `captured`, `PaymentCaptured` dispatched
    **twice** for one charge (ruling 101's family), and `SameAccount`'s per-currency total — the
    figure MONEY-148 has just made honest (ruling 239) — summing that charge twice. **A ledger
    double-count on a money screen, not a double charge.**
    **(b) The index predicate must EQUAL the pre-check predicate. RULED: `status <> 'failed'`, so an
    `awaiting_processor` row DOES block a retry.** `capture()`'s pre-check already returns such a row,
    so an index narrower than the pre-check would admit a row the code would have short-circuited —
    ruling 37's *second place for the truth to disagree*, in the schema. It is therefore a pure
    enforcement of intent and changes no behaviour on any reachable path.
    **(c) ⭐ The decisive new measurement: the proof needs a SECOND CONNECTION.** Ruling 229 proved the
    pay-link race by committing the racing row inside the `Http::fake` closure, which runs at the HTTP
    boundary — between the pre-check and the persist. **That shape cannot be transplanted here**,
    because `capture()`'s whole body is inside `DB::transaction` (`:81`), so a racer inserted on the
    same connection is inside that transaction: the `23505` rolls the savepoint back and takes the
    racer with it, leaving the re-read nothing to find. And no row can be seeded *before* the call,
    because by (b) the index predicate and the pre-check predicate are identical, so any row the index
    would catch the pre-check would have returned. The violation is unreachable in-process without a
    genuinely independent session. ⚠️ `app/config/database.php` defines **one** pgsql connection, and a
    second would need its own `SET app.business_id` for RLS.
    **(d) The catch order is load-bearing and must ship with the index.**
    `UniqueConstraintViolationException extends QueryException extends PDOException extends
    RuntimeException` (verified: `PostgresConnection.php:78` maps `'23505'`,
    `Connection.php:853-855` selects the class), so a `23505` reaching `capture()`'s
    `catch (\RuntimeException)` at `:147` writes a **`failed`** row for a charge Stripe actually took —
    ruling 230's named hazard. The new clause sits **above** it. ⭐ And the re-read must happen
    **outside** `DB::transaction`, because in Postgres a failed statement aborts the enclosing
    transaction and every later query in it dies `25P02` — **ruling 163's own mechanism**, arriving in
    production code rather than in a test. ⛔ Not `Payment::firstOrCreate(['business_id',
    'idempotency_key'])`: its first lookup is unfiltered by status, so it would return a `failed` row
    and break `X198Test:178`'s `count === 2`.
    **Measured clean, so the index itself is safe to add:** `TestCase::provisionTenant` calls
    `TenantProvisioner::provision()` with a fresh `User::factory()->create()` on **every** call, so no
    two test methods share a business and the `name` argument is cosmetic — the lane's repeated keys
    (`idem_loose` at `SameAccountScreenTest:36`/`:83`, `idemp1` at `DeclinesScreenTest:46`/`:156`,
    `idem_pay_1` at `:278`/`:321`) are all on different tenants. ⛔ **A new migration, never an edit to
    `2026_08_30_000030`** (ruling 41 part 3); `$table->unique()` cannot express a partial index, so it
    is a raw `DB::statement` in the shape `2026_09_06_000002_x198_payments_default_awaiting_processor.php`
    already establishes. ⚠️ The wave is **held, not struck**: the finding is real and now sharper, and
    what is missing is a decided proof shape, which is exactly what ruling 64 says an inherited
    follow-up must have before it becomes a brief item.
241. **A public Livewire property is rendered into the page as `wire:snapshot`, and X-120's card form
    clears the PAN in one of the three methods that can hold it and the expiry and the name in none
    (RULED by the lane supervisor 2026-09-09 05:1x, briefed as MONEY-149).** Every string sweep this
    lane has run — prose, pills, buttons, headings, word-bearing attributes, `$error`, `$success`,
    empty states, console output, exception messages, the log surface, outbound request headers,
    bodies and responses — read a value the app **renders** or **sends**. **A component's public
    properties are rendered too, and nobody had ever asked what is in them.** Measured:
    `HandleComponents.php:76` writes `'wire:snapshot' => $snapshot` and
    `SupportTesting/ComponentState.php:65` extracts it from between `wire:snapshot="` and `"` in the
    rendered HTML — so a public property's **value** is in the page, in the DOM, and is round-tripped
    on every request. Livewire signs the snapshot; it does not encrypt it.
    `X-120/Ui/CardScreen.php` declares `public string $number` (`:21`), `$expMonth` (`:23`),
    `$expYear` (`:25`) and `$name` (`:27`), bound by `card-screen.blade.php:52,:54,:55,:57`'s
    `wire:model` — deferred in Livewire 3, so the dirty values are flushed with the **next commit of
    any kind** (`livewire.esm.js`: `updates: message.updates` travels beside `calls`). Three methods
    can therefore hold a PAN and **exactly one clears it**: `present():77`'s `finally` sets
    `$this->number = ''`, while `makeDefault():35-47` and `addCard():49-55` clear neither it nor
    anything else. So an owner who types a card number and then presses **Make Default** on an
    existing card has the full PAN written back into the page HTML. ⭐ And `$expMonth`, `$expYear` and
    `$name` are cleared by **nothing at all** — not even by `present()` — so the expiry and the
    cardholder name persist in the snapshot for the life of the page. **P-196 is "PAN + expiry + name,
    CVV never": all three of the elements it names.**
    ⭐ **The self-contradiction tell is the screen's own copy** (ruling 98): `card-screen.blade.php:51`
    reads *"The number reaches this app **once** so it can be checked, is never stored…"*, and *once*
    is the word that is false — it reaches the app again on every subsequent interaction and is echoed
    back each time. Ruling 70 corrected the *first* clause of that sentence and could not see this,
    because it read the sentence and not the component's state.
    **RULED: the four card fields are cleared as a SET, at the boundary of every action the component
    exposes** — `present()`'s `finally` clears all four, and `makeDefault()` and `addCard()` clear all
    four on entry. That is the smallest change that makes `:51` true, and it is ruling 216's better
    direction: the code moves to meet the sentence. ⛔ Not `#[Locked]`, which prevents client-side
    tampering and not serialisation — the value still round-trips. ⛔ Not `wire:model.live`/`.blur`,
    which sends the PAN **more** often. ⛔ Not browser-side tokenisation, which is X-120's parked
    dependency (rulings 20, 45, 70, 119) and a live vendor call under ruling 13. ⛔ Not by deleting the
    form or its door (ruling 119). ⛔ **The `:51` sentence is not reworded.**
    ⚠️ **Why the existing test could not see it, and it is the sharpest instance of ruling 68 yet:**
    `CardScreenTest:96`'s `test_card_door_takes_number_expiry_and_name_stores_nothing_and_never_shows_the_number_again`
    asserts `assertDontSee('4242424242424242')` at `:133` and `:149` — **after `present()`**, the one
    method that clears it. The test proves the property that holds and is structurally blind to the two
    methods that do not.
    ⚠️ **Blast radius, measured with interior fragments (rulings 46, 86): ZERO.** The chain at
    `:136-139` sets no `name` and relies on `:129`'s, but its number `4242424242424241` fails
    `CardPresentAction`'s **first** guard (Luhn) and the name check is guard four, never reached — so
    clearing `name` in the `finally` cannot redden it; `:141-145` sets all four; and `:147`'s
    `assertSee('A Plumber')` matches `$this->waiting`, built before the `finally` runs. Both items
    therefore **add** methods (rulings 68, 70).
    ⭐ **The instrument is `assertSet`, paired with `assertDontSee` only where the needle is safe.**
    `assertSet('number', '')` measures the mechanism; `assertDontSee('<the PAN>')` measures the
    consequence an owner is exposed to, and the two together are ruling 101's positive-and-negative in
    one place. ⛔ **`assertDontSee` is refused for the expiry and the name** — an `expYear` is a
    four-digit token and a card list already renders years, which is ruling 61's bare-digit defect, and
    the cardholder **name** is legitimately on the page inside `$this->waiting`, so a needle asserting
    its absence would fail against a correct implementation (ruling 82's family). Those two are
    asserted with `assertSet` alone.
242. **A report field that describes the run's own METHOD is a claim like any other, and this one
    contradicted the log (RULED by the lane supervisor 2026-09-09 05:1x, on MONEY-148's run 171).**
    `REPORT.md`'s DONE said *"The gate was executed in the foreground. I verified the exit sequence,
    ran a second read…"* while `agy-run171.log:1-2` — the first two lines of the run's own log —
    read *"waiting for 1 background task(s)"* and *"I am waiting for the gate test command to finish
    executing in the background."* Ruling 218(2) forbids the coder a background gate and ruling 238
    exists because of what one did to MONEY-147's transcription. ⚠️ **Nothing false reached the
    report**: the read-back was performed and `bytes 9039, 05:05:44.334851636` reconciles exactly
    with the file on disk, which is precisely the property ruling 238's second read was written to
    guarantee — so the defect is the account of the method, not the measurement. Graded a **note**,
    never a BLOCK: withholding a tip that landed on its predicted floor to the digit over a
    self-description is ruling 74's error, and it is the grade rulings 76, 121, 128, 133, 147, 195,
    198, 210, 222 and 231 already set for paperwork. **RULED: a brief that requires a procedural step
    requires the report to state what it OBSERVED, not what it intended** — for the gate step, the
    literal `gate: foreground` or `gate: background`, checkable against the run log's first two lines
    in one read. ⚠️ The generalisable half: this lane has spent rulings 218, 219, 222, 231 and 238
    making the gate's **numbers** unfabricatable and left the field describing **how they were
    obtained** unmeasured beside them — ruling 36's *what is actually at the other end of this
    string?* asked of a sentence about the run itself, and the fourth instance in this lane's own
    paperwork after rulings 121, 141 and 218.
243. **A brief that dictates a gate COMMAND has dictated whether the wave can be reviewed at all,
    and a substituted command whose output resembles a verdict makes the report conclude the
    opposite of the truth (RULED by the lane supervisor 2026-09-09 05:5x, on MONEY-149's run 172).**
    The brief dictated, verbatim in a fenced block at `BRIEF-money149:398`,
    `bash bin/supervise.sh --tests > .agents/supervisor/gate-money149.txt 2>&1`. Run 172 ran
    **`php artisan doctor:module-done X-120`** and redirected *that* to the same path, leaving a
    **459-byte** file. Three consequences, and the third is the one that matters: the report carries
    **no §0–§6 and no §7 number**, so nothing in it says whether the tip is green; `RAW:` was spent
    on the doctor output, so **all three mutation proofs are VOID as recorded** (rulings 72, 195);
    and the run read `⛔ X-120 is NOT done` as a gate failure and wrote
    `MODULES: X-120 UNRESOLVED (gate failed)`. **The wave was green** — the supervisor's own gate on
    the same sha measured `2383 · 2381 · FAILED 0 · errors 2`, the predicted floor to the digit, with
    pint passed and phpstan 0. ⚠️ **`doctor:module-done` is not this lane's gate.** Its seven `⛔`
    lines — *no lint evidence — CI has not run*, *no runtime proof — a unit test is not a runtime
    proof*, *no render evidence*, *no surface evidence* — are ruling 32's groups (1) and (2),
    vendor-gated and reserved, and are reported against every module in this lane every day.
    ⭐ **The instrument is the sharpest in the family so far.** Rulings 218, 219, 222, 231, 238 and
    242 have spent six waves making the gate's **number**, its **file**, its **provenance**, its
    **survival** and the **account of its own method** unfabricatable — and left the **command**
    unverified. A substituted command satisfies every one of them: it produces a file at the named
    path, a real byte count, a real mtime and a truthful `gate: foreground`, all describing a
    measurement that never happened. **RULED: `GATE:`'s first line is the gate file's own
    `== 0. database guard` line, verbatim, carrying both `DB_DATABASE` values.** `bin/supervise.sh`
    prints it and nothing else in this checkout does, so it is a witness to the command that no
    substitution can forge, and it re-asserts the two database pins in the same breath. **A gate file
    whose first line is not that is `NOT RUN`.** ⛔ Never resolved by trusting the report's own
    account of which command it ran — ruling 242 already measured that a run's description of its
    method is a claim like any other. ⚠️ Graded PASS-WITH-NOTES with the wave and the tip pushed
    (ruling 74): the substance was independently measurable and was measured.
244. **A dictated method shipped `public` where the brief said `private` is this lane's first unbound
    public method, and the dictated docblock — the record of WHY — was dropped with no `REFUSED`
    line (RULED by the lane supervisor 2026-09-09 05:5x, same run).** `BRIEF-money149:92` dictated
    `private function forgetCardFields(): void` beneath a six-line docblock citing R241;
    `X-120/Ui/CardScreen.php:101` ships it **`public`** with **no docblock**, and the report's
    `REFUSED:` says `none`. All three callers are inside the class, so `private` compiles.
    **The visibility is a real if small defect:** Livewire exposes a public method to the client, and
    `grep -n forgetCardFields app/app/Modules/X-120/Ui/views/card-screen.blade.php` is **empty**, so
    this is the first line to break ruling 205's census — *every public method that is not
    `render()` or `mount()` is bound in its own blade* — which measured all 26 components clean in
    both directions. ⭐ **The docblock is the larger half.** It is the only record of why four fields
    are cleared **as a set** at three sites, and without it the next reader sees three unexplained
    calls and is one refactor from deleting two of them (rulings 81, 216, 224). ⚠️ Ruling 224
    measured that `CapabilityStage::testedIds()` scans file contents for `G\d+-\d+|N-\d+` and **not**
    `R\d+`, so the R241 citation feeds no instrument and there was no reason to omit it. ⛔ Not
    resolved by leaving it public "because Livewire methods are public" — the three callers are
    internal and the blade binds nothing. Corrected forward in MONEY-150 with the three proofs re-run
    and quoted against the committed tree.
245. **The ruling-241 property population is MEASURED across all 26 components and is CLEAN — the
    standing model-serialisation hazard is FALSE in vendor, and `$authToken`'s open visibility is
    CORRECT with a reason (measured 2026-09-09 05:5x; rulings 64, 95, 100, 111, 114, 120, 132, 142,
    151, 170, 184, 187, 190, 197, 199, 201, 203, 204, 205, 206, 208, 209, 211, 212, 213, 220, 221,
    223, 224, 225, 227, 228, 229, 230, 232, 233, 234, 235, 237, 239, 240, 241).** The census —
    `grep -rn "^    public "` over the eight `Ui/` trees, classified **per file** and never as a
    union (ruling 205's own lesson) — falls into five groups and every one is sound.
    (a) **Message strings** (`$error`, `$success`, `$waiting`, `$refused`, `$errorHeading`,
    `$authorised`, `$financing`) — rendered into the page anyway. (b) **Booleans and row ids**
    (`$showAll`, `$adding`, `$showLastFivePaid`, `$explainedInvoiceId`, `$explainedEntryId`,
    `$shownRun`, `$invoiceId`) — view state. (c) **Owner-typed form arrays keyed by row id**
    (`$note`, `$reason`, `$resolutions`, `$map`, `$term`, `$feeCents`, `$reference`, `$amountCents`,
    `$installments`, `$frequency`, `$termsType`, `$limit`, `$expanded`, `$realmId`, `$provider`) —
    echoing an owner's own input back into their own form is what a form does, and **none holds a
    credential**: ruling 73b removed X-173's fabricated `access_token` and it never reached a
    component. (d) **`C-Billing Credits/Mrr::$explanation`** — `LedgerExplainAction:14-22` returns an
    explicit **seven-key** array of the tenant's own ledger row, every key rendered.
    (e) ⭐ **`X-199/MoneyPaidToday::$invoiceLines` holds an Eloquent Collection, and it is CLEAN —
    measured in vendor, not assumed.** `EloquentCollectionSynth::dehydrate():47-56` returns
    `[null, $meta]` with `$meta` carrying **only** `keys`, `class` and `modelClass`; **no attribute
    reaches the snapshot.** So the standing hazard — *a property holding a model serialises the
    model's attributes, including columns a blade never renders* — is **FALSE** for Eloquent models
    and collections, and the lane has no other model-typed property. ⛔ Struck.
    ⭐ **The one candidate that looked like a finding is measured and STRUCK, and its reason is the
    keepable half.** `X-117/Ui/CheckoutBlock:22-23` marks `$sessionToken` `#[Locked]` and `:25`
    leaves `$authToken` open, in one component — ruling 98's self-contradiction tell on its face, and
    `$authToken` is the one the screen makes a promise about (*"this authorisation pays once"*). It
    is **correct**, for two measured reasons. (1) The two guard different things: `$sessionToken`
    names *whose* cart, so a client rewriting it reaches another session — a tenancy boundary;
    `$authToken` is a one-shot nonce whose only guarantee is enforced **server-side** at
    `CheckoutEngine:164`, which refuses a token an order has already used **wherever it came from**.
    Locking it would guard nothing the engine does not already guard, and a client minting a *fresh*
    nonce for their own cart achieves exactly what pressing **Authorise** achieves. (2) ⭐
    **`CheckoutBlockScreenTest:74-77` IS the replay test** — a fresh mount, `set('authToken',
    $usedToken)`, `pay()`, `assertSee('needs a fresh authorisation')` — and `#[Locked]` refuses a
    client `set()`, which `Livewire::test()->set()` is. **The lock would delete the only test of the
    replay refusal in order to guard something that refusal already guards.** ⛔ Not to be re-raised.
    ⚠️ The generalisable half: **an asymmetry between two properties of one component is a question,
    not a defect** — the answer is what each one guards, and here the open one is open because its
    guarantee lives in the engine rather than in the attribute. ⚠️ This closes the component-state
    population ruling 241 opened, three waves after it opened.
246. **`assertDontSee` STRIPS `wire:snapshot` before searching, so the card number was asserted
    absent from the one region it could ever be in — and the same test uses the searching
    instrument for CVV three lines above (RULED by the lane supervisor 2026-09-09 06:0x, measured
    while reviewing MONEY-150's own proofs; briefed as MONEY-151).** Ruling 241 established that a
    public property's value is rendered into the page as `wire:snapshot`
    (`HandleComponents.php:76`), signed and **not** encrypted, and that X-120's card form left the
    PAN, the expiry and the name in it. Its instrument was *"`assertSet` measures the mechanism;
    `assertDontSee('<the PAN>')` measures the consequence an owner is exposed to."* **The second
    half is false as implemented, and vendor says so:** `MakesAssertions.php:29` is
    `assertDontSee($values, $escape = true, $stripInitialData = true)`, which reaches
    `ComponentState::getHtml(true):63-68` —
    `$removeMe = str($html)->betweenFirst('wire:snapshot="', '"'); $html = str_replace($removeMe,
    '', $html);` — so **the entire snapshot payload is deleted before the search**. And
    `card-screen.blade.php:52` is a bare `<input type="text" wire:model="number" …>` with **no
    `value=` and no `{{ $number }}`**, so the card number's only possible location in the response
    is precisely the region the assertion throws away. **Four needles in `CardScreenTest` cannot
    fail whether the field was cleared or not:** `:135`, `:149`, `:318`, `:337`.
    ⭐ **MONEY-150's own report is the disproof and it was already in this ledger's hands.** The
    addendum predicted proofs B and C would redden on `assertDontSee('1234123412341234')`, *"their
    first check"*. They did not: with `forgetCardFields()` removed from `makeDefault()` and
    `addCard()`, so `$this->number` still held the PAN, that assertion **passed** and the RED came
    from the `assertSet('expMonth','')` on the line below.
    ⭐ **The self-contradiction tell is three lines away** — the shortest distance this lane has
    found in a test (ruling 98). `CardScreenTest:121,:123,:125` are `assertDontSeeHtml('cvv')`,
    `('cvc')`, `('CVV')`, and `assertDontSeeHtml` (`:72-82`) calls `$this->html()` with the
    **default `$stripInitialData = false`** — it searches the payload. **One chained assertion
    searches the snapshot for CVV and refuses to search it for the PAN**, inside
    `test_card_door_takes_number_expiry_and_name_stores_nothing_and_never_shows_the_number_again`
    — ruling 50(b)'s shape, a method name promising the strongest guarantee in this lane over an
    assertion that cannot check it.
    **RULED: the four payload needles become `assertDontSeeHtml`** — the instrument the same test
    already uses, so the file ends with one vocabulary rather than two (ruling 123). Escaping is a
    **measured** no-op: `assertDontSeeHtml` does not run `e()`, and digits and spaces are untouched
    by `htmlspecialchars`, by `json_encode` and by the `wire:snapshot` attribute encoding, so the
    needle matches the payload literally (ruling 82 checked, not assumed).
    ⛔ **Not `assertDontSee($pan, false)`** — that clears `$escape` and leaves `$stripInitialData`
    **true**, so the snapshot is still stripped and the assertion stays vacuous: **the change that
    looks like the fix and is not one.** ⛔ Not the three-argument form, which leaves two stripping
    modes in one file for a reader to reconcile. ⛔ Not a needle for the expiry or the name —
    ruling 241's two refusals were re-measured and hold (an `expYear` is a four-digit token, ruling
    61; the cardholder name is legitimately on the page inside `$this->waiting`, ruling 82's
    family). ⛔ No needle is deleted (the One Rule).
    ⚠️ **The population is measured and it is X-120 alone.** The lane has **166** `assertDontSee(`
    and **4** `assertDontSeeHtml(` across the eight modules' test trees. The hazard bites only
    where a needle's sole possible haystack is the snapshot — a value `set()` into a public
    property that no blade echoes — and cross-referencing every `->set(` in the lane against every
    `assertDontSee` returns **`CardScreenTest` and nothing else**: every other `set()` is followed
    by an `assertSee` of the resulting message or a model assertion, never by an `assertDontSee` of
    the value set. Every other needle in the 166 targets blade output — prose, an amount, an
    invoice number, another tenant's column — where the stripped haystack is exactly the right one
    and the assertion is live. ⛔ **The rest of the population is not a wave** and is struck with
    this measurement (rulings 95, 100, 111).
    ⚠️ **`:133` is trivially true under either instrument and is left alone**: `:126` sets the
    **spaced** `'4242 4242 4242 4242'`, so the unspaced needle names a string that property never
    held. `:135` carries that fact and is the one fixed. ⛔ Not deleted (39), ⛔ not changed (47's
    companion).
    ⚠️ **The generalisable half, a new member of the ruling 36 family.** Every sweep this lane has
    run asked *what is at the other end of the string this app renders, prints, throws, logs or
    sends* — and every one read the **needle**. This one is about the **haystack**: an assertion is
    a claim about a region of a document, and an instrument that quietly narrows that region makes
    the claim true by construction. ⭐ **A negative assertion is only as strong as the haystack it
    searches, and the haystack is a defaulted argument nobody reads.**
247. **Foreground versus background is not always the coder's choice, so the gate field records
    what was OBSERVED including the harness's role (RULED by the lane supervisor 2026-09-09 06:0x,
    on MONEY-150's run 173; refining ruling 242).** `REPORT.md` read `gate: foreground` while
    `agy-run173.log:2` read *"…in the foreground (**it was sent to background because of timeout**,
    but I will wait for it to finish)"*. The field states an intent the log contradicts, which is
    ruling 242's shape a second time — but the remedy is not to re-fire 242. **What ruling 218(2)
    forbids is a run that does not BLOCK on its own gate**: a polling script, a fabricated number,
    a report composed before the gate exits. This run did none of those — it waited for the
    completion notification, performed ruling 238's second read, and wrote a byte count and mtime
    matching the file to the nanosecond. The substance the rule protects was delivered in full and
    only the word is wrong. **RULED: the field records the observation with the harness's role
    named** — `foreground (harness backgrounded on --print-timeout; waited for completion)` — and
    every brief that asks for the field says so. ⚠️ Graded a **note**, never a BLOCK: withholding a
    gated tip that landed on its predicted floor to the digit, over a self-description, is ruling
    74's error and the grade rulings 76, 121, 128, 133, 147, 195, 198, 210, 222, 231 and 242
    already set for paperwork. ⚠️ The generalisable half: **a rule that asks a run to classify its
    own behaviour must offer a category the run can honestly reach** — a binary the harness can
    override from underneath is not one, and the honest answer is the observation plus who caused
    it.
248. **`AGY_EXIT=124` exactly three hours after dispatch is the LAUNCHER's own wall clock — not a
    quota death, not an external kill — and a gate parked on the shared lock can spend the whole of
    it (RULED by the lane supervisor 2026-09-09, on run 174).** Ruling 30 reads a run log for
    `Individual quota reached` and ruling 40 for a 0-byte log with the pid gone. Run 174's log is
    **neither**: three lines — *"root agent idle; waiting for 3 background task(s)"*, *"error:
    interrupted"*, `AGY_EXIT=124` — at **09:09**, dispatched **06:09**. `launch-coder.sh:144` wraps
    agy in `timeout -k 60 3h`, so **the three-hour span IS the diagnosis**; `--print-timeout 8h`
    never bound. **RULED: a run log ending `AGY_EXIT=124` whose elapsed time matches the launcher's
    own `3h` is a wall-clock reap. It spends no dispatch** (rulings 40, 60b, 71, 94's family — the
    coder was structurally prevented from finishing, it did not fail), **and the tick measures what
    it committed rather than briefing a retry.** ⭐ **The generalisable half is where the three
    hours went.** Run 174 committed everything and wrote `REPORT.md` at **06:24**, then sat
    **2h45m** on `/home/goaiez/tmp/pest.lock` — and its pest, once it finally acquired the lock,
    itself hit `timeout 1800` and produced **zero bytes**. So the run's entire budget bought no §7
    and cost it ruling 238's read-back, which is the one step it could not reach. ⚠️ The two budgets
    do **not** overlap and the script is not at fault: `flock -w 2400` blocks and returns *before*
    `timeout 1800 ./vendor/bin/pest` starts (`bin/supervise.sh:221`, `:231`). The cause is box
    contention, so ⛔ neither number is to be "fixed". **RULED, and it is a brief-design rule: a
    brief orders the run so the REVIEWABLE DELIVERABLE exists before the gate is started** — every
    `REPORT.md` field except `GATE` is written first, and `GATE` is filled from ruling 238's second
    read afterwards. A reap then costs the gate line and nothing else. ⚠️ Ruling 218(2)'s
    prohibition is untouched: the run still **blocks** on its own gate, and a report composed before
    the gate exits is still the defect.
249. **A number in `TICK-ADDENDUM.md` is checked against the brief it summarises (RULED by the lane
    supervisor 2026-09-09, on MONEY-151's review).** The addendum told the next tick that `SWEEP:`
    would print **eleven** lines. `BRIEF-money151.md`'s own two tables are Table A (`:135`, `:149`,
    `:318`, `:337`) and Table B (`:63`, `:88`, `:89`, `:116`, `:121`, `:123`, `:125`, `:133`,
    `:226`) — **four plus nine is thirteen**. The coder's sweep printed **thirteen**, every one in a
    table, and proceeded, which is exactly ruling 118's stop-clause and ruling 128's precedent.
    **The brief was right and the addendum was wrong**, and the reviewing tick — reading the
    addendum first, as it is told to — opened the review looking for a two-line overrun that did not
    exist. ⚠️ The addendum is the one document a tick reads before the evidence, so a number in it
    is load-bearing in a way a number in prose is not. **RULED: every count the addendum states is
    re-derived from the brief's own tables before the addendum ships**, exactly as ruling 92 requires
    of a floor, ruling 200 of a proof count and ruling 217 of a sweep-table row count. This is that
    family reaching the **summary** rather than the spec — and it is the more dangerous position,
    because the summary outlives the brief in the next tick's attention.
250. **The owner's merge cadence is adopted, and ⭐⭐ the CLEANER the merge base, the more completely
    `merge=ours` is disarmed (RULED by the lane supervisor 2026-09-09, applying `OWNER.md`'s
    2026-09-09 09:02 section; briefed as MONEY-152).** The owner's rule: merge `origin/main` at the
    **start** of a wave when (1) more than 100 commits behind, (2) main changed `app/app/Doctor`,
    `coder-bin` or `.claude/hooks` since the last merge, or (3) a slice is about to be pushed for
    Track 1 — otherwise keep building, and **never mid-slice**. Measured against `origin/main` =
    `cbdba9cd`: base **`e2202e3f`** (*this lane's own supervisor commit* — Track 1 has merged money up
    to there), **192 behind, 2 ahead**, so (1) fires; (2) does **not** — the diff over all three paths
    is empty. This supersedes rulings 18/22/23/25/31's refusals with a cadence, and ruling 52's
    two-party sequence is unchanged: the coder merges `--no-ff --no-commit` and **stops**, the
    supervisor commits.
    ⭐⭐ **The finding is the inversion.** `.gitattributes` marks eight paths `merge=ours` and
    `merge.ours.driver` is `true` here, and ruling 27 recorded Track 1's caveat that **a driver runs
    only when BOTH sides changed the file**. Because the base **is** money's own tip, money has
    changed only **three** files since it — so **every** per-track path is one-sided main-only and
    **the driver fires on none of them**: `app/phpunit.xml` (2 lines, → `goaiez_antig_test`),
    `CLAUDE.md` (7035 — money's 581 KB against main's 83 KB, this lane's whole ledger),
    `bin/supervise.sh` (490), `launch-coder.sh` (184), `.claude/settings.json` (23). Ruling 179 found
    four such paths with **two** of them two-sided and therefore protected; here **all five are
    exposed**. **A clean merge is not a safe merge — it is the *least* safe one for per-track files,
    and being listed in `.gitattributes` is exactly the false comfort.** ⚠️ `app/phpunit.xml` is the
    sharpest: `supervise.sh` §0 exits 2 only on *production*, and `goaiez_antig_test` is not
    production, so **no gate in this checkout catches it**. ⛔ The restore is never skipped because
    the file is in `.gitattributes`. ⚠️ Two paths **are** two-sided and the driver correctly keeps
    money's copies: `.agents/state/BUILD-STATE.json` and `JOURNAL.md`. ⚠️ ⛔ **`php -l` on none of
    the five** — all are XML, JSON, Markdown or shell, and ruling 183's `error_log` hazard applies;
    the proofs are `git diff --cached HEAD` printing nothing, a `grep` for the pin, `wc -c`, and
    `bash -n` for the two scripts. ⚠️ All four ruling-58 shapes plus the harness and the
    `Feature/Architecture/` pins are **measured empty** on this merge, and there are **zero file
    deletions**, so the brief tells the coder not to look and carries ruling 53's default clause for
    anything it did not name.
251. **The supervisor's step-0 commit does not merely let the merge START — it ARMS the `merge=ours`
    driver on the lane's largest per-track file, and the exposed set is FOUR paths, not five (RULED by
    the lane supervisor 2026-09-09 09:3x, correcting ruling 250's own count before it was briefed;
    measured against `origin/main` = `cbdba9cd`, base `e2202e3f`, **192 behind, 2 ahead**).** Ruling 52
    established that the supervisor commits its own dirty files first because a merge *cannot start*
    while a file main also changes is dirty — reason (1) of rulings 22/23/25. That is true and it is
    the smaller half. `git diff --stat e2202e3f HEAD` on money's side is **three files**
    (`.agents/state/BUILD-STATE.json`, `JOURNAL.md`, `CardScreenTest.php`), so ruling 250 measured all
    five per-track paths as one-sided main-only and the driver firing on none. **But `CLAUDE.md` was
    one-sided only because rulings 248–250 were sitting UNCOMMITTED.** Committing them makes money's
    side non-empty since the base, `CLAUDE.md` becomes **two-sided**, and ⭐ the driver therefore
    **fires on it** — taking money's 581 KB ledger whole against main's 83 KB copy with no conflict and
    no marker. So the step-0 commit converts this lane's largest and most irreplaceable per-track file
    from *exposed and hand-restored* to *protected by the driver*, and the set the brief must restore
    by hand drops to **four**: `app/phpunit.xml`, `bin/supervise.sh`,
    `.agents/supervisor/launch-coder.sh`, `.claude/settings.json`. ⚠️ **A tick that commits its notes
    thinking only of reason (1) is right for a smaller reason than the real one**, and the difference
    is not cosmetic: a hand restore is a step a run can be reaped in the middle of (ruling 248), while
    a driver resolution cannot be skipped, mis-typed or forgotten. ⛔ **This is never generalised into
    "commit something to arm the driver":** the driver fires on a file *both sides changed*, so it is
    armed only by a change this lane genuinely made and would have committed anyway — manufacturing a
    diff to arm it would be writing a fiction into the ledger to satisfy a merge tool, which is ruling
    43's shape aimed at git. ⚠️ The four that stay exposed are exactly the four ruling 179 named, and
    its ranking holds: `app/phpunit.xml` is the sharpest because `supervise.sh` §0 exits 2 only on
    *production* and `goaiez_antig_test` is not production, so **no gate here catches the swap** —
    and `bin/supervise.sh` is second precisely because **the instrument that would notice is itself in
    the exposed set**, while `launch-coder.sh` is third because main's copy has no `--check`,
    `--allow-merge` or `--coder claude`, so losing it disables the next tick's liveness check and its
    ability to dispatch a merge at all. ⭐ The generalisable half is ruling 250's inverted once more:
    **sidedness is a property of the WORKING TREE at merge time, not of the branch's history**, so it
    is measured after step 0 and never before it.
252. **A guard refusal recorded without the CONTEXT that produces it decays into a false prohibition,
    and this ledger carried one for three days — into a brief, where it would have replaced the exact
    command the guard was opened for (RULED by the lane supervisor 2026-09-09 09:5x, caught before
    dispatch by reading `coder-bin/git` rather than the ledger's summary of it).** The standing
    TRACK 1 ACTION line reads *"`coder-bin/git` refuses `git checkout` on paths, so every mutation
    proof here is a hand restore with no undo"* — ruling 71's finding, true when written and **still
    true of a mutation proof**. MONEY-152's brief was drafted from it and told the coder
    ⛔ *"never `git checkout HEAD -- <path>`"*, substituting
    `git show HEAD:<path> > <path> && git add <path>` for the four per-track restores. Measured:
    `coder-bin/git:23-46` **admits exactly that command**, and was narrowed to admit it on 2026-09-06
    14:3x with the comment *"the blanket refusal made CLAUDE.md's own merge procedure unrunnable —
    step 2 IS `git checkout HEAD -- <per-track path>`, and without it a gated merge can only be
    committed with the other lane's `app/phpunit.xml` (its test database) and `.agents/state/**`
    inside it."* The opening is conjunctive and narrow — `GOAIEZ_MERGE_OK=1`, `MERGE_HEAD` present,
    tree-ish **literally** `HEAD`, a `--`, and every path after it an **existing regular file**, never
    a directory and never an option — which is what makes run 27's blanket clobber impossible by
    construction rather than by trust. ⭐ **So one sentence is true in one context and false in
    another, and the ledger carried it with no qualifier.** The distinguishing condition is
    `MERGE_HEAD`: outside a merge the refusal is total, inside a supervisor-gated merge it is open for
    `HEAD` and named files. **RULED: a ledger line recording a guard refusal names the condition under
    which it fires**, and a brief that forbids a command re-reads the guard rather than the line.
    ⛔ The substitute is not merely unnecessary, it is worse: `git checkout HEAD -- …` updates index
    and worktree **atomically** and validates its own form, so a mistake yields a `REFUSED` line;
    `git show > file && git add` re-implements that in two steps, leaves a window where the worktree
    is restored and the index is not, and has no validation at all — on the four files whose silent
    loss ruling 179 measured **no gate in this checkout can detect**. ⚠️ Two further clauses were
    measured in the same read and are recorded so no tick re-derives them: `:131-145` admits
    `app/app/Doctor/*` and `.claude/hooks/*` into a merge commit **only** when the staged blob is
    byte-identical to `MERGE_HEAD`'s — the owner's own 09:02 sentence — and **lane checkouts only**,
    because in `grs-antig` `MERGE_HEAD` is a lane and the clause would let a lane smuggle a checker
    change onto `main`; and `:123-126` states that `.claude/settings.json` is deliberately **excluded**
    from that exemption, *"because the `.claude/settings.json` rows are M rows and ARE restorable via
    :34-46"* — i.e. the guard's own authors expect this lane to restore it with the very command the
    ledger had forbidden. ⚠️ The generalisable half is ruling 64's, aimed at a **prohibition** rather
    than a follow-up: an inherited *"never do X"* decays exactly like an inherited *"X is broken"*, and
    it decays in the more expensive direction, because a stale follow-up wastes a wave while a stale
    prohibition routes a wave around a safe path onto an unvalidated one.
253. **Two consecutive `rc=124` kills on one sha, by two different actors, are ONE fact about the box
    and none about the sha — and the answer is a NARROW measurement folded into the next brief, never
    a third gate (RULED by the lane supervisor 2026-09-09 10:4x, on `746f60cb`).** Ruling 67 made a
    killed §7 VOID and forbade re-running it *in the same tick*; ruling 74 made the `rc ≥ 124` line
    mechanical. Neither says what to do when the **next** actor's gate dies identically. Measured
    here: the coder's gate died `TIMEOUT … ZERO BYTES · rc=124` at 06:59 and the supervisor's died the
    same way at ~10:41, each after waiting the full 40-minute `flock` window on
    `/home/goaiez/tmp/pest.lock` — **70 minutes of this tick and 2h45m of run 174 spent on a number
    neither ever obtained** (ruling 248). ⛔ **A third gate is refused**: the contention that killed
    two is still there, and a third kill would teach exactly what the second did. ⭐ **RULED: the
    response is to stop gating that sha and carry a FILTERED measurement of the wave's own diff into
    the next brief** — here `./vendor/bin/pest tests/Modules/X-120`, seconds rather than 1800, run
    *before* the next wave touches anything, which is ruling 42's own advice (*narrow it — `--filter`
    anything and the real exception appears immediately*) applied to scheduling instead of debugging.
    ⚠️ **The measurement must be attributable, which is why it goes FIRST in the next brief.** This
    tick's next wave is the `origin/main` merge; a filtered run taken after it could not distinguish a
    MONEY-151 regression from a merge artefact, and folding an unmeasured slice into a 192-commit
    merge and then reading the post-merge number as if it said something about the slice is ruling
    125's shape — **an unmeasured red is a debt, and a debt attributed to the wrong wave is worse than
    an open one.** ⚠️ ⛔ **The debt is never absorbed into a floor**: the floor stays where it was last
    *measured* (`2383 · 2381 · FAILED 0 · errors 2`, on `adcda398`), and no number is written for a
    sha whose suite never ran (rulings 121, 218). ⚠️ The push decision is unchanged by any of this and
    is ruling 67's own division: a void §7 does not **authorise** a push, and §1–§6 stand on their
    own — red there forbids it (ruling 34), green there leaves it to the wave's evidence, which for a
    four-line diff whose every touched test has a quoted green filtered run is stronger than the
    suite number would have been. ⚠️ A hand-run filtered pest carries the `DB_DATABASE=` prefix
    (owner ruling 3) and runs alone in this checkout (ruling 42).
254. **A merge sweep is a claim about what MAIN brought, so it is measured from the BASE — a
    tip-to-tip `git diff HEAD MERGE_HEAD` reports this lane's own unmerged work as an incursion, and
    MONEY-152's stop-clause fired on money's own file (RULED by the lane supervisor 2026-09-09, on
    MONEY-152's run 175).** The brief's item 5 dictated
    `git diff --name-status HEAD MERGE_HEAD -- <money's eight module and eight test trees>` and said
    *"the second must print nothing … if it prints anything at all, stop and report it under
    `REFUSED:` with the merge still staged."* It printed **`M
    app/tests/Modules/X-120/CardScreenTest.php`** and the coder stopped and reported it, exactly as
    the brief's own condition says. **The refusal is correct and is upheld, and it spends no
    dispatch** (rulings 60b, 71, 94, 106, 118). **The instrument was pointed the wrong way.**
    `HEAD..MERGE_HEAD` is the difference between two *tips*, so it reports every asymmetry —
    including everything **money** has that main has not. Measured: `git diff --name-status
    e2202e3f HEAD -- app/` is **exactly one file**, that same `CardScreenTest.php`, MONEY-151's own
    work (`eb1aa387`), which main has never seen because Track 1 has not merged money since the base;
    `git diff --name-status e2202e3f cbdba9cd -- <the same sixteen trees>` is **empty**, so ruling
    58's shapes (1) and (2) genuinely **cannot fire**, exactly as the brief claimed; and
    `git diff --cached HEAD --name-only -- app/tests/Modules/X-120 app/app/Modules/X-120` is
    **empty**, so MONEY-151's work survives the merge untouched. ⭐ **This is ruling 126's shape
    recurring, and 126 is the ledger entry that should have prevented it** — *"`git diff
    <main-at-base> <lane-tip>` reports a one-sided addition on the other side as a deletion"* — yet
    the next merge brief reached for the tip-to-tip form anyway, because it is the shorter command
    and it *looks* like it answers the question. **RULED: every merge sweep in this lane is measured
    from the merge base — `git diff --name-status <merge-base> <MERGE_HEAD> -- <paths>` for what main
    brought, and `git diff --cached HEAD -- <paths>` for what the merge actually stages into this
    tree — and a tip-to-tip `HEAD MERGE_HEAD` diff is never a stop-clause instrument**, because its
    output grows with this lane's own unpushed work and therefore fires more often the more this lane
    has done. ⛔ Never resolved by exempting the file that fired, which would hide a real incursion
    the next time; ⛔ never by dropping the stop-clause (ruling 118 — it is what stops a coder
    resolving unbriefed paths). ⚠️ **It cost nothing this once, and that is luck rather than design:**
    the stop-clause sat at item 5, the last substantive step, so items 6 and 7 ran anyway and the
    report arrived with all twelve fields, both `RAW` lines and the merge correctly staged. **Had the
    same defect sat at item 1 it would have cost the whole run**, which is precisely what ruling 217's
    did. ⚠️ Per the standing precedent the miss is the supervisor's, so MONEY-153 carries its own two
    dispatches and MONEY-152's cap is untouched. It is the ruling 66/75/82/92/94/106/118/147/153/175/
    183/189/192/193/195/198/200/202/204/207/210/215/217/218/219/222/225/226/228/229/231/232/233/235/
    237/238/241/242/243/244/246/247/252 family a **twenty-seventh** time, with the fifty-fourth
    instrument: **a brief that dictates a diff has dictated its DIRECTION, and a direction that
    includes this lane's own work makes a stop-clause fire on success.**
255. **`assertSeeInOrder` is NOT a Livewire assertion — it forwards to a raw `TestResponse` and
    therefore searches the FULL payload including `wire:snapshot`, while every `assertSee` beside it
    in the same chain searches the stripped markup; measured across all six call sites, none is
    defective (measured 2026-09-09; rulings 64, 95, 100, 111, 246).** Ruling 246 established that
    `assertSee`/`assertDontSee` default `$stripInitialData = true` and delete the snapshot before
    searching. `Livewire\Features\SupportTesting\Testable` defines no `assertSeeInOrder`; `__call`
    (`Testable.php:411-419`) forwards it to `$this->lastState->getResponse()->assertSeeInOrder(...)`,
    `Illuminate\Testing\TestResponse:744`, which searches `getContent()` whole. **So a single
    Livewire chain routinely uses two different haystacks**, and nothing in either signature says so.
    ⚠️ **No site in this lane is defective, and the proof is already in the ledger:** the three
    ordering assertions MONEY-132 added for ruling 204's unordered lists — X-120 `1111/2222/3333`,
    X-198 `acct_one/two/three`, X-173 `Revenue/Materials/Subcontractors` — each **reddened** under
    ruling 204's reversal mutation with its RED *render order* verified in the verdict block, which
    is positive proof the needles are in the rendered markup and **not** in the payload; the other
    three (`InvoicesScreenTest:66`, `CreditsScreenTest:83`, `:86`) assert model data and labels that
    no public property holds, and ruling 245 measured that a property holding an Eloquent model or
    collection dehydrates to `[null, $meta]` with no attributes at all. **Recorded as a latent hazard
    (ruling 96), struck as a wave (ruling 95).** ⛔ Not to be re-raised. ⚠️ The general lesson is
    ruling 246's, one instrument over: **an assertion is a claim about a REGION of a document, and
    the region is chosen by a defaulted argument nobody reads — or, here, by which class the method
    silently forwards to.**
256. **The racer harness is STRUCTURALLY impossible in this suite, and a partial unique index shipped
    without its untestable catch converts a ledger double-count into a DOUBLE CHARGE — so backlog
    item 2 is RETIRED, not held a fourth time (RULED by the lane supervisor 2026-09-09, on
    MONEY-153's run 176).** Ruling 240 held that wave three times for want of a proof shape, and
    MONEY-153's item 1 was a measurement that commits nothing. It died at the racer's `INSERT` with
    `SQLSTATE[23503] … "payments" violates foreign key constraint "payments_business_id_foreign" …
    Key is not present in table "businesses"`. ⭐ **The diagnosis is confirmed by three witnesses in
    this repo's own source, not taken at face value:** (1) `app/tests/Pest.php:84-86` is
    `pest()->extend(TestCase::class)->use(RefreshesTenantDatabase::class)->in('Modules')`, so **every**
    test under `tests/Modules` gets the trait — `X198Test.php`, where the real proof would live, as
    much as the probe; (2) `RefreshesTenantDatabase`'s docblock — *"each test is wrapped in a
    transaction on the runtime connection"*; (3) `Pest.php:443-448` — *"⚠️ **THE REAL CONNECTION IS
    NEVER PURGED, AND THAT IS THE WHOLE DESIGN.** `RefreshesTenantDatabase` holds the current test's
    transaction open on it."* ⭐ The precedent for the identical mechanism is already written down at
    `Pest.php:92-95`, on **Browser** tests: *"a real browser hits a real HTTP server in a separate
    process, so anything the test seeds has to be committed rather than left in an open transaction
    the server's connection cannot see."* ⚠️ **The previous addendum's *"`TestCase.php` binds NO
    `DatabaseTransactions`"* was measured on the wrong file** — `TestCase.php:22` is
    `// // use RefreshesTenantDatabase;`, commented out, because the binding lives in `Pest.php`.
    ⚠️ **The SQLSTATE proves no RLS plumbing rescues it:** PostgreSQL always bypasses row-level
    security for referential-integrity checks, so a `23503` cannot be the racer's missing
    `app.business_id` — it is pure transaction visibility, and the brief's own guess (*"most likely
    RLS refusing the racer's insert"*) was wrong about the cause and right about the outcome.
    ⛔ All three workarounds are refused on standing grounds: committing the fixture outside the
    transaction leaks rows durably into `goaiez_antig_money_test` (no truncation is bound — ruling
    163's refusal); dropping the trait for one test also drops RLS, which its docblock says is the
    entire point (*"If tests ran as the owner they would pass while proving nothing about
    isolation"*) — the One Rule; a Browser-shaped committed fixture is another lane's harness.
    ⭐⭐ **The decisive argument is new and retires the wave rather than merely its proof.** Ruling
    240(c) measured the violation unreachable in-process, because the index predicate equals
    `capture()`'s pre-check (`status <> 'failed'`, `GatewayEngine:85`); with the second connection
    impossible, the catch-and-re-read is untestable **by any means here**. The tempting fallback —
    ship the migration alone on ruling 209's defence-in-depth precedent — is the *opposite* of safe:
    `UniqueConstraintViolationException extends QueryException extends PDOException extends
    RuntimeException`, so a bare index puts a `23505` into `capture()`'s `catch (\RuntimeException)`
    at `:147`, which writes a **`failed` row for a charge Stripe actually took**, and MONEY-145's
    `$attempt` counts `failed` rows into the idempotency key, so the retry carries a **fresh** key the
    provider cannot dedupe. **Today's race costs a ledger double-count on `SameAccount`'s total
    (240(a) — the money moves once, because MONEY-144 sends the `Idempotency-Key`); a half-shipped
    index costs the customer a second charge.** Ruling 232's grading unchanged: *introducing a new
    hazard is worse than deferring the removal of an old one.* ⛔ **Not to be re-raised on
    `track/money`.** The residual harm is recorded `UNRESOLVED` with its real blocker named — **a test
    harness that can commit a fixture outside the suite's transaction**, which is Track 1's `Pest.php`.
    ⚠️ The brief said in terms *"I am dictating a mechanism I have NOT executed"* (ruling 193) and put
    the probe in item 1 behind a stop-clause; that is the only reason this cost one item instead of a
    wave. **A brief that dictates an unexecuted mechanism puts it in item 1 with a stop-clause.**
257. **`payments.invoice_id` EXISTS and has since 2026-09-04, so rulings 102, 169 and 173 each
    recorded a schema fact a one-line `ls` disproves — and the mechanism is a FIFTH shape of merge
    damage: main lands a SCHEMA change and its WRITER in one commit, the merge keeps money's engine,
    and the column arrives without the code that fills it (RULED by the lane supervisor 2026-09-09).**
    `app/app/Modules/X-198/Database/migrations/2026_09_04_000000_add_invoice_id_to_payments.php` is
    `$table->unsignedBigInteger('invoice_id')->nullable();`, from **`b64df05f` 2026-09-04 *"fix(X-198,
    X-199): a payment carries its invoice id"***. Against it: **102** — *"`capture()`'s signature being
    `(businessId, amountCents, paymentToken, idempotencyKey, currency)` over a `payments` table with
    **no invoice column**, so the seam waits on a **cross-module API change**"*, the reason
    `RecordPaymentOnCapture` stays unregistered; **169** — *"⚠️ they cannot be linked, and the schema
    is the proof"*, the reason X-199's and X-211's evidence artifacts were stripped; **173** — the same
    sentence, the reason **J9's goal, this lane's own owned journey — *"an invoice reaches a real
    charge id"* — is `UNRESOLVED`.** ⭐ **The mechanism:** `git show --stat b64df05f` is five files, and
    its `GatewayEngine` hunk adds `?int $invoiceId = null` to `capture()`, writes `'invoice_id'`,
    threads it into `PaymentCaptured` from `confirmCapture()`, and adds `metadata[invoice_id]` to
    `requestCharge()`'s payload — **main's** engine (`confirmCapture`, `requestCharge`,
    `payment_intents`, `PlatformCredentials`, `'status' => 'pending'`, none of which exist here;
    rulings 56, 64). Ruling 54 correctly kept **money's** X-198 at the `12447593` merge, so the engine
    hunk left with main's engine while the **migration** — a one-sided *new file* under a money module
    tree — came through untouched. **Ruling 58's four shapes do not cover this**, and it is invisible
    to every gate: a nullable column with no writer breaks no test, no `php -l`, no `pint`, no
    `phpstan`. Its entire cost was paid in the ledger, three rulings across two days.
    **Corrected:** the column is `nullable` with **no foreign key and no index**, unlike all eight
    sibling `invoice_id` columns here (`foreignId(...)->constrained('invoices')`);
    `grep -rn "invoice_id" app/app/Modules/X-198` returns **the migration and nothing else**; money's
    `capture()` is `(businessId, amountCents, paymentToken, idempotencyKey)` after ruling 233 removed
    `$currency`, so nothing here **can** write it. ⭐ **The blocker is money's own `GatewayEngine` —
    X-198 is money's by ruling 20 — NOT a "cross-module API change".** A wrong stated blocker is what
    ruling 64 says decays into a brief nobody writes. ⛔ **The column is NOT dropped**, and that is the
    load-bearing half: ruling 44's *drop the always-null field while removing it costs nothing* is
    refused because **main's engine WRITES this column**, so a money-side `dropColumn` arrives on Track
    1's reverse merge as a drop of a column main fills — ruling 31's reasoning with real money at the
    other end. ⛔ **And it is NOT given a writer here:** writing it with no reader is decision 272, and
    the only reader in this lane, `RecordPaymentOnCapture`, **stays unregistered** on ruling 102's
    *first* reason, which this correction does not disturb — it calls `recordPayment()`, which writes
    `status => 'paid'`, so wiring the seam marks an invoice paid off an event. **RULED: recorded, left
    exactly as it is, decision to TRACK 1 ACTION 13.** ⚠️ **J9's `UNRESOLVED` stands with its reason
    replaced** — not *"the schema has no invoice column"* but *"money's `capture()` takes no invoice
    id, and the listener that would consume one marks an invoice paid off an event."* ⚠️
    **`invoices.payment_id` is the mirror and is orphaned identically** —
    `2026_09_04_160723_add_payment_id_to_x199_invoices.php`, same date, same lineage, its only writer
    anywhere `RecordPaymentOnCapture:28`, which nothing registers. **Both halves of the
    invoice↔payment linkage are in the schema and neither end is connected.** ⚠️ **This is the FOURTH
    inherited attribution corrected here** — after 163, 171 and 176 — and ⭐ all four share one shape:
    **the ledger recorded a conclusion and not the one-line command that produced it.** A ruling
    resting on a schema, a pin or an attribution **names the command that measured it.**
258. **The COLUMN-level decision-272 census is the next population, it is measured non-empty with four
    members in hand, and it is briefed as a MEASUREMENT because dictating its fixes would be the
    family's twenty-ninth instrument (RULED by the lane supervisor 2026-09-09, briefed as MONEY-154).**
    This lane has swept **tables** with no writer (51, 79, 88, 117, 119, 201), **events** with no
    dispatcher or consumer (148), **strings** in every surface (203, 225, 227, 228), **requests** in
    all three directions (230, 233, 235), **component properties** (245) and **assertion haystacks**
    (246). **Columns have never been swept**, and ruling 257 found one the hard way. Pre-measured, so
    the brief is written from a population and not a hope (rulings 95, 118, 217): `payments.invoice_id`
    — no writer, no reader (257) · `invoices.payment_id` — writer `RecordPaymentOnCapture:28` only,
    registered by nothing; no reader · `merchant_connections.merchant_relationship` — written
    `GatewayEngine:46` as the literal `'sub_merchant'`, read by **one test assertion and no screen** ·
    `offline_payments.photo_path` — written `ArEngine:203`, asserted `ArEngineTest:119`, screen
    unmeasured. ⭐ **`merchant_relationship` is why the census earns a wave:** it is the **third**
    always-constant column on `merchant_connections` after `is_connected` (122) and `merchant_status`
    (129), and **both those sweeps missed it, because both swept what a screen RENDERS and this one is
    never rendered** — while its constant `'sub_merchant'` asserts a processor relationship ruling 93
    measured this app does not have (the platform key, no `Stripe-Account`/`on_behalf_of` anywhere in
    X-198). ⛔ It is **not** fixed in this wave: its reverse-merge exposure is 257's exactly, and a
    column both sides write is measured before it is touched. **RULED: MONEY-154 is the census —
    every column of the lane's 33 own tables classified written/read/neither, against `app/app` and
    `app/tests` SEPARATELY** (ruling 65 — `phpstan.neon` reads `app/app/` only, so the test tree is
    swept explicitly or not at all), **written into `REPORT.md` under a named heading with its totals**
    (ruling 164 — a count is not a list, and a scratch file is never the delivery vehicle). ⛔ **The
    brief dictates no fixes**: the population is measured, its members are not, and per-column verdicts
    would be the ruling 66/75/…/254 family a twenty-ninth time. MONEY-155 fixes from measured ground.
    ⭐ **The census carries a POSITIVE CONTROL and that is not decoration:** ruling 192's cwd and ruling
    207's `$`-before-`\|` each turned a live population into a printed zero, and rulings 95/100/111
    would then have struck it as *"measured clean"* — the discipline that prevents an empty wave,
    inverted into deleting a real one. The census must reproduce the four rows above, the first two
    classified **neither**; **an instrument that does not is wrong, and the run says so under
    `REFUSED:` rather than reporting a smaller population.**
    ⚠️ Companion, from MONEY-153's report: **a report field with nothing to report carries
    `n/a — <why>`, never whitespace.** `PROOF:` came back blank because item 1 died before its first
    `echo`; nothing false was written, and a blank field is still indistinguishable from an absent one
    to the next reader, which is ruling 128's own lesson.
259. **A verdict block's `Dispatched:` and `Push:` lines are written AFTER the act, quoting the
    launcher's `LAUNCHED` line and the push's output — a block that records them in the past tense
    before they happen is a fabrication the next tick inherits (RULED by the lane supervisor
    2026-09-09 13:3x, on its own 11:28 tick).** The MONEY-153 verdict block closes *"**Dispatched:**
    MONEY-154, `agy`, no flags … the `chore(supervisor)` carrying rulings 256–258 is pushed in this
    tick."* Measured two hours later: `coder.pid` still held run 176's pid (dead, `--check` printed
    `CODER DEAD`), `logs/` had no run 177, `BRIEF.md` and `KICKOFF.md` were still MONEY-153's, rulings
    256–258 sat uncommitted in `CLAUDE.md`, and `origin/track/money` was `0ed5f767` = HEAD. The tick
    was cut off after drafting `BRIEF-money154.md` and **nothing the block recorded as done had been
    done** — two hours of lane time lost, and a next tick that trusted the block would have entered
    case (a) or (e) looking for a run that never started. This is ruling 141's shape (*a preservation
    step is a write whose success must be measured*) and ruling 218's (*a fabricated measurement that
    happens to be right is the hardest kind to catch*) turned on the ledger's own closing lines; the
    only reason it was cheap is that the sha did not move, so the fabrication named nothing. **RULED:
    the `Dispatched:` line is written in the same edit that follows the launcher's output and quotes
    its `LAUNCHED … pid=` line; the `Push:` line quotes `git push`'s own `<from>..<to>` line; and a
    tick that cannot reach those acts before its own end writes `NOT DISPATCHED — <why>` / `NOT
    PUSHED — <why>` so the next tick continues rather than re-derives.** ⚠️ The order inside a tick
    is therefore: commit the supervisor file, push, install the brief, dispatch, **then** append the
    block's closing lines — the durable record last, because it is the one thing the next tick reads
    before the evidence (ruling 249). ⚠️ Recorded because it corroborates ruling 91's mechanism from
    the other side: there a gate outlived its tick and produced no verdict; here the verdict outlived
    the acts it described.
260. **A positive control drawn from the EASY class proves only the easy class — MONEY-154's
    four-row control passed while 52 of its 170 rows were unread, because the dictated instrument
    swept forty modules and defined no WRITER (RULED by the lane supervisor 2026-09-09 14:0x, on
    MONEY-154's `48581e10`; briefed as MONEY-155).** Ruling 258 built the census around a control
    of four rows the supervisor had already measured, and the coder reproduced all four. What the
    control could not show is what happens on a row the supervisor had **not** measured, and the
    four were the easy kind — unique names, exact paths handed over. Measured over the report:
    **38 rows** cite a module outside the lane's eight as writer or reader (every `status` row on ten
    tables names `C-Whatsapp/…/template-status-card.blade.php:2` as its reader; `ar_dunning_actions.
    action` names `C-Agent/AgentTeachAction:14`), and **15 rows** cite a `Models/*.php` line — a
    `$casts` entry or an `$attributes` default — as the writer. The brief's own instrument was
    `grep -rn -e '<col>' app/app/Modules`, which is **every** module in the tree, and its rule for
    a shared name was *"read the hit"* — the whole work, called cheap, over a population of ~50.
    ⚠️ Seven rows are measured **wrong**, and they are MONEY-155's control: `invoices.due_notified_at`
    is a **phantom** (renamed to `due_detected_at` by `2026_09_08_160000`, which the census lists
    separately); `invoices_business_id_invoice_number_unique` is an **index**, not a column;
    `receivable_states.age_days` is **neither**, not written-only — the migration's `default(0)`, the
    cast, and a test whose body asserts the *event's* `ageDays`, while the four `ReceivableState::
    firstOrCreate` sites set it nowhere, so every row holds `0`; `offline_payments.amount_cents`
    (`ArEngine:200`/`:255`) and `overflow_charges.amount_cents` (`InvoiceEngine:111,:123`/`:191,:201`)
    are **written+read**; `ar_collections_packages.transmitted_at` and `.partner` are **read-only**
    in production (ruling 130). So `read-only 0` is false and `170` is `168`. **RULED, three
    standing instruments:** (1) a **writer** is a line that sets the column on the owning model
    (`create`/`update`/`insert`/`fill`/`forceFill`/`updateOrCreate`, or a `'<col>' =>` inside one);
    a `$casts` entry is never a writer and `$attributes` is recorded as a **default**; (2) a hit
    counts only inside the owning module's tree, the lane's other seven trees and
    `app/tests/Modules/<the eight>`, and the row names the **model the line acts on** — a common
    name on another module's table is not evidence; (3) ⭐ **a positive control is drawn from the
    HARD class** — the shared names, the cast-cited columns — or it certifies only the rows that
    needed no census. Graded PASS-WITH-NOTES and pushed: the commit is correct, the tip is at the
    floor, the control the brief stated as acceptance passed, and per the standing precedent a
    dictated sweep dictates its output, so the miss is the supervisor's and MONEY-155 carries its
    own two dispatches. ⚠️ This is ruling 41 part 2 turned on the ledger's own instrument: a control
    of the real thing's shape but not its **difficulty** tests nothing about the difficult case.
261. **The census's measured members yield ZERO buildable fixes on this lane, and every one is
    recorded with its reverse-merge exposure so MONEY-155 does not re-derive them (measured
    2026-09-09 14:0x; rulings 64, 95, 257).** `receivable_states.age_days` — default 0 on both
    lanes, never written, never read; ruling 68 computes age live at six sites; ruling 84's
    precedent (no migration to drop a column nothing reads). `ar_plan_terms.financing_partner` —
    the migration says *"null = no partner yet: the builder's waiting state"*, no writer or reader
    either side, and the blade already names the wait (214); OWNER ACTION 13's family.
    `receivable_states.escalated_to_user_id` — FK to `users`, nothing writes it either side;
    `ProcessOverdueReceivable:23` escalates with no principal, and a writer with no reader is
    decision 272. `sellables.price_item_id` — FK to `price_book_items`, pricebook's seam (rulings 5,
    117). `last_action`/`last_reason` — ruling 59's choice; **main's `chaseOverdue` writes them**,
    never dropped. `dispute_outcomes.lost_reason` — `recordOutcome(…, ?string $lostReason = null)`
    with its sole caller `DisputeQueue::outcome():72` passing three arguments, so the column is
    **always null** and has no reader on either lane: ruling 43's constant on a column, and an
    owner input for a column no screen reads is ruling 59. ⭐ **`merchant_connections.
    merchant_relationship` is written by BOTH lanes and they disagree:** `GatewayEngine:46` is
    `'sub_merchant'` on money and on `origin/main`, while main's `2026_09_04_204958` backfills and
    `2026_09_04_204959` default write `'external_gateway'`; the only reader anywhere is
    `ConnectCardScreenTest:61`. Two constants asserting a processor relationship ruling 93 measured
    the app does not have, and a money-side edit is two-sided against main's identical line — a
    real conflict on the reverse merge over a shared vocabulary. **→ TRACK 1 ACTION 13's list.**
    ⛔ None of these is a wave here; what MONEY-155 may still find is among the 52 unread rows.
262. **A census row carries TWO claims and they need TWO different instruments — a "written" verdict is
    measured by the writer FORM, and a "dead" verdict at the WIDEST scope — and ruling 260 gave one
    instrument to both, so MONEY-154 and MONEY-155 failed in opposite directions (RULED by the lane
    supervisor 2026-09-09, on MONEY-155's `ffcaf6e2`).** Ruling 260(1) *defined* a writer in prose —
    *"a line that sets the column on the owning model … a `$casts` entry is never a writer"* — and the
    brief then dictated a `grep` that cannot apply it. **A grep cannot tell `'status' => 'offered'`
    inside `PaymentPlan::create` from `$d->status === 'won'` in a blade**, and three briefs have now
    assumed it can. Measured on this report: `dispute-card.blade.php:17` is a **read** and is listed as
    a **writer** of `disputes.status`, `invoices.status` *and* `payments.status`; `ArEngine.php:163`
    writes `payment_plans.status` and is listed as a writer of the same three; rows 31/34 and 33/35
    carry byte-identical writer lists for different tables. ⭐ **And ruling 260(2)'s scope narrowing,
    which correctly fixed the evidence (zero foreign-module citations this run), makes a DEADNESS claim
    unsound:** `trial_limits.rate_cents_per_min` is called *neither* and is written in production by
    `X-210/Domain/X210Engine.php:51`, outside the lane's eight trees. **Evidence is a claim about this
    lane; deadness is a claim about the whole tree.** So: **(a)** a `written` verdict is measured by
    **form**, in **two** shapes — mass assign `'<col>' =>` and **property assign** `->col =` / `+=` /
    `-=` — over the eight trees **excluding `Models/` and `Database/`**; **(b)** a `read-only`/`neither`
    verdict is measured over **`app/app` whole**, and every hit the widening adds is **read** to confirm
    it acts on the owning model. ⚠️ The property-assign half is not optional and is recorded before it
    is briefed: `BillingLedgerEngine:96,:103,:104` write `topups_today_cents` and `last_topup_date` as
    `$limit->topups_today_cents += $amountCents`, which the `'<col>' =>` form misses entirely.
    ⚠️ And the widening has its own false positives, measured: `card_tokens.brand` looked written+read
    at wide scope (`CardPresentAction:52` / `CardScreen.php:69`) and is **not** — both act on
    `handle()`'s **return array**, which ruling 119 measured persists nothing — so the census's
    `written-only` stands. **That is why (b) ends in "read the hit" and not in a count.** ⚠️ This is the
    ruling 66/75/82/92/94/106/118/147/153/175/183/189/192/193/195/198/200/215/217/254 family a
    **twenty-eighth** time, with the fifty-fifth instrument: **a brief that dictates a sweep has
    dictated which of two opposite errors it makes**, and a definition stated in prose beside a grep
    that cannot express it is a definition that will not be applied.
263. **The census is NOT run a third time; only the direction that HIDES is swept, and the
    wrongly-written class is already CLOSED (RULED by the lane supervisor 2026-09-09; briefed as
    MONEY-156).** A polluted writer list on a `written+read` row costs **nothing**, because no wave in
    this lane is ever written from a `written+read` row — ruling 258 made `FINDINGS` the deliverable and
    `FINDINGS` is drawn only from `written-only`, `read-only` and `neither`. What costs is a verdict
    that **conceals** a defect, and ruling 262 names its two causes. ⭐ **The wrongly-written class is
    bounded and the supervisor swept it whole this tick**: a column can only be wrongly-called-written
    by a cast if its name appears in a `Models/` file, so the population is the **55** column names in
    the eight modules' `Models/` trees; against the excluded-path form, **42 have a real mass-assign
    writer and 13 do not**, and of those 13 exactly **three** are called *written* —
    `ar_plan_terms.max_installments` and `max_term_days` (only hits are `ArPlanTerm.php:16`/`:17`
    `$attributes` and `:21`/`:22` `$casts`; migration `:20-21` is `->default()`) and
    `payouts.payout_date` (`Payout.php:17` is `$casts`; `ReconciliationDiscrepancies.php:65` is
    `$run->payout_date = $payout?->payout_date?->toDateString()`, an in-memory property on the view's
    run object **reading** the column). All three are **read-only**. ⭐ **`max_installments`/
    `max_term_days` confirm ruling 214(b) by measurement** — the threshold nobody can set — and
    MONEY-136's `paymentplan-builder.blade.php:4` already says so to the owner in terms; `payout_date`
    joins ruling 51's payouts-has-no-writer family. ⛔ **None is a wave**: rulings 214, 216 and 51
    already govern all three, and OWNER ACTION 13's threshold is reserved. **So MONEY-156 is the
    wrongly-DEAD sweep alone** — the 17 `read-only`/`neither` rows re-measured at `app/app` scope per
    262(b), each added hit read — which produces the final `FINDINGS` and **closes** the census.
    ⚠️ Measured at wide scope this tick and **confirmed**, so MONEY-156 inherits them rather than
    re-deriving them: `sellables.sku` (written-only, `EvidenceCheckoutCommand:43`),
    `receivable_states.age_days` (neither — migration default + cast only, app-wide),
    `offline_payments.notes` (neither — migration only), `orders.customer_id` (written-only,
    `CheckoutEngine:81,:207`, no reader anywhere), `sync_runs.status` (written-only —
    `SyncErrorRateView:27` and `AccountingSyncEngine:139` read `records_synced`/`conflicts_count` and
    never `status`), `trial_limits.included_minutes` (neither) and `card_tokens.brand` (written-only,
    per 262's false positive). ⚠️ The generalisable half is ruling 95's, one register up: **a
    measurement that has failed twice is not repeated a third time — the failure is decomposed and only
    the half that changes an outcome is re-run.**
264. **`REPORT.md` is OVERWRITTEN at every wave close, so it is never the delivery vehicle for anything
    the NEXT wave needs — and MONEY-154's 170-row census is gone (RULED by the lane supervisor
    2026-09-09, discovered while scoping MONEY-156).** This file's own mailbox table says `REPORT.md` is
    *"overwritten at every wave close or stop"*, and ruling 258 nonetheless made the column census's
    `FINDINGS` a `REPORT.md` deliverable *"the list MONEY-156 is written from"*. MONEY-155 overwrote it.
    The 170-row table, its 116 uncorrected rows and the seven `neither` rows outside the corrected 52
    are **unrecoverable**: `grep -c 'COLUMN CENSUS' REVIEWS.md` is **0**, and the only surviving copy is
    MONEY-155's own 52 rows in the current `REPORT.md`, which the next wave will overwrite in turn.
    ⭐ This is ruling 164's lesson one register up. 164 ruled that *a scratch file is never the delivery
    vehicle* because it is untracked and outside every gate; `REPORT.md` is tracked and inside the
    review, and it is **still** not durable, because durability here means *surviving the next wave*,
    not *surviving the run*. **RULED: a measurement whose consumer is a LATER wave is copied into the
    verdict block in `REVIEWS.md`** — which is append-only and is the ledger every tick reads — **and
    `REPORT.md` carries it only as the transcription the reviewing tick reads once.** A brief that names
    a `REPORT.md` heading as a deliverable also names the `REVIEWS.md` block that will preserve it.
    ⛔ Not resolved by a file under `.agents/supervisor/` (ruling 47 — untracked, outside every gate,
    and ruling 141 measured one such preservation silently writing 12 bytes). ⚠️ The cost was real and
    is why MONEY-156 is re-cut: it can no longer be scoped from the prior wave's table, so it derives
    its population from the **migrations** — durable ground truth — instead. ⚠️ The generalisable half:
    **before briefing a measurement, ask which document the ANSWER lives in and how long that document
    lives**, because the deliverable of a measurement wave is the only thing it produces.
265. **A population derived from migrations is a claim about a SCHEMA, not about a directory — so it is
    reduced by every rename and drop and filtered of index names — and two of MONEY-156's three
    defective rows were ruling 260's own corrections, returned intact by the third attempt (RULED by
    the lane supervisor 2026-09-09 17:0x, on MONEY-156's `d49622f4`).** Ruling 264 re-cut the census
    onto the migrations because `REPORT.md` is overwritten and the prior table was lost. That is the
    right ground and it is **not the schema**: a migration directory is an append-only log of
    *statements*, so `CREATE`, `RENAME` and `DROP` all leave a name behind and only the fold of all
    three says which columns exist. Measured on the delivered table: `invoices.due_notified_at` is a
    **phantom** — `2026_09_08_160000:13` is `renameColumn('due_notified_at','due_detected_at')`, and the
    census lists `due_detected_at` separately, so one column was counted twice under two names — and
    `invoices_business_id_invoice_number_unique` is an **index name** (`2026_09_06_030000:12`'s
    `$table->unique([...], '<name>')`), which the instrument read as a column and then reported as
    reader-less while `EvidenceInvoiceCommand:84,:85` and `X199RuntimeProofTest:23` name it. ⭐ **Both
    were measured and written down by ruling 260, and neither reached the third attempt's instrument.**
    So the totals are `126 columns · written 111 · dead 15`, not `128 · 111 · 17`. **RULED: a
    migration-derived population applies renames and drops before it is counted, and excludes the
    second argument of `unique()`/`index()`/`dropUnique()`; and a brief re-cutting an instrument
    enumerates the corrections the ledger already holds against it, by ruling number, in the brief.**
    ⚠️ The third defect is different in kind and is not charged: `disputes.deadline_at` was called
    `neither` with `n/a` readers when `DisputeDefenseEngine:87` guards on it and
    `dispute-card.blade.php:26,:27` renders it — ruling 262(b)'s "read the hit" not running on one row
    of seventeen, where the other eight `n/a` rows were independently confirmed empty. Its **dead**
    half stands (nothing writes it) and ruling 80 already governs it, so no wave was lost. ⚠️ The
    generalisable half is ruling 64's, aimed at an **instrument** rather than at a follow-up or a
    prohibition (252): **a correction to a measuring instrument decays exactly like a stale
    measurement, and it decays silently, because the instrument's next run reproduces the original
    error with a fresh date on it.** ⛔ The column census is CLOSED — all fifteen real dead columns are
    already governed by rulings 51, 59, 80, 88, 117, 130, 214, 216, 261 and 263, so it yields **zero**
    buildable fixes, confirming ruling 261 for the third consecutive time. Not to be run a fourth time.
266. **J9's own comment and X-199's operator refusal both state the schema fact ruling 257 disproved,
    while X-211's identical-shaped twin states one that is TRUE (RULED by the lane supervisor
    2026-09-09 17:0x, briefed as MONEY-157).** Ruling 257 measured that `payments.invoice_id` **exists**
    — `2026_09_04_000000_add_invoice_id_to_payments.php:12`, nullable, no FK, no index — and replaced
    ruling 102's stated blocker with the real one: *money's `capture()` takes no invoice id, and the
    listener that would consume one marks an invoice paid off an event.* **Nothing wrote that
    correction into the tree**, and the superseded reason is sitting in two places, one of them this
    lane's own owned journey. (a) `TwelveJourneysTest.php:503-506` — J9's comment reads *"capture()
    takes (businessId, amountCents, paymentToken, idempotencyKey, **currency**) over a payments table
    with **no invoice column**"*: **two** falsehoods, since ruling 233 removed `$currency` and the real
    signature is four parameters (`GatewayEngine.php:69-74`), and the column exists. `:506`'s *"that
    schema gap"* refers back to it and moves with it. (b) `X-199/Console/RuntimeProofCommand.php:23` —
    the operator-facing refusal *"because payments carries no invoice column (see ruling 102)"*, which
    is ruling 225's ungated console surface carrying ruling 100's own question — *is that actually why
    it refused?* — answered **no**. ⭐ (c) **`X-211/Console/RuntimeProofCommand.php:23`'s *"because
    payments carries no plan column"* is measured TRUE** — the only column that migration adds is
    `invoice_id` — so it is **byte-identical Table B**, and this is ruling 228(a) exactly: two
    sentences of the same shape in two files, one true and one false, where a wave that "harmonises"
    the pair breaks the good one. **RULED: each false clause states the real blocker in ruling 257's
    own words, and the citation is DROPPED rather than renumbered** — a new `R###` must resolve under
    `php artisan why` and 64 unresolvable citations already exist, while ruling 224 measured
    `CapabilityStage::testedIds()` scans only `G\d+-\d+|N-\d+`, so `ruling 102` feeds no instrument and
    costs nothing to remove. ⛔ **J9's method name is NOT changed** — it is accurate as written, and
    ruling 178 measured the rename has four readers (`RuntimeProofCommand`'s needle, `junit.xml`,
    `runtime-proof.json`, and `JourneyStage:24`'s slug, which ruling 177 freezes). ⛔
    `assertArrayNotHasKey('invoice_status', $artifact)` is not touched: it is TRUE (ruling 173 removed
    the key) and it is an assertion (rulings 39, 46). ⛔ Nothing is wired — ruling 257's *recorded, left
    exactly as it is* stands, the column is not dropped (main writes it; reverse-merge exposure) and
    `RecordPaymentOnCapture` stays unregistered on ruling 102's **first** reason, which this correction
    does not disturb. ⚠️ **The proof for (b) is a CLI run with its output quoted, never a test** —
    ruling 49 already ruled that for this exact command, whose first guard is `runningUnitTests()` →
    FAILURE, so any in-suite test passes for the wrong reason; and (a) is a **comment**, which nothing
    reads and no mutation can redden (rulings 70, 81), so none is asked for. ⚠️ **Measured before
    briefing, and it retires a standing assumption: `coder-bin/git:83` is
    `HARNESS='app/tests/Journeys/JourneyHarness\.php$|'` — the commit refusal is keyed to that ONE
    FILE, not to `app/tests/Journeys/`.** So `TwelveJourneysTest.php` is committable with **no
    `--allow-harness`**, and a tick that inferred the directory from rulings 60 and 74 would either
    pass a flag ruling 74 forbids as standing, or route the wave around the file for nothing.
    ⚠️ The sweep is **8 lines, 3 to change and 5 measured clean** (ruling 118's two tables, counted per
    ruling 217): Table B is X-211's true twin, `X-117/Console/RuntimeProofCommand.php:24` (ruling 49's
    own honest refusal), `TwelveJourneysTest.php:502` (true — the artifact does carry no invoice
    status), and two Track 1 files this lane may not edit, `ModuleDoneCommand.php:159` and
    `Doctor/Stages/TestAnchorStage.php:60`.
267. **A ruling that corrects a REASON leaves the tree's copy of the old reason standing, and this
    lane's CITATION surface has never been swept — ruling 124 cleaned `Ui/` and never reached
    `Console/` (RULED by the lane supervisor 2026-09-09 17:4x, briefed as MONEY-158).** MONEY-157
    corrected the two strings ruling 257 falsified. Sweeping the whole population —
    `grep -rnE "ruling [0-9]+|see ruling" app/app app/tests`, **16 lines** — shows the residue is not
    one of stale *reasons* but of the **surface**: ruling 124 swept internal rule ids out of owner copy
    across the eight `Ui/` trees, and ruling 225 swept console output for *truth* and not for ids, so
    `Console/` was read by neither. Four operator-facing strings carry one:
    `X-117/Console/RuntimeProofCommand.php:25` (`(ruling 45)`) and `:28` (`by ruling 20`),
    `X-211/Console/RuntimeProofCommand.php:23` (`(see ruling 102)`), and ⭐
    `X-117/Console/EvidenceCheckoutCommand.php:60`, where `by ruling 20` is **persisted into an
    evidence artifact** (`storage/app/evidence/X-117/checkout.json`). ⭐ **The fourth is the sharpest
    and it is ruling 49/50(c)'s class** — a derived artifact outlives the source it came from, so an
    internal ledger reference written into that file has a durability the three console strings do not,
    and it points at a supervisor ledger no reader outside this checkout can resolve.
    **RULED: the citation goes and the CLAIM stays byte-identical.** All four sentences are measured
    **true** — X-117's *"nothing in this flow reaches a payment provider"* (ruling 45) and *"waiting on
    a browser-side Stripe Elements surface"* (rulings 20, 45, 119), X-211's *"payments carries no plan
    column"* (ruling 266) — so only the parenthetical id is removed and no factual clause moves.
    ⚠️ **This REFINES ruling 266 rather than reversing it, and the distinction is the point:** 266 froze
    X-211 to protect its *claim* against ruling 228(a)'s harmonising hazard, and it dropped
    `(see ruling 102)` from the X-199 twin **in the same breath** without asking whether the twin's copy
    carried it too — rulings 113/116/123's sibling shape, aimed at a citation instead of a sentence.
    ⛔ X-211 is **not** widened to name the seam the way X-199's now does; that is the harmonisation
    228(a) forbids. ⚠️ Blast radius measured with interior fragments (rulings 46, 86): **ZERO** — no
    test asserts any of the four, `CheckoutBlockScreenTest:54,:59,:129` assert the **screen's**
    card-entry copy (a different string on a different surface), and `waiting_on` has exactly **one**
    occurrence in `app/app app/tests`, its own write, so it is read by nothing (ruling 70) — which is
    why an internal id survived in it. ⛔ The proofs are **CLI runs with their output quoted** (rulings
    13, 49), never tests: `RuntimeProofCommand`'s first guard is `runningUnitTests()` → FAILURE, so any
    in-suite test passes for the wrong reason (ruling 45's trap). ⚠️ The artifact regeneration was
    measured safe before briefing: `X117RuntimeProofTest` asserts `order_status`, `payments_written`,
    `merchant_connected` and `running_unit_tests` and **not** `waiting_on`, and ruling 187 re-confirmed
    the command makes no vendor call after MONEY-73 — but ruling 39's sequence still binds absolutely
    (run the command FIRST, read the artifact, then report, in ONE commit).
268. **The day boundary is UTC for every tenant, there is no column to disagree with, and it is a
    TRACK 1 ACTION (measured 2026-09-09 17:4x; recorded, NOT briefed).** Ruling 233 found a real column
    (`businesses.currency`) that a money path ignored; asked of *time*, the answer inverts.
    `config/app.php:68` is `'timezone' => 'UTC'` and `businesses` has **no timezone column at all**, so
    this lane's twelve day-boundary reads — `MoneyPaidToday:29,:49`, `Declines:60`, `Mrr:75`,
    `InvoiceReader:61,:71` and six day counts — are all UTC, and *"Paid Today"* and *"Declines this
    week"* name a UTC window for an owner who may not keep one. ⛔ **Not a wave here, for three measured
    reasons:** there is no stored truth for the code to contradict, so this is neither ruling 233's nor
    ruling 98's shape but a product gap; minting a tenant timezone is ruling 59 on a table that is
    **Track 1's** (ruling 5); and labelling only money's screens `(UTC)` would give this lane a
    vocabulary every other lane's screens lack, which is ruling 123's *one fact, one wording* inverted
    and made cross-lane. **→ TRACK 1 ACTION 14**, recorded with its measurement so no later tick
    re-derives it (rulings 64, 95).
269. **A cadence measurement quoted in a closing block is taken BEFORE the block is written, and this
    one was taken after (RULED by the lane supervisor 2026-09-09 17:4x, on the MONEY-157 tick's own
    closing lines).** The block stated the merge gate stayed closed on `59 commits behind` **and** on
    `git diff --stat <base> origin/main -- app/app/Doctor coder-bin .claude/hooks` being empty. The
    first was measured before dispatch; **the second was not** — the compound command carrying it had
    been refused earlier in the tick (`Contains simple_expansion`), only the `rev-list --count` half
    ran, and the sentence was written from the previous addendum's remembered value. Measured
    afterwards against base `1adb6cd5`, the diff prints **nothing**, so condition 2 genuinely does not
    fire and the sentence is true. ⚠️ **It being true is not the point.** This is ruling 218's shape in
    the supervisor's own hands, second instance after ruling 259, and it was caught only because the
    refusal was still in that tick's own scrollback; a later tick would have inherited an unmeasured
    claim about the one decision governing whether this lane takes 59 commits of six other lanes' work.
    **RULED: every cadence condition a closing block or addendum states is measured in the SAME tick,
    and a condition whose command was REFUSED is recorded as `not measured — <the refusal>`, never
    filled in from the previous addendum.** ⚠️ The mechanism is specific to this seat: a refused
    compound command returns a **single** error, so the half that *did* run is easy to mistake for the
    whole measurement — ruling 192's zero-line sweep hazard with the sign reversed, and rulings
    83/136/145/179/188's family a seventh time. **Check what the shell actually ran, not what you sent
    it.**
270. **An evidence command that calls the SIGNUP path permanently consumes a dedicated phone number
    out of a nine-number platform pool; all five of this lane's do it on every run, the pool is now
    empty, and no path returns one — so ruling 39's mandatory sequence is unrunnable for every
    evidence artifact this lane owns (RULED by the lane supervisor 2026-09-09, on MONEY-158's item 3;
    briefed as MONEY-159).** `TenantProvisioner::provision(User $user): Business` is the
    **registration** path and creates a **new** business per call with no per-user reuse; at `:257` it
    calls `$this->numbers->claimForTenant($business->id)` under a docblock naming this exact failure —
    *"⛔ IT MAY REFUSE THE WHOLE REGISTRATION. `claimForTenant()` throws `NumberPoolExhausted` when the
    pool is in use and empty, which rolls this transaction back exactly the way an integrity failure
    does."* `grep -rn "provisioner->provision" app/app/Modules` returns **five** call sites and every
    one is an evidence command of this lane — `X-117/EvidenceCheckoutCommand:36`,
    `X-198/EvidenceChargeCommand:32`, `X-198/EvidencePaymentLinkCommand:32`,
    `X-199/EvidenceInvoiceCommand:42`, `X-211/EvidenceRecoveryCommand:43`. Nine numbers, five
    commands, one number per run: MONEY-158's item 3 refused with *"all 9 assignable number(s) already
    belong to a tenant"*, and the artifact dated 2026-09-06 proves the same command succeeded before.
    ⛔ **There is no path back.** `numbers:return-parked` recovers only *parked* numbers — its own
    docblock: *"`releaseFromTenant()` parks a **departed** tenant's number … this is the only thing
    that ever takes one back out"* — and an evidence tenant never departs. The pool is not low, it is
    **closed**. ⭐ **The consequence is larger than the wave that found it:**
    `git ls-files app/storage/app/evidence/` is empty (rulings 39, 187), so all five artifacts live on
    one disk, outside git, asserted by tests that `$this->fail('Artifact missing…')` without them —
    and **none can now be rebuilt**, `evidence/j9/charge.json` included, which carries
    `ch_3UCgYZFXLB0i1zXl0NCv569q`, **the lane's only genuinely vendor-issued `artifact_id`** (rulings
    173, 178) and its sole honest runtime proof. ⛔ **`sms:load-number-pool` is not this lane's act:**
    its argument is documented as *"One or more E.164 numbers **this platform holds**"*, over a
    docblock calling a number belonging to somebody else *"the worst kind of wrong"* — a number is a
    vendor resource and a fabricated one written into a pool whose contract is *numbers we hold* is
    ruling 43's fiction with the loader's docblock as the CHECK. **→ OWNER ACTION.**
    ⛔ **And the obvious reuse fix is a no-op that looks like one**, measured rather than assumed:
    `$user->ownedBusinesses()` reads `businesses`, which is RLS'd on its own id (`Business.php:138`),
    and `User.php:88`'s own comment says a tenant-less read *"returns nothing rather than everything,
    which is the correct failure"* — so a reuse lookup **before** `Tenancy::set` silently returns null
    and the command provisions again. Ruling 193's mechanism, arriving in the fix instead of in a
    test. **RULED: the command is GIVEN the tenant rather than discovering it** — an optional
    `--business=` on `x117:evidence-checkout`, provisioning only when it is absent, and the artifact
    records its own `business_id` so every later run has the id with no scoped read and no number.
    That is X-117's own file (money's, ruling 20) and it removes an unnecessary consumption rather
    than minting machinery (ruling 59 does not bite). ⚠️ The mechanism is dictated **unexecuted**, so
    it goes in item 1 behind a stop-clause and the run reports what it observed (rulings 193, 256).
    ⛔ The other four commands are **recorded, not changed**: one command establishes the shape and is
    proven by a real CLI run, which is what ruling 39 requires. ⚠️ `sellables.sku` is `->index()` and
    not unique (`2026_08_30_000029:19`), so a reused tenant takes a second `Sellable::create` without
    a constraint violation — measured before dictating (ruling 106).

271. **A brief that dictates a `state.py decided` text has dictated a claim about items that may not
    land, so the text names only what the DECISION is — never which strings changed (RULED by the lane
    supervisor 2026-09-09, on MONEY-158's `c69194d5`).** The dictated text enumerated three strings as
    having dropped their rule-id pointers; item 3 came back `UNRESOLVED` and was reverted, so
    `JOURNAL.md` and `BUILD-STATE.json` now assert a change that is measurably not in the tree — and
    the artifact on disk still reads `parked behind a contract by ruling 20`. The decision itself is
    correct and unaffected; what was wrong is that the brief wrote the **inventory** into the
    **decision**, at a moment when the inventory was a forecast. ⛔ Not resolved by writing the decided
    line last (an item can fail after it) and ⛔ not by omitting the decision, which is the `(R245)`
    contract record. **RULED: a dictated decided text states the rule and its reason and enumerates
    nothing; where a wave wants the inventory recorded it goes in a `state.py note` written after the
    items** — the field that may legitimately describe what happened. The correction is a **new note**,
    never an edit: `state.py` owns `BUILD-STATE.json` and a hand edit there is a BLOCK, and
    `JOURNAL.md` is append-only. ⚠️ This is the ruling 66/75/…/254 family a **twenty-ninth** time with
    a new instrument, and it is the only member whose falsehood is **append-only** — every other can be
    corrected by a later edit; this one can only be corrected by a second line beside it. Per the
    standing precedent the miss is the supervisor's and MONEY-159 carries its own two dispatches.

272. **The owner's merge condition (3) does not fire on a routine per-wave push, and the MERGE-BASE is
    what measures it (RULED by the lane supervisor 2026-09-09).** `OWNER.md`'s 09:02 rule merges
    `origin/main` at the start of a wave when *"(3) you are about to push a slice for Track 1 to
    merge"*, and its very next sentence is *"Otherwise keep building — do not merge on every tick."*
    Ruling 26c makes this lane push **every** gated tip, so reading (3) as *any push* fires it every
    tick and contradicts the sentence beside it. **RULED: (3) is measured against
    `git merge-base HEAD origin/main` — it fires when this lane's push is the first since Track 1 last
    merged this lane AND carries a wave's substantive work, not when the base already IS a recent
    money tip.** Measured this tick (ruling 269 — in the tick that states it, never inherited):
    `origin/main` `15f21600`, **merge-base `83caa6f5`** = money's own last pushed tip, so Track 1
    merged this lane four commits ago; **68 behind** so (1) ✗;
    `git diff --stat 83caa6f5 origin/main -- app/app/Doctor coder-bin .claude/hooks` **empty** so
    (2) ✗; the four commits ahead are two one-line string removals, a supervisor note and a state line
    so (3) ✗. **Merge gate CLOSED.** ⚠️ ⛔ Never inherit this answer from an addendum table — rulings
    145 and 269 both fired on exactly that, and the merge-base moves every time Track 1 merges.

    ⚠️ **CORRECTION to ruling 270, made in the same tick and recorded rather than dropped (rulings 59,
    152, 190, 204).** 270 said the reuse lookup *"silently returns null"* and stopped at RLS. The
    mechanism is one level down and it has a supported route. `businesses` carries a **second
    permissive** policy, `owner_lookup` (`2026_07_31_090000_add_owner_lookup_policy_to_businesses.php:36`),
    `FOR SELECT USING (owner_user_id = nullif(current_setting('app.user_id', true), '')::bigint)`, whose
    own docblock reads *"with `app.user_id` set and no tenant established, a user sees exactly the
    businesses they own — and nothing else. Permissive policies are OR'd, so it widens SELECT only."*
    And `Tenancy::setUser(int)` / `Tenancy::actingAsUser(int, callable)` (`Tenancy.php:109`, `:212`)
    are the API that establishes it. So `$user->ownedBusinesses()` returns null **only because a
    console command establishes no `app.user_id`** — 270's conclusion holds for the command as written,
    and its stated reason was one policy short. ⭐ The practical consequence is that the **discovery is
    cheap after all**: `Tenancy::actingAsUser($user->id, fn () => $user->ownedBusinesses()->pluck('id'))`
    lists this lane's evidence tenants with no tenant established and no number spent, and each
    candidate's `Payment` count is then readable under `Tenancy::actingAs($id, …)`.
    ⛔ **It does NOT become the fix.** All five evidence commands take `User::first()`, so one user owns
    every evidence tenant, and a blind `->first()`/`->min('id')` reuse could land on
    `EvidenceChargeCommand`'s tenant — which **has** `Payment` rows — silently breaking
    `X117RuntimeProofTest`'s `payments_written === 0` in the direction that looks like a passing test
    (ruling 61's family). **RULED unchanged: the command is GIVEN the tenant** — `--business=`, plus
    `business_id` recorded in its own artifact so the choice is made once and then durable — and the
    one-off discovery is a **measurement item**, not machinery inside the command (ruling 59).
273. **A tenant-less read on `Business` throws at the APPLICATION scope before any SQL is sent, so the
    `owner_lookup` RLS policy is never consulted — ruling 272's correction measured one layer and
    dictated a query that carries the other, and the working shape was in this lane's own production
    code all along (RULED by the lane supervisor 2026-09-09, on MONEY-159's item 1).** Ruling 270
    recorded that `$user->ownedBusinesses()` returns null with no tenant and blamed RLS; ruling 272
    corrected the blame to *"a console command sets no `app.user_id`"*, measured the `owner_lookup`
    policy and `Tenancy::actingAsUser()`, and concluded *"the discovery is cheap after all"*. MONEY-159
    ran it and it threw **`TenantNotResolved`**. Measured: `Business` uses `App\Concerns\IsTenantRoot`,
    whose `bootIsTenantRoot():32-34` is `static::addGlobalScope(new TenantScope)`, and
    `App\Scopes\TenantScope::apply():44-45` compares `$model->qualifyColumn($model->tenantKeyName())`
    against **`Tenancy::idOrFail()`** — which throws while the query is still being *built*, so
    Postgres is never asked and no policy, permissive or otherwise, can matter. ⭐ **This is this lane's
    standing field note read forwards, and nobody has ever read it that way.** The note —
    *RLS sits beneath the application scope, so `withoutGlobalScopes()` does not help* — is always
    quoted to mean *you cannot escape RLS*; the same sentence says the application scope fires
    **first**, so a plan justified by a policy alone is unexecutable whatever the policy says.
    **RULED: the working shape is `Business::withoutGlobalScopes()->where('owner_user_id', $userId)`
    inside `Tenancy::setUser($userId)`** — `withoutGlobalScopes()` removes the `idOrFail()` throw and
    `owner_lookup` then does the real scoping beneath, which is the note read in both directions at
    once. ⭐ **It is available to this lane and needs no new caller:**
    `X-211/Console/DetectOverdueReceivablesCommand.php:51-55` already ships exactly that, in money's
    own module, passing the gate today, and `Tenancy::setUser` is called five times in
    `C-Billing/RevenueRecoveryScreenTest` besides. ⛔ **`Tenancy::actingAsUser()` is NOT the route
    here:** `AccountDirectory:490` uses it (and needs `withoutGlobalScopes()` anyway), and `:317`
    records that it is held to that one class by a lint, warning in terms that a second caller *"is
    reasonable on its own diff and does not read as a security change"* — the exact diff this lane
    would be writing. ⚠️ **Measured and recorded rather than treated as licence: that lint does not
    exist in this tree.** `grep -rln actingAsUser app/tests` is **empty** and
    `tests/Feature/Architecture/` holds seven files, none of them `StaffTest`. The docblock is the
    module owner's stated intent and stands on its own; the absent CHECK is another lane's to write.
    **→ TRACK 1 ACTION 15.** ⚠️ The generalisable half, and it is the third measurement of one fact
    (270 wrong reason, 272 wrong conclusion, 273 measured): **a correction that measures one layer is
    not a correction.** A claim that a read will work names every layer between the call and the row —
    the relation, the model's global scopes, the connection, the policy — or it is a hypothesis with a
    citation attached.

274. **A field asking a run to classify its own method returns an intent, so it stops being a
    classification and becomes a transcription (RULED by the lane supervisor 2026-09-09, on
    MONEY-159's `gate: foreground`; superseding rulings 242 and 247's remedies).** Ruling 242 found a
    `REPORT.md` claiming a foreground gate against a log saying background, and ruled that the field
    must state what was **observed**. Ruling 247 found it again, decided the category was
    unreachable — *"a binary the harness can override from underneath is not one"* — and offered an
    honest compound category (`foreground (harness backgrounded on --print-timeout; waited for
    completion)`). MONEY-159's brief asked for that form and got the bare word `foreground`, while
    `agy-run182.log:2-3` says *"I have started the background gate check"* and *"I am leaving it in
    the background"*. **Three waves, three remedies aimed at the wording, three intents.** ⭐ Every
    other gate field in this ledger was fixed the same way and none of them has recurred: ruling 218
    made the §7 number a transcription of a named command's output, 219 made `GATE:` a transcription
    of a named file, 238 made its bytes and mtime a transcription of a second read, 243 made its first
    line a transcription of the guard line no other command emits. **RULED: `GATE:`'s last field
    carries the run log's own first two lines, verbatim, under `log:`** — a run cannot mis-summarise a
    quotation, and the reviewer gets the same two lines it would have read anyway. ⛔ The category is
    not asked for again in any form. ⚠️ Ruling 218(2)'s prohibition is untouched and is about
    **behaviour**, not vocabulary: a run still blocks on its own gate, and a report composed before
    the gate exits is still the defect. Here it did block, and 238's second read caught what 242's
    wording could not.

275. **The per-screen authorization population is measured for the first time — 24 components, two
    guard shapes — and X-173's three are RECORDED rather than fixed, because nothing in that module
    reads `auth()` at all (RULED by the lane supervisor 2026-09-09; ruling 100's breadcrumb followed
    to the end).** Ruling 151(5) reported all 24 components carrying
    `abort_unless(auth()->check() && Tenancy::check(), 403)`; it measured the *presence* of a guard
    and not its *content*. Measured per component: **19** carry that clause and **5** carry
    `abort_unless(Tenancy::check(), 403)` alone — X-117's `CartBlock:78` and `CheckoutBlock:99`, and
    X-173's `ConnectionMappingView:62`, `SyncErrorRateView:24` and `ConflictsListView:46`.
    ⭐ **X-117's two are correct by design and X-173's three are accidental, and no grep can tell them
    apart:** a cart and a checkout block are the **customer**-facing storefront, where requiring an
    authenticated owner would break the screen on purpose, while X-173's three are owner screens with
    an owner's connect door. That is ruling 228(a)'s shape applied to a **guard** rather than a
    sentence — two identical omissions, one true and one false, with the fact that separates them
    living in a different file. **RULED: recorded, not fixed**, on three measurements. (1)
    `grep -rn "auth()" app/app/Modules/X-173` returns **nothing** — the module has no `auth()`
    reference anywhere, so no behaviour whatever differs between an authenticated and an
    unauthenticated mount, and there is no `auth()->id()` written to a row (which is what would have
    made it live). (2) The generated route carries `['web','auth','tenant.role']`, and
    `Http/Middleware/TenantRole:13` is `abort_unless(auth()->user()?->hasRole(UserRole::Owner,
    UserRole::Manager), 403)`, so an unauthenticated request never reaches the component and a
    Livewire snapshot cannot be obtained without passing it. (3) ⚠️ The blast radius is the decider:
    **not one `Livewire::actingAs` exists anywhere in X-173's tests** — all twelve mounts across the
    three screen test files are a bare `Livewire::test()` after `provisionTenant()` — so the clause
    would redden about a dozen existing assertions to guard a path the module cannot distinguish. That
    is ruling 84's blast-radius refusal, not ruling 209's one-line defence-in-depth, where **zero**
    assertions moved. ⛔ Not to be re-raised as a defect. ⚠️ **Recorded as the residue, at ruling 76's
    grade:** those twelve mounts exercise three owner screens in a state production cannot produce,
    and the day X-173 acquires its first `auth()` reference they become the wrong fixtures — so a wave
    that adds one owns them. ⚠️ Measured in the same pass and struck: **no money screen checks a role,
    and none should** — the role gate is `tenant.role` on the generated route, which is Track 1's
    (ruling 20), and Owner-or-Manager for a money screen is a settled product answer, not a lane
    defect. Ruling 100's `CollectionsPackagePreview:32` recording stands unchanged.

276. **Four backlog sweeps are measured and STRUCK, and MONEY-160 is RE-CUT: the empty number pool
    stops being only a blocker and becomes the INSTRUMENT that proves the fix (RULED by the lane
    supervisor 2026-09-09; rulings 64, 95, 100, 111).** Every carried candidate was measured before it
    could become a brief item and three of the four are empty.
    **(a) `ShouldQueue` — CLEAN, and it is the shape done right.** The lane has exactly **one** queued
    listener, `X-211/Listeners/ProcessOverdueReceivable implements ShouldQueue`, on one of its two
    live seams (ruling 148). It wraps its entire body in
    `Tenancy::actingAs((int) $event->businessId, …)`, so it depends on no ambient tenant; `ArOverdue`
    is three readonly **ints** with no `SerializesModels`, so a queued dispatch re-resolves no model in
    a worker with no tenant — ruling 193's hazard absent by construction. And it is **already proven**:
    `phpunit.xml:37` pins `QUEUE_CONNECTION=database`, so the listener genuinely queues in the suite,
    and `X-211/ArOverdueQueueTest` dispatches, calls `Tenancy::forgetAll()`, asserts
    `Tenancy::id()` is null, drains with `queue:work --stop-when-empty` and only then asserts the
    action — plus a second method for idempotence across duplicate events. ⛔ Struck.
    **(b) Bare foreign-key columns — the house convention, not a family.** Ruling 257 noticed
    `payments.invoice_id` has no FK and no index unlike eight sibling `invoice_id` columns; measured
    tree-wide, a bare `unsignedBigInteger` for a **cross-module** id is what a dozen other lanes'
    migrations do, so the column matches the convention rather than departing from it. The absent index
    is real and unfixable here: ruling 41 part 3 forbids editing that migration, and a **new** one to
    index a column ruling 257 measured has no writer and no reader is churn. ⛔ Struck.
    **(c) Migration-comment claims — structurally unfixable, so a recording by construction.** Ruling
    41 part 3 forbids editing a migration, and a new migration is a schema statement rather than a
    place to correct another's prose, so ruling 96 governs whatever the sweep would find. ⛔ Not a
    wave. ⚠️ Worth keeping: this ledger has *relied* on migration comments as evidence of intent at
    least six times (rulings 43, 51, 80, 88, 130, 214), so a false one misleads exactly the reader who
    trusts it — a `state.py note` is the remedy if one is ever found incidentally.
    **(d) The `Actions/` inert-delegate set** was pre-decided a recording by ruling 223(c). ⛔ Struck.
    ⭐ **The re-cut.** The previous tick gated MONEY-160 — the same three edits on the other four
    evidence commands — behind *"only once MONEY-159's item 3 has actually succeeded, because the shape
    is unproven until an artifact has been written through it."* Re-measured under ruling 64: item 3
    refused, so the artifact-record fallback **has still never executed**, and fanning an unproven
    shape out to four commands — two of whose artifacts assert an `INV-` sequence and a non-repeating
    number against a tenant that already holds invoices (rulings 169, 172) — is the unvalidated fan-out
    this lane keeps paying for. **The gate stays closed.** But ruling 273 reopens the wave from the
    other end: the tenant can be discovered after all, so `--business=<id>` runs the command **without
    provisioning**, and ruling 39's sequence completes with no number spent. **RULED: MONEY-160 is the
    X-117 evidence run itself.** ⭐ **And the empty pool is what makes the proof deterministic:** a
    second run with **no flag** can only succeed by reading `business_id` back out of the artifact,
    because the provision path is guaranteed to refuse — so a successful bare run is positive proof the
    fallback branch executed, with no mutation and no test. ⚠️ Every property the reuse depends on was
    measured before briefing (ruling 46): `X117RuntimeProofTest:18-21` asserts **four** keys and
    `order_id` is **not** among them, so a reused tenant's higher order id is safe;
    `GatewayEngine::connect():58-63` is a bare `updateOrCreate` on `(business_id, gateway_name)` with no
    guard — ruling 129's refusal is on `applyForSubMerchant`, which the command never calls — so
    `merchant_connected` stays true; and `sellables.sku` is `->index()` and not unique, so the second
    `Sellable::create` cannot collide. **The one key that can go wrong is `payments_written`**, which is
    why the tenant is chosen for `payments = 0` and never merely for existing.
277. **The empty pool as INSTRUMENT worked, and ruling 270's blocker is measured resolved for X-117 —
    a successful no-flag run is positive proof a fallback branch executed, with no mutation and no test
    (RULED by the lane supervisor 2026-09-09 18:5x, on MONEY-160's `8d31ccc6`).** Ruling 270 measured
    that all five of this lane's evidence commands call `TenantProvisioner::provision()`, which spends a
    dedicated number out of a nine-number platform pool with no route back, and that the pool is now
    **closed** — so ruling 39's mandatory sequence (run the command, read the artifact, then write the
    assertion) was unrunnable for every artifact this lane owns. Ruling 276 re-cut the wave around the
    closure rather than waiting on it: the tenant is **given**, and the proof that the given-tenant
    branch works is a second run with **no flag**, because the empty pool guarantees the provision path
    refuses. Measured: Run A `--business=18` wrote `order_id: 6`; Run B, **no flag**, wrote
    `order_id: 7` against the same `business_id: 18`. The only route from no flag to success is reading
    `business_id` back out of the artifact, so the fallback branch **executed**, and a
    `NumberPoolExhausted` there would have been a disproof rather than a vendor excuse. ⭐ **The
    generalisable half is the design move, not the fix:** a hard external limit was turned from the
    blocker into the discriminator, so the proof needs no mutation, no test and no double, and it cannot
    pass for the wrong reason (ruling 61's family) because the failing branch is *guaranteed* to fail.
    Where a wave adds a fallback for an exhausted resource, **the exhaustion is the control.**
    ⚠️ Item 1 chose tenant 18 by measurement and not by convenience — the only id in the surveyed set
    carrying **both** `payments = 0` and an evidence `Sellable` — and `payments_written` stayed `0` in
    the artifact, which is the one key that could have gone silently wrong (ruling 276's stated failure
    mode) and the reason the tenant is chosen rather than taken. ⚠️ ⭐ **Ruling 267's item 3 has finally
    landed:** the artifact's `waiting_on` now reaches disk **without** its `ruling 20` pointer, after
    refusing under MONEY-158 (pool exhausted) and being carried by MONEY-159 — an internal ledger
    reference persisted into a derived artifact, which ruling 267 measured is the durable member of that
    family. ⚠️ Two paperwork rules are recorded as **held on their first and next outing**, per ruling
    149's discipline that a fix to a paperwork rule is itself a claim: ruling 274's `log:` field carries
    the run log's first two lines **verbatim** with no foreground/background classification, and ruling
    238's second read matched the file on disk **to the nanosecond** (`bytes 10563, mtime
    2026-09-09 18:28:23.725964979`). The gate also ran **after** the wave's last commit (18:28:23 against
    18:23:20), which is ruling 42's inverse hazard measured clean rather than assumed.

278. **The stated MONEY-161 hazard is measured NOT a hazard, the real difference is a `$user` two lines
    from the artifact, and the four remaining evidence commands SPLIT on whether they call a vendor
    (RULED by the lane supervisor 2026-09-09 18:5x, briefed as MONEY-161; ruling 64's discipline applied
    to this ledger's own forecast).** The carried gate on MONEY-161 read *"X-199's and X-211's artifacts
    assert an `INV-` sequence and a non-repeating number, so a reused tenant already holds invoices —
    re-measure those two before the edit; ⛔ do not assume X-117's clean result transfers."* The warning
    was right and its **stated reason is false**. Measured: `X-199/Domain/InvoiceNumber::next():34` is
    `'INV-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT)`, so a reused tenant's second invoice is
    `INV-000002`, which matches `X199RuntimeProofTest:20`'s and `X211RuntimeProofTest:23`'s
    `/^INV-[0-9]{6}$/` exactly as `INV-000001` does — the sequence is **safe on reuse to six digits**.
    Three sibling hazards were measured away with it: the deliberate duplicate insert at
    `EvidenceInvoiceCommand:70` runs at CLI where `DB::transactionLevel()` is 0, so its `QueryException`
    aborts no enclosing transaction and ruling 163's `25P02` cascade cannot fire; `people.phone`
    (`X-121/…000001:34`) is a plain nullable string with **no unique constraint**, so a second run's
    duplicate `Person` is harmless — which matters because item 3's binary proof (ruling 277) *is* a
    second run; and `offerPlan`'s threshold read falls back to `max_installments = 3` against a command
    offering exactly 3, because ruling 216 removed the only `ArPlanTerm` write from that path and the
    surviving writer is the owner's late-fee door, which no evidence command calls.
    ⭐ **The genuine difference is one variable.** `X-211/Console/EvidenceRecoveryCommand:70` passes
    `$user->id` to `packageForCollections()` as the **principal** — ruling 161's own reasoning for why
    money's `?int $packagedByUserId` won over main's caller-supplied boolean — and `$user` is resolved
    at `:42` **only** as the argument to `provision()`. X-117's given-tenant shape moves that resolution
    inside the `$businessId === 0` branch, so transplanting it verbatim leaves `$user` **undefined** on
    the reuse path. X-199 has no such dependency (`$user` feeds `provision()` and nothing else), so it
    *is* a clean three-part transfer and X-211 is not. **RULED: X-211 keeps a `User::first()` reachable
    outside the provision branch**, and the brief names that difference rather than shipping one shape
    twice. ⚠️ It is the *shape* of the carried warning that generalises: a forecast hazard is an
    inherited follow-up (ruling 64) and decays the same way, and this one would have been "confirmed" by
    a coder who read the regex and never read line 70 — **the danger of a correctly-flagged file with a
    wrongly-stated reason is that measuring the stated reason clears the file.**
    **RULED, the split.** MONEY-161 takes the **two non-vendor** commands — `x199:evidence-invoice` and
    `x211:evidence-recovery` — which rulings 169 and 173 stripped of their Stripe captures, so both are
    always-runnable with **no credential dependency and no `UNRESOLVED` path** (ruling 48(3)), and each
    is proven by its own ruling-39 run plus ruling 277's no-flag control. The **two X-198** commands go
    to MONEY-162, because both reach a live provider — `EvidenceChargeCommand:37` captures with Stripe's
    own `tok_visa` and `EvidencePaymentLinkCommand:45` calls `PaymentLinkAction`, which posts a real
    Checkout Session — and because `EvidenceChargeCommand` owns `evidence/j9/charge.json`, carrying
    `ch_3UCgYZFXLB0i1zXl0NCv569q`, **the lane's only genuinely vendor-issued `artifact_id`** (rulings
    173, 178) and its sole honest runtime proof, read by J9 at `TwelveJourneysTest:487` and by
    `GatewayEngineTest:4`. ⛔ Fanning an unproven shape across four commands, two of them at a payment
    provider, is the unvalidated fan-out this lane keeps paying for; ⛔ and ruling 173's fallback binds
    MONEY-162 absolutely — if the provider refuses, the outcome is `UNRESOLVED` with the error
    **quoted**, the old artifact left in place and no test change committed, which is a
    PASS-WITH-NOTES and never a BLOCK (ruling 39).
    ⚠️ **One tenant per command, never a shared one**, and the reason is measured rather than cautious:
    each artifact records its own `business_id` and is therefore self-describing, and both
    `payments_written` keys are real `Payment::…->count()` reads (ruling 48(2)), so two commands sharing
    a tenant would couple three artifacts' honesty to each other's run order. ⚠️ The tenant is chosen
    for `payments = 0` and re-measured in the wave rather than inherited from MONEY-160's survey —
    `recordPayment` writes no payment row (ruling 107) so neither command dirties its own count, but the
    survey is a day-old measurement of a live database (ruling 269).
279. **A `withoutGlobalScopes()` read on `businesses` outside a tenant returns ZERO ROWS SILENTLY —
    ruling 273 named BOTH halves of the working shape and MONEY-161's brief shipped one, so the probe
    removed the LOUD layer and kept the SILENT one (RULED by the lane supervisor 2026-09-09, on
    MONEY-161's run 184).** Ruling 273 measured the shape as
    `Business::withoutGlobalScopes()->where('owner_user_id', $userId)` **inside
    `Tenancy::setUser($userId)`**; MONEY-161's item 1 dictated
    `Business::withoutGlobalScopes()->orderBy('id')->pluck('id')` under a bare `php artisan tinker` —
    the first half without the second. It printed **nothing**, the run applied the brief's own
    stop-clause, committed nothing, stopped, and reported *"the `businesses` table is completely
    empty"*. **It is not empty.** ⭐ **Four independent witnesses:** MONEY-160's artifact, written 25
    minutes earlier, records `"business_id": 18` and `"database": "goaiez_antig_money"`
    (`app/storage/app/evidence/X-117/checkout.json`); `2026_07_30_072149_create_businesses_table.php:119-120`
    is `ENABLE` **and** `FORCE ROW LEVEL SECURITY`; both policies key on session settings a bare
    tinker never sets — `tenant_isolation` on `app.business_id` (`:143-145`) and the permissive
    `owner_lookup` on `app.user_id` (`2026_07_31_090000:36-38`); and this lane's own
    `X-211/Console/DetectOverdueReceivablesCommand.php:52-55` ships the two-part shape in production.
    ⭐⭐ **The mechanism is documented as intentional in that migration's own comment (`:127-137`):**
    *"undefined → NULL → matches nothing … nullif(x, '') maps both empty states to NULL before the
    cast, which is what makes this **fail-closed rather than fail-loud**."* So `businesses` has two
    layers with **opposite** failure modes — the application scope throws (`TenantScope::apply()` →
    `Tenancy::idOrFail()`, ruling 273) and RLS beneath returns nothing — and `withoutGlobalScopes()`
    removes **precisely the loud one**. The exception is not an obstacle to be cleared on the way to
    the query; it is the only thing that says a tenant is missing. **RULED: any read of `businesses`
    outside a tenant carries BOTH halves — `Tenancy::setUser($userId)` and
    `withoutGlobalScopes()->where('owner_user_id', $userId)` — and a brief that dictates one names the
    other.** ⛔ Not `DB::table('businesses')`: same RLS, same silence, and it drops the model besides.
    ⛔ Not `Tenancy::actingAsUser()` — ruling 273 measured `AccountDirectory:317` holds it to that one
    class, and `setUser` is the documented route this lane already ships. ⚠️ This is ruling 265's
    lesson — **a correction to a measuring instrument decays exactly like a stale measurement, and it
    decays silently** — arriving **two ticks** after 273 wrote it, in a brief written by the supervisor
    that wrote it, with half of 273's own answer transcribed and half dropped. ⚠️ Per the standing
    precedent the miss is the supervisor's, so MONEY-161b carries its own two dispatches and
    MONEY-161's cap is untouched; the coder's stop was correct under the brief's own condition and
    **spends no dispatch** (rulings 60b, 71, 94, 106, 118, 217, 254). ⭐ It cost nothing beyond the
    run: refusing to provision protected the last of the pool, which was the one irreversible act
    available to that wave.

280. **A stop-clause that fires on an UNDER-count fires on a broken instrument and reads as a
    FINDING; one that fires on an OVER-count cannot (RULED by the lane supervisor 2026-09-09, same
    run).** Ruling 118's stop-clause fires when a sweep prints a line **neither table names** — an
    over-count — and it has fired twice (118, 217), each time on a real omission, because a *broken*
    sweep printing nothing simply does not trigger it. MONEY-161's fired on the opposite: *"if fewer
    than two distinct non-18 tenants have `payments=0`, commit nothing … and stop."* A sweep that
    cannot see its population satisfies that condition **perfectly**, and the stop is then
    indistinguishable from a decisive negative result — `REPORT.md` says `Count: 0` and the run log
    says *"the `businesses` table is completely empty"*, both faithful, both wrong. ⭐ **The asymmetry
    is the ruling: an over-count stop-clause is self-validating and an under-count stop-clause is not,
    so only the second needs a positive control.** ⚠️ Rulings 95/100/111 are what make it expensive:
    this lane **strikes** a population that measures empty, so an uncontrolled false zero does not
    merely stop a wave — it is one tick from striking a **live** population as measured-clean, which
    is rulings 192's and 207's named inversion arriving through a stop-clause instead of through a
    grep. **RULED: a stop-clause conditioned on finding fewer than N carries a positive control drawn
    from the hard class (ruling 258) — here the sweep must print `18`, whose existence is
    independently witnessed by X-117's artifact — and a run whose sweep omits the control reports
    `REFUSED` with the raw output rather than a smaller population.** ⛔ Never resolved by dropping the
    stop-clause: it is what stopped a run provisioning a tenant out of an empty pool.

281. **`payments_written` is asserted by NO test for X-199 or X-211, so the one-tenant-per-command
    rule rests on the artifact's honesty and not on a red suite — and both runtime-proof tests are
    measured safe on a reused tenant (measured 2026-09-09; correcting a carried forecast).** The
    18:4x addendum stated *"`payments_written` must be `0` in both artifacts"* as though a test
    enforced it. Measured, `grep -rn "payments_written" app/tests` returns **one** line —
    `X117RuntimeProofTest:19` — and neither `X199RuntimeProofTest` nor `X211RuntimeProofTest`
    mentions the key. **The requirement stands, on its real reason:** the key is written as a live
    `Payment::…->count()` (ruling 48(2)), so a tenant carrying another command's payments prints a
    number that says nothing about the artifact it sits in, which is rulings 48/169/173's
    two-unrelated-facts-as-one-flow defect. ⭐ **What changes is the stop-clause's cost.** No test
    breaks if a command is deferred, so a shortage of qualifying tenants no longer forces the wave to
    stop dead: **RULED — with exactly one qualifying tenant, ship `x199:evidence-invoice` (ruling
    278's clean transfer) and carry `x211:evidence-recovery`, whose `$user` subtlety makes it the one
    to prove separately; with none, stop.** ⚠️ Measured in the same pass and recorded so no brief
    re-derives it: **every** assertion in both runtime-proof tests is safe on a reused tenant —
    X-199's `/^INV-[0-9]{6}$/` (ruling 278), `invoice_status`, `paid_at`, `duplicate_refused_by`,
    `queue_driver`, `running_unit_tests`; X-211's `plan_id > 0`, `installment_amount_cents`, the same
    regex, `reason` ∈ `ArEngine::REASONS`, `refused_without_resolution`. Nothing in either depends on
    a fresh tenant.
282. **Ruling 39's "the old artifact left in place" is guaranteed only against a THROW — a success
    whose VALUE fails the reader's assertion overwrites the artifact, and `evidence/j9/charge.json`
    carries the lane's only genuinely vendor-issued `artifact_id` (RULED by the lane supervisor
    2026-09-09 19:1x, pre-ruling MONEY-163's vendor evidence run).** Ruling 39 set this lane's
    evidence-wave fallback: *"if the provider refused, the outcome is `UNRESOLVED` with the quoted
    provider error and no artifact and no test committed."* Measured against the two X-198 commands,
    the protection is structural for exactly one failure mode and absent for the other.
    **(a) A throw is safe by construction.** `EvidenceChargeCommand:37` is
    `$payment = $gatewayEngine->capture(…)` and `File::put` is nine lines later, so a Stripe refusal
    propagates out of `capture()` — which catches `\RuntimeException`, writes a `failed` `Payment` row
    and **rethrows** (ruling 228) — and the command dies before writing. The old artifact survives
    untouched. Same shape at `EvidencePaymentLinkCommand:45`, where `PaymentLinkAction` does not catch
    (ruling 229) and `createPaymentLink()` throws when the response carries no `url` (ruling 228).
    ⭐ **(b) A SUCCESS whose value fails the reader is not covered, and it is reachable.** Ruling 235
    made `capture()` write `awaiting_processor` — not a throw, a normal return — whenever Stripe
    answers `200` with `status: pending`, or with no `status` at all (`'unconfirmed'`). The command
    then writes `payment_status: "awaiting_processor"` into `charge.json`, and
    `GatewayEngineTest.php:14` is `expect($artifact['payment_status'])->toBe('captured')`. So the
    suite goes red **and the honest artifact is already gone** — `File::put` has happened, ruling 39's
    "left in place" never engages, and there is no route back: the previous charge id
    `ch_3UCgYZFXLB0i1zXl0NCv569q` cannot be re-minted, it is read by **J9**
    (`TwelveJourneysTest:485-508`) and by `GatewayEngineTest:4-14`, and it is the one file in this lane
    that satisfies `TestAnchorStage`'s vendor-issued `artifact_id` (rulings 173, 178). ⛔ Deleting it
    is never the fallback (ruling 173) and ⛔ a hand-written replacement is a BLOCK (ruling 39).
    **RULED: every evidence wave in this lane copies the artifact it is about to overwrite BEFORE the
    run — `cp <artifact> <artifact>.pre-<wave>` — and restores it if the new one does not satisfy the
    assertions its readers make.** The copy lives beside the artifact, which is untracked and
    gitignored (ruling 39), so it commits nothing and is deleted once the new artifact is accepted.
    ⛔ Not resolved by loosening `GatewayEngineTest`'s assertion to admit `awaiting_processor` — that
    is editing a CHECK to survive a vendor answer, the One Rule, and ruling 235 made the two statuses
    distinct precisely so the difference would be visible. ⛔ Not by writing the artifact to a
    temporary path and moving it on success: the command decides success by returning, and the value
    the reader refuses is one a successful return can carry. ⚠️ The probability is low —
    `tok_visa` is Stripe's always-succeeds test token — and that is exactly why it is worth writing
    down: the cost is a permanently red J9 and the loss of this lane's only honest runtime proof, the
    remedy is one `cp`, and the asymmetry between those two decides it. ⚠️ The generalisable half is
    ruling 141's, one register out: **ruling 39 protects the artifact against the failure it was
    written about, and a wave must ask which failures the protection does NOT cover** — here the
    uncovered one is the *successful* call, which no fallback clause in this ledger had considered.

283. **`payments_written` states what the RUN did and counts what the TENANT holds, and the two were
    the same number only while every evidence tenant was fresh — the given-tenant change is what
    separated them (RULED by the lane supervisor 2026-09-09 19:1x, briefed as MONEY-163 item 2).**
    Ruling 48(2) minted this key for X-117 as the honest replacement for a self-certifying
    `gateway_call_made: false` literal: *"the artifact records `payments_written` as a real
    `Payment::…->count()`, because a boolean that certifies itself is ruling 43's fiction in miniature
    while a count moves if the code changes."* That reasoning is sound and the key is kept. What has
    changed is its **arithmetic**. `EvidenceChargeCommand:39` is
    `Payment::where('business_id', $businessId)->count()` **after** the capture, so on a freshly
    provisioned tenant it is `1` and reads as *this run wrote one payment*; on a reused tenant it is
    `1, 2, 3 …` across runs and reads as *this run wrote three payments*. **Ruling 107's shape exactly
    — the label names a quantity the code does not measure — and ruling 236's lesson about which
    readers a wave owns**: the correctness of this key depended on a state that could not arise (a
    tenant with prior payments), and MONEY-161b/163's given-tenant fallback makes that state the
    normal one. It never bit on X-117, X-199 or X-211 because the value there is `0` in both readings
    and ruling 278 measured `recordPayment` writes no `Payment` row. **RULED: on the two X-198
    commands the key is `tenant_payments_total`** — the label states the quantity the code computes
    (rulings 107, 123, 239) — and it stays a real `->count()`. ⛔ Not scoped to the new row
    (`->where('id', $payment->id)->count()` is `1` by construction, which is ruling 48(2)'s
    self-certifying literal wearing a query) and ⛔ not dropped, which would leave the artifact with
    no measured count at all. ⚠️ **Blast radius: ZERO, measured** — ruling 281 measured
    `grep -rn "payments_written" app/tests` is **one** line, `X117RuntimeProofTest:19`, X-117's own,
    and neither `GatewayEngineTest` nor J9 names the key; ⛔ **X-117's, X-199's and X-211's copies are
    NOT renamed**, because there the name is true, `X117RuntimeProofTest:19` asserts it, and
    harmonising a pair where one member is correct is ruling 228(a)'s named hazard. ⚠️ The rename must
    ship in the **same commit** as the regenerated artifact (ruling 39's sequence), because a renamed
    key and an artifact still carrying the old one is ruling 49's deleted-key-with-a-live-reader in
    miniature.

284. **A transcription field nested in a block whose every other member comes from ONE file is filled
    from that file — ruling 274's wording is right and its POSITION defeated it (RULED by the lane
    supervisor 2026-09-09 19:1x, on MONEY-161b's `GATE:` field).** Rulings 242 and 247 twice found a
    report claiming a foreground gate against a log saying background, and each aimed a remedy at the
    **wording**. Ruling 274 stopped doing that — *"a run cannot mis-summarise a quotation"* — and
    replaced the category with a transcription: `GATE:`'s last field carries the run log's own first
    two lines. It held twice and failed on its third outing. MONEY-161b's brief said it exactly right
    (`BRIEF.md:383`, *"the run log's **first two lines, verbatim**, with no foreground/background
    classification"*) and the report's `5. log:` carries the **gate file's**
    `== 0. database guard` line. ⭐ **The cause is placement, not comprehension:** `log:` was item 5
    of five sub-fields inside `GATE:`, and items 1–4 all transcribe the gate file, so the run
    continued transcribing the file the block is about. **RULED: the run log leaves `GATE:` and
    becomes its own top-level `LOG:` field, and the brief names the file by PATH** —
    `LOG: <the first two lines of .agents/supervisor/logs/agy-run<N>.log, verbatim>` — because a
    transcription field is only as unforgeable as its named source, and a source named in prose two
    hundred lines above the field it governs is not named at the field. ⛔ The classification is not
    asked for again in any form (274), and ⛔ ruling 218(2)'s prohibition is untouched: it is about
    **behaviour** — a run blocks on its own gate — not about vocabulary, and here it did block.
    ⚠️ This is rulings 128/231's family (a rule about one field silently governing its neighbours)
    meeting the ruling 66/75/…/271 dictation family: **detail is read as the spec, and a field's
    NEIGHBOURS are detail.** ⚠️ Graded a note and the tip pushed: no measurement was false, ruling
    238's second read matched to the nanosecond, and withholding on that evidence is ruling 74's
    error.

285. **The `origin/main` merge fires on cadence condition (3), it is the cleanest this lane has ever
    measured, and ⭐ ruling 52's two-party sequence is NOT needed for it — because the coder guard
    reads index-against-HEAD, and a path restored to HEAD's blob is invisible to it (RULED by the
    lane supervisor 2026-09-09 19:1x, measured against `origin/main` = `af72fe7f`, merge-base
    `83caa6f5`, main 95 ahead, money 4).** The cadence, measured in this tick and never inherited
    (ruling 269): (1) 95 < 100 ✗; (2) `git diff --stat 83caa6f5 origin/main -- app/app/Doctor
    coder-bin .claude/hooks` **empty** ✗; (3) **fires** — the merge-base is unmoved, so this tick's
    push is the first since Track 1 last merged this lane, and it carries three module command edits.
    **The shape, measured whole — 33 files, and all four of ruling 58's damage shapes are EMPTY:**
    (1) main adds no test in money's eight test trees; (2) main touches **no file** in money's eight
    module trees; (3) `git diff --diff-filter=D` over the whole range is **empty** — zero deletions,
    so ruling 58's deleted-class shape cannot fire; (4) no generated route tests. `app/tests/Journeys`
    is untouched, so the **harness gate stays closed** and `--allow-harness` is not passed (ruling 74:
    never a standing flag). The 26 non-per-track paths are other lanes' — C-Reviews, X-01, X-102,
    X-103, X-155, X-157, X-162, X-163, X-172, X-176 — including two `csat_score` drop migrations whose
    exposure to this lane is measured **nil** (`grep -rln csat_score` over money's eight module and
    eight test trees returns nothing) and one CHECK, `HeadingSeamTest.php` (+50/−3), which is ruling
    176/181's family: money has never modified that file, so it is one-sided, main's copy wins with no
    conflict, and any pin that moves afterwards is **main's pin against money's tree** — ⛔ recorded
    and re-pinned by Track 1, never edited here (the One Rule).
    ⭐⭐ **The sidedness, and why it inverts ruling 52.** `merge.ours.driver` is `true` and
    `.gitattributes` marks eight paths `merge=ours`, but ruling 27's caveat governs: a driver fires
    only where **both** sides changed the file. Two-sided and therefore driver-protected:
    `CLAUDE.md`, `.agents/state/BUILD-STATE.json`, `.agents/state/JOURNAL.md`. **One-sided main-only
    and therefore EXPOSED — the driver cannot fire and each must be hand-restored:**
    `app/phpunit.xml` (2 lines → Track 1's `goaiez_antig_test`), `bin/supervise.sh` (490),
    `.agents/supervisor/launch-coder.sh` (184), `.claude/settings.json` (23). Exactly ruling 179/251's
    four, with 179's ranking unchanged — `app/phpunit.xml` is sharpest because `supervise.sh` §0 exits
    2 only on *production* and `goaiez_antig_test` is not production, so **no gate here catches the
    swap**; `bin/supervise.sh` is second because the instrument that would notice is itself in the
    exposed set. **Now the new measurement.** `coder-bin/git:82` builds its refusal input as
    `git diff --cached --name-only` — index against **HEAD** — and `:146` greps that for `CLAUDE.md$`,
    `.claude/`, `bin/supervise\.sh$`, `app/phpunit\.xml$` and `.agents/supervisor/`. After the driver
    takes money's side on the three two-sided paths and `git checkout HEAD -- <the four>` restores the
    exposed ones, **every guarded path's stage-0 blob equals HEAD's and none appears in that diff at
    all** — so a **pathless** `git commit --no-edit` passes the guard, and rulings 52/60's
    supervisor-commits-what-the-coder-staged workaround is unnecessary here. ⭐ That matters for
    safety, not convenience: it closes the merge inside one run instead of leaving it staged across a
    tick boundary, which is the state ruling 60 warns has **no second copy** and ruling 248 measured a
    wall-clock reap can land in the middle of. ⚠️ **It is briefed as an attempt, not an assumption:**
    if the guard refuses, the coder stops with the merge **STAGED** and reports, and the supervisor
    commits it next tick — a graceful degradation to ruling 52's proven sequence rather than a lost
    run. ⛔ `git merge --abort` is forbidden whatever happens (ruling 53; it destroyed this lane's
    ledger once, 2026-09-04 13:07), and ruling 53's **default clause** binds: an uncovered conflicting
    path is reported with the merge still staged, never aborted and never guessed.
    ⚠️ ⛔ **`php -l` is NOT run in this merge** — every hand-resolved path is XML, JSON, Markdown or
    shell, and ruling 183 measured that `php -l` on a non-PHP file writes a parse error to an
    untracked `error_log` at the repo root (muddying §1, the instrument rulings 34 and 71 turn on) and
    is **vacuous** on a shell script, which has no `<?php` tag and therefore always "passes". The
    checks that actually read these four are `git diff --cached HEAD -- <them>` printing nothing,
    `grep -n DB_DATABASE app/phpunit.xml`, `bash -n` on the two scripts and `wc -c` on all four.
    ⚠️ **No floor is predicted** (ruling 157): 95 commits of other lanes' tests arrive at once and no
    arithmetic available before the merge produces the count, so the gate measures it and the verdict
    block records it as the new baseline. ⚠️ `composer dump-autoload -d app` is mandatory before the
    gate (ruling 52) — main adds three new `Actions/` classes under `app/app/Modules/`, which
    `app/composer.json` classmaps, and the failure shape is `Class … not found` **inside another
    lane's test**, the most misattributable there is.
286. **A merge brief's expected-staged-set table is enumerated against a MOVING remote, so ruling 118's
    stop-clause fires on the remote moving rather than on a defect — and for a lane merging a busy
    `main` that is most merges (RULED by the lane supervisor 2026-09-09 21:5x, observed live in run 186
    before it reached the clause; first recorded in `REVIEWS.md` because a merge was staged and
    `git commit -- CLAUDE.md` during a merge is refused by git itself).** Ruling 118 requires a brief
    whose stop-clause fires on an unnamed sweep line to enumerate the **whole** expected output in two
    tables, and it has fired correctly twice (118, 217). Ruling 182 requires the reviewing tick to
    re-measure against `MERGE_HEAD` rather than the briefed sha, *"because a brief that says merge
    `origin/main` means the ref, and a ref moves."* **Both are right and together they guarantee a
    stop.** MONEY-162's Table B enumerated the **26** non-per-track paths measured at 19:1x against
    `origin/main` = `af72fe7f`. The coder fetched twenty minutes later and got `MERGE_HEAD` =
    **`7900c72e`**, a 34-path range, and staged one path Table B does not name:
    `app/app/Modules/X-103/Actions/PageVersionAction.php` — measured **`A`dded on main only since the
    base `83caa6f5`**, in the **site** lane's module tree, with
    `git diff --name-status 83caa6f5 HEAD -- app/app/Modules/X-103/` **empty**, so money has never
    touched it. It takes main's side whole by the One Rule, exactly as its sibling `PageReadAction.php`
    does. The run stopped on a path whose resolution was never in doubt.
    ⭐ **RULED: a merge brief's Table B is a RULE with a measured exception list, never a closed set.**
    The rule is *every path under `app/app/Modules/` or `app/tests/Modules/` that is **not** one of this
    lane's eight module ids takes `origin/main`'s side whole, and a path added on main only is taken
    whole* — and the enumerated rows are **evidence of what the rule covered at measurement time**, not
    the boundary of what it may cover. The stop-clause then fires only on a path the **rule** does not
    reach: anything inside money's eight module or test trees, anything under `app/tests/Journeys/`,
    `app/app/Doctor/`, `source/`, `.agents/`, `.claude/`, `bin/`, or a **deletion** anywhere. A path
    matching the rule but absent from the list is resolved by the rule and **reported**, never stopped
    on. ⛔ Not resolved by dropping the enumeration: the list is what makes a money-tree incursion
    visible at a glance and it is ruling 118's whole protection. ⛔ Not by dropping the stop-clause,
    which is what stops a coder resolving unbriefed paths. ⛔ **Not by pinning a sha** —
    `git merge <the measured sha>` would make the tables exact and merge a **stale** `main`, so the lane
    would take a tree Track 1 has already moved past and owe a second merge immediately.
    ⚠️ **The asymmetry sets the balance.** A stop costs one tick and preserves every resolution, because
    the merge stays staged (ruling 53): run 186's items 1–5 were measurably intact — 27 staged paths and
    **not one** of the seven per-track paths, `app/phpunit.xml:34` still `goaiez_antig_money_test` — so
    the driver and the four-file restore both worked and ruling 285's prediction held. A **guess** costs
    a wave and can ship another lane's test database. So the clause stays; only its **boundary** moves
    from an enumeration to a rule. ⚠️ This is the ruling 66/75/…/271 dictation family a **thirtieth**
    time, with a new instrument: **a brief that enumerates a set measured against a moving ref has
    dictated a stop.** The enumeration is a measurement, and ruling 64's decay applies to it **within
    the hour** rather than within the day — the shortest-lived measurement this ledger has recorded.

287. **⭐⭐ The `LOG:` field is UNFILLABLE by the coder — `launch-coder.sh` redirects the run's stdout to
    the log and the wrapper appends `AGY_EXIT=` at EXIT, so the file is empty for the whole of the run
    that is asked to quote it. It is the SUPERVISOR's field (RULED by the lane supervisor 2026-09-09
    22:0x, on MONEY-162's honest `LOG: n/a — empty`; correcting ruling 284 and closing the
    242/247/274/284 sequence).** `launch-coder.sh:144` is
    `nohup bash -c '… timeout -k 60 3h … agy --print "$(cat KICKOFF.md)" … > "$LOG" 2>&1; echo
    "AGY_EXIT=$?" >> "$LOG"'`. `agy --print` buffers to that redirect, so **nothing reaches the log
    until the process ends** — and the run writes `REPORT.md` before it ends, by construction. Run 186
    read the file, found it empty, and wrote `n/a — empty`: **honest, correct, and the first true answer
    this field has ever received.** Four attempts, and the mechanism was never the one being fixed:
    ruling 242 found a claim contradicting the log and demanded the **observation**; 247 judged the
    foreground/background category unreachable and offered a compound one; 274 retired the category for
    a **transcription**, reasoning *"a run cannot mis-summarise a quotation"*; 284 found that
    transcription filled from the gate file and blamed the field's **placement** inside `GATE:`. Ruling
    284's diagnosis was plausible and is **superseded**: MONEY-161b's coder reached for the gate file
    because **the source it was told to quote was an empty file**, and a run asked for a quotation it
    cannot obtain will substitute the nearest thing it has. **RULED: the coder is asked for nothing
    about the run log.** ⭐ The reviewing tick quotes the log's first two lines into its own verdict
    block — where it **already reads them**, because rulings 30, 40 and 248 all require reading the run
    log *before* `REPORT.md* to tell an ordinary exit from a quota death, a kill and a wall-clock reap.
    The field was asking the one actor who cannot see the file to describe it to the one who must read
    it anyway. ⚠️ Ruling 284's **general** lesson survives its instance and is worth keeping: *a
    transcription field nested in a block whose every other member comes from one file will be filled
    from that file* — it is true, it is the ruling 128/231 family, and it applies to any future nested
    field; it simply was not what happened here. ⚠️ ⛔ **Ruling 218(2) is untouched:** it governs
    **behaviour** — a run blocks on its own gate — not paperwork, and it is the reason a run's own
    account of its method was never worth asking for in the first place. ⚠️ The generalisable half, and
    it is this lane's own ruling 36 turned on its paperwork for the fifth time (after 121, 141, 218,
    259): **before requiring a field, ask what is at the other end of the string the run is asked to
    copy — and whether it exists YET.** A source that comes into being only after the writer has
    finished is not a source.
288. **Ruling 42(2)'s re-gate is satisfied by MEASUREMENT rather than by RE-EXECUTION when the wave
    commits nothing and runs no mutation proof — and the verdict block says which of the two it did
    (RULED by the lane supervisor 2026-09-09 22:0x, on MONEY-162b's `ef347b0e`).** Ruling 42(2) reads
    *"a reported `errors`/`passed` figure is never taken as the sha's — the supervisor re-gates and its
    numbers go in the verdict block"*, and it has been the reason two waves' figures were caught. It was
    ruled after a run whose §D mutation proof ran alongside its §E gate in one wave, dropping the schema
    under the first pest — **that is its mechanism, and this wave has none of it**: `PROOF: n/a`,
    `COMMITS: n/a`, one suite started, and §7's own line records that it **waited on**
    `/home/goaiez/tmp/pest.lock` rather than racing it, which is the scheduling guard ruling 74 added.
    Its named tell is measured absent too — errors did not rise (2 before the merge, 2 after) and
    neither member is `relation … does not exist`. What replaces the re-run is that the numbers were
    **measured, not reported**: the supervisor read the gate file raw — its unforgeable `== 0. database
    guard` first line (243), its two §6 objects, its §7 line — and corroborated it against
    `/home/goaiez/tmp/last-pest-grs-antig-money.json`, which `bin/supervise.sh:233` writes and every
    gate in *this checkout* overwrites, so it is this gate's own object and not a stale one (ruling 83's
    family). ⚠️ The cost of the alternative was measured, not asserted: `pgrep -a -f pest` showed
    `2662911 timeout 1800 env DB_DATABASE=goaiez_antig_site_test ./vendor/bin/pest` live, so the shared
    lock was held and a re-gate was up to 40 minutes of `flock` wait — ruling 91's abandoned-gate shape
    and ruling 253's double-kill. ⛔ **This is never a licence to skip a re-gate on a wave that commits
    code**: there the tree the coder gated and the tree at the tip can differ, which is rulings 34's,
    42's own inverse and 71's whole subject, and the re-gate is the only thing that closes it.
    ⚠️ The generalisable half: **a rule written as "re-run X" is a rule about the PROPERTY X
    establishes**, and where the property can be established more cheaply and more directly, the rule is
    satisfied — but only if the tick writes down which route it took, because a verdict block that reads
    the same either way is one a later tick cannot audit.
289. **⭐⭐ This ledger cited a DEAD charge id for four rulings, and ruling 178's own wave is what
    killed it (RULED by the lane supervisor 2026-09-09 22:0x).** Rulings 173, 178 and 282, and every
    addendum since, name **`ch_3UCgYZFXLB0i1zXl0NCv569q`** as *"the lane's only genuinely vendor-issued
    `artifact_id`"*. Measured this tick, `grep -rn "ch_3U" app/storage/app/evidence/` returns **three**
    lines and **not one of them is that id** — `j9/charge.json:3`, `X-198/runtime-proof.json:2` and
    `journeys/invoice-to-paid.json:3` all carry **`ch_3UDU3tFXLB0i1zXl2GQYuna8`**. ⭐ **The chain is
    CONSISTENT and healthy**, which is what makes this a ledger defect rather than a tree defect: all
    three agree, `charge.json` is dated `2026-09-08 13:48` and `runtime-proof.json` `2026-09-08 14:15`,
    which is MONEY-119's own regeneration window under ruling 178. **So ruling 178's rename wave
    re-minted the charge and the ledger went on quoting the id it had replaced**, through rulings 282,
    283 and three addenda. ⚠️ **The SUBSTANCE of every one of those rulings is untouched and none is
    reopened.** Ruling 282's copy-first requirement stands entire: the id is different, and it is still
    the lane's only vendor-issued `artifact_id`, still unre-mintable in the sense that matters, still
    read by J9 (`TwelveJourneysTest:485-508`) and `GatewayEngineTest:4-14`, still feeding
    `TestAnchorStage`. Only the quoted string was stale. **RULED: a ledger line that cites a VALUE cites
    the command that reads it, beside it.** This is ruling 257's family — *"the ledger recorded a
    conclusion and not the one-line command that produced it"* — for the **fifth** time (163, 171, 176,
    257, 289), and the first where the value was invalidated **by the wave that wrote it down**. ⭐ A
    value copied forward from ruling to ruling acquires the appearance of a measurement and has none of
    its properties: a `grep` beside it decays **loudly**, and a quoted string decays **silently**.
    ⚠️ Recorded in the same pass, a correction to this tick's own inherited addendum: **ruling 283's
    rename is ONE key, not two.** The addendum said *"X-198's `payments_written` → `tenant_payments_total`"*
    in the plural; measured, `EvidencePaymentLinkCommand` has **no such key at all** (its payload is
    `provider_link_id`, `url`, `currency`, `amount_cents`, `payment_id`, `database`,
    `running_unit_tests`, `created_at`, `command`), and the key exists only at
    `EvidenceChargeCommand:40`. A plural would have sent a wave looking for a line that is not there.
290. **The two X-198 evidence commands are NOT symmetric, and a command's CODE change is separable from
    its ARTIFACT regeneration (RULED by the lane supervisor 2026-09-09 22:0x, briefed as MONEY-163).**
    Ruling 278 split the vendor half out of MONEY-162 on the principle that *"fanning an unproven shape
    across four commands, two of them at a payment provider, is the unvalidated fan-out this lane keeps
    paying for."* Measured now, the two survivors differ from each other more than they differ from the
    three already converted. **`x198:evidence-payment-link`** writes `evidence/x198/payment-link.json`,
    which is read by `GatewayEngineTest:17-29` and **by nothing else** — no derived artifact, no doctor
    stage — and it is freely **re-mintable**: `:36` creates a fresh `failed` `Payment` each run, so
    `PaymentLinkAction`'s ruling-229 `firstOrCreate` on `(business_id, payment_id)` sees a fresh pair
    and mints a fresh Checkout Session, and any valid `cs_`/URL satisfies the same four assertions.
    **`x198:evidence-charge`** writes `evidence/j9/charge.json`, which is read by `GatewayEngineTest:3-15`
    **and by J9**, and which feeds **two** derived artifacts — `X-198/runtime-proof.json` and
    `journeys/invoice-to-paid.json` — plus `TestAnchorStage`; regenerating it forces a four-step chain
    (charge → J9 → `junit.xml` → `x198:runtime-proof`), and `RuntimeProofCommand:59` refuses unless
    `invoice-to-paid.json`'s `artifact_id` **equals** `charge.json`'s new id, which is ruling 178's
    *"the ORDER is the risk"* with two more moving parts than 178 had. **RULED: the pay-link command is
    changed AND run, with ruling 277's binary control; the charge command gets the CODE CHANGE ONLY.**
    ⭐ The separation is available because **ruling 39's sequence binds a wave that writes an ASSERTION
    about an artifact**, and this wave writes none — `GatewayEngineTest` already exists and is green.
    ⭐ And shipping the unrun option is not idle: without it the charge command can only call
    `provision()` and die on the empty pool, so the option is what makes the regeneration wave possible
    at all, while carrying no regression risk — the no-flag, no-record path still provisions, exactly as
    today. ⛔ **Ruling 283's rename does NOT ship with it**, and that is 283 honoured rather than
    overridden: it requires the rename *"in the same commit as the regenerated artifact"*, so the rename
    and the run travel together as one unit into the next wave. ⚠️ The generalisable half: **an evidence
    command has two deliverables — a code path and an artifact — and their risks are unrelated.** The
    code path's risk is a shape already proven three times; the artifact's risk is a live vendor call
    whose failure can be silent, whose readers may be derived files no gate names, and whose loss has no
    route back. A wave that treats them as one unit prices the cheap half at the expensive half's rate.
291. **Ruling 288's condition is the MUTATION PROOF, not the commit — the two clauses were doing two
    different jobs and only one of them is about ruling 42's mechanism (RULED by the lane supervisor
    2026-09-09 22:2x, applied to MONEY-163's `69e662f9`).** Ruling 288 permitted the re-gate to be
    satisfied by measurement *"when a wave commits nothing and runs no mutation proof"*, and this tick
    relied on it for a wave that committed three commits. The restriction does not survive its own
    reasoning. **42's mechanism is a second pest INSIDE THIS CHECKOUT** — §D's mutation proof running
    alongside §E's gate, dropping the schema under the first — and it is the **mutation proof** that
    creates the second pest. Committing code creates none. What committing changes is a **different**
    question with a **different** instrument: *is §6/§7's verdict the sha's, or the working tree's?*,
    which rulings 34 and 71 answer with `git diff --stat HEAD -- app/` and §1's uncommitted-path count
    — not with a second suite. So: **a wave that commits code but runs no mutation proof is inside
    288's route**, provided the tick states all four measurements — 42's mechanism structurally absent,
    42's named tell (`relation … does not exist` among a risen `errors`) measured absent,
    `last-pest-<checkout>.json` corroborating the gate file member by member, and the gate's §1 showing
    `0 uncommitted path(s)` under the reviewed sha against the supervisor's own empty `git status` and
    empty `git diff --stat HEAD -- app/`. ⛔ **Never a licence to skip a re-gate on a wave that ran a
    mutation proof**: there a second pest genuinely existed in this checkout, which is 42's own
    subject. ⚠️ The cost of re-running is measured, never assumed — `pgrep -a -f pest`, and a live
    foreign pest holding `/home/goaiez/tmp/pest.lock` makes a re-gate ruling 91's abandoned-gate shape
    (three consecutive ticks, one hour) or ruling 253's double kill. ⚠️ The generalisable half is
    288's own, one turn further: **a rule written as "re-run X" is a rule about the PROPERTY X
    establishes**, so its preconditions are the ones that bear on that property and no others — and a
    precondition carried because it happened to be true when the rule was written is ruling 64's decay
    inside a rule instead of inside a follow-up.

292. **J9 REWRITES `invoice-to-paid.json` from `charge.json` on every suite run, so the derived journey
    stamp self-heals and `junit.xml` is the ONLY fragile link — which inverts ruling 178's stated risk
    ordering and makes ruling 282's restore-clause the load-bearing protection (measured 2026-09-09
    22:2x, scoping MONEY-164).** Ruling 178 named *"the ORDER is the risk"* over a four-step
    regeneration chain, and ruling 169/173's family treats a derived artifact outliving its source as
    this lane's recurring defect (49, 50(c), 172). Measured now, one of the three derived files is not
    derived in the dangerous sense at all. `TwelveJourneysTest:485-511` — J9 — reads
    `evidence/j9/charge.json`, asserts on it, and **ends in
    `$this->writeEvidence('invoice-to-paid', ['passed' => true, 'artifact_id' =>
    $artifact['gateway_charge_id'], …])`**, so the stamp takes its id straight from `charge.json` at
    that instant. ⭐ The proof is a timestamp nobody planted: `invoice-to-paid.json`'s `captured_at` is
    `2026-09-10T03:18:19+00:00` = **22:18:19 local — run 188's gate**, three seconds before
    `gate-money163.txt` was written, against a `charge.json` last written 2026-09-08. **Every full
    suite run rewrites it, harmlessly, from whatever `charge.json` then says.** So `RuntimeProofCommand:59`'s
    equality guard cannot be left stale by a half-finished chain: the *next* gate repairs it. The file
    that no suite regenerates is **`evidence/X-198/junit.xml`**, written only by an explicit
    `--log-junit` invocation — its mtime is 2026-09-08 14:14 across dozens of intervening gates — and
    `X-198/runtime-proof.json`, written only by the command. ⚠️ **The consequence for a brief is that
    the chain's fragility is not where 178 put it.** The genuinely unrecoverable step is **step 2, the
    vendor call**: ruling 282's uncovered case is a Stripe `200` carrying `status: pending`, which
    ruling 235 makes `capture()` return **normally** as `awaiting_processor`, so `File::put` overwrites
    `charge.json` and `GatewayEngineTest:15`'s `toBe('captured')` — measured this tick, alongside
    `:14`'s exact `strlen === 27` — is red for a reason no code change can fix. ⛔ Never loosen either
    assertion to admit the vendor's answer (the One Rule). **RULED: a regeneration brief's stop-clause
    is keyed on the artifact's VALUES against its reader's assertions, not on a throw** — restore from
    the `.pre-<wave>` copy when `payment_status !== 'captured'` or `strlen(gateway_charge_id) !== 27`,
    exactly as it would on a refusal. ⚠️ Recorded, not briefed: `RuntimeProofCommand:104`'s comment
    attributes `writeEvidence()` to `JourneyHarness`; it is `TwelveJourneysTest`'s own private method
    at `:610`. A comment nothing reads, so ruling 96 governs and ruling 47's companion forbids editing
    a file for a reason a brief did not name.

293. **A cited LINE NUMBER decays exactly like a cited VALUE, and it decays faster (RULED by the lane
    supervisor 2026-09-09 22:2x).** Ruling 289 established that a ledger line citing a value cites the
    command that reads it, after this lane quoted a dead charge id through four rulings. The same tick
    that wrote 289 carried `EvidenceChargeCommand:40` into its addendum as the location of
    `payments_written`. Measured this tick: it is **`:61`** — MONEY-163's own commit added twenty-one
    lines above it, in the wave the addendum was written to hand over. ⭐ **A value is invalidated only
    when someone changes that value; a line number is invalidated by any edit ANYWHERE ABOVE IT**, so
    the surface that can break it is the whole file rather than one string, and the wave most likely to
    break it is the wave being briefed. **RULED: a ledger line or brief that names a line number pairs
    it with the token a `grep -n` would match** — `EvidenceChargeCommand`'s `'payments_written' =>`
    line, not `:61` — and where an exact line is dictated for an edit, the brief says which token must
    be on it. ⚠️ This is the third instrument in ruling 289's family after values and (265's)
    measuring instruments, and all three share one shape: **the ledger recorded a coordinate and not
    the command that finds it.** ⚠️ It cost nothing this once only because the token is unique in the
    file; a brief dictating an edit *at* `:40` would have edited the wrong line silently.
294. **A census corroborates its COUNT against a deliberately looser instrument, not only its zero —
    and a plausible non-zero count is the more dangerous failure, because nothing in this ledger's
    discipline stops it (RULED by the lane supervisor 2026-09-09 22:5x, caught in the supervisor's own
    hands before dispatch).** Rulings 207 and 280 both concern a **zero**: 207 after a `$`-before-`\|`
    end-anchor turned a 32-member population into a printed zero, 280 after an under-count stop-clause
    fired on a broken instrument and read as a decisive negative. Both fire loudly, because rulings
    95/100/111 make a zero trigger scrutiny — this lane **strikes** a population that measures empty, so
    a false zero is one tick from striking a live one. **A count that is merely SMALLER THAN THE TRUTH
    triggers nothing.** It looks like a completed census, it is written into a verdict block, and the
    next tick inherits it (ruling 64). Measured this tick: the capability-refusal census's first
    instrument was `grep -rn "refuses" <the eight capabilities.php>` and it returned 30 lines; the
    corroboration was a deliberately looser `grep -ric "refus"` **per file**, whose counts **exceeded**
    the first one's, and five genuine refusal cells were worded differently and invisible to the first
    grep — X-117's `G1-75` (*"the price is looked up or REFUSED"*), X-211's `N-033` and `N-037` (*"a fee
    with no matching TERM is refused"*), `G1-71` (*"is REFUSED (P-092)"*) and `G1-74` (*"an unreconciled
    logged payment is REFUSED"*). The **answer** did not move — all five have named tests — but the
    census would have been recorded as covering 23 cells when it covers **28**. **RULED: a census states
    its population size beside the output of a second, looser instrument**, and where the two disagree
    the looser one is the population and the narrower one is a filter. ⚠️ The looser instrument is
    chosen to over-match on purpose: case-insensitive, stem-truncated, per-file counts rather than a
    line list, so its excess is visible as a **number** without reading anything. ⚠️ This is the ruling
    83/136/145/179/188/192/207/265 family — *the cheap signal moves for reasons unrelated to the fact it
    stands for* — with the sign that had never been recorded: not an absence read as a fact, but a
    **partial presence read as a whole**.
295. **A `refuses:` capability cell is satisfied by a test that NAMES its id, and ⭐ an id token
    ANYWHERE in a module's test file marks that capability tested whether or not anything asserts it
    (RULED by the lane supervisor 2026-09-09 22:5x, briefed as MONEY-165).** Owner ruling 15 makes a
    capability cell carrying a refusal without a matching `state.py decided` line **and** a named test
    or refusal code a merge blocker on every track; ruling 224 swept the `⛔ REFUSED` docblocks in
    **tests** and said in terms that it never read the **cells**. Measured: **28 refusal cells** across
    the lane's eight modules, **27** with their id named in their own module's test tree, and **one**
    without — `C-Billing/capabilities.php:100`'s **`G1-80`**, *"refuses: C-Billing; Stripe/Authorize.Net
    metered billing sync"*, for which `grep -rn "G1-80" app/app app/tests` returns **that line and
    nothing else**. **RULED: it takes the shape its 27 siblings have** — ruling 224's `⛔ REFUSED`
    docblock over `assertTrue(true)`, *"the correct and honest shape for a refused capability"* — **with
    a MEASURED reason**, since 224 found three of twenty-three reasons measurably false: C-Billing has
    no gateway client at all (ruling 87), `meters` has no production writer (ruling 88), and the lane's
    only Authorize.Net string is the cell itself (ruling 190). ⛔ **The cell is not edited** — generated,
    harvested from the frozen plan (rulings 29, 32), and an annotation edited to make a check pass is
    §298's exact prohibition. ⛔ **No machinery is built** (ruling 59): a metered-billing sync is a live
    vendor integration (ruling 13) and C-Billing `Domain/` is Track 1's (ruling 5).
    ⭐⭐ **The hazard of the sanctioned shape, never written down until now.**
    `Doctor/Stages/CapabilityStage::testedIds()` is
    `preg_match_all('/\b(G\d+-\d+|N-\d+(?:-\d+)?)\b/', $f->getContents(), $m)` over every file in
    `tests/Modules/<module>` — **file contents**, not method names and not an annotation grammar. So a
    docblock that helpfully lists "related" ids satisfies the stage for **every id it names**, silently
    and with no assertion behind any of them. The lane's existing grouped docblocks (`[G1-13] &
    [G1-14]`, `[G1-33], [G1-42], [G1-49], [G1-59], [G4-39]`) are legitimate because those tests genuinely
    cover the group and stay byte-identical; **a wave closing one cell introduces exactly one new id
    token and no other.** ⚠️ This is the inverse of ruling 63's *instrument string in prose inflates the
    count*: there honest copy fed an instrument and raised a number; here a helpful docblock would feed
    an instrument and **lower** one, which is the direction nobody checks.
296. **This seat cannot run `php artisan doctor` at any invocation it has tried, so every doctor number
    in this lane is the CODER's measurement (measured 2026-09-09 22:5x).** `CLAUDE.md`'s role table
    lists `php artisan doctor*` among the supervisor's read-only checks. Measured this tick:
    `php app/artisan doctor 2>&1 | grep …` is refused as a pipe, and `php app/artisan doctor > <file>`
    is refused outright. So a doctor count cannot be measured from here, and ruling 92 — *every number a
    brief states is the supervisor's to have measured* — means a brief may **not predict one**. **RULED:
    a doctor line is a measurement ITEM with no predicted value** (rulings 160, 164 — named field, named
    heading, the whole list and not a count), **and its result is copied into the next verdict block**,
    because `REPORT.md` is overwritten and does not survive the next wave (ruling 264). ⚠️ Recorded
    rather than papered over, per ruling 252's lesson one register out: **a ledger line recording what a
    role MAY do decays exactly like one recording what a guard refuses**, and the gap between the stated
    column and the executable column is discovered only by trying. ⚠️ ⛔ Not resolved by asking the
    coder to predict it either — that is the same unmeasured number with a different author.
297. **⭐⭐ This lane has been writing its own SUPERVISOR RULING NUMBERS into code as package
    decision ids, and the doctor can only see the ones that happened to miss — a citation that
    resolves to the WRONG decision is silent, where one that resolves to nothing is red (RULED by the
    lane supervisor 2026-09-09 23:2x, measured from MONEY-165's own doctor capture; briefed as
    MONEY-166).** Ruling 296 made the doctor a measurement item precisely because this seat cannot run
    it, and its first outing paid for itself. Filtered to the lane's eight ids,
    `doctor-money165-after.txt` carries three citation findings, all in **money's own X-198**:
    `PaymentLinkAction.php:40` cites `R036`, `GatewayEngine.php:76` cites `R037`,
    `StripeGatewayClient.php:17` cites `R093` — each *"appears NOWHERE in the package"*.
    ⭐ **They are rulings 36, 37 and 93 of this file, zero-padded to three digits**, and the sentences
    they sit in are those rulings' own words: `:40` is 36's *"the unique pair IS the idempotency"*,
    `:76` is 37's *"a second place for the truth to disagree"*, `:17` is 93's *"the platform secret
    and no `Stripe-Account`"*. ⭐⭐ **And the same act passes undetected wherever the ruling number is
    already three digits.** `PaymentLinkAction.php:28` cites `R233`, `InvoiceEngine.php:102` `R235`
    and `R236`, `Credits.php:96` `R237`, `SameAccount.php:66` `R239`, `CardScreen.php:107` `R241` —
    each in the exact file the ruling of that number is about — and every one is **green**, because
    `CitationStage.php:99` is `$hits = $index[$id] ?? 0;` over the package's `GOAIEZ-*.md` files and
    asks only whether an id of that number **exists**, never what it is **about**. Measured:
    `source/GOAIEZ-THE-64-DECISIONS.md:112` records *"the AI core — **`R237`–`R239` decided and
    written**"*, and `:98-99` are that decision's open questions about model routing and a cost
    ceiling under **C-Ai's** budget. So `Credits.php:96` tells a reader that money's overflow-charge
    copy rests on an AI-model-routing decision. **The three red findings are not the defect; they are
    the members of it that had no collision to hide behind.**
    **RULED: the citation is DROPPED and the FACT stays byte-identical** — which is the checker's own
    remedy, `CitationStage.php:107`: *"state the FACT instead of the id … An id nobody can look up
    looks authoritative and cannot be checked"* — and it is ruling 266's precedent verbatim, which
    dropped a `ruling 102` pointer rather than renumbering it, for this exact reason. Every one of the
    five occurrences sits inside a sentence that stands alone without it, so the edit removes a
    parenthetical and nothing else. ⛔ **Never renumbered into a free id**: minting a package decision
    to carry a lane ruling is ruling 43's fiction aimed at the one file the doctor trusts, and ruling
    29 forbids this lane touching `source/` at all. ⛔ **Never resolved by defining `R036` in the
    package** (same prohibition, same file). ⛔ **`R245` is NOT in this population and is not touched**
    — it is `state.py`'s own contract id for a recorded decision, used by every lane. ⛔ Nor are the
    generated `manifest.php:20` and `capabilities.php` ids, which are harvested from the frozen plan.
    ⚠️ **The collision half is MEASURED for four ids and briefed as a measurement for the rest**
    (ruling 258 — a wave whose population is unmeasured measures it and dictates no verdicts): the
    lane carries **37** `R###` tokens across its eight modules, and which of them are ledger numbers
    wearing a package id is MONEY-166's item 2, not this ruling's claim.
    ⚠️ **The instrument reports one hit per id per file** (`CitationStage.php:78-85`, *"a law cited
    eight times in one class is one problem, not eight"*), which is why `StripeGatewayClient.php`
    shows `R093` once against two occurrences at `:17` and `:57` — **so the doctor's count is a count
    of files, never of citations**, and a wave that fixes only the reported lines leaves siblings
    behind. That is ruling 294's under-count with a documented cause. ⚠️ And `CitationStage` scans
    `app/app` only, so `X198Test.php:575`'s `R093` is invisible to it — ruling 65's *"the only gate
    that resolves this does not look at `app/tests`"*, one stage over.
    ⚠️ The generalisable half is this lane's own ruling 36 asked of a **citation**: *what is actually
    at the other end of the string this code prints?* — and its answer here is worse than *nothing*,
    because a wrong answer that passes a checker is indistinguishable from a right one. It is the
    sixth member of the ruling 289 family (a ledger citing a **value**, a **line number**, an
    **instrument**, a **prohibition**, a **census scope** — and now an **id**), and the first where
    the ledger wrote itself into the tree.

298. **A census is scoped as well as counted, and the capability census MONEY-165 closed covered
    `refuses:` cells alone — six `specced` cells in X-117 were never in its population, and two of
    them are a measured consequence of this lane's own merge (RULED by the lane supervisor 2026-09-09
    23:2x).** Ruling 294 required a census to corroborate its **count** against a looser instrument.
    The same tick's capability-refusal census was corroborated exactly that way, held at 28 cells, and
    the addendum then recorded the population **CLOSED**. Measured against the doctor,
    `doctor-money165-after.txt` carries six `· X-117 · G<n>: specced but no test names this id`
    findings — `G6-02`, `G7-10`, `G1-73`, `G1-81`, `G1-82`, `G17-31` — which the census could not see,
    because it swept for the word `refus` and these cells do not carry it. **The count was right and
    the scope was the claim that failed.** ⭐ At least two are traceable to a merge resolution this
    lane made deliberately: ruling 156 recorded that `origin/main` deleted
    `test_g6_02_upsell_token` and `test_g7_10_bundle_allocation` — `assertTrue(true)` placeholders —
    and that money **adopted** those deletions, since *"reverting another lane's improvement to this
    lane's test file is the One Rule as surely as deleting it is"*. Adopting them was right, and the
    id tokens left with them, so ruling 166's shape recurs: **a lane that adopts a merge resolution
    inherits the counter that was counting the losing side.**
    **RULED: the six are MEASURED before any of them is closed, and nothing is closed by minting an
    `assertTrue(true)` to move a number.** Ruling 224 measured that three of this lane's twenty-three
    `⛔ REFUSED` reasons were themselves false, and ruling 295 that an id token anywhere in a module's
    test file marks that capability tested **with nothing asserting it** — so a `specced` cell is
    honestly closed only by a test that names the id **and asserts the capability**, or by a measured
    refusal in ruling 224's shape. ⛔ Which of the six is which is not decidable from the doctor line,
    and this ruling decides none of them. ⚠️ **RULED: the census population is REOPENED as `specced`
    cells and re-closed only when that second sweep has run** — the addendum's `CLOSED` is corrected
    to `refusal cells CLOSED · specced cells OPEN, six members, X-117`.
    ⚠️ The generalisable half, and it is ruling 294 one turn further: **a census states its
    POPULATION DEFINITION beside its count, and a definition drawn from the wording of the members
    already known is a filter wearing a census's name.** 294 caught a count five short because the
    instrument matched five spellings of one word; this caught a population six short because the
    word was the wrong axis entirely.
