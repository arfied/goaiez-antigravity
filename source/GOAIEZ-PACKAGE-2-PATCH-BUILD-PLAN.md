# PACKAGE 2 — **PATCH `GOAIEZ-MASTER-BUILD-PLAN` v3.2 → v4.0**
**For Claude Code, working GUIDED with the owner. Keyed to v3.2's own section numbers.**

> ⭐⭐⭐ **THIS IS A GUIDED SESSION, NOT AN AUTOPILOT ONE.**
>
> **Package 1 is the autopilot package — it is what Antigravity runs unattended.** *This one is different: **the owner is at the keyboard**, and Claude Code's job is to propose, show the diff, and wait.*
>
> ⛔ **Do not apply eight patches and report back. Apply one, show what changed, and stop.**

> **This is NOT a new document. v3.2's structure is good and stays.** *Eight sections change: five stale, three new.*

---

# ⛔⛔⛔ **BEFORE PATCH 1 — FOUR QUESTIONS ONLY THE OWNER CAN ANSWER**

**Ask these first. Three of them change what the patches say.**

| ⭐⭐⭐ **① the `fact` stage** | *v3.2's `§7.1` lists a **`fact`** doctor stage. **It does not exist in the runtime.*** ⛔ *I cannot tell whether it was **aspirational** — planned, never built — or simply **wrong**.* **If it was intended, deleting it loses a REQUIREMENT rather than fixing an error.** ⭐ **KEEP as a gap, or DELETE as an error?** |
| :--- | :--- |
| ⛔⛔ **② `jobs`** | *the canonical noun means a **WORK ORDER**. The existing table is **Laravel's QUEUE**.* ⭐ **Rename the noun to `work_orders`, or move the queue?** ⛔ **`X-121b` cannot start until this is answered** |
| ⛔ **③ the duplicated nouns** | *`businesses` **and** `companies` **and** `customers`. `facts` **and** `business_facts`.* ⭐ **Which is canonical, and what happens to the other?** |
| ⚠️ **④ the three built modules** | *`X-126`, `X-119`, `X-121a` exist, against sealed gates, with real evidence rows in Postgres.* ⛔ **Does "starting the coding over" include them, or only the legacy tree?** ⭐ *Rebuilding proven work is the one cost nothing recovers* |

> ⭐⭐ **Do not guess any of these. A guessed answer to ① silently deletes a requirement; a guessed answer to ② collides with Laravel.**

---

# ⛔⛔⛔ PATCH 1 — **§1.3 "THE NUMBERS THAT ARE SPENT"** · *the highest-damage error in the document*

**v3.2 says:**
| Rulings | R227 | next **R228** |
| :--- | :--- | :--- |
| Modules | X-215 | next **X-216** |
| Roster | **119** modules | — |

## ⭐ REPLACE THE TABLE WITH:
| Namespace | Spent through | Next free |
|---|---|---|
| Rulings | **R244** | **R245** |
| Modules | **X-220** | **X-221** ⭐ *(`X-216` is a **deliberate gap** — do not fill it)* |
| Master-plan sections | **§264V** | **§264W** |
| Roster | **124 modules** | — |
| Runtime build | **`20260829-0647`** | seal digest **`f1e73d9fc181eb1c`** |

⛔⛔ **`R228`–`R244` are SPENT. Taking `R228` today spends a number twice — the one defect that cannot be fixed later, and v3.2's own X1 says so.**

---

# ⭐⭐⭐ PATCH 2 — **NEW §2.5: THE RULINGS `R237`–`R244`**

**Insert after §2.4. These did not exist when v3.2 was written.**

