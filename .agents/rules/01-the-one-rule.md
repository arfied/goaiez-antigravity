# 01 — THE ONE RULE

> # DID THE SYSTEM CHANGE, OR DID THE CHECK CHANGE?

**If you cannot answer "the system", you have not fixed anything.**

---

Before you edit any file, ask: *am I making the code correct, or am I making the
checker quieter?* If it is the second — stop, and record the violation as
`UNRESOLVED` instead.

**An unresolved violation is a useful fact. A silenced one is a lie that
survives into production.**

## ⛔ THESE ARE NOT FIXES AND ARE DETECTED

- **Disabling, commenting out or weakening any check** in `app/Doctor/**`. That
  directory is sealed and hashed; `doctor --stage=integrity` reports every
  modification by filename.
- **Adding an exemption** so a violating file stops being scanned.
- **Deleting a capability id, an assertion or a refusal** to clear a violation.
  **The id going missing IS the failure.**
- **Stubbing `tests/Journeys/JourneyHarness.php`.** Those methods throw on
  purpose.
- **Re-seal.** Never.

`match (true) { … default => … }` **is legal** — a chained conditional, not an
enum. Do not "fix" it and do not report it.

## ⛔ AND THE SUBTLE ONE: A CHECK THAT PASSES BY MATCHING NOTHING

Measured on this codebase, all four of these:

- **19 test anchors grepped directories that do not exist.** `grep` on a missing
  path returns nothing, and *"shows no path from X to Y"* is **satisfied by
  nothing**. They passed and proved nothing.
- **`notPath()` matched nothing, silently.** A filter that excludes zero files
  looks exactly like one that works.
- **A checker scanned itself** — three times, in three files.
- **A gate whose precondition nobody owns** — `help_cards`, unpassable for all
  122 modules. **A gate nobody can pass is not a standard, it is a stop sign.**

⭐ **So when you write a check, drive it RED first.** A check that has never
failed is not a check. Plant the violation, watch it redden, remove the
violation, watch it pass — then keep it.

## ⛔ AND THE ONE THAT MATTERS MOST

> **You are NOT scored on the count going down.**
> **You are scored on whether the count that REMAINS is TRUE.**

Three unresolved violations honestly reported are worth more than three hundred
cleared by deletion.
