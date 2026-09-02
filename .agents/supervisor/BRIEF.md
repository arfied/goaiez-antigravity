# BRIEF — Track 2 (UI), from the supervisor

updated: 2026-09-02 (UI-4, run 1 — coder run 8)
push: **OPEN** for UI-3 — REVIEWS.md (coder run 7) says PASS-WITH-NOTES.
      Item 0 below. Commits made AFTER that push wait for the next PASS.

## 0. Push UI-3 (before any edit)

`git fetch --no-write-fetch-head origin && git push origin track/ui`
Verify: `git log --oneline -1 origin/track/ui` prints de4c5af.

## UI-4 — mobile nav, then the public and advanced screens

Rules: one concern per commit, paths named, never `-a`. Scope: TRACK 2.
No migrations, no Domain/Actions, no Doctor/seals/harness. Do not reword copy
a test pins. **The rig runs after your last commit, always** — a fix with no
capture after it is unproven (REVIEWS.md, run 6). APP_NAME stays as is;
the owner is choosing the name.

1. **Collapsed mobile nav.** At 390px the account nav wraps to three rows with
   "More ▾" floating mid-row. In `resources/views/components/account/nav.blade.php`,
   below the `sm` breakpoint render ONE row: the business pill, and a single
   native `<details>` disclosure labelled with the current item's name plus
   "▾" (same pattern as the existing More menu), containing every item with
   the current one marked. Desktop unchanged. Min-height 40px throughout.
   Commit `fix(ui): single-row mobile nav`. Verify: account-home@390.png and
   account-inbox@390.png show one nav row.
2. **Rig: public pages.** Signed-out, at 1280 and 390, capture the footer
   links on `/`: Pricing, What it does, Compare, Guarantee, Questions,
   Customers, Affiliates, Agencies (read the hrefs from the rendered page
   with Playwright rather than guessing URIs), plus the first screen of
   "Start free". Names `public-<slug>.png` / `public-<slug>@390.png`. Commit
   `test: rig captures the public pages`.
3. **Rig: advanced screens.** Logged in, capture `advanced.citations` and
   `advanced.visibility` at 1280. Commit `test: rig captures advanced screens`.
4. Run the rig. Look at every NEW PNG yourself; list each under RAW as
   `<file> — OK` or `<file> — <defect>` (same defect checklist as UI-3). Fix
   at most three, worst first, one commit each. **Recapture after the last
   fix.**
5. `bash bin/supervise.sh --tests`. REPORT.md, rule-10 shape, with the
   `TESTS :` line filled and the raw `tests …` line under RAW. STOP.

Hard rules: this worktree only; DB_DATABASE stays goaiez_antig_ui (and _ui_test
for pest); never edit or stash/checkout/clean supervisor files; never
amend/reset/rebase; no dump()/dd(); one concern per commit, paths named;
push only what the `push:` line clears.