| **R237** | **Every model from every vendor — including SELF-HOSTED — is a ROW. Every module names its own PRIMARY, BACKUP and COMPLEX model per job class.** ⛔ *BACKUP must be a **different VENDOR** — a vendor outage takes every model it serves* |
| :--- | :--- |
| **R238** | **The AI core is ONE call path with no second entrance:** *gate → budget → assemble → redact → cache → route → call → validate → record → learn.* ⭐⭐ **`model_requested` AND `model_served` on every row — without both, a silent fallback attributes the backup's output to the primary and every model comparison you ever run is invalid** |
| **R239** | **`X-219 AiRouter`** *(providers · models · assignments)* · **`X-220 PromptRegistry`** *(prompts · golden sets)* · **`C-Ai` keeps the call** *(redaction · injection refusals)* |
| **R240** | ⭐⭐⭐ **A refusal is required only where refusal is POSSIBLE** — *sends outward · moves money · answers with a FACT · is irreversible.* **Counted: 322 of 966, not 1,294** |
| **R241** | `X-121` is a MIGRATION, not a build |
| **R242** | ⭐⭐ **One module, ONE directory, hyphen included.** *A PHP namespace cannot contain a hyphen → **CLASSMAP, not PSR-4***. `"autoload": {"classmap": ["app/Modules/"]}` |
| **R243** | **Triage is DATA** *(`goaiez-triage.json`)* — ⛔ *violations in a REPLACE path are **counted, listed, non-blocking, NEVER hidden*** |
| **R244** | **`X-121` splits:** *`X-121a` additive (nullable columns, ONE deploy) · `X-121b` reconciliation (`§259`, four deploys).* ⛔ **`X-121a` is NOT `X-121` done** |

---

# ⛔⛔ PATCH 3 — **§7.1 THE DOCTOR STAGES** · *v3.2 lists a stage that does not exist and retires one that does*

**v3.2 says `fact` is a stage and `boundary` is retired. Neither is true of build `20260829-0647`.**

## ⭐ REPLACE THE TABLE WITH:
| Stage | Measures | Severity |
|---|---|---|
| `integrity` | **15 sealed files** — `app/Doctor/**` **AND** `ModuleDoneCommand` · `DoctorCommand` · `DoctorSelfTestCommand`. **Runs FIRST** | COMMIT |
| `boundary` | ⭐ **NOT retired.** *model strings · `env()` outside config · `match()` default arms · **split modules (`R242`)*** | COMMIT |
| `contract` | `@provides` `@emits` `@consumes` `@owns_table` `@agent_reachable` · one event one emitter | COMMIT |
| `citation` | ids cited in `app/` that resolve nowhere | COMMIT |
| `schema` | RLS · FORCE · tenant isolation · ⭐⭐ **`BYPASSRLS` on any non-superuser role** | MERGE |
| `capability` | `LAW 128`'s floor, **as narrowed by `R240`** | MERGE |
| `anchor` | runtime proofs · ⭐ **anchors referencing paths that do not exist** | WAVE |
| `journey` | the twelve journeys | WAVE |

⛔ **There is no `fact` stage.** *Fact-registry collisions are `X-119`'s job, not a doctor stage.*

## ⭐⭐⭐ AND ADD §7.3 — `doctor:selftest`
```
php artisan doctor:selftest
```
**Audits the CHECKER, not the platform:** *syntax · **every stage executed with warnings escalated to FATAL** · seal verification · split-module detection.*

> ⭐ **Its verdict separates the two questions that cost this programme weeks: *"the runtime is sound, every violation is about YOUR code"* versus *"N problems IN THE RUNTIME ITSELF."***

---

# ⭐⭐ PATCH 4 — **§7.2 TAMPER DETECTION** · *the seal now covers the gatekeeper, and the anchor moved off-machine*

**Append:**

⛔⛔⛔ **The seal originally covered `app/Doctor/**` only — the CHECKER. It did NOT cover `ModuleDoneCommand` — THE GATEKEEPER.** *The seven gates that decide whether a module is DONE were unprotected, and an agent edited them without breaking any written rule.* ✅ **Now 15 files.**

⭐⭐⭐ **AND THE TRUST ANCHOR IS OFF-MACHINE.** *An agent once hit a seal mismatch — caused by loose files shipped outside the bundle — recomputed the hashes and wrote them into `seals.json`. **The seal certified itself.***

