---
name: goaiez-doctor
description: Work the eight doctor stages down. Use after building, or whenever a stage is red.
---

# WORK THE STAGES

```bash
php artisan doctor:selftest      # FIRST. Is the CHECKER sound?
php artisan doctor               # all 8
php artisan doctor --stage=<s>   # one
```

⛔ **`doctor:selftest` audits the CHECKER, not the platform.** They are different
questions and mixing them cost this project weeks. If it reports a problem **in
the runtime itself**: stop, paste that output, nothing else. Do not patch
`app/Doctor` — it is sealed, and a checker you repaired yourself is a checker
nobody reviewed.

## THE SEVERITY LADDER

| `integrity` | COMMIT | **the stage that watches the other stages.** Red = stop |
| :--- | :--- | :--- |
| `boundary` | COMMIT | pure grep, ~2s. Cheapest thing in the pipeline |
| `contract` | COMMIT | reads the annotations, never the prose |
| `citation` | COMMIT | every cited law resolves |
| `schema` | MERGE | tenancy and RLS, ~20s |
| `capability` | MERGE | id-matched, two-pass |
| `anchor` | WAVE | a real message-id, not an assertion |
| `journey` | WAVE | end to end, real transports |

⭐ **A MERGE-level violation does not stop you working — it stops you closing.**
Commit, record, continue.

## FOR EACH VIOLATION, IN THIS ORDER

1. **Can you fix the SYSTEM?** Do it. Re-run that stage. **The count must FALL.**
2. **Would you be changing a CHECK?** → `UNRESOLVED`. Never edit `app/Doctor`.
3. ⛔ **Needs a decision nobody has made?** → **R245: decide it and build**, marked
   `(R245)`. **Not `UNRESOLVED`.** *(A threshold, retention period, price or legal
   text is the owner's — leave `TODO(Q-045)` with your recommendation and build
   the rest.)*
4. **Needs something that does not exist yet?** → `UNRESOLVED — <what is missing>`.
   **A missing dependency is the only thing `UNRESOLVED` is for.**

## ⛔ THE STOP-THAT-STAGE RULE

```bash
php artisan doctor --stage=<stage>
python3 bin/state.py stage <stage> <n>
```

**If the count did not fall, the fix did not land.** Do not fix it again, do not
try something else — record `UNRESOLVED` and move to the next stage.

## WORTH FIXING, MECHANICALLY

| `boundary` | **`match()` default arms** — enumerate the cases |
| :--- | :--- |
| `boundary` | **hardcoded model strings** — route through `C-Ai`, never name a vendor |
| `contract` | **prose inside a declaration** — move it past the closing backtick, re-scaffold |

## ⛔ DO NOT TOUCH

| `capability` | 966 rows need assertions and refusals **written**. That is an owner decision |
| :--- | :--- |
| `journey` | 12 of 12 failing is the **designed** state until the harness is implemented against real transports |
