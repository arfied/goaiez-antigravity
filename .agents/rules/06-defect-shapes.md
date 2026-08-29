# 06 — THE DEFECT SHAPES

**Every one of these was measured on this programme, not imagined.** They are
the failure modes of this codebase; treat them as a checklist against your own
work, not as history.

The full register is `source/GOAIEZ-AUDIT-LEDGER.md` — 222 findings.

---

## A. THE CHECK THAT PASSES BY MATCHING NOTHING

| ⛔⛔⛔ | **19 test anchors grepped directories that do not exist.** `grep` on a missing path returns nothing, and *"shows no path from X to Y"* is **satisfied by nothing.** They passed and proved nothing |
| :--- | :--- |
| ⛔ | **`notPath()` matched nothing, silently.** A filter that excludes zero files looks exactly like one that works |
| ⛔ | **A checker scanned ITSELF** — found its own pattern list. Three times, in three files |
| ⛔⛔⛔ | **A gate whose precondition nobody owns** — `help_cards`, unpassable for all 122 modules. **A gate nobody can pass is not a standard** |

⭐ **The remedy is always the same: drive it RED before you trust it.** Plant the
violation, watch it fail, remove it, watch it pass.

## B. THE REGEX THAT SILENTLY DROPS DATA

| ⛔⛔ | **A regex with a multibyte character and no `/u` dropped 758 capability rows.** ⭐ **And this is the part to carry: it worked on the starred rows that were eyeballed, and failed on the plain ones.** Spot-checking confirmed it |
| :--- | :--- |
| ⛔⛔ | **Regexing a file that was already parsed into an object — EIGHT occurrences.** A manifest is compiled PHP: `require` it |
| ⛔ | **A patch script that could not match and printed success.** `·` in a heredoc is six literal characters |

⭐ **A count that looks plausible is more dangerous than one that looks wrong.**
When a transform reports a number, check the number against the source.

## C. THE CODE THAT NEVER RAN

| ⛔ | **A private method overriding a base-class public one** killed EVERY artisan command on the tree |
| :--- | :--- |
| ⛔⛔ | **A variable used outside the scope that defines it — FOUR occurrences.** ⭐ **Every one passed `php -l`** |

⛔ **`php -l` is a syntax check, not an execution.** Run the thing.

## D. THE SECURITY CONTROL THAT REPORTED ITSELF HEALTHY

| ⛔⛔⛔ | **`ALTER ROLE goaiez_app BYPASSRLS`** switched tenant isolation off platform-wide — **and the schema stage still reported 0, because it checked TABLES and not ROLES** |
| :--- | :--- |
| ⛔⛔ | **`db:bootstrap` forced RLS and never GRANTED.** It secured the database and made it unusable. **`permission denied` is a GRANT error, not an RLS error** |

⭐ **A control has a subject.** Ask what yours actually reads before you believe
what it reports.

## E. THE CLAIM THAT OUTLIVED ITS EVIDENCE

| ⛔⛔⛔ | A section claimed **41 changes applied**; measurement found **ZERO** |
| :--- | :--- |
| ⛔⛔ | Three consecutive `doctor` runs produced byte-identical output because the files were re-downloaded into a folder and **never copied into the tree.** Nothing in any output said so |
| ⛔⛔ | **An edit that reports success and changes nothing** — six counted occurrences |

⭐ **This is why the stop-that-stage rule exists.** If the count did not fall,
the fix did not land, and doing it again is an hour spent on something already
done.

## F. THE ID THAT LOOKS AUTHORITATIVE

| ⛔⛔⛔ | **64 of 105 rulings are cited with no definition anywhere.** An id nobody can look up looks authoritative and cannot be checked |
| :--- | :--- |
| ⛔⛔ | **`Str::ulid()` accepted as an external artifact id.** A generated id is not an issued one — **a string is not an artifact** |

## G. THE STALE COUNT THAT OUTVOTES THE TRUE ONE

⛔⛔⛔ **Roster 119 appears in 47 places, 122 in 49, and the current 124 in 8.**

**An agent handed all the files reads the majority and is wrong.** The old counts
are not lies — they were true when written — and nothing distinguishes a document
that was true in August from one that is true now.

⭐ **This is why `build-plan.json` is generated and never typed**, and why
`bin/validate-plan.py` checks every claim the plan makes about itself. When the
roster moves, re-run the generator — do not edit a number.

## H. THE ARCHITECTURAL SHAPE THAT LOOKS LIKE DATA

⛔⛔ **A first draft of this very build plan ordered 124 modules topologically
over the event graph.** It put the carriers in wave 39 of 43 — leaving *a missed
call becomes a text back* unproven until the build was 90% done.

The measurement that killed it: **197 of 199 `consumes` edges resolve against
`emits`, 2 against `provides`, and the vocabularies are disjoint.** So the graph
encodes **subscriptions**, which constrain nothing about build order.

⭐ **A graph will always return an ordering. That it returned one is not evidence
that it encodes one.**

## I. AND IT IS ALREADY TRUE OF THE CAPABILITY CORPUS

⛔ **Five sources, five numbers, for the one corpus that gates every module.**
Counted at wave 0, before a line was built:

| the tracker, first cell is the id | **887** |
| :--- | :--- |
| the master plan, same rule | **901** |
| the union, which is what `CapabilityStage` sees | **1047** |
| `GOAIEZ-INDEX.json` → `law_surface.capabilities` | **765** |
| the handover and the manifest | **966**, of which **322** need a refusal |

⚠️ **The first three are a REIMPLEMENTATION of the parser** — `php artisan
capabilities:scaffold` is the authority and settles it the first time it runs.
**Record what it says.**

⛔ **Do not reconcile these by editing a number**, and do not assume 966 because
it is the one written in prose twice. **The 322 figure is derived from 966**, so
if scaffold reports a different total the refusal scope moves with it — and that
is an owner decision, not an agent one.

⭐ **This is shape G happening live, inside the corpus rather than in a document
about it.** `bin/preflight.py` prints all five on every run so the disagreement
is visible before it is inherited.
