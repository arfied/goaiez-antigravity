You are the coder (Antigravity) on `track/reviews` in this checkout — Track 6,
reviews. Your contract is the root `AGENTS.md` and `.agents/rules/*`.

**This is run 13 — a BUILD run.** The owner's go (`OWNER.md`, 12:17Z) stands.

Read, in this order, before you run anything:
1. `.agents/supervisor/BRIEF.md` — REV-11: nine steps, a nine-row conflict
   table, one harness method, a never-list.
2. `CLAUDE.md` §TRACK 6 and §Owner rulings 1, 5, 6, 8.

The wave in one breath: commit the supervisor notes; merge `origin/main`
(`cd5a2f7`) resolving nine conflicts as the table says — mailbox files
untracked but kept on disk, C-Reviews merged with both sides' additions and
**our** listener only; implement `reviewInvitesFor` in the harness (that one
method, nothing else); migrate both of this track's databases; record the
`completeJob` UNRESOLVED and a note through `state.py`; delete the root scratch
files; pint the one test file; run the gate; report.

Hard rules:
- `DB_DATABASE` is `goaiez_antig_reviews` for dev and `goaiez_antig_reviews_test`
  for tests. `goaiez_antig` is production; `_dev`/`_test` are Track 1's.
- `app/phpunit.xml` is modified in the working tree by the owner. Leave it
  exactly as it is; never commit it, never revert it.
- Named-path commits only. The merge commit is the one exception (staged
  resolutions, no `-a`).
- **Do not push.** No rebase/amend/force/stash/reset.
- `JourneyHarness.php`: only `reviewInvitesFor`'s body. `completeJob` and
  `personWithPendingSteps` stay as `main` has them.
- Never `state.py done|journey|stage`. J10 is expected **red** this run; a
  green J10 in your report will be read as a stubbed harness.
- If a step cannot be done as written, do every other step, record
  `UNRESOLVED` through `state.py`, put it under `REFUSED`/`UNRESOLVED` in
  `REPORT.md` with the rule, and stop.

Finish by writing `REPORT.md` in the rule-10 shape and stopping.
