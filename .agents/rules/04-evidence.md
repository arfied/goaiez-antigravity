# 04 — EVIDENCE

**Every gate returns a boolean from something OBSERVED, never from something
asserted.** Where a gate cannot observe, it returns FALSE — it is refusing to
judge, not judging against you.

## ⛔⛔ NEVER FABRICATE AN EVIDENCE FILE

`render.json` · `runtime-proof.json` · `lint.json`

**A proof you wrote in order to pass a gate is the one thing this whole system
exists to prevent.** If you are writing the file the gate reads, you are not
passing the gate, you are forging it.

## ⛔ GATE 2: A GENERATED ID IS NOT AN ISSUED ONE

`Str::ulid()` was once accepted as an "external artifact id". It is not one.

> **A string is not an artifact.**

The runtime proof must carry an id **something outside this process issued**:

| a carrier | a message id / SID |
| :--- | :--- |
| a gateway | a charge id |
| a provider | a request id |

The test is simple: **could you find this id in somebody else's system?** If
only this process has ever seen it, it is not evidence.

## ⛔ THE HASH CHAIN

`doctor:module-done` appends to `INSTRUCTIONS.jsonl`, and **refuses to record
DONE at all if the chain is broken.**

Without it, DONE means somebody said so. With it, DONE means there is a
hash-chained, reason-carrying line that cannot be edited without breaking every
entry after it.

⭐ **This exists because a phase of this programme claimed 41 changes were
applied and measurement found ZERO.** A claim that outlives its evidence is the
most expensive defect here.

## ⛔ A JOURNEY ON THE `sync` DRIVER PROVES NOTHING

Laravel runs queued jobs synchronously in tests. A journey on `sync` **would
pass with no worker running**. The harness refuses it, and it is right to.

## ⛔ AND THE HARNESS THROWS ON PURPOSE

`tests/Journeys/JourneyHarness.php` — every method throws until it is implemented
against the real system.

**The alternative — returning plausible fixtures — makes all twelve journeys
PASS while touching no carrier, no gateway, no queue and no database.** A green
journey suite that proves nothing is worse here than anywhere else, because
journeys are the only checks that cross a module seam.

## ⭐ THE HABIT THAT CATCHES ALL OF THIS

**Run it before you ship it.**

The single root cause of the twenty-odd defects in `source/GOAIEZ-AUDIT-LEDGER.md`
is that for 29 turns the author could not execute PHP, so **every file ran for
the first time on the owner's machine.** You have a terminal. There is no excuse
and you must not create one.
