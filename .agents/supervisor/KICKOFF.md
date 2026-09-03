You are the coder on track/stages (worktree /home/goaiez/agents/grs-antig-stages,
branch track/stages). Read .agents/supervisor/BRIEF.md FIRST and follow it exactly.
It is directive S-7 and it supersedes the earlier HOLD.

S-7 is ONE mechanical task: take origin/main into this branch by merge. You are
NOT building anything this run. Do not start a wave, do not touch app/ except as
the merge itself moves files, do not run state.py done/journey/stage, do not
hand-edit .agents/state/BUILD-STATE.json.

This tree is 155 commits behind origin/main (fe09446). Track 1 has already merged
this track's work (371aa08), so origin/main..HEAD is ahead 0. The §2c dd() finding
in X-179 is our stale copy — main's copy is already clean.

Steps, in order:

1. Commit the supervisor notes that are already modified in the tree:
   .agents/supervisor/{BRIEF,KICKOFF,REPORT,REVIEWS}.md, CLAUDE.md,
   .claude/settings.json, bin/supervise.sh, and add the untracked
   .agents/supervisor/launch-coder.sh. Delete the root debris schema_before.txt
   and schema_after.txt. Leave every dot-prefixed scratch file in
   .agents/supervisor/ untracked.

2. git fetch --no-write-fetch-head origin
   git merge --no-ff --no-commit origin/main
   NEVER rebase. NEVER stash — the stash stack is shared across worktrees on this
   box. NEVER `git checkout main -- <dir>`; main is checked out in production.

3. Restore the per-track paths, per path, from HEAD:
     git checkout HEAD -- .agents/supervisor
     git checkout HEAD -- CLAUDE.md
     git checkout HEAD -- .claude/settings.json
     git checkout HEAD -- bin/supervise.sh
   .agents/rules/10-supervisor.md and app/phpunit.xml are already identical to
   main — do not touch them.

4. .agents/state is NOT restored. Read BRIEF.md §4. BUILD-STATE.json: keep main's
   staged copy. JOURNAL.md: the result must be the UNION of both sides in
   timestamp order, with no line lost from either. This HEAD has 17 lines main
   lacks (the 14:50:48 R245 DECLINED rows and the 15:10:58 / 15:11:06 / 15:42:41
   notes); main has 23 this HEAD lacks (J5/J7/J8 marks and `stage journey =`
   lines). Both files are state.py-owned; a blind restore either way is a BLOCK.

5. git commit -m "merge: origin/main into track/stages"

6. DO NOT PUSH. BRIEF.md's push line is NO.

7. Verify and capture raw output for all four:
     git rev-list --count HEAD..origin/main
     grep -rn 'dd(' app/app/Modules/X-179/
     comm -23 <(git show 6b64614:.agents/state/JOURNAL.md | sort) <(sort .agents/state/JOURNAL.md) | grep -c .
     comm -23 <(git show origin/main:.agents/state/JOURNAL.md | sort) <(sort .agents/state/JOURNAL.md) | grep -c .
   Expected: 0, no output, 0, 0.

8. Run: bash bin/supervise.sh --tests
   The 10 journey-harness errors in §7 are EXPECTED and are not yours — this
   track owns no journeys. §2c must print nothing after the merge.

9. Write .agents/supervisor/REPORT.md in the rule 10 shape, with the merge commit
   sha, the four verification outputs raw, and the §2c and §7 lines raw. If any
   step could not be completed, record it under UNRESOLVED with the reason — do
   not retry past a second failure and do not loosen any check to get green.

Never edit BRIEF.md or REVIEWS.md. Never touch app/Doctor, seals.json,
JourneyHarness.php, or the phpunit DB lines.
