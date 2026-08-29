---
name: goaiez-module
description: Build one module from its brief through all seven gates. Use whenever bin/state.py names a module to build.
---

# BUILD ONE MODULE

## 1. The brief is the specification

```bash
php artisan brief <X-nnn>
```

Assembled from the annotations and **from nothing else**. It carries the TEST
ANCHOR — *how this is proven*. **Do not read the master plan.**

Need more? `php artisan context <X-nnn>` (that module and never another's) ·
`php artisan why <id>` · `php artisan impact <subject>` · `php artisan find <term>`.

## 2. Scaffold

```bash
php artisan module:scaffold --module=<X-nnn>
php artisan capabilities:scaffold
```

⛔ `app/Modules/X-126/` — **hyphen included**, classmap not PSR-4 (R242).
⛔ `manifest.php` and `capabilities.php` are **generated**. Never edit them.

If scaffold refuses on *prose inside a declaration*: move the note after the
closing backtick in the master plan, re-run. **It refuses to invent** — that is
correct behaviour, not an obstacle.

## 2b. Does this module get a `Domain/` layer?

`bin/state.py next` answers it per module — `domain_layer` and `domain_because`.

| **true** | owns ≥4 tables, or touches money/consent/entitlement. Build `Domain/` with the invariants |
| :--- | :--- |
| **false** | ⭐ **the Eloquent model plus an Action class IS the aggregate.** No `Domain/` folder |

⚠️ **The verdict is a starting classification, not a ceiling.** If the brief names
an invariant spanning two of the module's own tables, or a state machine with
legal transitions, **add `Domain/` whatever the JSON says.**

⛔ **No repositories over Eloquent. No event sourcing.** Rule 08 §4.2 carries why —
event sourcing contradicts `P-163`, which the `schema` stage enforces.

## 3. Build against the seven gates

| 1 BUILT | the code exists where the manifest says |
| :--- | :--- |
| 2 TESTED | a runtime proof carrying an **external artifact id** |
| 3 CONTENT | strings as data, never hardcoded |
| 4 HELP | a help card per action *(passes while `help_cards` does not exist)* |
| 5 DASHBOARD | visible where it declares it is |
| 6 SURFACES | an **undeclared** surface is refused |
| 7 GATE | the capability gate decides it |

⛔ **P-163 — one table, one owner.** Never write another module's table, **and a
test that does is the breach wearing a test's clothes.**

⛔ **Gate 2 refuses `Str::ulid()`.** A generated id is not an issued one. Carrier
message id, gateway charge id, provider request id — something outside this
process issued it.

⛔ **Never write the evidence file yourself.**

## 4. Wiring

`bin/state.py next` gave you four lists. Use them:

- `subscribes_to` — wire these
- `not_yet_built_publishers` — **subscribe anyway.** It will not fire yet, and
  that is correct
- `subscribed_to_by` — already listening to you. Do not break them
- `calls` — a real dependency; it exists already

## 5. Close

```bash
php artisan doctor:module-done <X-nnn>
python3 bin/state.py done <X-nnn>          # all seven green
python3 bin/state.py unresolved <X-nnn> <stage> "<why>"
```

**`UNRESOLVED` is a complete, acceptable outcome.** Move to the next module.
