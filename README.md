# grs-antig — the GO AI EZ autopilot package

**A build plan Antigravity executes unsupervised, and everything it needs to do
it.** Fresh build. 124 modules, 31 waves, 12 journeys.

---

## Start here

```bash
cd /home/arf/dev/grs-antig
bash bin/loop.sh                 # session preamble — checks the checker
python3 bin/state.py next        # do what it says. Run it again. That is the loop.
```

The agent reads `AGENTS.md` (and `GEMINI.md`, which points at it) on its own.

---

## What is in here

| `BUILD-PLAN.md` | ⭐ **generated** — the 31 waves, what orders them, what "done" means |
| :--- | :--- |
| `build-plan.json` | the same thing for a machine: waves, wiring, journeys, orphan events |
| `AGENTS.md` · `GEMINI.md` | the entry contract every agent reads first |
| `OWNER-QUESTIONS.md` | ⭐ the four decisions only the owner can make, in forwardable prose |
| `.agents/rules/` | seven rules — precedence, the one rule, autonomy, the module contract, evidence, forbidden, and the measured defect shapes |
| `.agents/skills/` | six skills — module · doctor · journey · schema · unresolved · ai |
| `.agents/workflows/` | bootstrap (once) · loop (the whole control flow) · wave (3–5 modules) |
| `.agents/state/` | `BUILD-STATE.json`, owned by `bin/state.py`. `JOURNAL.md` appends |
| `bin/` | the generator, the validator, the state machine, the session preamble |
| `runtime/` | `goaiez-runtime.sh` — the whole runtime as one self-extracting file |
| `source/` | the package: the 3.26 MB plan, the index, the 222-finding ledger |
| `docs/COMPARISON.md` | this plan against the one in `goaiez-review-system` |

---

## The plan is generated, not written

```bash
python3 bin/generate-plan.py     # rebuild from source/GOAIEZ-INDEX.json
python3 bin/validate-plan.py     # 23 claims, checked
```

⭐ **No count in the plan is typed.** The source package measured its own problem:
*roster 119 appears in 47 places, 122 in 49, and the current 124 in 8* — an agent
handed all the files reads the majority and is wrong. A generated plan cannot
drift that way, and the validator fails if the prose and the data disagree.

**When the laws grow: update `source/`, re-run both, commit.** Never edit a
number by hand.

---

## Why it does not stop

The loop stops for **four** reasons: `FINISHED` · `RUNTIME` · `SEAL` · `STARVED`.

Everything else — a red stage, a failing gate, a missing publisher, a decision
that has not been made — is recorded as `UNRESOLVED` and the build moves to the
next module.

> **You are not scored on the count going down.**
> **You are scored on whether the count that remains is TRUE.**

---

## Off limits

⛔ `/home/arf/dev/goaiez-review-system` and `.../-antigravity` are different
builds under a different plan. Prior art at most — never read for requirements,
never written to.

⛔ `app/Doctor/**` and `seals.json` are sealed. A mismatch is a finding. Re-run
the installer; **never re-seal.**
