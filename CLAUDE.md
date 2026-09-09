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
| Commits **only its own files** (`CLAUDE.md`, `bin/supervise.sh`, `.agents/rules/10-supervisor.md`) as `chore(supervisor): …`, and pushes **only a sha it has gated and recorded in `REVIEWS.md`**, by explicit ref (`git push origin <sha>:track/pricebook`) — never a branch head, never `--force` (owner, 2026-09-05 08:0x) | Runs `state.py decided\|unresolved\|stage\|note`, migrations, tests, `git commit` |
| **Never:** migrate, touch a database, edit `app/**`, run a test suite outside `supervise.sh --tests`, commit a path outside the three files above (`app/phpunit.xml` restored by the coder is the one exception, ruling 27) | **Never:** edit `BRIEF.md`/`REVIEWS.md`, push before `PASS`, edit sealed or generated files |

`.claude/settings.json` enforces your column. If a check needs a command the
deny list blocks, that is the signal it is the coder's job — brief it.

## The mailbox — `.agents/supervisor/`

| `BRIEF.md` | you → coder. The current directive, overwritten in place. Its `push:` line is the push gate |
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

## Dispatching the coder (added 2026-09-02)

When the user has enabled the settings rule for
`.agents/supervisor/launch-coder.sh`, the supervisor launches runs itself:
write `KICKOFF.md`, arm the run's monitor, then
`bash .agents/supervisor/launch-coder.sh`. The script refuses a second
concurrent run and auto-numbers logs.

**Retry cap — absolute:** at most **two** dispatches per BLOCK (the original
run plus one fix run). If the same BLOCK item survives a second dispatch,
STOP and put it to the user — never dispatch a third time for the same
failure, never loosen the check to get past it. A journey/wave marked green
by the coder is never taken at face value: the supervisor's own gate decides.
Never run `state.py done/journey/stage` from the supervisor; never touch
`app/Doctor`; never let the coder and supervisor loop without a human seeing
each verdict block in `REVIEWS.md`.

## Style

Terse and factual. Cite rules and traps by name — "that is the One Rule",
"that is the count-did-not-fall trap". Warn on dangerous git operations rather
than assuming they are blocked: the deny rules are prefix matches and stop a
habit, not a determined reordering.

## TRACK 4 — pricebook (this worktree)

This checkout is **Track 4**: branch `track/pricebook`, worktree
`/home/goaiez/agents/grs-antig-pricebook`. Track 1 (`/home/goaiez/agents/grs-antig`
on `main`) is the ONLY track that merges to `main`. This track pushes to
`origin track/pricebook` after a PASS; Track 1's supervisor reviews and merges.

- Databases: dev `goaiez_antig_pricebook`, tests `goaiez_antig_pricebook_test` (the gate
  exports it over phpunit.xml's pin; brief every pest run with the
  `DB_DATABASE=goaiez_antig_pricebook_test` prefix). `goaiez_antig` is production and
  `goaiez_antig_dev`/`goaiez_antig_test` belong to Track 1 — touch neither.
- Journeys owned: J3 (a quote comes from the pricebook or does not come at all).
- Modules owned: X-163, X-119, X-126. Edits stay under `app/app/Modules/<id>/**` for
  those ids, plus the owned journeys' methods in
  `tests/Journeys/TwelveJourneysTest.php`. OUT of scope: every other track's
  modules and journeys, `resources/views` and `app/Livewire` (Track 2),
  and everything in Track 1's never-list (Doctor, seals,
  `JourneyHarness.php`, phpunit DB lines, generated manifests).
- Goal: J3 green on the real pricebook path; a quote with no pricebook line never goes out.
- **Lane since 2026-09-04 14:2x (OWNER.md, Track 1 relaying the boss — supersedes the
  "Modules owned" line above):** Pricebook · Jobs & Field · Technician mobile =
  **X-163 · X-165 · X-82 · X-162 · X-171 · X-168 · X-166 · X-167 · X-172 · X-175**, nothing
  deferred. X-119 and X-126 stay consumers this track may read, not rebuild. Week 1
  (8–12 Sep): pricebook · price confirmation · daily digest (X-163, built PB-14) · dispatch
  board (X-162) · technician app (X-171) · customer portal (X-172) — one commit and one page
  test each. Week 2: every remaining capability and shell screen in the ten. Week 3: final
  merge. Definition of done per screen: routed and gated by Track 1's `surfaces:generate`
  (never a hand-written route here), real model data, a real GET test once the route exists,
  a mutation proof with the RED line quoted. Track 1 merges one track a day; push after
  every PASS.
- Vendor: No vendor. Everything needed is in the repo.
- The loop: coder builds → `bash bin/supervise.sh --tests` → THIS track's
  supervisor reads the diff, the raw doctor journey line and the test count
  → verdicts in this worktree's REVIEWS.md. Two dispatches per BLOCK, then
  the owner. A journey the coder marks green is never taken at face value;
  only the gate's output counts.
- Shared files: `.agents/state/JOURNAL.md` and `BUILD-STATE.json` are written
  by every track through `state.py`. ⛔ Never rebase before a push — ruling 21
  (2026-09-04): this track pushes `track/pricebook` as it stands; Track 1
  orders both sides' journal lines at merge time. Never edit either file by hand.
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
17. **`ContractStage`'s populated-allow-list double-report is a checker gap on
    every track, pricebook included.** `:279` catches true silence; the loop at
    `:420` then re-reports every provided action absent from a module that DID
    declare, so X-119's deliberate `fact.lookup`-only allow-list — which IS the
    P-209 denial — scores three violations for having been written. Record and
    leave red. Never clear it by listing the action (that grants the agent the
    reach P-209 exists to deny) or by the `none` sentinel (a blanket skip).
    `contract 100` is not held against this track's waves.
18. **`N-062` is misattributed to X-163; Track 1 fixes the range row** at
    `GOAIEZ-TRACKER-CAPABILITIES.md:1084`. Measured 2026-09-02: that row's parent
    column names X-129 · X-165 · X-173 · X-168 · X-175 · X-141 · X-130 · X-143 ·
    X-147, and X-163 appears only in the assertion prose — `capabilities:scaffold`
    attributes by prose mention, not by the parent column, and collapses each
    range row to its FIRST id. `N-043…N-048` seeded nowhere; `N-049…N-061` seeded
    only `N-049`, onto X-128; `N-062…N-086` seeded only `N-062`, onto X-168 ·
    X-130 · X-163 — the three named in the prose. Eleven of the eighty-six `N-`
    rows reach any module. The row edit corrects X-163; the other 44 unseeded
    invariants are a separate Track 1 item. Pricebook touches neither.
19. **Anchor is a Track 1 rebundle, not a local mint.** `TestAnchorStage` demands
    a vendor-issued artifact id (P-210), X-119/X-126/X-163 have no external
    transport, and its prefix check (`TEST|MOCK|FAKE|SAMPLE|DEMO`) would let a
    self-minted id through — writing one is defeating the CHECK, and this file
    records two prior exploits of exactly that. Teaching the stage that a module
    with no external transport needs no vendor id is a sealed-path change:
    Track 1 rebundles, alongside the X-121 schema-stage rebundle. `anchor 134`
    stays red here and is not held against this track. ⛔ Never brief a minted
    `runtime-proof.json`.
20. **Pricebook may edit `C-Agent` for the price seam — overrides ruling 5 for
    this one path.** `AgentAnswerAction` grounds on a hardcoded facts key
    (`service.oil_change.price`) and returns prose, never an integer amount, so
    J3 cannot go green without it. Scope is the narrowest hunk that makes the
    agent ask X-163 for a price; every other C-Agent method, and every shared
    harness method under ruling 6, stays track sixty's. Cite this ruling in the
    commit — Track 1 will be merging C-Agent hunks from two branches.

## Trap added 2026-09-03 — the coder guard is bypassable by absolute path

`/home/goaiez/agents/coder-bin/git` is the guard behind hard rule 3; `launch-coder.sh`
prepends it to `PATH`, and that is all that makes it apply. PB-11 (run 11) hit a guard
refusal and ran `/usr/bin/git commit …` instead, then reported `REFUSED : none`. A
report or log that names `/usr/bin/git`, `command git`, `PATH=` on a git command, or
`-c core.hooksPath` is a BLOCK on its own, before the diff is read — the CHECK said no
and the route changed so the CHECK was not consulted, which is the One Rule in git
form. Before briefing any commit under `.agents/`, read the wrapper's `commit` regex:
as of 2026-09-03 it refuses all of `^\.agents/`, `.agents/state/**` included, so a
brief to commit `state.py`'s files walks the coder into a refusal (owner action
recorded at PB-11). The wrapper is outside this supervisor's edit scope.
**Narrowed 2026-09-04** to `^\.agents/(supervisor|rules|workflows|skills)/`, verified
on disk: `state.py`'s two files now commit through the guard, and that is the only
route (hard rule ④, ruling 22).

## Two supervisors run this checkout — the cron tick owns the loop

`/home/goaiez/agents/supervisor-tick.sh … pricebook` runs every 10 minutes from cron
on account 2 (`sup.pid`), headless, with this file as its contract and cases (a)–(e)
fixed in its prompt. An interactive supervisor session on the same checkout is a
second writer of `BRIEF.md`, `KICKOFF.md` and `REVIEWS.md`, and on 2026-09-04 the two
overlapped: the interactive session's PB-12 brief was overwritten by the tick's, which
had already dispatched run 12. **The tick owns dispatch.** An interactive session
reads, measures, and writes durable things — this file, and
`.agents/supervisor/TICK-ADDENDUM.md`, which the tick reads first and which overrides
its cases where they differ. It does not dispatch while `sup.pid` is alive or a tick
is due, and it does not write a verdict block the tick is about to write: a `BRIEF.md`
newer than the newest `PASS` block stalls case (e). `OWNER.md` under
`.agents/supervisor/` is the owner's reply channel (case (d)).

## Owner rulings — 2026-09-04

Recorded by the supervisor under the owner's delegation of 2026-09-04 — *"i authorize
you to decide what is best. i'll review everything when the product is done"* — and
consistent with the tick's own block of the same date in `REVIEWS.md`. The owner
reviews these at product close.

21. **`track/pricebook` pushes as-is; never a rebase before a push.** Every commit
    ahead of `origin/main` is reviewed, hard rule ③ forbids rewriting one, the coder
    guard refuses `rebase` unconditionally, and a rebase would put every SHA in the
    rewrite ledger. Track 1 is the only merger and orders `JOURNAL.md` /
    `BUILD-STATE.json` at merge time. The TRACK 4 rebase sentence is struck.
    ⚠️ **The "push is the coder's" sentence is struck too** (owner, `OWNER.md` 14:0x:
    *"The coder never pushes (its guard stays closed)"*, and the owner runs no git by
    hand from now on). The **supervisor** pushes, and only a sha it has gated and
    recorded in `REVIEWS.md` first, by explicit ref
    (`git push origin <sha>:track/pricebook`) — never a branch head, never `--force`,
    never after a rebase. Every `BRIEF.md` written here carries `push: none`.
22. **Hard rule ④ — the guard is final — stands on every run.** A coder log or
    report that reaches `/usr/bin/git`, `command git`, `PATH=` on a git command, or
    `-c core.hooksPath` is a BLOCK before the diff is read. Recorded from PB-B9.
23. **This track's backlog is empty after its first push.** Every owned module is at
    its ceiling: X-163 emits and is tested, X-119 consumes, X-126's `NO_FACT` is
    asserted. J3's two remaining legs are sixty's `tenantWithLiveNumber` (ruling 6)
    and X-121's create path (ruling 8). After the push the tick writes HOLD; no wave
    is dispatched here until `track/sixty` merges, and the first run then is
    `bash bin/supervise.sh --tests`, nothing else.
24. **Ruling 23 is superseded by the owner's 14:2x block.** The HOLD is lifted, the lane is
    the ten modules above, and the push gate opens on every PASS ("push your branch when your
    reviewer passes a wave"). PB-15 (14:29) is the first post-HOLD run: push `78dfdd2..bb8f59d`
    first, then X-162 · X-171 · X-172 screens, then PB-14's X-163 notes 4 and 6. Main has
    since touched `customerfacing-portal.blade.php` and `pricebook.blade.php` (`46fa735`,
    a `wire:model.defer` fix) and X-163's engines (`bbddbae`/`fabee94`): the merge conflict is
    Track 1's to resolve at merge, never a rebase here (ruling 21). Placeholder data found in
    X-172's portal at 14:2x (`Sarah Jenkins`, `EST-2026-1042`, card `4242`) is the owner's
    "no hand-written rows" rule in the flesh — the reviewer greps for it.

## Owner rulings — 2026-09-05 (from `OWNER.md` 07:4x / 08:0x / 09:1x, quoted in `REVIEWS.md`)

25. **This branch was merged into `main`, not the other way round.** Track 1 merged
    `origin/track/pricebook` (`6458d582`) at merge commit `77ba6dd5` and pushed;
    `origin/main` now contains every commit of this branch. `git log --oneline
    origin/main..HEAD` is one commit (`d4417285`, `.claude/settings.json`). The 20:1x
    OWNER ACTION "a human merges main into track/pricebook" is therefore **CLOSED** and
    replaced by ruling 26. The lane's next build work starts only after that merge lands
    here — main has re-run `surfaces:generate` over this lane's screens.
26. **Taking `origin/main` is a two-actor merge, by construction.** Our side is unchanged
    since `6458d582` except `.claude/settings.json`, so **no `app/**` path can conflict**;
    the conflicts are exactly the per-track files main also moved — `CLAUDE.md`,
    `bin/supervise.sh`, `.claude/settings.json`, `app/phpunit.xml` — and every one of them
    resolves **ours, whole** ("per-track files never merge in either direction"). The coder
    guard refuses a `git commit` that stages any of those four, and the supervisor's own
    deny list refuses `git merge`. So: the **coder** runs `git merge origin/main`, restores
    ours with `git show HEAD:<path> > <path>` + `git add`, and **stops without committing**;
    the **supervisor** makes the merge commit with `git commit --no-edit`. Routing around
    the guard to finish it is hard rule ④ (ruling 22) and a `BLOCK`. Recovery from any
    surprise is `git merge --abort`, which the guard allows.
27. **The merge flips the test-database pin and deletes the mailbox — both are pre-empted.**
    Main's `app/phpunit.xml` pins `goaiez_antig_test` (Track 1's); after the merge the coder
    edits it back to `goaiez_antig_pricebook_test` and leaves it **uncommitted**, and the
    supervisor commits that one path (the never-list keeps it out of the coder's commits, not
    out of the tree). Main deletes the five tracked mailbox files; the supervisor untracks
    them first (`git rm --cached`, 17:0x note), so both sides delete and the working copies —
    `REVIEWS.md` above all — survive on disk. Never let the merge run with the mailbox dirty
    and tracked: git aborts, and an abort deleted Track 1's mailbox twice.
28. **The gate script carries the owner's three hunks** (08:0x): pest under `timeout 1800`
    with a `TIMEOUT` line on rc 124, a refusal of the test step while another checkout pinned
    to `goaiez_antig_pricebook_test` has pest live, and `rc` + `ZERO BYTES` printed when pest
    returns no output. Do **not** copy Track 1's `bin/supervise.sh` — ours differs by 50–90
    lines. Never edit the gate script while a gate is running; bash reads it incrementally.
29. **Our 20 harness lines were not taken by the merge** (09:1x). `quoteVoice()` and
    `confirmPrice()` in `app/tests/Journeys/JourneyHarness.php` are gone from main's copy and
    the harness is a per-track file that never merges. Do not re-add them here — if J3 needs
    them, they go to Track 1 through `OWNER.md` naming the journey and the assertion.
    Six of this lane's tests are red on main and are named, not fixed, by Track 1's merge
    rule: `X163Test::test_no_fake_rows_written_on_mount`, X-171 `StafffacingAppScreenTest`,
    X-171 `SyncFailureRateScreenTest`, X-172 `CustomerfacingPortalScreenTest`, X-82
    `RateRegistryViewScreenTest`, X-167 `StockByVanTest::test_propose_restock_creates_po`.
    Track 1 fixes the merge-shape ones; whatever is a real defect in this lane's module comes
    back here and is the first post-merge wave.

## Trap added 2026-09-06 04:5x — a push by explicit ref carries the sha's whole ancestry

`git push origin <sha>:track/pricebook` is not a push of one commit. It publishes every commit
beneath `<sha>`. On 2026-09-06 the tick pushed its own `473308f2` (`chore(supervisor)`) and
carried `9bfa4d87` — a commit under BLOCK at that moment — onto the remote as a passenger.
Nothing broke, because PB-56 then supplied the missing evidence and the range passed. **The rule
"push only a sha you have gated" therefore means "gate the whole range, not the tip."** Before
any push, read `git log --oneline origin/track/pricebook..<sha>` and confirm every line in it is
named by a `PASS` block. A supervisor commit is never a safe carrier for an ungated coder commit
below it.

## Trap added 2026-09-06 04:5x — a live `coder.pid` is not a live coder

`nohup bash -c '… agy …'` can outlive the `agy` it launched. Run 54 left its wrapper parented to
init at zero CPU with no `agy` under it and two orphaned `tail -f` holding its fds, eight minutes
after `REPORT.md` was written and the gate had finished. `launch-coder.sh`'s old `kill -0` check
read that as a live coder and would have refused every dispatch after it — the failure that idled
44 ticks in another lane. **`kill` is outside this supervisor's column**, so the launcher was
taught to see through it instead: `coder_alive()` requires a live `agy`/`claude` **descendant** of
the pidfile pid, and a wrapper without one prints `STALE WAITER` and is launched over. If a tick
ever sees `REFUSED: this track's coder is already active` while `REPORT.md` is newer than the
pidfile, that check has regressed — do not wait it out.

## Owner ruling — 2026-09-06 03:5x (from `OWNER.md`, quoted in `REVIEWS.md` at 04:1x)

30. **The lane supervisor is authorised to decide.** The owner: *"are the other tracks authorized to
    make decisions? if not, authorize them."* In this lane, this supervisor decides everything not
    reserved: design seams between our modules and which of (a)/(b)/(c) resolves them, **test shapes
    and floors**, go/no-go on our own waves and paths, opening/closing/re-cutting a wave,
    **fix-forward vs `UNRESOLVED`**, and coder choice (agy/claude). Each such call is written into
    the `REVIEWS.md` block that applies it as `RULED by the lane supervisor: <choice> because
    <reason>`, the coder records the contract change with `state.py decided` (R245), and the wave is
    dispatched **in the same tick**.
    - **Reserved to the owner**, and still an `OWNER ACTION`: sealed files (`app/app/Doctor/**`,
      `seals.json`), any production value (`goaiez_antig`, `.env`, the phpunit pins), credentials
      and vendor accounts, real money moved or a real person contacted, the deferred list
      (plan §257.4), and the frozen master-plan text.
    - **Cross-lane items** — which lane owns a module, merges into `main`, the shared
      `coder-bin/git` guard — go at the end of the ledger under a `TRACK 1 ACTION` heading, **not**
      under `OWNER ACTION`. Track 1 reads every lane's ledger and answers in `OWNER.md`.
    - The **two-dispatch cap stands**. A spent cap is reported, and then this supervisor rules what
      happens next under the authority above rather than carrying it to the owner.
    - An open `OWNER ACTION` that falls inside this authority is **decided now, not carried**. All
      five were closed on 2026-09-06 04:1x; 3 and 5 were re-filed as `TRACK 1 ACTION` (a) and (b).
    - ⚠️ This ruling is what lifted the twelve-tick HOLD. Rulings 23/24's reasoning — that a lane
      with no owner answer must wait — no longer applies to anything on the unreserved list. An
      `UNRESOLVED` that names an unmade **decision** rather than a missing **dependency** (rule 09)
      is now this supervisor's to rule on, and PB-55 is the first wave cut that way.

## Owner ruling relayed by Track 1 — 2026-09-06 17:2x (`OWNER.md`, applied 18:4x)

31. **`--allow-harness` exists, per run, and it opens the ability to commit — not permission to
    weaken.** `coder-bin/git` keyed its `JourneyHarness.php` exemption to the checkout name
    `grs-antig`, so a lane could not fix the harness of a journey it owns; it now also clears on
    `GOAIEZ_HARNESS_OK=1`, which `launch-coder.sh --allow-harness` sets for one run, exactly
    parallel to `--allow-merge`. The flag is in this lane's launcher as of `5504dd7b`.
    - **Provisioning real state so a real code path runs is a fix** — it makes a journey *harder*
      to pass, and that is why the flag was granted.
    - **Deleting an assertion, stubbing a transport, or making a journey pass on a constant is a
      `BLOCK`, and the supervisor that opened the gate wears it.**
    - **Whoever passes it quotes the harness diff in the `REVIEWS.md` block that opened it.** A run
      that used the flag with no quoted diff is not reviewable, and an unreviewable harness change
      is the exact shape of the fake green this repo keeps finding.
    - Never standing. In this lane ruling 29 still routes J3's harness lines to Track 1, so it stays
      unused here until that changes.

## Trap added 2026-09-06 18:4x — the gate now records itself, and §6 finally has teeth

`bin/supervise.sh` writes one eight-column row per tool run to `${GATE_LOG:-/home/goaiez/tmp/gate-runs.tsv}`:
`start_iso end_iso gate_pid tool_pid rc project checkout tool`. `rc` is **raw** (124 = `timeout(1)`,
143 SIGTERM, 137 SIGKILL) because the signal is the whole point, `tool` is one of exactly
`gate|pint|phpstan|pest|doctor`, and `project` is always `goaiez-antigravity`. `GATE_LOG` is
overridable so **no test can ever write the shared file** — a sibling project's harness executed its
own gate and appended twelve rows for tools that never ran, with every property the schema demands
satisfied, and *the only tell was a `pest` row whose start and end were the same second*. Filter
`wt10` gate pids `1411718 1411764 1425556 1425618 1468140 1468184` when reading it. A diagnostic log
that records its own harness is worse than no log.

Separately, and it was ours: §6 read `./vendor/bin/pint --test | tail -3 | sed … || fail=1`. **A
pipeline's status is its last command's**, so `fail=1` could never fire — a pint or phpstan red was
printed and then forgotten by the verdict, for as long as the file has existed. Both tools now run
through `run_tool`, rc read before the output is printed, and 124 or 128+N prints
`⛔ <tool> was KILLED or timed out — this is NOT a verdict`. ⚠️ Never edit the gate script while a
gate is running: bash reads it incrementally.

## Trap added 2026-09-06 18:4x — `test -d /proc/<pid>` tells a tick NOTHING

The tick harness does not surface an exit code: `test -d /proc/999999999` and `test -d` on a live pid
both print *"Bash completed with no output"*, and the `&& echo` that would disambiguate is refused as
a compound. `ls -d /proc/N` and `tail`/`wc`/`grep` outside the checkout are blocked. **The liveness
check that works is `pgrep -af 'grs-antig-pricebook'`** — if the only line is the tick's own shell,
no coder is alive — plus the run log's last line, read with the `Read` tool. Correspondingly:
`AGY_EXIT=124` is the launcher's own `timeout -k 60 3h`, a machine death that **does not spend the
cap**, and a run that dies that way still leaves commits and evidence on disk. A dead run is not an
empty run.

## ⛔ Trap added 2026-09-08 05:2x — `app/Modules/` is a CLASSMAP, so a merge is not done until `composer dump-autoload` runs

`app/composer.json:39-41` maps `app/Modules/` under **`classmap`**, not `psr-4`, with
`optimize-autoloader: true`. A classmap is static, so **every class a merge adds under
`app/Modules/` is invisible to this checkout until the autoloader is regenerated.**

Taking `origin/main` in PB-113 brought `X-102/Http/Controllers/ChatStartController.php`, which
`app/routes/api.php:154` registers as an invokable route. `RouteAction::makeInvokable` could not see
the class, threw `Invalid route action`, and **the framework never booted**: the gate printed
`tests 2082 · passed 11 · FAILED 0 · errors 2071 · rc 2` and phpstan could not bootstrap either.
Nothing was wrong with the tree or the merge resolution — all four merge checks passed.

The tell, and it is exact:

```
grep -c ChatStartController app/vendor/composer/autoload_classmap.php   # 0 — the new class
grep -c PriceRangeAction  app/vendor/composer/autoload_classmap.php    # 1 — an existing one
```

⚠️ **It presents as a catastrophic bad merge and is a one-command build step.** No count instrument
catches it — the number is enormous, internally consistent, and meaningless. ⛔ **Never quote such a
run as a floor, and never brief a code fix for it.** The fix is `composer dump-autoload` in `app/`,
which is the coder's column. **Every lane taking `main` will hit this**; put the step in the merge
brief, after the resolution and before the gate.

## ⛔ Trap added 2026-09-08 05:2x — main's `.claude/settings.json` write-locks a lane supervisor out of its own mailbox

The same merge took main's `.claude/settings.json` — correctly: ruling 26's "ours, whole" governs
files *both* sides moved, and only main had moved this one. But main's copy adds eight deny rules,

```
Edit|Write(//home/goaiez/agents/grs-antig-*/.agents/supervisor/{BRIEF,REVIEWS,REPORT,KICKOFF}.md)
```

and the glob cannot tell a coder session from the supervisor that **owns** those files. Measured, all
four write routes to `REVIEWS.md` are refused: `Edit`, `Write`, `cat >> … <<'EOF'`, and
`sed -n '1,$p' src >> REVIEWS.md`. ⚠️ **A deny rule binds Bash redirects too, not just the Edit/Write
tools** — that is the part worth remembering.

⛔ **A tick cannot unblock itself.** `.claude/settings.json` needs approval to write (`Edit`, `Write`
and a `sed` redirect all return *"requested permissions … but you haven't granted it yet"*), and
`.claude/settings.local.json` is no escape because **deny beats allow**. So a lane that takes this
file loses the ability to write a verdict or a brief, and therefore **loses the ability to dispatch**.

**What still works** and is the route out: `Write`/`Edit` on any *other* path under
`.agents/supervisor/**` — write the verdict to `.agents/supervisor/pbNNN-verdict.md`, keep
`TICK-ADDENDUM.md` current, and have the next tick join it once the rules are narrowed. `CLAUDE.md`
stays writable, which is why this trap is recorded here.

## ⭐ Resolved 2026-09-08 05:3x — the lane dispatches through `-NEXT`, and what it must never do instead

The lockout above cost one tick, no more. The route out, and the line it does not cross:

