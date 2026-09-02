# BRIEF — Track 2 (UI), from the supervisor

updated: 2026-09-02 (run 2 — UI-1 fix run, dispatch 2 of 2)
push: to `origin track/ui` only, after PASS. NEVER main.

## Verdict on run 1 (REVIEWS.md 2026-09-02): PASS-WITH-NOTES, UNRESOLVED on owner

Your report was right: goaiez_antig_ui had no pgvector extension. The owner has
now run `CREATE EXTENSION vector` on goaiez_antig_ui and goaiez_antig_ui_test.
Do not retry until BRIEF says so — this line is that signal.

## Current task — UI-1 (continued): finish the bootstrap

1. Database, in the bootstrap workflow's order (`.agents/workflows/bootstrap.md` §5):
   `php artisan migrate` (against goaiez_antig_ui from .env), then apply the
   grants file as the owner role using the credentials already in `.env`:
   `PGPASSWORD="$(grep -E '^DB_MIGRATE_PASSWORD=' .env | cut -d= -f2-)" psql -h 127.0.0.1 -U goaiez_owner -d goaiez_antig_ui -f ../runtime/goaiez-grants.sql`
   Rule 05: `permission denied` is a GRANT error, never RLS. Never BYPASSRLS.
   Verify: `php artisan migrate:status | grep -c Pending` prints 0, and
   `php artisan tinker --execute='echo DB::table("sessions")->count();'` prints a number, not 42501.
2. `DB_DATABASE=goaiez_antig_ui_test ./vendor/bin/pest --filter=nothing` boots.
   If it fails with 42501 on the test DB, run the same grants command with
   `-d goaiez_antig_ui_test` after the first migrate:fresh has created tables.
3. `bash bin/supervise.sh --tests` — target Track 1's last numbers
   (886 · 876 · FAILED 0 · errors 9-ish). Paste the raw `tests …` line.
4. Fix the rig (`scripts/ui-shots.mjs`) — three findings from REVIEWS.md:
   a. Routes: `/builder` does not exist. Real screens: `/login`, `/home`,
      `/account`, `/memberships`, `/advanced/website-builder` (behind
      EnsureAdvancedDashboard — if the seeded owner is refused there, capture
      the refusal page and say so in REPORT; do not widen the gate).
   b. Log in as an OWNER of a business, not DatabaseSeeder's bare user.
      Seed one deterministically (a small `UiReviewSeeder` or a factory call in
      an artisan one-liner the rig invokes) — email/password fixed, business
      attached, plan active enough for /home to render.
   c. After submitting login, assert `page.url()` is not `/login`; if it is,
      write `login-FAILED.png` and `process.exit(1)`. A rig that silently
      screenshots the login page five times is worse than none.
5. Run the rig against `php artisan serve --port=<free>`, one PNG per screen
   in `storage/app/ui-review/`, then `ls -la storage/app/ui-review` into REPORT.
   Commit the rig fix and the seeder as separate commits (one concern each).
6. REPORT.md, rule-10 shape. STATUS is `brief item done` (not RUNTIME — the
   checker never broke). STOP for review.

Hard rules: this worktree only; DB_DATABASE stays goaiez_antig_ui (and _ui_test
for pest) — production goaiez_antig and Track 1's _dev/_test are off-limits;
scope per the TRACK 2 section of CLAUDE.md; never edit or stash/checkout/clean
supervisor files; never amend/reset/rebase; no dump()/dd() in app code; one
concern per commit; push only to origin track/ui after PASS.
