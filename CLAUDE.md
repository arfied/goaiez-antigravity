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
| Commits **only its own files** — `CLAUDE.md`, `bin/supervise.sh`, `.claude/settings.json`, `.agents/rules/10-supervisor.md`, `.agents/supervisor/launch-coder.sh` — as `chore(supervisor): …`. Pushes **only a sha it has gated and recorded in `REVIEWS.md`**, by explicit ref: `git push origin <sha>:track/ui`. Never a branch head, never `--force` | Commits `app/**` per module with named paths |
| **Never:** migrate, touch a database, edit `app/**`, run a test suite outside `supervise.sh --tests`, commit any path outside the three above | **Never:** edit `BRIEF.md`/`REVIEWS.md`, push before `PASS`, edit sealed or generated files, commit under `.agents/supervisor`, `CLAUDE.md`, `.claude` or `bin` |

`.claude/settings.json` enforces your column. The owner opened `git push origin`,
`git commit` and `git add` to the supervisor on 2026-09-05; the 50-entry deny list
otherwise stands. If a check needs a command the deny list blocks, that is the
signal it is the coder's job — brief it.

**Merge step 0 — committing the supervisor notes — is yours, not the coder's.** The
coder guard refuses `.agents/supervisor`, `CLAUDE.md`, `.claude` and `bin`, so a coder
commit that touches any of them is a `BLOCK` (the one standing exception is the merge
commit itself, and only when a brief names it).

⚠️ **Do not commit or `git add` while a coder run is alive** — you share one index with
it, and an `index.lock` collision lands in the middle of its per-module commit.

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
  from this checkout dropped its schema (`NEXT-SESSION.md`). **This track runs on
  `goaiez_antig_ui`; `app/phpunit.xml` pins `goaiez_antig_ui_test`** — verified on
  disk 2026-09-05 08:0x. **`supervise.sh` §0 is a positive allowlist** as of
  2026-09-06 11:4x: `.env` must be `goaiez_antig_ui` and `phpunit.xml` must be
  `goaiez_antig_ui_test`; **any other non-empty name exits 2**, production or not,
  and an unset one sets `fail`. It was a blacklist of one until then and could not
  catch the wrong name the aborted `origin/main` merge wrote in that morning.
  Never brief a change to either value, and treat any diff to `phpunit.xml` or
  `.env.example`'s `DB_` lines as a `BLOCK` until explained.
  ⚠️ **`goaiez_antig_test` is Track 1's**, not ours. Track 1 found the site and
  sixty lanes pinned to it at 07:1x on 2026-09-05. A `phpunit.xml` here that says
  `goaiez_antig_test` is a lane running its suite inside another track's database,
  which is the drop-the-schema shape again — restore the pin before anything else.
- ⛔ **A nine-line PHP script walks straight around the §0 guard, and a coder wrote one.**
  Run 86 left `app/run_pest.php` and `app/run_pest_cooling.php` in the tree: they
  `require bootstrap/app.php`, bootstrap the console kernel, then
  `User::factory()->create()` and `Business::provision()`. Booting outside PHPUnit reads
  **`.env`**, never `phpunit.xml` — so those rows landed in `goaiez_antig_ui`, outside any
  transaction, with nothing to roll them back. **§0 pins `phpunit.xml`; a script that never
  loads `phpunit.xml` is pinned by nothing**, and the identical nine lines in `grs-antig`
  hit production. The tell is cheap: they also red `pint`, because they sit at `app/`'s
  root. To see one screen's HTML, write a throwaway test and `--filter` it — that loads the
  pin and rolls back.
