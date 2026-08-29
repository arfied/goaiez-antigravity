# ONE WAVE

`bin/state.py next` named the wave, its modules, and its wiring. This is what you
do with them.

---

## 1. Read the briefs — and nothing else

```bash
php artisan brief <X-nnn>        # for each module in the wave
```

⛔ **`brief` is the specification.** It is assembled from the annotations and
from nothing else, and it carries the TEST ANCHOR — *how this is proven*.

Do not read the master plan. `php artisan context <X-nnn>` gives that module's
full context and never another's; `php artisan why <id>` gives a law's reason;
`php artisan impact <subject>` gives blast radius, exhaustively or it refuses —
because a partial answer reads as *"nothing else is affected"*.

## 2. Scaffold

```bash
php artisan module:scaffold --module=<X-nnn>
php artisan capabilities:scaffold
```

⛔ **One module, ONE directory, hyphen included** — `app/Modules/X-126/`, never
`app/Modules/X126/`. PHP namespaces cannot contain a hyphen, so these autoload by
**classmap**, not PSR-4. Check `composer.json` before you wonder why nothing
resolves.

⛔ **`manifest.php` and `capabilities.php` are GENERATED.** Editing them is work
the next scaffold erases. Fix the plan or the tracker and regenerate.

If scaffold refuses — *prose inside a declaration* — move the note after the
closing backtick in the master plan and re-run. It refuses to invent, which is
the behaviour you want.

## 3. Build

Everything in the brief, and:

⛔ **P-163 — one table, one owner.** A module never writes another module's
table, **and a test that does is the breach wearing a test's clothes.**

⛔ **Subscribe to `not_yet_built_publishers` anyway.** They will not fire yet.
That is correct and expected — the wave record lists them so you do not go
looking for a bug.

⛔ **Do not break `subscribed_to_by`.** Those modules are already listening to
what this wave emits.

## 4. The gate — every wave ends here

```bash
php artisan doctor:selftest              # is the CHECKER sound?
php artisan doctor                       # 8 stages
php artisan doctor:module-done <X-nnn>   # 7 gates, per module
```

Record each result:

```bash
python3 bin/state.py stage <stage> <n>
python3 bin/state.py done <X-nnn>        # all 7 gates green
python3 bin/state.py unresolved <X-nnn> <stage> "<why>"
python3 bin/state.py journey <Jn> green
```

### The severity ladder decides what a red stage costs you

| `integrity` | COMMIT | **stop** — the checker changed |
| :--- | :--- | :--- |
| `boundary` `contract` `citation` | COMMIT | you may not commit. Cheap and mechanical — fix them |
| `schema` `capability` | MERGE | commit yes, close the wave no |
| `anchor` `journey` | WAVE | the wave does not close |

⭐ A `schema` violation does not stop you working. It stops you *closing*.

## 5. Gate 2 will refuse a generated id

⛔ **`Str::ulid()` is not an external artifact id.** A generated id is not an
issued one — **a string is not an artifact.** The runtime proof must carry an id
something outside this process issued: a carrier message id, a gateway charge
id, a provider request id.

⛔ **Never write the evidence file yourself.**

## 6. Closing

A wave closes when every module returns `is DONE` or carries a written
`UNRESOLVED`, and `anchor` and `journey` are clean or `UNRESOLVED`.

`doctor:module-done` appends a hash-chained line to `INSTRUCTIONS.jsonl`. **If
the chain is broken it refuses to record DONE at all** — because a previous phase
of this programme claimed 41 changes were applied and measurement found zero.

Then: `python3 bin/state.py next`.
