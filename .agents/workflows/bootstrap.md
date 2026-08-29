# WAVE 0 — BOOTSTRAP

**Not a module. A tree to build the modules in.** Runs once.

---

## 1. A Laravel tree

```bash
cd /home/arf/dev/grs-antig
composer create-project laravel/laravel app
```

Everything below runs from `app/`. **`artisan` must exist there** or the runtime
installer refuses, correctly.

## 2. Postgres, as a NON-OWNER role

⛔ **The app must not connect as the table owner.** RLS the owner bypasses is
decoration, and it fails silently — locally it passes, in production it does not.

Two roles: an owner for migrations, a non-owner for the app. Point
`DB_CONNECTION` at the non-owner and give migrations their own connection.

## 3. Install the runtime

```bash
bash ../runtime/goaiez-runtime.sh .
```

It prints a **SEAL DIGEST**. Record it:

```bash
python3 ../bin/state.py seal <the digest it printed>
```

⛔ **It must match `seal_digest` in `build-plan.json`.** If it does not, stop —
that is the `SEAL` condition.

The installer also reports which build it replaced. If it says *"the tree ALREADY
has build …"* on a first install, you are installing into the wrong directory.

## 4. The package files

The runtime reads these from the Laravel root:

```bash
cp ../source/GOAIEZ-MASTER-PLAN.md ../source/GOAIEZ-INDEX.json .
cp ../source/GOAIEZ-TRACKER-CAPABILITIES.md .          # see below
python3 ../bin/preflight.py                            # refuses if one is missing
```

⛔ **Without the master plan the scaffold reports "0 modules" instead of
failing.** A generator that finds nothing and says so quietly is the shape to
watch for.

### ⛔⛔⛔ `GOAIEZ-TRACKER-CAPABILITIES.md` IS LOAD-BEARING AND WAS NOT IN THE DROP

**It holds all 966 capability rows.** The chain, traced through the runtime:

1. `capabilities:scaffold` reads it and writes `app/Modules/<id>/capabilities.php`.
   Without it the command **fails loudly** — `Tracker not found` — and writes
   nothing. *(Good: it does not emit an empty file, deliberately.)*
2. `CapabilityStage::specIds()` then reads those `capabilities.php` files. With
   none present it returns `[]` for every module.
3. **The floor fires**: *"ZERO specced capabilities"* on **all 124 modules**.
4. `capability` fails the **MERGE**, so **no wave ever closes.**

⛔ **It is not reconstructible from the master plan.** Only 299 of the ids live
there, and the stage's own history records that reading the plan alone made
things *worse* — 269 required refusals became 671, because section summaries
merely *mention* a G-id without defining one.

⛔ **If it is absent: STOP at wave 0 and ask for it.** Do not start building —
you would run the entire roster and close nothing.

## 5. The database

```bash
php artisan db:bootstrap --dry-run
php artisan db:bootstrap
psql -f ../runtime/goaiez-grants.sql
```

⛔ **`db:bootstrap` forces RLS and does not GRANT.** Run the grants file. Without
it the database is secure and unusable, and the error you get is
`permission denied` — **a GRANT error, not an RLS error**, which is where an hour
goes if you read it the other way.

## 6. The runtime layer

`Procfile` · `deploy/supervisor.conf` · the three wired lines from
`runtime/README`: the worker heartbeat (**it must fire on an EMPTY pass**, or an
idle queue and a dead worker look identical), the scheduler heartbeat and its
tick claim, and `numbers:return-parked` daily.

⛔ **Never run `schedule:work` and cron together.**

## 7. The one command that settles it

```bash
php artisan app:deploy-check
```

**Nine checks. Any FAIL means the box is not deployed, whatever else is true.**

```bash
python3 ../bin/state.py note bootstrap-done
```

---

## ⛔ THE TWO THAT WILL COST YOU AN HOUR

| ⛔⛔ | **`php artisan queue:work` bare NEVER RETURNS.** Use `--stop-when-empty`. Everything chained after it with `&&` silently never ran |
| :--- | :--- |
| ⛔⛔ | **A journey on the `sync` driver proves nothing** and the harness refuses it — it would pass with no worker running |
