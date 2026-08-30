# PASTE THIS TO ANTIGRAVITY / ANY AGENT WORKING `doctor`

---

You are fixing violations reported by `php artisan doctor`. Read this before you touch anything.

## THE ONE RULE

**Every fix must change the SYSTEM. No fix may change a CHECK.**

Before you edit any file, ask: *am I making the code correct, or am I making the checker quieter?* If it is the second, stop and report the violation as **unresolved** instead. An unresolved violation is a useful fact. A silenced one is a lie that survives into production.

## FORBIDDEN — these are not fixes and will be detected

- **Disabling, commenting out, or weakening any check** in `app/Doctor/**`. That directory is sealed and hashed; `doctor --stage=integrity` reports every modification by filename.
- **Adding an exemption** so a violating file stops being scanned.
- **Deleting a capability id, an assertion, or a refusal** to clear a capability violation. `P-210`: the id going missing IS the failure.
- **Stubbing `tests/Journeys/JourneyHarness.php`.** Those methods throw on purpose. Stubbing them makes all twelve journeys pass while touching no carrier, no gateway, no queue.
- **`match (true) { … default => … }` is legal.** It is a chained conditional, not an enum. Do not "fix" it and do not report it.

## WHEN YOU CANNOT FIX SOMETHING

Say so. Use exactly this shape, one line each:

```
UNRESOLVED  <stage>  <where>  — <why you did not fix it>
```

Three unresolved violations honestly reported are worth more than three hundred cleared by deletion. **You are not scored on the count going down.** You are scored on whether the count that remains is true.

## THE STOP RULE

After each fix, re-run only that stage:

```
php artisan doctor --stage=<stage>
```

**If the count did not fall, STOP.** Your fix did not land. Do not fix it again, do not fix something else — find out why the first change had no effect. An edit that reports success and changes nothing has happened repeatedly on this codebase.

## VERIFY WHICH CODE YOU ARE RUNNING

`doctor`'s first line is `goaiez doctor · build <stamp>`. If you did not just install that build, you are looking at old results. Three consecutive runs once produced byte-identical output because the files were re-downloaded into a folder and never copied into the tree.

## WHAT IS ACTUALLY WORTH FIXING RIGHT NOW

| `boundary` | ⭐ **`match()` default arms** — enumerate the cases. Mechanical and real. |
| :--- | :--- |
| `boundary` | **hardcoded model strings** — route through `C-Ai`, never name a vendor. |
| `contract` | **prose inside a declaration** — move the note after the closing backtick in the master plan, then re-run `module:scaffold`. |
| `capability` | ⛔ **DO NOT TOUCH.** 966 rows need assertions and refusals written. That is an owner decision, not an agent task. |
| `journey` | ⛔ **DO NOT TOUCH.** 12 of 12 failing is the designed state until the harness is implemented against real transports. |

## THE TEST, ONE MORE TIME

> **DID THE SYSTEM CHANGE, OR DID THE CHECK CHANGE?**

If you cannot answer "the system", you have not fixed anything.
