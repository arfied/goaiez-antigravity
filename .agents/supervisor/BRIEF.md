# BRIEF — track/stages · wave S-23

status: **S-22 is a BLOCK.** Item 0 (the push) landed and is closed. Items 1, 3
and 4 are accepted and are **not** reopened. **Item 2's third state — the error
state — is not tested**, and that is the whole of the BLOCK. Item 1 below fixes
it. This is **dispatch 1 of 2**; if the error test is still not load-bearing
after this run, it goes to the owner and is not dispatched again.

Nothing in this wave needs a check loosened, a grep widened, a `notPath()`
added, a capability id deleted, or history rewritten.

push: **NO.** The passed range is already on `origin/track/stages` at
`475a665f` (item 0 of S-22 pushed it; Track 1 has since merged that tip into
their main). The four S-22 commits and everything you commit this run stay
**local** until the next PASS. Do not push. Do not force. Do not push any ref.

Track 8, branch `track/stages`, worktree `/home/goaiez/agents/grs-antig-stages`.
Dev DB `goaiez_antig_stages`, tests `goaiez_antig_stages_test`. Every hand-run
pest carries the prefix:

```
DB_DATABASE=goaiez_antig_stages_test php artisan test --filter=<name>
```

---

## What S-22 got right (do not touch these again)

- **Item 0 — the push.** `92673abd..475a665f  track/stages -> track/stages`,
  verified against `git for-each-ref` after a fetch. Closed.
- **Item 1 — one source of truth.** `public const IRREVERSIBLE` on
  `AssistantExecuteAction`, both call sites now `in_array(…, self::IRREVERSIBLE,
  true)` / `in_array(…, AssistantExecuteAction::IRREVERSIBLE, true)`, strict in
  both, and `wipe_database` no longer disagrees between preview and execute.
  `test_constant_irreversible_actions` loops the constant instead of retyping
  it. Closed — except for the one-line gap in item 2 below.
- **Item 1's `Mutation NOT POSSIBLE`.** Your reasoning was right and worth
  writing down: because the test loops the constant, deleting a key shrinks the
  loop and the test passes silently, so the briefed mutation cannot redden. You
  said so in the required form and substituted a real one (make `handle` ignore
  the constant → verbatim RED on `'refused_confirmation_required'` vs
  `'executed'`). That is exactly how a `NOT POSSIBLE` should read. Closed.
- **Item 3 — you left `test_help_and_escalation` alone.** Still one
  `assertTrue(true)` at line 107 with all three ids. No capability id deleted,
  `capability` held at 346. Closed.
- **Item 4 — decisions journalled.** Both X-124 lines (`16:59:30`, `16:59:33`)
  are in `.agents/state/JOURNAL.md` and show in the gate's journal tail. Closed.
- **The One Rule, clean.** Five files, all X-124. No Doctor, no seals, no
  harness, no `phpunit.xml`, no supervisor paths. Doctor stamp `20260829-0647`
  matches `runtime_build`. Every hard limit held.
- **The two `PreviewCard` ready-state tests are good.** `data-irreversible` /
  `data-action-key` hooks in your own blade, `assertSeeHtml` on the full
  attribute string, sentence-length `assertSee`, and the briefed mutation
  (`'is_irreversible' => false`) produced a verbatim RED. Keep them as they are.

Two carry-forwards, neither a blocker, both fixed by items 3 and 5:

- **`REPORT.md` went to the repo root**, not `.agents/supervisor/REPORT.md`.
  The mailbox is how the supervisor detects a finished wave at all.
- **The mutation `git status --short` was captured before the revert — again.**
  This was the S-21 carry-forward, verbatim, one wave later. Both blocks paste a
  status showing dirty files under `app/`. The tree is genuinely clean, so
  nothing is wrong with the work; the proof just is not one.

---

## Item 0 — the authorised mailbox-untrack commit (FIRST commit of this run)

**Read this whole item before you touch anything.** Every brief on this track
says *a commit touching `.agents/supervisor` is a BLOCK*. This item is the one
authorised exception, in one narrow form and no wider. It comes from Track 1
under the owner's delegation (`.agents/supervisor/OWNER.md`, block
"2026-09-04 17:0x — untrack your mailbox"; recorded as OWNER ACTION 51 in
`REVIEWS.md` tick 262).

Why: `main` gitignores `.agents/supervisor/*` except `launch-coder.sh`; this
branch **tracks** five mailbox files. When main merges this branch, git writes
this track's copies over Track 1's mailbox, and an abort deletes it. That has
already happened twice today. `REVIEWS.md` alone is 1.4 MB of this track's
history.

