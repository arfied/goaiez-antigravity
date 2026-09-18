#!/usr/bin/env python3
"""
The build state machine.  This is what makes the loop autonomous.

    python3 bin/state.py init            # seed state from build-plan.json
    python3 bin/state.py next            # what to do now  (JSON, one object)
    python3 bin/state.py start   <id>
    python3 bin/state.py done    <id>
    python3 bin/state.py unresolved <id> <stage> <why...>   # MISSING DEPENDENCY only
    python3 bin/state.py resolve <id> <stage> --reason <why...>  # withdraw one of those
    python3 bin/state.py decided <id> <what you chose...>   # an R245 design decision
    python3 bin/state.py journey <J1..J12> green|red
    python3 bin/state.py note    <text...>
    python3 bin/state.py report          # the one-message report
    python3 bin/state.py status          # one screen

THE RULE THAT MAKES IT AUTONOMOUS
---------------------------------
UNRESOLVED DOES NOT STOP THE LOOP.  It records a fact and the loop moves to the
next module.  A blocker parks one module; it does not park the build.

AND UNDER R245 IT IS NARROWER THAN IT WAS
-----------------------------------------
UNRESOLVED is ONLY for a MISSING DEPENDENCY - a table another module owns, a
credential that does not exist, a transport nobody built.  NEVER for an unmade
decision: where the plan does not decide, the agent decides and BUILDS, and
records it with `decided`.  "I do not know" is not a stopping condition.

The loop stops for exactly four reasons and no others:

  FINISHED   every module DONE or UNRESOLVED, every journey green or UNRESOLVED
  RUNTIME    doctor:selftest reports a problem in the checker itself
  SEAL       the seal digest moved - a sealed file changed or arrived off-bundle
  STARVED    nothing is actionable and something is still not done, which means
             every remaining module is blocked on an owner decision

Anything else - a red stage, a failing gate, a missing publisher, a module you
cannot finish - is a fact to record, not a reason to stop.
"""
import json, pathlib, sys, datetime

ROOT  = pathlib.Path(__file__).resolve().parent.parent
PLAN  = ROOT / "build-plan.json"
STATE = ROOT / ".agents/state/BUILD-STATE.json"
JRNL  = ROOT / ".agents/state/JOURNAL.md"

def now(): return datetime.datetime.now().isoformat(timespec="seconds")
def load_plan():  return json.loads(PLAN.read_text())
def load():       return json.loads(STATE.read_text())
def save(s):
    STATE.parent.mkdir(parents=True, exist_ok=True)
    STATE.write_text(json.dumps(s, indent=1) + "\n")

def journal(line):
    JRNL.parent.mkdir(parents=True, exist_ok=True)
    with JRNL.open("a") as f:
        f.write(f"- `{now()}` {line}\n")

def cmd_init(force=False):
    p = load_plan()
    if STATE.exists() and not force:
        print("state exists; use `init --force` to reseed (this loses progress)")
        return 1
    s = {
      "_what": "The live build state. bin/state.py owns this file - do not hand-edit.",
      "started": now(), "updated": now(),
      "plan_sha256": p["plan_sha256"], "roster": p["roster"],
      "runtime_build": p["runtime_build"], "seal_digest": p["seal_digest"],
      "seal_digest_observed": None,
      "runtime_sound": None,
      "bootstrap_done": False,
      "stages": {d["stage"]: {"violations": None, "fails": d["fails"]}
                 for d in p["doctor_stages"]},
      "waves": {str(w["wave"]): {"track": w["track"], "status": "NOT_STARTED",
                                 "modules": w["modules"]} for w in p["waves"]},
      "modules": {m: {"status": "NOT_STARTED", "wave": d["wave"],
                      "intent": d["intent"], "unresolved": []}
                  for m, d in p["modules"].items()},
      "journeys": {j["id"]: {"status": "RED", "title": j["title"],
                             "green_after_wave": j["green_after_wave"]}
                   for j in p["journeys"]},
      "unresolved": [], "decisions": [], "notes": [],
    }
    save(s); journal("state initialised")
    print(f"initialised: {len(s['modules'])} modules, {len(s['waves'])} waves")
    return 0

TERMINAL = {"DONE", "UNRESOLVED"}

