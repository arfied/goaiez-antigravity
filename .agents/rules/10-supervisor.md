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
  ⚠️ **Restored 2026-09-09 (REV-121).** The 09:54 fast-forward to `origin/main`
  overwrote this file — `.agents/rules/10-supervisor.md` is `merge=ours`, and a
  fast-forward never consults the driver — and put the superseded 2026-09-03
  push step back in this bullet's place for six hours. A coder reading its own
  contract in that window was told to run `git push origin main`.
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
  ⚠️ **Also restored 2026-09-09 (REV-121) — and read what it says.** This bullet
  names `app/phpunit.xml` as the exact file a merge moves with nothing refusing.
  It was deleted by a fast-forward, which moved `app/phpunit.xml`.
  ⛔⛔ **CORRECTED 2026-09-09 (REV-126). The bypass condition is not "a
  fast-forward" — it is "our side did not move the path since the merge base",
  and this bullet has said so since 2026-09-05.** Read the Run 39 sentence eight
  lines up: *"our side had not touched `phpunit.xml` since the base, so git
  reported no conflict at all."* That is the whole mechanism. `merge=ours` is a
  **conflict-resolution driver**; git consults it only when it must do a
  three-way content merge, i.e. only when **both** sides moved the path. Ours
  unchanged + theirs moved = no conflict = the driver never runs = theirs is
  taken silently. A fast-forward is merely one case of it. REV-119 §A blamed the
  fast-forward, REV-121 §A repeated that framing, and run 122 was one dispatch
  away from repeating run 114 through an ordinary merge — the correct diagnosis
  was in this file the entire time and was not read back.
  ⭐ **And the round trip makes the bypass the normal case.** Once Track 1 merges
  this lane into `main`, the merge base becomes a lane commit that already
  contains the lane's copy of every per-track path. After that the lane reads
  unchanged on all of them and only `main` moves, so the lane silently adopts
  `main`'s. **`merge=ours` protects whichever side is "ours" at merge time; it
  cannot protect a path across a lane→main→lane round trip.**
  ⭐⭐ **`supervise.sh` §2f now measures this every run** — the list read from
  `.gitattributes`, never restated — so it is a check rather than a ruling
  somebody must remember. Measured 2026-09-09 on base `e3aea7ff`: **four of
  eight** in the bypass state, including `app/phpunit.xml` and this file.
  ⚠️ **A bypass on `CLAUDE.md`, `bin/supervise.sh`, `.agents/rules/**` or
  `.claude/**` is the SUPERVISOR's to repair and the coder CANNOT do it** —
  `--allow-restore` refuses exactly those paths by design (restoring one would
  discard the supervisor's uncommitted notes, run 27). So a merge wave whose §2f
  names one of them is not fully delegable: the supervisor moves those paths on
  our side **before** dispatching, which makes the driver fire, and the coder
  restores only what it is permitted to touch.
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
- ⛔ **A sentence explaining why a count did not move names a change to the
  TREE, never a change to `BUILD-STATE.json`.** Added 2026-09-09 (REV-132).
  Run 127's report wrote *"none of the stages moved … because restoring the
  `BUILD-STATE.json` in step 1 effectively synchronized it"*. The conclusion was
  right and the cause was inverted: the stages agreed because the **tree** was
  restored; `BUILD-STATE.json` is the record the stages are compared **against**
  and can cause nothing. Believe that inversion once and the next mismatch is
  closed by moving the record — which is the hand-edit `BLOCK` and the
  count-did-not-fall trap arriving together. If a stage number and
  `BUILD-STATE.json` disagree, the tree or the checker moved; say which.
- **A ruling given verbatim in `BRIEF.md` is recorded verbatim.** When the brief
  hands you the exact words of a decision, `state.py decided` gets those words,
  not a summary of them. `REPORT.md`'s `DECIDED` field is
  `tail -n 1 .agents/state/JOURNAL.md` **pasted whole** — never `state.py`'s
  stdout, which prints a confirmation rather than the line that landed. A
  `DECIDED` line that does not begin with the brief's text is a `BLOCK`: it
  means the journal and the ruling have already diverged, and the journal is
  what the next reader has. (Recorded 2026-09-05 under the 08:0x opening; three
  runs had already complied by brief alone. Deleted by the 2026-09-09 09:54
  fast-forward, restored 2026-09-09 as REV-121.)

## ⛔ ADDED 2026-09-10 (REV-135) — AN ARTEFACT THE BRIEF ASKED FOR IS A FILE, NOT A SENTENCE

Three runs running, the brief asked for a measurement to be **pasted** and got a
conclusion instead — `pint --test`'s file list twice, the `grep -c` test counts
once, the mutation's failing assertion text once. Each time the instruction was
correct, cited by name, and restated more firmly on the next run. Restating it
again is not the fix.

**RULED: every measurement a brief requires is redirected to a NAMED FILE under
`.agents/supervisor/`, and `REPORT.md` cites that file by path.** The artefact
then exists whether or not the report quotes it, and the reviewer can read the
real output instead of trusting a retyped number. A brief item that says *"paste
the output"* with no path is an item that will come back as a summary — that is
this project's own evidence, four times over.

⭐ **The precedent is REV-134 §5 and it worked on the first try.** `schema` was
reported without its database twice; the third brief stopped citing REV-119 §B
and handed over the literal line to emit. It came back correct. **A paste-ready
string beats a citation, and a redirect beats a paste-ready string**, because a
redirect cannot be paraphrased.

⛔ **A number in `REPORT.md` with no artefact path beside it is a memory.** Same
standard as REV-132's erratum for the supervisor's own rulings: *any count that
enters a brief or a ruling is produced by a command whose scope is the tree, and
the command is printed beside the number.* The two columns are now held to one
rule.

## ⛔ ADDED 2026-09-10 (REV-137) — A REDIRECT IS AN INSTRUMENT ONLY IF THE BRIEF SUPPLIES THE COMMAND

REV-135's ladder — *a paste-ready string beats a citation, and a redirect beats a
paste-ready string* — has a rung missing at the bottom, and run 132 fell through
it. The brief said **"Dump the stages to `.agents/supervisor/r132-doctor.txt`"**
and gave no command. The file was created, ran to 40 bytes, and read

```
bash: line 1: goaiez: command not found
```

`REPORT.md`, written 41 seconds later, said *"All stages clean. No stage moved"*
and cited that file. Nothing was measured, and the artefact's existence read as
evidence that something had been.

**RULED: a brief item that names a redirect target also gives the command that
fills it, verbatim and runnable.** A named path with no command is the same
defect as *"paste the output"* one level down: it specifies where the answer goes
and leaves how to get it to be reconstructed.

⛔ **And the report's side of it: an artefact is quoted, or it is not cited.**
Every claim in `REPORT.md` that rests on an artefact carries a line **from** that
artefact. A citation-without-a-quote is how a 40-byte shell error became *"all
stages clean"*, and it is the shape REV-131 §1 already ruled on for the gate log.

⛔ **A shell or grep error inside an artefact is a `REFUSED`-shaped event.** The
report says *the command did not run, and here is the error* — never what the
tree contains. Run 132's `r132-seam.txt` carried
`grep: app/routes/routes.generated.php: No such file or directory` (the brief had
named a path that does not exist; the real file is per-module) and the report
turned it into *"the file does not exist"*, a statement about the tree. That is
REV-136 §1's ruling — *a negative result from a scoped command is a statement
about the scope* — arriving in the coder's column for the first time.

⭐ `bin/supervise.sh` §3 now scans every `.agents/supervisor/r*-*` artefact
touched in the last 24h for `command not found`, `No such file or directory`,
`Permission denied`, `syntax error` and `Could not open input file`, prints the
offending line, and sets `fail=1`. It found both of run 132's on its first run —
118 scanned, 2 flagged — which is the instrument standard. **A brief fix protects
one run; a check protects every run** (REV-135 §4).

## ⛔ ADDED 2026-09-02 — THE SUPERVISOR'S WORKING TREE

The supervisor edits `BRIEF.md`, `REVIEWS.md` and its own files **in the
working tree, uncommitted**. ⛔ **Never `git stash`, `git checkout`,
`git restore`, `git clean` or otherwise displace those files.** If a gate or a
status flags them, that is the supervisor mid-thought: leave the files exactly
as they are and note it in `REPORT.md`. A stash of the supervisor's ledger was
dropped once and the record had to be reconstructed; that is why this rule has
its own heading. The same applies to history: **never amend or rebase a commit
that has already been reviewed** — fix forward.
