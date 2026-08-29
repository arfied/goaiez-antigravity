#!/usr/bin/env python3
"""
Refuse to start the build if a load-bearing source file is missing.

    python3 bin/preflight.py            # run from the package root

WHY THIS EXISTS
---------------
`files-61.zip` delivered 8 of the 20 files its own manifest lists.  Most of the
absences are harmless prose.  ONE is not:

  GOAIEZ-TRACKER-CAPABILITIES.md holds all 966 capability rows.  Without it
  `capabilities:scaffold` fails, no capabilities.php is written, and
  CapabilityStage's floor fires "ZERO specced capabilities" on every module -
  so `capability` fails the MERGE and NO WAVE EVER CLOSES.

Discovering that at wave 14 costs the whole run.  This turns it into a refusal
at wave 0, which is the only cheap moment to find it.
"""
import pathlib, sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
SRC  = ROOT / "source"

REQUIRED = [
 ("GOAIEZ-MASTER-PLAN.md",
  "module:scaffold, brief, why, context and find all read it. Without it the "
  "scaffold reports 0 modules instead of failing."),
 ("GOAIEZ-INDEX.json",
  "the machine-readable roster. bin/generate-plan.py reads it."),
 ("GOAIEZ-TRACKER-CAPABILITIES.md",
  "all 966 capability rows. capabilities:scaffold reads it; without it "
  "CapabilityStage's floor reddens every module and no wave can close. "
  "NOT reconstructible from the plan - only 299 of the ids live there."),
]

OPTIONAL = [
 ("GOAIEZ-TRACKER-MODULES.md",  "roster tracker - no runtime code reads it; "
                                "GOAIEZ-INDEX.json already carries the roster"),
 ("GOAIEZ-AI-CORE.md",          "R238 in full - prose; the goaiez-ai skill "
                                "summarises it"),
 ("GOAIEZ-THE-DOCTOR-LOOP.md",  "prose runbook - the workflows cover it"),
 ("GOAIEZ-GODTIER-SYSTEMS.md",  "prose"),
 ("GOAIEZ-COLD-START.md",       "prose"),
]

missing = [(f, why) for f, why in REQUIRED if not (SRC / f).is_file()]
absent  = [(f, why) for f, why in OPTIONAL if not (SRC / f).is_file()]

print("REQUIRED")
for f, why in REQUIRED:
    print(f"  {'OK  ' if (SRC / f).is_file() else 'MISS'}  {f}")
if absent:
    print("\nabsent, and it does not matter")
    for f, why in absent:
        print(f"  --    {f:<34} {why}")

if missing:
    print("\n⛔ REFUSED — do not start the build.\n")
    for f, why in missing:
        print(f"  MISSING: {f}\n           {why}\n")
    print("  Ask the owner for the file(s) above, drop them in source/, re-run.")
    sys.exit(1)

print("\nevery load-bearing source file is present.")

# ---------------------------------------------------------------- the corpus
# Report the capability counts, because the artefacts disagree about them and
# a number nobody re-derives is how the roster came to say 119 in 47 places.
import re
ID = re.compile(r'^(G\d+-\d+|N-\d+(?:-\d+)?)$')

def first_cell_ids(path):
    out = set()
    for line in path.read_text(encoding="utf-8").split("\n"):
        if not line.startswith("|"):
            continue
        cells = [c.strip() for c in line.split("|")]
        if len(cells) > 1:
            m = ID.match(cells[1].strip("*` "))
            if m:
                out.add(m.group(1))
    return out

trk = first_cell_ids(SRC / "GOAIEZ-TRACKER-CAPABILITIES.md")
pln = first_cell_ids(SRC / "GOAIEZ-MASTER-PLAN.md")
import json as _json
idx = _json.loads((SRC / "GOAIEZ-INDEX.json").read_text())

print("""
CAPABILITY CORPUS — the artefacts disagree, and this is not blocking
-------------------------------------------------------------------""")
print(f"  tracker, first cell is the id      : {len(trk)}")
print(f"  master plan, same rule             : {len(pln)}")
print(f"  union, which is what the stage sees: {len(trk | pln)}")
print(f"  GOAIEZ-INDEX.json law_surface      : {idx['law_surface']['capabilities']}")
print(f"  handover and manifest both say     : 966  (of which 322 need a refusal)")
print(f"""
  Four numbers for one corpus. The counts above come from a REIMPLEMENTATION
  of the parser in Python — `php artisan capabilities:scaffold` is the
  authority and settles it the first time it runs. Record what it says.

  ⛔ Do NOT reconcile these by editing a number. The 322-refusal figure is
     derived from 966; if scaffold reports a different total, the refusal
     scope moves with it and that is an owner decision, not an agent one.""")
