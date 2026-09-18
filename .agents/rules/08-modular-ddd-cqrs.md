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

## 4. ⛔⛔⛔ HOW FAR TO TAKE IT — DECIDED, AND THE MEASUREMENT BEHIND IT

**The measurement first, because it is what settled this:** ⭐ **122 of 124
modules own tables, the median is 3, and only `X-121` is large at 13.** The
modules are **already aggregate-sized**, and the runtime already enforces the
boundary between them with a build-failing lint.

⭐⭐⭐ **So the module boundary is doing the job DDD's aggregate boundary normally
does** — and a rich domain model inside a 2-table module is a layer enforcing
something already enforced one level up.

| ✅ **command/query separation** | **EVERY module.** `Queries/` never writes; `Actions/` never returns a read model. Near-zero cost, and it makes *"where does this code go"* mechanical rather than a judgement — which matters enormously when nobody is reviewing 124 modules |
| :--- | :--- |
| ✅ **domain events as the only seam** | **EVERY module.** Already mandatory — the boundary lint permits nothing else |
| ⚠️ **a `Domain/` layer** | **34 of 124 modules, by the mechanical rule in §4.1** |
| ⛔ **repository interfaces over Eloquent** | **NO.** Doubles the code, fights the framework, buys portability nobody wants. It is the classic way DDD-in-Laravel goes bad |
| ⛔ **separate read/write stores** | **NO.** RLS plus one-table-one-owner already gives the isolation |
| ⛔ **event sourcing** | **NO — and it is not a taste call. See §4.2** |

### 4.1 ⭐ WHEN A MODULE GETS A `Domain/` LAYER — MECHANICAL, SO IT IS ANSWERED THE SAME WAY IN WAVE 3 AND WAVE 27

**`build-plan.json` carries the verdict per module** — `domain_layer` and
`domain_because`. It is computed from:

| ✅ | the module **owns ≥4 tables** |
| :--- | :--- |
| ✅ | its subject touches **money, consent or entitlement** |
| ✅ | its **brief names an invariant spanning two of its own tables** |
| ✅ | it has a **state machine** with legal transitions |

⚠️ **The first two are computed; the last two are yours to spot with the brief in
hand.** The generated verdict is a **starting classification, not a ceiling** —
if the brief names an invariant, add `Domain/` whatever the JSON says.

⛔ **Everywhere else: the Eloquent model plus an Action class IS the aggregate.**
Adding a `Domain/` folder to a 2-table integration module is ceremony, and 124
inconsistently-applied patterns are worth less than 124 uniform simple ones.

### 4.2 ⛔⛔⛔ WHY EVENT SOURCING IS REFUSED, AND IT IS NOT BECAUSE IT IS HEAVY

**The case FOR it is real and was weighed**: `X-123` is already a typed event bus
with `event.replay`, audit is a requirement, actions carry reversal classes, and
`X-121` provides `entity.history` / `entity.restore` over an `entity_history`
table. That last one looks like temporal storage and nearly settles it the other
way.

**It is still refused, for three reasons in descending order:**

| ⛔⛔⛔ **it contradicts `P-163`** | Event sourcing wants **one shared append-only store**; *one table, one owner* is enforced by the `schema` stage. **Direct structural conflict with a rule that fails the build** |
| :--- | :--- |
| ⛔⛔ **erasure** | This platform holds **PHI and consent records**. You cannot delete from an immutable log without breaking it, and a right-to-erasure request is exactly the case it fails |
| ⭐ **`entity_history` is VERSIONING, not event sourcing** | A history table beside current state gives ~90% of the benefit at ~10% of the cost, **and it is what the plan actually describes** |

### 4.3 ⭐ "I NEED ANOTHER MODULE'S DATA"

**A read model in YOUR OWN tables, fed by a `Listeners/` projection from events
you already consume.**

⛔ Never a cross-module `use`. ⛔ Never a join into a table you do not own.

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
