# 08 — FULLY MODULAR: DDD AND CQRS

**The owner's instruction: this version is fully modular, on DDD and CQRS.**

⭐⭐⭐ **Read this first: the runtime ALREADY enforces the strategic half of DDD,
and it does it with a build-failing lint.** You are not introducing an
architecture — you are naming one that is already load-bearing, and then
specifying the layer it leaves open.

---

## 1. THE BOUNDED CONTEXT IS THE MODULE, AND IT IS ENFORCED

```
⛔ imports <X-nnn> across a module boundary
   fix: emit an event, or invoke <X-nnn>'s registered action — never `use`
```

That is `BoundaryStage`, and it **fails the COMMIT**.

⛔ **A module may not `use` another module's class. Ever.** Not a model, not an
enum, not a value object, not a helper. There are exactly two ways across a
boundary:

| an **EVENT** | `@emits` → `@consumes`, through `X-123`. Asynchronous, many listeners, the publisher does not know who listens |
| :--- | :--- |
| a **REGISTERED ACTION** | `@provides`, through `X-122`. Synchronous, typed params, typed return, a permission, and a **reversal class** |

⭐ **This is what makes the modules real rather than decorative.** A folder
structure with shared imports is one application in fancy dress; this one cannot
become that, because the lint reddens before the merge.

⛔ **And `@owns_table` is aggregate ownership, enforced by P-163: one table, one
owner.** A module never writes another module's table — **and a test that does
is the breach wearing a test's clothes.**

## 2. WHAT THE RUNTIME LEAVES OPEN — AND THIS IS WHERE YOU APPLY DDD

`make:module` scaffolds only `Actions/`, `Tests/` and `seeds.yml`. Gate 1 asks
that `app/Modules/<id>/` exists and carries real lint evidence, **and nothing
constrains the layout inside.** So:

```
app/Modules/X-nnn/
├── Actions/          ⭐ scaffolded. THE COMMAND SIDE — one class per registered action
├── Queries/             THE READ SIDE — no writes, ever
├── Domain/              entities · value objects · domain services · the invariants
├── Events/              the domain events this module @emits
├── Listeners/           handlers for what it @consumes, and its projections
├── Ui/                  Livewire components + Blade views
├── Database/            migrations for the tables it @owns_table
├── Tests/            ⭐ scaffolded
├── ModuleServiceProvider.php   ⭐ see §5 — without it nothing is discovered
├── manifest.php      ⛔ GENERATED
├── capabilities.php  ⛔ GENERATED
└── seeds.yml         ⭐ P-193: operational values are ROWS, never code
```

## 3. CQRS — AND IT MAPS ONTO THE CONTRACT ALREADY WRITTEN

⭐ **You do not have to invent the split. The module contract already is one:**

| `@provides` | the **QUERY** side — `entity.read`, `entity.history` |
| :--- | :--- |
| `X-122` actions | the **COMMAND** side — *id · label · typed params · returns · permission · **reversal class*** |
| `@emits` | the **DOMAIN EVENTS** |
| `@consumes` | the **handlers and projections** |
| `@owns_table` | the aggregate's tables, and its read models |

⛔ **An action carries a REVERSAL CLASS.** That is a compensating command, and it
is the reason commands are objects here rather than service methods. Design the
undo when you design the do — retrofitting reversal means revisiting every action.

## 4. ⛔⛔⛔ HOW FAR TO TAKE CQRS — READ THIS BEFORE YOU BUILD THE FIRST ONE

**CQRS is three separable things and only the first two are wanted by default.**

| ✅ **command/query separation** | **every module.** A `Queries/` class never writes; an `Actions/` class never returns a read model. Free, and it is what makes a module testable |
| :--- | :--- |
| ✅ **domain events as the seam** | **every module.** Already mandatory — the boundary lint permits nothing else |
| ⛔ **separate read/write stores, projections, event sourcing** | **NOT by default. Do not build it into 124 modules.** |

⛔ **Do not event-source this platform.** It is a large, permanent cost, it makes
every RLS and erasure question harder, and **nothing in the plan asks for it.**

⭐ **Where a genuine read/write asymmetry shows up** — a dashboard aggregating
across many aggregates, a report that would otherwise cross a module boundary to
read — build a **read model in that module's own tables**, fed by a `Listeners/`
projection from events it already consumes.

⛔ **That is the correct answer to "I need another module's data": a projection
you own, built from events you already receive** — never a cross-module `use`,
and never a join into a table you do not own.

## 5. ⛔ THE THREE THINGS THAT WILL NOT WORK WITHOUT A MODULE PROVIDER

**`R242`: one module, one directory, hyphen included — so classes autoload by
CLASSMAP, not PSR-4.** `app/Modules/X-126/`, never `app/Modules/X126/`.

Classmap autoloading loads classes. **It does not make Laravel discover
anything**, and three things silently do not work until each module's
`ModuleServiceProvider` says so:

| **Livewire components** | Livewire resolves a name to a class and will not find one outside its configured namespace. **Register each explicitly**: `Livewire::component('x-126.thing', Thing::class)` |
| :--- | :--- |
| **migrations** | `loadMigrationsFrom(__DIR__.'/Database/migrations')` — otherwise `migrate` never sees the module's tables and the schema stage reports a module that owns nothing |
| **views** | `loadViewsFrom(__DIR__.'/Ui/views', 'x-126')` |

⭐ **Register providers in `bootstrap/providers.php`.** That file is outside every
module, so listing them there is **not** a boundary violation — the lint asks
which module a *file* belongs to, and that one belongs to none.

⛔ **After adding a module directory, `composer dump-autoload`.** A classmap is
built, not scanned at runtime. "My class does not exist" is this, nearly every
time.

## 6. THE DOMAIN LAYER IS WHERE THE REFUSALS LIVE

⭐ **`X-126` decides whether an action may run at all, and `X-119` decides
whether there is a Fact to run it on.** *No Fact → no skill* — the agent never
invents a price, a time or a link.

**So a module's `Domain/` holds the invariants that are true regardless of who
is asking**, and the capability gate holds the ones about *this* caller, *this*
tenant, *this* moment. **Do not reimplement the gate inside a module**, and do
not push a domain invariant out into the gate where it becomes a policy row
somebody can edit.
