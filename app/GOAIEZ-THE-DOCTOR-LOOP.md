# GOAIEZ — **THE `doctor` LOOP: HOW TO DRIVE THE NUMBER TO ZERO**
**2026-08-27 · the operating procedure · this is the loop that terminates**

---

# ⭐⭐⭐ THE LOOP IN ONE LINE
> **`php artisan doctor` → a number → fix the TOP severity → re-run → the number falls → repeat until `All stages clean.`**

⛔ **You do not need me for most of it.** *Every violation carries its own fix. Bring me the ones where the fix is wrong or where fixing it would break a law.*

---

# ① ⭐ WHAT A VIOLATION LOOKS LIKE
**Every stage returns the same three fields, by design:**
| `where` | *the module and the exact declaration* — `X-110 @owns_table` |
| :--- | :--- |
| `what` | *what is wrong* — "claims the canonical noun `messages`" |
| ⭐⭐ `fix` | ***what to do about it*** — "move it to `@reads_table`" |

⭐⭐⭐ **The `fix` field is not advice — it is the instruction.** *It was written when the check was written, by someone who knew the failure mode. **Do what it says before you reason about it.***

---

# ② ⛔⛔ FIX IN SEVERITY ORDER. NEVER MIX.
**`doctor` already orders them, and the order is the cheapest-first rule from `§231`:**

| **stages 0–3** | `boundary` · `contract` · `citation` | ⛔⛔ **fail the COMMIT** | *fix FIRST — with agents committing in parallel, a broken contract poisons every branch* |
| :--- | :--- | :--- | :--- |
| **stages 4–5** | `schema` · `capability` | ⛔ **fail the MERGE** | *fix second* |
| **stage 6** | `journey` | **fails the WAVE** | ⭐ *fix LAST — these are the only checks that cross a module seam, and they need the others green to mean anything* |

> ⛔ **Do not chase a journey failure while a contract violation is open.** *The journey is failing BECAUSE of it, and you will fix the same thing twice.*

---

# ③ ⭐⭐ THE LOOP, STEP BY STEP

| **1** | `php artisan doctor` | *record the total. **This number is your progress bar*** |
| :--- | :--- | :--- |
| **2** | `php artisan doctor --stage=boundary` | *work ONE stage at a time — a mixed list is unreadable* |
| **3** | fix every violation in that stage, **using its own `fix` text** | |
| **4** | ⭐⭐ **re-run that ONE stage** | *seconds, not minutes. **Confirm the count fell*** |
| **5** | ⛔ **if the count did NOT fall, STOP** | *your fix did not land. **This happened to me repeatedly this session** — an edit that reports success and changes nothing* |
| **6** | when the stage is clean, move to the next | |
| **7** | ⭐ **full `doctor` again** after each stage | *fixing one stage can expose the next — that is normal, not regression* |

---

# ④ ⛔⛔⛔ THE THREE WAYS TO CHEAT — AND WHY EACH IS WORSE THAN THE VIOLATION

**The fastest route to zero is to weaken the check. Every one of these produces a green run and a broken platform.**

| ⛔ **① Delete the assertion** | *a capability id with no test → remove the id.* **The build goes green and the requirement is gone.** ⭐ `P-210` exists for this: **an agent may not delete an inconvenient assertion — the id going missing IS the failure** |
| :--- | :--- |
| ⛔⛔ **② Stub the harness** | *the twelve journeys throw until implemented. **Stubbing them makes all twelve PASS while touching no carrier, no gateway, no queue.*** ⭐ The installer says it out loud: **"Do NOT stub them to get green"** |
| ⛔⛔⛔ **③ Widen an exemption** | *`ContractStage` skips `@ingress` and `@scheduled` events. **Marking a broken event `@ingress` silences the check and keeps the bug.*** ⭐ `§235`: those lists are **NAMED, never patterned**, precisely so widening one is visible in a diff |

> ⭐⭐⭐ **The test for any fix: DID THE SYSTEM CHANGE, OR DID THE CHECK CHANGE?** *If you edited a check to make a violation disappear, you have not fixed anything — you have hidden it, and the next person will trust the green.*

---

# ⑤ ⭐ WHAT TO EXPECT ON THE FIRST RUN
| **`citation`** | ⭐ *likely CLEAN — I ran its logic by hand: 92 ids in module headers, 0 unresolvable* |
| :--- | :--- |
| **`contract`** | ⚠️ *should be near-clean; the ladder, gate and `@consumes` work is done* |
| ⛔ **`boundary`** | **expect the most.** *It reads REAL CODE — cross-module imports, `env()` calls, model literals — **and no real code has ever been checked*** |
| ⛔⛔ **`schema`** | **expect failures until `db:bootstrap` has run.** *RLS `FORCE` is asserted on tables that may not exist yet* |
| **`anchor` · `journey`** | ⛔ **expect ALL of them to fail** — *the harness is unimplemented and there is no evidence directory.* ⭐ **That is correct**|

⭐⭐ **A first run reporting ZERO means the checks are not connected to anything.** *Treat a suspiciously clean run as a defect and check `php artisan list` shows the commands.*

---

# ⑥ ⭐⭐ WHEN TO BRING IT TO ME
**Not for the routine ones — the `fix` field handles those. Bring me:**
| ⛔ **a fix that would break a law** | *"move `messages` to `@reads_table`" but the module genuinely owns it → **a `P-163` question, not a code question*** |
| :--- | :--- |
| ⛔⛔ **a violation whose `fix` is wrong** | ***I wrote those fix strings against documents, not running code.*** *Some will be wrong and I would rather correct them than have you work around them* |
| ⭐ **a count that will not fall** | *you applied the fix and the number did not move — **that is the "claimed but never applied" shape and it recurred five times this session*** |
| ⭐⭐ **anything in `journey`** | *the seam-crossing failures are the ones worth thinking about together* |

---

# ⑦ ⭐⭐⭐ DONE IS FOUR NUMBERS, AND THEY ARE ALL ZERO OR ALL COUNTED
| `php artisan doctor` | **`All stages clean.`** |
| :--- | :--- |
| `doctor:module-done <id>` × 122 | **122 report DONE** *(7 gates each)* |
| `php artisan test --group=journeys` | **12 green, each with an EXTERNAL artifact id** |
| `php artisan brief <id>` × 122 | **0 refusals** |

⛔ **Until all four are true, the platform is not built — however good the documents look.** ⭐ **When all four are true, it is, and no further prose audit will improve it.**

---

**CHECKED:** `doctor`'s severity map read from the code — `boundary`/`contract`/`citation` COMMIT, `schema`/`capability` MERGE, `anchor`/`journey` WAVE · the violation shape confirmed as `{where, what, fix}` from the `Stage` interface · the three cheat modes each traced to the law that forbids them *(`P-210`, the installer's stub warning, `§235`'s named lists)*.
**FAILS IF:** the `fix` strings are wrong at scale. ⚠️⚠️ ***I wrote them against documents and none has been tested against a failing case.*** ⛔ **If more than a few are unhelpful, tell me early** — *the fix text is the whole reason this loop does not need me, and if it is bad the loop is much slower.*
**LEAST CONFIDENT:** the first-run expectations in `§⑤`. ⚠️ *`boundary` reading real code for the first time could return **anything** — twelve violations or two hundred.* ⭐ **That number is also the single most useful measurement in the whole programme, because it is the first true signal about the live tree.**