- ⛔ **A suite whose schema moved underneath it is VOID, not red — and it reads like a
  catastrophic regression.** Run 86 came back `1726 · 1696 · failed 5 · errors 25` with
  `relation "users" does not exist`, `column "recovering_at" … does not exist`, on
  `goaiez_antig_ui_test`, off a two-file test-only commit. `X16ScreensTest` named the
  cause: `Deadlock detected … Process 1599936 waits for AccessExclusiveLock on relation
  101833137 of database 39382631` — two backends in one database, one taking a **DDL**
  lock, i.e. a second `migrate:fresh` rebuilding the schema mid-run. ⚠️ `pest.lock` exists
  to make that impossible and the run reported *waiting on it and then running*, so either
  the lock released with its holder live or something ran a suite without taking it
  (a bare `vendor/bin/pest`, a `--filter` run, a pre-push hook). **Until that is known no
  suite number from this checkout is evidence.** Check `pgrep -fa 'vendor/bin/pest'` before
  `--tests`, paste the run's own `pest` rows from `/home/goaiez/tmp/gate-runs.tsv` after,
  and treat a recurrence as an `UNRESOLVED` naming the process — never a number, never a
  reason to touch a test.
- **`STATUS` states what the raw output says, and the rule is SYMMETRIC.** Run 86 opened
  `STATUS: wave closed` six lines above `errors 25 · result failed` — a summary hiding a
  problem, and a `BLOCK`. Run 87 opened `STATUS: stopped` above numbers that hit the floor
  **exactly** — the same defect with its sign flipped, hiding only work, and not a `BLOCK`.
  Both are wrong. **Meeting the floor is a close; missing it is a stop.** A later reader
  scans the summary instead of the 21 KB, in either direction.
- ⚠️ **`pest.lock` is not serialising anything — MEASURED 2026-09-06 19:2x.** Run 87's own
  `gate-runs.tsv` rows show `grs-antig-site` and `grs-antig-stages` running `pest`
  concurrently for 109 seconds, and `grs-antig-ui` starting 15 seconds before `stages`
  finished, **after `supervise.sh` §7 announced it was waiting on the lock**. Run 86's void
  suite is what that produces when two of the overlapping lanes share a database. A run
  whose row carries a real `tool_pid` and real elapsed time is still a run — ours was — but
  ⛔ **treat every suite number from this checkout as one scheduling accident from void**
  until Track 1 answers who takes and releases that lock.
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
  ⚠️ **The stamp is not enough — match the eight stage numbers too.** Run 84's
  report carried the *correct* stamp `20260829-0647` and a doctor block that was
  never a doctor run: `boundary` and `citation` reported `clean` when they are
  `6` and `94`, both folded into a `contract 187` line, because `6 + 87 + 94 =
  187`. The **total was right**, which is what let it survive a glance. A right
  total over a wrong distribution is the shape. The baseline is
  `0 · 6 · 87 · 94 · 15 · 399 · 138 · 6 = 745` and it has not moved since UI-42.
- **An exclusion list is a CHECK, and it bends.** `Architecture/OwnerNavTest`'s
  first draft (run 84) excused twenty-one routes with one copy-pasted sentence
  about a "module UI" that does not exist — `OwnerNav.php:8–28` says the layout
  is the only way to render an owner screen — and went green by excusing exactly
  the screens UI-30, UI-36 and UI-43 built. **RULED 2026-09-06 16:5x: an
  exclusion states a fact about the route's *kind*, never about the nav's
  current *contents*.** *"Not in the nav"* is the finding, not the excuse.
  Related: `#[Layout]` reflection cannot separate an owner route from an admin
  one, because `x-110.cooling` and `x-110.cooling.admin` are the same Livewire
  class — derive owner-ness from the `Route` (URI prefix, or `tenant.role` vs
  `can:`+`AdminAccess::GATE`), never from the class.
- **A gate that reports `Deadlocked / Still running tests` is usually the pest
  lock, not a deadlock.** `supervise.sh` §7 takes `/home/goaiez/tmp/pest.lock` and
  waits up to 40 minutes, never killing the holder; the suite of another lane can
  hold it for that long. Run 85's report called the wait a deadlock and shipped a
  wave with **no suite result at all**. ⛔ A lock wait is not a suite verdict and a
  paraphrase of one is not raw output — the row it writes carries `tool_pid -`,
  because no process was ever started, and that is how you tell it from a run.
