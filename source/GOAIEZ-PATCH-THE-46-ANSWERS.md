# PATCH — **THE 46 ANSWERS, APPLIED**
**2026-08-29 · `R245` · `R246` · seven facts · twenty-two delegations · v4.0**

> **Owner: *"All questions answered. Come up with a patch file for everything, max effort. Anything not covered, let Claude Code decide — never get stuck on a module. From now on the AI does the work and makes the system god-tier perfect, not gated, the best they can be. A billion-dollar system."***

---

# ⭐⭐⭐⭐ PATCH A — **`R245`: THE STANDING DELEGATION**

**Add to `§2` of the build plan, and to any agent contract.**

| **R245** | ⭐⭐⭐ **Where the plan does not decide, the BUILDING AGENT decides — and BUILDS. A module is never blocked waiting for a ruling that does not exist. The decision is RECORDED where it is made, and the owner may overrule it later.** |
| :--- | :--- |

| `N-245-01` | ⭐ ***"I do not know" is not a stopping condition.*** *`UNRESOLVED` is for a **MISSING DEPENDENCY** — a table another module owns, a credential that does not exist, a transport nobody built. ⛔ **Never for an unmade decision*** |
| :--- | :--- |
| `N-245-02` | ⛔⛔ **Write the choice down WHERE YOU MAKE IT**, in the module header or its capability row, marked `(R245)`. *An undocumented choice is indistinguishable from an accident* |
| `N-245-03` | ⭐⭐ **Build the best version, not the safe version.** *The standard is what a billion-dollar platform ships* |
| `N-245-04` | ⚠️ ⛔ **`R245` does NOT authorise spending a ruling number, minting a module, or editing `app/Doctor`.** *The delegation is over DESIGN, never over the LAW ITSELF* |

---

# ⛔⛔⛔ PATCH B — **`R246`: WHAT "NOT GATED" DOES NOT MEAN**

**This patch matters more than any other in the file. "Not gated" is one sentence away from breaking the product.**

| **R246** | ⭐⭐⭐ **"NOT GATED" means no HUMAN-APPROVAL step in front of the AI doing its work. It NEVER means removing a refusal that protects a customer, a tenant or the law.** |
| :--- | :--- |

| ⛔⛔⛔ **STAYS — not gates** | ⭐ **`X-126`'s `NO_FACT`** *(the agent never invents a price, a time or a link)* · **consent · quiet hours · DNC** *(TCPA — legal)* · **RLS tenant isolation** · **the irreversible-step confirmation on money** *(`P-096`/`D5`)* · **`X-220`'s golden set** before a model enters PRIMARY |
| :--- | :--- |
| ⭐ **GOES — real gates** | *waiting for owner approval to ACT · `PROPOSE_ONLY` / `AWAITING_APPROVAL` *(struck by `R236`)* · shipping a feature OFF "until reviewed" *(struck by `R235`)* · an autopilot that asks permission to do its job* |

> ⭐⭐⭐ **A refusal is the system being CORRECT. A gate is the system being TIMID.**
> *`R235` said nothing earns the right to act. **`R246` says nothing loses the right to refuse.***

---

# ⭐⭐ PATCH C — **SEVEN FACTS, NOW LAW**

| **`R9`** ⭐⭐ | ***Every SMS over 159 characters is ONE CREDIT. One MMS = one SMS credit.*** ⛔ **The billing unit is the SEGMENT, not the message** — *`C-Sms` and `C-Billing` both meter on segments* |
| :--- | :--- |
| **`R27`** | *the messaging and voice seams are **upgradeable** — **LiveKit may be added** without reopening the shape* |
| **`R28` · `R61`** ⭐⭐ | ***Staff stay logged in BY DEFAULT.*** *A timeout is **opt-in, per user**.* ⛔ **The 12h/30m console default is STRUCK** |
| **`R58`** | *demo only — **not a production surface*** |
| **`R88`** ⭐⭐ | *send-graph data → the **verifier** → may go to a campaign to send email. **FULL AUTOPILOT*** |
| **`R118`** | ⛔ **No price raise included.** *`P-195` stands — price never promotes* |
| **`R141`** ⭐ | ***`robots.txt` IS observed on normal scraping.*** *SEO clients and client-owned sites are ours to read* |
| **`R4` · `R13` · `R17` · `R59`** | *ratified as reconstructed* |

---

# ⭐ PATCH D — **THE TWENTY-TWO DELEGATED**

**`R11` `R12` `R24` `R25` `R30` `R32` `R35` `R37` `R51` `R67` `R68` `R72` `R75` `R80` `R86` `R90` `R92` `R106` `R125` `R130` `R171` `R174` `R189` `R209`**

