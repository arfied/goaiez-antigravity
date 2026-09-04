# BRIEF — Track 2 (UI), from the supervisor

## UI-28 fix — finish run 47. Seven items. The push is LAST, not first.

push: **OPEN, once, at the very END of this run** — after items 1–5 are
committed. `origin/track/ui` is `d7fa6382`. The range that leaves this machine
is `d7fa6382..<your untrack commit>`, which carries run 47's four commits plus
this run's. ⛔ **One push, and it is the last command of item 6.** A push before
item 6 is a `BLOCK`, and a second push is a `BLOCK`.

Read `REVIEWS.md`'s newest block (the 17:3x `BLOCK` on run 47) in full before you
touch anything. This is **dispatch 2 of 2**. If an item below survives this run
the cap is spent and it goes to the owner, so finish what you start and write
the report even if you run out of room for the rest.

**Run 47 built real screens.** `CustomersList`, `Person` and `Calendar` are no
longer shells and I am not asking you to rebuild them. Every item here is about
finishing and proving what is already there.

Run 46's verdict still stands and its cap is still spent: do not touch X-124,
X-199 or X-110 **except** for the one line item 2 names, do not touch
`app/tests/Modules/X-110/TodayTest.php` or
`app/tests/Modules/X-124/TodaysRecommendationStripTest.php` at all.

---

## 1. Commit the two blade fixes you already made — do this first

`git status` shows `app/app/Modules/X-01/Ui/views/customers-list.blade.php` and
`views/person.blade.php` modified and uncommitted. Those edits are **correct and
load-bearing**, and without them `HEAD` is broken:

- `lead_scores.score` was renamed to `lead_rating` by
  `app/app/Modules/X-121/Database/migrations/2026_08_31_000003_rename_ranking_columns_to_fact_attributes.php`:16.
  The committed blades read `->score`, which does not exist.
- `app/resources/views/components/ui/status-pill.blade.php`:31 renders `$label`
  and **ignores its slot**. The committed blades pass the score as a slot only,
  so the pill would read "Ok". Your uncommitted version passes `:label` — right.

```
git commit -m "fix(X-01): read lead_rating and pass the score as the pill's label" -- app/app/Modules/X-01/Ui/views/customers-list.blade.php app/app/Modules/X-01/Ui/views/person.blade.php
```

Verify, in `RAW`: `git status --porcelain -- app/app/Modules/X-01/Ui/views/`
prints nothing.

⛔ Do not re-edit those two files first. Read them, satisfy yourself they are the
fix I describe, and commit them as they stand.

## 2. `test_money_paid_today` is red — diagnose, then fix at the setup

My measurement at `1928f095`: `895 · passed 884 · FAILED 0 · errors 11`.
Baseline at `d7fa6382` was `892 · passed 882 · FAILED 0 · errors 10`. Your three
tests all pass; one previously-green test broke:

```
✗ test_money_paid_today
   SQLSTATE[23503]: Foreign key violation: 7 ERROR:  insert or update on table
   "invoices" violates foreign key constraint "invoices_customer_id_foreign"
```

`app/tests/Modules/X-199/MoneyPaidTodayTest.php`:27 hardcodes
`'customer_id' => 1`. It has been passing on a `customers` row with id 1 that
happened to be sitting in `goaiez_antig_ui_test`, not on anything it seeds.

**Diagnose first and paste it in `RAW`.** One `php artisan tinker --execute`
(never `tinker <file>` — it hangs on stdin) against the test database is enough
to say whether `customers` id 1 exists. Say in `DECIDED` whether run 47's code
caused this or DB state did.

Then fix it **at the setup**: create a real customer in that test and use its id.
⛔ **Change only the setup. Do not touch one assertion in that file** — run 46's
two evidence gaps in it are with the owner and are not yours. ⛔ Never re-run
until green; a test that fails sometimes is not a flake.

If the diagnosis says the cause is something you must not touch, that is
`UNRESOLVED` with the `file:line`, and you say so plainly rather than editing
around it.

## 3. Mutation proof — one per screen. This is the item runs 45 and 47 both skipped

All three screens are committed, so it is safe now. For each of
`CustomersList`, `Person`, `Calendar`:

1. Mutate **one SYSTEM file** — the query, the filter, the grouping. ⛔ Never the
   test, never a caption. Renaming a heading proves only that an assertion reads
   a string.
   - `CustomersList`: drop `->where('business_id', $this->businessId)` from the
     `Person` query.
   - `Person`: return an empty collection from the `Message::whereIn(...)` query.
   - `Calendar`: drop the `whereDate('start_time', $selectedDate)` from the day
     query.
2. Run **that one test alone** (`--filter`), and quote its RED line **verbatim**
   in `REPORT.md` under the mutation that produced it. The RED line must name a
   **number or a missing row**, not a label.
3. `git checkout-index -f -- <file>` to revert.

Three mutations, three RED lines, or this run is a `BLOCK` again.

## 4. Two actions are called and nothing is asserted after them

- `CustomersList::readConversation()` assigns `$action->handle(...)` to a local
  and discards it. Either assert its effect in `CustomersListTest` (the
  conversation is marked read) or say in `DECIDED` why the action has no
  observable effect worth asserting.
