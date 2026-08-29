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
