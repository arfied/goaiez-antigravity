# PASTE TO ANTIGRAVITY — **THE AUTONOMOUS LOOP**

**Do not report back after each step. Work the loop until you are genuinely stuck, then report ONCE.**

---

# ⭐⭐⭐ WHY THIS INSTRUCTION EXISTS

**Thirty exchanges have gone: run one command → paste the output → wait → get one fix → run one command.** ⛔ *Each round costs the owner a full cycle through WSL, an agent, and a copy-paste — sometimes transcribed off a phone.*

⭐⭐ **You have a terminal, the tree, and PHP. Most of what has been round-tripping is work you can do yourself.**

---

# ① FIRST — IS THE RUNTIME ITSELF SOUND?

```bash
php artisan doctor:selftest
```

**This audits the CHECKER, not the platform. They are different questions and mixing them cost this project weeks.**

| ⭐ **"The runtime is sound"** | *every violation `doctor` reports is about **YOUR CODE**. Go to ②* |
| :--- | :--- |
| ⛔ **"N problem(s) IN THE RUNTIME ITSELF"** | ***STOP. Paste that output and nothing else.*** *Do not patch `app/Doctor` — it is sealed, and a checker you repaired yourself is a checker nobody reviewed* |

---

# ② THEN — LOOP, WITHOUT ASKING

```bash
php artisan doctor --stage=<stage>
```

**For each violation, decide in this order:**

| ⭐ **can you fix the SYSTEM?** | *do it. Re-run only that stage. **The count must FALL*** |
| :--- | :--- |
| ⛔ **would you be changing a CHECK?** | *`UNRESOLVED` — never edit `app/Doctor`* |
| ⛔ **does it need an owner decision?** | *`UNRESOLVED` — say which decision* |
| ⛔ **does it need something that does not exist yet?** | *`UNRESOLVED — <what is missing>`* |

## ⛔⛔⛔ THE STOP RULE — THE ONLY ONE THAT MATTERS
**After a fix, re-run that stage. If the count DID NOT FALL, STOP that stage and move to the next.**

⭐⭐⭐ *Do not fix it again. Do not try something else. **A change that reports success and alters nothing has happened repeatedly on this codebase** — six times that I know of — and each time the next hour was spent fixing a thing that was already fixed.*

## ⭐ THE BUDGET
**Up to 20 fixes, or until every stage is clean or `UNRESOLVED`.** *Then report once.*

---

# ③ WHAT TO SEND BACK — ONE MESSAGE

```
SELFTEST : sound | N problems
STAGES   : integrity <n> · boundary <n> · contract <n> · citation <n>
           schema <n> · capability <n> · anchor <n> · journey <n>
FIXED    : <one line each — what you changed and which count fell>
UNRESOLVED:
  <stage>  <where>  — <why>
STUCK ON : <the one thing you most need decided>
```

⛔⛔ **Paste raw `doctor` output for anything you did not fix.** *Every wrong turn in this project came from acting on a paraphrase — "mostly prose in backticks" cost three rounds, and the raw text would have cost none.*

---

# ⛔⛔⛔ NEVER EDIT `app/Doctor/seals.json`

**If `doctor:selftest` reports a seal mismatch, that is a FINDING. Report it.**

⛔⛔ *Recomputing the hashes and writing them into `seals.json` makes the check pass and **destroys the only thing it was measuring.** It happened once, for an understandable reason — Claude shipped two loose `.php` files and the seals no longer matched — and the seal certified itself.*

| ⭐ **the mismatch means one of two things** | *a file was edited · **or a file arrived outside the bundle*** |
| :--- | :--- |
| ⭐⭐ **you cannot tell which, and neither can the seal** | ***that is exactly why you must not decide it yourself*** |

⭐⭐⭐ **`doctor:selftest` prints a SEAL DIGEST. `goaiez-runtime.sh` prints the same digest at install.** *If they differ, `seals.json` changed after install — and the owner is the one holding that number.*

⭐ **The fix for a mismatch is always: re-run `goaiez-runtime.sh`.** *It restores every sealed file and the seals together.*

---

# ⛔ THE FIVE THINGS THAT WILL WASTE YOUR TIME

| ⛔⛔ **`php artisan queue:work` bare** | ***IT NEVER RETURNS.*** *Use `--stop-when-empty`. Everything chained after it with `&&` silently never ran* |
| :--- | :--- |
| ⛔ **editing `app/Modules/*/capabilities.php` or `manifest.php`** | **GENERATED.** *Erased by the next scaffold. Edit `GOAIEZ-TRACKER-CAPABILITIES.md` or `GOAIEZ-MASTER-PLAN.md`, then regenerate* |
| ⛔ **`app/Modules/X126/`** | *`R242`: one module, ONE directory, hyphen included. Classes autoload by **classmap***, not PSR-4 |
| ⛔ **touching `app/Enums`, `app/Services`, `app/Livewire`** | ***the LEGACY tree, most of it being replaced.*** **Triage with the owner first** |
| ⛔ **fabricating an evidence file** | *`render.json`, `runtime-proof.json`, `lint.json` — **a proof you wrote to pass a gate is the one thing this whole system exists to prevent*** |

---

# ⭐⭐ AND WHEN YOU ARE GENUINELY STUCK

**Say so early. `UNRESOLVED` with a reason is worth more than an hour of trying.**

> ⭐⭐⭐ **You are NOT scored on the count going down. You are scored on whether the count that remains is TRUE.**

---

**CHECKED:** `doctor:selftest` written and linted in a container with PHP 8.3.6 · verified free of base-class method collisions *(the defect that once killed every artisan command on this tree)* · every `$this->` call resolves to a method that exists.
**FAILS IF:** ⚠️ **`doctor:selftest` is not in your build.** *It ships in `20260828-2110` and later — check `php artisan list | grep selftest` before assuming it is missing.*
**LEAST CONFIDENT:** the 20-fix budget. ⚠️ *It is a guess. **If you hit it with stages still falling, keep going and say so** — a budget that stops useful work is worse than no budget.*