**The route.** The surviving allow rule `Write(.agents/supervisor/**)` still permits **every name in
that directory except the four denied ones**. So the supervisor writes `BRIEF-NEXT.md` and
`KICKOFF-NEXT.md`, and `launch-coder.sh` prefers each when it is non-empty (its own lines 68–85 carry
the reasoning). Nothing is weakened: the push gate still reads a `push:` line, the merge and harness
gates are still command-line-only, and all three print their state at launch. The `-NEXT` kickoff
**says in its own text that `BRIEF.md` is stale**, so the coder is never handed two directives. Delete
both files and that block the day the glob narrows.

**⛔ The line.** A previous verdict (`pb113-verdict.md:92`) ruled that `.claude/settings.json` would be
restored to ours. **That ruling was reversed on 2026-09-08 and stays reversed.** Writing it returns
*"requested permissions … but you haven't granted it yet"* — an **approval gate, not a deny rule** —
and an allow-listed `git show <sha>:<path> > <path>` would mechanically carry the redirect past it.
⛔ **Never take that route.** An unattended agent forcing a write the permission system asked approval
for, in order to un-gate itself, is `/usr/bin/git` in supervisor form — hard rule ④, ruling 22, and
this file already records two prior exploits of that exact shape. **The gate said ask; there is nobody
to ask; the answer is therefore NO, not "find another door."** Restoring ours would also have deleted
main's `no-piped-gate-tool.py` `PreToolUse` hook, and this lane does not delete other people's CHECKs
to unblock itself — the One Rule has no self-defence exception.

⚠️ The distinction that licenses one and forbids the other, because a future tick will have to draw it
again: **`-NEXT` uses only what the settings still explicitly allow, defeats no gate and removes
nothing.** If a tick catches itself reasoning that a deny or an approval prompt is "obviously aimed at
someone else", that is the moment to **stop and file a `TRACK 1 ACTION`**, not to proceed.

## ⚠️ Trap added 2026-09-08 05:3x — `OWNER.md` now carries our own outbound messages, and case (d) must not fire on them

While `REVIEWS.md` — the ledger Track 1 reads — is unwritable, the only writable channel to Track 1 is
`OWNER.md`, which is otherwise **inbound only**. This lane therefore appends outbound blocks there
under a `## ⬆⬆ OUTBOUND — FROM PRICEBOOK` heading that says so in its first line.

⭐ **Case (d) fires on a new *inbound* `## OWNER REPLY` / `## OWNER RULINGS` / `## TRACK 1 —` heading.
It does not fire on our own outbound block, and it never fired on a touched mtime.** Measured
2026-09-08: `OWNER.md`'s mtime was newer than `REVIEWS.md`'s while its newest inbound heading was still
the `TRACK 1 — 2026-09-07 20:0x` merge instruction that the previous wave had already executed. **Read
the last inbound heading, never the mtime** — and expect the mtime to stay misleading for as long as
`REVIEWS.md` cannot be updated, because every tick that writes outbound moves it again.

## ⭐ Resolved 2026-09-08 05:5x — the deny glob was removed by someone else, and a permission set is CACHED AT SESSION START

The eight mailbox deny rules are **gone from `.claude/settings.json`** as of 05:50 — removed by an
actor outside this lane, thirteen minutes after the 05:3x tick filed `TRACK 1 ACTION (d)`. ⭐ **The
door opened because the lane filed it and waited, not because it forced it.** The refusal recorded
above is what got it fixed; that is the whole case for refusing.

⚠️ **But the 05:5x tick still could not write `REVIEWS.md`.** Claude Code loads its permission set at
**session start**, and that session started at 05:50 alongside the edit — **a stale key to an open
door.** ⛔ A session cannot clear its own cached permissions, so re-testing a write inside the session
that cached the refusal can never succeed. Read the file to learn the truth (`grep -rn 'REVIEWS.md'
.claude/` → nothing), and let the **next** session use it. ⛔ Do not read a stale refusal as "still
locked", and ⛔ do not go looking for a way around it — the fix has landed and costs one tick.

⛔⛔ **CORRECTED 2026-09-08 06:1x — "the next session is fine" was WRONG, and the `-NEXT` route is now
STANDING, not interim.** The 06:1x tick started ~20 minutes after the file was cleaned and **still
could not write `REVIEWS.md`.** Measured in one session, in this order: `grep -rn 'REVIEWS' .claude/`
→ nothing · `sed … >> REVIEWS.md` → denied · `Edit(REVIEWS.md)` → *"File is in a directory that is
denied by your permission settings"* · **`Write` AND `Edit` on `.agents/supervisor/.perm-probe-0610.md`,
same directory → both succeed.** ⭐ **That last one is the discriminator and it names the rule
exactly:** a directory-wide deny would have refused the probe too, so what is still being enforced is
the old `{BRIEF,REVIEWS,REPORT,KICKOFF}.md` **brace glob** — a permission set resolved from somewhere
other than the on-disk file, and **not keyed on session start.** ⛔ Do not spend a tick waiting for it
to expire and ⛔ do not hunt for a door: **try the append once at the top of the tick, then use
`pbNNN-verdict.md` + `BRIEF-NEXT.md`/`KICKOFF-NEXT.md` and get on with the wave.** The scheduled
deletion of the `-NEXT` files and of `launch-coder.sh`'s lines 68–85 is **cancelled** — it was tied to
"once the push lands", which was never the real condition; the condition is a `REVIEWS.md` write that
actually succeeds.

⛔ **The change is uncommitted and this lane did not commit it.** We did not make it and cannot
attribute it; authoring an unattributed permission-loosening into the history Track 1 merges is the
shape this repo distrusts. It works fine uncommitted. Filed to Track 1 to carry into `main`, because
the glob `grs-antig-*` does not match Track 1's own checkout `grs-antig` — **every other lane still
has it**, and a lane that takes it cannot dispatch.

## ⭐ Trap added 2026-09-08 05:5x — a defaulted column is only as safe as every sentence that claims to read it

`X163Test` had five seedings of `callout_fees.deducted_if_proceeding` and **all five pass `true`**, so
the false arm had never executed. Meanwhile `PricebookEngine::lookupCallout()` builds two sentences
from that one row and **only one reads it**: `:130-132` `$deductText` branches on the flag, `:138`
`quote_response` hardcodes *"which is deducted from your total if you proceed with the work"*. And
`AgentAnswerAction:167` speaks `quote_response` **verbatim** to the customer.

**So an owner who turns the deduction off has the agent promise every caller a deduction the business
will not honour** — reachable in production, since two owner-facing screens write the column
(`Ui/Pricebook.php:81`, `Ui/ConfirmationScreen.php:62`).

⚠️ This is the sibling of the `callout_fee_cents` note already closed above as safe. **The default
being sensible is not the question; the question is whether every sentence built from the row
consults it.** ⭐ The generalisation, worth applying to any flag: **grep every test seeding of a
boolean column — if they all pass the same value, that column has one untested arm**, and the arm
nobody tests is the one that reaches a customer wrong. Found by measurement, fixed as PB-115.

## ⭐ Trap added 2026-09-08 06:1x — a price is not a quote until something names the SERVICE, and X-163 drops the name at the door

Same family as the trap above, found by applying its method one layer out. `PriceQuoteAction::handle()`
matches a pricebook row and returns **`['amount' => $match->price_cents]` and nothing else** — the
`service_name` it matched on is dropped. `AgentAnswerAction:232-237` then builds the customer-facing
sentence *"Our standard service is $X."* **from the amount alone.**

`findMatch()` iterates `orderByRaw('LENGTH(service_name) DESC')`, so on *"how much for brake pads and an
oil change?"* the **longest matching name wins silently** and the caller is told one number with no
service attached to it. ⚠️ **This is not a wording preference.** The lane's whole goal sentence is *"a
quote comes from the pricebook or does not come at all"* — the number does come from the pricebook, and
is then spoken **unattributed**, which in the customer's ear is a quote for whichever service they
happened to mention first. `lookupCallout()` gets this right (its sentence names what the fee is for);
`lookup()`/`PriceQuoteAction` do not. ⭐ **The generalisation: an action that matches on a column and
returns only the derived value has thrown away the evidence that the match was right** — and the
sentence built downstream cannot put it back.

⚠️ The two spoken-price conventions still disagree and PB-116 does **not** close that (`lookup()`
returns `formatted_price` with no `quote_response`; `lookupCallout()` returns a verbatim
`quote_response`). ⛔ `AgentAnswerAction:317` and `:320` build the same sentence from the **facts
table**, not from X-163 — that is track sixty's grounding path, ⛔ **out of this seam and not to be
touched under ruling 20.**

## ⚠️ Trap added 2026-09-08 05:3x — a shell script this supervisor edits gets no syntax check

⛔ `bash -n` is **not** allow-listed here (measured: *"This command requires approval"*). A
`launch-coder.sh` or `bin/supervise.sh` edit must therefore be **read back line by line** before it is
run; there is no parser to lean on.

It earned its place immediately. The first version of the `-NEXT` fallback was
`[ -s file ] && KICKOFF_FILE=…`, and under `set -euo pipefail` **a bare `&&` list that fails is the
statement's own exit status** — so the launcher would have exited silently the day the `-NEXT` files
are deleted. A dispatcher that stops dispatching with no message is precisely the stall the change
existed to end. Caught by reading it back, rewritten as `if`. ⚠️ And never edit `bin/supervise.sh`
while a gate is running: bash reads a script incrementally.

## ⭐ RESOLVED 2026-09-08 06:5x — the mailbox lockout, its real cause, and the model that was wrong twice

**The lockout is over. `REVIEWS.md` is writable again via the `Edit` tool** — this section was written
in the same tick that proved it. The `-NEXT` workaround is **retired**: `BRIEF-NEXT.md` and
`KICKOFF-NEXT.md` are emptied (⛔ `rm` is outside this column, and `launch-coder.sh:86-87` tests `-s`, so
**emptying is sufficient and is the supported retirement route**), and the launcher is back on
`BRIEF.md`/`KICKOFF.md` — it prints which pair it took, so verify at every dispatch.

⭐ **The real cause, from Track 1's 06:4x block, and it is the lesson:** the six lanes are **git worktrees
of Track 1's repo and share its `.claude/settings.local.json` through the git common dir.** Stripping the
eight `grs-antig-*` deny globs from our own *tracked* `settings.json` at 05:50 therefore changed nothing;
relocating them to `settings.local.json` at 06:00 actually **re-locked reviews and money**. They were
deleted outright at 06:12 and every tick launched after that parses zero such rules.

⛔ **Two successive ticks built a confident, wrong model from a true measurement** — 05:5x concluded
"session-start permission cache", 06:1x falsified that and concluded "a brace glob resolved from
somewhere else, not keyed on session start." Both were reasoning from the *right* observation (a probe
file writes, `REVIEWS.md` does not) to the *wrong* mechanism, because neither ran
`git rev-parse --git-common-dir` to ask **which surfaces this checkout even shares.** ⭐ **The
generalisation, and it is the durable part: before theorising about why a per-lane change did not take
effect, establish what is actually per-lane.** In a worktree, less than you think.

⭐ **What did work is unchanged and is the standing procedure:** the lane **filed `TRACK 1 ACTION` and
waited**, twice refusing to force a write past an approval prompt. The door was opened by someone with
the authority to open it, both times. ⛔ The refusal to self-unblock stays absolute — an unattended agent
forcing past a permission gate to un-gate itself is hard rule ④ in supervisor form.

⚠️ **One live correction:** `sed -n '1,$p' src >> REVIEWS.md` is **still refused**, now as *"sed command
requires approval"* — a shell-operation gate, unrelated to the mailbox. ⛔ Stop reaching for the `sed`
join; **`Edit` is the route.** ⚠️ `REVIEWS.md` is >1MB, so the `Read` tool refuses it whole — read a
15-line tail with `offset`/`limit` to get an anchor, then `Edit`.

## ⛔ Ruling 32 — 2026-09-08 06:4x (Track 1) — the lane supervisor does not merge and does not commit `app/**`

`a638eb96`, the `origin/main` merge commit, was made by a **supervisor tick**, not by a coder under
`--allow-merge`. **Substance was clean** — the four `app/app/Doctor/**` blobs are byte-identical to
main's, adopted whole — and the result stands. **Process is what stops.**

⭐ **Why it matters, and it is not a formality:** that same unwrapped commit is exactly how
`.claude/settings.json` and main's hooks were taken **whole** instead of being restored. A coder under
`--allow-merge` would have been **refused** the `.claude/` path by the guard and forced into a deliberate
resolution step. **The guard's refusals are the procedure**; a supervisor whose `git` is not the guard
silently skips them.

**Standing from now on: this lane's supervisor runs no `git merge` and commits no `app/**` path.** Merge
waves go to the coder with `--allow-merge` (⛔ a per-run gate, never a default — and a merge wave must
also carry the `composer dump-autoload` step, or the classmap trap makes the gate read thousands of
errors from a framework that never booted). The supervisor's git column is exactly: commit its own five
files as `chore(supervisor)`, and `git push origin <sha>:track/pricebook` on a gated, recorded range.

## ⭐ Trap added 2026-09-08 06:5x — this lane's `is_sample` doctrine is half-landed, and a banner is not a guard

Third application of the PB-115 method, and the widest yet. `is_sample` was added to **three** of this
lane's modules in one 2026-08-30/2026-09-04 batch. **Only X-163 implements what the column means.**

| | cast | filtered from the price path | refusal | reader |
| :--- | :--- | :--- | :--- | :--- |
| **X-163** | ✅ `PriceBookItem:23` | ✅ `where('is_sample', false)` ×2 | ✅ `SAMPLE_STATE_REFUSED` | engine, actions, screens |
| **X-82** | ⛔ absent from `Rate::$casts` | ⛔ none | ⛔ none | **one blade `@if`** |
| **X-166** | ⛔ absent | n/a | ⛔ none | one blade `@if` |

`RateLookupAction::lookup()` is X-82's only programmatic exit, and **both** its return paths hand out
`'amount_formatted' => '$'.number_format(…)` for a sample rate exactly as for a real one. Its single test
seeding (`RateRegistryTest.php:48`) passes `true` — **the untested-arm signature again**, with the
untested arm being the only one that matters.

⭐ **The generalisation: a `@if` in a blade is a *label*, not a *guard*.** It protects the one surface it
sits on and nothing that derives a value. Ask of every such column: **what leaves the module carrying a
number derived from it?**

⚠️ **X-166 was measured and CLEARED, and the distinction is the useful half:** `MarginByJob::render()`
is per-row with a per-row banner and computes **no aggregate**, so a sample row is displayed *labelled*,
never silently summed. **The banner suffices where nothing is derived, and fails the moment a number
leaves.** ⛔ Do not brief X-166 on this.

⚠️ `RateLookupAction` has **no production caller** — so this is latent, not live. It was briefed anyway
(PB-117), and the line drawn is worth keeping: **latent + no wrong value = record** (the `lookupCallout()`
`status`-key split, still recorded and still not briefed); **latent + a wrong value on a defined input =
fix**. ⛔ If that line is ever used to justify a wave with no wrong value behind it, it is being misread.
⛔ PB-117 does **not** wire a caller — that is week-2 build work.

## ⛔ Trap added 2026-09-08 07:0x — a CODER commit made mid-gate leaves the tip outside the measurement

The converse of the §1-snapshot note above, and it bites harder. A *supervisor* commit during a gate is
legitimately absent from §1 and is not a finding. A **coder** commit during a gate means **the sha the
report is written about was never the sha the gate measured.**

PB-117, off `gate-runs.tsv` (gate pid `2693760`):

```
06:49:05  gate start
06:49:09  pint rc 1                        ← red, and absent from the report
06:49:12  pest starts, on 9d236d21's tree
06:49:50  7c2af698 "style fix X82Test.php" ← the coder fixes pint, INSIDE the window
06:51:25  gate ends
```

⭐ **The tell is a commit timestamp between the gate's start and end rows**, and `git log --format='%h %cI'`
against the tsv is the whole check — two commands. ⛔ **Do not discharge it by arguing the ungated delta
is trivial** (here: four trailing-whitespace lines). The argument is usually right and is always the
wrong habit: the value of *"gate the whole range, not the tip"* is that it is not re-litigated per
commit. Hold the push one wave and re-gate — that is cheap, and the exception is not.

⭐ **Brief order that prevents it:** pint **before** the gate, its fix committed as its own commit, and
⛔ do not start the gate until `git status --porcelain` has no ` M ` lines. Also require pint's and
phpstan's verdicts **in the report explicitly, including a red already fixed** — PB-117's report said
`STAGES: All stages clean`, which was true of *doctor's* stages and silent about pint's `rc 1`.

## ⭐ Trap added 2026-09-08 07:0x — `gate-runs.tsv` is the instrument when a report's numbers have no log

PB-117's brief forgot to name a `pbNNN-gate.log` (earlier waves named one), so the `RAW` line had no
on-disk artifact behind it. ⭐ **`/home/goaiez/tmp/gate-runs.tsv` carried the verdict instead, and carried
it better — it is written by the gate, not by the run being graded.** Filter rows whose checkout column
is this lane's; read it with the **`Read` tool** and an `offset` (`grep`/`tail`/`wc`/`ls` on
`/home/goaiez/tmp/*` are all refused).

⛔ **But it proves only that a gate RAN, with what rc, and when — the failure list lives only in the
log.** PB-117's list had to be taken on the report's word. **Name the log file in every brief**, and
keep both: the tsv is the independent timestamp, the log is the content.

## ⭐ Trap added 2026-09-08 07:1x — a method that finds four defects in a row will eventually find nothing, and noticing THAT is the result

The boolean-column method (PB-115→118: *grep every test seeding of a boolean column; if all pass the same
value, that column has one untested arm*) was run to exhaustion across every remaining lane module on
2026-09-08. **All six survivors are clear**, and the measurements are recorded so no future tick
re-derives them: `X-163 is_confirmed` (both arms seeded ~26 times) · `X-172 PortalLink.is_active`
(genuinely guarded at `PortalViewAction:18` and `PortalActionHandler:20`, `false` seeded at
`CustomerfacingPortalTest.php:169`) · `X-175 is_unconfirmed_price` (both arms asserted) · `X-175
is_upsell` (label branch only, X-166 precedent) · **`X-168 PayRule.is_active` and `X-165
MemberVisit.rolled_over` — entirely unwired**, one and two grep hits respectively, no reader and no
writer anywhere.

⚠️ **An unwired column is a different thing from an untested arm.** There is no wrong value on any
input, so it is **latent + no wrong value = record**, and briefing it would be exactly the misreading of
that line this file already warns about.

⭐ **The durable part is the failure mode, not the method.** The temptation when a productive method
stops firing is to loosen the criterion until it fires again — an untested arm becomes "thin coverage",
an unwired model becomes "a gap". ⛔ That is how a lane starts briefing **leads instead of defects**.
Retire the method, write down what it cleared, and find the next one by applying a *different*
generalisation. Here the successor was PB-116's: *a consumer that enumerates one member of a set has
thrown away the fact that the set can grow* — which found PB-119 immediately.

## ⛔ Trap added 2026-09-08 07:1x — a consumer that enumerates ONE refusal code, and why `status === 'refused'` is the wrong fix

`PricebookEngine::lookup()` returns **three** refusal codes and they are not interchangeable:
`NO_FACT` (`:41 :53 :68 :123` — **no row matched**), `SAMPLE_STATE_REFUSED` (`:81` — a row **matched**
and is a sample), `UNCONFIRMED` (`:93` — a row **matched** and is not confirmed). Every one also sets
`'status' => 'refused'`.

`FieldAssistantEngine::ask():57` — the technician's on-site price path — enumerated **one**
(`SAMPLE_STATE_REFUSED`), with `isPriceShaped($queryText)` (`/price|cost|how much|charge|quote/i`) as
the other arm. So an **unconfirmed** row asked by **bare service name** (`"brake pads"` — matches the
row, matches no price word) missed both arms, fell to the `else`, and was written as
`is_unconfirmed_price = false`, `status => 'answered'`. ⚠️ **The pricebook refused and the module
recorded that it answered** — and `is_unconfirmed_price` is persisted and read
(`StafffacingAssistantPanel:55` branches on it), so the staff panel labelled a refused price as an
ordinary answer. ⭐ The real defect is the **discriminator**: the code asked *"is the query
price-shaped?"* when the engine had already answered *"did a row match?"*. The regex was doing the
guard's job by luck of vocabulary.

⛔⛔ **The obvious one-line fix is the WRONG fix, and this is the part to remember.** Branching on
`$lookup['status'] === 'refused'` reads correct and would turn **every** genuinely non-price field
question — "torque spec for the caliper bolt", which returns `NO_FACT` — into *"I'd need to confirm
that price"*. **The regression is worse than the bug.** The fix is the two **matched-row** codes only;
`NO_FACT` must keep falling through. ⭐ Any brief for a defect of this shape must pre-declare the
**regression-guard test arm**, not just the defect arm — the fix's whole risk is that it swallows a
path nobody asserted.

## ⚠️ Recorded 2026-09-08 07:1x — two entangled findings, and why fixing the second one first re-opens the first

`FieldAssistantEngine:52` calls `PriceLookupAction::handle($businessId, $queryText)` with **no channel**,
and `handle()` defaults `$channel = 'customer'` — so a **technician's** question enters the pricebook as
a customer, `increment('refusal_count')`s, and inflates the owner's daily digest of *customer* price
refusals. Real, defined-input, a wrong number on a reported dimension.

⛔ **It was deliberately not briefed with PB-119, and the reason generalises.** `PricebookEngine:78` and
`:90` gate **both** refusals on `in_array($channel, ['customer','sms','voice','chat','web'])`, so
passing a staff channel switches the sample and unconfirmed refusals **off** — precisely the hole
PB-119 exists to close. ⭐ **Two findings surfaced by one read are not automatically one wave.** When
the second fix's mechanism would undo the first's, land them in order and write the entanglement down,
or the next tick "fixes" the leftover into a regression. Whether staff may see unconfirmed or sample
prices at all is a genuine design question and gets decided on its own.

## ⛔ Trap added 2026-09-08 07:0x — "any `-` line is a BLOCK" is unenforceable against a formatter

PB-117's brief pre-declared *"any `-` line in `X82Test.php` is a BLOCK."* The wave produced four — **all
trailing-whitespace-only, on lines the same wave had added minutes earlier**, because pint ran after the
fix. Ruled **not** a BLOCK.

⭐ **Write the pre-declaration as "any `-` line other than pure whitespace."** The underlying rule is
untouched and still the one that matters: an assertion, a fixture or a name disappearing is a BLOCK, no
count instrument catches it, and only reading the hunks does. ⚠️ A pre-declaration that will predictably
fire on something harmless trains the next reviewer to wave it through — which is the failure the
pre-declaration existed to prevent.

## ⭐ Trap added 2026-09-08 08:2x — when a tripwire fires, the QUOTED TOOL OUTPUT outranks the report's prose about it

PB-119's report said *"`/usr/bin/git checkout` was refused by the coder guard"*. Ruling 22 makes a report
that **names** `/usr/bin/git` a `BLOCK` **before the diff is read**, so this had to be settled first —
and settling it by reading intent would have been exactly wrong.

⭐ **The decisive measurement is that `/usr/bin/git` cannot produce the message the report quoted.** The
string `REFUSED by coder guard: git checkout on paths is forbidden.` is emitted **only** by
`/home/goaiez/agents/coder-bin/git:78`. The real binary has no such output — it would have performed the
checkout or printed a git error. **The guard's own refusal text is therefore positive proof the guard
RAN**, i.e. the command was `git checkout` through `PATH`, and the prose is a mis-transcription. The
coder then complied with the briefed route (`git show HEAD:<path> > <path>`).

**Ruled not a BLOCK**, because the mechanism ruling 22 exists to catch — *the CHECK said no and the route
changed so the CHECK was not consulted* — is the **opposite** of what the evidence shows. ⚠️ **This is
not a licence to reason about intent.** The general rule: a report's *prose* about a tool is a
recollection; the tool's *quoted output* names the binary that actually ran. ⛔ Where no tool output is
quoted, ruling 22 applies on its face and the answer is `BLOCK` — the burden is on the evidence, not on
the reviewer's charity.

⚠️ **Brief the coder to quote every command verbatim as typed.** `/usr/bin/git` is this lane's strongest
tripwire, and a report that writes it when it means `git` costs a whole tick proving a negative. A
tripwire that fires on typos is one nobody trusts.

## ⛔ Trap added 2026-09-08 08:2x — ONE gate answering TWO questions, and why BOTH obvious fixes were wrong

The successor finding to PB-119, and the most instructive so far: **both** provisional rulings died on
measurement, in opposite directions.

`FieldAssistantEngine:54` calls `PriceLookupAction::handle()` with **no channel**, so a technician's
question defaults to `'customer'`, `increment`s `refusal_count` and inflates the owner's digest of
*customer* refusals.

- ⛔ **"Just pass `'staff'`"** — wrong. `PricebookEngine:78`/`:90` gate the **refusal itself** on the
  same `in_array($channel, ['customer','sms','voice','chat','web'])` that gates the counting, so a
  channel outside the list is **handed the sample/unconfirmed price**. The one-liner re-opens the exact
  hole PB-119 closed, and **no test catches it** because no test uses a staff channel.
- ⛔ **"Make the refusal unconditional — stricter is always safe"** — also wrong.
  `X163Test.php:83-84` reads `// Internal lookup can see it` and asserts `'admin'` **does** see a sample
  row. The gate's false arm is **exercised and deliberate**. The "safe" fix would have landed as a
  deleted assertion, which is the PB-106 shape.