Add these two lines to `.gitignore`:

```
.agents/supervisor/*
!.agents/supervisor/launch-coder.sh
```

Then, one command, exactly these five paths:

```
git rm --cached -q -- .agents/supervisor/BRIEF.md .agents/supervisor/KICKOFF.md .agents/supervisor/REPORT.md .agents/supervisor/REVIEWS.md .agents/supervisor/REWRITES.log
git commit -m "chore(supervisor): untrack the mailbox" -- .gitignore .agents/supervisor
```

Verify, and paste both in `REPORT.md`:

```
git ls-files .agents/supervisor      → must print exactly: .agents/supervisor/launch-coder.sh
ls -la .agents/supervisor/REVIEWS.md → must still find the file on disk
```

**`git rm --cached` removes the index entry and leaves the working file.** If
any of those files vanishes from disk, you used the wrong flag — stop
immediately and report it; do not try to restore anything.

**The limits on this exception:**

- It is `git rm --cached` and two `.gitignore` lines. **Never a content edit** to
  any mailbox file — not one byte of `REVIEWS.md`, `BRIEF.md` or `KICKOFF.md`.
- **Never `launch-coder.sh`** — it stays tracked, which is why the `!` line
  exists. If `git ls-files` prints nothing at all, you over-matched; fix the
  `.gitignore` and re-add it by name.
- **One commit**, that message. Any *other* `.agents/supervisor` hunk anywhere
  in this run is still a BLOCK.
- Both named paths are already tracked, so the named-path commit form stages
  them with no `git add`. **`git add -A` is still never** — tick 259, run 25 used
  it and committed a 3.6 MB `composer.phar`.

Do this **first**, before the X-124 work, so a later mistake cannot entangle it.

## Item 1 — THE BLOCK: `test_preview_card_error` cannot fail

This is the wave. Everything else is small.

The test as it stands sets the component's own property to the literal string it
then asserts on:

```php
->call('load')
->set('errorMessage', 'Failed to load preview')
->assertSee('Failed to load preview');
```

`AssistantPreviewAction::handle()` is never made to throw, so the
`catch (Exception $e)` in `PreviewCard::render()` never runs. Change the catch's
message to anything else: still green. Delete the whole `try`/`catch`: still
green. Delete the `x-ui.error-panel` and leave a bare `{{ $errorMessage }}`:
still green. The test proves a public property renders. It proves nothing about
the error state, and the brief specified that state as "**the Action threw**;
`x-ui.error-panel` with `retry="load"`".

That is the S-17 defect — a shipped state behind a green test that does not
exercise it. Twenty lines above in your own file,
`test_todays_recommendation_strip_handles_error_state` does it correctly and
asserts `wire:click="load"`. Match it.

**Make the Action actually throw.** The clean way, and the way that module
already resolves its Action, is the container — `render()` takes
`AssistantPreviewAction` as a method parameter, so a binding wins:

```php
$this->instance(AssistantPreviewAction::class, new class extends AssistantPreviewAction {
    public function handle(int $businessId, string $actionKey, array $params = []): array
    {
        throw new \RuntimeException('boom');
    }
});
```

(`$this->instance(...)`, `$this->app->bind(...)` or `$this->mock(...)` — your
call; check the real signature of `handle()` before you copy that stub, and if
`AssistantPreviewAction` is `final` you cannot subclass it, so bind a closure or
use `$this->mock()`. Do **not** un-`final` the Action to make the test easier —
that is changing the SYSTEM to suit a CHECK.)

Then assert the state, not the string you planted:

- `assertSeeHtml` on the error-panel's `retry` hook, the way the strip test
  asserts `wire:click="load"`;
- the panel's `heading` and the sentence, as two separate assertions — S-21's
  discipline, sentences only, no bare `assertSee` on a string shorter than a
  sentence (Livewire v3's `<!--[if BLOCK]><![endif]-->` and the random base62
  `wire:id` make short strings latent flakes);
- and **remove the `->set('errorMessage', …)` line entirely.** If the test still
  passes with that line present, it is still passing for the wrong reason.

**Delete this comment** while you are in there:

```php
// We force an error by making AssistantPreviewAction throw, but Livewire testing
// sometimes captures it. We can just set errorMessage directly since we test the blade.
```

"sometimes captures it" is a guess written into the tree. S-21 replaced four
`// Wait,` lines for exactly this reason one wave ago. If you keep a comment,
make it one factual line naming the condition.

