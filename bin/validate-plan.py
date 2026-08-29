#!/usr/bin/env python3
"""
Check build-plan.json against every claim BUILD-PLAN.md makes about it.

A plan that asserts a property nobody checks is the defect this programme is
about.  Run after every generate-plan.py.  Exit 0 = the claims hold.
"""
import json, pathlib, sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
P    = json.loads((ROOT / "build-plan.json").read_text())
IDX  = json.loads((ROOT / "source/GOAIEZ-INDEX.json").read_text())
MD   = (ROOT / "BUILD-PLAN.md").read_text()
fail = []

def check(name, ok, detail=""):
    print(f"  {'OK  ' if ok else 'FAIL'}  {name}{'  ' + detail if detail else ''}")
    if not ok: fail.append(name)

waves   = P["waves"]
wave_of = {m: w["wave"] for w in waves for m in w["modules"]}
roster  = set(IDX["modules"])
placed  = [m for w in waves for m in w["modules"]]

check("every roster module is placed", set(placed) == roster,
      f"{len(set(placed))} of {len(roster)}")
check("no module placed twice", len(placed) == len(set(placed)), f"{len(placed)}")
check("no wave exceeds 5 modules",
      all(len(w["modules"]) <= 5 for w in waves),
      str([(w["wave"], len(w["modules"])) for w in waves if len(w["modules"]) > 5]))

# the ONE ordering constraint that is real: a call edge
bad = [(a, wave_of[a], b, wave_of[b])
       for a, bs in P["call_edges"].items() for b in bs
       if a in wave_of and b in wave_of and wave_of[b] > wave_of[a]]
check("every call edge is satisfied (caller not before callee)", not bad, str(bad))

# the spine is first
spine = set(waves[0]["modules"]) | set(waves[1]["modules"])
check("X-121 and X-123 are in wave 1", {"X-121", "X-123"} <= set(waves[0]["modules"]),
      str(waves[0]["modules"]))
check("the spine track is the first track", waves[0]["track"] == "spine")

# journeys
for j in P["journeys"]:
    miss = [r for r in j["requires"] if r not in roster]
    check(f"journey {j['id']} requirements are on the roster", not miss, str(miss))
half = len(waves) // 2
early = [j["id"] for j in P["journeys"] if (j["green_after_wave"] or 99) <= half]
check("at least 4 journeys are green by the halfway wave", len(early) >= 4,
      f"{len(early)} by wave {half}: {' '.join(early)}")

# every journey is reachable
unreach = [j["id"] for j in P["journeys"] if j["green_after_wave"] is None]
check("every journey becomes green at some wave", not unreach, str(unreach))

# the markdown and the json agree
check("BUILD-PLAN.md states the generated roster count", str(P["roster"]) in MD)
check("BUILD-PLAN.md states the generated wave count", str(len(waves)) in MD)
check("plan sha matches the index", P["plan_sha256"] == IDX["plan_sha256"])
check("no wave table row is missing from the markdown",
      all(f"| {w['wave']} | {w['track']} |" in MD for w in waves))

# the edge-model claim the generator's docstring rests on
em = P["edge_model"]
check("the measured edge split is recorded",
      em["call_edges"] == 2 and em["subscription_edges"] > 100,
      f"calls={em['call_edges']} subs={em['subscription_edges']}")

# the architecture decision is data, and the rule cites its count
arch = P.get("architecture", {})
check("build-plan.json carries the architecture decision", bool(arch))
dl = [m for m, v in P["modules"].items() if v.get("domain_layer")]
check("every module has a domain_layer verdict",
      all("domain_layer" in v for v in P["modules"].values()),
      f"{len(dl)} get Domain/")
check("the domain_layer count matches the emitted total",
      arch.get("domain_layer_count") == len(dl), f"{arch.get('domain_layer_count')} vs {len(dl)}")
check("event sourcing is refused in writing", "event_sourcing" in arch.get("refused", {}))
rule = (ROOT / ".agents/rules/08-modular-ddd-cqrs.md").read_text()
check("rule 08 states the generated Domain/ count", f"{len(dl)} of 124" in rule)

print()
if fail:
    print(f"{len(fail)} FAILING CLAIM(S): " + ", ".join(fail)); sys.exit(1)
print("every claim BUILD-PLAN.md makes about build-plan.json holds.")