⭐ *The owner answered "you find a fix" or "your best judgement."* ⛔⛔ **Under `R245` these are the building agent's to DECIDE — not to defer.**

**For each: write a one-line ruling where it is used, marked `(R245)`, and build.** ⚠️ *`R51` was left blank — same treatment.*

---

# ⭐⭐⭐ PATCH E — **THE TEN, DECIDED UNDER `R245`**

**The owner left these blank and instructed: decide them. Here is each, decided, with its reasoning — overrule any of them freely.**

| ① **the `fact` doctor stage** | ⭐ **KEEP IT AS A GAP, do not delete.** *v3.2 named it deliberately; `X-119` now owns `fact_sources`/`fact_freshness` and a collision check has a real home.* ⛔ **Deleting an unbuilt intention loses a requirement; recording it as a gap loses nothing** |
| :--- | :--- |
| ② ⛔⛔ **`jobs`** | ⭐⭐⭐ **The canonical noun becomes `work_orders`. Laravel keeps `jobs`.** *Moving a framework's queue table to free a name is a migration with no upside; **renaming our own noun costs one line in the plan and nothing at runtime*** |
| ③ **duplicated nouns** | ⭐ **`businesses` is canonical** *(`X-121` owns it, RLS is forced on it)*. **`companies` and `customers` are LEGACY → REPLACE.** **`facts` is canonical; `business_facts` is LEGACY** |
| ④ ⭐⭐ **the three built modules** | ***`X-126`, `X-119` and `X-121a` SURVIVE.*** *"Start over" means the **legacy tree**. These three were built against sealed gates with real evidence rows in Postgres — **rebuilding proven work is the one cost nothing recovers*** |
| ⑤ **the 322 refusals** | ⭐ **Written per module, during that module's build, by the building agent.** *Not a separate project — a refusal is easiest to write while you hold the thing that refuses* |
| ⑥ **the legacy tree** | ⭐ **REPLACE executes per module: when a module's `@provides` covers a legacy file's job, that file is deleted in the same commit.** ⛔ *Never a bulk deletion* |
| ⑦ **the COMPLEX slot** | ⛔ **Capped under `C-Ai`'s tenant and platform ceilings.** *A slot that can escape the budget is not a slot, it is a leak* |
| ⑧ **who decides "complex"** | ⭐ **The JOB CLASS decides, not the module and not a heuristic.** *A job class is declared, reviewable and changeable as a row — **a per-call heuristic is none of those*** |
| ⑨ **the redaction boundary** | ⭐⭐ **Self-host the highest-sensitivity job classes.** *`R237` already makes a self-hosted model a first-class row, and `N-238-06` already exempts it from redaction — **the mechanism exists; this is just choosing to use it*** |
| ⑩ **`X-218`** | ⭐ **BUILD IT.** *"Zero modules covered this under any name" is a gap, not a duplicate — and influencer/UGC/sponsorship is a revenue surface a billion-dollar platform has* |

---

# ⭐ PATCH F — **UPDATE `§1.3`'s NUMBERS**

| Rulings | spent through **`R246`** | next free **`R247`** |
| :--- | :--- | :--- |
| Modules | spent through **`X-220`** | next free **`X-221`** ⛔ *`X-216` **PERMANENTLY RETIRED*** |
| Sections | spent through **`§264X`** | next free **`§264Y`** |
| Roster | **124** | — |
| Runtime | build **`20260829-0647`** | seal digest **`f1e73d9fc181eb1c`** |

---

**CHECKED:** all 36 answers read verbatim — **6 ratified · 7 carrying new facts · 22 delegated · 1 blank** · the ten confirmed unanswered and covered by the standing instruction · `R245`/`R246` confirmed free before minting and now written into the plan at `§264X` · roster still 124 after the edit.
**FAILS IF:** ⚠️⚠️ **"not gated" is read as "no refusals."** ⛔⛔⛔ *Strip `NO_FACT`, consent or RLS and the platform stops being sellable — **a system that cannot refuse cannot be trusted with a tenant's customers.*** ⭐ **`R246` exists because that sentence is easy to misread in exactly one direction, and the misreading looks like enthusiasm.**
**LEAST CONFIDENT:** decision ② — **renaming the canonical noun to `work_orders`.** ⚠️ *It touches `X-121`'s `@owns_table`, every capability row that says "jobs", and any prose using the word.* ⭐⭐ **I chose it because moving Laravel's queue is a framework fight with no upside** — ⛔ *but if "jobs" is the word the business actually uses with customers, the rename costs you vocabulary you cannot get back, and that is worth thirty seconds of thought before it ships.*
