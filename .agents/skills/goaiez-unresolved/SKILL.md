---
name: goaiez-unresolved
description: Decide it and build (R245), or record a missing dependency. Use the moment you cannot finish something — before you consider stopping.
---

# ⛔⛔⛔ FIRST — IS THIS A DECISION, OR A MISSING DEPENDENCY?

**They are handled in opposite ways, and getting it wrong stalls the build.**

| ⭐ **an unmade DECISION** | **R245: decide it and BUILD.** Mark it `(R245)` where you make it. ⛔ **NOT `UNRESOLVED`** |
| :--- | :--- |
| ⛔ **a missing DEPENDENCY** | `UNRESOLVED` — a table another module owns, a credential that does not exist, a transport nobody built |
| ⚠️ **one of the five owner values** | a new threshold · a retention period · pricing · legal text · reversing a ruling → `TODO(Q-045)` **with your recommendation**, and build around it |

> ⭐⭐⭐ ***"I do not know" is not a stopping condition.***

**Read `.agents/rules/09-r245-r246-delegation.md`.** It supersedes what this
skill used to say about owner decisions.

---

# RECORD A MISSING DEPENDENCY AND CARRY ON

```bash
python3 bin/state.py unresolved <X-nnn> <stage> "<why you did not fix it>"
```

**This does not stop the build.** It parks one module and moves you to the next.

## THE SHAPE

```
UNRESOLVED  <stage>  <where>  — <why>
```

One line. Specific. **Say what would unblock it**, because that sentence is what
the owner acts on.

| ✅ | `UNRESOLVED capability X-204 — consumes subscription.renewed, nothing on the roster emits it` |
| :--- | :--- |
| ✅ | `UNRESOLVED schema X-121 — canonical noun for a work order is undecided; the existing jobs table is Laravel's queue` |
| ⛔ | `UNRESOLVED X-121 — blocked` |

## WHEN TO USE IT

| would fixing this change a **CHECK**? | `UNRESOLVED`. Never edit `app/Doctor` |
| :--- | :--- |
| does it need something that **does not exist yet**? | `UNRESOLVED — <what is missing>` |
| did the count **not fall** after your fix? | `UNRESOLVED`, and move on |
| ⛔ **does it need a DECISION nobody has made?** | **NOT `UNRESOLVED`. Decide it, mark `(R245)`, build** |
| ⚠️ is it a threshold, retention, price, or legal text? | `TODO(Q-045)` with your recommendation — **and build the rest of the module** |

## ⛔ RECORD AN R245 DECISION WHERE YOU MAKE IT

```bash
python3 bin/state.py decided <X-nnn> "<what you chose, and why in one line>"
```

**And write it in the module header or the capability row, marked `(R245)`.**
An undocumented choice is indistinguishable from an accident.

## ⭐ WHY THIS IS THE MOST IMPORTANT COMMAND HERE

Unsupervised work fails two ways: the agent **stops** at the first hard thing, or
it **guesses** and builds something wrong. This is the third option.

> **You are not scored on the count going down.**
> **You are scored on whether the count that remains is TRUE.**

**Three unresolved violations honestly reported are worth more than three hundred
cleared by deletion.**

⛔ **But `UNRESOLVED` is now the narrow door.** Under R245 most of what once went
through it is a decision you make and build. **Reach for it only when something
you need does not exist.**
