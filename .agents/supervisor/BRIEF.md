# BRIEF — Track 2 (UI), from the supervisor

updated: 2026-09-03 00:00 (UI-11, run 1 — coder run 22)
push: **OPEN** for UI-10 — REVIEWS.md (coder run 21) says PASS-WITH-NOTES.
      Item 0 below. Commits made AFTER that push wait for the next PASS.

## 0. Push UI-10 (before any edit)

`git fetch --no-write-fetch-head origin && git push origin track/ui`
Verify: `git log --oneline -1 origin/track/ui` prints b947283.

## Standing rules

Guard on git; no foreground processes; build → commit → rig → LOOK → report
in the template (keep doing what run 21 did); one-off scripts in
/home/goaiez/tmp; never edit `app/app/Services/**`, `Domain/**`,
`Actions/**` — refuse in REPORT instead; no vendor calls.

## UI-11 — the advanced area on mobile, and the seed's loose ends

1. **Rig: mobile pass for the advanced area.** Capture at 390×844, logged in
   as the owner: `advanced.home`, `advanced.website-builder`,
   `advanced.citations`, `advanced.visibility`, `advanced.broadcasts`,
   `advanced.voice`, `advanced.integrations`, `advanced.settings`. Names
   `advanced-<slug>@390.png`. Commit `test: rig mobile pass for advanced`.
2. **Seed dates.** Spread the seeded messages, conversations, feedback and
   reviews over the last 30 days (created_at / sent_at as the models name
   them). Commit `chore: seed dates spread over 30 days`. Verify: messages
   page shows different relative times.
3. **Home tiles read 0/0/0 with seeded feedback.** Read what the three tiles
   count (the Livewire component and whatever it calls). If the seeder writes
   the wrong table/column, fix the SEEDER. If the tiles count something the
   seed cannot produce without a vendor (Google reviews), say so under RAW
   and leave them. No Service/Domain edits. Commit only if the seeder changes.
4. **axe**: website-builder preview footer `text-gray-500` on white → a shade
   passing 4.5:1. Commit `fix(advanced): builder preview footer contrast`.
5. Full rig after the last commit. Look at every NEW @390 capture and list it
   under RAW (`— OK` / `— <defect>`): horizontal scroll, tables, tap targets,
   text clipping. Fix at most three, worst first, one commit each; recapture.
6. `bash bin/supervise.sh --tests`. REPORT.md in the template with the raw
   `tests …` line, `axe/SUMMARY.txt`, PNG count, capture/commit times, the
   per-screen list. STOP.

Hard rules: this worktree only; DB_DATABASE stays goaiez_antig_ui (and _ui_test
for pest); never edit or stash/checkout/clean supervisor files; never
amend/reset/rebase; no dump()/dd(); one concern per commit, paths named; no
foreground long-running process; push only what the `push:` line clears.