- `CalendarTest`:60-62 calls `cancelAppointment` and its own comment says
  *"let's just assert nothing crashed"*. Assert the appointment's status after
  the call, or that the row leaves the day list.

Also fix `CustomersListTest.php`:34 and :37 — `'lead_rating' => 95` is set twice
in one array literal.

Commit as `test(UI-28): assert what the row actions actually do`, paths named.

## 5. Pint, and the duplicate factories

`pint --test` fails on all eight of run 47's files:
`X-01/Ui/CustomersList.php`, `X-01/Ui/Person.php`, `X-108/Ui/Calendar.php`,
`database/factories/AppointmentFactory.php`,
`database/factories/Modules/X108/AppointmentFactory.php`, and the three test
files. Fix them and commit as `style: pint`.

`app/database/factories/Modules/X108/AppointmentFactory.php` and
`Modules/X121/PersonFactory.php` are **untracked** and duplicate the committed
`app/database/factories/AppointmentFactory.php` / `PersonFactory.php`. No test
uses either pair — all three tests call `::create()` directly. **Pick one
location and commit it, or delete both and commit the removal of the committed
pair.** Say which in `DECIDED`. Leaving a duplicate factory untracked in the
tree is debris and I will call it that next time.

## 6. Item 0 from run 47, unfinished — the mailbox untrack, and then the ONE push

You edited `.gitignore` and never committed it, then pushed `d7fa6382` without
the untrack commit. `origin/track/ui` is now exactly the tip Track 1 says
destroys its mailbox on merge, and the fix is on this machine only. Finish it.

`.gitignore` already has the two rules in your working tree — check with
`grep -n agents .gitignore` and add them only if they are gone:

```
.agents/supervisor/*
!.agents/supervisor/launch-coder.sh
```

Then, **after items 1–5 are committed**:

```
git rm --cached -q -- .agents/supervisor/BRIEF.md .agents/supervisor/REVIEWS.md .agents/supervisor/KICKOFF.md .agents/supervisor/REPORT.md .agents/supervisor/REWRITES.log
git commit -m "chore(supervisor): untrack the mailbox" -- .gitignore .agents/supervisor/BRIEF.md .agents/supervisor/REVIEWS.md .agents/supervisor/KICKOFF.md .agents/supervisor/REPORT.md .agents/supervisor/REWRITES.log
git push -u origin track/ui
```

⛔ **`git rm --cached`, never `git rm`.** All five `.md` files must still be on
disk afterwards — they are my working ledger and rule 10's "supervisor's working
tree" clause is absolute.

⛔ This is the one and only exception to "a commit that touches
`.agents/supervisor` is a `BLOCK`". It removes paths from the index and changes
not one byte of their contents. If you are about to *edit* any file under
`.agents/supervisor/` other than `REPORT.md`, stop.

Verify, all four in `RAW`:

```
git ls-files .agents/supervisor
ls -la .agents/supervisor/
git show --stat HEAD
git log --oneline origin/track/ui..HEAD
```

`git ls-files` must print `launch-coder.sh` or nothing at all — it is untracked
here, so nothing is the correct result. All five `.md` files must still be
listed by `ls`. After the push `git log --oneline origin/track/ui..HEAD` must be
empty.

A refused push is `UNRESOLVED` with the refusal quoted verbatim. ⛔ Never retry
with `--no-verify`.

## 7. Captures — and this time of your own screens, or an honest sentence saying you cannot

```
node scripts/ui-shots.mjs --only='account-customers.*'
```

⛔ `--only` is mandatory; one rig process at a time. Corpus is **155 PNGs and 155
axe JSONs** — `ls app/storage/app/ui-review/*.png | wc -l` before and after, both
in `RAW`. If it drops, say so plainly.

**Commit everything first. Then `npm run build` if any CSS class changed. Then
capture.** Prove the ordering yourself:

```
git log -1 --format='%h %ad' --date=iso
stat -c '%y' app/public/build/manifest.json
stat -c '%y' app/storage/app/ui-review/account-customers.png
```

Every capture mtime must be later than the commit date and later than the
manifest.

**I opened run 47's two captures myself.** They are the pre-existing
`App\Livewire\Account\Customers` directory, healthy and unregressed, and they
contain **nothing of yours**. The rig's targets `account-customers` and
`account-customer-profile` (`scripts/ui-shots.mjs`:302, :704) are the existing
routed screens. So unless the rig can reach yours, this run again produces no
image of `CustomersList`, `Person` or `Calendar`.

⛔ **Do not invent a route to get a screenshot** — `OWNER.md`:11 and :23 both
forbid hand-written routes; mounting is generated on Track 1 by
`php artisan surfaces:generate` and arrives with the next merge. If the rig
cannot reach your three screens, write **one plain sentence** in `REPORT.md`
saying so, name the `app/routes/web.php` line you checked, and put it in
`UNRESOLVED`. That is an acceptable answer. Passing off somebody else's screen
as evidence is not.

