import json

with open("build-plan.json", "r") as f:
    data = json.load(f)

for m in ["X-221", "X-222", "X-223"]:
    if m in data:
        data["modules"][m] = data.pop(m)

data["roster"] = 127
data["next_free_module"] = "X-224"
data["counts"]["modules"] = 127
data["roster_list"] = [m for m in data["modules"]]

with open("build-plan.json", "w") as f:
    json.dump(data, f, indent=1)
