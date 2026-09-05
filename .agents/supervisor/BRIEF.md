# BRIEF — PB-23 · take `origin/main` into `track/pricebook`, **staged only**

date: 2026-09-05 11:3x
from: the supervisor of track/pricebook (cron tick)
verdict this follows: the 11:3x block in REVIEWS.md (case (d) — OWNER.md 08:25 applied)
push: none
commit: **none — you do not commit at all on this run.** The supervisor makes the merge commit.

## Why this run exists

Track 1 merged this branch into `main` (`origin/track/pricebook` = `6458d582`, merge commit
`77ba6dd5`). `origin/main` is now `54ead493` and contains every commit of this branch; we are
791 behind, 1 ahead. Nothing more can be built here until main is taken (main re-ran
`surfaces:generate` over this lane's screens, so the routes this lane has been recording as
`UNRESOLVED — waits on Track 1 surfaces:generate` are arriving).

**Our side is unchanged since `6458d582` except per-track files, so no `app/**` path can
conflict.** The conflicts are exactly the files main also moved that are ours by rule
("per-track files never merge in either direction"), and every one resolves **ours, whole**.

## The two-actor rule for this merge — read it before you type anything

- `git merge` is on the supervisor's deny list, so **you** start the merge.
- The coder guard refuses any `git commit` whose index carries `CLAUDE.md`, `.claude/`,
  `bin/supervise.sh`, `app/phpunit.xml` or `.agents/supervisor/` — and a merge commit's index
  carries all of them. So **you never commit on this run**; you resolve, stage, and stop.
  The supervisor commits with `git commit --no-edit` at its next tick.
- Reaching `/usr/bin/git`, `command git`, `PATH=` on a git command or `-c core.hooksPath` to
  get past that is hard rule ④ / ruling 22 — a BLOCK before the diff is read. If something
  refuses, that is the answer; write it in REPORT.md and stop.
- `git merge --abort` is allowed by the guard and is the recovery for anything unexpected.

## Steps

**1. Fetch.**

```
git fetch --no-write-fetch-head origin main
git log -1 --format='%h %ci %s' origin/main
```

**2. Move our launcher out of the way** — main tracks `.agents/supervisor/launch-coder.sh`,
it is untracked here, and git refuses to overwrite an untracked file (the merge would not even
start):

```
cp .agents/supervisor/launch-coder.sh /home/goaiez/tmp/launch-coder.pricebook.ours
mv .agents/supervisor/launch-coder.sh .agents/supervisor/launch-coder.ours.bak
```

**3. Merge.**

```
git merge origin/main
```

**4. Read the conflict list before resolving anything.** Expected, exactly:

- `both modified:` `CLAUDE.md` · `bin/supervise.sh` · `.claude/settings.json`
- `deleted by them:` `.agents/supervisor/BRIEF.md` · `KICKOFF.md` · `REPORT.md` · `REVIEWS.md` ·
  `REWRITES.log`

If **any** path under `app/**` appears in the conflict list, or the list differs from the above
in any other way: `git merge --abort`, write REPORT.md with the verbatim list, and stop. That is
a finding, not something to resolve by judgement.

**5. Resolve — ours, whole, for the three both-modified files** (`HEAD` is our pre-merge tip):

```
git show HEAD:CLAUDE.md > CLAUDE.md
git show HEAD:bin/supervise.sh > bin/supervise.sh
git show HEAD:.claude/settings.json > .claude/settings.json
git add CLAUDE.md bin/supervise.sh .claude/settings.json
```

**6. Resolve the mailbox as deleted, keeping the files on disk** — main gitignores them and
`REVIEWS.md` is this track's review ledger; losing it is unrecoverable in practice:

```
git rm --cached -q -- .agents/supervisor/BRIEF.md .agents/supervisor/KICKOFF.md .agents/supervisor/REPORT.md .agents/supervisor/REVIEWS.md .agents/supervisor/REWRITES.log
ls -la .agents/supervisor/
```

`--cached` is not optional: the files must still be on disk afterwards. Verify that in the `ls`.

**7. The silent one — restore our test-database pin.** `app/phpunit.xml` does **not** conflict
(our side never touched it since the merge base), so main's `goaiez_antig_test` — Track 1's
database — lands without a word. This is the hazard Track 1 named at 07:4x:

```
git show HEAD:app/phpunit.xml > app/phpunit.xml
git add app/phpunit.xml
grep DB_DATABASE app/phpunit.xml
```

Verify: the grep prints `value="goaiez_antig_pricebook_test"`.

**8. Restore our launcher** (main's copy lacks the `GOAIEZ_PUSH_OK` export the coder guard's
`push` rule reads):

```
cp /home/goaiez/tmp/launch-coder.pricebook.ours .agents/supervisor/launch-coder.sh
chmod +x .agents/supervisor/launch-coder.sh
git add .agents/supervisor/launch-coder.sh
grep -c GOAIEZ_PUSH_OK .agents/supervisor/launch-coder.sh
```

Verify: the count is `5`.

**9. Prove nothing is left unresolved.**

```
git status --short
```

Verify: no line begins with `U`, `AA`, `DU` or `UD`. Then:

```
composer dump-autoload -d app
```

**10. STOP. Do not commit, do not gate, do not run `state.py`, do not push.** Write REPORT.md:

```
# REPORT — PB-23 (run <N>) — MERGE STAGED, awaiting the supervisor's commit
STATE     : merge in progress, index resolved, nothing committed
MERGE     : origin/main <sha> into track/pricebook <our sha>
CONFLICTS : <the verbatim list from step 4>
RESOLVED  : <what you did per path, one line each>
VERIFY    : phpunit.xml pin = <grep output>
            launcher GOAIEZ_PUSH_OK count = <n>
            mailbox on disk = <the five names from the ls>
            git status --short (first 40 lines):
            <paste>
REFUSED   : <anything the guard refused, verbatim — or `none`>
UNRESOLVED: <anything you could not resolve without judgement>
```

## Standing lines

- Every pest run, if one is ever needed, carries `DB_DATABASE=goaiez_antig_pricebook_test`
  (ruling 3). None is needed on this run.
- `vendor/bin/phpstan analyse --memory-limit=1G`, never `composer stan`.
- Never name `.gitignore`, `.agents/supervisor/` or `CLAUDE.md` in a commit — and on this run
  you make no commit at all.
- A commit that touches `.agents/supervisor`, `CLAUDE.md`, `.claude` or `bin` is a BLOCK; so is
  any commit on this run.
- Commits on later runs use named paths — `git commit -m "…" -- <paths>`, never `-a`, never
  `add -A`.
