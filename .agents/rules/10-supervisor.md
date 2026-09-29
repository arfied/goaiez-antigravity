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
STATUS    : wave closed | stopped: RUNTIME|SEAL|FINISHED|STARVED | brief item done   (a STATE, never a verdict — PASS/BLOCK are the supervisor's words)
COMMITS   : <the full shas this wave made, the MERGE commit first, then the merged tip — never a count, never a paste of every commit since origin/main (N160, N174 note)>
MODULES   : X-nnn DONE · X-nnn UNRESOLVED (<what is missing>)
STAGES    : <stage> <before> → <after> from THIS wave's two doctor files, or `none moved` — never a move carried from an earlier wave>
TESTS     : <file>  needle `public function test_` for a PHPUnit class, `test(\|it(` for Pest — before <n> after <m>, and baseline + added = suite total>
GATE      : <the gate's own `tests N · passed N · FAILED N · errors N · result …` line verbatim, followed by ONE `ls -la --time-style=full-iso` of the gate file (N160)>
DECIDED   : (R245) <one line each, as recorded with state.py decided>
UNRESOLVED: <stage> <where> — <what is missing>
REFUSED   : <brief items that would change a CHECK, with the reason> | none
DOCTOR    : <the eight stage counts from `php artisan doctor`, never the `STAGES` line of supervise.sh (N115)>
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
- **Push step (Track 1, added 2026-09-03 — the owner automated pushes).** The
  FIRST thing every run does, before reading the task: if `BRIEF.md`'s `push:`
  line reads `YES — <from>..<to>`, run `git push origin main` and confirm the
  output names exactly that range (or a prefix of it ending at `<to>`). Quote
  the `<from>..<to>  main -> main` line in `REPORT.md` under `PUSHED`. If the
  push is refused or the range differs, STOP and report — never `--force`,
  never push a tip newer than `<to>`. If the line reads `NO` or `⛔`, push
  nothing. A run that is only a push (`KICKOFF` says so) ends right after it.
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

## ⛔ ADDED 2026-09-27 — EVERY SOCIAL, REVIEW AND MESSAGING CHANNEL GOES THROUGH ZERNIO (owner's boss, standing)

Google Business Profile (posts, reviews, replies), WhatsApp (connect, templates, sends, inbound), Facebook, Instagram, LinkedIn, TikTok and the other networks Zernio lists, and inbox DMs/comments/reviews are reached **only through Zernio** (`https://zernio.com/api/v1`, the existing `app/Services/Gbp/ZernioGbpClient.php` shows the auth, errors and credential). **Never** write a call to `graph.facebook.com`, the WhatsApp Cloud API, `mybusiness*.googleapis.com` or any Google Business Profile API, and never extend `App\Services\Providers\MetaService` — it has no callers and is not the path. A brief that touches one of these channels quotes the Zernio endpoint it read from the live docs (`.agents/supervisor/ZERNIO-DOCS-2026-09-27.md`); **work from that text, never from memory of Meta's or Google's own APIs** — that memory is exactly how this repo kept drifting back to them. If a brief's endpoint looks wrong, STOP and say so; do not substitute an official-API shape. Exceptions Zernio does not offer stay direct: Search Console, Google Places, Gmail, and browser Web Push.

## ⛔ ADDED 2026-09-29 — BEFORE YOU TOUCH A PATH OUTSIDE YOUR OWN MODULE, READ THE OWNERSHIP TABLE

`.agents/supervisor/LANE-OWNERSHIP.md` — a copy sits in your own mailbox, and the canonical one is
`/home/goaiez/agents/grs-antig/.agents/supervisor/LANE-OWNERSHIP.md`, which you can read directly
because every lane is a git worktree of the same repo. **The `grs-antig` copy rules if they differ.**

It exists because two waves on 2026-09-29 had to stop for a supervisor ruling on a path no table could
express — `app/app/Services/Voice/VoiceCalls.php` and `app/app/Livewire/Admin/PlatformSettings.php` —
since the old table listed **module ids** and roughly two thousand tracked files live outside
`app/app/Modules/`.

**The rule it encodes: a file outside `app/app/Modules/` belongs to whoever owns the SUBJECT it serves.**
Voice is sixty's, so a voice service is sixty's. X-103 is site's, so an `AiTask` case for site authoring
is site's.

Three things it asks of you:

1. **Your brief names every path you write.** If the work needs a path the brief does not name, that is
   the signal to stop — not to widen scope quietly.
2. **A CONTENDED path** (§3 — `app/app/Services/Zernio/`, `app/app/Support/DefaultsManifest.php`,
   `app/app/Enums/`, `app/tests/` outside `Modules/`) is written only when your brief names the **exact
   file** as its own item. More than one lane legitimately writes there, so the protection is the brief,
   not an owner.
3. ⛔ **If the table is silent about a path, write `UNRESOLVED` with the path and stop. Do not reason
   from "nearest fit"** — the supervisor did exactly that on 2026-09-29, assigned `app/app/Livewire/` to
   the wrong lane, and the data refuted it within the hour.

⭐ **Editing another module is not forbidden — reaching into its tables is.** `Events\`, `Actions\` and
`Domain\` imports are the sanctioned seams and `boundary` exempts them; a `Models\` import across a module
boundary is what that stage flags, and it went `0 → 2` on `main` that same day for exactly this. **The
module's owner is who you ask for a new seam.**
