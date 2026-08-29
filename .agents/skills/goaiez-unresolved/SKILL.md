---
name: goaiez-unresolved
description: Record a blocker and keep building. Use the moment you cannot finish something — before you consider stopping.
---

# RECORD IT AND CARRY ON

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
| does it need an **owner decision**? | `UNRESOLVED`, and name the decision |
| does it need something that **does not exist yet**? | `UNRESOLVED — <what is missing>` |
| did the count **not fall** after your fix? | `UNRESOLVED`, and move on |
| would you be **guessing**? | `UNRESOLVED` |

## ⛔ AND WRITE THE OWNER'S HALF SOMEWHERE THEY CAN READ IT

Append to `OWNER-QUESTIONS.md`, in **plain forwardable prose with no id
numbers** — the person answering does not read this repository and should not
have to. State the question, the options, and what each one costs.

Then keep building.

## ⭐ WHY THIS IS THE MOST IMPORTANT COMMAND HERE

Unsupervised work fails two ways: the agent **stops** at the first hard thing, or
it **guesses** and builds something wrong. This is the third option.

> **You are not scored on the count going down.**
> **You are scored on whether the count that remains is TRUE.**

**Three unresolved violations honestly reported are worth more than three hundred
cleared by deletion.** Say so early — `UNRESOLVED` with a reason is worth more
than an hour of trying.
