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
| a decision you need from the owner | `OWNER-QUESTIONS.md`, then `UNRESOLVED … — needs: <the decision>` |
| something that does not exist yet | `UNRESOLVED — <what is missing>` |
| a fix budget | **there is none.** The previous loop had one, at 20; it was a guess, and a budget that stops useful work is worse than no budget |

## ⭐ WHY `UNRESOLVED` IS THE WHOLE DESIGN

Unsupervised work fails in one of two ways: the agent **stops** at the first
thing it cannot do, or it **guesses** and builds something wrong.

`UNRESOLVED` is the third option, and it is why you never have to choose. It
records the fact, parks that one module, and moves you on. The build keeps
moving; the blocker keeps its evidence; the owner gets a true list at the end.

⛔ **So do not stop to ask a question.** Write it in `OWNER-QUESTIONS.md` — in
plain forwardable prose with no id numbers, because the person answering does not
read this repository — and keep building.

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
