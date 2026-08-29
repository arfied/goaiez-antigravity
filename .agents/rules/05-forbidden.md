# 05 — FORBIDDEN

## ⛔⛔⛔ SEALED — `app/Doctor/**` AND THREE COMMANDS

`ModuleDoneCommand` · `DoctorCommand` · `DoctorSelfTestCommand`

**The gatekeeper is sealed, not just the checker.** 15 sealed files.

⛔ **`app/Doctor/seals.json` — NEVER EDIT.**

If `doctor:selftest` reports a seal mismatch, **that is a FINDING. Report it.**
Recomputing the hashes and writing them into `seals.json` makes the check pass
and **destroys the only thing it was measuring.** It has happened once, for an
understandable reason — two loose files shipped and the seals no longer matched —
and the seal then certified itself.

> **A mismatch means one of two things: a file was edited, or a file arrived
> outside the bundle. You cannot tell which, and neither can the seal — which is
> exactly why you must not decide it yourself.**

**The fix is always: re-run `runtime/goaiez-runtime.sh`.** It restores every
sealed file and the seals together.

## ⛔ GENERATED — REGENERATE, DO NOT EDIT

`app/Modules/*/manifest.php` · `app/Modules/*/capabilities.php`

## ⛔ OFF THIS TREE

`/home/arf/dev/goaiez-review-system` and `/home/arf/dev/goaiez-review-system-antigravity`
are **different builds under a different plan.** Do not read them for
requirements, do not copy from them, do not write to them. This build is fresh
and self-contained.

## ⛔ NEVER CITE AN ID YOU CANNOT RESOLVE

```bash
php artisan why <id>
```

Nothing back? **State the fact instead of the id.**

## ⛔ THE FIVE THAT WILL WASTE YOUR TIME

| ⛔⛔ | **`php artisan queue:work` bare NEVER RETURNS.** `--stop-when-empty`. Everything chained after it with `&&` silently never ran |
| :--- | :--- |
| ⛔ | **editing generated manifests** — erased by the next scaffold |
| ⛔ | **`app/Modules/X126/`** — R242, the hyphen is part of the directory name |
| ⛔ | **a bare `schedule:work` alongside cron** — never both |
| ⛔ | **fabricating an evidence file** — see rule 04 |

## ⛔ AND THE ONE THAT LOOKS LIKE A FIX

**`ALTER ROLE … BYPASSRLS`** switched tenant isolation off platform-wide, and the
schema stage still reported 0 **because it checked TABLES and not ROLES.**

If a query fails with `permission denied`, that is a **GRANT** error, not an RLS
error. Run `runtime/goaiez-grants.sql`. Never reach for `BYPASSRLS`.