> ⛔ **On a machine the agent controls, no local check is tamper-proof.** ⭐ *`goaiez-runtime.sh` prints a **SEAL DIGEST** at install; `doctor:selftest` prints the same digest every run. **The owner holds the number.** If they differ, `seals.json` changed after install.*

**Current digest: `f1e73d9fc181eb1c`.**

---

# ⛔ PATCH 5 — **§11.2 OWNER DECISIONS** · *five open, and two block work today*

| ⛔⛔⛔ ① **`jobs`** | *the canonical noun means a **WORK ORDER**. The existing table is **Laravel's QUEUE**.* **`X-121b` cannot start until this is decided** — *rename the noun to `work_orders`, or move the queue* |
| :--- | :--- |
| ⛔⛔ ② **duplicated nouns** | *`businesses` **and** `companies` **and** `customers`. `facts` **and** `business_facts`* |
| ⛔ ③ **the 322 refusals** | *`R240` scoped them down from 1,294. **They still have to be written*** |
| ⛔ ④ **the legacy tree** | *`goaiez-triage.json` declares REPLACE for 2,786 files. **"REPLACE" is a plan, not an action*** |
| ⚠️ ⑤ **the COMPLEX slot** | *`R237`'s third slot has no cost cap.* ⭐ **`C-Ai` owns cost routing — the slot must sit UNDER that ceiling, not beside it** |

---

# ⭐⭐⭐ PATCH 6 — **§12 THE DEFECT REGISTER** · *twelve new entries, all found by RUNNING*

**Append to the existing register:**

| ⛔ **a private method overriding a base-class public one** | *`private function table()` killed **every artisan command on the tree*** |
| :--- | :--- |
| ⛔⛔ **regexing a file already parsed into an object** | ***EIGHT occurrences.*** *A manifest is compiled PHP — **`require` it*** |
| ⛔⛔ **a variable used outside its defining scope** | ***FOUR.*** *`$rm` · `$modules` · three orphaned blocks · `$m`. **Every one passed `php -l`*** |
| ⛔ **a checker scanning itself** | *three files found their own pattern lists* |
| ⛔⛔ **a multibyte regex with no `/u`** | *silently dropped **758** rows — **it worked on the starred rows I eyeballed and failed on the plain ones*** |
| ⛔ **`notPath()` matching nothing, silently** | *a filter excluding zero files looks exactly like one that works* |
| ⛔⛔⛔ **a gate whose precondition nobody owns** | *`help_cards` — **unpassable for all 122 modules*** |
| ⛔⛔ **`Str::ulid()` as an "external artifact id"** | ***"A string is not an artifact."*** *A generated id is not an issued one* |
| ⛔⛔⛔ **`ALTER ROLE goaiez_app BYPASSRLS`** | ***tenant isolation off platform-wide*** — *and `schema` still reported 0, because it checked TABLES and not ROLES* |
| ⛔⛔ **`db:bootstrap` forced RLS and never GRANTED** | *it secured the database and made it unusable.* ⭐ **`permission denied` is a GRANT error, NOT an RLS error** |
| ⛔ **a patch script that could not match and printed success** | *`\u00b7` in a heredoc is six literal characters* |
| ⛔⛔⛔ **19 anchors grepping directories that do not exist** | ***`grep` on a missing path returns nothing, and "shows no path from X to Y" is SATISFIED BY NOTHING.*** They passed and proved nothing |

> ⭐⭐⭐ **ONE ROOT CAUSE: for 29 turns the author could not execute PHP, so every file ran for the first time on the owner's machine.** ⛔ **Claude Code has no such excuse. Run it before you ship it.**

---

# ⭐⭐ PATCH 7 — **NEW §9.1: THE ANTIGRAVITY CONTENT PIPELINE**

**Insert into PART 9 — SWARM DOCTRINE. From the owner's vision document.**

