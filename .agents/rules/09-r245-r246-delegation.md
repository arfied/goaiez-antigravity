# 09 — R245 AND R246: THE STANDING DELEGATION

**Added 2026-08-29 from `GOAIEZ-PATCH-THE-46-ANSWERS.md`. This rule OVERRIDES
what rule 02 and the `goaiez-unresolved` skill said about owner decisions.**

> **Owner: *"All questions answered… Anything not covered, let Claude Code
> decide — never get stuck on a module. From now on the AI does the work and
> makes the system god-tier perfect, not gated, the best they can be."***

---

## ⭐⭐⭐ R245 — WHERE THE PLAN DOES NOT DECIDE, **YOU** DECIDE, AND BUILD

**A module is never blocked waiting for a ruling that does not exist.** The
decision is recorded where it is made, and the owner may overrule it later.

| `N-245-01` | ⭐⭐⭐ ***"I do not know" is not a stopping condition.*** |
| :--- | :--- |
| `N-245-02` | ⛔ **Write the choice down WHERE YOU MAKE IT** — in the module header or its capability row, marked `(R245)`. *An undocumented choice is indistinguishable from an accident* |
| `N-245-03` | ⭐⭐ **Build the best version, not the safe version.** *The standard is what a billion-dollar platform ships* |
| `N-245-04` | ⛔⛔ **R245 does NOT authorise spending a ruling number, minting a module, or editing `app/Doctor`.** *The delegation is over DESIGN, never over the LAW* |

## ⛔⛔⛔ THIS CORRECTS `UNRESOLVED`, AND THE CORRECTION IS THE POINT

**`UNRESOLVED` is ONLY for a MISSING DEPENDENCY:**

| ✅ | a table another module owns and has not built yet |
| :--- | :--- |
| ✅ | a credential that does not exist |
| ✅ | a transport nobody has built |

⛔ **NEVER for an unmade decision.** An earlier version of these rules told you to
record `UNRESOLVED` when something *"needs an owner decision."* **That is now
wrong.** Decide it, mark it `(R245)`, and build.

## ⚠️ R245 AND Q-045 LOOK LIKE THEY CONTRADICT. THEY DO NOT.

They are **the same line drawn twice.** `N-245-04` carves out the LAW; `Q-045`
names what is inside that carve-out.

| ⛔ **STOP — always the owner's** | **a new threshold** *(a number that changes behaviour)* · **a retention period** · **pricing** · **legal text** · **anything that REVERSES a ruling** |
| :--- | :--- |
| ⭐ **DECIDE AND BUILD — everything else** | schema shape · service boundaries · naming · error handling · which pattern · what to test · the twenty-four delegated rulings |

> ⭐⭐⭐ **The test: does the choice change what the platform CHARGES, KEEPS,
> PROMISES IN LAW, or has already RULED?** ⛔ Then stop. ⭐ Otherwise decide it,
> write `(R245)`, and build.

⛔ **AND "STOP" DOES NOT MEAN STOP THE MODULE.** Build everything around it, leave
that one value as a named `TODO(Q-045)` **with your recommendation**, and keep
going. **A blocked VALUE is not a blocked MODULE.**

⚠️ **These five are a FILTER for classifying decisions as they arise — not a
backlog.** Searched 2026-08-29: zero open instances of retention, pricing or
legal text, and all five threshold matches are already decided. **There is
nothing waiting to be answered.**

---

## ⛔⛔⛔ R246 — "NOT GATED" HAS A LINE, AND IT IS ABSOLUTE

**"Not gated" means no HUMAN-APPROVAL step in front of the AI doing its work. It
NEVER means removing a refusal that protects a customer, a tenant or the law.**