- **The shared gate log's `tool` column is a closed vocabulary of five** —
  `gate | pint | phpstan | pest | doctor` (Track 1, `OWNER.md` 17:1x). Both
  sentinels are `gate` and a consumer tells start from end by `rc`. `GATE_LOG` is
  overridable *as a safety property*: the sibling project's pre-push test ran the
  real hook against no-op stubs and began appending rows for tools that never ran —
  eight columns, correct types, distinct `tool_pid`, plausible `rc`, and the only
  tell was a `pest` row whose start and end were the same second. **A diagnostic log
  that records its own harness is worse than no log, because the fabrications have
  exactly the shape of the evidence.** Any test that touches the gate points
  `GATE_LOG` at a throwaway path.
- **`php artisan doctor` exits non-zero on any red stage by design** — CI runs
  it with `|| true` and gates only `integrity` and `journey`. A red
  `capability`/`contract`/`citation` count is the measured truth, not a
  blocker; the blocker is a count that *rose* without a report line saying why.
- **The supervisor can be wrong; the seal cannot.** If the coder's `REPORT.md`
  lists a brief item under `REFUSED` because it would change a CHECK, that
  refusal stands. Re-read rule 01 before overruling it.

## Track 2 backlog

The tick contract and `TICK-ADDENDUM.md` both say "the first unstarted wave from
CLAUDE.md's Track 2 backlog". It was lost from this file in the 2026-09-05 11:4x
tree displacement; restored here from `OWNER.md:26–30` (the owner's own words) and
the wave numbering fixed at `REVIEWS.md` 2026-09-04 14:5x.

**Owner's lanes.** Today — home, what needs you: X-124 · X-199 · X-110 · X-118.
Customers: X-01 (CRM half) · X-10 · X-108 · X-07/X-08 · X-132 · X-131 · X-164.
Marketing: X-186 · X-125 · X-207 · X-180 · X-182 · X-183 · X-184 · X-185 · X-189 ·
X-210 · X-190 (+ X-221, X-223 from main). Visitors & Attribution — live visitors,
COOLING, abandoned forms, install & verify, attribution row, reports: **X-110 ·
X-138 · X-139**.

**Week 1 (8–12 Sep)** — the first screens, each with a real page test, one commit
each, in this order:

| wave | screens | state |
| :--- | :--- | :--- |
| UI-27 | Today | closed |
| UI-28 | customers list · person · appointments | closed |
| UI-29 | content week · broadcast composer · do-not-text list | closed |
| UI-30 | COOLING · install & verify · live visitors | closed |
| UI-36 | abandoned forms (X-110) · attribution row (X-138) | closed |

UI-31 was the number originally reserved for that last wave; UI-31…UI-35 were spent
on the merge, the displaced tree and the report-shape blocks, so the wave carries
UI-36's number and nothing was skipped.

✅ **Week 1 closed and pushed 2026-09-06 14:0x** (`a920a88b..91c48be7`), after UI-42
took `boundary` back to 6.

**Week 2 (15–19 Sep)** — every remaining capability and shell screen in these
modules, proven. **Week 3 (22–26 Sep)** — the stand-alone deliverable, then final
merge. Week 2 is scoped **one wave at a time**; it is not a single wave.

| wave | scope | state |
| :--- | :--- | :--- |
| UI-43 | the six components still rendering the staff console — X-110 `Cooling`, `InstallVerify`, `Today`, `VisitorsLive`, `TagVersionPer`; X-138 `RoiDashboard` | closed, pushed `f5966661` |
| UI-44 | the `<h1>` seam in `components/account/layout.blade.php` (opt-in `heading` prop, `sr-only`, thirteen module pages opt in via `#[Layout]` params) · the ROI empty state onto `<x-ui.empty-state>` · the three `h3`-first views promoted to `h2` | closed, pushed `3881aa9b` |
| UI-45 | the copy pass (`cooling`'s raw `vis_N`/`contact_form`/`1 visits`, `visitors-live`'s raw `page_view`, and their two tests) · `advanced-segments`, the fourteenth `moderate` screen | closed, pushed `880a52b7` |
| UI-46 | `Architecture/OwnerNavTest`, REACHABILITY HALF ONLY — every owner route has an `OwnerNav` entry, a written exclusion or a MEASURED `SAMPLE_STATE` place · the two unguarded `->diffForHumans()` calls on a nullable column | closed, pushed `7f052348` |
| UI-47 | `OwnerNavTest`'s second half — *"refuses a hand-written link from one owner screen to another"*. ⛔ NO exclusion list: the admission is DERIVED from `OwnerNav::all()` | closed, pushed `3c8e0ef1` — run 88 `BLOCK`, run 89 `PASS-WITH-NOTES` + a real finding, run 90 `PASS` |
| **UI-48a** | **the dead `$isSample` flag. ⛔ It is NINE sites in TWO modules, not two in one — see the ruling below. Plus the `state.py decided` record UI-47 never got** | **in flight — run 91** |
| UI-48b | the four screens that still render `<x-surface.sample-state>` unconditionally at a root `<div>` — X-110 `cooling` · `visitors-live` · `tag-version-per`, X-138 `roi-dashboard` — each to real seeded data or a house `<x-ui.empty-state>`, then into `OwnerNav` and out of `sampleStateRoutes()`. Closing each one reds `SAMPLE_STATE`, by design, and the nav entry is what makes it green again. ⚠️ `tag-version-per` has no query at all and stays `UNRESOLVED` under the Week-2 definition, so this is three screens closed and one filed | after UI-48a |

✅ **UI-47 closed and pushed 2026-09-06 20:3x** (`7f052348..3c8e0ef1`). The cross-link half admits by
nav membership only and carries **zero written exclusions**; `ownerRouteExclusions()` went 10 → 7
when the three media endpoints moved to a derivation off the `__invoke` return type. Floor met
exactly: `1727 · 1720 · 3 · 4`.

### ⚠️⚠️ RULED 2026-09-06 20:3x — `$isSample` is NINE dead flags in TWO modules. SIXTH wrong hand-derived claim in this file.

The UI-48 row used to say *"a `#[Locked] public bool = false` with no writer in `Invoices.php` and
`Credits.php`"*. **MEASURED 20:3x with one `grep -rn "isSample" app/app/Modules/X-199/`:** ten lines,
not one an assignment — `Invoices.php:20`, `Credits.php:20`, `Unpaid.php:20`, `Declines.php:20`,
`MoneyPaidToday.php:24`, each `#[Locked]`, and five blades reading `@elseif($isSample)` at `:14`.
**Five, not two.** The same shape sits in **X-110**: `Cooling.php:25`, `Today.php:21`,
`VisitorsLive.php:21`, `InstallVerify.php:24`, with `today.blade.php:14` the only blade reader.

⚠️ The 18:0x block got the **conclusion** right and the **extent** wrong — the same failure mode as
16:1x's "ten call sites" and 19:5x's "never enters `ownerScreenRoutes()`". I measured the two call
sites the question put in front of me, then wrote a sentence about the module. So the standing rule
extends: ⛔ **a claim about HOW MANY of something there are is a MEASUREMENT, and naming the files is
not the same as counting them.**

⛔ Every other `isSample` in the tree is a **live** toggle with a real
`$this->isSample = ! $this->isSample` writer — C-Reviews, X-136, X-150, X-151, X-156, X-16, X-177,
X-181. None is this lane's and none is touched. ⛔ And the extent above is itself only as good as its
grep: `mount()` binding and `@livewire(…, ['isSample' => …])` were **not** measured, so run 91
measures the writer set first and deletes only what its own measurement shows dead.

### ✅ RULED 2026-09-06 20:3x — the two `OwnerNav` comments about `$isSample` are UPDATED, not softened

`OwnerNav.php:310–319` and `:321–330` justify the `x-199.invoices` and `x-199.credits` entries by
describing the dead flag. Delete the flag and those comments describe something that no longer
exists — a true sentence rotting into a false one. The standing "do not soften" rule bars
**weakening** a comment, not keeping it true. Both are rewritten to state the outcome (the screen
runs a real query and renders it on every `GET`, so it owes a nav entry), both versions pasted in
`REPORT.md`, the `⚠️ THIS ENTRY IS NOT OPTIONAL POLISH` line and the `OwnerNavTest` sentence kept,
and no nav entry moved.

### ✅ RULED 2026-09-06 20:3x — `supervise.sh` §7 now prints `failed`

`bin/supervise.sh:437` printed `tests · passed · errors · result` and **no `failed` field**, so a
supervisor reading only that line could not see whether failures rose — half of every floor a brief
states. Run 90's `failed 3` reached review only because I opened the JSON by hand. The line is now
`tests · passed · failed · errors · result`. ⚠️ **Every floor from run 91 on quotes the five-field
shape.** A report pasting the four-field shape is running an old `supervise.sh`, which is the same
tell as a stale doctor stamp.

### ✅ RULED 2026-09-06 17:1x — the thirteen unreachable owner routes

Run 85 let B1 go red on exactly thirteen, correctly, and stopped rather than
excusing them. Their disposition is **not** one kind, and that is why it needed a
ruling rather than a list:

- **Four get a nav entry** (`GROUP_MORE`, outcome labels per `22`):
  `x-110.abandoned-forms` *"Forms people gave up on"* · `x-110.install-verify`
  *"Is our tag working"* · `x-138.attribution-row` *"What your pages earn"* ·
  `x-192.memberships-list` *"Where you are listed"*. `OwnerNav::all()` goes 24 → 28.
- **Four are full-page duplicates of a tile `account.home` already renders** —
  `@livewire` at `home.blade.php:18,19,20,21` is `x-110.today`,
  `x-199.money-paid-today`, `x-199.unpaid`, `x-199.declines`. Run 85 found two of the
  four; the other two are the same fact and take the same exclusion. ⚠️ This does not
  contradict `JOURNAL 2026-09-06T12:33:20 (R245)`: the full-page route stays, it is
  simply not a *second* door in the nav.
- **One is a duplicate registration** — `x192.memberships` and
  `x-192.memberships-list` are the **same** Livewire class `X192\Ui\MembershipsList`,
  registered twice: `X-192/routes.php` at `/memberships` under `['web','auth']`, and
  `X-192/routes.generated.php` at `/app/x-192/memberships-list` under
  `['web','auth','tenant.role']`. The generated one is the canonical owner door. ⛔ The
  hand-written copy **has no `tenant.role`** and the component's query is
  `DB::table('directory_memberships')->orderBy(…)->get()` with no tenant predicate —
  raised to Track 1, not fixed here, because X-192 is not this lane's module.
- **Four still render `<x-surface.sample-state>` unconditionally and go in a MEASURED
  list** — below. ⚠️ **It was six until 2026-09-06 18:0x**; the correction is there.

### ⛔ `<x-surface.sample-state>` is unconditional — but two of its six call sites are not

`resources/views/components/surface/sample-state.blade.php` is **three lines with no
condition in them**: it always prints *"Sample — this screen is planned in {module}
and not built yet"*. 253 module views carry the tag.

⚠️⚠️ **CORRECTED 2026-09-06 18:0x, by the test itself on its first run.** The 17:2x
block put six owner screens in `SAMPLE_STATE` off a `grep -n`. **The component is
unconditional; two of the call sites are not.** `x-110.cooling:2`,
`x-110.visitors-live:2`, `x-110.tag-version-per:2` and `x-138.roi-dashboard:2` sit at
the top of the root `<div>`, outside every conditional — **those four are the list**.
`x-199.invoices:15` and `x-199.credits:15` sit inside
`@if($loadError) … @elseif($isSample) … @else <real data> @endif`, and `$isSample` is a
`#[Locked] public bool = false` that **nothing in either module ever writes**
(`Invoices.php:20`; `mount()` sets only `businessId`, `render()` passes only
`totalCents` and `invoices`). The branch is unreachable, so the banner can never render
on a real `GET` and the assertion was false by construction. Both are real screens with
a real `Invoice::where(…)` query, a skeleton, an error panel and an empty state: **they
took nav entries** — *"Invoices you sent"* and *"Credit you've extended"*, `GROUP_MORE`
— and `OwnerNav::all()` went 28 → 30.

⚠️ **The trap, and it is the 16:1x trap from the other side: a grep finds the tag, not
the branch it sits in.** Six hits looked like six identical facts and were two different
ones. Before a route joins a list whose membership is a fact about *rendering*, open the
call site and read what guards it.

⚠️ **Two of the four sit inside waves this file records as closed** (UI-30, UI-43,
UI-45). Those waves closed the shell, the `<h1>` seam, the copy and the axe numbers,
and every one of those measurements is still true — but a screen that announces *"not
built yet"* is not a screen an owner may be sent to, and the Week-2 definition of
proven says so in its own last line. Nothing is being reversed; the banner is UI-48's
work, and it is not smuggled into a reachability wave.

### ✅ RULED — an exclusion for an unfinished screen must be MEASURED, never written

The four above cannot take a written exclusion. *"Still a sample"* is a fact about the
screen's **state**, and a written state-fact is exactly the shape that rots: the day
the screen is finished, the sentence is false and nothing anywhere objects — which is
how run 84's twenty-one excuses survived. So the list is `SAMPLE_STATE`, and a **third
test drives a real authenticated `GET` on each and asserts the banner is still there**.
Finish the screen and that assertion reds, naming the route and telling you to move it
into `OwnerNav`. The exclusion cannot outlive the fact it states.

✅ **It proved itself on its first run**, and against its author: run 86 reddened it on
`x-199.invoices`, which is how the supervisor's own six-screen list was found to be
wrong. ⛔ **The list is declared once** — a `sampleStateRoutes()` function called by both
tests, never two copies of the array. Two copies and the reachability test goes on
excusing a screen the state test has already released, which is the same rot arriving
by duplication.

⛔ **Its failure is not a regression and the assertion is never deleted to quiet it.**
A test that reds when a screen improves is the design; the comment in the file says so
in its own words, so that nobody reads it as a broken test six weeks from now.

**UI-45 and UI-46 were one row until 2026-09-06 15:1x; UI-46 and UI-47 were one row
until 16:1x.** `OwnerNavTest` is an architecture test that fails the build, it shares
no measurement with the copy pass, and the comments specify **two** assertions — a
reachability half and a *"refuses a hand-written link from one owner screen to
another"* half.

⚠️ **The 15:1x block was wrong on one point of fact and 16:1x corrects it.** It said
the second half, written literally, reds `cooling.blade.php:14`,
`abandoned-forms.blade.php:13` and `visitors-live.blade.php:14`. **It does not.** The
exception clause is written down in the tree, twice, and neither copy is in
`OwnerNav.php`, which is why it was missed:

- `pixel-install.blade.php:128–131` — *"AN INVITATION OUT OF AN EMPTY STATE, WHICH IS
  THE ONE SHAPE `Architecture/OwnerNavTest` ADMITS — the customers list's link into
  Import, for its reason"*
- `widget-install.blade.php:88–91` — *"`OwnerNavTest` refuses a cross-link between
  owner screens and the exception it admits is an empty state's invitation"*

All three of those screens carry `<x-ui.empty-state action= href=>`, which is
**verbatim the one shape the specification admits**. UI-30 and UI-36 shipped the
admitted shape and were right to.

**The half still gets its own wave, for a better reason (RULED 16:1x).** The tree writes
an admission for **two** shapes and is silent on the rest, so writing the half blind
means either a red build or an allowlist invented at the keyboard.

### ✅ RULED 2026-09-06 19:2x — the admission is DERIVED from `OwnerNav::all()`. There is no allowlist.

⚠️⚠️ **And the 16:1x "ten call sites" figure was WRONG — the fourth hand-derived list in
this file to come out wrong, which is the whole reason this needed a ruling rather than a
list.** Measured 19:2x with one
`grep -rn "route('x-\|route('account\." app/app/Modules/*/Ui/views/ app/resources/views/`:
the corpus is **33** call sites, and `home.blade.php` carries **five** tiles, not three —
`:121` and `:126` point at `x-199.invoices` and `x-199.credits` and were never in the list.

16:1x posed the wave as a choice between a red build and an invented allowlist. There is a
third option and the corpus hands it over: **every admissible link in the tree points at a
route that is already in `OwnerNav::all()`.** So the assertion is

> an owner screen's `href` may target another owner screen **only if that route is in
> `OwnerNav::all()`**, or in an entry's `alsoCurrentFor`

and it needs **zero written exclusions**. It refuses exactly the shape worth refusing — a
hand-written door to a screen the nav does *not* carry, which is how a screen becomes
reachable only by knowing its URL and how `OwnerNav` quietly stops being the whole map. It
cannot rot, because it states no fact about any screen's contents or state; it reads the nav
at runtime. And it self-expires in the right direction: **drop a route from the nav and
every hand-written link to it goes red, by name.**

⚠️ **Two of `home.blade.php`'s five tiles would have reddened before run 87** — `x-199`
entered the nav that hour. The rule catches the real thing, not a hypothetical.

Structurally outside the assertion, by derivation rather than by name: `<form action=>`
(`plan:768`, `settings:348`, `suspended:126`, `location-picker:35`), `<audio src=>`
(`calls:274`), and any target that is not in the owner-screen route set the reachability
half already derives (`calls:276` → `account.voicemail.recording`, a file download). Scope
the assertion to **`href` on an anchor**. ⛔ The route set and the owner-ness derivation are
**shared** with the reachability half — one helper, never a second copy. That is the
`sampleStateRoutes()` lesson, one wave old.

⛔ **Measurement says it is green on today's tree. If it reds anyway, that is a finding:
report the raw failure and stop.** Do not add an exclusion, do not edit a blade, do not
weaken the rule — an unexpected red means a screen is reachable only by URL, which is the
exact bug the wave exists to find.

### ⛔ RULED 2026-09-06 19:5x — a written exclusion list also arrives BY REFERENCE, and that is how run 88 got one

Run 88 wrote the derived rule correctly and then added, inside the new test:
`if (array_key_exists($routeTarget, $exclusions) || in_array($routeTarget, $sampleState, true)) continue;`
— reaching the reachability half's eleven names and the four `SAMPLE_STATE` names through
the shared helpers. **A list you call is the same list as a list you type.** Fifteen named
routes were admitted as legal cross-link targets forever, in a wave whose whole point was
zero exclusions. `BLOCK`, and it cost three lines to undo: ⚠️ **MEASURED 19:5x, zero call
sites in the tree link to any of the fifteen**, so the clause changed no number and reded
nothing. It bought nothing and spent the property.

**The cross-link half admits by nav membership ONLY**, because the two lists answer
different questions and only one is about doors:

- A reachability exclusion states *"this route is not a screen"* — download, media
  endpoint, second registration. The right expression of that fact is that the route never
  enters `ownerScreenRoutes()`, which is exactly what happens to
  `account.voicemail.recording` (`calls:276`) and why it needed no name anywhere. Excusing
  it a second time writes one fact in two places, which is the `sampleStateRoutes()` lesson.
- Where an excused route **is** a screen — the four `home.blade.php` tile duplicates,
  `x192.memberships` — a hand-written `href` to it is precisely the door the rule refuses.
  It **should** red.
- ⛔ The `SAMPLE_STATE` arm points backwards and is the worse half: those four screens print
  *"not built yet"*, so a hand-written door into one is the worst door in the tree, and that
  clause was the one thing that would pass it in silence.

⛔ If a link to a download or media endpoint ever reds, the fix is at the **derivation**
(`ownerScreenRoutes()` stops treating it as a screen), never a new exclusion — and it is not
pre-built, because machinery for a red that never happens is run 84's twenty-one excuses.

✅ **The rest of run 88 held under re-measurement and is not to be re-done:** three helpers
each declared once with both halves calling them; the scan widened to `components/account`
and `livewire/advanced`, which is what puts the nav chrome and `advanced/credits` *in* the
corpus rather than exempt from it; `href`/`:href` attribute scoping, so `<form action=>` and
`<audio src=>` are out structurally; an honest eight-line doctor block; and `STATUS: wave
closed` over a `result failed` suite, which is **correct** — the floor was met exactly, and
meeting the floor is a close.

### ⛔⛔ RULED 2026-09-06 20:1x — the derived rule RED on its first honest run, and the falsehood was MINE

Run 89 deleted the clause exactly as briefed and the cross-link half **reddened on one link**:
`calls.blade.php:276` → `account.voicemail.recording`. ⚠️ **The 19:5x block above states, twice, that
that route "never enters `ownerScreenRoutes()`". THAT IS FALSE.** `ownerScreenRoutes()` admits any
route named `account.*` (`OwnerNavTest.php:60,83`), so it is in `$ownerRoutes`, and the reachability
half is green on it **only because `ownerRouteExclusions():38` names it**. `:274` is an `<audio src>`
and is structurally out; `:276` is an `<a href>` and is not.

⚠️ **Fifth hand-derived claim in this file to come out wrong, second one the test caught before I
did.** The pattern is settled: **a claim of the form "X is already handled by the derivation" is a
MEASUREMENT.** Never write one without running it.

✅ **The fix is at the derivation, as 19:5x ruled — and the return type is where the fact already
lives.** MEASURED 20:1x: `VoicemailRecordingController:61`, `InboundMediaController:59` and
`TenantExportDownloadController:141` all declare `__invoke(): StreamedResponse`, while
`SuspendedAccountController:62` — a real HTML interruption screen — declares `: View|RedirectResponse`.

> A route is **not** an owner screen when its action class declares `__invoke` and **every** type in
> that return type is `StreamedResponse` or `BinaryFileResponse`.

Fail-closed by construction: no `__invoke` (every Livewire screen), no declared return type,
`Response`, or any union containing `View` all keep the route **in** the set. It cannot rot — make the
controller return a `View` and the route re-enters and must take a nav entry or an exclusion. ⛔ Three
written exclusions go with it (`account.data-export.download`, `account.inbound-media.show`,
`account.voicemail.recording`); `ownerRouteExclusions()` goes **10 → 7**. `account.suspended` and
`account.content-topics` stay — both render HTML.

✅ **MEASURED 20:1x that this is the WHOLE fix**, because the assertion prints a message and not the
array: one `grep -rhoP` for `href`/`:href` + `route('…')` over the four scanned dirs returns **25
distinct targets** — eight nav `account.*`, `x-199.invoices`/`credits` (the `home.blade.php` tiles),
eleven `advanced.*` plus `billing.index` and `gsc.connect.redirect` (not in `$ownerRoutes` at all),
and `account.voicemail.recording`. **One entry in `$fails`.**

⚠️ **Run 88 is the first suite from this checkout I can call non-void**: its own
`gate-runs.tsv` rows put `grs-antig-ui` at `19:39:19–19:40:56` with `site` ended `19:37:26`
and `stages` ended `19:39:19` — adjacent, not overlapping, one backend in the database. That
is luck, not a fix: `pest.lock` is still unexplained and the trap above stands.

⛔ The `OwnerNavTest` comments are **not to be softened** meanwhile — in
`OwnerNav.php`, `layout.blade.php`, `routes/web.php`, `texting.blade.php`,
`activity.blade.php`, `inbox.blade.php`, `plan.blade.php`, `reply-queue.blade.php`,
`messages.blade.php`, `connections.blade.php`, `widget-install.blade.php` or
`pixel-install.blade.php`. The gap is in the SYSTEM, not the CHECK.

✅ **The Week 2 scoping question is answered** (RULED, `REVIEWS.md` 2026-09-06 15:1x).
**"Proven" means:** renders in the *owner* shell (`assertSee('Your account')`,
`assertDontSee('Internal Platform Console')`) · has a real `GET` asserting `assertOk()`
plus its own `<h1 class="sr-only">` · measures `critical 0 · serious 0 · moderate 0`
at both widths · **and either** renders real seeded data **or** renders the house
`<x-ui.empty-state>` *and* carries an `UNRESOLVED` line naming the writer that does not
exist. ⛔ **`<x-surface.sample-state>` is not proof of anything** — a screen still
carrying that banner is an open item, and `x-110-tag-version-per`, which has no query
at all, is `UNRESOLVED` rather than done.

⛔ **Taking `origin/main` is never a coder item on this track.** The coder guard
refuses it by design — the merge stages `JourneyHarness.php`, a CHECK. The
supervisor takes main with the owner's `GIT_GUARD_BYPASS=1`, resolves supervisor
files **ours**, and forces `app/phpunit.xml` back to `goaiez_antig_ui_test` before
the close.

## Style

Terse and factual. Cite rules and traps by name — "that is the One Rule",
"that is the count-did-not-fall trap". Warn on dangerous git operations rather
than assuming they are blocked: the deny rules are prefix matches and stop a
habit, not a determined reordering.
