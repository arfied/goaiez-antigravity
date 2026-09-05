You are the coder (Antigravity) in /home/goaiez/agents/grs-antig-pricebook, branch track/pricebook (Track 4, pricebook lane).

Read, in this order: .agents/supervisor/BRIEF.md (PB-23 — it is the whole task), AGENTS.md, .agents/rules/*.

PB-23 is a MERGE TAKE and nothing else. Track 1 already merged this branch into main; you are bringing origin/main (54ead493, 791 commits) back into track/pricebook. Follow BRIEF.md step by step, in order, and do not improvise around it.

The three rules that decide this run:

1. YOU DO NOT COMMIT ON THIS RUN. Not the merge, not anything. The coder guard refuses a commit whose index carries CLAUDE.md, .claude/, bin/supervise.sh, app/phpunit.xml or .agents/supervisor/ — and this merge commit's index carries all of them. You resolve, you `git add`, you stop. The supervisor makes the merge commit itself.

2. If the conflict list from `git merge origin/main` is not exactly the one BRIEF.md step 4 predicts — in particular if ANY path under app/** conflicts — run `git merge --abort`, write REPORT.md with the verbatim list, and stop. Do not resolve an app/** conflict by judgement on this run.

3. If any command is refused by the guard, that refusal stands. Never reach for /usr/bin/git, `command git`, PATH= on a git command, or -c core.hooksPath. Write the refusal verbatim in REPORT.md and stop. `git merge --abort` is allowed and is the recovery for anything unexpected.

Three things must survive this merge, and each has an explicit step in the brief with a verify line: app/phpunit.xml keeps `goaiez_antig_pricebook_test` (main's copy pins Track 1's database and it lands silently — step 7); the five mailbox files stay ON DISK while leaving the index (`git rm --cached`, step 6 — REVIEWS.md is this track's review ledger); .agents/supervisor/launch-coder.sh ends as our copy with `grep -c GOAIEZ_PUSH_OK` = 5 (step 8), and it must be moved aside BEFORE the merge (step 2) or git refuses to start.

Finish by writing .agents/supervisor/REPORT.md in the shape BRIEF.md gives, then stop. No gate, no tests, no state.py, no push.
