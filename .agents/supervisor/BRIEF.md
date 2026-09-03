# BRIEF — S-7: take origin/main by merge

status: ACTIVE — supersedes the HOLD of 2026-09-02 16:13
push:   NO. Commit only. The gate runs before any push, and Track 1 has already
        merged this track's work (`371aa08`); `origin/main..HEAD` is `ahead 0`.

The hold is lifted. The owner replied through Track 1 and every open owner
action on this track is ruled. See REVIEWS.md 2026-09-03 02:05.

This tree is **155 behind** `origin/main` (`fe09446`). The §2c `dd()` ⛔ in
X-179 is our stale copy, nothing else — main's copy has no `dd(`.

## One task. Mechanical. Do not build anything.

### 1. Commit the supervisor notes first

Tracked and modified: `.agents/supervisor/BRIEF.md`, `KICKOFF.md`, `REPORT.md`,
`REVIEWS.md`, `CLAUDE.md`, `.claude/settings.json`, `bin/supervise.sh`.
Add `.agents/supervisor/launch-coder.sh` — it is untracked and CLAUDE.md's
dispatch section references it.

Delete the root debris `schema_before.txt` and `schema_after.txt` — working
files from S-3, never meant to be tracked. Leave every dot-prefixed scratch
file in `.agents/supervisor/` untracked; supervise.sh §2 already classifies
them as working notes.

### 2. Merge — never rebase, never stash

    git fetch --no-write-fetch-head origin
    git merge --no-ff --no-commit origin/main

### 3. Restore the per-track paths, per path, from HEAD

    git checkout HEAD -- .agents/supervisor
    git checkout HEAD -- CLAUDE.md
    git checkout HEAD -- .claude/settings.json
    git checkout HEAD -- bin/supervise.sh

`.agents/rules/10-supervisor.md` and `app/phpunit.xml` are already identical to
main — do not touch them.

### 4. `.agents/state` is NOT restored — it is a union

The owner's restore list named `.agents/state`. It is excluded, and the reason
is in REVIEWS.md: `371aa08` never carried this track's 17 journal lines to main,
and main holds 23 lines this HEAD lacks. Neither side is a superset, and both
files are `state.py`-owned — a blind restore either way is the hand-edit BLOCK.

- `.agents/state/BUILD-STATE.json` — take **main's** copy whole (it is the newer
  aggregate of every track; both sides stamp `runtime_build 20260829-0647`).
  That is what the merge already staged, so leave it alone.
- `.agents/state/JOURNAL.md` — the file must end up as the **union of both
  sides, in timestamp order**, no line dropped from either. Our 17 are the
  `14:50:48` R245 DECLINED rows and the `15:10:58` / `15:11:06` / `15:42:41`
  notes. Verify with the two `comm` lines in step 6 before committing.

### 5. Commit

    git commit -m "merge: origin/main into track/stages"

### 6. Verify — paste all four outputs into REPORT.md

    git rev-list --count HEAD..origin/main          # must be 0
    grep -rn 'dd(' app/app/Modules/X-179/           # must print nothing
    comm -23 <(git show 6b64614:.agents/state/JOURNAL.md | sort) \
             <(sort .agents/state/JOURNAL.md) | grep -c .   # must be 0
    comm -23 <(git show origin/main:.agents/state/JOURNAL.md | sort) \
             <(sort .agents/state/JOURNAL.md) | grep -c .   # must be 0

Then `bash bin/supervise.sh --tests` and paste the §2c and §7 lines raw.

## Watch for

- **Never `git checkout main -- <dir>`** — `main` is not checked out in this
  worktree and the per-path restore is the only correct form.
- **Never stash.** The stash stack is shared across every worktree on this box.
- **Never rebase.** The instruction is a merge; 155 commits replayed over this
  branch is not the same operation and is not authorised.
- The 10 journey harness errors in §7 are **not yours** — this track owns no
  journeys. They are expected and are not a failure of this merge.
- Do not run `state.py done/journey/stage`. Do not hand-edit `BUILD-STATE.json`.