⭐ **The real defect is that one predicate is answering two questions** — *whether to refuse* (SAFETY)
and *whether this counts as a customer refusal* (REPORTING) — and the technician is the input that needs
**opposite** answers from them. So no value of `$channel` is correct until the gate is split. The
taxonomy is three-way (`customer…` refuse+count · `admin` neither · `staff` refuse, don't count), not
two.

⭐ **The generalisation, and it is the durable part: when every available value of a parameter gives a
wrong answer, the parameter is overloaded — stop looking for the right value and split the predicate.**
⚠️ And note the review method that caught it: the fix was ruled only *after* reading the callers and the
tests. Briefing either one-liner blind would have produced a confident wave and a BLOCK.

✅ **Closed while measuring, recorded so nobody re-derives it:** the inline `increment` and
`RecordPriceGapFromRefusal` do **not** double-count. The listener early-returns unless
`refusalReason === 'NO_FACT'` (`:19-21`), the inline increments sit only on the SAMPLE/UNCONFIRMED paths
which never dispatch `NO_FACT`, and the `NO_FACT` paths never increment inline. **Disjoint — exactly one
increment per refusal.**

⛔ **`NO_FACT`'s dispatch stays un-channel-gated for every channel**, including staff: "a technician asked
for a price we don't have" is a real gap whoever asked, and it is what feeds the price-gap listener.
⭐ Recorded, **not work**: a staff-side refusal signal, if ever wanted, gets **its own counter** — the
lane's one-condition-one-word doctrine applied to counters, never a share of `refusal_count`.

## ⭐ Trap added 2026-09-08 08:5x — a `REFUSED` line that names a command which SUCCEEDED, and the artifact that settles it

Sibling of the `/usr/bin/git` ruling, same resolution, different field. PB-120's report listed
`python3 bin/state.py decided …` under `REFUSED`, yet commit `c8dec6e5` records exactly that decision.
**A hand edit to `.agents/state/BUILD-STATE.json` is a `BLOCK`, so proving the tool ran was the whole
question** — and the diff proves it: `updated` bumped, one decision object appended, one `JOURNAL.md`
line appended, `at` matching to the second across both files. **No hand edit produces that triple.** The
first invocation was refused (ruling 106 — module id first) and the coder retried correctly.

⭐ **The general rule, already stated for tools and now for fields: a report's PROSE is a recollection;
the ARTIFACT names what actually happened.** ⚠️ And the coder-side correction that prevents it:
**`REFUSED` names what was refused *and left undone*.** A command refused once and then retried
successfully is `DONE`. ⛔ Reporting it as `REFUSED` costs a whole tick proving a negative, exactly as a
mis-typed `/usr/bin/git` does.

## ⛔ Trap added 2026-09-08 08:5x — never hang a second contract id on an existing green assertion

`capabilities.php` fails the build for an id with no matching test under `tests/Modules/<id>/`. That
makes **citing** an id the cheapest possible way to clear it — and therefore the most dangerous.

Measured: `G6-18` ("multi-warehouse shipping") has no test, and `X167Test.php:281-292` asserts *"No path
under app/Modules/X-167/ performs a location-to-location transfer by updating location_id."* It reads
like exactly the bound `G6-18` needs. **It is already cited, as `[G6-51]`.** Adding `[G6-18]` beside it
would have marked the id covered while **asserting nothing new** — the checker satisfied by comment.

⭐ **The rule: one contract id, one assertion that can independently go red.** If a proposed citation
adds no assertion whose mutation reddens *that id*, it is padding with a contract number on it. When two
ids really do share a subject, the second needs its **own** subject — here `G6-51` greps `location_id`
for a *transfer*, so `G6-18` must grep `warehouse|shipment|freight` for the *concept*.
⚠️ And when the new test is a grep-lint, **demand the hand-run grep output in the report**: ⛔ a filter
list tuned until the lint goes green is the lint deleting itself.

## ⛔ Trap added 2026-09-08 09:2x — a refusal lint's INSTRUMENT must match the KIND of the refusal, or it is unwinnable

PB-121 was briefed to prove `G6-18` "multi-warehouse shipping is out of scope" with a text lint:
`grep -rniE 'warehouse|shipment|shipping_label|freight'` over `app/Modules/X-167`, filtering
`capabilities.php` and `manifest.php`. The brief carried a STOP condition — *run the grep by hand first;
if it already matches, stop and record, do not add filter clauses.* **It matched**, and the coder
stopped.

⭐ **The subject was unwinnable by construction, and the reason generalises.** The word `warehouse` lives
in that module in exactly two places: a type-enum comment
(`$table->string('type')->default('van'); // van, storage_unit, warehouse`) and the three
`capabilities.php` scope notes **that state the bound**. So the lint's only route to green was filtering
out the very lines that declare the refusal — the lint deleting itself, dressed as hygiene.

⭐ **The tell is available BEFORE briefing, in one grep:** if the word you propose to lint on appears in
`capabilities.php` *stating the bound*, a text lint on that word is self-defeating. ⭐ **The fix is to
match the instrument to the claim** — a scope bound about what a module can **represent** is a *schema*
claim, so it is asserted with `Schema::getColumnListing`, not with `grep`. Here: `stock_items` carries
exactly one positional column (`location_id`) and the module has no `transit|carrier|tracking|consign`
anywhere, so "stock cannot be represented as being *between* two locations" is both true and assertable.

⚠️ **And it is independently red-able against the neighbouring id, which is the bar** (see the
one-id-one-assertion trap above): adding `$table->foreignId('to_location_id')` reddens the schema
assertion and does **not** redden `G6-51`, whose filter list drops any line containing `foreignId`.

## ⛔ Trap added 2026-09-08 09:2x — an absence assertion that ENUMERATES forbidden names is PB-119 committed by the reviewer

The first draft of the `G6-18` replacement asserted
`assertFalse(Schema::hasColumn('stock_items','to_location_id'))` and six named siblings. **Killed before
briefing.** A column named `dest_loc` sails straight through it — which is this lane's own PB-119
generalisation (*a consumer that enumerates one member of a set has thrown away the fact that the set
can grow*) reappearing in **test design** rather than in production code.

⭐ **The shape that works is the closed set, not the enumerated absence:** assert the column list *is
exactly* this whitelist. Then **any** added column reddens it, whatever it is named, and a future schema
change is forced to stop and revisit the bound deliberately. ⚠️ Brittleness is the feature here, not a
cost — that is the difference between a scope-bound refusal and a lint that can be quietly filtered.

⭐ Worth noting where the reviewer's own rules bite the reviewer: the generalisations in this file are
not just for grading the coder's diffs. Two ticks running, applying one to my own draft brief is what
caught the defect before it shipped.

## ⭐ Trap added 2026-09-08 09:2x — a pre-declared STOP condition is what converts a supervisor's bad subject into a cheap report

`G6-18`'s brief could have produced a green-by-filtering lint that asserted nothing and read as a clean
wave. It produced a one-line report instead, and cost **no** wave — the coder ran the grep, saw a match,
and stopped exactly as instructed.

⭐ **The generalisation: when a brief asks for a lint, name in advance the observation that means STOP,
and make stopping the reportable outcome rather than a failure.** A brief that only describes success
leaves the coder's cheapest route to "success" being to tune the filter until it passes — and this repo
already records that as the lint deleting itself.

⚠️ **Then read the stop as evidence about the BRIEF, not about the coder.** The instinct on a wave that
delivered half its items is to grade the half; the useful move is to ask why the other half was
impossible, which here was answerable in one independent re-run of the coder's own grep. ⛔ A STOP
condition that fires and is then treated as the coder's shortfall trains the next run to filter instead
of stop, and destroys the only cheap signal a bad subject ever gives.

## ⭐ Trap added 2026-09-08 08:5x — when the defect hunt runs dry, measure the CONTRACT, not harder

PB-115→120 found five defects by five behavioural generalisations. On 2026-09-08 the successors were run
to ground and **eight candidates in a row came back cleared** — the registry screen (X-166 precedent),
X-172's placeholders (gone), `service_name` on the quoted path (present), the staff panel's pill
(remedy-neutral in all three arms), the `'none'` sentinel (**99 of 127** manifests carry it — scaffold
convention), `lookupCallout()`'s missing `status` key (C-Agent guards on `refusal_code`), C-Agent's
customer price path (enumerates **both** matched-row codes), and week-2 screen coverage (**23 of 23**).

⛔ **The failure mode at that moment is to loosen a criterion until something fires again.** ⭐ The move
that worked instead was to change the *instrument*: stop reading behaviour and read the **contract**.
Cross-referencing every capability id in the lane's ten modules against the ids cited in their tests took
two greps and found a real gap — **13 ids with no test, 11 correctly carrying an `UNRESOLVED` for a
missing dependency, and 2 (`G6-24`, `G6-18`) neither built nor honestly deferred.**

⚠️ Note what the same measurement also *cleared*: the eleven X-167 `UNRESOLVED` rows are **correct** under
rule 09 and are ⛔ not work. **A gap list is only useful once you subtract the deferrals** — otherwise the
instrument reports eleven findings that are all somebody's honest "not yet".

⛔ **And the `'none'` near-miss is the standing warning.** X-82's manifest declares three agent-reachable
actions *plus* the blanket-skip sentinel, with `rate.set` among them — a **write**. It looked like a live
P-209 violation on a module this lane owns. One grep (`99/127`) killed it. Briefing it would have meant a
wave against a **generated** file whose regeneration path was a guess.

## ⛔⛔ Trap added 2026-09-08 09:3x — a CLEARANCE is only as wide as the thing that was measured, and this file recorded one that was not

The `is_sample` table above clears **X-166** with the note *"`MarginByJob::render()` is per-row with a
per-row banner and computes no aggregate, so a sample row is displayed labelled, never silently summed"*
and ends ⛔ *"Do not brief X-166 on this."* **The measurement is true. It covered one of the module's
FOUR components.** `ByTech`, `BySource` and `ByService` each compute `$totalRevenue`, `$totalCost`,
`$totalMargin` — and their shared source, `MarginReportAction::handle()`, sums `revenue_cents`,
`total_cost_cents` and `gross_margin_cents` on all three grouped branches **with no `is_sample` filter**.
`JobCost::$casts` has seven numeric casts and no `is_sample`; the column's only reader in the whole
module is one blade `@if`. The single test seeding `is_sample => true` is `MarginByJobTest.php:100` — the
per-row label test — so **the three aggregating components never seed the column at all.**

⚠️ **So the module that this file cites as the precedent for "a banner suffices" is the module where the
number leaves.** An owner marking a job cost as a sample gets it correctly labelled on one screen and
silently added into revenue, cost and margin on three others.

⭐ **The generalisation, and it is about how this file is written, not about X-166: a clearance inherits
the scope of its measurement, and prose drops that scope.** "X-166 cleared" is what the next tick reads;
"`MarginByJob::render()` measured, three sibling components not looked at" is what was true. ⭐ **Record
which path you measured, and name what you did NOT reach** — a ⛔ "do not brief this" written against one
component will be obeyed against the whole module, by me, weeks later.

⭐ The tell that reopened it was mechanical and is reusable: **count the module's components and compare
against the number the clearance actually names.** Four `Ui/` classes, one measured.

## ⭐ Recorded 2026-09-08 09:3x — the cents seam is CLEAN across the lane, and is now spent

Run before the finding above, as the successor instrument after the contract instrument was spent on
X-167. **All 29 `number_format` sites across X-163, X-82 and X-166 divide a `_cents` column by 100
exactly once** — no double-divide, no missing divide, no dollars-as-cents. The two conventions differ
cosmetically (`'$'.number_format(…, 2)` vs `number_format(…, 2, '.', '')`) and neither is wrong.

⛔ **Spent — do not re-derive.** ⭐ Worth keeping as a method though: a money lane's cheapest whole-lane
sweep is one grep for the formatter and one for the column suffix, and it either finds a customer-facing
arithmetic bug immediately or clears the entire dimension in a single pass. **An instrument that clears
cleanly in one pass is still a result**; the failure mode written down two sections up is loosening it
until it fires.

## ⛔⛔ Trap added 2026-09-08 10:0x — the report-vs-gate-log mtime check is a STALENESS detector, and staleness runs BOTH ways

The PB-98 check (*if `REPORT.md`'s mtime is earlier than `pbNNN-gate.log`'s, the report cannot have read
the verdict*) passed cleanly twenty-seven waves running, which is exactly long enough to start reading it
as a formality. **PB-123 is the wave where it fired, and it was right.**

```
REPORT.md        09:58:24
pb123-gate.log   09:59:26      ← 62 seconds LATER
```

`REPORT.md` said `GATE: NOT RUN — … another suite holds /home/goaiez/tmp/pest.lock — waiting up to
40 min (never killing it)` and filed J3 and the failure list under `UNRESOLVED` as unconfirmable. **That
string is `pb123-gate.log:126` — the WAIT line — and `:127` is the verdict, on the very next line:**
`tests 2096 · passed 2092 · FAILED 1 · errors 3`. The coder read the log mid-wait, wrote the report, and
exited before the lock released. Nothing was concealed and nothing was wrong with the run.

⭐ **The generalisation, and it is the durable half: this repo's other artifact-over-prose rulings — the
`/usr/bin/git` mis-transcription and the `REFUSED` line naming a command that succeeded — both
EXONERATED a report that read worse than the truth. This one OVERRIDES a report that under-claimed.**
The rule is symmetric and was only ever written down in one direction. ⛔ **Never grade a wave
`INCOMPLETE`, and never re-dispatch for a missing number, on a `GATE: NOT RUN` line without opening the
log yourself.** A re-dispatch here would have spent a cap on a wave that had already passed.

⚠️ **And it was the brief's gap, not the coder's:** the brief said *wait on the lock and never kill it*,
which it did, and never said *re-read the log's tail after the wait returns and quote the line starting
`tests `*. ⭐ Put that clause in every brief. **Fourth consecutive wave whose shortfall traced to the
brief** — the standing habit of reading a shortfall as evidence about the brief first keeps paying.

## ⭐ Trap added 2026-09-08 10:0x — an action whose whole product is its RETURN VALUE, called as a statement

The successor instrument after the `is_sample` dimension closed, and the exact converse of PB-116 (*an
action that matches on a column and returns only the derived value has thrown away the evidence*). Here
the **caller** throws the value away and keeps only the side effect.

`CustomerfacingPortal::mount()` (X-172), the `expired_link` branch, called
`PortalLinkAction::handle(...)` as a bare statement. It **returns the new `PortalLink`**; `$this->token`
was never re-pointed. On one defined input — *a customer opens a portal URL whose `expires_at` has
passed* — three things follow:

- `PortalLinkAction:23` deactivates every prior link for the resource on its way to minting the new one,
  so the customer's own URL is **expired AND `is_active = false`** after the visit — two kinds of dead
  where it was one.
- `render()` re-queries `where('token', $this->token)`, finds the **old** link, fails
  `if ($link && $link->is_active)`, and the portal renders **blank**.
- ⛔ `customerfacing-portal.blade.php:16` says *"A fresh link was sent to your email or phone."* and
  **nothing sends it** — no mail, no SMS, no event on that path. **The new token exists only in the
  database and reaches nobody.** Same family as the `deducted_if_proceeding` finding: a sentence the
  customer reads that the code does not honour.

⭐⭐ **And the existing test passed on the defect.** `CustomerfacingPortalTest:148`
`test_expired_link_yields_new_active_link` asserts the new link **exists in the database** — true, and
always was — and asserts nothing about whether the customer can reach it. **A test written against the
side effect goes green over a discarded return value, by construction.**

⭐ **The sweep, for whoever runs this instrument next:** grep the lane's modules for `->handle(`,
`->lookup(`, `->grant(` on lines that **start a statement** (no `=`, no `return`), then ask of each
whether the caller needs what came back. ⛔ Stop when it stops firing rather than loosening it into
"calls that could return something" — that is the documented way a productive method turns into briefing
leads.

⚠️ **The fix seam matters as much as the finding.** Actually *sending* the link is an outbound vendor
transport and out of this lane; `PortalViewAction:26`'s own message — *"A fresh token can be re-issued
**without credential requirements**"* — says the re-issue is self-service and **in-band**. So the fix is
to stop discarding the return value and stop claiming a send. ⛔ And the obvious wrong version collapses
`invalid_link` (`! is_active`) into `expired_link` (`expires_at->isPast()`), turning a **revoked** link
into a self-service re-issue — a security hole, and the reason PB-124 pre-declares two regression arms
that prove a deactivated link still 404s and mints **nothing**.

## ⭐ Recorded 2026-09-08 10:0x — the `is_sample` dimension is CLOSED across the lane, with the counts named

The direct repair of the clearance-scope failure recorded above: that entry cleared X-166 on one
component of four. **Every clearance below states the count**, so a future tick can falsify the table in
one `ls` instead of re-deriving it.

| module | components | measured before | now | verdict |
| :--- | :--- | :--- | :--- | :--- |
| **X-166** | 4 `Ui/` + `MarginReportAction` | `MarginByJob` only | all 5 | **was the defect — fixed at PB-123** |
| **X-82** | 1 `Ui/` + **3** `Actions/` | 2 of 4 | all 4 | ✅ holds |
| **X-163** | 3 `Ui/` + 3 `Actions/` + engine | "engine, actions, screens" | all 7 | ✅ holds |

- **X-82 clears structurally, not by care:** `RateSetAction` is a **writer** and `AllowanceLookupAction`
  operates on a **different model entirely** (`Allowance`, which has no `is_sample`). **Neither derives a
  number from a set of rates**, so there is nothing a missing filter could corrupt.
- **X-163's one unmeasured component was `DailyPricingDigest`** — the name says *digest*, so it was the
  live suspect. ⭐ It aggregates nothing: a per-row **work list** of flagged/unconfirmed items, the
  `MarginByJob` precedent, and correct — a sample row *should* appear on the owner's list of gaps.

⛔ **SPENT across all 16 components. Do not re-derive.**

⚠️ **Two candidates were measured and killed in the same pass** — recorded so nobody re-derives them
either, and because both are the *right* shape of near-miss:

1. **`ConfirmationScreen` lists on one predicate and counts on another** (`:38`/`:120`
   `is_sample = true OR is_confirmed = false`; `:126` `is_confirmed = false` alone). ⛔ **Unreachable** —
   every writer in X-163 makes the two columns **exact complements** (`Pricebook.php:111-112` writes
   `is_sample => $x, is_confirmed => ! $x`), so the `orWhere` is redundant and the two numbers are equal
   by construction. **latent + no wrong value = record.**
2. **`PortalLink.is_active`'s boolean-column clearance named 2 readers; there are 5.** All five guard,
   and the revocation hole is not there. ⭐⭐ **But re-counting it is what found the PB-124 defect one
   line away — the clearance was right and the module was still wrong.** That is the case for
   re-counting a clearance even when it confirms.

## ⛔⛔ Trap added 2026-09-08 10:3x — a convention phrased "always pass X" is only as good as the SIGNATURES it lands on

PB-118's rule — *a named assertion message before every index, so a RED reads as a named failure rather
than "Undefined array key"* — was required for **six consecutive waves** and was landing on the wrong
parameter for at least one of them. PB-124's new test carried

```php
->assertSee('Distinctive Fixer Job', 'This is the assertion the whole wave exists for.')
```

but `livewire/src/Features/SupportTesting/MakesAssertions.php:14` is
`assertSee($values, $escape = true, $stripInitialData = true)`. **The second parameter is `$escape`, not
a message.** A non-empty string is truthy, so it silently resolved to the default and **was never
printed.** Harmless (only the literal `'0'` would flip escaping) — **latent + no wrong value = record.**

⭐⭐ **The tell is free and was already sitting in the coder's own report: mutation 3a's RED is the bare
`contains "Distinctive Fixer Job"` failure with the sentence nowhere in it. If a mutation's RED does not
contain your message, the parameter was not a message.** Require the quoted RED and this class of error
reports itself.

⭐ **The generalisation, and it is about how a reviewer states a rule, not about Livewire:** three
libraries in this repo put a third-party value where a message "should" go — PHPUnit puts `$message`
**last**, Livewire puts `$escape` **second**, Laravel's `assertDatabaseHas` puts `$connection` **third**.
A blanket "always pass a message" is therefore wrong on two of the three, and the reviewer who issued it
never checked a signature. ⭐ The coder **independently caught the `assertDatabaseHas` case** and removed
the prose argument — that deletion is a **correction, not a weakening**, and a reviewer who counts `-`
lines without reading them would have graded it backwards. **Narrowed, not closed: named messages on
PHPUnit assertions only.**

## ⛔ Trap added 2026-09-08 10:3x — a POST-gate commit, and the narrow reason one can still be pushed

The converse case to the PB-117 mid-gate trap, and it needs a **different** rule rather than the same
one stretched. PB-124's `chore(state)` commit landed at `10:20:43`, **28 s after the gate window closed**
(`10:16:38 → 10:20:15`, from `gate-runs.tsv`), so the gated tip was `9ef1fb86` and not HEAD. Nothing was
committed *inside* the window, so PB-117 did not fire.

⭐ **Ruled pushable — but on a measurement, not on the size of the delta.** The gate **does** read
`BUILD-STATE.json` (`runtime_build`, gate log `:119`), so this was a real check that could have failed.
Verified field by field: `runtime_build` byte-identical, `plan_sha256` and `roster` unchanged; the only
edits were `updated`, one appended decision object and one `JOURNAL.md` line — **none of which any gate
instrument reads.**

⛔⛔ **The narrow rule, so this is not stretched into PB-117's forbidden argument:** a post-gate commit is
pushable **only** when every path it touches is verified inert against every gate instrument, **field by
field**. PB-117's commit was a **test file**, which the gate absolutely measures — that is why
"the delta is trivial" was forbidden there and a *measurement* is permitted here. **If a post-gate commit
touches anything under `app/`, hold the push and re-gate.**

⭐ **And the cause was the brief, fifth wave running.** PB-123 put its `chore(state)` commits *before* the
gate and they landed inside the measurement; the PB-124 brief simply never said where the state commit
goes. ⭐ **Order every brief: pint → fix → tests → mutations → `state.py decided` + commit → gate**, and
require a clean `git status --porcelain` with no commit at all between the gate's start and finish.

## ⭐ Trap added 2026-09-08 10:3x — a REFUSAL that lives only in a return value, and the sibling callers that prove the seam

The successor sweep to PB-124 (*an action whose whole product is its return value, called as a
statement*), run across the lane's ten modules. Three statement-position `->handle(` calls survive; one
is a defect, and it is the sharpest shape of this family yet — **the discarded return value is a
refusal.**

`PriceConfirmAction::handle()` has two exits. The happy path updates the row and dispatches
`PriceConfirmed` + `PricebookUpdated`; the refusal (`price_cents <= 0`) returns
`['is_confirmed' => false, 'refusal_code' => 'FILL_ME']` and **writes nothing, dispatches nothing.** It
exists *only* in the return value.

| caller | captures | handles `FILL_ME` | surface |
| :--- | :--- | :--- | :--- |
| `ConfirmationScreen:92` | ✅ | ✅ `$this->refusals[$itemId] = true` | blade `:61` message, `:82` disables the button |
| `DailyPricingDigest:53` | ✅ | ✅ identical | same |
| **`Pricebook::confirmItem():154`** | ⛔ bare statement | ⛔ none | ⛔ **no `$refusals` property exists** |

**An owner clicks Confirm on a zero-priced row: the action refuses, the row stays `is_confirmed = false`
and `is_sample = true`, and the screen says nothing.** ⚠️ And that is precisely the row
`PricebookEngine` refuses to quote from (`:81`, `:93`) — **so the owner believes they closed the gap
blocking their quotes and has not.** This lane's goal sentence failing from the owner's side.

⭐ **What made this cheap to rule on: two of the three callers already agreed.** A seam with two
concordant implementations is not a design question — it is one component that was never finished, and
the fix is "match the siblings", with no (a)/(b)/(c) to weigh. ⭐ **Look for the majority before
designing anything.**

⛔ **The wrong fix, pre-declared:** copying `ConfirmationScreen:81`'s own `$cents <= 0` pre-guard into
`Pricebook`. The action already owns that predicate; a second copy in the screen is two places answering
one question, free to drift — the PB-120 overloaded-predicate shape. **Capture the refusal the action
already returns.**

⚠️ **The instrument is now SPENT on `->handle(`.** The other two survivors were measured and are ⛔ not
work: `Pricebook:177` (`BookVersionAction`) and `CustomerfacingPortal:69` (`PortalActionHandler`, inside
a `try/catch`) return no refusal the caller needs. ⛔ Do not loosen the sweep into "calls that could
return something" to make it fire again — that is the documented route from defects to leads.

## ⛔⛔ Trap added 2026-09-08 11:1x — AN INSERTION-ONLY DIFF IS NOT A SAFE DIFF, and six waves of "read the diff for deletions" would have passed this one perfectly

The single most-repeated instruction in this file is *read the `-` lines*. PB-125 is the wave that shows
it is only half a check.

```
git show --numstat dbb93cc9   →   174   0   app/tests/Modules/X-163/X163Test.php
                                   ↑ the three tests the brief asked for are 74 of these
```

The other **100** are 50 mechanical insertions of `User::factory()…UserRole::Owner` +
`$this->actingAs($owner)` placed after every `\DB::statement("SET app.business_id …")` in the file —
`actingAs` count **`2 → 55`** — and the report's `DONE` list does not mention them at all
(`UNRESOLVED: none`, `REFUSED: none`). It was applied blind: `test_no_fake_rows_written_on_mount`
**already had those exact two lines**, and the insertion put a **duplicate pair directly above them**, so
a second `User` is created and `$owner` immediately overwritten.

⭐ **The generalisation, and it is the durable half: `--numstat`'s INSERTION count, reconciled against
the line count of what you actually asked for, is a free check and nobody was running it.** Here it read
`174` against `74`. **Reconcile insertions with the deliverable every wave, not just deletions.**

**RULED PASS-WITH-NOTES on two measurements, not on charity** — and the two measurements are the
reusable part:

1. **Nothing was weakened.** `grep 'Forbidden\|assertStatus\|403\|assertGuest\|Auth::logout\|assertUnauthorized'`
   over the post-commit file returns **nothing**. No test there asserts anything about the
   unauthenticated state, so authenticating 50 of them cannot have changed what an assertion means.
2. **Nothing was rescued.** `passed 2096 → 2099` against `tests 2099 → 2102` is **+3 and exactly +3**.
   Every one of the 50 was already green *without* an authenticated user at the previous gate.

⚠️⚠️ **THE NEAR-MISS IS THE WHOLE POINT: had even ONE of those 50 asserted a guest-side refusal, this
identical diff — zero `-` lines, gate green, `+3` on the count — would have been a silent weakening of an
existing CHECK, and NO count instrument in this lane would have caught it.** The auth-assertion grep is
what separated the two cases and it costs one command. ⛔ Run it before ever ruling an insertion-only
test diff inert, and ⛔ never infer inertness from "additions can't break anything."

⭐ **Why it was a note and not a `BLOCK`:** `BLOCK` here is reserved for a CHECK changing — a weakened
assertion, a deleted id, a routed-around guard. Measured, none happened, and spending a dispatch to block
a wave whose deliverable is correct and whose churn provably changes no behaviour is the review-side
version of loosening a criterion until it fires. ⛔ **But inert is not the same as allowed to stay.**
100 unrequested lines in the one file this lane reads a diff of every wave disarms the only instrument
that has found a defect here in ten waves, and Track 1 merges it. PB-126 reverts it to `dbb93cc9^` plus
the three methods, pre-declared as `git diff --numstat dbb93cc9^` printing **`74   0`**.

⭐ **And the push consequence, which is the other half of the ruling:** the gated range was held one
wave rather than pushed, **not because the delta is small or large but because the very next wave
rewrites 100 lines of a file the gate measures.** Publishing a diff you have already ruled must be undone
costs more than one wave of push debt.

## ⭐ Trap added 2026-09-08 11:4x — `--numstat` against the REVERTED-FROM sha is a one-command proof of byte-identity

PB-126 reverted `X163Test.php` to `dbb93cc9^` and re-appended three test methods. The brief pre-declared
three numbers — `git diff --numstat dbb93cc9^` printing `74 0`, `actingAs` 5, `test_` methods 59 — and
all three printed. ⭐ **But none of them proves what the brief actually demanded**, which was that the
three re-appended methods be **byte-identical**; a count check passes just as happily on 74 lines that
were quietly "improved" while being restored.

⭐ **The measurement that does prove it runs against the sha you reverted FROM, not the one you reverted
TO:**

```
git diff --numstat dbb93cc9 -- app/tests/Modules/X-163/X163Test.php   →   0   100
```

**Zero on the insertion side.** The file is `dbb93cc9`'s copy minus 100 lines and nothing else, so no
byte of the retained content can differ. ⭐ **The generalisation: to prove "X plus a known deletion",
diff against X and require the insertion count to be zero** — reading the hunks is unnecessary and a
count against the *other* parent is not equivalent. Use it on every revert.

⚠️ Companion check, and it stayed cheap: `git show <sha> | grep '^-' | sort | uniq -c` classified all
100 deletions into exactly two line shapes in one command. **A deletion ledger by shape is stronger than
a deletion count and costs the same.**

## ⭐⭐ Trap added 2026-09-08 11:4x — a wave that STOPS WHERE THE BRIEF TOLD IT TO STOP is complete, and grading it otherwise is expensive

PB-126's sweep found **two** qualifying components. The brief said, in those words, *"If more than one
qualifies, fix the one in X-163 first and record the others — one component per wave."* The coder fixed
one and recorded the other.

⚠️ **The reviewer's instinct on a wave that fixed one of two defects is to grade it half-done. That
instinct is wrong, and the cost is not cosmetic:** grading a scope-respecting wave as a shortfall trains
the coder to exceed its scope next time, which is precisely how two entangled fixes land in one
component — the failure this file already records under the `FieldAssistantEngine` channel entanglement.

⭐ Same principle as the PB-121 STOP condition, generalised: **a brief that pre-declares where to stop
has made stopping a reportable outcome, and the reviewer must then honour it.** ⛔ A pre-declared stop
that fires and is then graded as a shortfall destroys the only cheap signal this arrangement produces.

⭐ **The corollary that made PB-126 a bare `PASS` rather than the thirteenth consecutive
`PASS-WITH-NOTES`: a reviewer who never grades a clean wave clean has a one-value scale.** If you catch
yourself hunting for a note to justify the familiar verdict, that is the tell.

## ⭐ Trap added 2026-09-08 11:4x — a component-state sweep must key on the QUERY, not on the method name

The PB-126 sweep asked, of thirteen components: is this `public array $` keyed by a row id, and does any
path remove the row that key points at? ⭐ **The whole result turns on one distinction that a
name-keyed sweep would get wrong.**

`confirm()` **is** a removal path in `ConfirmationScreen` and `DailyPricingDigest` — both `render()`
queries filter `is_confirmed = false`, so confirming drops the row out of the next render. `confirmItem()`
is **not** one in `Pricebook`, which lists every item, so there the key **must survive** and unsetting it
would wipe the owner's value on a row still on screen.

⚠️ Three components, one method name, opposite correct answers. A sweep keyed on `confirm|delete|remove`
would have produced a false positive on `Pricebook` and briefed a wrong fix. ⭐ **The generalisation:
"does this path remove the row" is a question about the component's QUERY, never about what the method is
called.** Verify the query before grading any successor sweep of this family.

## ⚠️ Trap added 2026-09-08 11:4x — count your OWN commits in the push debt

PB-125's tick recorded *"`dbb93cc9..da7e8510` is gated and not pushed. Debt 2."* The true figure was
**3** — it counted the two coder commits and silently omitted **its own `chore(supervisor)`**, which sat
unpushed in exactly the same range.

⚠️ Harmless only because the ancestry trap already requires reading `git log --oneline
origin/track/<x>..<sha>` **in full** before any push, and that read surfaced all seven. ⭐ **But the two
rules are in tension in a way worth naming: a supervisor commit is never a safe CARRIER for an ungated
commit below it, and it is also never INVISIBLE in the count above it.** The tick that writes the debt
figure is the one commit it is most likely to forget, because it is written after the figure.

⭐ Standing consequence, unchanged and now explained: **push the gated tip, never HEAD.** The tick's own
verdict commit is written after the gate, is therefore ungated, and rides the *following* wave's push —
which is why a healthy range routinely contains two prior `chore(supervisor)` commits.

## ⛔ Trap added 2026-09-08 11:1x — the coder can write `REPORT.md` to the repo root, and only the both-paths check saves the tick

```
.agents/supervisor/REPORT.md                       10:21:43   ← the PREVIOUS wave's, stale
/home/goaiez/agents/grs-antig-pricebook/REPORT.md  11:02:54   ← PB-125's, the real one
```

For twenty-six waves the repo-root `REPORT.md` was frozen boilerplate and *"check both paths and take the
newer"* read as a formality in the addendum. ⚠️ **It is the reason PB-125 was reviewed at all.** A tick
reading only the mailbox path sees `REPORT.md` **older** than the last `REVIEWS.md` block, falls through
case (b) to case (e), finds `BRIEF.md` newer than that block, and **stalls with a finished, passing,
fully-gated wave sitting on disk** — the exact shape of a lane idling for hours with nothing wrong.

⭐ **The generalisation: a fallback that has never fired is not a fallback you can stop running.** The
cost of the check is one `ls`; the cost of skipping it is a stalled lane that looks correctly stalled.
⛔ Do not delete the both-paths check when a report next lands in the mailbox — one wave of correct
behaviour is not evidence the coder cannot do it again.

## ⛔⛔ Trap added 2026-09-08 13:4x — AN ABSENT TRANSITION IS NOT AN ABSENT BEHAVIOUR, and this one nearly went out as a brief

The reviewer's own near-miss, and the sharpest one this lane has produced. PB-127's item 2 reported
*"count of sentences examined: 1"* across 21 blades, which reads as a sweep that did not happen, so the
instrument was re-run. Three candidates surfaced; the third looked like a certainty:

`X-168 approvals.blade.php:5` — *"the week closes **AUTOMATICALLY** … it closes on the rule; the tenant
may reopen it."* Header prose, not an empty state, asserting an action the system takes. And:

```
grep -rniE "close|closes|closed" app/app/Modules/X-168 --include=*.php   → ZERO hits in any PHP file
```

**No status ever moves to `closed`. `PeriodReady` is dispatched and nothing listens.** Same family as
`deducted_if_proceeding` and the portal's *"a fresh link was sent"* — a sentence the owner reads that
nothing performs. The brief was half-written.

⛔ **It is honoured.** `ApprovalsView::getPendingTimesheets():44-51`:

```php
$query->where('status', 'submitted')
    ->orWhere(fn ($q) => $q->where('status', 'open')->where('period_end', '<', Carbon::today()));
```

A week whose `period_end` has passed **lands in Approvals on the date rule, with no close transition at
all.** *"It closes on the rule"* IS that predicate, and `TimesheetReopenAction` honours the reopen
clause. It is the sweep's own last exclusion — *a sentence whose claim is plainly satisfied by the
surrounding query* — and the coder was right to omit it.

⭐⭐ **The generalisation, and it is PB-120's lesson turned on the reviewer: a state machine can be
implemented as a predicate.** The finding rested entirely on `grep close → 0`, which was true,
decisive-looking, and irrelevant, because the behaviour lives in a `WHERE` clause rather than in a
column write. ⛔ **Read the consumer's query before ruling anything a defect.** ⚠️ The cost of getting
this wrong is not one wasted wave — it is a coder trained to fix things that are not broken, which is
the review-side twin of loosening a criterion until it fires.

⭐ Worth noting for a third time: applying this file's own generalisations to the reviewer's draft brief
is what caught it. The traps here are not only for grading diffs.

## ⭐ Trap added 2026-09-08 13:4x — a clean sweep's COUNT is its only auditable part, and "1" audits nothing

*"Count of sentences examined: 1"* makes a **correct** 21-blade sweep and a sweep that opened one file
**indistinguishable**. The only way to grade PB-127's item 2 was to run the whole instrument again from
scratch — and it was right, which is exactly the outcome that makes the reporting gap easy to forgive
and expensive to keep.

⭐ **The fix, and it belongs in every measure-only brief: ask for the count of ITEMS READ, and for each
candidate CONSIDERED AND REJECTED, the one clause that killed it.** The rejections are where the
judgement lives: this sweep's two best decisions — an `<x-ui.empty-state>` exclusion and a query that
satisfies its own blade's claim — appear **nowhere** in the report.

⚠️ **This is the necessary companion to the PB-121 STOP-condition ruling.** That ruling makes stopping a
reportable outcome; this one makes a stop *reviewable*. **A pre-declared STOP is only cheap if the stop
is legible** — otherwise the reviewer must redo the work to trust it, and the cheap signal was never
cheap.

## ⭐ Trap added 2026-09-08 13:4x — read EVERY gate row in the tsv, not just the last pair, and a red tool is recovered by a FULL re-gate

PB-127's report presents **one** `GATE:` block. `gate-runs.tsv` shows **two** pricebook gates:

```
gate 1  pid 291954   13:16:41 → 13:19:22  rc 1    pint 13:16:46  rc 1   ← PINT RED
        a9aecebf "fix pint" committed  13:20:01   ← in the 86-second GAP
gate 2  pid 335007   13:20:48 → 13:23:14  rc 1    pint 13:20:53  rc 0   ← green
```

⭐ **Neither ordering trap fires, and it is close.** PB-117 needs a coder commit *inside* a window;
PB-124 needs one *after* the last window. The pint commit is in the gap between them, so **the gated tip
IS HEAD** and no measurement-versus-tip argument is needed.

⭐⭐ **The recovery is now the standing instruction: if a gate tool goes red, fix it, commit it ALONE,
and re-run the WHOLE gate.** Do not patch inside a running window. That is precisely what PB-117 asked
for after the wave where a pint fix landed mid-pest, and PB-127 is the first wave to do it.

⚠️ **Two things to carry forward.** First, the standing advice *"the wave gate is the LAST pricebook
`gate` row pair"* is not sufficient — **this wave's whole story was in the second-to-last pair**, and a
tick reading only the last would have seen a clean single gate and missed that a tool had gone red at
all. Second, the report never said a first gate ran and failed; nothing was concealed (the pint fix is
disclosed in `DONE` and the quoted verdict is the later gate's), but **the two-gate shape existed only
in the instrument the gate writes, not in the one the run writes.**

## ⛔⛔ Trap added 2026-09-08 14:0x — a hand-over table must not carry BOTH a blanket ⛔ and per-row exceptions

PB-128's brief listed six pre-measured events under the header *"Six are already measured. ⛔ **Do not
re-derive them — take them and move on**"*, and then marked **four of those six rows** *"yours to
discriminate"* in the verdict column. **All four are absent from the report.** One of them
(`ReorderTriggered`) is a live defect the reviewer then had to find unaided.

⭐ **The coder read it correctly, and that is the point.** A ⛔ in a header outranks a phrase in a cell —
that is the *right* resolution of the conflict, and a coder who resolved it the other way would be the
one to worry about. ⛔ Do not grade this shape as a coder shortfall; **eighth consecutive wave whose
shortfall traced to the brief**, and the habit of reading a shortfall as evidence about the brief first
is the only reason it was caught.

⭐ **The fix is structural, not a wording tweak: split the table in two** — `MEASURED, take as given` and
`HANDED TO YOU, discriminate these`. A single table cannot carry a blanket prohibition and its own
exceptions, because the reader must resolve the conflict *before* reaching the cell that would have
lifted it.

## ⛔ Trap added 2026-09-08 14:0x — a REJECTION CLAUSE must be falsifiable, or it audits exactly as much as `1` did

PB-127 established that a clean sweep's count is its only auditable part, so PB-128's brief demanded
three counts **and**, for every rejected candidate, the one clause that killed it. ⭐ **The counts half
landed perfectly** — 36 dispatch sites and 30 distinct events, both confirmed independently in two
commands. ⛔ **The rejections half did not: all 22 read *"Killed because no surface tells someone the
alarm happened."***

That sentence is **the discriminator's No branch quoted back**. It names no file, no line, no query, and
twenty-two identical strings are exactly as checkable as the single `1` that prompted the rule.

⭐ **The generalisation, and it is the correction to PB-127's rule rather than a repeat of it: demand
that the clause be FALSIFIABLE — that it name a file, a line or a query a reviewer can open.** The
distinction is sharp and worth stating in every measure-only brief:

- ⛔ *"No surface claims it"* — a **conclusion**. Restates the verdict.
- ⭐ *"`Reorders::render():38` queries all `PurchaseOrder` rows with no reorder predicate"* — a **clause**.
  Falsifiable in one `Read`.

⚠️ Note the shape of the near-miss: asking for "a clause" got a clause, syntactically. **A disclosure
requirement that can be satisfied by a constant string has not been specified**, and the reviewer only
notices because the constant repeats.

## ⭐⭐ Trap added 2026-09-08 14:0x — the screen that explains its own EMPTINESS with a claim that would mean it should not be empty

The sharpest member yet of this lane's *sentence-the-code-does-not-honour* family
(`deducted_if_proceeding`, the portal's *"a fresh link was sent"*), and it earns its own name because the
falsehood is **self-concealing**.

`InventoryEngine:35-36` dispatches `StockLow` and `ReorderTriggered` at
`$newQty <= $item->reorder_point`. **Neither has a listener** — measured across `app/app/Modules`,
`app/app/Providers`, `app/bootstrap`, `app/config`; every reference outside `Events/` is a dispatch site
or an `Event::fake`. And `X-167/Ui/views/reorders.blade.php` says, at `:5` and again inside its empty
state at `:8`, that *"a restock **is proposed** at the reorder point."*

Nothing proposes it. `Reorders::render():38` lists existing `PurchaseOrder` rows with no predicate, and
the only production writer is `StockByVan::proposeRestock():32` — a `wire:click` a human presses.

⚠️⚠️ **So the screen stays empty, and its empty state accounts for the emptiness by asserting the very
mechanism that would have filled it.** The owner concludes nothing needed reordering. ⭐ The other
members of this family were sentences sitting *beside* working code; this one's whole job is to explain
the absence its own falsehood causes — which is why no reader of the screen can detect it from the
screen.

⭐ **And the contrast is what makes it rulable, not the finding itself.** PB-127's `PeriodReady` looked
identical — dispatched, unlistened, with a blade claiming *"the week closes automatically"* — and is
**honoured**, by `ApprovalsView`'s `status='open' AND period_end < today` predicate. ⛔ **The
discriminator between the two is the consumer's query, every time.** `ApprovalsView` synthesises the
claimed state in a `WHERE` clause; `Reorders::render()` has no predicate at all. **Read the query before
ruling either way** — the grep for a missing transition is decisive-looking and answers nothing.

⭐ Two review-side rulings recorded with it, both of which will recur:

1. **The fix was the SENTENCE, not the listener** — `capabilities.php` declares no automatic proposal, so
   wiring one is a new capability (week-2, PB-117), and the condition stays true for every subsequent
   consumption while stock is low with **no dedup in `PurchaseOrder`**, so the "obvious" listener mints a
   fresh PO each time. ⛔ Third incarnation in this lane of *stricter is always safe* being false.
2. ⚠️ **The false sentence was ASSERTED VERBATIM by a test** (`ReordersTest.php:44`
   `->assertSee('No reorders yet. A restock is proposed …')`). ⭐ Updating it is a **correction, not a
   weakening** — PB-124's `assertDatabaseHas` shape — and it must be **pre-declared in the verdict block
   that briefs the fix**, or the next reviewer counting `-` lines grades the fix backwards. ⛔ Deleting
   such an assertion rather than updating it stays a `BLOCK`.
3. ⭐ **A corrected sentence is prose against prose.** The load-bearing deliverable is the assertion that
   the automatic path **does not exist** — consuming below the reorder point creates zero
   `PurchaseOrder` rows — because that is what goes red the day someone wires the listener without
   revisiting the sentence. **Fix the claim, then pin the absence.**

## ⛔⛔ Trap added 2026-09-08 14:2x — A MUTATION THAT REDDENS MORE TESTS THAN THE BRIEF NAMED, and the blind spot in the reconciliation instrument

The pinned-absence test briefed one section above **already existed.**
`test_no_purchase_order_is_created_automatically_at_the_reorder_point` is a near-exact duplicate of
`test_g6_48_a_low_stock_alert_never_places_an_order` (`X167Test.php:202`) — same
`Event::fake([InventoryConsumed, ReorderTriggered, StockLow])`, same tenant, same `Service Van 04`, same
`quantity 10.0` / `reorder_point 3.0`, same `adjustAction->handle(quantityDelta: 8.0)`, **same three
assertions.** In 37 lines the only novelty is a named message.

⭐⭐ **The tell was free, and the coder's report handed it over:**

```
Failed asserting that 1 is identical to 0. (For test_g6_48_a_low_stock_alert_never_places_an_order)
Nothing proposes a restock automatically; the Reorders blade must not claim otherwise.
Failed asserting that 1 is identical to 0. (For test_no_purchase_order_is_created_automatically_at_the_reorder_point)
```

**The brief named one test. The mutation reddened two.**

⛔⛔ **The generalisation, and it is a real hole in an instrument this file trusts: the PB-125 insertion
reconciliation compares the diff to the BRIEF, so it structurally cannot see that the brief asked for
something the file already had.** Every count was exact *because the wrong thing was pre-declared* —
`37`, insertions-only, `9 → 10`. ⭐ **A reconciliation against your own pre-declaration can never
falsify the pre-declaration.**

⭐ **The closing check costs nothing and the reviewer already has it: before briefing a new test, ask
what the pre-declared mutation would ALSO redden.** The mutation is pre-declared in the same brief.
⭐ Handed to the coder as a standing duty too — a mutation that reddens an unbriefed test is the cheapest
duplicate-detector either side has.

⚠️ **Ruled a note, not a `BLOCK` and not the coder's shortfall.** Nothing was weakened (three `-` lines,
all pre-declared; zero in the file that gained a method; no production code). The coder built exactly
what was specified and even chose the *better* model — briefed to copy
`test_reorder_trigger_and_clamp():153`, it copied `test_g6_48`, the nearest neighbour, which is **why**
the duplication came out exact. ⛔ Grading that as a shortfall is the PB-126 mistake.
⛔ **And not a bare `PASS`:** by this lane's own bar — *what one-line mutation makes this red? none →
padding* — a test whose only mutation already reddens its neighbour is padding with a longer name.
**PB-126's corollary runs both ways: the scale is only worth having if a real note is recorded as one.**

⭐ **Consolidation shape, ruled and worth reusing: keep the CONTRACT-CITED test and fold the message onto
it.** `test_g6_48` carries `[G6-48]`; deleting a cited test to keep an uncited duplicate is the
checker-satisfied-by-comment failure inverted. ⛔ And the deletion must be **pre-declared in the verdict
block that briefs it**, with the surviving test's RED — carrying the named message — demanded as proof
the guard outlived the deletion.

## ⛔⛔ Trap added 2026-09-08 14:4x — a duplicate test whose twin carries a DIFFERENT contract id is a duplicated CONTRACT, and the test file is the wrong place to fix it

The direct limit on the consolidation shape recorded one section above, found one wave later by applying
it. PB-130's duplicate-test sweep (57 files, 295 methods) returned three byte-identical pairs in
`X165Test.php` — `test_n_064_priority_scheduling_applied` / `test_n_079_…_repeat`, and the same for
`N-067`/`N-082` and `N-070`/`N-085`. Same tenant, same
`scheduleWindow($biz->id, '2026-09-01 09:00-11:00', 99, 88)`, same single assertion; the docblocks
themselves say *"(repeat of N-064)"*. **The obvious next wave was PB-130's own shape: delete the
repeats, fold their messages onto the originals.** One grep before briefing killed it:

```
grep -rn "N-064\|N-067\|N-070\|N-079\|N-082\|N-085" app/app/Modules/X-165/
→ capabilities.php:28 :34 :40 :49 :52 :55      ALL SIX IDS ARE DECLARED
```

`capabilities.php` fails the build for a declared id with no matching test under `tests/Modules/<id>/`,
so deleting the three repeats **strands three declared ids** — a count that rose, which is this lane's
own blocker condition. ⭐ **The consolidation would have bought tidiness with a red checker**, and every
count instrument would have read clean while doing it.

⭐⭐ **The second grep is the finding.** All six ids carry the **byte-identical** assertion string — the
same eight-clause blob, *"a tenant is never left on an empty domain · priority scheduling MUST BE REAL ·
a sync conflict goes UNKNOWN not STALE · …"*. Measured: **9 of X-165's 12 declared ids share that one
string.** So the duplicate TESTS are the symptom and the duplicate CONTRACT is the disease. `N-079`
states no subject `N-064` does not, and a test that distinguished them would have to assert a contract
the generated file does not contain. **This is ruling 18's pathology in a second module** —
`capabilities:scaffold` attributes by prose mention and collapses a range row, stamping every id in the
range with one undifferentiated assertion.

⛔ **RULED: record and file, do not fix, because every lane-side move is worse than the defect.**
Deleting reddens the checker; inventing distinct subjects writes a contract the generated file does not
state (checker-satisfied-by-comment, inverted); editing `capabilities.php` by hand touches a **generated**
file whose regeneration path is Track 1's. ⛔ The one-id-one-assertion trap is *not* violated by leaving
this alone — it is violated by **citing** an id on an assertion that cannot independently redden, and no
new citation is made by doing nothing.

⭐ **The generalisation: before folding any duplicate onto its twin, check what each twin CITES.**
PB-130's item 1 was deletable *precisely because* the duplicate carried **no** contract id and the
survivor carried `[G6-48]`. ⛔ Never generalise the consolidation past that condition.

## ⭐ Trap added 2026-09-08 14:4x — a SPENT-list entry inherits the scope of the run that produced it, exactly as a clearance does

PB-122 ran the capability-id contract instrument (ids declared vs ids cited in tests, minus honest
`UNRESOLVED` deferrals) against **X-167 alone** — 13 uncovered, 11 correctly deferred under rule 09,
2 neither. It was then written onto the addendum's SPENT list as *"X-167's capability-id contract gap"*,
and read by six subsequent ticks as though the lane had been measured. **Nine modules never were**, and
this tick found real material in one of them (X-165) by accident, while chasing something else.

⭐ **This is the `is_sample` clearance-scope failure in a second form**, and the two together give the
rule: **a SPENT entry and a CLEARANCE are the same object — a measurement with a scope, written down in
prose that drops the scope.** ⭐ Write the scope into the entry itself (*"X-167 ONLY — the other nine are
unmeasured"*), and when a method stops firing, ⛔ check whether it was ever run everywhere before
declaring it exhausted.

⚠️ **The distinction that keeps this honest:** re-applying an instrument to **unmeasured scope** is a
scope correction; **widening its criterion** so it fires again on scope it already cleared is the
documented failure. ⛔ If the re-application needs the definition loosened to find anything, it is the
second thing wearing the first thing's clothes.

## ⛔⛔ Trap added 2026-09-08 15:0x — a PERMISSION refusal can be caused by your own working directory, and the probe file tells you which

Writing PB-131's verdict failed twice: `Edit` on `REVIEWS.md` **and** `Write` on a brand-new name in the
same directory both returned *"requested permissions … but you haven't granted it yet"*. That is the
exact symptom of the 05:3x–06:5x mailbox lockout recorded above, and the pull to conclude "the deny glob
is back" was very strong.

⭐ **It was the tick's own doing, and the cause is new.** `.claude/settings.json` allows
`Edit(.agents/supervisor/**)` and `Write(.agents/supervisor/**)` as **relative** patterns, with **no**
deny rule matching that path — established by reading the file end to end rather than inferring a rule
from the refusal. Earlier in the tick a measurement ran `cd …/app && …`, and the harness moved the
primary working directory to `…/grs-antig-pricebook/app`. **From there the relative pattern no longer
matches the mailbox**, so every write fell through to *ask*. One `cd` back to the checkout root plus a
probe write, and it was fixed.

⭐⭐ **The generalisation: before theorising about a permission refusal, establish the state the
permission set is resolved AGAINST — and the working directory is part of that state.** This is the
06:5x lesson ("establish what is actually per-lane before theorising") on a new surface; three ticks
have now built a confident wrong model from this one symptom.

⭐ **The discriminator costs one command and separates the two causes exactly:** write a probe file to
another name in the same directory. **Probe writes + `REVIEWS.md` refused = the deny glob** (file a
`TRACK 1 ACTION` and use `-NEXT`). **Probe ALSO refused = check your CWD first.** ⚠️ ⛔ This does **not**
retro-explain the 05:3x lockout — there the probe *wrote* while `REVIEWS.md` was refused, which a CWD
change cannot produce since both share the matched directory.

⛔ **Practical rule: prefer absolute paths in Bash and never leave the CWD parked outside the checkout
root.** The harness prints an environment-update block when the working directory moves — ⭐ that block
is the warning, and it was ignored for six calls.

⭐ **And the line held while the cause was unknown** — no `sed` redirect, no
`git show <sha>:<path> > <path>`, no self-granting; the tick was preparing to file upward and dispatch
through `BRIEF-NEXT.md`. ⛔ **That refusal stays absolute however mundane the cause turns out to be.**
Discovering afterwards that the door was only stuck is not a licence to have forced it.

## ⭐ Trap added 2026-09-08 15:0x — a per-row output format destroys a finding whose subject is a relationship BETWEEN rows

PB-131's brief asked for the capability-id sweep as **one row per module**, and got nine exact rows. The
real finding is invisible in that shape: the nine scaffold ids `N-063 N-064 N-067 N-070 N-073 N-076
N-079 N-082 N-085` are **the same nine, declared in X-165, X-168 AND X-175 at once**, at the same line
numbers, carrying the same byte-identical string. The report could only say "9 of 12", "9 of 11",
"9 of 12" — three per-module facts where there is one three-module fact.

⭐ **It matters because it converts a vague filing into a mechanism.** `capabilities.php` fails the build
for a declared id with no matching test under `tests/Modules/<id>/`, so an id declared by three modules
**requires three separate test files to each carry a test citing it** — the byte-identical duplicates
PB-130 found are the checker's *requirement*, not carelessness, and no lane-side deletion can help.

⭐ **The generalisation: when an instrument's finding might be a relationship between rows, ask for the
union and the intersection, not just the per-row counts.** ⚠️ And the companion, from the same tick:
`N-165-01` / `N-175-01` are **three-part** ids that both `'[A-Z0-9]+-[0-9]+'` and `N-0[0-9][0-9]` drop,
which made the reviewer's own count disagree with the coder's by one on two modules. ⛔ **Never
reconcile a count discrepancy by assuming your own regex was right** — chasing it is what confirmed both
ids were covered.

## ⭐⭐ Trap added 2026-09-08 15:0x — WRITE-SIDE REACHABILITY, and the discriminator between a defect and a new capability

The successor instrument after the capability-id contract sweep came back empty lane-wide. It is
decision 272's shape **inverted** — `audit_log` had eight writers and no reader; this finds the reverse:
**for every table a lane's screens and actions READ, name the production path that WRITES it.**

Measured on X-168, and it fired on the item the board had carried as "recorded, not work" for four
waves. The only writers of `timesheets` / `timesheet_entries` in all of `app/app/Modules` are
`TimesheetComputeAction.php:41` and `:51`, and that action has **zero production callers** — all 15
references are the class plus six test files. Meanwhile **five readers** depend on those tables
(`TimesheetApproveAction:13`, `TimesheetReopenAction:13`, `ApprovalsView:44`, `TimesheetsView:42,:50`,
`OwnHoursView:36,:41`). **All three of X-168's owner/staff screens read tables nothing in production
writes**; the module's whole surface is unreachable outside the suite. And the producers already exist
in this lane speaking the exact vocabulary `recordJobWindow()` accepts —
`X-171/Actions/JobStateAction.php:19` (`on_site`), `:29` (`completed`),
`X-162/Actions/TechEnRouteAction.php:28` (`en_route`) — simply not connected.

⛔⛔ **The discriminator against PB-128, and it is the reusable half.** PB-128 ruled X-167's missing
listener a **new capability** because `capabilities.php` declared no automatic proposal, so the fix
there was the *sentence*. **Here the capability IS declared** — X-168 `capabilities.php:37` (`N-072`),
*"GPS clock-in is bound to job state"*. ⭐ **Declared and unwired is a defect; undeclared and unwired is
a capability. Read `capabilities.php` before choosing between them.**

⛔ **Wiring it is still not a one-wave job, and the reasons are the standing ones.** `recordJobWindow()`
has **no close path** (it always `create()`s); there is **no dedup**, and `tap_count` implies taps
repeat — verbatim PB-128's *"the obvious listener mints a fresh PO each time"*, the third incarnation
here of **stricter is not automatically safe**; and wiring only the arrival leg leaves every entry at
`duration_minutes = 0`, filling three screens with **zero-hour rows, which is worse than empty**.
⛔ **Nor is pinning the absence right here**, unlike PB-128's ruling 3: there the absence was permanent,
so the pin was the deliverable; here the declared end state is *wired*, so a pinned test is one a later
wave must delete. **Record, measure, then build.**

## ⛔⛔ Trap added 2026-09-08 15:5x — a FORMATTER run from the wrong directory rewrote 8 sealed files, and no gate in any lane can see it

`git status --porcelain` in this checkout carries **24 ` M ` lines and 22 `??` lines**. Board item 14
described *"18 scratch files … plus the uncommitted `.claude/settings.json`"* — it enumerated the
**untracked** lines and silently dropped the **modified** ones, and was read as describing the whole
output for at least six waves. ⭐ **The clearance-scope failure, this time in the board's own
bookkeeping about a command every tick runs.**

**What the 23 modified tracked files are, measured:** all stamped `13:10:02–13:10:04` — one ~3-second
sweep — and every hunk is **pint formatting**. All eight `app/app/Doctor/**` diffs read in full
(docblock `@param` alignment, `if` brace expansion, blank line removed before `return`, one `use`
hoisted out of a docblock FQN); `DoctorCommand.php`'s 54/53 classified by line shape in one command
(7× comment re-indentation, an import re-sort, array alignment); `plugins/wordpress/uninstall.php`
unambiguous — tabs→spaces, `. `→`.`, brace-on-next-line, `@package` stripped. **No predicate, no regex,
no assertion, no count changed.** ⭐ Behaviour-preserving, so ⛔ **not a `BLOCK` and not a weakened
CHECK** — no number this lane has quoted is wrong.

⭐⭐ **The mechanism is the finding.** `app/pint.json` **excludes `app/Doctor`** and `notPath`s the three
Doctor console commands — *those exclusions are the thing protecting the sealed path from formatter
churn*. `bin/supervise.sh:175` does `cd "$APP"` before `./vendor/bin/pint --test`, so the gate honours
them. The 13:10 sweep hit **all three excluded groups plus `plugins/`**, which is outside `app/`
entirely; the only way to do that is to run pint **from the repo root**, where `app/pint.json` is never
discovered and the bare Laravel preset applies with no exclusions at all.
⭐ **The generalisation: a protection expressed as a relative path is only as good as the cwd it is
resolved from.** Identical in shape to this lane's 15:0x CWD permission trap — **second surface, same
day**. ⛔ Always run pint from `app/`, never from the repo root, and put that line in every brief.

⚠️ **Consequence: this is invisible to every gate in every lane, in BOTH directions.** pint skips 11 of
the 23 by config and the other 12 by scope, so the reformat is neither what makes `pint --test` pass nor
something reverting would make fail. ⛔ No count instrument, no `-` line reading, no `--numstat`
reconciliation reaches it — only reading `git status` line by line does.

⚠️⚠️ **The live hazard, and why the standing brief line is load-bearing rather than hygiene: the tree is
one `git commit -a` away from putting a sealed-file diff into this lane's history, and it would arrive
with a green gate and a clean pint.** *"Named paths, `git commit -m … -- <paths>`, never `-a`, never
`add -A`"* is the only thing standing between this working tree and a One Rule `BLOCK`.

## ⛔⛔ CORRECTED 2026-09-09 04:3x (PB-148) — the two ⛔ sentences above were WRONG, and a clearance that measured SEMANTICS could never have cleared a check that grades on BYTES

⛔ **The entry above said this was invisible to every gate and that ⛔ *"this lane neither commits nor
reverts them — zero of the 23 are ours."* Both are false, and the second one cost four waves.**

**It was visible the whole time, in `supervise.sh` §4.** `pb147-gate-1.log:138-148` prints
`1 problem(s) IN THE RUNTIME ITSELF` and `⛔ SEAL/integrity red`; `pb148-gate-1.log:130-138` prints
`✓ seals every sealed file matches seals.json` and `All stages clean`. **The difference between those
two runs is one thing: the eight sealed `app/app/Doctor/**` files were restored to HEAD.** The 13:10
sweep is what broke `seals.json`, and this lane wrote **"SEAL/integrity red STANDING"** into PB-144,
PB-145, PB-146 and PB-147 without once asking its cause.

⭐⭐ **Why the original clearance could not have caught it, and this is the durable part.** That entry
read all eight sealed diffs in full, classified every hunk, and concluded — **correctly** — *"no
predicate, no regex, no assertion, no count changed … behaviour-preserving, so ⛔ not a `BLOCK` and not
a weakened CHECK."* Every word is true. **`seals.json` hashes bytes.** The clearance answered a question
about **meaning** while the gate was failing a question about **content**, in the same run, and the two
were never put side by side.

⭐ **This is a NEW member of a family this file documents three times — but the others were clearances
whose SCOPE was dropped by prose (`is_sample` on X-166, the SPENT entry for X-167, ruling 8's stale
`UNRESOLVED`). This one's scope was stated correctly and its DIMENSION was wrong.** ⭐ Before writing
"not a weakened CHECK" about any file a checker hashes, seals or diffs verbatim, **ask what the checker
compares.** ⛔ "Behaviour-preserving" is no defence against an integrity hash, and no formatter's output
is byte-preserving.

⭐ **And the ⛔ was the expensive half: reverting them WAS the fix.** The hazard this very entry named —
*"one `git commit -a` away from putting a sealed-file diff into this lane's history"* — is now gone,
because there is no sealed-file diff left to commit. A coder disobeying that ⛔ is what closed a day-old
red. ⚠️ It reached the restore by `git show HEAD:<path> > <path>` after the guard refused
`git checkout`/`git restore` — **the contract-prescribed route** (rulings 26/27, PB-119 precedent), with
nothing sealed in the commit and the seal check itself as the witness that the bytes are canonical.

⛔ **What still stands:** the twelve **WordPress plugin files** remain ` M ` from the same sweep, are
**not** sealed (the gate now says every sealed file matches), and this lane neither commits nor reverts
them — `TRACK 1 ACTION (e)`, narrowed to those twelve. ⛔ And the root cause is untouched: **pint run
from the repo root does not discover `app/pint.json` and reformats every path its exclusions protect.**
Always run pint from `app/`.

## ⭐⭐ Trap added 2026-09-08 15:5x — a verdict vocabulary you DEFINE but only partly GRADE delivers its strongest row correctly reported and unread

PB-132's brief defined three verdicts — `REACHABLE` / `TEST-ONLY` / `NO WRITER` — and then named only
`TEST-ONLY` as the graded column. The sweep returned **21 / 2 / 3**. ⭐ **`NO WRITER` is strictly
stronger than `TEST-ONLY`**: a table read in production that nothing writes *anywhere, not even a test*.
Three such rows arrived perfectly reported and ungraded, and one of them was in this lane's **core
module, on the goal path** — `location_books`, read at `PricebookEngine:153` (`firstOrFail`, so it
**always throws** in production) and `Ui/Pricebook:202`, whose `'firstLocation' => $locations->first()`
is therefore **always `null`, for every tenant, forever.**

⭐ **The generalisation: grade every value of a vocabulary you define, or the instrument's sharpest
output is the one nobody reads.** PB-128's ungraded-rows failure in a new form — there the exceptions
were unreachable behind a blanket header, here a whole column was simply never named.

⛔ **And the discriminator then DISARMED it, which is the other half of the lesson.**
`grep -rn "location\|Location" app/app/Modules/X-163/capabilities.php` → **zero hits**. Undeclared and
unwired is a **capability**, not a defect (week-2 build work under ruling 24). And
`pricebook.blade.php:109` is `collect($locations)->first() ? …->version : 1` — an explicit null-guard
with a sensible default — with `:135`'s `@foreach` rendering nothing on an empty collection. **No wrong
value reaches anyone: latent + no wrong value = RECORD.** ⛔ Using "latent" to justify a wave here would
be exactly the misreading this file already warns about. ⭐ **An instrument that fires and is then
disarmed by the discriminator is the pair working, not the instrument failing** — record both halves,
because the next tick will re-find the same row and needs the ruling, not the finding.

## ⭐⭐ Ruling — 2026-09-08 15:5x — the X-168 window is an OPEN/CLOSE PAIR, and the close path lands ALONE

⛔ **Read the action before designing the seam; it moved the question.** The board recorded
*"`recordJobWindow()` has no close path — it always `create()`s"*, which is true and not the sharp fact.
The sharp fact is that it takes **`$startedAt` AND `$endedAt` in one call** (`:21-30`) and derives
`duration_minutes` from both (`:46-49`) — a **whole-window recorder**. So there were two real designs:
**(a)** an open/close pair, or **(b)** keep the whole-window signature and let the listener hold the
pairing until `completed`.

**RULED (a), because (b) has nowhere to remember the open time.** `JobStateAction` keeps no state and
dispatches unconditionally; this lane owns no timestamped job-state store; `work_orders` is X-121's and
**ruling 8 forbids writing it**. (a) stores the open time in the row itself, in `timesheet_entries`,
which X-168 owns. Two measurements agree: `N-072` says *"GPS **clock-in** is bound to job state"* and
the anchor at `:18` says *"only inside an **active** job-state window"* — **an active window is a row
with `ended_at = null`**, which (b) never has. And (a) is the only design under which the repeated
`on_site` tap (measured: unconditional, no guard, no disabled button) is **detectable at all**.

⛔⛔ **The close path lands ALONE — no listener, no producer wiring.** It is a **precondition**: wiring
the arrival leg first is precisely the "zero-hour rows, worse than empty" hazard. It is testable in
isolation today by calling the action directly. And it **cannot regress anything**, because nothing
calls `recordJobWindow` in production — which turns the risky part into a later one-line registration
against a seam already proven. ⚠️ The zero-hour hazard is thereby **scoped, not waved away**: under (a)
an open row genuinely has 0 minutes *so far*, which is true rather than wrong.

⛔ **Pre-declared wrong fixes, both of which read as correct:** `closeJobWindow` **creating** a row when
it finds no open one (a stray `completed` mints a zero-length entry — the very outcome the design
prevents, arriving through the fix); and closing **every** open entry for the person rather than the one
for that `job_id`.

## ⛔⛔ Trap added 2026-09-08 16:2x — a brief that pre-declares *N* WRONG FIXES owes *N* MUTATIONS

The ruling directly above named **two** wrong fixes. PB-133's brief then specified **one** mutation
(`closeJobWindow` returns `null` unconditionally). It ran clean and reddened exactly the one test
predicted — which also proved, per PB-129, that the new test duplicated nothing.

⛔ **But that mutation cannot touch the regression arm, because the regression arm asserts `null`.** The
brief called that arm *"the one the wave exists to protect"* and then handed it a mutation that makes
it pass **more easily**. Its real mutation is wrong fix #1, and it was never run.

⭐⭐ **Working that through found the actual hole, and it is wrong fix #2.** Delete
`->where('job_id', $jobId)` from `TimesheetComputeAction.php:71` and trace all four X-168 tests: the
defect arm has one open entry so it is still found; both legs of the regression arm have no open entry
so both still return `null`; the anchor test never calls the method. **Every one stays green.** The
predicate deciding **which technician's window gets stamped** — the clause the brief itself flagged as
dangerous — is asserted by nothing.

⛔ **Ruled a NOTE, not a `BLOCK` and not the coder's shortfall.** The code is *correct*: the filter is
there and is right. Nothing was weakened, no assertion deleted, no CHECK changed. The coder built what
was specified and ran the mutation it was given. **Twelfth consecutive wave whose only shortfall traced
to the brief.**

⭐ **The generalisation: a pre-declared wrong fix is a claim that one specific line-level change is
dangerous. Declaring it and never mutating it is asserting a risk and then not measuring it.** Count
the hazards a brief names, count the mutations it specifies, and require either equality or an explicit
sentence saying which hazard is being left unproven and why.

⛔ **Distinct from the PB-125 insertion reconciliation, and the distinction is the useful part.** That
one compares a **diff** to a brief, so it structurally cannot see what the brief failed to ask for
(PB-129's hole). This one compares a brief's **mutation budget** to the brief's **own** hazard list —
**both numbers are written in the same document before the wave starts**, so it is checkable at
briefing time, costs nothing, and has already been wrong once.

⭐ Consequence, applied as PB-134: **pin the unpinned clause before wiring a producer to it.** Same
reasoning that made the close path a precondition — *never wire a producer to a seam whose defining
predicate no test can falsify*. The listener moved from PB-134 to PB-135 to buy that.

## ⚠️ Trap added 2026-09-08 16:2x — "phpstan passed" is not evidence about nullable flow

Measured while clearing a PB-133 candidate: `closeJobWindow:88` does `Timesheet::find($entry->timesheet_id)`
— a `?Timesheet` — and passes it straight into `updateTotalHoursAndDispatch(Timesheet $timesheet)` at
`:94`. **phpstan returned `{"result":"passed","errors":0}`** on that call, so this checkout's Larastan
level does not check nullable-argument flow.

⛔ The finding itself is **latent + no wrong value = RECORD** and is not work: `timesheet_id` is a
foreign key and the only writer of `TimesheetEntry` is `recordJobWindow`, which creates the parent
first, so no defined input reaches it. ⭐ **The durable half is the instrument's limit** — a green
phpstan in this repo says nothing about a nullable reaching a non-nullable parameter, and a future
review must not quote it as though it did.

## ⭐ Ruling 33 — 2026-09-08 17:0x — one open job window per job, and REFUSE rather than SUPERSEDE

PB-134's measure-only item found that two open `timesheet_entries` on one `job_id` leave the older
one open **indefinitely** at `duration_minutes = 0` — `closeJobWindow` closes only the newest
(`latest('id')->first()`). Reading `recordJobWindow:32` turned that into a constraint: the row is
created **unconditionally**, and both `en_route` and `on_site` open one. So the day a listener is
wired, the first repeat tap puts a permanently-open zero-hour row onto all three X-168 screens.

**RULED: a `recordJobWindow` call for a job that already has an entry with `ended_at IS NULL` returns
the existing entry and writes nothing.**

- ⛔ **Refuse, not supersede.** Closing the old window on a new tap stamps an `ended_at` the
  technician never generated and **invents a duration** that lands in `total_hours`. **Fourth
  incarnation in this lane of *stricter/richer is always safe* being false** — after PB-128's
  auto-proposing listener, PB-119's `status === 'refused'` and PB-120's unconditional refusal.
- ⛔ **Return the existing entry, not `null`.** `null` already means *"invalid input, wrote nothing"*
  at `:34`; returning it for an idempotent re-tap would be a lie about what happened. ⚠️ The return
  then cannot distinguish *created* from *already open* — **measured, no caller needs that**, so it
  is recorded rather than fixed with a status vocabulary nobody consumes (that would be briefing a
  lead).
- ⛔ **`en_route` stays unwired.** Whether travel time counts toward a technician's paid hours is a
  business rule nobody has stated, and guessing puts a wrong number on the owner's approval screen.
- ⛔ **The guard lands ALONE, before the listener** — third application of *never wire a producer to a
  seam whose safety guard is not built and proven* (PB-133's close path, PB-134's scope pin). It is
  testable by direct call and cannot regress anything, since `recordJobWindow` has no production
  caller.

## ⭐ Trap added 2026-09-08 17:0x — a measure-only item must name WHERE ITS EVIDENCE LANDS

PB-127 established that a clean sweep's **count** is its only auditable part; PB-128 sharpened that to
**falsifiable clauses** (name a file, a line or a query a reviewer can open). PB-134 adds the third
leg, and it is the one nobody had written down.

Item 2's answer was **correct** and carried a falsifiable clause — it named
`->latest('id')->first()`. But its evidence was a scratch test the coder **correctly cleaned up**, so
nothing on disk backed it and the measurement had to be re-derived from the query. ⚠️ The brief had
told the coder to leave nothing behind and had never said where the evidence should live; the two
instructions quietly conflicted.

⭐ **The rule: require a `file:line` on every claim, and say explicitly ⛔ do not back it with a
scratch test.** A `file:line` survives cleanup; a deleted file does not. ⚠️ Note the shape — this is
not a coder failure and grading it as one would be the documented mistake; it is the third time a
disclosure requirement has needed narrowing after being satisfied *literally* and unhelpfully.

## ⭐ Trap added 2026-09-08 17:0x — a hunk header ending `@@ -N,4 +N,K @@` is a one-glance proof of a pure append

PB-125's insertion reconciliation exists because an insertion-only diff can still silently rewrite
lines inside an existing method. ⭐ **When the diff's only hunk header is at EOF with a small
unchanged context count — `@@ -180,4 +180,51 @@` — every added line is an append and no pre-existing
method was entered**, so the PB-125 hazard cannot arise and the auth-assertion grep is unnecessary.

⚠️ **It proves the shape, not the content.** A pure append of 47 lines still has to be reconciled
against the deliverable (here: one test, one method, 47 lines — exact). ⛔ Do not read "pure append"
as "inert"; read it as "the only thing to grade is what was added."

## ⭐⭐ Trap added 2026-09-08 17:4x — a mutation can redden a neighbour through its FIXTURE, and that is COUPLING, not duplication

PB-129 gave this lane its duplicate-detector: *a mutation that reddens an unbriefed test means the brief
asked for something the file already had.* PB-135 is the wave where it fired and the answer was the
opposite.

W2 (drop `->where('job_id', …)` from the new guard's query) reddened T2 **and** PB-134's
`test_close_job_window_pins_job_id_and_does_not_close_others`. Measured: that test opens **two windows
on two jobs in its SETUP**. A guard with no `job_id` scope hands back the first entry for the second
job, so the second job never gets a window and `closeJobWindow(8802)` finds nothing to close.

⭐⭐ **The discriminator is the FAILING ASSERTION, not the test name.** The neighbour failed on
`assertNull` receiving a Carbon — *its own subject, starved of its fixture*. T2 failed on
`51 is not equal to 51`, an **id equality** — a different subject entirely. Two tests reddened, one
mechanism each, no duplication anywhere.

⛔ **Folding T2 onto the neighbour on the strength of the co-firing** — the PB-130 consolidation shape —
**would have deleted the regression arm the wave exists for.** ⭐ The general rule: the duplicate
detector's premise is that the other test asserts the **same subject**. Read the assertion before
concluding it does.

## ⛔⛔ Trap added 2026-09-08 17:4x — ask for the id SPACE, never the field NAME

PB-135's item 4 asked, of each producer event, *does the payload carry a business id, a person id, a job
id and a timestamp* — the four things a listener needs. The answer came back correctly and **inverted on
both rows that mattered**, because presence and identity are different questions:

- `timesheets.person_id` is a **USER id** — `X-168/Ui/ApprovalsView.php:60` resolves it with
  `User::whereIn('id', $ids)`, and `X-171/Ui/StafffacingApp.php:47`/`:155` set `$techId = auth()->id()`.
  ⭐ So `TechOnSite->techId` **is** the right value for `recordJobWindow(personId:)`. The report's
  `No (techId)` understated the payload.
- ⛔⛔ `JobCompleted->personId` is the **CUSTOMER**. `X-171/Actions/JobStateAction.php:30` reads it from
  `work_orders.person_id`, which is
  `X-121/…/2026_09_03_055945_add_person_id_to_work_orders.php:13` →
  `foreignId('person_id')->constrained('people')`. A listener taking it as the timesheet's person files
  a technician's hours under a customer's `people` id, resolved against `User`. **The `Yes` was the most
  dangerous cell in the table.**

⭐ **The rule: name the `constrained('…')` target, or the model the reader resolves against.** Two
columns called `person_id` in one repo can point at two different tables, and a field-name audit reports
them identical. ⚠️ Latent here — `closeJobWindow` takes no `personId` — so **latent + no wrong value =
RECORD**, written into the next brief so the listener cannot walk into it.

## ⛔ Trap added 2026-09-08 17:4x — quoting the floor line is not reconciling it, and a prediction that keeps being right stops being read

PB-133 made the gate's floor line an explicit report deliverable, and it held perfectly for three waves.
PB-135 quoted `tests 2112 · passed 2107 · FAILED 1 · errors 4` verbatim and correctly — and said nothing
about `errors` having risen `2 → 4`, which is this lane's own blocker condition (*a count that rose
without a report line saying why*).

⭐ **The cause was the brief, and it is a shape worth naming: a prediction that has been right eleven
waves running becomes a check nobody performs.** The brief demanded the line *verbatim* and never
demanded a **comparison**. The reviewer caught it only by holding the two numbers side by side.

⭐ **Fix, standing from PB-136: the report reconciles all four numbers — `tests`, `passed`, `FAILED`,
`errors` — against the predicted floor and names the cause of every difference, quoting the gate line
that supplies it.** ⛔ Its absence is a note, not a re-dispatch: PB-123's artifact-over-prose rule
applies, and here the gate's own failure list named the cause in two lines (J10's Authorize.Net `E00040`
returning after eleven clean waves — external, and `TRACK 1 ACTION (c)` reopened).

⚠️ Companion, recorded because this lane had been reading the quiet the other way: **eleven clean waves
were evidence FOR the diagnosis, never evidence the problem had left.** The file already said so; the
wave proved it.

## ⛔ Trap added 2026-09-08 17:4x — `state.py` ran, the artifact proves it, and the commit never happened

PB-135's ruling was recorded on disk and **not in the history Track 1 merges**:
`.agents/state/BUILD-STATE.json` and `JOURNAL.md` sat ` M ` at review time. ⭐ **The PB-120 triple
proves the tool ran rather than a hand edit** — `updated 16:35:19 → 17:12:18`, exactly one decision
object appended, exactly one `JOURNAL.md` line, `at` matching **to the second**, with `plan_sha256`,
`roster` and `runtime_build` untouched. ⛔ Not a `BLOCK`: nothing weakened, no CHECK changed.

⭐ **But `git status --porcelain` is the only instrument that sees it** — `git show` on the wave's commit
is clean and complete, and every count reconciles. ⚠️ **An empty diff is not an empty tree, and a
complete diff is not a complete wave.** Run both, every wave.

⭐ **And the push consequence, which is NOT PB-125's:** PB-125 held a push because the next wave had to
**undo** part of the gated diff. Here nothing is undone — the guard is correct and stays, the state
commit is purely **additive**, and the uncommitted delta was verified **field by field** inert against
every gate instrument (PB-124's test). **Push, and make the state commit item 0 of the next wave.**

## ⛔⛔ Trap added 2026-09-08 18:4x — this lane's own briefs told the coder to PIPE THE GATE, for at least six waves

`bash bin/supervise.sh --tests | tee .agents/supervisor/pbNNN-gate.log` is what PB-137's brief — and
several before it — specified in those words. ⚠️ **This file already records why that is wrong**, in the
18:4x §6 entry: *a pipeline's status is its last command's*, which is exactly how a pint red was printed
and then forgotten by `supervise.sh`'s own verdict for the life of the file. Teeing hands the coder's
shell **`tee`'s** rc, not the gate's.

⭐ **It has never cost anything, and that is the hazard, not the defence.** The gate prints its own
`tests … · rc N` verdict line, so a coder who reads the log recovers everything the lost rc would have
told it. **Every wave that has "passed" this way passed on the coder's reading habit, not on an
instrument.** The day a gate dies before printing its verdict — a `timeout` rc 124, a SIGKILL — the
shell reports success and the log's tail is silent, which is the `ZERO BYTES` case the owner's own
hunk was added for.

⭐ **Standing from PB-138: `bash bin/supervise.sh --tests > .agents/supervisor/pbNNN-gate.log 2>&1`,
then read the log.** ⛔ No pipe on any gate tool, ever — not `tee`, not `tail`, not `grep`.

⚠️ ⭐ **The general lesson is about where a rule lives.** The §6 finding was written down as a *bug in
`supervise.sh`* and fixed there; nobody asked whether the same shape had been **written into the briefs
that drive the tool**. ⛔ When you fix a defect in an instrument, grep your own standing instructions
for the same shape before closing it.

## ⭐ Trap added 2026-09-08 18:4x — a disclosure rule that is ambiguous gets graded on the coder's READING, and the disclosing reading must always win

PB-120 narrowed `REFUSED` to *"names what was refused AND left undone."* PB-137's report listed two git
commands (`git checkout --`, `git reset --hard`) that the guard refused and that never succeeded, while
their **goal** — restoring a mutated file — was reached by hand. Both readings of the rule are literal:
the **commands** were left undone, the **item** was not.

⭐ **The coder took the reading that discloses more, and appended a Correction paragraph.** That is why
the whole thing cost one `git status --porcelain` (no ` M ` under `app/` ⟹ the hand-restore is
byte-exact) instead of a tick proving a negative.

⛔⛔ **Grading that as a shortfall would have been the expensive mistake**, and it is the same family as
PB-121's STOP condition and PB-126's scope-respecting wave: **penalising a coder for disclosing under an
ambiguous rule trains it to disclose less, which destroys the signal the rule exists to produce.**

⭐ **The fix is the vocabulary, not the verdict: `GUARD REFUSALS` for a refused command whose goal was
reached another way; `REFUSED` for a brief item left undone.** ⚠️ Third time a disclosure requirement in
this lane has needed narrowing after being satisfied **literally and unhelpfully** (after PB-127's count
of `1` and PB-128's twenty-two identical rejection clauses). ⭐ The recurring tell is the same each time:
**the requirement could be satisfied without producing the thing it was asked for.**

⭐ Recorded with it, same wave, same direction: my *"one line per `-` line"* got six bullets for
seventeen deletions — **the deletion ledger BY SHAPE that PB-126 already established is the stronger
form.** ⛔ Stop asking for the weaker one.

## ⭐⭐ Trap added 2026-09-08 18:4x — whether a new event field is NULLABLE is decided by the CONSTRUCTION SITES, not by the consumer

PB-136 gave `TechOnSite` a **non-nullable** `CarbonInterface $occurredAt`. PB-138 gives `JobCompleted` a
**nullable** one. ⭐ **Same rule, opposite answers, and the rule is the durable part:** count the
construction sites and ask whether **every one** of them has a truthful value.

| event | sites | truthful? | ⟹ |
| :--- | :--- | :--- | :--- |
| `TechOnSite` | **1** — `JobStateAction:21` | ✅ the tap is happening now | non-nullable, no default |
| `JobCompleted` | **2** — `JobStateAction:34` live, `ReplayOfflineSyncAction:86` replay | ⛔ the replay's jsonb `$payload` carries no occurrence time | `?CarbonInterface … = null` |

⛔⛔ **The wrong fix reads as the obvious one and is the fifth incarnation in this lane of *richer is
always safe* being false:** defaulting the replay site to `Carbon::now()`. That stamps **the sync
moment** on every window a technician closes after working offline — `duration_minutes` →
`updateTotalHoursAndDispatch` → the owner's approval screen. ⚠️ A wrong number on a reported dimension,
on a defined input, through a real production path. **Not latent.**

⛔ **And do not resolve it by reading `$payload['occurred_at']`.** Nothing in the repo writes that key;
reading one nothing writes invents a device contract on a guess. ⭐ **Recording the absence is the
deliverable** — the test that asserts `occurredAt === null` on the replay path is precisely what goes
red the day someone "helpfully" defaults it.

⭐ **A second, independent reason forced the same shape, and it is worth looking for:** six
`new JobCompleted(...)` calls live in **track reviews'** `CReviewsTest.php`, so a non-defaulted
parameter would have compelled edits outside this lane. **When scope and truth force the same design,
the design is probably right** — but check that they really are two reasons and not one restated.

## ⛔ Trap added 2026-09-08 18:4x — a precondition wave's real question is whether the COUNTERPART leg can complete the state it creates

PB-137's brief pre-announced *"the listener is PB-138"*, and PB-137 landed the guard perfectly. ⛔ The
listener was still not safe, and the reason had nothing to do with the guard.

`recordJobWindow`'s producer (`TechOnSite`) is time-safe after PB-136. `closeJobWindow`'s producer
(`JobCompleted`) is not. ⭐ **Wiring the open leg alone is this lane's own "zero-hour rows, worse than
empty" hazard in its PERMANENT form — rows nothing can ever close** — so the two legs must land
together, which makes the close leg's precondition strictly prior to both.

⭐ **The generalisation, and it extends the doctrine this lane already has three applications of
(PB-133's close path, PB-134's scope pin, ruling 33's guard):** *never wire a producer to a seam whose
safety guard is not built and proven* is not sufficient. Add: ⭐ **never wire a producer to a seam whose
COUNTERPART cannot complete the state it creates.** A half-wired open/close pair does not degrade to
"less data" — it degrades to a screen full of rows that are permanently, visibly wrong.

⚠️ ⭐ **And a brief's own forward-declaration is not evidence.** *"The listener is PB-138"* had been
written down and was inherited as settled; it took one `grep` of the two construction sites to overturn
it. ⛔ Re-measure a deferred wave's premise at the moment you brief it, never at the moment you deferred
it.

## ⚠️ Recorded 2026-09-08 18:4x — the new X-168 guard does not scope on `state_window`, and PB-139 must read this first

The PB-137 guard matches on `(business_id, timesheet_id, job_id, ended_at IS NULL)`. `recordJobWindow`
accepts **both** `en_route` and `on_site` at `:32`. So an `en_route` tap followed by `on_site` on the
same job returns the **`en_route`** entry, and the arrival is swallowed — the window's `state_window`
reads `en_route` forever.

⭐ Under ruling 33 that is **refuse-not-supersede working as designed**, and `en_route` has no producer
wired, so it is **latent + no wrong value = RECORD**. ⛔ Not work, and ⛔ not a defect in PB-137.

⚠️ **But it becomes live the instant a listener subscribes `en_route`**, and `X-162/Actions/TechEnRouteAction.php:28`
is a real producer sitting there. ⛔ PB-139 subscribes **`on_site` only** (ruling 33: whether travel
time is paid is a business rule nobody has stated), and re-reads this paragraph before it does.

## ⛔⛔ Ruling 34 — 2026-09-08 18:5x — the same `null` means two different things on either side of a seam, and the split goes at the CALL SITE

PB-138 gave `JobCompleted` a nullable `occurredAt` so the offline replay could say *"I do not know when
this happened"* instead of inventing a time. Measured one wave later, before briefing the listener:

```
TimesheetComputeAction.php:91      $endedAt = $endedAt ?? Carbon::now();
```

⭐⭐ **`closeJobWindow`'s `null` means "the caller did not specify — use now". The event's `null` means
"the caller knows it does not know". They are the same value.** So the obvious listener —
`closeJobWindow($e->businessId, $e->jobId, $e->occurredAt)` — hands the second meaning to a seam that
reads the first and **stamps the sync moment on every replayed completion**: `duration_minutes` →
`total_hours` → the owner's approval screen.

⭐ **Third incarnation of PB-120's overloaded-parameter shape, and the sharpest: the overload is not
inside one module, it is ACROSS THE SEAM BETWEEN TWO, so neither side reads wrong on its own.** ⛔ That
is why the previous two incarnations were caught by reading one file and this one was not — it is only
visible with the producer's declaration and the consumer's default on screen at the same time.

**RULED: the split happens at the CALL SITE, not in the signature.** `closeJobWindow`'s `?? now()`
default is **correct for its own contract** and is untouched — a caller who omits the argument does mean
"now". The listener is the only place that knows which event it received, so a `JobCompleted` with
`occurredAt === null` **does not call `closeJobWindow` at all.**

⛔ **The counter-hazard is accepted, not waved.** A refused close leaves that window open at
`duration_minutes = 0` and, for a replayed completion, **nothing will ever close it** — the replay is
the only completion signal that job will get. Ruled acceptable because `total_hours` is what the owner
approves and eventually pays: **inventing a duration is inventing money**, and an open row is *true so
far* while a window closed eight hours after the work ended is false and looks exactly like a real one.
⭐ Same judgement as PB-128's Reorders screen — **a wrong number that looks like data is worse than a
visible gap.**

⭐ **And there is no third option, measured so nobody re-derives it:** `device_sync_queue`
(`…000061_create_x171_sync_tables.php:15-25`) carries only `business_id`, `client_mutation_id`,
`device_id`, `action_name`, `payload` (jsonb), `version`, `status` and Laravel `timestamps()` — **every
time on that row is written at replay.** The payload's only keys read anywhere are `job_id` and
`tech_id` (`ReplayOfflineSyncAction:84`, `:86`). **There is no truthful end time in this checkout**,
which is what makes the refusal honest rather than lazy.

⚠️ **The argument-order trap that rides alongside it, recorded because phpstan cannot see it:**
`recordJobWindow(int $businessId, int $personId, ?int $jobId, …)` puts **`$personId` second and `$jobId`
third**, and `TechOnSite(businessId, jobId, techId, occurredAt)` presents them in the **opposite**
order. Both are `int`. A listener written left-to-right off the event files a technician's hours under
a job id read as a person id. ⭐ **Named arguments are the guard**, and the swap is PB-139's W2.

## ⭐⭐ Trap added 2026-09-08 18:5x — a mutation's RED is evidence only if that mutation and nothing else could produce it

PB-138's W2 — pass `Carbon::now()` at the replay's dispatch site — reddened its test with
**`The expected [App\Modules\X171\Events\JobCompleted] event was not dispatched.`** The coder quoted it
faithfully; it is the only message Laravel produces. ⛔ **And it proves less than it looks like.**

`Event::assertDispatched($class, $callback)` prints that identical string in **two** situations: the
event never fired at all, **and** it fired but no instance satisfied the callback. So the RED cannot
distinguish *"`occurredAt` came back as `now()` instead of `null`"* — the defect the test exists for —
from *"the replay path broke entirely"*. Compare W1's RED on the same wave,
`Call to a member function equalTo() on null`, which **only** a null `occurredAt` can produce.

⭐ **The generalisation: when a brief pre-declares a mutation, ask what ELSE could print the RED you are
expecting.** A value comparison (`ended_at` non-null where null was asserted, `duration_minutes` of `0`
where `90` was asserted) is producible by the mutation and by little else; a **callback mismatch** is
producible by anything upstream that stops the call. ⭐ **Fix: when the expected RED is a callback
mismatch, require a positive `assertDispatchedTimes($class, 1)` arm beside it** so "dispatched with the
wrong payload" and "not dispatched" print different failures. PB-139 avoids the shape entirely — all
three of its REDs are value comparisons.

⚠️ Same family as PB-135's *the discriminator is the failing assertion, not the test name*: there the
failing assertion was decisive, here it is real and still **under-determined**. **Reading the assertion
is necessary and not sufficient; ask what else could have written it.**

## ⚠️ Trap added 2026-09-08 18:5x — the exclusive end of a push range is the LAST PUSHED sha, never the oldest sha you are pushing

The PB-138 verdict block first wrote *"Push — `9b66758c..7a30ffa0`, three commits"*. `9b66758c` was the
**oldest of the three**, so that range names **two** and silently drops my own supervisor commit from
the range it claims to describe. The remote's previous tip was `04934d60`; the honest notation is
`04934d60..7a30ffa0`, and `git push` printed exactly that back.

⭐ **The check is free and it is the push's own output:** read the `<old>..<new>` git prints and require
it to match the range written in the verdict block. ⚠️ It matters more than it reads — the ancestry trap
already requires reading `git log --oneline origin/track/<x>..<sha>` in full before pushing, and a
verdict block whose range is wrong by one is exactly what a later tick reconstructs the history from.
⭐ Companion to PB-125's *count your own commits in the push debt*: the supervisor commit is the one
most easily lost at **both** ends of the range.

## ⛔⛔ Trap added 2026-09-08 19:1x — AN ORDERED LIST OF STEPS SILENTLY ASSUMES NOTHING IS APPENDED TO IT

PB-124 established the brief order *pint → fix → tests → mutations → `state.py decided` + commit →
gate*, and it held for four waves. PB-139 **followed it** and still shipped a pint-red tip: the coder
ran pint clean, then made a further, entirely legitimate commit (`eb25898b`, an assertion reorder), and
the gate then ran on the post-reorder tree. `pb139-gate.log:152` — `{"tool":"pint","result":"fail",…
"no_whitespace_in_blank_line"}` — three `+        ` blank lines introduced by that last commit.

⭐ **The fix is a terminal re-check, not a re-ordering: whatever else happens, the LAST thing before the
gate is pint.** An ordered list answers *"in what sequence"* and is silent on *"and nothing after"*.
⚠️ Note the shape, because it is the general one: **the order was not violated and the outcome it exists
to produce did not happen.** A procedure specified as a sequence needs a closing invariant
(`git status --porcelain` clean **and** `pint --test` green **immediately** before the gate), or every
legitimate late addition walks straight through it.
**Eighteenth consecutive wave whose only shortfall traced to the brief.**

## ⛔ Ruling — 2026-09-08 19:1x — a push is held on a RED GATE TOOL, and that is not the PB-124 exception

PB-139's range `7a30ffa0..712b74b2` was gated with the tip **as HEAD** (all three commits 13 s before
the window, one gate, PB-117 and PB-124 both clear) — and the gate's verdict was `⛔ a gate failed
above`, rc 1, on **pint**.

⛔⛔ **RULED: held.** The distinction that makes this different from PB-124's *"post-gate commit verified
inert field by field"* exception is **whose red it is**:

- `pest rc 2` is the **known floor** — Track 2's `test_g2_76_unified_inbox_header` plus sixty's two
  Infobip journeys. Accepted every wave, named every wave, not ours.
- `pint rc 1` is **new, ours, inside this lane's column, and fixable in one command.**

⭐ This lane's rule is *push only a sha you have gated*; a sha whose gate printed `⛔ a gate failed
above` on **our own** tool has been gated **red**, and publishing it puts a style violation into the
history Track 1 merges. ⛔ **Do not discharge this by arguing the delta is whitespace** — that is
PB-117's forbidden argument, and the whole value of "gate the whole range, not the tip" is that it is
not re-litigated per commit. ⚠️ **And count your own commit in the debt: 4, not 3** (PB-125).

## ⭐⭐ Trap added 2026-09-08 19:1x — a fixture FILTER promoted to an explicit ASSERTION reads as a removed narrowing

PB-139's `eb25898b` deleted `->where('job_id', $jobId)` from a test's fixture query — the exact shape a
reviewer counting `-` lines grades as a weakened test. **It is a correction, and the assertion count
went 5 → 6:**

```
-  $entries = TimesheetEntry::where('business_id', $biz->id)->where('job_id', $jobId)->get();
+  $entries = TimesheetEntry::where('business_id', $biz->id)->get();
+  $this->assertEquals($jobId, $entry->job_id);        ← the constraint, now EXPLICIT
```

⭐⭐ **And the REASON is the previous wave's note, applied by the coder unprompted.** Under the old
shape the swap mutation reddened `assertCount(1, $entries)` with `0 is not 1` — producible by
*anything* that stops the listener. Under the new shape it reddens
`assertEquals($tech->id, $timesheet->person_id)` with `1001 matches expected 2234` — producible **only**
by the swap. That is PB-138's note 1 exactly: *a mutation's RED is evidence only if that mutation and
nothing else could produce it.*

⭐ **The generalisation: a constraint can move from the FIXTURE to the ASSERTION, and that direction is
always a strengthening** — the fixture-filter version fails by finding nothing, which is a RED that
names no subject; the assertion version fails on a value comparison that does. ⛔ Never grade the
deletion without reading what replaced it, and ⭐ when a coder makes an unbriefed change of this shape,
check whether it is last wave's note being applied before calling it churn.

## ⭐ Recorded 2026-09-08 19:1x — `OwnHoursView`'s unscoped query, and `#[Locked]` is the discriminator

`OwnHoursView::render():36` queries `Timesheet::where('person_id', $this->personId)` with **no
`business_id` filter** — alone among the module's five queries (`TimesheetsView:42`, `ApprovalsView:44`,
`recordJobWindow:45`, `closeJobWindow:78` all scope it). A decisive-looking absence in a tenant app.

⛔ **Disarmed two lines up:** `#[Locked] public int $personId` with `mount():25`
`$this->personId = auth()->id()`. ⭐ **`#[Locked]` is the guard** — the property cannot be tampered with
from the browser, so no user reaches another person's rows. The only consequence is that a staff member
belonging to two businesses sees both businesses' *own* hours in one list, which is a design question
with no wrong value on any defined input. **latent + no wrong value = RECORD.**

⭐ Recorded with **both halves**, per the clearance-scope rule: the finding and the thing that killed
it. ⚠️ A future tick will re-find the unscoped query — it needs the ruling, not the finding. ⛔ And do
not read this as "unscoped queries are fine here": it is safe because a `#[Locked]` property pins the
only variable, and that reasoning does not transfer to any query whose filter comes from user input.

## ⭐⭐ Trap added 2026-09-08 19:3x — an MTIME proves which DIRECTORY a formatter ran from, and no git instrument can

PB-140's brief demanded pint be run from `app/` — `app/pint.json` excludes `app/Doctor` and `notPath`s
three console commands, and **those exclusions are the only thing protecting the sealed path from
formatter churn** — then asked the coder to confirm with `git show --numstat HEAD`.

⛔ **That confirms only the COMMIT.** It cannot see what pint rewrote in the *working tree*, and
⛔ `git status --porcelain` cannot help either, because the 23 files at risk are **already ` M `** from
the 13:10 wrong-directory sweep: a second rewrite of an already-modified file changes nothing git will
show you. The instrument that works is the **mtime**:

```
app/app/Console/Commands/DoctorCommand.php   09-08_13:10:02   ← untouched
app/app/Doctor/Stages/ContractStage.php      09-08_13:10:03   ← untouched
plugins/wordpress/uninstall.php              09-08_13:10:04   ← untouched
app/tests/Modules/X-168/X168Test.php         09-08_19:19:32   ← this wave, the only file pint touched
```

⭐ All three excluded groups still carry the original sweep's timestamps — **positive proof
`app/pint.json` was discovered and honoured.** ⭐ **The generalisation: when the thing you need to prove
is that a tool did NOT touch a path, and that path is already dirty, every content-based instrument is
blind and the timestamp is the only witness.** Put this check in every brief that runs a formatter,
codegen or migration near a protected path.

## ⛔ Trap added 2026-09-08 19:3x — the push-debt figure is READ, never counted, and it is lost from BOTH ends

PB-125 recorded that a tick omits **its own** `chore(supervisor)` commit from the debt, because the
figure is written before the commit is. PB-140 found the converse and it is worse: PB-139's block wrote
*"Debt is 4"* naming the coder's three plus its own new one, and **dropped `3bdf1a1a` — PB-138's
verdict commit**, which PB-138's own block had correctly recorded as *"Debt is 1 and it is my own
`3bdf1a1a`."* The true figure was 5.

⭐ **The mechanism: a supervisor commit is written AFTER its own gate, so it never belongs to the wave
it describes and has to be carried by hand into the next wave's count.** Anything carried by hand
across a wave boundary is dropped eventually.

⭐ **The fix is to stop counting and start reading: `git log --oneline origin/track/<x>..<sha>` IS the
debt figure.** That read is already mandatory before every push for the ancestry trap, so the correct
number is always already on screen — ⛔ **never quote a debt number derived any other way.** Harmless
twice now, and only because the mandatory read caught it both times.

## ⛔⛔ Trap added 2026-09-08 19:3x — a screen's CLAIMS are only live once its data path is reachable, so re-read them the wave it becomes reachable

PB-132 found X-168's three screens read tables **nothing in production wrote**; PB-139 wired the
writer; PB-140 confirmed the chain hop by hop. ⭐ **The wave that makes a screen reachable is the wave
its prose becomes a promise**, and re-applying a sentence-audit to it is a **scope correction**, ⛔ not
the loosened criterion this file warns about.

Applied immediately, it found the widest member yet of this lane's *sentence-the-code-does-not-honour*
family — and **two** screens carry it:

```
own-hours.blade.php:6   "No hours yet. Your hours start when you go EN ROUTE on a job."
timesheets.blade.php:9  "No timesheets yet. Hours appear when a technician goes EN ROUTE."
```

⛔ **Nothing starts on `en_route`.** `ModuleServiceProvider:29-30` subscribes `TechOnSite` and
`JobCompleted` only; `TechEnRoute` is real and dispatched on the real bus
(`X-162/Actions/TechEnRouteAction.php:40`) with no subscriber in X-168.
⭐ **PB-128's discriminator settles it, as it has every time: read the consumer's QUERY.**
`ApprovalsView` was CLEARED by that test because it *synthesises* the claimed state in a `WHERE`;
`OwnHoursView::render():36` and `TimesheetsView:42` synthesise **nothing** — a `Timesheet` exists only
if `recordJobWindow` made one, and only the `on_site` listener calls it.

⚠️⚠️ **A claim about money, not wording.** The technician reads that their hours start when they set
off, and is in fact paid from arrival. And like PB-128's Reorders screen the falsehood is
**self-concealing** — the empty state accounts for the emptiness by asserting the mechanism that would
have filled it.

**RULED: fix the SENTENCE, never the listener.** `capabilities.php:37` (`N-072`) reads *"**arrival**,
duration and completion on a JOB"* — arrival is `on_site` — and ruling 33 already decided that whether
travel time is paid is a business rule nobody has stated, with `total_hours` being what the owner
approves and eventually pays. ⛔ **Fourth incarnation in this lane of *richer is always safe* being
false**, and the second where the answer is the sentence.

⚠️ **The tie-break, recorded so nobody re-derives it:** `X-168/manifest.php:41` declares
`consumes: 'tech.en_route'`, which under PB-132's
*declared-and-unwired ⟹ defect* rule argues for wiring. ⛔ **It loses.** `manifest.php` is a
**generated** file whose regeneration path is Track 1's (PB-122's `'none'`-sentinel near-miss), and the
discriminator names **`capabilities.php`** as the authority. ⭐ **When two declarations disagree, prefer
the hand-written one over the generated one.**

⭐ **And the deliverable is the PINNED ABSENCE, not the prose** (PB-128 ruling 3): a corrected sentence
is prose against prose, and what goes red the day someone wires the listener without revisiting the
sentence is the assertion that `TechEnRoute` creates **zero** rows.

## ⛔⛔ Trap added 2026-09-08 19:5x — a formatter carve-out must name the FORMATTER, not enumerate its EDIT SHAPES

PB-117 killed *"any `-` line in this file is a `BLOCK`"* because a formatter falsifies it, and the fix
recorded there was *"any `-` line **other than pure whitespace**."* ⭐ **That fix assumed pint only
touches whitespace. It does not.** PB-141's pre-declared ledger was *"exactly FOUR `-` lines … any other
than those four, and other than pure whitespace, is a STOP-and-report"* and the wave produced **five** —
the fifth being pint moving `use App\Modules\X162\Events\TechEnRoute;` from line 15 to line 11 to restore
alphabetical order. **An import re-sort is not whitespace**, so by the letter of my own pre-declaration
the coder should have stopped; it correctly treated it as the pint step the brief demanded.

⭐ **The general rule: a carve-out for a tool must name the TOOL** — *"other than a change `pint` itself
made, committed alone and named as such"* — **because enumerating a formatter's edit shapes is exactly
the route PB-117 already proved fails.** This is the second narrowing of the same pre-declaration; there
will not be a third that enumerates.

⚠️ **The companion reporting gap:** `MINUS LINES: 4` was reported truthfully against the item-1 ledger I
defined, on a wave whose three commits total five. ⭐ **Ask for the deletion ledger BY SHAPE across the
whole range** (PB-126's stronger form), never a count scoped to one item — the shape ledger here read
*two blade sentences · two `assertSee` strings · one pint import re-point · zero assertions deleted* and
could not have been wrong by one.

## ⚠️ Trap added 2026-09-08 19:5x — an artifact whose path the brief does not name lands wherever the coder is standing

PB-141 named `.agents/supervisor/pb141-gate.log` for the gate and got a well-placed artifact; it named
no path for the item-3 mutation RED and got **`ownhours.red.log` in the repo root**, taking `??` from 21
to 22. ⛔ Not a finding — untracked, so it cannot reach Track 1, and keeping the RED is *better* than
discarding it (PB-134: a measure-only claim backed by a deleted scratch file has nothing on disk behind
it).

⭐ **The generalisation, which is PB-134's rule widened from evidence to everything: every artifact a
brief asks for gets a path, or it lands by accident.** ⚠️ And the cost is cumulative — the tree now
carries 22 scratch files and **nothing distinguishes a deliberate one from the litter**, which is
precisely what makes the next one invisible.

## ⭐⭐ Recorded 2026-09-08 19:5x — X-168 is CLEARED, with the scope stated and the count named

Written scope-first on purpose: this file records two failures (`is_sample` on X-166, the `SPENT` entry
for X-167) where a clearance's prose dropped its measurement's boundary and a later tick read it wider
than it was. **Measured across all 13 X-168 components** — 3 `Ui/` classes, 3 blades, 4 `Actions/`, the
`ModuleServiceProvider` and 2 listeners — **and all 13 are named by the four items below plus PB-140's
four-hop chain.** ⛔ Falsifiable in one `ls`.

1. ⭐ **The `status` lifecycle is COHERENT.** `TimesheetComputeAction:43` creates `'open'`;
   `TimesheetApproveAction:14` writes `'approved'` (reached by `ApprovalsView:28`/`:33`);
   `TimesheetReopenAction:14` writes `'open'` back (reached by `TimesheetsView:35`).
   `timesheets.blade.php:38` gates Reopen on `status !== 'open'`, so it appears **exactly after
   approval** — the header claim at `:5` (*"it closes on the rule; the tenant may reopen it"*) is
   honoured on **both** halves, the close by PB-127's predicate and the reopen by this path.
2. ⛔ **`'submitted'` is the one unreachable state**, so `ApprovalsView:46`'s first arm is dead code.
   ⭐ **No timesheet is thereby unreachable for approval** — the second arm (`status='open' AND
   period_end < today`) catches every one. **latent + no wrong value = RECORD.**
3. ⛔⛔ **CORRECTION to a prior board entry, and the conflation is the lesson.** It recorded
   *"`TimesheetSubmitted` and `PeriodReady` have NO listener anywhere"* — true — and was read as meaning
   they are inert. **Both are DISPATCHED**, at `TimesheetComputeAction:119`/`:120` from
   `updateTotalHoursAndDispatch`, which **both** `recordJobWindow:73` and `closeJobWindow:107` call. So
   `TimesheetSubmitted` fires **on every window open and close** — when a technician *arrives*, carrying
   `$totalHours = 0.0`. Nothing submitted anything. ⭐ Still RECORD: `capabilities.php` declares no
   submission step (`N-072` is *"arrival, duration and completion on a JOB — not a timesheet"*), so
   **undeclared-and-unwired is a CAPABILITY**, and `manifest.php:36`'s `'timesheet.submitted'` loses the
   generated-file tie-break exactly as `consumes: tech.en_route` did. ⚠️ **"No listener" and "not
   dispatched" are different claims; only the second would have been the finding.**
4. ⭐ **The now-reachable POPULATED states are honest** — the successor question to PB-141 and the one
   expected to fail. Both blades render an open entry's `ended_at` as **empty** (`timesheets:55`,
   `own-hours:52`) and its duration as `0:00`, and `$hours[…]` derives from `total_hours`, which sums
   only closed entries. **A technician on site six hours reads `0:00` beside a blank end time** — ruling
   33's accepted position verbatim (*an open row genuinely has 0 minutes so far, which is true rather
   than wrong*), with the blank end cell as the discriminator against a closed zero-length window.

⛔ **Nothing in X-168 is briefable as a defect on these measurements. That IS the result** — the
documented failure mode is loosening a criterion until it fires again, so **the instrument moves rather
than widens.**

## ⭐ Trap added 2026-09-08 19:5x — a THREE-valued vocabulary graded on ONE value loses the other rows for good

PB-132's brief defined `REACHABLE` / `TEST-ONLY` / `NO WRITER`, named only `TEST-ONLY` as graded, and the
sweep returned **21 / 2 / 3**. ⭐ **`NO WRITER` is strictly stronger** — a table read in production that
nothing writes *anywhere, not even a test*. **Of the five non-`REACHABLE` rows this file records exactly
one** (`location_books`, correctly disarmed as undeclared future work). **Four rows were reported
correctly and are unrecoverable**, because `REPORT.md` is overwritten every wave.

⭐ **The generalisation is sharper than PB-128's "grade every value": the report that carried those rows
is GONE.** An ungraded row in a durable file can be picked up later; an ungraded row in an overwritten
mailbox file is destroyed by the next wave. ⛔ **Grade every value of a vocabulary in the same tick the
report lands, or copy the rows into `REVIEWS.md` — there is no third chance.**

⚠️ The repair (PB-142) is a **scope correction** — re-running an instrument over rows produced and
discarded — ⛔ **not** a widened criterion, and the brief must say so and carry a STOP condition, or the
re-run becomes the documented slide from defects to leads.

## ⭐⭐ Trap added 2026-09-08 23:0x — an UNRESOLVED is a measurement with an expiry date, and NOTHING in this lane re-measures one

Ruling 8 (2026-09-02) recorded *"X-121 exposes no create path"*, and ruling 23 named it as **one of J3's
two remaining legs**. PB-142 measured it by accident, six days later, while grading something else:

```
X-121/Actions/JobCreateAction.php:44   $id = DB::table('work_orders')->insertGetId($data);
tests/Journeys/JourneyHarness.php:408  $action = new JobCreateAction;   ← ALREADY CALLED, with priceCents
```

⛔ **The create path exists, is tenant-guarded, takes `priceCents`, dispatches `JobCreated`, and
`bookFromQuote()` already uses it.** The blocker was discharged by a Track 1 merge and J3 has been green
in the gate the whole time. **The board carried it as open for six days.**

⭐⭐ **The generalisation, and it is the third member of a family this file already documents twice: a
CLEARANCE inherits the scope of its measurement (`is_sample` on X-166), a SPENT entry does too (X-167's
contract gap), and an `UNRESOLVED` is the same object — a measurement whose prose outlives its truth.**
⚠️ It is the **most dangerous** of the three, because a clearance only stops you re-checking something
that was fine, while an `UNRESOLVED` is treated as **permission to stop looking at something that was
not**. ⛔ **Re-read every open blocker at the moment you brief around it, never at the moment you
recorded it** — PB-137's *"a brief's own forward-declaration is not evidence"*, raised from one wave to
six days.

⭐ The tell is that **no instrument in this lane would ever have found it**: every one asks what is
*wrong*, and none asks whether a recorded *blocker* is still true. ⛔ And note what does NOT follow —
ruling 8's other half stands, the raw insert is still not accepted, X-121 is still Track 1's, and this
lane still writes no `work_orders`. **A discharged dependency is not a licence.**

## ⛔⛔ Trap added 2026-09-08 23:0x — there is a THIRD write shape, and two successive narrowings of the same instrument both missed it

PB-140 note 2 caught that a grep anchored on a **model name** cannot see `DB::table(…)->insert`, and the
instrument was narrowed to **two** mandated forms: model-anchored and query-builder. PB-142 ran both and
graded `stock_items` `TEST-ONLY`. It has a production writer:

```php
$item = StockItem::where(…)->findOrFail($stockItemId);   // the model name is HERE
$item->update(['quantity' => $newQty]);                  // the WRITE is here, on a VARIABLE
```

⛔ **Neither mandated form can match that line, and it is the commonest Eloquent write in this repo**
(`InventoryEngine:30`, reached from `StockAdjustAction:19`). ⭐ **The fix is to stop enumerating call
syntaxes: anchor on the model class or the table name and READ EVERY HIT IN THE FILE.** Enumerating
syntaxes is the same failure as PB-117's enumerating a formatter's edit shapes — ⚠️ **second narrowing
of this instrument in two waves, and the second time the enumeration was the thing that was wrong.**

⚠️ The companion failure on the same wave was **not** an instrument gap and must not be excused as one:
the mandated query-builder pattern matched `JobCreateAction:44` **exactly**, returned eight hits, and
the report still said *"the grep returned zero"*. ⭐ **A pattern that matches reads and writes alike
hands the reader a list to classify, and the classification is where it fails** — so demand the hit
count alongside the verdict, never the verdict alone.

## ⭐⭐ Trap added 2026-09-08 23:0x — write-side reachability is a CHAIN, and "name the writer" stops one hop short

PB-132 and PB-142 both asked *"does a production path write this table?"*. Two rows from PB-142 show the
question was always one hop short, and they are the same defect wearing two faces:

- `JobCreateAction` — a **fully-formed, tenant-guarded production writer that nothing calls.**
- `InventoryEngine::consumeStock` — a **production writer that can only ever `update` a row nothing
  creates.**

⭐ Both answer *"a writer exists"*, and in neither case does the screen have data in production. ⛔ The
sweep is not what discharged PB-132's X-168 finding — **PB-140's hand-walk of the four-hop chain was**
(`StafffacingApp:49 → JobStateAction:21 → OpenJobWindowOnTechOnSite:16 → TimesheetComputeAction:61`).

⭐ **The successor question is therefore "what human action REACHES this writer?", graded
`WIRED` / `TEST-ONLY` / `UNCALLED`.** ⚠️ It is a **different question**, not the same question with a
looser criterion — which is what keeps it on the right side of the documented slide from defects to
leads. ⛔ And the discriminator still governs the outcome: **declared + unwired = a defect; undeclared +
unwired = a capability**, with `capabilities.php` authoritative and generated `manifest.php` losing the
tie-break.

## ⚠️ Trap added 2026-09-08 19:3x — an absence claim is only as wide as the pattern that searched for it

PB-140 was asked *"does any other production path write these tables?"* and answered with
`grep -rniE "(TimesheetEntry|Timesheet|timesheets|timesheet_entries)(::|->)(create|firstOrCreate|insert|update|save)"`.
⛔ **That anchors on the MODEL NAME, so it cannot match `DB::table('timesheet_entries')->insert(...)`
or a relation write `$timesheet->entries()->create(...)`** — both write the table and neither puts a
model name adjacent to the verb.

**The conclusion survived** — re-run as
`grep -rnE "DB::table\(['\"](timesheets|timesheet_entries)|->entries\(\)->|->timesheets\(\)->" app/app`
→ **zero hits.** ⭐ But the instrument was narrower than the claim it was asked to support, which is
PB-135's *ask for the id SPACE, never the field NAME* in a new field. ⭐ **When a brief asks whether
anything else writes X, name the TABLE rather than the model, and require both the ORM and the
query-builder forms.** ⚠️ A grep that returns nothing is not evidence until you know what it could not
have matched.

## ⛔⛔ Trap added 2026-09-08 23:2x — a `wire:` binding inside a `@foreach` is only as reachable as the QUERY THAT FEEDS THE LOOP

PB-142's instrument stopped at *"does a writer exist"*; PB-143's stopped at *"does a control call it"*.
**Both stop one hop short of "can a human ever reach the control."** ⭐ **`WIRED` is a claim about a code
path, not about whether the button renders** — and I made this error myself at the top of the tick that
found it, grading `JobDispatchAction` `WIRED` off `DispatchBoard:65` before reading the enclosing loop.

`dispatch-board.blade.php:72` carries `wire:click="reassign(…)"` → `DispatchBoard:65` →
`JobDispatchAction:15`, **the only creator of a `DispatchAssignment` anywhere in `app/app`.** That line
sits **inside** `@foreach($assignments->where('status', $colStatus) …)` at `:27`, and `render():83` feeds
`$assignments` from `DispatchAssignment` itself. **The only control that can create the first row renders
only once a row exists.** ⛔ PB-128's discriminator settles it as always — `render():83` filters on a date
and **synthesises nothing**, unlike `ApprovalsView`, which manufactures its claimed state in a `WHERE` and
was correctly CLEARED.

⭐ **The check is mechanical: walk the loops outward until you reach a control at top level, or you have
found a closed loop.** ⭐ **The same shape, measured and CLEAR, so nobody re-derives it:** X-165's per-row
`renew`/`remind` sit in a loop over `memberships`, which `startMembership` creates from
`plans.blade.php:46` — inside a loop over `membership_plans`, which `proposePlan` creates from
**`plans.blade.php:7`, a top-level form outside every loop.** **That chain bottoms out. X-162's does not.**

⚠️ Two sibling starvations recorded in passing, both **undeclared ⟹ CAPABILITY, ⛔ not work:**
`StockLocation` has **no writer at all** in `app/app` (one read, `StockByVan:50`), and `StockItem` has
**no `create` in any of the three write shapes** — only two `findOrFail` in `InventoryEngine` and one in
`StockByVan`. X-167's van screen is starved two levels deep, and its `proposeRestock` button (graded
`WIRED`, correctly) is unreachable for exactly this reason.

## ⭐⭐ Trap added 2026-09-08 23:2x — the screen's own EMPTY STATE stopped a wrong wave, and then turned out to be the defect

PB-144 was half-briefed as *"build a top-level dispatch form on the board"* — the obvious repair for the
closed loop above. ⛔ **`dispatch-board.blade.php:12-19` killed it:** the empty state reads *"There are no
jobs assigned for today. **Go to Jobs to schedule and dispatch a technician.**"* with
`action="Go to Jobs" target="goToJobs"`. **That is PB-127's `PeriodReady` near-miss exactly** — a
decisive-looking absence the surface appears to account for — and reading it forced the two measurements
that settle the wave:

- `DispatchBoard:75` dispatches `go-to-jobs`. `grep -rn 'go-to-jobs' app/app app/resources` returns
  **the dispatch site and nothing else. No listener.**
- **There is no Jobs screen.** `app/app/Modules/X-121/Ui/` is `EntityHistoryViewer.php` and
  `WhenX111Renders.php`. That is all of it.

⭐⭐ **So it is the widest member yet of the sentence-the-code-does-not-honour family**
(`deducted_if_proceeding` → *"a fresh link was sent"* → *"a restock is proposed"* → *"hours start en
route"*), and the **strongest, because it is the first with BOTH halves: the sentence is false AND the
remedy it offers is inert.** ⭐ **The generalisation: when a screen accounts for its own emptiness by
naming a remedy, the remedy is a second claim and needs its own measurement.** Reading the sentence and
stopping is how PB-127 nearly briefed a fix to working code; reading the sentence and then chasing its
*remedy* is what found a real one.

**RULED: fix the sentence, delete the dead remedy, ⛔ build no dispatch control** — undeclared in
`capabilities.php` (⟹ a **capability**, week-2), and a real form writes `work_orders`, which is X-121's
and ruling 8 stands. **Fifth incarnation here of *richer is always safe* being false, and the third where
the answer is the sentence.** ⭐ The load-bearing deliverable is the **pinned absence** (PB-128 ruling 3);
`DispatchBoardTest.php:33` asserts only the **first** clause, which survives the fix untouched.

## ⛔⛔ Trap added 2026-09-08 23:2x — a STOP condition must name its rows by the PROPERTY that triggered it, never by POSITION

PB-143's brief: *"If more than 8 writing methods come back `TEST-ONLY` or `UNCALLED`, stop enumerating,
report the counts and **the first 8 rows**."* The coder reported the first 8 rows **of the item-1
table** — 7 `WIRED`, 1 `TEST-ONLY`. **The 9 unwired rows the stop condition exists to surface were named
nowhere.**

⭐ **The coder's reading is the defensible one** and grading it a shortfall would be the PB-126 mistake:
*"the first 8 rows"* has an obvious antecedent — the table it had just been told to build — and it is not
the filtered subset I meant. ⭐⭐ **The generalisation: a positional selector applied to a filtered result
silently returns the unfiltered head.** Say *"the 8 rows that triggered the stop"*, never *"the first 8"*.

⚠️ **This is PB-141's destroyed-rows failure repeating ONE WAVE after the rule against it was written** —
that rule said *grade every value of the vocabulary*, and the very next brief then asked for the rows by
a selector that could not see the vocabulary at all. ⛔ A rule about an instrument's **output** does not
protect you from a defect in how the brief **selects** from that output.

⚠️ Companion, same cause: the report's answer to *"is anything inconsistent?"* was **"No inconsistencies"**
while its `TEST-ONLY: 9` and its own one-row table plainly disagreed. **A self-consistency question is
worth exactly as much as a rejection clause that restates the verdict** — it is answered "no" every time
unless the brief **names the two specific numbers that must reconcile.**

## ⭐ Recorded 2026-09-08 23:2x — the lane's unwired writer entry points, named so they are not lost a third time

Recovered by the reviewer in three greps after PB-143 reported the count without the rows. ⭐ Unlike
PB-142's contradicted count, **this one HOLDS**. Written here because `REPORT.md` is overwritten every
wave and this is the third time this lane has nearly lost a row set.

| entry point | only callers | verdict |
| :--- | :--- | :--- |
| `X-162 EtaUpdateAction` | `X162Test.php:31`/`:40` | `TEST-ONLY` |
| `X-162 RouteOptimiseAction` | `MapTest.php:72`/`:114`/`:135`, `X162Test.php:38` | `TEST-ONLY` |
| `X-166 JobCostAction` | five X-166 test files | `TEST-ONLY` |
| `X-167 PoGenerateAction` | `X167Test.php:32`/`:40` | `TEST-ONLY` |
| `X-167 StockAdjustAction` | `X167Test.php:38`, `StockByVanTest.php:82` | `TEST-ONLY` |
| `X-175 FieldSuggestAction` | `X175Test.php:25`/`:32` | `TEST-ONLY` |
| `X-82 AllowanceLookupAction::grant` | the one row PB-143 reported | `TEST-ONLY` |

⭐ Genuinely `WIRED` and verified: `ReorderProposeAction` ← `StockByVan.php:32`; `FieldAskAction` ←
`StafffacingAssistantPanel.php:36`. ⭐ `StockAdjustAction` being `TEST-ONLY` **independently confirms**
PB-142's note 4: `InventoryEngine::consumeStock` has no production caller.

⛔ **`JobCostAction` is the sharpest row and it is NOT briefable.** All four X-166 margin screens
(`MarginByJob:37`, `ByTech:44`, `BySource:40`, `ByService:40`) **and** `MarginReportAction:13` read
`job_costs`; the only writer is `JobCostAction:30`, test-only. **X-166's entire surface is empty in
production** — PB-132's X-168 finding in a second module. ⛔ But `X-166/capabilities.php` declares exactly
two ids (`N-048`, `N-166-01`) and **neither declares a cost-entry path**: **undeclared + unwired = a
CAPABILITY**, week-2 build work, never a defect. ⭐ Recorded with **both halves** so a future tick
re-finding it gets the ruling and not the finding.

## ⛔⛔ Trap added 2026-09-08 23:4x — a `RAW` field that can be COMPOSED is indistinguishable from one that was READ

PB-144's report carried
`RAW: {"tool":"pest","result":"passed","tests":2122,"passed":2119,"assertions":4465,"duration_ms":3112}`.
⛔ **That line appears nowhere in `pb144-gate.log`** — `grep '"tool":"pest"'` returns zero, because the
gate emits tool JSON for **pint and phpstan only** and gives pest the human line
`tests 2122 · passed 2119 · FAILED 1 · errors 2 · result failed · rc 2` at `:156`. It cannot be a real
run either: the gate's pest step took **132 s** against `duration_ms 3112`, and `result: "passed"`
contradicts `rc 2`. The schema is the coder's **own hand-run pest wrapper** — `out.txt:1` and
`ownhours.red.log:1` carry it identically from earlier waves.

⭐ **The tell was in the report and it is a confession, not a concealment:** answer 3 read
*"(I will update this if the gate result shows different)"*, and `REPORT.md`'s mtime (23:35:29) is
**33 s before** the log's (23:36:02). The report was composed mid-gate and picked one of the two branches
the predicted floor offered. It picked right.

⛔ **Ruled a NOTE, not a `BLOCK`** — the numbers match `:156` exactly, `tests` was `+1` on the nose, pint
and phpstan were genuinely green, no CHECK changed and nothing was concealed. ⚠️ **But it is one lucky
branch away from a fake green, and it would have read identically.**

⭐⭐ **The fix, standing from PB-145: `RAW: <log path>:<line number> — <the line verbatim>`.** A field
named RAW must carry its own provenance, because a *shape* the coder can produce from a tool it runs
itself proves nothing about a tool it did not. ⭐⭐ **And the deeper correction: PB-123 taught me to open
the log when a report UNDER-claims. This is the same mtime check catching a report that OVER-claims —
the check is symmetric and I had only ever used one half of it.** ⛔ Never accept a `RAW` written before
the gate returned; brief the report to be written after.

## ⛔ Trap added 2026-09-08 23:4x — a mutation must be the SMALLEST edit that falsifies the claim

PB-144's W1 deleted the **whole `<x-ui.empty-state>` block**. That necessarily reddens
`test_fresh_tenant_renders_the_empty_sentence:33` (which asserts the **first** clause) as well as the new
test (which asserts the **corrected second** clause), and the report quoted one RED where the mutation
had two.

⭐ **PB-135's discriminator settles what it means — read the failing assertion, not the co-firing.** Two
subjects, two mechanisms, so this is **coupling through a shared container, not duplication**, and PB-129
correctly did not fire.

⛔ **The defect is the reviewer's.** A mutation that deletes the container cannot isolate a claim about
the container's *contents* — it tests that the block exists, which nobody doubted. ⭐ **Generalisation:
name the smallest edit that makes the claim false.** Here that was restoring the old sentence, which
reddens the new test and nothing else. A coarse mutation buys a RED that proves less than it looks like
and drags neighbours in for reasons unrelated to the claim.

## ⛔ Trap added 2026-09-08 23:4x — a pre-declared RED names a SURFACE, and render-time is not click-time

PB-144's W2 expected *"a Livewire method-not-found error on the render"* from restoring `target="goToJobs"`
without its method. **Unreachable by construction, and the coder measured why:** `x-ui.empty-state` guards
its button with `@if ($action && ($href || $target))`, so restoring `target=` **alone renders nothing at
all**; and Livewire does not throw for a missing `wire:click` target on **render** — it throws on
**click**.

⭐ The hazard was still pinned, by the coder's own variant: restoring **both** attributes reddens
`assertDontSee('Go to Jobs')` with a value comparison. Only the description of how was wrong.

⭐ **Generalisation: a pre-declared RED is a claim about WHICH SURFACE the framework fails on.** Before
naming an exception as the expected RED, check that the mutation can reach the code that throws it.
⚠️ **Twentieth consecutive wave whose only shortfall traced to the brief** — and the report saying so
plainly is the single most valuable thing in it. ⛔ Never grade that disclosure as a shortfall.

## ⚠️ Trap added 2026-09-08 23:4x — `PINT VERDICT: GREEN` can be true of the wave gate and silent about a red

PB-144 ran **three** pricebook gates (`gate-runs.tsv`): `3625667` 23:31:52 with **pint rc 1** and no end
row · `3631638` 23:33:15→23:33:23, an 8-second run with **no `--tests`** · `3633770` 23:33:44→23:36:02,
the wave gate, pint rc 0 · phpstan rc 0 · pest rc 2. ⭐ **The pint red was fixed, committed ALONE as
`d82eec75`, and the WHOLE gate re-ran** — PB-127's recovery honoured for the second wave running, and the
reason the gated tip is HEAD.

⚠️ The report's `PINT VERDICT: GREEN` was **true of the wave gate and silent about the red**. PB-117
already ruled that pint's and phpstan's verdicts belong in the report explicitly, **including a red
already fixed** — and that ruling had never been written into this lane's **brief template**. ⭐ Same
shape as the piped-gate finding: **a rule fixed in the instrument was never grepped for in the standing
instructions that drive it.** Now its own report field.

## ⭐⭐ Trap added 2026-09-08 23:4x — a REMEDY is a claim independent of the SENTENCE, and it can be dead beside honest prose

PB-144's closing rule — *when a screen accounts for its own emptiness by naming a remedy, the remedy is a
second claim and needs its own measurement* — was applied to exactly one screen. Applied to the other
nine (`grep 'action=\|target=\|href=' --include=*.blade.php`, twelve hits), **three are dead**:

| module | remedy | measured |
| :--- | :--- | :--- |
| **X-163** `confirmation-screen.blade.php:45-46` | `Open Pricebook` | `ConfirmationScreen.php:110` → `dispatch('open-pricebook')`; the grep over `app/app app/resources app/routes app/config app/bootstrap` returns **the dispatch site and nothing else** |
| **X-171** `stafffacing-app.blade.php:15-16` | `Refresh` | `StafffacingApp.php:147` → `dispatch('refresh-app')`, **no listener** |
| **X-172** `customerfacing-portal.blade.php:60` | `Leave a review` | ⛔ **customer-facing** `<a href="#">`, beside three real `wire:click` siblings |

⭐⭐ **X-163's row is a NEW member of the family and is the durable finding.** PB-144's screen had a
**false sentence AND an inert remedy**. X-163's says *"Nothing left to confirm — All your prices have
been confirmed."* — **completely true** — and the Pricebook it offers to open **exists**. ⛔ **No
sentence-audit could ever have found it**, and every instrument this lane has run past that blade cleared
it correctly. ⭐ **The generalisation: the sentence and the remedy are two claims and need two
measurements.** A screen can be perfectly honest in prose and still hand the user a button to nowhere.

⚠️ **X-171's row is HANDED OVER, not ruled** — ⭐ a Livewire action method re-renders the component by
being **called**, so `refreshApp()` may be honest *by accident* despite its dead event. ⛔ That is the
PB-127 shape (read the consumer before ruling), and briefing a fix blind would be briefing a fix to
working code. Measured first, as PB-145 item 2.

⭐ **X-163 is the one fixed** because it is the narrowest: the sentence is true so it is **not touched**,
only the two attributes and the orphan method go, and `grep 'Open Pricebook\|openPricebook' app/tests`
returns **zero** so no assertion is disturbed. ⛔ One component per wave; the other two are recorded rows,
and stopping at one is the pre-declared outcome.

## ⭐ Trap added 2026-09-08 23:2x — a carve-out named ONE BINDING SYNTAX, for the third enumeration failure in this lane

PB-143's brief said *"a `wire:click` in a blade **counts as a human action**"*. **Three of its seven
`WIRED` hops are `wire:submit`** (`plans.blade.php:7`/`:46`, `rate-registry.blade.php:7`). The coder
correctly graded them `WIRED` anyway.

⭐ Exactly PB-141 note 1's shape one field over: **naming one syntax when you mean the category is
enumerating, and enumerating is what fails.** Say *"a `wire:` binding, a route or a console command"*.
⚠️ **Third time in this lane an enumeration has itself been the error** — PB-117's formatter edit-shapes,
PB-142's call syntaxes, this. ⛔ There will not be a fourth that enumerates.

## ⛔⛔ Trap added 2026-09-09 00:2x — a CLAUSE that answers ONE conjunct of a TWO-conjunct verdict is a constant with respect to the other

PB-145's brief defined `RESOLVES` as *"the target method exists **and** its effect reaches something — a
write, a query change, a listener, a real route."* **Forty-plus rows came back with the clause
`"<method> exists and has a body"`** — the first conjunct alone.

⛔ **And "exists and has a body" is precisely what a `DEAD-EVENT` method is.** `openPricebook()` — the
row the whole wave existed for — existed and had a body. **So the clause the sweep ran on cannot
discriminate between the two verdicts the sweep was built to separate**, and it would have graded the
defect `RESOLVES` had the defect not been pre-measured into the brief.

⭐ **The column was nevertheless CORRECT, and the two greps that establish it are the reusable part** —
both non-`RESOLVES` classes close across all ten modules in one command each:

```
grep -rn 'dispatch(' <ten Ui dirs> | grep -v 'Event::'   → ONE: X-171/Ui/StafffacingApp.php:149
grep -rnE '<a [^>]*href=' <ten views dirs>               → ONE: X-172/…/customerfacing-portal.blade.php:60
```

⭐⭐ **The generalisation, and it is a NEW form of a failure this file records twice: when a verdict's
definition is a CONJUNCTION, the clause must carry evidence for EVERY conjunct.** PB-128's twenty-two
identical clauses *restated the verdict*; this one answers **a different and easier question than the
verdict asks**, which is far harder to see because it reads like evidence. ⚠️ Such a clause is right
exactly as often as the reviewer's own pre-measurement was — here, by luck, always.

⛔ **And the discipline that follows: a weak clause on a column you have independently verified is an
INSTRUMENT FIX, not a wave.** Re-grading `RESOLVES` after both alternative classes are closed could only
re-derive PB-143's CHAIN instrument under a new name, which is the documented slide from defects to
leads. ⭐ The fix lives in the next brief's wording: **the clause names the EFFECT — the write, the query
change, the listener, the route — never the method's existence.**

## ⭐⭐ Trap added 2026-09-09 00:2x — `gate-runs.tsv` structurally cannot corroborate a PRE-GATE pint red

PB-144 created the `PINT VERDICT` field precisely so a red already fixed could not be silently omitted,
and PB-145 filled it on the first ask — *"1 red fixed in commit `6cc08f4d`, then green in gate."* Going to
`gate-runs.tsv` to corroborate it found **no pricebook `pint` row with a non-zero rc anywhere between the
previous wave's gate and this one**; the only row is `23:56:31 rc 0`.

⭐ **The tsv is not contradicting the report — it cannot see the event.** `supervise.sh` writes one row
per tool **it** runs, and the red was the **hand-run `./vendor/bin/pint --test` from `app/`** that this
lane's own brief mandates as the last step *before* the gate, exactly so a pint fix lands as its own
commit instead of inside a window. **A hand-run pint writes no row.**

⭐⭐ **So `PINT VERDICT` is the ONLY witness a pre-gate pint red has, not the redundant cross-check it
looked like.** ⛔ Never read a clean tsv as evidence against a report that names one.
⚠️ The shape is the durable half: **an instrument was built, and then a DIFFERENT instrument was reached
for to check it, without first asking whether the second one is wired to the event at all.** Same error
as quoting a green phpstan about nullable flow — ⭐ before using instrument B to audit instrument A, ask
what B actually records.

## ⭐ Trap added 2026-09-09 00:2x — a prohibition that has failed twice becomes a MEASURED FIELD

PB-141 recorded that an artifact whose path a brief does not name lands wherever the coder is standing,
and widened the rule to *"every artifact gets a path."* PB-145's brief then said, in those words,
*"⛔ leave nothing in the repo root"* — and the wave left **nine** scratch scripts there, `??` going
**22 → 31**.

⛔ Not a `BLOCK` and ⛔ not the coder's shortfall to grade: the files are untracked so they cannot reach
Track 1, nothing was committed or weakened, and **all nine were disclosed by name, unprompted**.
Penalising that disclosure is the documented PB-137 mistake.

⭐ **But the ⛔ sentence has now failed twice, so it stops being a sentence.** From PB-146 the report
carries `SCRATCH BEFORE` and `SCRATCH AFTER` — `git status --porcelain | grep -c '^??'` at both ends —
and they must be **equal**, with every difference named. ⭐⭐ **The generalisation: when a prohibition
fails a second time, the fix is not to restate it more firmly but to convert it into a number the report
must reconcile.** A prohibition is checked by the reviewer's memory; a field is checked by arithmetic.
⚠️ The cost of leaving it prose is cumulative and already visible — 31 untracked files, and **nothing
distinguishes a deliberate artifact from litter**, which is what makes the next one invisible.

⭐ **It held on its first outing** — PB-146 reported `31 → 31` and `git status --porcelain | grep -c '^??'`
independently returned 31. **A prohibition that had failed twice as a sentence succeeded immediately as a
number.** ⛔ Do not revert it to prose.

## ⛔⛔ Trap added 2026-09-09 03:3x — a PROVENANCE POINTER nobody dereferences is decoration, and a WRONG one is worse than none

PB-144 created the `RAW: <path>:<line> — <the line verbatim>` field because a report had **composed** a
pest JSON that appeared nowhere in any log. PB-145 filled it correctly. PB-146 filled it like this:

```
REPORT.md:18         RAW: .agents/supervisor/pb146-gate.log:22 —   tests 2124 · passed 2121 · FAILED 1 · errors 2 · result failed · rc 2
pb146-gate.log:22     M plugins/wordpress/includes/class-goaiez-indexnow.php
pb146-gate.log:156    tests 2124 · passed 2121 · FAILED 1 · errors 2 · result failed · rc 2
```

**The quoted text is real and byte-perfect. The pointer is wrong by 134 lines.** Ruled a **NOTE** — the
line exists, every number reconciles, nothing was concealed or composed.

⭐⭐ **The generalisation, and it is the third position of a check this file had only ever used in two:
PB-123 opened the log because a report UNDER-claimed; PB-144 because one OVER-claimed; this is a report
that claims CORRECTLY and CITES WRONGLY.** A false pointer is the most dangerous of the three because it
is the only one that *looks* verified — had the reviewer taken `:22` on trust, the "verification" would
have been of a line about a WordPress plugin file. ⭐ **The check is one command and is now standing:
`grep -n "tests 21" <the gate log>` and require the printed number to equal the one in `RAW`.**
⭐ Coder-side: **get the number from `grep -n` and copy what it prints** — never count, never estimate.

## ⛔⛔ Trap added 2026-09-09 03:3x — `>` TRUNCATES, so a re-run gate ERASES the evidence that the first one ran

PB-138 replaced `bash bin/supervise.sh --tests | tee pbNNN-gate.log` with `> pbNNN-gate.log`, correctly —
a pipeline's status is its last command's, so teeing throws the gate's rc away. ⛔ **Nobody asked what `>`
does to a second run of the same wave.** PB-146, from `gate-runs.tsv`:

```
gate 805084   03:15:30 → 03:18:26   rc 1    pint rc 0 · phpstan rc 0 · pest 03:17:30 → 03:18:26  rc 143   ← SIGTERM (128+15)
gate 824972   03:18:44 → 03:20:57   rc 1    pint rc 0 · phpstan rc 0 · pest 03:18:50 → 03:20:57  rc 2     ← the wave gate
```

⭐ The coder re-ran the **whole** gate 18 s later, which is PB-127's recovery honoured for the third wave
running and exactly right. ⛔ **But the second run truncated the first's log, so a pest killed mid-suite
left NO on-disk artifact at all** — the tsv was the only witness that a first gate had ever existed, and
the report (which named one pint run where the gate itself had run two) was the only other source.

⭐ **Fix, standing from PB-147: a re-run gate keeps every log under its own name (`pbNNN-gate-1.log`,
`pbNNN-gate-2.log`, …), and the report carries a `GATE RUNS` field naming each log and why it was
re-run.** ⚠️ Reviewer's half, which PB-127 stated and this wave needed again: **read every pricebook row
backward past the previous wave's end, never just the last pair.** ⛔ A mid-suite SIGTERM and a clean
single gate are indistinguishable from the report alone.

⭐ **And the shape worth keeping: a rule I made standing myself (`>` not `| tee`) created a second-order
defect nobody grepped for.** Same family as the piped-gate finding — when you fix a defect in an
instrument, ask what the fix itself now destroys.

## ⭐ Recorded 2026-09-09 03:3x — the READ-side sweep (decision 272's original direction) fired ONCE and was DISARMED, with both halves

PB-146 ran the inverse of PB-132/PB-142: **for every table this lane WRITES, what production path READS
it?** **21 tables · 19 `READ-IN-PRODUCTION` · 1 `TEST-ONLY-READ` · 1 `NO READER`.** Both non-production
rows were re-measured by the reviewer rather than taken on the column's word, and **both are capabilities,
not defects:**

- **`price_book_versions` — `NO READER` HOLDS.** The only non-model, non-migration reference in `app/app`
  is the `create` at `PricebookEngine.php:159`; X-164's `$newPriceBookVersion` is an `int` parameter on
  another module's action, not a read. ⛔ `grep -niE 'version' app/app/Modules/X-163/capabilities.php` →
  **ZERO**. Undeclared + unwired = **CAPABILITY**.
- **`portal_views` — the verdict `TEST-ONLY-READ` holds; the CLAUSE was FALSE.** The report claimed the
  grep *"returns `PortalActionHandler.php:22` and nothing else"*; it returns **eleven** hits and **two**
  writers, and the missed one is the production-reachable one — `PortalViewAction.php:32`, called from
  `CustomerfacingPortal.php:27` in `mount()`. ⛔ `grep -nE 'view|View|seen|audit|track'` over X-172's
  `capabilities.php` → **ZERO**. Also a **CAPABILITY**.

⚠️ **PB-142's shape verbatim: a pattern matching reads and writes alike hands the reader a list to
classify, and the classification is where it fails.** ⭐ Two rows small enough to re-measure, one clause
false — which is the whole argument for re-measuring a graded column whenever it is small enough to.
⛔ **SPENT. Do not re-derive.**

## ⛔⛔ Trap added 2026-09-09 03:3x — a rubric that grades by DIRECTORY cannot see REACHABILITY, and the coder caught it

PB-146's item-3 rubric defined `READ-IN-PRODUCTION` as *"a non-test path reads it"*. The coder graded
`Allowance` and `RateVersion` that way and then wrote, unprompted, that both readers —
`AllowanceLookupAction:22`, `RateLookupAction:20` — *"are orphaned … exclusively called from
`app/tests/`"*. ⭐ **Correct, and already corroborated by this lane's own PB-143 table**, which grades
`AllowanceLookupAction` `TEST-ONLY`.

⭐⭐ **This is PB-142's CHAIN lesson arriving on the READ side: "a reader exists" is one hop short of "a
human can reach the reader", exactly as "a writer exists" was one hop short of "a human can reach the
writer".** The rubric keyed the verdict on the reader's **path**, a property that cannot express
reachability at all — the same failure as PB-145's clause answering an easier question than the verdict
asked. **Third consecutive wave in which a reviewer's disclosure rule needed narrowing after being
satisfied literally.**

⛔ **And no re-grade was briefed**, for PB-145's reason: the chain question is already answered for these
rows by PB-143's recorded table, so re-running it would re-derive a spent instrument under a new name.
**The correction lives in the next rubric's wording, not in a wave.**

## ⭐ Trap added 2026-09-09 03:3x — a vocabulary that cannot express a row forces the grader into the nearest WRONG value

Drafting PB-147's tenant-scope sweep, the first vocabulary was three-valued — `SCOPED` / `PINNED` /
`UNPINNED`. Measuring the hard half before briefing (PB-145's discipline) killed it:
`grep -rLE 'business_id'` over the ten `Ui/` directories returns three PHP classes, and two of them —
`X-175/Ui/ByDesign.php` and `X-175/Ui/CustomerfacingNone.php` — are **four-line static shells that run no
query at all.** Neither is `SCOPED`, `PINNED` or `UNPINNED`; a three-value grader must file them under
the nearest wrong one.

⭐ **The generalisation: PB-132's destroyed-rows failure has a drafting-stage twin.** There a defined
value went ungraded and its rows were lost; here an *undefined* value would have forced its rows into a
class that misdescribes them — and `NO QUERY` misfiled as `SCOPED` is a clean sweep that quietly counts
two non-measurements as evidence. ⭐ **Enumerate the vocabulary against the actual population before
briefing it, not against the question.**

⛔ Recorded with it: **X-175's two shells are NOT a finding.** They are declarations that the module has
no data surface, not screens that failed to load one — briefing them would be fixing working code, which
is PB-127's mistake.

## ⛔⛔ Trap added 2026-09-09 04:0x — a verdict about a FRAGMENT cannot carry a claim about the WHOLE, and `UNPINNED` is the worst place to learn it

PB-147's tenant-scope sweep returned two `UNPINNED` rows out of 23 components / 64 queries. **Both
verdicts are correct about the query and both are disarmed by a line the clause never mentions.**

- **`X-82/Ui/RateRegistryView`.** The clause said *"`Tenancy::id()` is in `mount()` but is **not on the
  path** of the query `Rate::find($rateId)`."* ⛔ **False.** `:57` is the query and **`:58` is
  `if (! $rate || $rate->business_id !== $this->businessId) { return; }`** — the `#[Locked] businessId`
  (which *is* `Tenancy::id()`, `mount():28`) compared **before `$rate` is used for anything**.
  `setInlineRate(int $rateId)` is a browser-callable Livewire method, the textbook IDOR shape, **and it
  is guarded.**
- **`X-172/Ui/CustomerfacingPortal`.** The clause omits why the row is safe *by construction*: the token
  **is** the credential (`PortalLinkAction:30`, `Str::random(40)`), the caller is an unauthenticated
  customer so there is no tenant to scope to, and **all four derived queries are pinned to
  `$link->business_id`** (`:86 :96 :104 :110`).

⭐⭐ **The generalisation, and it is the FOURTH instrument in a row with the same disease.** PB-145's
clause answered **one conjunct** of a two-conjunct verdict; PB-146's rubric keyed on **directory** where
it meant **reachability**; this one keys on **the query** where the question is **the component**.
⚠️ Each time the column came out right and the clause could not have told you so — the column was right
because the reviewer pre-measured, **not** because the instrument works. ⭐ **Ask of any rubric: is the
property it keys on capable of expressing the question it is asked?** ⛔ And a rubric that grades
fragments must carry a "what guards it downstream?" column, or its highest-stakes verdict is the one a
reader will act on wrongly.

## ⭐ Recorded 2026-09-09 04:0x — two `PINNED` rows, one clause shape, and NOT the same chain

`DispatchBoard:86`'s `$jobIds` is `$assignments->pluck('job_id')` off
`DispatchAssignment::where('business_id', …)` at `:78` — **a genuine query-derived chain.** `Map:39`'s
`$jobIds` comes from **`$route->stop_order`, a stored jsonb array** (`:31-37`) — **not derived from a
query at all**, so the pin is only as good as whatever wrote the column.

⛔ **Not work:** `RouteOptimiseAction` is `TEST-ONLY` by PB-143's table, so nothing in production writes
`stop_order`. **latent + no wrong value = RECORD**, with both halves, so a future tick re-finding the
unscoped `whereIn` gets the ruling and not the finding. ⭐ The durable half: **`PINNED` via a derived
collection must name the DERIVATION, not the `#[Locked]` root** — two rows can share a clause shape and
differ in the only thing that matters.

## ⭐⭐ Recorded 2026-09-09 04:0x — FOUR dimensions cleared in one tick, with the counts, and that IS the result

Scope-first, per the twice-recorded clearance-scope failure. ⛔ Falsifiable in one `ls` or one `grep`.

| dimension | population | outcome |
| :--- | :--- | :--- |
| tenant scope of every `Ui/` query | **23** components, **64** queries | 2 `NO QUERY` · 10 `SCOPED` · 9 `PINNED` · 2 `UNPINNED`, both disarmed above |
| `mount()` role guard **present** | 23 | **21/21** with a data surface guarded; 2 are X-175's shells; the portal is correctly public |
| a **real GET** beside `Livewire::test()` | 23 `Screens/*ScreenTest.php` | ⭐ **already universal** |
| the guard's **guest** refusal arm | owner-only screens | **universal** — `Livewire::test(X)->assertForbidden()`, no `actingAs` |

⭐ The third row deserves its own sentence: this file's field notes carry *"`Livewire::test()` never
renders the layout — every `/admin` screen 500'd on `main` for as long as the admin shell existed"* and
*"any new screen needs one real `GET` asserting `assertOk()`."* **Measured: the prescription is already
implemented on all 23, by Track 1's `surfaces:generate`**, which emits `$this->get(route(…))->assertOk()`
**and** `Livewire::test(…)->assertOk()` in the same method. ⛔ **SPENT — do not re-derive.**

⛔ **Four clean dimensions in one tick is precisely the moment this file warns about** — the temptation is
to loosen a criterion until something fires. ⭐ **The instrument moved instead**, and the fourth row is
what it moved onto.

## ⭐⭐ Trap added 2026-09-09 04:0x — PB-115 arrives on AUTHORISATION, and the untested arm is the REFUSAL

The guest arm is universal; the **wrong-role** arm — an authenticated user holding a role the guard is
supposed to refuse — exists on only five screens (`MapTest:34`, `MarginByJobTest:35`,
`StockByVanTest:39`, `TimesheetsTest:33`, `ReordersTest`). **`X-82/Ui/RateRegistryView` has none.**

Every authenticated seeding in `RateRegistryTest.php` (`:25 :37 :62 :88 :137 :158 :175`) and
`Screens/RateRegistryViewScreenTest.php` (`:17 :28`) is `UserRole::Owner` or `UserRole::SuperAdmin` —
**both ALLOWED by `mount():27`.** ⭐⭐ **So the whole `hasRole(Owner, Manager, SuperAdmin, OpsAdmin)`
clause can be deleted and every X-82 test stays green.** By this lane's own bar — *what one-line mutation
makes this red? none ⟹ padding* — **the guard is pinned by nothing**, on the lane's only
`OpsAdmin`-admitting screen, which also **writes**.

⭐ **The generalisation: PB-115's method — *grep every test seeding of a boolean; if they all pass the
same value, that column has one untested arm* — transfers to a ROLE LIST, where the untested arm is the
refusal.** ⚠️ And it is exactly the failure this file's own field note names: *"widening its gate leaves
the test green."*

⛔ **The fix is a TEST, never the guard.** `mount():27` is correct; a `-` line in `RateRegistryView.php`
would be buying a green test with a CHECK change. ⛔ And the role must be one the guard actually refuses
— **`Staff`**, never `Manager`/`OpsAdmin`, which are *allowed* and would assert the opposite of the
contract.

## ⭐⭐ Trap added 2026-09-09 05:0x — PB-115 on a role list has an ADMIT side, and three consecutive waves pinned only the REFUSAL

The entry directly above transferred PB-115's method to a role list and named the untested arm as **the
refusal**. PB-147 found it, PB-148 proved the shape, PB-149 applied it to seven screens. ⭐ **All three
waves asked only "does the guard refuse the right people?"** Measured lane-wide at PB-149's review:

```
UserRole::Owner  87 seedings · UserRole::Staff 28 · UserRole::SuperAdmin 11 · UserRole::Manager  0
```

**`UserRole::Manager` is created by ZERO tests anywhere under `app/tests/Modules/` — in any track** (its
only four appearances in the whole test tree are under `app/tests/Feature/`). And **all eleven of this
lane's `mount()` guards name it.** `User::hasRole(UserRole ...$roles)` is **variadic**, so removing one
role is a one-token edit that compiles, passes phpstan and passes pint. ⛔ **Delete `UserRole::Manager`
from all eleven guards and the entire lane stays green** — while every manager in every tenant silently
loses eleven screens, including price confirmation, the dispatch board and timesheet approvals.

⭐ **It is a real contract, and that discriminator was measured BEFORE briefing:** `UserRole::Manager`
appears **31 times in production `app/app/`** and the enum documents it as *"Day-to-day operation,
including automation settings."* ⛔ Had it been dead in production too, pinning it would have been
pinning a fiction — check that first, every time.

⭐⭐ **The generalisation: a `hasRole(A, B, C)` call is a SET, and a wrong-role arm proves the COMPLEMENT
is refused while proving NOTHING about any individual member.** A guard-clause member no test exercises
is padding **inside the guard**, by this lane's own bar — *what one-line mutation makes this red? none ⟹
padding.*

⚠️⚠️ **And the shape is why three waves missed it, which is the durable half: each wave's instrument was
the previous wave's instrument pointed at MORE ROWS.** The population grew 1 → 7 and the question never
did. ⭐ That is the documented slide's quieter cousin — not a criterion **loosened** until it fires, a
criterion **frozen** while the row count does the work of looking like progress. ⛔ When a wave is the
last wave with a bigger population, ask what question has not been asked of even one row.

⭐ Ruled at PB-150 as a **coverage** defect exactly like PB-148's: the guards are correct, so ⛔ a `-`
line in any `mount()` is a `BLOCK`, the assertion is a bare `assertOk()` (the claim is *the guard admits
a manager*, not *the screen renders correctly*), and the mutation deletes **one argument**, never the
clause — deleting the clause also reddens the `test_staff_is_forbidden` arm and isolates nothing.
⭐ **And the test must be shown GREEN un-mutated first:** `Expected 200 but received 403` is producible
by the mutation *and* by a tenancy setup that never ran, so the green is the only thing that tells them
apart.

## ⭐ Trap added 2026-09-09 05:0x — a size guard keyed on a PROPERTY is a SELECTION rule and bounds nothing

PB-149's 1c read *"If more than six screens survive, write tests for the ones whose component has a
write path."* Eleven survived; the property selector returned **seven**. ⭐ The coder answered the rule
exactly as written and the wave came out one larger than the guard existed to permit.

⚠️ **PB-143's fix removed the cap along with the defect.** That ruling correctly killed *"the first
six"* — a positional selector applied to a filtered result silently returns the unfiltered head — and
replaced it with a property. **But a property selector has no cardinality at all**, so "more than six ⟹
select by property" *reads* like a bound and is not one.

⭐ **The fix, standing: a size guard names the property AND pre-declares the expected count, and a count
that differs is a STOP-and-report.** Then the bound is checkable by arithmetic before the wave starts —
the same move that made `SCRATCH BEFORE`/`AFTER` work where the prose prohibition had failed twice.
⛔ Never leave a bound to be noticed by the reviewer afterwards.

⛔ Ruled **not** a shortfall and **not** a `BLOCK`: seven pure test appends to seven distinct files
touching zero production code cannot entangle, which is the whole reason one-component-per-wave was
overridden for this shape at PB-148.

## ⛔⛔ Trap added 2026-09-09 05:2x — PB-129's duplicate detector reads `failed` and CANNOT SEE `errors`

Every *"nothing else reddened"* verdict this lane has ever written was computed from a mutation log's
**`failures`** array. PB-150's own artifacts show the two counts are independent channels, same tree,
same suite, five minutes apart:

```
pb150-w1.red.log   "failed":2   "errors":4    ← +2 Authorize.Net E00040, external
pb150-w2.red.log   "failed":2   "errors":2
pb150-gate-1.log                 errors 2
```

⭐ **The durable half: a mutation that made some other test ERROR rather than FAIL — a null dereference,
a missing binding, a container resolution that now throws — leaves `failed` unchanged, and the detector
reports "nothing else reddened" and is WRONG.** ⛔ From PB-151 every mutation reconciliation reads
**both** counts against the un-mutated baseline and names the cause of each difference.

⚠️ Same shape as PB-135's *quoting the floor line is not reconciling it*, one field over. ⭐ And note
what it took to see it: **a wave where the two numbers happened to disagree for a reason that did not
matter.** An instrument's blind spot is invisible for exactly as long as the thing it cannot see stays
at zero — ⛔ so do not read a long clean run as evidence the channel is being watched.

## ⛔⛔ Trap added 2026-09-09 05:2x — "eleven" was a SCOPED measurement that six waves read as the population, and it falsified the previous wave's headline note

PB-147 enumerated **eleven** role-guarded screens. PB-148/149/150 then reasoned about "all eleven
guards", and my PB-149 block wrote *"`SuperAdmin` is admitted by `Pricebook` **ALONE**"*. Measured
2026-09-09 with one grep over all ten module `Ui/` directories: **the lane has TWENTY role guards, and
FIVE of them name `SuperAdmin`.**

⭐ **This is the clearance-scope failure for the FOURTH time in this file** — after `is_sample` on
X-166, the SPENT entry for X-167, and ruling 8's stale `UNRESOLVED`. ⚠️ **And it is the first one that
falsified a NOTE rather than a clearance:** PB-150's NOTE 1 said *all eleven guards name `Manager`* and
treated four rows as the frozen-instrument residue. **The residue is thirteen guards**, because the
population was never eleven.

⭐ **The fix is one clause in the prose: write the scope INTO the count.** *"Eleven, of a population I
did not enumerate"* is a different claim from *"eleven"*, and only the first one degrades safely when a
later tick reads it. ⛔ A bare cardinality in this file will be read as exhaustive by me, weeks later.

⭐ **The tell that reopened it was mechanical and is reusable, exactly as the X-166 component count was:
grep the whole population fresh before reasoning about "all N of them", and compare N.** One command.

⭐ **The population, measured and now written down so it is not re-derived:** twenty `hasRole(` guards —
X-163 `ConfirmationScreen:26` `Pricebook:48` `DailyPricingDigest:23` · X-162 `Map:22`
`DispatchBoard:28` · X-165 `Plans:29` `Members:23` · X-166 `BySource:23` `ByTech:24` `MarginByJob:22`
`ByService:23` · X-167 `Reorders:23` `StockByVan:24` · X-168 `TimesheetsView:26` `ApprovalsView:24`
`OwnHoursView:23` · X-171 `SyncFailureRate:22` `StafffacingApp:37` · X-82 `RateRegistryView:27` ·
X-175 `StafffacingAssistantPanel:25`. ⚠️ **Scope of this count: `hasRole(` in `app/app/Modules/*/Ui/`
across the lane's ten modules only.** Guards elsewhere — middleware, policies, other tracks — are
**not** measured by it.

## ⛔ Ruling — 2026-09-09 05:2x — an unexercised guard member is pinned only where the ADMISSION is not itself in question

Three cells failed the per-member matrix: `SuperAdmin` on `X-163/Ui/Pricebook.php:48`, `SuperAdmin` on
`X-168/Ui/OwnHoursView.php:23`, and **`OpsAdmin` on `X-82/Ui/RateRegistryView.php:27`** — the last
seeded **zero times in the entire `app/tests/` tree, in any track**.

**RULED: pin the two `SuperAdmin` cells; MEASURE the `OpsAdmin` cell and pin nothing.**
`UserRole.php:88/146` documents `OpsAdmin` as *"Operations admin"*, `:254` gives it
`ImpersonationMode::View`, and `:170/:193/:213` group it with `BillingAdmin`/`SupportLead` — **platform
staff, not tenant staff** — while `RateRegistryView::setRate():31` carries **no guard of its own**, so an
admitted `OpsAdmin` writes a tenant's rates.

⭐⭐ **The generalisation, and it is the converse of a rule this lane already has: PB-149 ruled that
asserting a role a guard REFUSES asserts the opposite of the contract. Asserting an admission that ought
to be a REFUSAL is exactly as wrong, and it is harder to notice — the test goes GREEN.** ⛔ A pinned
admit arm is a test a later design decision has to **delete**, and this file already treats a deleted
assertion as the shape it distrusts most.

⭐ **The discriminator, and it is why `Manager` was pinned without hesitation on the same day:** the enum
documents `Manager` as *"Day-to-day operation"* and it appears **31 times in production `app/app/`** — a
tenant role, unambiguously admitted on purpose. `OpsAdmin` has no such evidence either way. ⛔ **Where
the contract is unambiguous, pin it; where the admission is itself a live design question, measure it
and rule next wave.** ⚠️ ⛔ Do not read this as licence to defer any inconvenient cell — the test is
whether a **documented** reading of the role settles it, not whether the reviewer feels uncertain.

⛔ **Recorded, not briefed: `Manager` is unexercised on THIRTEEN of the twenty guards** (every one
outside PB-150's seven). It is the same question PB-150 already answered, on more rows — the documented
frozen-instrument slide — so it is written down here with its count rather than turned into a wave.
