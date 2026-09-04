Read `.agents/supervisor/BRIEF.md` first. **S-22 is a BLOCK.** Item 0 (the push)
landed — `92673abd..475a665f` is on the remote and Track 1 has merged that tip.
Items 1, 3 and 4 are accepted and are **not** reopened: the `IRREVERSIBLE`
constant, both Actions reading it strictly, the looping agreement test,
`test_help_and_escalation` left alone, both journal lines. Your item-1
`Mutation NOT POSSIBLE` was right and well argued — keep writing them that way.
The two `PreviewCard` ready-state tests are good and stay as they are. This is
wave **S-23**.

**`push:` is NO.** Nothing is pushed this run — not the four S-22 commits, not
what you commit now. No force, no other ref, no merge, no rebase, no
cherry-pick, no `git reset`/`stash`/`clean`.

---

**The BLOCK, in one line: `test_preview_card_error` cannot fail.** It sets the
component's own `errorMessage` property to the literal string it then asserts
on, so `AssistantPreviewAction` never throws and the `catch (Exception $e)` in
`PreviewCard::render()` never runs. Change the catch's message: still green.
Delete the whole `try`/`catch`: still green. Delete the `x-ui.error-panel`: still
green. That is the S-17 defect — a shipped state behind a green test that does
not exercise it. `test_todays_recommendation_strip_handles_error_state` twenty
lines above your test does it right and asserts `wire:click="load"`; match it.

Two carry-forwards, both now items. **Your `REPORT.md` went to the repo root**,
not `.agents/supervisor/REPORT.md` — that path is the mailbox and is how a
finished wave is detected at all; run 28's was found only by `git status`. And
**your mutation `git status --short` was captured before the revert again** —
this was the S-21 carry-forward verbatim, one wave later. Both blocks show dirty
files under `app/`. The tree is genuinely clean, so the work is fine; the proof
is not one. Capture it *after* the hand-revert, and it must show nothing under
`app/`.

---

Do the items **in order**, 0 first.

**Item 0 is the one authorised commit that touches `.agents/supervisor`** — the
single exception to a rule every brief on this track states. It comes from Track
1 under the owner's delegation (`OWNER.md`, block "17:0x — untrack your
mailbox"). `main` gitignores `.agents/supervisor/*` except `launch-coder.sh`;
this branch tracks five mailbox files, so main's merge writes your copies over
Track 1's mailbox and an abort deletes it — twice today already. Add
`.agents/supervisor/*` and `!.agents/supervisor/launch-coder.sh` to `.gitignore`,
`git rm --cached -q --` the five files named in the brief, commit
`chore(supervisor): untrack the mailbox`. **`--cached` leaves the files on
disk** — if any vanishes, stop and report; restore nothing. Verify
`git ls-files .agents/supervisor` prints exactly `launch-coder.sh`. Never a
content edit to any mailbox file, never `launch-coder.sh`, one commit, that
message. Any other `.agents/supervisor` hunk this run is still a BLOCK.

**Item 1 lifts the BLOCK.** Make the Action actually throw — bind a throwing
implementation of `AssistantPreviewAction` into the container (`render()` takes
it as a method parameter, so a binding wins); check the real `handle()`
signature, and if the class is `final`, bind a closure or use `$this->mock()`.
**Do not un-`final` the Action to make the test easier** — that is changing the
SYSTEM to suit a CHECK. Remove the `->set('errorMessage', …)` line entirely,
assert the panel's `retry` hook with `assertSeeHtml` plus the heading and the
sentence as two separate assertions, and delete the "Livewire testing sometimes
captures it" comment — a guess written into the tree, the same defect S-21
cleared four `// Wait,` lines for. **Mutation:** change the catch body's message
string, run only `test_preview_card_error`, paste the verbatim RED, hand-revert,
paste the clean status. If it does not redden, the item has not landed — record
`UNRESOLVED`, do not ship it green. If Livewire genuinely swallows the exception
and you cannot reach the catch, that is a real finding: paste the evidence,
delete the `try`/`catch` and the error branch rather than shipping theatre, and
record a `state.py decided` line.

