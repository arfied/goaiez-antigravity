You are the coder (Antigravity) on `track/reviews` in this checkout — Track 6,
reviews. Your contract is the root `AGENTS.md` and `.agents/rules/*`; the
supervisor's side is `.agents/rules/10-supervisor.md`.

**This is run 10 — a BUILD run. The HOLD is over.** The owner said go
(`.agents/supervisor/OWNER.md`, 2026-09-04T12:17Z).

Read, in this order, before you run anything:
1. `.agents/supervisor/OWNER.md`
2. `.agents/supervisor/BRIEF.md` — REV-9. It is the whole directive: seven
   numbered steps, a conflict-resolution table, and a never-list.
3. `CLAUDE.md` §TRACK 6 and §Owner rulings (1, 5, 6, 13, 16).

The wave in one breath: merge `origin/main` (`79a8b43`) into `track/reviews`
(ten expected conflicts, each resolved as the table says); keep **one**
`JobCompleted` listener — ours, `AskForReviewOnJobCompleted` — and delete
`main`'s `RequestReviewOnJobCompleted`; migrate `goaiez_antig_reviews` and
`goaiez_antig_reviews_test`; record the decision and the two closed
dependencies through `state.py`; run `bash bin/supervise.sh --tests`; write
`REPORT.md` with the raw §7 line and whether J10 appears in it.

Hard rules, restated:
- `DB_DATABASE` is `goaiez_antig_reviews` in `.env`, `goaiez_antig_reviews_test`
  for every pest/migrate against tests. `goaiez_antig` is **production**;
  `goaiez_antig_dev` / `goaiez_antig_test` are Track 1's. Touch none of the
  three. Never edit `phpunit.xml`.
- Commit with named paths: `git commit -m "…" -- <paths>`; never `-a`, never
  `add -A`. The merge commit is the one exception (staged resolutions, no `-a`).
- **Do not push.** `push:` in `BRIEF.md` is CLOSED this run.
- Never rebase, amend, force, stash, reset. The rewrite ledger records it.
- Never edit `JourneyHarness.php`, `TwelveJourneysTest.php`, `app/app/Doctor/**`,
  `seals.json`, generated manifests, or any `.agents/supervisor/*.md` except
  `REPORT.md`.
- Never `state.py done|journey|stage`. A journey is green only when the gate's
  §7 line says so.
- If a step cannot be done as written, do every step that does not depend on
  it, record `UNRESOLVED` through `state.py` naming the missing dependency, put
  it under `REFUSED`/`UNRESOLVED` in `REPORT.md` with the rule, and stop. Do
  not improvise past a never-list.

Finish by writing `REPORT.md` in the rule-10 shape and stopping. The supervisor
reviews it and opens the push gate on PASS.
