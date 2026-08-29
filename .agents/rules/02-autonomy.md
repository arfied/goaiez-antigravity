# 02 — AUTONOMY: WHEN TO STOP

**You work until `bin/state.py next` says `FINISHED` or `STOP`. Nothing else
stops you.**

## ⛔⛔⛔ THE FOUR STOP CONDITIONS

| `RUNTIME` | `doctor:selftest` reports a problem in the **checker**, not your code |
| :--- | :--- |
| `SEAL` | the seal digest moved |
| `FINISHED` | every module DONE or honestly UNRESOLVED, every journey green |
| `STARVED` | nothing is actionable and work remains |

## ⛔ WHAT DOES NOT STOP YOU — AND ALL OF THESE WILL HAPPEN

| a red stage | record the count, fix what you can, move on |
| :--- | :--- |
| a failing gate | `UNRESOLVED`, next module |
| a module you cannot finish | `UNRESOLVED`, next module. **The wave still closes** |
| a missing publisher | subscribe anyway — `not_yet_built_publishers` says it is expected |
| ⛔ **a decision nobody has made** | **R245: DECIDE IT AND BUILD**, marked `(R245)`. ⛔ **Not `UNRESOLVED`** — rule 09 |
| a threshold, retention, price or legal text | `TODO(Q-045)` with your recommendation, and build around it |
| something that does not exist yet | `UNRESOLVED — <what is missing>`. **The only thing `UNRESOLVED` is for** |
| a fix budget | **there is none.** The previous loop had one, at 20; it was a guess, and a budget that stops useful work is worse than no budget |

## ⛔⛔⛔ AND `UNRESOLVED` IS NARROWER THAN THIS FILE ONCE SAID

**Corrected 2026-08-29 by `R245`. Read rule 09.**

An earlier version of this rule told you to record `UNRESOLVED` when something
*"needs an owner decision."* **That is wrong and it would stall the build.**

| `UNRESOLVED` is **ONLY** for a **MISSING DEPENDENCY** | a table another module owns · a credential that does not exist · a transport nobody built |
| :--- | :--- |
| ⛔ **never** for | an unmade decision. ***"I do not know" is not a stopping condition*** |

⭐ **Where the plan does not decide, YOU decide and BUILD** — mark it `(R245)`
where you make it, and the owner may overrule it later. The five things that are
still the owner's are in rule 09, and even those do not stop the module.

## THE STOP-THAT-STAGE RULE

After a fix, re-run **only that stage**. **If the count did not fall, your fix
did not land.**

⛔ **Do not fix it again. Do not try something else.** Find out why the first
change had no effect, or record `UNRESOLVED` and move on. An edit that reports
success and changes nothing has happened repeatedly here — six times that anyone
counted — and each time the next hour went on fixing what was already fixed.

## VERIFY WHICH CODE YOU ARE RUNNING

`doctor`'s first line names its build. If you did not just install that build,
you are reading old results. Three consecutive runs once produced byte-identical
output because the files were re-downloaded into a folder and never copied into
the tree, and **nothing in any output said so.**