**Item 2** closes the hole your own `NOT POSSIBLE` opened: because the test loops
the constant, no test can detect a key vanishing from `IRREVERSIBLE`. Add one
`assertEqualsCanonicalizing` against the four literal keys — that literal is the
assertion, not a third copy of the list. **Mutation:** now delete
`'wipe_database'`, run only that test, verbatim RED, hand-revert, clean status.

**Item 3** — `rm` the untracked scratch files by name (`REPORT.md`,
`push_output.txt`, `diff_journal.txt` at the root; `app/doctor_before.txt`,
`app/doctor_after.txt`, `app/stan.json`, `app/pint_output.json`,
`app/pint.json`). `git clean` is refused by the guard and working around it is a
BLOCK. Take the root `REPORT.md` **last**, after this wave's report is written
to `.agents/supervisor/REPORT.md`.

**Item 4** — `git fetch --no-write-fetch-head origin` then `git for-each-ref
--format='%(refname:short) %(objectname:short)' refs/remotes/origin`. If
`origin/main` is still `cd5a2f7a`, do nothing and record the sha. If it moved,
**still do nothing** — record it under `UNRESOLVED`. Ruling 43: Track 1 merges;
this track never rebases or merges without a brief that says so. Ruling 46c
stands either way — no `action`/`href`, no hand-written routes.

**Item 5** — a `python3 bin/state.py decided` line for every decision, proved by
pasting `git diff -- .agents/state/JOURNAL.md`. Never `state.py
done/journey/stage`. Never `state.py unresolved` for a coverage fact (ruling
48 — it flips X-124 out of `DONE`); `UNRESOLVED` is a missing dependency only.

---

Hard limits, live `php artisan doctor` after the wave: `capability` ≤ **346**,
`anchor` ≤ **134**, `citation` ≤ **128**, `boundary` ≤ **3** — and none of them
held there by restoring an `assertTrue(true)`, widening a grep, adding a
`notPath()`, deleting a capability id, or touching `app/app/Doctor/`.

**Commit with named paths only:** `git commit -m "…" -- <paths>`. Never `-a`,
never `git add -A`/`.`/`-u` — tick 259, run 25 used `add -A` and committed a
3.6 MB `composer.phar`. `git add` a new file by name first; the named-path form
cannot commit an untracked file. `git status --short` before every commit. One
commit per coherent change; write the message from the diff. A commit touching
`CLAUDE.md`, `.claude/**`, `bin/**`, `app/app/Doctor/**`, `seals.json`,
`tests/Journeys/JourneyHarness.php`, `app/phpunit.xml` or any `.env` is a BLOCK,
as is any `notPath()`/exclusion, any deleted capability id, and any weakened
assertion. `app/resources/views/**`, `app/app/Livewire/**` and
`app/resources/views/components/ui/**` are Track 2's — read them, change
nothing; read a `<x-ui.…>` component's `@props` before you call it
(`error-panel`: `heading`, the slot, `retry`).

ONE test process at a time in this checkout. Every hand-run pest carries
`DB_DATABASE=goaiez_antig_stages_test`. Every artisan call carries
`--no-interaction`. `DB_DATABASE` is never `goaiez_antig` and never edited;
nothing is written under `/home/goaiez/public_html`; never rewrite a reviewed or
pushed commit; never edit a ran migration.

Close with `bash bin/supervise.sh --tests`. The suite is **915** with 2 known
FAILUREs (`a_quote_comes_from_the_pricebook_or_does_not_come_at_all`,
`a_published_site_carries_all_seven`) and 7 known harness errors — all other
tracks'. An **8th** reading `a_deliberately_corrupted_backup_fails_the_restore …
permission denied to terminate process` is **owner ruling 12 — concurrency, not
grants**: record it, move on, never request the grants file for it, never re-run
until green. Then write `.agents/supervisor/REPORT.md` in the brief's shape —
both halves of §6 as the final two lines — and STOP. No push, no journey mark,
no `REVIEWS.md`.