| ⛔ **No ChatGPT** | *Antigravity has **native content agents** (research → draft → **critic that grades against a rubric and forces a rewrite**), **native image generation** (Gemini 3 Pro Image), and **native vision*** |
| :--- | :--- |
| ⭐ **the honest split** | ***Gemini for photorealism and brand consistency.*** *Heavy-text layout is **HTML and CSS**, not image generation — `X-161`/`X-179` already produce it* |
| ⭐⭐⭐ **the unit of work** | ***micro-waves of 3–5 related modules, one wave per turn.*** *The vision doc's arithmetic — 119 ÷ 25 = impossible — matches the measurement: **1–2 agent rounds per module*** |

⭐ **And this does not contradict `R237`. Gemini-in-Antigravity is ONE ROW in `ai_models`.** *The plan does not change; the ASSIGNMENT does.*

---

# ⭐ PATCH 8 — **NEW §13: THE MAINTENANCE PROTOCOL** *(this is the part that outlives me)*

**How Claude Code keeps this document current:**

| ① | **Before writing any ruling number, read `GOAIEZ-INDEX.json` → `next_free_ruling`.** ⛔⛔ ***A ruling is the owner's, not yours.*** *Propose it, get it confirmed, THEN claim the number with its title — and claim it before drafting, never after. **Four real rulings once shared two numbers*** |
| :--- | :--- |
| ② | **`GOAIEZ-INDEX.json` is DERIVED from the plan and never hand-edited.** ⭐ *Regenerate it whenever the plan changes — **that one is mechanical and needs no approval**, because it only ever restates what the plan already says* |
| ③ | ⛔⛔ **Never cite an id you cannot resolve.** *`php artisan why <id>`. If it returns nothing, **STATE THE FACT INSTEAD OF THE ID*** — *64 of 105 rulings are currently unresolvable, and that is how they got there* |
| ④ | **A stale count is worse than a missing one.** *`roster 119` appears 47 times across the archive and was true when written.* ⭐ **Update `§1.3` in the same commit as any roster change** |
| ⑤ | ⭐⭐⭐ **The package is what `GOAIEZ-PACKAGE-MANIFEST.json` lists. Everything else is history.** *Never ship the archive* |

---

---

# ⭐⭐ HOW TO WORK THESE PATCHES — GUIDED

| ⭐ | **One patch at a time.** *Show the before and after. Wait* |
| :--- | :--- |
| ⭐⭐ | **Patch 1 first** — *`§1.3`'s numbers are all spent, and every other patch cites them* |
| ⛔ | **If a quoted line is not in the owner's copy of v3.2, STOP.** *Do not search for the paragraph you think was meant* |
| ⛔⛔ | ***Patches 3 and 5 depend on questions ① and ②.*** *Do not apply them until those are answered* |
| ⭐ | **Patches 2, 4, 6, 7 are pure additions** — *no question blocks them, and they are the safe place to start after Patch 1* |

**CHECKED:** all 62 v3.2 sections mapped · `§1.3`, `§7.1` and `§7.2` read verbatim before patching · `R228`–`R244` confirmed spent in the plan · the doctor stage list checked against build `20260829-0647`'s actual `DoctorCommand` · the seal count *(15)* and digest *(`f1e73d9fc181eb1c`)* verified by a clean install test this session.
**FAILS IF:** ⚠️ **Claude Code applies these patches to a v3.2 it has edited since.** *Every patch is keyed to a section number and quotes the text it replaces* — ⛔ **if the quoted text is not there, STOP; do not guess which paragraph was meant.**
**LEAST CONFIDENT:** `§7.1`'s stage list. ⚠️ *v3.2 lists a `fact` stage that does not exist and retires `boundary`, which does.* ⭐⭐ **I cannot tell whether those were aspirational (planned but unbuilt) or simply wrong** — *and if `fact` was a genuine intention, deleting it from the table loses a requirement rather than correcting an error.* ⛔ **Ask the owner before removing it.**
