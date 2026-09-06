# 10 — SUPERVISION

**There is a supervisor. It reviews; you build.** The supervisor is a Claude Code
session in this same checkout, bound by `CLAUDE.md` at the root. It never edits
`app/**`. You never edit its files.

⚠️ **Updated 2026-09-05 (owner ruling, 08:0x and 14:0x): "and never commits" is
retired.** The supervisor commits its **own five files only** — `CLAUDE.md`,
`bin/supervise.sh`, `.claude/settings.json`, `.agents/rules/10-supervisor.md`,
`.agents/supervisor/launch-coder.sh` — always as `chore(supervisor): …` with named
paths, plus the one merge commit nobody else can make. **Merge step 0 — committing
supervisor notes — is therefore the supervisor's, not yours**; your guard refuses
those paths, and that refusal is correct.

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
TESTS     : <file>  grep -c 'function test\|test(\|it('  before <n> after <m>
            (⚠️ 2026-09-05: this repo's module tests are PHPUnit method style, so the
             Pest-only 'test(\|it(' returned 2 on a nine-test file. Name the command
             you actually ran, whatever it is — the count and the command must agree.)
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
- ⛔⛔ **You never `git push`. Not ever, on this lane.** (Owner ruling 2026-09-05
  14:0x: *"the supervisor runs ALL git for this lane from now on"*, and the owner
  runs no git by hand either.) Commit; the supervisor pushes the sha it has gated,
  by explicit ref. `BRIEF.md`'s `push:` line reads `⛔ closed — the supervisor
  pushes`, and there is no wording that opens it for you — a `PASS` verdict does
  **not** open it. This retires the earlier "push after a PASS" and "push step"
  rules that stood here; if an old `BRIEF.md` still says `YES — <from>..<to>`, that
  brief is stale — report it and push nothing. Pushing is the one thing here that
  cannot be reviewed back.
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

## ⛔ ADDED 2026-09-02 — THE SUPERVISOR'S WORKING TREE

The supervisor edits `BRIEF.md`, `REVIEWS.md` and its own files **in the
working tree, uncommitted**. ⛔ **Never `git stash`, `git checkout`,
`git restore`, `git clean` or otherwise displace those files.** If a gate or a
status flags them, that is the supervisor mid-thought: leave the files exactly
as they are and note it in `REPORT.md`. A stash of the supervisor's ledger was
dropped once and the record had to be reconstructed; that is why this rule has
its own heading. The same applies to history: **never amend or rebase a commit
that has already been reviewed** — fix forward.
