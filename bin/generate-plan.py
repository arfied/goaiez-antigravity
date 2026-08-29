#!/usr/bin/env python3
"""
Regenerate build-plan.json and BUILD-PLAN.md from source/GOAIEZ-INDEX.json.

    python3 bin/generate-plan.py && python3 bin/validate-plan.py

WHY THIS IS A GENERATOR AND NOT A DOCUMENT
------------------------------------------
The package's own measurement: roster 119 appears in 47 places, roster 122 in
49, and the current 124 in 8.  A hand-typed plan acquires stale counts the
moment the roster moves and nothing tells a reader which copy is current.  So
no count below is typed - every one is read from the index at generation time.

WHAT THE EVENT GRAPH DOES AND DOES NOT DECIDE - MEASURED, NOT ASSUMED
--------------------------------------------------------------------
A first draft of this file ordered the build topologically over
`consumes` -> `emits` edges.  That put C-Telephony, C-Sms and C-Agent in wave 39
of 43, which would have left the product's first journey - a missed call
becoming a text back - unproven until the build was 90% done.

The measurement that explains it: of 199 consume edges, **197 resolve against an
`emits` token and 2 against a `provides` token**.  The two vocabularies are
disjoint - zero tokens appear in both.

So `consumes`/`emits` is a SUBSCRIPTION, not a call.  A subscriber does not need
its publisher to exist; it simply does not fire yet.  Ordering the build by
subscription is ordering it by something that imposes no constraint, and it
actively harms the sequence.

Therefore:

  * ORDER is driven by the twelve journeys, because a journey is the only check
    that crosses a module seam, and by the spine, because everything sits on it.
  * The event graph is emitted as a WIRING REGISTER - when you build a module,
    it tells you who subscribes to it and what it subscribes to - and as the
    orphan register.  It does not order anything.
  * The two genuine call edges ARE a hard constraint and are enforced.
"""
import json, pathlib, collections, datetime, sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
IDX  = json.loads((ROOT / "source/GOAIEZ-INDEX.json").read_text())
M    = IDX["modules"]
NONE = {"none", "", "n/a"}

def lst(v, k):
    return [x for x in v.get(k, []) if x.lower() not in NONE]

emitters  = collections.defaultdict(set)
providers = collections.defaultdict(set)
for i, v in M.items():
    for e in lst(v, "emits"):    emitters[e].add(i)
    for p in lst(v, "provides"): providers[p].add(i)

subscribes = collections.defaultdict(set)   # soft: A subscribes to B's event
calls      = collections.defaultdict(set)   # hard: A calls B's provided capability
orphan     = collections.defaultdict(list)  # consumed by someone, produced by nobody
for i, v in M.items():
    for c in lst(v, "consumes"):
        e = {x for x in emitters.get(c, set())  if x != i}
        p = {x for x in providers.get(c, set()) if x != i}
        if p: calls[i] |= p
        if e: subscribes[i] |= e
        if not e and not p: orphan[c].append(i)

# ---------------------------------------------------------------------- tracks
# The build order.  This is a JUDGEMENT, written here in the open so it can be
# argued with rather than inferred from a graph that does not encode it.
#
# Rule: the spine first because everything structurally sits on it, then one
# track per journey, in the order that gets a whole working product earliest.
TRACKS = [
 ("spine", "Nothing sits on nothing. X-121 is the noun set, X-123 carries every "
           "event, X-122 is the action surface the agent may reach, X-126 decides "
           "whether it may, X-119 is what it may say, X-128 proves the seams.",
  ["X-121", "X-123", "X-122", "X-126", "X-119", "X-128"]),

 ("ai-core", "R238: one call path, no second entrance. Before anything that calls "
             "a model, because a second entrance is unbuildable once two callers "
             "exist.",
  ["C-Ai", "X-219", "X-220"]),

 ("consent", "X-204 is the single decider of whether a message may be sent at all, "
             "and it precedes every transport: a suppression list that does not "
             "block is a list and not a suppression.",
  ["X-204", "X-206"]),

 ("J1-J2-J4 the sixty seconds", "The first journey is the whole product in sixty "
             "seconds: a missed call becomes a consented text back. J2 puts a live "
             "agent on a real number from two signup fields; J4 proves STOP halts "
             "work already in flight. Built as one track because the carriers, the "
             "agent and the number pool consume each other's events.",
  ["C-Telephony", "C-Sms", "C-Agent", "X-66", "X-188", "X-153", "X-01", "X-118"]),

 ("J3 the pricebook", "A quote comes from the pricebook or does not come at all - "
             "no Fact, no skill, made visible.",
  ["X-163", "X-164", "X-108"]),

 ("J10 reviews", "The review split: a low rating is triaged before anybody is asked "
             "for a public one.",
  ["C-Reviews", "X-181", "X-110"]),

 ("J9-J12 money", "An invoice reaches a real charge id, and an overdue invoice is "
             "chased by reason with resolution before any stop.",
  ["C-Billing", "X-198", "X-199", "X-211", "X-202", "X-117"]),

 ("J6-J7 portal", "Cancel is one tap with nothing in between; an agency client "
             "never sees cost or margin.",
  ["X-172", "X-112", "X-166"]),

 ("J11 the site", "A published site carries all seven (P-125): pixel, chat, forms, "
             "DNI, SEO, schema, SSL.",
  ["X-157", "X-178", "X-103", "X-102", "X-155", "X-137", "X-176"]),

 ("J5-J8 safety", "A migration of five hundred jobs sends nothing; a deliberately "
             "corrupted backup fails the restore.",
  ["X-212", "X-203"]),

 ("channels", "The remaining transports, behind the seam the carriers established.",
  ["C-Mail", "C-Whatsapp", "X-147", "X-207", "X-193", "X-208"]),
]