**Mutation for this item — this is what lifts the BLOCK.** In
`PreviewCard::render()`, change the catch body's
`$this->errorMessage = 'Failed to load preview';` to a different string. Run
**only** `test_preview_card_error`. Paste the verbatim RED line. Hand-revert.
Then paste `git status --short` **captured after the revert**, showing nothing
under `app/`. If that mutation does not redden, the test is still not
load-bearing and the item has not landed — say so as `UNRESOLVED` rather than
shipping it green.

**If Livewire genuinely swallows the exception** and you cannot get the catch to
run from a `Livewire::test`, that is a real finding, not a reason to fake it:
say so in `REPORT.md` with the verbatim evidence, delete the `try`/`catch` and
the error branch rather than shipping an untested state, and record one
`state.py decided` line. A screen with two honest states beats one with three
where the third is theatre.

## Item 2 — one assertion so a shrunken `IRREVERSIBLE` reddens

Your `NOT POSSIBLE` finding was right, and it leaves a real hole: because
`test_constant_irreversible_actions` loops the constant, **no test on this
branch can detect a key vanishing from it.** Delete `'wipe_database'` today and
every test stays green.

The loop is the right shape for "the two Actions agree". It is the wrong shape
for "the list is complete". Add the second, in the same test:

```php
$this->assertEqualsCanonicalizing(
    ['delete_tenant', 'refund_charge', 'bulk_delete', 'wipe_database'],
    AssistantExecuteAction::IRREVERSIBLE,
);
```

That one literal is deliberate and is **not** a third copy of the list — it is
the assertion itself. Anything derived from the constant cannot catch the
constant changing.

**Mutation for this item:** now delete `'wipe_database'` from the constant — the
mutation S-22 correctly reported as impossible — run only
`test_constant_irreversible_actions`, paste the verbatim RED, hand-revert, paste
`git status --short` showing nothing under `app/`. That closes the loop your own
report opened.

## Item 3 — clean up the scratch files, and put the report in the mailbox

Untracked debris in the tree from run 28:

```
REPORT.md  push_output.txt  diff_journal.txt
app/doctor_before.txt  app/doctor_after.txt  app/stan.json  app/pint_output.json  app/pint.json
```

None is committed, so this is not yet the tick-259 recurrence — but they are one
`git add -A` away from being it, and `add -A` is what put `composer.phar` on
this branch. Delete them with `rm`, by name (`git clean` is refused by the
guard and working around it is a BLOCK). Take the root `REPORT.md` **last**, and
only after you have written this wave's report to the right place.

This wave's report goes to **`.agents/supervisor/REPORT.md`** — that path and no
other. It is the mailbox; the supervisor detects a finished wave by its mtime,
and run 28's report was found only by `git status`. It is a working file, not a
commit: after item 0 it is gitignored, so it will not appear in `git status` at
all. Write it there anyway.

## Item 4 — check `origin/main`, act only if it moved

Before your first commit, once:

```
git fetch --no-write-fetch-head origin
git for-each-ref --format='%(refname:short) %(objectname:short)' refs/remotes/origin
```

If `origin/main` is still **`cd5a2f7a`**, the owner has not pushed Track 1's
merge. **Do nothing** — no merge, no rebase, no cherry-pick — and record the sha
in `REPORT.md` as one line. Ruling 46c still stands: no hand-written routes, no
`action`/`href` anywhere, until the surfaces generator lands on `origin/main`.

If `origin/main` has moved, **still do nothing this run** beyond recording the
new sha in `REPORT.md` under `UNRESOLVED`. Ruling 43: Track 1 merges; this track
never rebases, cherry-picks or merges without a brief that says so. A merge is
its own wave.

## Item 5 — record the decisions

Every decision this wave gets a `python3 bin/state.py decided` line and must
appear in `.agents/state/JOURNAL.md`, with `git diff -- .agents/state/JOURNAL.md`
pasted in `REPORT.md` as proof. At minimum: how you made the Action throw in
item 1, and — if you take the fallback — why the error state was deleted rather
than shipped untested.

Never `state.py done/journey/stage`. Never `state.py unresolved` for a coverage
fact — ruling 48: it appends to the module's `unresolved` list and flips X-124
out of `DONE`. `UNRESOLVED` is for a **missing dependency** only.

