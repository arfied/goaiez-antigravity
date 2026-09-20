# GO AI EZ — BUILD PLAN FOR ANTIGRAVITY

**Generated 2026-09-19 by `bin/generate-plan.py`. Do not hand-edit — re-run the generator.**

| | |
| --- | --- |
| Roster | 124 modules |
| Waves | 31 |
| Runtime build | `20260829-0647` · seal digest `f1e73d9fc181eb1c` |
| Doctor stages | 8 |
| Gates per module | 7 |
| Journeys | 12 |

---

## 0. Read this first, then stop reading

**This document is not the specification.** `source/GOAIEZ-MASTER-PLAN.md` is,
and it is 3.26 MB — you will not hold it in context and you must not try.

The runtime reads it for you, one module at a time:

```bash
php artisan brief <X-nnn>      # the module contract, from the annotations only
php artisan context <X-nnn>    # that module's full context and no other's
php artisan why <id>           # why a law, ruling or module is the way it is
php artisan find <term>        # declarations and code, never prose
php artisan impact <subject>   # blast radius, exhaustive or it refuses
```

**`brief` is the specification for the module in front of you.** This plan says
only which module is in front of you, in what order, and what "done" means.

⛔ **If `php artisan why <id>` returns nothing, state the fact instead of citing
the id.** 64 of the 105 rulings are cited in the plan with no definition
anywhere. An id nobody can look up looks authoritative and cannot be checked —
never add a 65th.

---

## 1. The unit of work is a wave

**One wave = 3–5 related modules, built together, ending at the same gate.**

Not one module (the gates are too expensive to run per module) and not twenty
(nothing is verifiable at that size). The measurement behind this: the modules
that exist took 1–2 agent rounds each once tooling defects were excluded, and a
25-turn budget against 124 modules would need ~5 finished modules per turn,
which has never happened once.

**Every wave ends at the same three commands, in this order:**

```bash
php artisan doctor:selftest              # is the CHECKER sound?
php artisan doctor                       # 8 stages
php artisan doctor:module-done <X-nnn>   # 7 gates, once per module in the wave
```

A wave is closed when every module in it returns `is DONE` and the stages that
fail the WAVE — `anchor` and `journey` — are clean or carry a written
`UNRESOLVED`.

---

## 2. The severity ladder decides what you may do next

The doctor stages are not equally serious. Each one names what it blocks:

| Stage | Blocks | Meaning |
| --- | --- | --- |
| `integrity` | COMMIT | the checker itself was modified — **stop, do not fix** |
| `boundary` | COMMIT | you may not commit |
| `contract` | COMMIT | you may not commit |
| `citation` | COMMIT | you may not commit |
| `schema` | MERGE | commit yes, close the wave no |
| `capability` | MERGE | commit yes, close the wave no |
| `anchor` | WAVE | the wave does not close |
| `journey` | WAVE | the wave does not close |

⭐ **This is what lets the loop keep moving.** A `schema` violation does not stop
you working — it stops you *closing*. Commit the work, record the violation,
continue. Only `integrity` stops everything.

---

## 3. The waves, and what actually orders them

⛔ **The event graph does not order this build, and a first draft of this plan
assumed it did.** The measurement that killed that assumption is worth carrying,
because it is the difference between a good sequence and a useless one:

> Of 214 `consumes` edges on the roster, **2 resolve against a
> `provides` token and every other one resolves against an `emits` token.** The
> two vocabularies are disjoint — not one token appears in both.

**So `consumes`/`emits` is a SUBSCRIPTION, not a call.** A subscriber does not
need its publisher to exist; it is built, it is wired, and it does not fire yet.
Ordering the build by subscription orders it by something that constrains
nothing — and it does real harm: the topological draft put `C-Telephony`,
`C-Sms` and `C-Agent` in wave 39 of 43, which would have left *a missed call
becomes a text back* unproven until the build was 90% finished.

**What orders the build instead:**

| | |
| --- | --- |
| **the spine** | everything sits on it structurally |
| **the twelve journeys** | a journey is the only check that crosses a module seam, so each track is placed to light one up |
| **the 2 genuine call edges** | a real ordering constraint, and the generator enforces it |

The track grouping is a **judgement**, written in `bin/generate-plan.py` where it
can be argued with rather than inferred from a graph that does not encode it.

⭐ **Read the "Journeys green" column as the real progress bar.** Every journey is
green by wave 14 of 31; the waves after that are the remaining roster,
built on a product already proven end to end.

