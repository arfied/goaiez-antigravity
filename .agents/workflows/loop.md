# THE AUTONOMOUS LOOP

**Work this loop until it says `FINISHED`. Report once, at the end.**

Do not report after each wave. Do not ask whether to continue. You have a
terminal, the tree and PHP — most of what would round-trip is work you can do
yourself, and each round trip costs the owner a full cycle through WSL, an
agent, and a copy-paste sometimes transcribed off a phone.

---

## THE LOOP, IN FULL

```bash
# once per session, before anything
php artisan doctor:selftest
python3 bin/state.py selftest sound      # or: selftest problems

while true; do
    python3 bin/state.py next            # -> one JSON object with an `action`
    # do what it says
done
```

### `action: BOOTSTRAP`
Run `.agents/workflows/bootstrap.md`. It ends at `php artisan app:deploy-check`.
When that passes: `python3 bin/state.py note bootstrap-done`.

### `action: BUILD_WAVE`
Run `.agents/workflows/wave.md` against the wave it names. Then loop.

### `action: JOURNEYS`
Every module is terminal and journeys are still red. Implement them against real
transports. **Never stub the harness.**

### `action: FINISHED`
`python3 bin/state.py report`, write it up, stop.

### `action: STOP`
Stop. Report the `say` field verbatim and nothing else.

---

## ⛔⛔⛔ THE FOUR THINGS THAT STOP YOU

**These are the only four. Nothing else is one.**

| `RUNTIME` | `doctor:selftest` reports a problem in the **checker**, not your code. `app/Doctor` is sealed — do not patch it. A checker you repaired yourself is a checker nobody reviewed |
| :--- | :--- |
| `SEAL` | the seal digest moved. A sealed file was edited, **or a file arrived outside the bundle** — you cannot tell which and neither can the seal, which is exactly why you must not decide it. Re-run `runtime/goaiez-runtime.sh` |
| `FINISHED` | everything is DONE or honestly UNRESOLVED, and every journey is green |
| `STARVED` | nothing is actionable and work remains — every remaining module is blocked on an owner decision |

## ⛔ WHAT DOES **NOT** STOP YOU

| a red stage | record the count, fix what you can, move on |
| :--- | :--- |
| a failing gate | record `UNRESOLVED`, move to the next module |
| a module you cannot finish | `UNRESOLVED`, next module. **The wave still closes** |
| a missing publisher | subscribe anyway. `not_yet_built_publishers` says it is expected |
| a question for the owner | append to `OWNER-QUESTIONS.md` and keep building |
| an owner decision you need | `UNRESOLVED <stage> <module> — needs: <the decision>` |
| 20 fixes | there is no fix budget. The old loop had one; it was a guess and it stopped useful work |

⭐ **`UNRESOLVED` is the pressure valve that makes unsupervised work safe.** It
is why you never have to choose between stopping and guessing.

---

## AFTER EVERY FIX

```bash
php artisan doctor --stage=<stage>
python3 bin/state.py stage <stage> <n>
```

**If the count did not fall, the fix did not land.** Do not repeat it. Do not
try something else on the same violation. Record `UNRESOLVED` and move on.

---

## KEEP A TASK ARTIFACT

Antigravity has no todo tool. At the start of each wave, write a task artifact —
`write_to_file` with `IsArtifact: true` and `ArtifactMetadata.ArtifactType:
"task"` — listing every module in the wave and the three gate commands. Mark
each `- [x]` as it lands, with `replace_file_content`. Once the conversation is
long, **re-read it before starting each step**: it is your source of truth for
what remains, and `bin/state.py next` is the source of truth for what is next.

---

## THE FINAL REPORT — ONE MESSAGE

```
SELFTEST : sound | N problems
STAGES   : integrity <n> · boundary <n> · contract <n> · citation <n>
           schema <n> · capability <n> · anchor <n> · journey <n>
MODULES  : <n> done · <n> unresolved of 124
JOURNEYS : <n>/12 green
FIXED    : <one line each — what changed and which count fell>
UNRESOLVED:
  <stage>  <where>  — <why>
OWNER    : <the decisions in OWNER-QUESTIONS.md that are now blocking>
```

⛔ **Paste raw `doctor` output for anything you did not fix.** Every wrong turn
in this programme came from acting on a paraphrase — *"mostly prose in
backticks"* cost three rounds, and the raw text would have cost none.