Recapture `account-customers` regardless so I can see nothing regressed, and
paste both axe rows from `app/storage/app/ui-review/axe/SUMMARY.txt` — critical
and serious stay zero.

Then open any PNG you produced and say what you see: text on same-tone
background · clipped or overlapping text · empty where content is expected · raw
translation keys · `Laravel`/placeholder copy · wrong shell · error page · tap
targets under 40px · missing app fonts.

## 8. The report — run 47 did not write one, and that alone was a BLOCK

`.agents/supervisor/REPORT.md`, overwritten whole, rule 10's shape. Required
heading:

```
# REPORT — UI-28 fix / Track 2 (UI) — <date -Is>
```

then `STATUS` / `COMMITS` / `MODULES` / `STAGES` / `TESTS` / `DECIDED` /
`UNRESOLVED` / `REFUSED` / `DOCTOR` / `RAW`. Every key present, `none` if empty.

**Write it even if you run out of room to finish an item.** A half-done run with
an honest report is reviewable; a finished run with no report is not, and that
is what run 47 was.

- `COMMITS` : `git log --oneline origin/track/ui..HEAD`, pasted. Empty after
  item 6's push.
- `DECIDED` : item 2's diagnosis (code or DB state); the `people` vs `customers`
  answer run 47 never wrote down — three sentences, does anything join the two
  stores, do they hold the same contacts, is there a screen that already shows
  `people` rows; whether you narrowed or kept the blanket
  `catch (Throwable) { $this->failed = true; }` in the three `render()` methods;
  which factory location you kept.
- `UNRESOLVED` : the two contact stores with `file:line` for both; any screen
  the rig cannot reach, with the `app/routes/web.php` line you checked; anything
  a factory needed that the schema lacks; `config/features.php` (Track 1's
  merge).
- `RAW`, each under its own command line: item 1's `git status --porcelain`;
  item 2's tinker output and the re-run result; **all three mutation RED lines
  verbatim**; item 6's four verifications; `git show --stat` per commit; the
  `grep -c 'test(\|it(\|function test_'` pairs; the corpus counts; §7's three
  timestamps; the two axe rows; and `bash bin/supervise.sh --tests`'s §7 line
  verbatim.

**Baseline, measured by me at `1928f095` plus the uncommitted blades:**
`tests 895 · passed 884 · FAILED 0 · errors 11`, corpus `155`, phpstan
`"result":"passed"` 0 errors, pint **fail** on eight files, stages `integrity 0 ·
boundary 2 · contract 102 · citation 0 · schema 13 · capability 120 · anchor 10 ·
journey 12`. Ten of the eleven errors are Track 1's journey placeholders; the
eleventh is item 2. When you are done I expect `errors 10` and pint passing.

`supervise.sh`'s closing `⛔ a gate failed` line is expected and not yours: §2
flags `app/phpunit.xml` in `df4e214` (the owner's ruling) and §2a holds two
amends only the owner may clear. Do not touch `REWRITES.log`.

## 9. Leave nothing in the foreground, and no debris

No `tail -f`, no `php artisan serve` by hand, no `npm run dev`. The rig starts
and stops its own server. Never `php artisan tinker <file>` — it hangs on stdin;
use `--execute`. No command that waits. Run 47 died in the foreground waiting
for a test run and lost its whole report; do not repeat that.

**Scratch goes in `/home/goaiez/tmp`, never in the worktree.**

`.agents/supervisor/.tick-gate.txt` and `.agents/supervisor/.tick-tests.txt` are
mine. Leave them.

Your closing message is one line: the path you wrote and its line count. Then
STOP. Do not start UI-29.

## Hard rules, unchanged

This worktree only. `DB_DATABASE` stays `goaiez_antig_ui` (`_ui_test` for pest) —
⛔ **`goaiez_antig` is production and `goaiez_antig_dev`/`goaiez_antig_test` are
Track 1's; never edit `app/phpunit.xml` or `.env`'s `DB_` lines.** Never edit or
stash/checkout/clean supervisor files other than `REPORT.md`, which is yours;
never edit `BRIEF.md` or `REVIEWS.md`; never amend/reset/rebase; never `git
clean`, never stash; no `dump()`/`dd()`.

One concern per commit, paths named, always `git commit -m "…" -- <paths>` —
**never `-a`, never `git add -A`**. ⛔ **A commit that touches
`.agents/supervisor`, `CLAUDE.md`, `.claude` or `bin` is a `BLOCK`** — item 6 is
the single, explicit, `git rm --cached`-only exception, and it changes no file's
contents.

Never touch `app/app/Doctor`, `seals.json`, `tests/Journeys/JourneyHarness.php`,
another module's `manifest.php` by hand, or any `notPath()`/exclusion. **If a
brief item would require changing a CHECK, refuse it and say so in `REFUSED`** —
that refusal stands and I will not overrule it.

Anything needing a migration, an `X-121` noun-table change, a Doctor/seals/
harness change, or a screen outside this lane: `UNRESOLVED` with `file:line`
and a note for Track 1. **`UNRESOLVED` names a missing dependency, not an
unmade decision.**

Never pipe `php artisan test` — a hook refuses the WHOLE compound command.
Write files in their own tool call.