def cmd_next():
    s, p = load(), load_plan()
    out = lambda d: (print(json.dumps(d, indent=1)), 0)[1]

    if s.get("runtime_sound") is False:
        return out({"action": "STOP", "reason": "RUNTIME",
                    "say": "doctor:selftest reports a problem in the runtime itself. "
                           "app/Doctor is sealed - do not patch it. Re-run "
                           "runtime/goaiez-runtime.sh; if it persists, report and stop."})
    obs, exp = s.get("seal_digest_observed"), s.get("seal_digest")
    if obs and obs != exp:
        return out({"action": "STOP", "reason": "SEAL",
                    "say": f"seal digest is {obs}, expected {exp}. A sealed file changed "
                           f"or arrived outside the bundle. This is a FINDING - never "
                           f"re-seal. Re-run runtime/goaiez-runtime.sh."})
    if not s.get("bootstrap_done"):
        return out({"action": "BOOTSTRAP",
                    "workflow": ".agents/workflows/bootstrap.md",
                    "say": "no tree yet. Run the bootstrap workflow, then "
                           "`state.py note bootstrap-done` once app:deploy-check passes."})

    for w in p["waves"]:
        wid  = str(w["wave"])
        mods = w["modules"]
        if all(s["modules"][m]["status"] in TERMINAL for m in mods):
            continue
        todo = [m for m in mods if s["modules"][m]["status"] not in TERMINAL]
        return out({
          "action": "BUILD_WAVE", "wave": w["wave"], "track": w["track"],
          "why": w["why"], "modules": mods, "remaining": todo,
          "next_module": todo[0],
          "journeys_unlocked": w["journeys_unlocked"],
          "subscribes_to": w["subscribes_to"],
          "subscribed_to_by": w["subscribed_to_by"],
          "not_yet_built_publishers": w["not_yet_built_publishers"],
          "workflow": ".agents/workflows/wave.md",
          "brief": [f"php artisan brief {m}" for m in todo],
          "domain_layer": {m: p["modules"][m].get("domain_layer") for m in todo},
          "domain_because": {m: p["modules"][m].get("domain_because") for m in todo},
        })

    red = [j for j, d in s["journeys"].items() if d["status"] == "RED"]
    if red:
        return out({"action": "JOURNEYS", "red": red,
                    "workflow": ".agents/workflows/wave.md",
                    "say": "every module is terminal. Implement the remaining journeys "
                           "against REAL transports. Never stub JourneyHarness."})

    unres = [m for m, d in s["modules"].items() if d["status"] == "UNRESOLVED"]
    return out({"action": "FINISHED", "unresolved_modules": unres,
                "unresolved": s["unresolved"],
                "say": "every module is DONE or UNRESOLVED and every journey is green. "
                       "Write the final report with `state.py report`."})

def _set(mid, status, s):
    if mid not in s["modules"]:
        print(f"{mid} is not on the roster"); sys.exit(1)
    s["modules"][mid]["status"] = status
    w = str(s["modules"][mid]["wave"])
    mods = s["waves"][w]["modules"]
    s["waves"][w]["status"] = ("DONE"
        if all(s["modules"][m]["status"] in TERMINAL for m in mods) else "BUILDING")
    s["updated"] = now()

