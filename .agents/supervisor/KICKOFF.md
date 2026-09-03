You are the coder on track/money in /home/goaiez/agents/grs-antig-money.

There is no wave. This kickoff exists so that an accidental dispatch is a no-op
rather than a retry of a withdrawn brief.

1. Read .agents/supervisor/BRIEF.md. It is a HOLD.
2. Read the newest block of .agents/supervisor/REVIEWS.md (2026-09-03 00:41).
   Verdict PASS-WITH-NOTES. No BLOCK is open.

MONEY-13 is WITHDRAWN. It ordered `git rebase --autostash origin/main`; rule 10
lines 73 and 78-79 forbid both the stash and the rebase of a reviewed commit.
You refused it and that was correct. Do not attempt it now or in any later run.

Do exactly this and nothing more:

    bash bin/supervise.sh --tests

Expect, unchanged: tests 895 · passed 886 · FAILED 0 · errors 9, and
doctor build 20260829-0647.

Then overwrite .agents/supervisor/REPORT.md with:
  STATUS   : stopped: STARVED
  COMMITS  : git log --oneline origin/main..HEAD, pasted raw
  the raw gate tail line, and the `goaiez doctor · build <stamp>` line
  UNRESOLVED: J12 waiting on track/sixty merge (route closed, OWNER ACTION 5/6);
              X-199 and X-211 runtime proof (owner ruling pending); X-103;
              J1/J2/J10 harness methods are other tracks'
  REFUSED  : none

If any gate number moved, that is the finding: paste it verbatim and stop.
Do not chase it, do not fix it, do not build.

FORBIDDEN, without exception:
- No git rebase, merge, stash, --autostash, checkout, restore or clean.
- No push. No state.py done|journey|stage. No php artisan x198:evidence-charge.
- No edit to app/app/Doctor/**, seals.json, app/phpunit.xml, any .env, any
  manifest.php or capabilities.php.
- No edit to any JourneyHarness.php method money does not own; no implementing
  tenantWithLiveNumber() or personWithPendingSteps() (rulings 1 and 6).
- No edit to BRIEF.md, REVIEWS.md, CLAUDE.md, .claude/ or bin/.
- Never weaken an assertion, anchor, capability id or refusal to move a count.
  That is the One Rule.
- If /home/goaiez/agents/coder-bin/git refuses anything, that is a stop: record
  it UNRESOLVED. Never reach past it with /usr/bin/git.

Any pest you run by hand carries DB_DATABASE=goaiez_antig_money_test.
Never goaiez_antig — that is production.

If you commit anything at all, use named paths:
    git commit -m "…" -- <explicit paths>
Never -a. Never git add -A. A commit touching .agents/supervisor, CLAUDE.md,
.claude or bin is a BLOCK.