| Wave | Track | Modules | Journeys green |
| --- | --- | --- | --- |
| 1 | spine | `X-121` `X-123` `X-122` `X-126` `X-119` | - |
| 2 | spine | `X-128` | - |
| 3 | ai-core | `C-Ai` `X-219` `X-220` | - |
| 4 | consent | `X-204` `X-206` | - |
| 5 | J1-J2-J4 the sixty seconds | `C-Telephony` `C-Sms` `C-Agent` `X-66` `X-188` | J1 J4 |
| 6 | J1-J2-J4 the sixty seconds | `X-153` `X-01` `X-118` | J2 |
| 7 | J3 the pricebook | `X-163` `X-164` `X-108` | J3 |
| 8 | J10 reviews | `C-Reviews` `X-181` `X-110` | J10 |
| 9 | J9-J12 money | `C-Billing` `X-199` `X-211` `X-202` | J12 |
| 10 | J9-J12 money | `X-117` `X-198` | J9 |
| 11 | J6-J7 portal | `X-172` `X-112` `X-166` | J6 J7 |
| 12 | J11 the site | `X-157` `X-178` `X-103` `X-102` `X-155` | - |
| 13 | J11 the site | `X-137` `X-176` | J11 |
| 14 | J5-J8 safety | `X-212` `X-203` | J5 J8 |
| 15 | channels | `C-Mail` `C-Whatsapp` `X-147` `X-207` `X-193` | - |
| 16 | channels | `X-208` | - |
| 17 | roster | `X-125` `X-127` `X-149` `X-170` `X-201` | - |
| 18 | roster | `X-10` `X-113` `X-124` `X-143` `X-151` | - |
| 19 | roster | `X-162` `X-165` `X-171` `X-189` `X-214` | - |
| 20 | roster | `X-215` `X-07` `X-104` `X-111` `X-129` | - |
| 21 | roster | `X-138` `X-139` `X-145` `X-148` `X-150` | - |
| 22 | roster | `X-16` `X-160` `X-167` `X-168` `X-175` | - |
| 23 | roster | `X-177` `X-180` `X-194` `X-195` `X-197` | - |
| 24 | roster | `X-209` `X-82` `X-08` `X-120` `X-136` | - |
| 25 | roster | `X-141` `X-142` `X-156` `X-173` `X-213` | - |
| 26 | roster | `X-105` `X-109` `X-114` `X-116` `X-130` | - |
| 27 | roster | `X-131` `X-132` `X-134` `X-135` `X-140` | - |
| 28 | roster | `X-144` `X-154` `X-158` `X-159` `X-161` | - |
| 29 | roster | `X-179` `X-182` `X-183` `X-184` `X-185` | - |
| 30 | roster | `X-186` `X-190` `X-191` `X-192` `X-196` | - |
| 31 | roster | `X-200` `X-205` `X-210` `X-217` `X-218` | - |

---

## 4. Wave 0 — bootstrap, and it is not a module

Before wave 1 there is a tree to make. `.agents/workflows/bootstrap.md` is the
runbook. It ends with one command that decides whether any of it worked:

```bash
php artisan app:deploy-check
```

**Nine checks. Any FAIL means the box is not deployed, whatever else is true.**

---

## 5. The twelve journeys are the acceptance test

A module passing its own seven gates says nothing about whether a plumber can be
paid. The journeys are the only checks that cross a module seam, and they are
the reason the wave order is what it is — each wave is placed to light one up.

⛔ **`tests/Journeys/JourneyHarness.php` throws on every method on purpose.**
Implement them against real transports. **A stub converts the entire
seam-crossing suite into theatre** — all twelve pass while touching no carrier,
no gateway, no queue and no database.

⛔ **A journey on the `sync` queue driver proves nothing** and the harness
refuses it: it would pass with no worker running.

---

## 6. Wiring, and the 28 events nobody emits

Each wave in `build-plan.json` carries four wiring lists, because the event graph
is a wiring register even though it is not an order:

| Field | What to do with it |
| --- | --- |
| `subscribes_to` | modules whose events this wave listens to |
| `subscribed_to_by` | modules that will listen to **this** wave — do not break them later |
| `calls` | a real call. This one **is** ordered |
| `not_yet_built_publishers` | ⭐ **the important one** — subscribe anyway; it will not fire yet, and that is correct |

### 28 events are consumed by somebody and emitted by nobody

Read `build-plan.json` → `orphan_events`. These are not bugs to fix on sight —
some are emitted by modules whose declaration has not been written yet, and some
are genuinely missing producers.

⛔ **When you reach a module that consumes one, do not invent the producer.**
Record `UNRESOLVED capability <module> — consumes <event>, nothing emits it` and
carry on. The largest is `subscription.renewed`, consumed by six modules.

---

## 7. What "done" means, and it is not "the tests are green"

Seven gates, per module, and every one returns a boolean from something
**observed**:

1. **BUILT** — the code exists where the manifest says it does
2. **TESTED** — with a runtime proof carrying an **external artifact id**
3. **CONTENT** — the strings exist as data, not hardcoded
4. **HELP** — a help card per action *(returns true while `help_cards` does not
   exist — a gate whose precondition nobody owns is not a standard)*
5. **DASHBOARD** — the module is visible where it declares it is
6. **SURFACES** — an undeclared surface is refused
7. **GATE** — the capability gate decides it

⛔ **Gate 2 will not accept `Str::ulid()`.** A generated id is not an issued one.
A string is not an artifact. The proof must carry an id something outside this
process issued — a carrier message id, a gateway charge id, a provider request id.

⛔ **Never fabricate an evidence file.** `render.json`, `runtime-proof.json`,
`lint.json` — a proof you wrote in order to pass a gate is the one thing this
whole system exists to prevent.

---

## 8. When a module is DONE, the log records it and cannot be edited

`doctor:module-done` appends a hash-chained line to `INSTRUCTIONS.jsonl`. If the
chain is broken it **refuses to record DONE at all**.

This exists because a previous section of this programme claimed 41 changes were
applied and measurement found **zero**. A claim that outlives its evidence is the
most expensive defect here; the chain ends it.

---

## 9. Where this plan disagrees with the old one

`docs/COMPARISON.md` — the row-and-slice plan in `goaiez-review-system` against
this one, what carries over and what does not.

## 10. What only the owner can answer

`OWNER-QUESTIONS.md` — forwardable, in plain prose, no id numbers. The loop
routes around every one of them rather than stopping.