**Hard limits.** Live `php artisan doctor` after this wave: `capability` must not
rise above **346**, `anchor` not above **134**, `citation` not above **128**,
`boundary` not above **3** — your own S-22 after-numbers, from `php artisan
doctor` itself, not the gate's `BUILD-STATE` line. And none of them held there by
restoring an `assertTrue(true)`, widening a grep, adding a `notPath()`, deleting
a capability id, or touching anything under `app/app/Doctor/`.

---

## Rules for this run

- **Commit with named paths only:** `git commit -m "…" -- <paths>`. Never `-a`,
  never `git add -A`, never `git add .`, never `git add -u`. If you create a new
  file, `git add` **that one file by name** first — the named-path form cannot
  commit an untracked file. `git status --short` before every commit; if
  anything you did not name is staged, unstage it.
- **Write the commit message from the diff**, not from the option you
  considered. And one commit per coherent change — S-22 spent two commits with
  near-identical messages on item 1.
- A commit that touches `CLAUDE.md`, `.claude/**` or `bin/**` is a **BLOCK**. So
  is `app/app/Doctor/**`, `seals.json`, `tests/Journeys/JourneyHarness.php`,
  `app/phpunit.xml`, any `.env`, any `notPath()`/exclusion, **any deleted
  capability id**, and any weakened assertion. `.agents/supervisor/**` is a BLOCK
  **except** item 0's single authorised commit, in exactly the form written
  there.
- `app/resources/views/components/ui/**`, `app/app/Livewire/**` and
  `app/resources/views/**` are **Track 2's** — read them, change nothing. Read
  every `<x-ui.…>` call against that component's `@props` before you write it:
  `error-panel` takes `heading` (declared, no default) plus the **slot** for the
  sentence plus `retry` (default `'$refresh'`). Your blade edits go in
  `app/app/Modules/X-124/Ui/views/`.
- Do **not** un-`final` an Action, widen a visibility, or add a setter to make a
  test easier. That is changing the SYSTEM to suit a CHECK.
- No merge, no rebase, no cherry-pick, no `git reset`/`stash`/`clean` — the coder
  guard refuses them and working around it is a BLOCK.
- **No push at all this run.**
- Do not brief yourself a tenant-isolation mutation on this module: check the
  module's migration for `FORCE ROW LEVEL SECURITY` before ever trying "drop the
  `where('business_id')` and watch it redden" — where RLS is on, that mutation
  cannot redden and the attempt burns the wave.

## REPORT.md shape — write it to `.agents/supervisor/REPORT.md`

- `UNTRACK` — item 0's two verify lines verbatim (`git ls-files
  .agents/supervisor`, and the `ls` proving `REVIEWS.md` is still on disk).
- `COMMITS` — every sha with its message.
- `TESTS` — **per file** `before -> after`, and name every test added and every
  test removed. Use `grep -c 'function test_' <file>`; `grep -c 'test(\|it('`
  returns 0 on this repo's PHPUnit-style module tests.
- `MUTATIONS` — **two** this wave (item 1's and item 2's). Each as
  `Mutation: <file> — <what changed>` above its verbatim RED line, then one
  `git status --short` **captured after the revert**, showing nothing under
  `app/`. Third time this has been asked for; it is the one line that proves the
  mutation was undone. `Mutation NOT POSSIBLE: <briefed one> — <why>.
  Substituted: <what>` if one genuinely cannot be made — S-22's was exemplary.
- `DOCTOR` / `STAGES` — the **raw** `php artisan doctor` output, build-stamp line
  plus per-stage lines, verbatim, before and after.
- `MAIN` — item 4's `origin/main` sha, one line.
- `DECIDED` — with `git diff -- .agents/state/JOURNAL.md` pasted as proof.
- `UNRESOLVED` (missing dependencies only) / `REFUSED`.
- **Final two lines: both halves of §6, re-run after your LAST commit** —
  `composer stan` and `pint --test`, both JSON objects. A report carrying one and
  losing the other cost S-15 and S-17 a wave each.

## Close

`bash bin/supervise.sh --tests` at the tip. The suite is **915** with 2 known
FAILUREs (`a_quote_comes_from_the_pricebook_or_does_not_come_at_all`,
`a_published_site_carries_all_seven`) and 7 known harness errors — all other
tracks', none yours. If you see an **8th** error reading
`a_deliberately_corrupted_backup_fails_the_restore … permission denied to
terminate process`, that is **owner ruling 12 — concurrency, not grants**
(another checkout was running pest). Record it and move on; never request the
grants file for it and never re-run until green.

Then write `.agents/supervisor/REPORT.md` and stop — do not push, do not mark a
journey, do not touch `REVIEWS.md`.
