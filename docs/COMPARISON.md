# THIS PLAN vs `goaiez-review-system/docs/BUILD-PLAN.md`

**Both are real build plans for the same product. They are not versions of each
other** — they have different units, different gates and different sources of
truth, and this document exists so nobody tries to merge them.

⛔ **The old tree is not touched by this build.** Fresh build, owner's
instruction. Nothing here reads, copies from, or writes to it.

---

## 1. Side by side

| | `goaiez-review-system` | **this package** |
| --- | --- | --- |
| **Size** | 424 KB, one file | 31 KB generated + 21 KB rules/skills |
| **Source of truth** | `29 §11.2` (the spec's build order) | `GOAIEZ-MASTER-PLAN.md` (3.26 MB) via `GOAIEZ-INDEX.json` |
| **Unit of work** | a **slice** — days, hand-scoped | a **wave** — 3–5 modules |
| **Roster** | 26 rows → ~90 slices | **124 modules**, 31 waves |
| **Written for** | a person reading once | **an agent executing repeatedly** |
| **Gates** | prose, per row | **8 doctor stages + 7 module gates, executable** |
| **Verification** | Pest, Pint, Larastan 8, CI | doctor · module-done · 12 journeys · seals · hash chain |
| **Acceptance** | per-slice tests | **12 seam-crossing journeys on real transports** |
| **Progress** | prose status, corrected in place | `BUILD-STATE.json`, machine-owned |
| **Staleness** | corrections appended inline, forever | **regenerate; nothing is typed** |
| **Blocked work** | a paragraph explaining why | `UNRESOLVED` — **and the build continues** |

## 2. What this plan takes from the old one

⭐ **The habits, which are the expensive part and were paid for once already.**

| **Drive a check RED before trusting it** | The old plan's hardest-won lesson — "a lint you ship *after* the thing it would have caught" — and the new runtime has the same failure four times over. Rule 01 |
| :--- | :--- |
| **A count in prose goes stale silently** | The old plan corrects the same figures repeatedly across 424 KB. **The direct answer here is a generator** — `bin/generate-plan.py` — and a validator that fails if the prose and the data disagree |
| **State the property, never the inventory** | A hand-written list of what something covers goes stale the first time the set moves |
| **Tenancy is not retrofittable** | `ENABLE` + `FORCE` in the creating migration, app on a non-owner role. Carried verbatim into `goaiez-schema` |
| **An unresolved finding beats a cleared one** | The old plan's refusal to close a gate line it could not honestly close is the same instinct `UNRESOLVED` mechanises |

## 3. Where they genuinely disagree

| | old | this | Which governs |
| --- | --- | --- | --- |
| **Architecture** | Laravel app: `app/Services`, `app/Livewire`, `app/Jobs` | 124 modules under `app/Modules/X-nnn/`, classmap | **This one.** The master plan is first in precedence and the owner called a fresh build |
| **Autonomy** | supervised — a slice ends and a person reviews | **unsupervised until FINISHED** | This one |
| **Cost caps** | dollar cap deleted; the credit balance is the only ceiling | `C-Ai` ceiling + `R237` COMPLEX slot, **which has no cap** | ⚠️ **Open** — question 5 |
| **Outbound voice** | ruled permitted, refused in code in five kinds | on the roster and buildable | This one, and it needs its own wave |
| **The noun `jobs`** | Laravel's queue table | a **WORK ORDER** | ⛔ **Undecided — question 1, and it blocks `X-121b`** |

## 4. What does NOT carry over, and it is worth being clear about the cost

The old tree holds real, argued, tested compliance work: consent lanes and the
`captured_by` derivation, the credit ledger and its two pools, suppression
registers that block rather than advise, the PHI exclusion chain, RLS on every
tenant table with a named-exception lint.

**None of it is imported by this plan** — fresh build, and the module boundaries
are different enough that a file-level copy would carry the old shape with it.

⭐ **What it is good for is the ARGUMENT.** When you build `X-204`, the old tree
has already discovered what a suppression read costs and where a channel-family
read is needed rather than an exact-channel one. **Read it as prior art if you
are stuck; never as a source of requirements, and never write to it.**

## 5. The one structural improvement over both

Neither plan could tell you whether it was internally consistent.

```bash
python3 bin/generate-plan.py && python3 bin/validate-plan.py
```

**Twenty-three claims, checked.** Every module placed exactly once, every call
edge satisfied, the spine first, every journey reachable, the markdown agreeing
with the data, the sha matching the index.

⭐ It has already earned it: the first draft asserted a dependency floor it did
not hold — **50 breaches** — and the validator caught it before the plan shipped.
The fix was not to weaken the claim but to find out the claim was measuring the
wrong thing entirely. That is written up in `BUILD-PLAN.md` §3 and rule 06 §H.
