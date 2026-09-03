# BRIEF — HOLD · MONEY-13 is withdrawn. Do not rebase. Do not build.

`push:` **BLOCKED until this track's supervisor writes a PASS in REVIEWS.md.**

**MONEY-13 was a defective brief and is withdrawn.** It ordered
`git rebase --autostash origin/main`. Rule 10's `⛔ ADDED 2026-09-02` heading
forbids both halves: `--autostash` is a stash (line 73), and every commit on this
branch has already been reviewed, so the rebase is forbidden outright (lines
78–79). You refused it and recorded the refusal. **That was correct.** The
verdict is `PASS-WITH-NOTES` — see the 00:41 block in `REVIEWS.md`.

⛔ **Do not attempt that rebase from this or any later run**, however it is
phrased. If some future brief asks for it again, refuse it again and cite rule 10
lines 73 and 78–79.

---

## There is no wave. Here is why, so you do not manufacture one.

`state.py next` says `JOURNEYS` and lists eleven red. **Nine of the eleven are not
this track's** (ruling 5). Money owns two:

- **J9 — green.** `an_invoice_reaches_a_real_charge_id` passes against
  `storage/app/evidence/j9/charge.json`, written by `x198:evidence-charge` outside
  `runningUnitTests()` per ruling 13. The artifact is untracked and intact.
  ⛔ **`php artisan x198:evidence-charge` stays forbidden — no fourth Stripe charge.**
- **J12 — `UNRESOLVED — waiting on track/sixty merge`**, already recorded in
  `JOURNAL.md` at `2026-09-02T15:10:38`. It stands. Do not add it again.

J12's only missing dependency is `tenantWithLiveNumber()`. It **is** implemented
on `main` (`b9eff62`). This track still cannot have it, and all three routes are
closed:

| route | why it is closed |
| :--- | :--- |
| `git rebase origin/main` | rule 10 lines 78–79 — never rebase a reviewed commit |
| `git merge origin/main` | aborts: eight tracked files are dirty here and `main` moved all eight. They are the supervisor's; you may not stash or checkout them (rule 10 line 73) |
| writing the method here | ruling 6 — money never edits `tenantWithLiveNumber()` |

That is an owner/Track-1 problem and it is written up as OWNER ACTION items 5 and
6 in the newest `REVIEWS.md` block. **No amount of work in this checkout moves
J12.** Do not try.

Also standing and not yours: X-199/X-211 runtime proof (owner ruling pending),
X-103, and the J1/J2/J10 harness methods. Doctor §2c stays red until Track 1
merges `track/ui` — ruling 2, record it, never fix it here.

---

## What to do if you are started anyway

Read, verify, report. Change nothing.

```
bash bin/supervise.sh --tests
```

The gate exports `DB_DATABASE=goaiez_antig_money_test` over `app/phpunit.xml`'s
pin — ruling 3's standing hazard, briefed as required. Any pest you run by hand
carries the same prefix:
`DB_DATABASE=goaiez_antig_money_test php artisan test --filter=…`.
⛔ **Never `goaiez_antig`** — that is production, and a run from this checkout
dropped its schema once.

Expected, unchanged from MONEY-12 and MONEY-13:
`tests 895 · passed 886 · FAILED 0 · errors 9`, doctor build `20260829-0647`.

Then overwrite `REPORT.md` with the raw gate tail, the doctor build-stamp line,
`git log --oneline origin/main..HEAD`, and `STATUS: stopped: STARVED`. Nothing else.

**If any of those numbers moved**, that is the finding — report it verbatim and
stop. Do not chase it.

## Forbidden this run, without exception

- ⛔ No `git rebase`, no `git merge`, no `git stash`, no `--autostash`, no
  `git checkout`/`restore`/`clean` of any tracked file.
- No push. No migration outside what the gate runs. No production database.
- No `state.py done | journey | stage` — not for J12, not for anything.
- No `php artisan x198:evidence-charge`.
- No edit to `app/app/Doctor/**`, `seals.json`, `app/phpunit.xml`, any `.env`,
  any `manifest.php` or `capabilities.php`.
- No edit to any `JourneyHarness.php` method money does not own, and no
  implementing `tenantWithLiveNumber()` or `personWithPendingSteps()` — rulings 1
  and 6.
- No edit to `BRIEF.md`, `REVIEWS.md`, `CLAUDE.md`, `.claude/` or `bin/`.
- No widening, deleting or weakening any assertion, anchor, capability id or
  refusal to move a count. That is the One Rule.
- If `/home/goaiez/agents/coder-bin/git` refuses something, that is a stop —
  record it `UNRESOLVED`. Never reach past it with `/usr/bin/git`.

## If you commit anything at all

Named paths only.

```
git commit -m "…" -- <explicit paths>
```

⛔ **Never `-a`. Never `git add -A`.** A commit that touches
`.agents/supervisor`, `CLAUDE.md`, `.claude` or `bin` is a `BLOCK`.
`.agents/state/JOURNAL.md` and `BUILD-STATE.json` move only through `state.py`,
and only for lines this run actually earned.