| ⛔⛔⛔ **STAYS — these are not gates** | **`X-126`'s `NO_FACT`** *(never invent a price, a time or a link)* · **consent · quiet hours · DNC** *(TCPA — legal)* · **RLS tenant isolation** · **the irreversible-step confirmation on money** · **`X-220`'s golden set** before a model enters PRIMARY |
| :--- | :--- |
| ⭐ **GOES — these are real gates** | waiting for owner approval to ACT · `PROPOSE_ONLY` / `AWAITING_APPROVAL` · shipping a feature OFF *"until reviewed"* · an autopilot that asks permission to do its job |

> ⭐⭐⭐ **A refusal is the system being CORRECT. A gate is the system being TIMID.**
> **Nothing earns the right to act; nothing loses the right to refuse.**

⚠️ **This is the one sentence in the whole package that is easy to misread in
exactly one direction — and the misreading looks like enthusiasm.** Strip
`NO_FACT`, consent or RLS and the platform stops being sellable: **a system that
cannot refuse cannot be trusted with a tenant's customers.**

---

## THE TEN, DECIDED — THESE ARE ANSWERS, NOT QUESTIONS

| the `fact` doctor stage | **KEEP as a gap**, do not delete |
| :--- | :--- |
| ⛔ **`jobs`** | **the canonical noun is `work_orders`. Laravel keeps `jobs`** |
| duplicated nouns | **`businesses` and `facts` are canonical.** `companies`, `customers`, `business_facts` are LEGACY → REPLACE |
| the 322 refusals | **written per module, during that module's build, by you.** Not a separate project |
| the legacy tree | **REPLACE executes per module**, in the same commit as the module that replaces it. ⛔ Never a bulk deletion |
| the COMPLEX slot | **capped under `C-Ai`'s tenant and platform ceilings.** *A slot that can escape the budget is a leak* |
| who decides "complex" | **the JOB CLASS, declared as a ROW** — never a per-call heuristic |
| the redaction boundary | **self-host the highest-sensitivity job classes** |
| `X-218` | **BUILD IT** — a gap, not a duplicate |
| `X-126` `X-119` `X-121a` | *the package says they survive.* ⚠️ **In THIS tree they do not exist** — the owner called a fresh build and the older trees are off limits. **They are wave 1 here** |

## SEVEN FACTS THAT ARE NOW LAW — AND THEY LAND ON SPECIFIC MODULES

| `R9` ⭐⭐ | **every SMS over 159 characters is ONE CREDIT; 1 MMS = 1 SMS credit.** ⛔ **The billing unit is the SEGMENT, not the message** — `C-Sms` **and** `C-Billing` both meter on segments |
| :--- | :--- |
| `R28` · `R61` ⭐ | **staff stay logged in BY DEFAULT.** A timeout is **opt-in, per user**. ⛔ The 12h/30m console default is STRUCK |
| `R88` | send-graph data → the **verifier** → may go to a campaign. **FULL AUTOPILOT** |
| `R141` | **`robots.txt` IS observed** on normal scraping; SEO clients and client-owned sites are ours to read |
| `R118` | ⛔ **no price raise included.** `P-195` stands — price never promotes |
| `R27` | the messaging and voice seams are **upgradeable** — LiveKit may be added |
| `R58` | **demo only** — not a production surface |

## THE TWENTY-FOUR DELEGATED RULINGS — DECIDE, DO NOT DEFER

`R11` `R12` `R24` `R25` `R30` `R32` `R35` `R37` `R51` `R67` `R68` `R72` `R75`
`R80` `R86` `R90` `R92` `R106` `R125` `R130` `R171` `R174` `R189` `R209`

**For each: write a one-line ruling where it is used, marked `(R245)`, and
build.** `R51` was left blank — same treatment.

## AND THE NUMBERS MOVED

| rulings spent through | **`R246`** · next free **`R247`** |
| :--- | :--- |
| modules spent through | **`X-220`** · next free **`X-221`** |
| ⛔ **`X-216`** | **PERMANENTLY RETIRED — never assign.** *(It was "a deliberate gap"; it is now retired outright)* |
