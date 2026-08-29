#!/usr/bin/env python3
"""
Refuse to start the build if the source package is not what the manifest says.

    python3 bin/preflight.py            # run from the package root

WHY THIS EXISTS
---------------
files-61.zip delivered 8 of the 20 files its own manifest listed.  Most absences
were harmless prose.  ONE was not: GOAIEZ-TRACKER-CAPABILITIES.md holds all 966
capability rows, and without it capabilities:scaffold fails, no capabilities.php
is written, CapabilityStage's floor reddens every module, and NO WAVE CAN CLOSE.

Discovering that at wave 14 costs the whole run.  This makes it a wave-0 refusal.

It now checks the WHOLE manifest rather than a hand-kept list, because a
hand-kept list of another document's contents is the exact artefact this
programme keeps getting wrong.
"""
import json, pathlib, re, sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
SRC, RT = ROOT / "source", ROOT / "runtime"
MAN = json.loads((SRC / "GOAIEZ-PACKAGE-MANIFEST.json").read_text())

# Load-bearing: named because runtime CODE reads them, verified by grepping the
# runtime for every GOAIEZ-* filename it opens.
LOAD_BEARING = {
 "GOAIEZ-MASTER-PLAN.md":
   "module:scaffold, brief, why, context and find all read it. Without it the "
   "scaffold reports 0 modules instead of failing.",
 "GOAIEZ-TRACKER-CAPABILITIES.md":
   "all 966 capability rows. capabilities:scaffold reads it; without it "
   "CapabilityStage's floor reddens every module and no wave can close. "
   "NOT reconstructible from the plan.",
 "GOAIEZ-INDEX.json":
   "the machine-readable roster. bin/generate-plan.py reads it.",
}

# Every group the manifest defines - not a hand-picked two. `package_2` is the
# guided v3.2-patch path and is not built here, but it IS a manifest file and
# must not trip the stray guard below.
listed = {e["file"].split("/")[-1]: e["bytes"]
          for v in MAN.values() if isinstance(v, list)
          for e in v if isinstance(e, dict) and "file" in e and "bytes" in e}

def find(name):
    for d in (SRC, RT):
        if (d / name).is_file():
            return d / name
    return None

absent, wrong, ok = [], [], []
for name, want in sorted(listed.items()):
    p = find(name)
    if p is None:
        absent.append((name, want))
    elif p.stat().st_size != want:
        wrong.append((name, want, p.stat().st_size))
    else:
        ok.append(name)

print(f"MANIFEST — {len(listed)} files listed")
print(f"  {len(ok)} present at the listed size")
for name, want, have in wrong:
    print(f"  SIZE  {name}  have {have:,}  listed {want:,}")
for name, want in absent:
    tag = "⛔ LOAD-BEARING" if name in LOAD_BEARING else "  not load-bearing"
    print(f"  MISS  {name}  ({want:,} bytes) {tag}")

# ⛔ Superseded working documents must never enter source/.  Measured in the
#    history archive: roster 119 appears 27 times, 122 seventeen, 124 twice.
strays = sorted(p.name for p in SRC.rglob("GOAIEZ-*.md") if p.name not in listed)
if strays:
    print("\n⛔ SUPERSEDED WORKING DOCUMENTS HAVE ENTERED source/")
    print("   The manifest lists what to read; the other 83 carry stale counts,")
    print("   and an agent reading the majority is wrong.")
    print("   Move these to ../grs-antig-history/ :")
    for f in strays[:20]:
        print(f"     {f}")
    sys.exit(1)

blocking = [n for n, _ in absent if n in LOAD_BEARING]
if blocking:
    print("\n⛔ REFUSED — do not start the build.\n")
    for n in blocking:
        print(f"  MISSING: {n}\n           {LOAD_BEARING[n]}\n")
    sys.exit(1)

print("\nevery load-bearing source file is present." if not (absent or wrong)
      else "\nno load-bearing file is missing; the differences above are not blocking.")

# ---------------------------------------------------------------- the corpus
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
idx = json.loads((SRC / "GOAIEZ-INDEX.json").read_text())

print(f"""
CAPABILITY CORPUS — the artefacts disagree, and this is not blocking
-------------------------------------------------------------------
  tracker, first cell is the id       : {len(trk)}
  master plan, same rule              : {len(pln)}
  union, which is what the stage sees : {len(trk | pln)}
  GOAIEZ-INDEX.json law_surface       : {idx['law_surface']['capabilities']}
  handover and manifest both say      : 966  (of which 322 need a refusal)

  Five numbers for one corpus. The first three come from a REIMPLEMENTATION of
  the parser in Python — `php artisan capabilities:scaffold` is the authority
  and settles it the first time it runs. Record what it says.

  ⛔ Do NOT reconcile these by editing a number. The 322-refusal figure is
     derived from 966; if scaffold reports a different total, the refusal scope
     moves with it, and that is an owner decision rather than an agent one.""")
