# 10 — SUPERVISION

**There is a supervisor. It reviews; you build.** The supervisor is a Claude Code
session in this same checkout, bound by `CLAUDE.md` at the root. It never edits
`app/**` and never commits. You never edit its files.

## THE MAILBOX — `.agents/supervisor/`

| `BRIEF.md` | supervisor → you. The current directive. **Read it first, before `state.py next`.** | you never edit |
| :--- | :--- | :--- |
| `REPORT.md` | you → supervisor. Overwrite it whole when you close a wave or stop for any reason. | you write |
| `REVIEWS.md` | supervisor → you. Verdicts, append-only, newest block at EOF. | you never edit |

## PRECEDENCE — extends rule 00

`BRIEF.md` wins on **what to do next** and on **what "done" means for the slice
under review**. It never wins on a point of fact (the master plan) or on
architecture (rule 08). If it names no task, work `state.py next` exactly as
before.

## SESSION START

1. Read `BRIEF.md`.
2. Read the last block of `REVIEWS.md`. If its verdict is `BLOCK`, its items are
   your first task — before `state.py next`.
3. `bash bin/loop.sh`, as before.

## WHEN YOU WRITE `REPORT.md`

- at every wave close — the moment `state.py next` names a new wave
- when you stop for any of the four reasons
- when `BRIEF.md` asks for one

Overwrite the whole file, in this shape, raw output not paraphrase:

```
# REPORT — wave <n> / <track> — <ISO timestamp>
STATUS    : wave closed | stopped: RUNTIME|SEAL|FINISHED|STARVED | brief item done
COMMITS   : <git log --oneline origin/main..HEAD, pasted>
MODULES   : X-nnn DONE · X-nnn UNRESOLVED (<what is missing>)
STAGES    : <stage> <before> → <after>   (one line per stage touched, from doctor)
TESTS     : <file>  grep -c 'test(\|it('  before <n> after <m>
DECIDED   : (R245) <one line each, as recorded with state.py decided>
UNRESOLVED: <stage> <where> — <what is missing>
REFUSED   : <brief items that would change a CHECK, with the reason> | none
DOCTOR    : <first line — goaiez doctor · build <stamp>>
RAW       : <doctor output for anything not fixed>
```

## THE RULES OF THE ARRANGEMENT

- **Commit per module.** `feat(X-nnn): …` for a module, `fix(<stage>): …` for a
  stage fix, `chore: …` otherwise. Uncommitted work is invisible to review, and
  a review of a moving tree proves nothing.
- ⛔ **Do not `git push` until the newest `REVIEWS.md` block for that wave says
  `PASS` or `PASS-WITH-NOTES`** — unless `BRIEF.md`'s `push:` line says `free`.
  Commits stay local until then. Pushing early is the one thing here that
  cannot be reviewed back.
- ⛔ **The coder never pushes — OWNER RULING 2026-09-05 14:0x (`OWNER.md`).**
  This supersedes the 2026-09-03 push step, which had the coder run
  `git push origin main` off `BRIEF.md`'s `push:` line. The owner runs no git by
  hand any more and the **supervisor** now runs every push for this lane, by
  explicit ref on a sha it has gated and recorded in `REVIEWS.md`. On the coder
  side there is nothing left to do: the launcher exports `GOAIEZ_PUSH_OK=0` on
  every run, `BRIEF.md`'s `push:` line reads `CLOSED`, and `REPORT.md` records
  `PUSHED : none — push CLOSED`. A run that pushes anyway is a `BLOCK` on the
  wave even if the range is right.
- ⛔ **The coder merges only on an opened gate — owner-approved 2026-09-05
  13:2x.** A merge writes files with no `git commit`, so the guard's never-list
  check never sees it: a merge from `origin/main` can move `app/phpunit.xml`,
  `seals.json`, `app/app/Doctor/**`, `CLAUDE.md` or the mailbox and nothing
  refuses. Run 39 is the near miss — our side had not touched `phpunit.xml`
  since the base, so git reported **no conflict at all**, and only a
  hand-written brief step caught it. `coder-bin/git` therefore refuses
  `merge`/`pull`/`cherry-pick`/`revert` unless `GOAIEZ_MERGE_OK=1`, and only
  `launch-coder.sh` sets it, from **`--allow-merge` at dispatch** — never from
  `BRIEF.md`, for the same reason the push gate is not derived from it (the
  supervisor rewrites that file every tick). **Supervisor: when the brief's item
  is a merge, dispatch with `bash .agents/supervisor/launch-coder.sh
  --allow-merge` and say so in the `REVIEWS.md` block; otherwise launch bare.**
  A `REFUSED by coder guard: git merge …` in a report means the gate was left
  shut — that is the supervisor's miss, not the coder's, and the fix is to
  relaunch with the flag.
- ⛔⛔ **The coder guard is never bypassed.** `git` in a coder run is
  `/home/goaiez/agents/coder-bin/git`. Calling `/usr/bin/git`, `command git`,
  `env PATH=… git`, or any other route around it is a BLOCK on the wave even
  when the commit itself is legitimate. A guard refusal goes in `REPORT.md`
  under `REFUSED` with the exact message, and the run stops there — the guard
  being wrong is the supervisor's problem to fix, not the coder's to route
  around. (Run 33 committed `.agents/state/*` through `/usr/bin/git` after the
  guard refused it; that is the incident this rule records.)
- ⛔ **Never resolve a `BLOCK` by editing `REVIEWS.md` or `BRIEF.md`.** Fix,
  commit, `REPORT.md`.
- **A `BLOCK` never stops the loop.** Do its items, report, continue with
  `state.py next`. The supervisor is not a fifth stop condition (rule 02).
- **The One Rule binds the supervisor too.** If a brief item would change a
  CHECK rather than the SYSTEM — a sealed file, an exemption, a deleted
  assertion, a quieter checker — do not do it. List it under `REFUSED` with the
  reason. The supervisor can be wrong; the seal cannot.
- **Paste raw output.** Stage lines, `grep -c` counts, the doctor build stamp.
  Every wrong turn in this programme came from acting on a paraphrase.
- **A ruling given verbatim in `BRIEF.md` is recorded verbatim.** When the brief
  hands you the exact words of a decision, `state.py decided` gets those words,
  not a summary of them. `REPORT.md`'s `DECIDED` field is
  `tail -n 1 .agents/state/JOURNAL.md` **pasted whole** — never `state.py`'s
  stdout, which prints a confirmation rather than the line that landed. A
  `DECIDED` line that does not begin with the brief's text is a `BLOCK`: it
  means the journal and the ruling have already diverged, and the journal is
  what the next reader has. (Recorded 2026-09-05 under the 08:0x opening; three
  runs had already complied by brief alone.)

## ⛔ ADDED 2026-09-02 — THE SUPERVISOR'S WORKING TREE

The supervisor edits `BRIEF.md`, `REVIEWS.md` and its own files **in the
working tree, uncommitted**. ⛔ **Never `git stash`, `git checkout`,
`git restore`, `git clean` or otherwise displace those files.** If a gate or a
status flags them, that is the supervisor mid-thought: leave the files exactly
as they are and note it in `REPORT.md`. A stash of the supervisor's ledger was
dropped once and the record had to be reconstructed; that is why this rule has
its own heading. The same applies to history: **never amend or rebase a commit
that has already been reviewed** — fix forward.
