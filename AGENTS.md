# GO AI EZ — AGENT CONTRACT

**You are building a 124-module platform, unsupervised, until it is done.**

Read this file, then `.agents/workflows/loop.md`, then start. Do not read the
3.26 MB master plan — the runtime reads it for you, one module at a time.

---

## THE LOOP

```bash
python3 bin/state.py next        # tells you exactly what to do now
```

**Run it. Do what it says. Run it again.** It returns one JSON object with an
`action`. That is your whole control flow.

| `action` | What you do |
| --- | --- |
| `BOOTSTRAP` | run `.agents/workflows/bootstrap.md` |
| `BUILD_WAVE` | run `.agents/workflows/wave.md` for the wave it names |
| `JOURNEYS` | implement the red journeys against real transports |
| `FINISHED` | write the final report and stop |
| `STOP` | stop and report — this is the **only** thing that stops you |

---

## ⛔ THE ONE RULE

**Every fix must change the SYSTEM. No fix may change a CHECK.**

Before you edit any file: *am I making the code correct, or am I making the
checker quieter?* If it is the second, stop and record it as `UNRESOLVED`.

**An unresolved violation is a useful fact. A silenced one is a lie that
survives into production.**

> **You are not scored on the count going down. You are scored on whether the
> count that remains is TRUE.**

---

## ⭐⭐⭐ R245 — WHERE THE PLAN DOES NOT DECIDE, **YOU** DECIDE AND BUILD

> ***"I do not know" is not a stopping condition.***

```bash
python3 bin/state.py decided <X-nnn> "<what you chose and why>"     # a DECISION
python3 bin/state.py unresolved <X-nnn> <stage> "<what is missing>" # a DEPENDENCY
```

⛔ **`UNRESOLVED` is ONLY for a missing dependency** — a table another module
owns, a credential that does not exist, a transport nobody built. **Never for an
unmade decision.** Decide it, mark it `(R245)` in the module header or its
capability row, and build.

**Five things stay the owner's**: a new threshold · a retention period ·
pricing · legal text · anything that REVERSES a ruling. ⛔ **And even those do
not stop the module** — leave `TODO(Q-045)` with your recommendation and build
everything around it. **A blocked value is not a blocked module.**

⭐ **Build the best version, not the safe version.**

## ⛔⛔⛔ AND "NOT GATED" NEVER MEANS "NO REFUSALS" — R246

**A refusal is the system being CORRECT. A gate is the system being TIMID.**

⛔ **These STAY, always**: `X-126`'s `NO_FACT` · consent, quiet hours, DNC ·
RLS tenant isolation · the money confirmation · `X-220`'s golden set.

**Read `.agents/rules/09-r245-r246-delegation.md` before wave 1.**

---

## ⛔ THE STOP-THAT-STAGE RULE

After a fix, re-run **only that stage**:

```bash
php artisan doctor --stage=<stage>
```

**If the count did not fall, your fix did not land.** Do not fix it again and do
not try something else — record `UNRESOLVED` and move to the next stage. An edit
that reports success and changes nothing has happened repeatedly on this
codebase; six times that anyone counted, and each time the next hour went on
fixing something that was already fixed.

---

## ⛔ NEVER

| ⛔⛔⛔ | **Edit `app/Doctor/**` or `app/Doctor/seals.json`.** Sealed. A mismatch is a FINDING — re-run `runtime/goaiez-runtime.sh`, never re-seal. A checker that certifies itself measures nothing |
| :--- | :--- |
| ⛔⛔⛔ | **Fabricate an evidence file.** `render.json`, `runtime-proof.json`, `lint.json` — a proof you wrote in order to pass a gate is the one thing this system exists to prevent |
| ⛔⛔ | **Stub `tests/Journeys/JourneyHarness.php`.** Those methods throw on purpose. Stubbing makes all twelve journeys pass while touching no carrier, no gateway, no queue and no database |
| ⛔⛔ | **Add an exemption** so a violating file stops being scanned |
| ⛔⛔ | **Delete a capability id, an assertion or a refusal** to clear a violation. The id going missing IS the failure |
| ⛔ | **Edit `app/Modules/*/manifest.php` or `capabilities.php`.** GENERATED — erased by the next scaffold. Edit the plan or the tracker, then regenerate |
| ⛔ | **Cite an id you cannot resolve.** `php artisan why <id>` — if it returns nothing, state the fact instead. 64 unresolvable citations already exist; never add the 65th |
| ⛔ | **Touch `/home/arf/dev/goaiez-review-system*`.** Different trees, different plan. This build is self-contained |

`match (true) { … default => … }` **is legal.** It is a chained conditional, not
an enum. Do not "fix" it and do not report it.

---

## THE RULES

`.agents/rules/` — read all seven before wave 1. They are short.

| `00-precedence.md` | which document wins where they disagree |
| :--- | :--- |
| `01-the-one-rule.md` | system vs check, in full |
| `02-autonomy.md` | when to stop, and the four reasons |
| `03-module-contract.md` | the eight fields, R242, the classmap |
| `04-evidence.md` | what counts as proof, and what does not |
| `05-forbidden.md` | sealed, generated, and off-limits |
| `06-defect-shapes.md` | the twenty defects this programme already produced |
| `07-tech-stack.md` | ⛔ **the pinned stack** — Laravel 13, Livewire 4, Postgres 16, Tailwind 4. Not a default to be re-chosen |
| `08-modular-ddd-cqrs.md` | ⛔ **fully modular, DDD, CQRS** — and how far to take CQRS, which is not all the way |
| `09-r245-r246-delegation.md` | ⭐⭐⭐ **decide and build; what "not gated" never means; the ten decided; the seven new facts.** Supersedes rule 02 on owner decisions |

## THE SKILLS

`.agents/skills/` — invoke the one that matches the work in front of you.

## VERIFY WHICH CODE YOU ARE RUNNING

`doctor`'s first line is `goaiez doctor · build <stamp>`. If you did not just
install that build, you are looking at old results. Three consecutive runs once
produced byte-identical output because the files were re-downloaded into a
folder and never copied into the tree, and nothing in any output said so.