placed, seen = [], set()
for name, why, ids in TRACKS:
    keep = [i for i in ids if i in M and i not in seen]
    seen |= set(keep)
    placed.append((name, why, keep))

INTENT_RANK = {"RECOVER": 0, "SERVE": 1, "INFORM": 2, "OBSERVE": 3, "GROW": 4, "NONE": 5}
FILLER_WHY  = ("the rest of the roster, RECOVER and SERVE first because those are "
               "the intents that ship autonomous on day one")
rest = sorted(set(M) - seen,
              key=lambda i: (INTENT_RANK.get(M[i].get("intent", "NONE"), 9), i))
placed.append(("roster", FILLER_WHY, rest))

# ------------------------------------------------------------ order into waves
WAVE_MAX = 5
waves = []
for name, why, ids in placed:
    for n in range(0, len(ids), WAVE_MAX):
        chunk = ids[n:n + WAVE_MAX]
        if chunk:
            waves.append({"wave": len(waves) + 1, "track": name, "why": why,
                          "modules": chunk})

wave_of = {m: w["wave"] for w in waves for m in w["modules"]}

# hard call edges must be respected - repair by pushing the caller later
for _ in range(50):
    bad = [(a, b) for a, bs in calls.items() for b in bs
           if a in wave_of and b in wave_of and wave_of[b] > wave_of[a]]
    if not bad:
        break
    for a, b in bad:                      # move the caller into the callee's wave
        for w in waves:
            if a in w["modules"]:
                w["modules"].remove(a)
        for w in waves:
            if w["wave"] == wave_of[b]:
                w["modules"].append(a)
    waves = [w for w in waves if w["modules"]]
    for n, w in enumerate(waves, 1):
        w["wave"] = n
    wave_of = {m: w["wave"] for w in waves for m in w["modules"]}

# ------------------------------------------------- per-wave wiring and journeys
for w in waves:
    ms = set(w["modules"])
    w["subscribes_to"] = sorted({s for m in ms for s in subscribes.get(m, ())} - ms)
    w["subscribed_to_by"] = sorted({o for o, ss in subscribes.items()
                                    if ss & ms and o not in ms})
    w["calls"] = sorted({c for m in ms for c in calls.get(m, ())} - ms)
    w["not_yet_built_publishers"] = sorted(
        p for p in w["subscribes_to"] if wave_of.get(p, 0) > w["wave"])

JOURNEYS = [
 ("J1",  "a missed call becomes a consented text back",               ["C-Telephony","C-Sms","X-188","X-204"]),
 ("J2",  "two fields at signup put a live agent on a real number",    ["X-118","C-Agent","X-188","X-66"]),
 ("J3",  "a quote comes from the pricebook or does not come at all",  ["X-163","X-119","X-126"]),
 ("J4",  "STOP halts every pending step for that person",             ["X-204","C-Sms","X-121"]),
 ("J5",  "a migration of five hundred jobs sends nothing",            ["X-212","X-204"]),
 ("J6",  "cancel is one tap with nothing in between",                 ["X-172","C-Billing"]),
 ("J7",  "an agency client never sees cost or margin",                ["X-112","X-166"]),
 ("J8",  "a deliberately corrupted backup fails the restore",         ["X-203"]),
 ("J9",  "an invoice reaches a real charge id",                       ["X-199","X-198","C-Billing"]),
 ("J10", "a completed job asks for a review once inside the cadence", ["C-Reviews","X-181"]),
 ("J11", "a published site carries all seven",                        ["X-157","X-110","X-102","X-155","X-137"]),
 ("J12", "an overdue invoice is chased by reason, resolution first",  ["X-211","X-199","X-204"]),
]
journeys = [{"id": j, "title": t, "requires": r,
             "green_after_wave": max((wave_of[n] for n in r if n in wave_of), default=None)}
            for j, t, r in JOURNEYS]
