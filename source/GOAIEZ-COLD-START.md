# COLD START — **PASTE THIS TO A FRESH AGENT**

**No history required. Do not read the previous conversation. Everything you need is in the repository.**

---

# ⭐⭐⭐ WHY YOU DO NOT NEED CONTEXT

**The plan, the trackers and `doctor` ARE the memory.** *A prior agent's context window overflowed and it lost track of what it was doing — that cost nothing, because nothing important lived in it.*

⛔ **If you find yourself needing to remember something, you are doing it wrong.** *Ask the repository.*

| **where am I?** | `bash goaiez-status.sh .` |
| :--- | :--- |
| **what does this module need?** | `php artisan brief X-126` |
| **what is broken?** | `php artisan doctor` |
| **who else is affected?** | `php artisan impact X-126` |
| **why is this rule here?** | `php artisan why P-163` |

---

# ⭐ THE ONE RULE

> **Every fix must change the SYSTEM. No fix may change a CHECK.**

⛔⛔ **`app/Doctor/**` is SHA-256 sealed.** *`php artisan doctor --stage=integrity` reports any modification by filename.* ⭐ **If a checker has a bug, say so and stop — do not patch it.** *That has already happened once and stopping was the right call.*

**If you cannot fix something, report it in exactly this shape:**
```
UNRESOLVED  <stage>  <where>  — <why>
```
⭐⭐⭐ **You are NOT scored on the count going down. You are scored on whether the count that remains is TRUE.**

---

# ⭐⭐ THE CURRENT TASK

**Build `X-126 CapabilityGate`.** *Full instructions: `GOAIEZ-BUILD-X-126.md`.*

**Three gates are achievable. Three are not, and that is expected:**
| ⭐ **1 BUILT** | write `storage/app/evidence/X-126/lint.json` |
| :--- | :--- |
| ⭐ **2 TESTED** | ⛔ **a REAL runtime proof, not a unit test** — run the TEST ANCHOR against actual behaviour |
| ⭐ **7 GATE** | `php artisan doctor` clean for this module |
| ✅ 3 CONTENT | already passing |
| ⭐ 4 HELP | **PENDING** — the `help_cards` table does not exist yet and no module owns it |
| ⚠️ **5 DASHBOARD · 6 SURFACES** | ⛔ **report `UNRESOLVED — phase 5 infrastructure`.** *Render and surface evidence need a pipeline nobody has built. **Do not fabricate the evidence files*** |

---

# ⛔⛔⛔ THE FIVE THINGS THAT WILL WASTE YOUR TIME

| ⛔ **`php artisan queue:work` bare** | ***IT NEVER RETURNS.*** *Use `--stop-when-empty`. A previous session lost hours to this, and every command chained after it with `&&` silently never ran* |
| :--- | :--- |
| ⛔ **editing `app/Modules/*/capabilities.php`** | **GENERATED** from `GOAIEZ-TRACKER-CAPABILITIES.md`. *Your edit is erased by the next `capabilities:scaffold`* |
| ⛔ **editing `app/Modules/*/manifest.php`** | **GENERATED** from `GOAIEZ-MASTER-PLAN.md`. *Same* |
| ⛔ **`--tries=N` on the queue** | *forbidden by `deploy/supervisor.conf`: **"retry is a per-job decision; a global flag silently overrides every one of them"*** |
| ⛔⛔ **fixing violations in `app/Enums`, `app/Services`, `app/Livewire`** | ***That is the LEGACY tree and most of it is being replaced.*** **Triage KEEP/REPLACE with the owner before touching any of it** |

---

# ⭐ WHEN YOU FINISH

**Report exactly this and nothing else:**
```
X-126 : <N> rounds
doctor total  : <before> → <after>
module-done   : <n>/7
UNRESOLVED    : <list, or none>
```

⭐⭐ **`N` matters more than the module.** *Four modules × seven gates recalibrates the estimate for the remaining 120 — **and that figure is currently the least certain number in the entire programme.***

---

**CHECKED:** every command above exists in `app/Console/Commands` · `app/Doctor` confirmed sealed with 12 hashes in `seals.json` · gate 4's `help_cards` confirmed absent from the 49 real tables and owned by no module · the `queue:work` blocking behaviour reproduced from the owner's own session log.
**FAILS IF:** ⚠️ **`brief X-126` refuses.** ⭐ *That is not an obstacle — it is the tooling saying the spec is too thin to build from, and it is the finding. **Paste the refusal rather than working around it.***
**LEAST CONFIDENT:** whether gate 2's runtime proof can be produced at all for `X-126`. ⚠️ *Its TEST ANCHOR's second half needs `ConsentService`, and `X-204` is unbuilt.* ⛔ **If so, half the anchor is `UNRESOLVED` — and a proof covering half an anchor must SAY it covers half.**