def main(argv):
    if not argv: print(__doc__); return 1
    c, a = argv[0], argv[1:]
    if c == "init": return cmd_init("--force" in a)
    if c == "next": return cmd_next()
    s = load()
    if c in ("start", "done"):
        _set(a[0], "BUILDING" if c == "start" else "DONE", s)
        journal(f"{a[0]} -> {'BUILDING' if c=='start' else 'DONE'}")
    elif c == "unresolved":
        mid, stage, why = a[0], a[1], " ".join(a[2:])
        _set(mid, "UNRESOLVED", s)
        rec = {"module": mid, "stage": stage, "why": why, "at": now()}
        s["modules"][mid]["unresolved"].append(rec); s["unresolved"].append(rec)
        journal(f"UNRESOLVED {stage} {mid} - {why}")
    elif c == "resolve":
        # Withdraws ONE unresolved record and says why.  It never marks work done:
        # UNRESOLVED is TERMINAL, so a module whose last blocker goes returns to
        # BUILDING and `next` surfaces it again; `done` stays the coder's after a gate.
        # Optional --match <needle> selects one of several same-stage rows by substring
        # of `why` (SITE-218: three boundary rows blocked a bare resolve).
        if "--reason" not in a or len(a) < 4:
            print("resolve <id> <stage> [--match <needle>] --reason <why...>  "
                  "(a withdrawal without a reason is not a record)"); sys.exit(1)
        i = a.index("--reason")
        mid, stage, why = a[0], a[1], " ".join(a[i + 1:]).strip()
        match = None
        if "--match" in a:
            mi = a.index("--match")
            if mi + 1 >= i:
                print("resolve --match needs a needle before --reason"); sys.exit(1)
            match = a[mi + 1]
        if not why:
            print("resolve needs a reason after --reason"); sys.exit(1)
        if mid not in s["modules"]:
            print(f"{mid} is not on the roster"); sys.exit(1)
        hit = [r for r in s["modules"][mid]["unresolved"] if r["stage"] == stage]
        if match is not None:
            hit = [r for r in hit if match in r.get("why", "")]
            if not hit:
                print(f"no UNRESOLVED {stage} on {mid} matching {match!r}"); sys.exit(1)
            if len(hit) > 1:
                print(f"{len(hit)} UNRESOLVED {stage} on {mid} match {match!r} — "
                      f"narrow the needle"); sys.exit(1)
        elif not hit:
            print(f"no UNRESOLVED {stage} on {mid}"); sys.exit(1)
        elif len(hit) > 1:
            print(f"{len(hit)} UNRESOLVED {stage} records on {mid} — a stage does "
                  f"not name one of them; withdrawing would take both. Pass "
                  f"--match <needle> (substring of why) to pick one, or say which "
                  f"in REVIEWS and fix the duplicate first"); sys.exit(1)
        was = hit[0]["why"]
        # Drop only the matched row (module+stage+why+at), not every same-stage row.
        # JSON reload breaks object identity across the two lists, so key on fields.
        drop_key = (hit[0].get("module", mid), hit[0]["stage"], hit[0]["why"], hit[0].get("at"))
        def _keep(r, default_mid=mid):
            return (r.get("module", default_mid), r["stage"], r["why"], r.get("at")) != drop_key
        s["modules"][mid]["unresolved"] = [
            r for r in s["modules"][mid]["unresolved"] if _keep(r)]
        s["unresolved"] = [r for r in s["unresolved"] if _keep(r)]
        s.setdefault("resolved", []).append(
            {"module": mid, "stage": stage, "was": was, "why": why, "at": now()})
        journal(f"RESOLVED {stage} {mid} - {why} (was: {was})")
        left = len(s["modules"][mid]["unresolved"])
        if left:
            print(f"withdrew {stage} on {mid}; {left} UNRESOLVED left, status unchanged")
        else:
            _set(mid, "BUILDING", s)
            print(f"withdrew the last UNRESOLVED on {mid}; status -> BUILDING "
                  f"(actionable again; `done` is still yours after a gate)")
    elif c == "decided":
        mid, what = a[0], " ".join(a[1:])
        if mid not in s["modules"]:
            print(f"{mid} is not on the roster"); sys.exit(1)
        s.setdefault("decisions", []).append(
            {"module": mid, "chose": what, "at": now(), "ruling": "R245"})
        s["updated"] = now()
        journal(f"(R245) {mid} — {what}")
        print(f"recorded (R245) {mid}. Also write it in the module header "
              f"or its capability row, marked (R245).")
    elif c == "journey":
        s["journeys"][a[0]]["status"] = "GREEN" if a[1] == "green" else "RED"
        journal(f"journey {a[0]} -> {a[1]}")
    elif c == "stage":
        s["stages"][a[0]]["violations"] = int(a[1]); journal(f"stage {a[0]} = {a[1]}")
    elif c == "selftest":
        s["runtime_sound"] = (a[0] == "sound"); journal(f"selftest: {a[0]}")
    elif c == "seal":
        s["seal_digest_observed"] = a[0]; journal(f"seal observed {a[0]}")
    elif c == "note":
        txt = " ".join(a)
        if txt == "bootstrap-done": s["bootstrap_done"] = True
        s["notes"].append({"at": now(), "note": txt}); journal(f"note: {txt}")
    elif c in ("report", "status"):
        st = lambda v: sum(1 for d in s["modules"].values() if d["status"] == v)
        print(f"SELFTEST : {'sound' if s.get('runtime_sound') else s.get('runtime_sound')}")
        print("STAGES   : " + " · ".join(
            f"{k} {v['violations'] if v['violations'] is not None else '?'}"
            for k, v in s["stages"].items()))
        print(f"MODULES  : {st('DONE')} done · {st('BUILDING')} building · "
              f"{st('NOT_STARTED')} not started · {st('UNRESOLVED')} unresolved "
              f"of {len(s['modules'])}")
        g = sum(1 for d in s["journeys"].values() if d["status"] == "GREEN")
        print(f"JOURNEYS : {g}/{len(s['journeys'])} green")
        wd = sum(1 for d in s["waves"].values() if d["status"] == "DONE")
        print(f"WAVES    : {wd}/{len(s['waves'])} closed")
        if s.get("decisions"):
            print(f"R245     : {len(s['decisions'])} decision(s) made and built")
        if s["unresolved"]:
            print("UNRESOLVED (missing dependencies only):")
            for u in s["unresolved"]:
                print(f"  {u['stage']:<11} {u['module']:<12} — {u['why']}")
        return 0
    else:
        print(__doc__); return 1
    save(s); return 0

sys.exit(main(sys.argv[1:]))