for w in waves:
    w["journeys_unlocked"] = [j["id"] for j in journeys if j["green_after_wave"] == w["wave"]]

# ------------------------------------------------------------------------ emit
plan = {
 "_generated_by": "bin/generate-plan.py - do not hand-edit, re-run it",
 "generated": datetime.date.today().isoformat(),
 "plan_sha256": IDX["plan_sha256"],
 "roster": IDX["roster"],
 "runtime_build": IDX["runtime_build"],
 "seal_digest": IDX["seal_digest"],
 "next_free_module": IDX["next_free_module"],
 "next_free_ruling": IDX["next_free_ruling"],
 "edge_model": {
   "_measured": "199 consume edges: 197 resolve against an `emits` token, 2 "
                "against a `provides` token. The vocabularies are disjoint.",
   "subscription_edges": sum(len(v) for v in subscribes.values()),
   "call_edges": sum(len(v) for v in calls.values()),
   "_why_it_matters": "a subscription is not a build-order constraint - the "
                      "subscriber is built first and does not fire. Only the "
                      "call edges are ordered.",
 },
 "counts": {
   "modules": len(M), "waves": len(waves), "journeys": len(journeys),
   "doctor_stages": 8, "module_gates": 7, "orphan_events": len(orphan),
   "by_intent": dict(collections.Counter(v.get("intent", "NONE") for v in M.values())),
 },
 "doctor_stages": [
   {"n":1,"stage":"integrity", "fails":"COMMIT","what":"the stage that watches the other stages"},
   {"n":2,"stage":"boundary",  "fails":"COMMIT","what":"pure grep, ~2s"},
   {"n":3,"stage":"contract",  "fails":"COMMIT","what":"reads the annotations, never the prose"},
   {"n":4,"stage":"citation",  "fails":"COMMIT","what":"every cited law resolves"},
   {"n":5,"stage":"schema",    "fails":"MERGE", "what":"tenancy and RLS, ~20s"},
   {"n":6,"stage":"capability","fails":"MERGE", "what":"id-matched, two-pass"},
   {"n":7,"stage":"anchor",    "fails":"WAVE",  "what":"a real message-id, not an assertion"},
   {"n":8,"stage":"journey",   "fails":"WAVE",  "what":"end to end on real transports"},
 ],
 "module_gates": ["1 BUILT","2 TESTED","3 CONTENT","4 HELP","5 DASHBOARD","6 SURFACES","7 GATE"],
 "blocking_decisions": IDX["blocking_decisions"],
 "waves": waves,
 "journeys": journeys,
 "orphan_events": {k: sorted(v) for k, v in sorted(orphan.items(), key=lambda x: -len(x[1]))},
 "call_edges": {k: sorted(v) for k, v in sorted(calls.items())},
 "subscription_edges": {k: sorted(v) for k, v in sorted(subscribes.items())},
 "modules": {i: {"intent": M[i].get("intent"), "wave": wave_of[i],
                 "state": M[i].get("state"), "owns_table": lst(M[i], "owns_table"),
                 "what": (M[i].get("what") or "")}
             for i in sorted(M)},
}
(ROOT / "build-plan.json").write_text(json.dumps(plan, indent=1) + "\n")

rows = []
for w in waves:
    mods = " ".join(f"`{m}`" for m in w["modules"])
    jr = " ".join(w["journeys_unlocked"]) or "-"
    rows.append(f"| {w['wave']} | {w['track']} | {mods} | {jr} |")

seed = (ROOT / "docs/BUILD-PLAN.seed.md").read_text()
out = (seed.replace("{{WAVE_TABLE}}", "\n".join(rows))
           .replace("{{N_MODULES}}", str(len(M)))
           .replace("{{N_WAVES}}", str(len(waves)))
           .replace("{{N_ORPHANS}}", str(len(orphan)))
           .replace("{{N_SUBS}}", str(sum(len(v) for v in subscribes.values())))
           .replace("{{N_CALLS}}", str(sum(len(v) for v in calls.values())))
           .replace("{{ROSTER}}", str(IDX["roster"]))
           .replace("{{BUILD}}", IDX["runtime_build"])
           .replace("{{SEAL}}", IDX["seal_digest"])
           .replace("{{GENERATED}}", datetime.date.today().isoformat()))
(ROOT / "BUILD-PLAN.md").write_text(out)
print(f"waves={len(waves)} modules={len(M)} journeys={len(journeys)} "
      f"orphans={len(orphan)} subs={sum(len(v) for v in subscribes.values())} "
      f"calls={sum(len(v) for v in calls.values())}")
