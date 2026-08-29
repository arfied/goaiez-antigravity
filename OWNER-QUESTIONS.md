# QUESTIONS FOR THE OWNER

# ⭐⭐⭐ THERE ARE NONE. ALL 46 WERE ANSWERED ON 2026-08-29.

**This file used to carry four. Every one of them now has an answer, and they
are recorded below so nobody re-opens them.** The governing ruling is `R245`:

> **Where the plan does not decide, the building agent decides — and BUILDS.
> A module is never blocked waiting for a ruling that does not exist.**

⛔ **An autopilot package with a question in it is a stalled autopilot.** If you
are the agent and you find yourself wanting to add a question here — **you have
almost certainly found a decision, and R245 says decide it.** Read
`.agents/rules/09-r245-r246-delegation.md`.

---

## THE FOUR THIS FILE ASKED, AND THEIR ANSWERS

| ① **what does "job" mean?** | ⭐ **The canonical noun is `work_orders`. Laravel keeps `jobs`.** Moving a framework's queue table to free a name is a migration with no upside; renaming our own noun costs one line in the plan and nothing at runtime |
| :--- | :--- |
| ② **one word per thing** | ⭐ **`businesses` is canonical** *(`X-121` owns it, RLS forced)*; `companies` and `customers` are LEGACY → REPLACE. **`facts` is canonical**; `business_facts` is LEGACY |
| ③ **the 322 refusals** | ⭐ **Not an owner task at all — written per module, during that module's build, by the building agent.** A refusal is easiest to write while you are holding the thing that refuses |
| ④ **a ceiling on the COMPLEX AI slot** | ⭐ **Capped under `C-Ai`'s tenant and platform ceilings.** *A slot that can escape the budget is not a slot, it is a leak.* And **the JOB CLASS decides what is "complex"**, declared as a ROW — never a per-call heuristic |

⚠️ **Two of these were also the recommendations this package made before the
answers arrived — `work_orders` and capping the COMPLEX slot.** Recorded because
agreement is not the same as confirmation, and the answer is what governs.

## SIX MORE, DECIDED IN THE SAME PASS

| the `fact` doctor stage | **KEEP as a gap**, do not delete — deleting an unbuilt intention loses a requirement |
| :--- | :--- |
| the legacy tree | **REPLACE runs per module**, in the same commit as the module that replaces it. ⛔ Never a bulk deletion |
| the redaction boundary | **self-host the highest-sensitivity job classes** |
| `X-218` | **BUILD IT** — a gap, not a duplicate |
| `X-216` | ⛔ **PERMANENTLY RETIRED — never assign** |
| `X-126` `X-119` `X-121a` | *the package rules they survive, because "start over" meant the legacy tree.* ⚠️ **In THIS tree they do not exist** — a fresh build was called and the older trees are off limits, so they are simply **wave 1** here |

---

## WHAT IS STILL THE OWNER'S — AND IT IS A FILTER, NOT A BACKLOG

**Five kinds of decision, always:**

| ⛔ a new **threshold** — a number that changes behaviour |
| :--- |
| ⛔ a **retention period** |
| ⛔ **pricing** |
| ⛔ **legal text** |
| ⛔ anything that **REVERSES a ruling** |

> **The test: does the choice change what the platform CHARGES, KEEPS, PROMISES
> IN LAW, or has already RULED?** Then stop. Otherwise decide it, write
> `(R245)`, and build.

⚠️ **Searched 2026-08-29: zero open instances of retention, pricing or legal
text, and all five threshold matches are already decided. Nothing is waiting.**

⛔ **And "stop" never means stop the module.** Build everything around it, leave
the one value as `TODO(Q-045)` **with your recommendation**, and keep going.

---

## ⚠️ ONE THING THE PACKAGE ITSELF FLAGS AS ITS LEAST CONFIDENT

**Renaming the canonical noun to `work_orders`.** It touches `X-121`'s
`@owns_table`, every capability row that says "jobs", and any prose using the
word.

> *"If **jobs** is the word the business actually uses with customers, the rename
> costs you vocabulary you cannot get back."*

⭐ **It is decided and the build will proceed on `work_orders`.** Raised here
only because it is cheap to reverse now and expensive later.
