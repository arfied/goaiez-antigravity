import json

with open("build-plan.json", "r") as f:
    p = json.load(f)

with open(".agents/state/BUILD-STATE.json", "r") as f:
    s = json.load(f)

for m in ["X-221", "X-222", "X-223"]:
    if m not in s["modules"]:
        d = p["modules"][m]
        s["modules"][m] = {
            "status": "NOT_STARTED",
            "wave": d["wave"],
            "intent": d["intent"],
            "unresolved": []
        }
        w = str(d["wave"])
        if m not in s["waves"][w]["modules"]:
            s["waves"][w]["modules"].append(m)

s["roster"] = p["roster"]

with open(".agents/state/BUILD-STATE.json", "w") as f:
    json.dump(s, f, indent=1)

print("State updated")
