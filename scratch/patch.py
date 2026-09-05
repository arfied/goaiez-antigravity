import json

with open("build-plan.json", "r") as f:
    data = json.load(f)

data["X-221"] = {
    "intent": "GROW",
    "wave": 3,
    "domain_layer": False,
    "domain_because": "Eloquent model + Action class IS the aggregate here",
    "state": "not started",
    "owns_table": ["ad_report_imports", "ad_findings", "ad_plans"],
    "what": "The ads advisor: plans campaigns, writes ad copy and creative variants, analyses performance reports and coaches optimisation through the omni chat"
}

data["X-222"] = {
    "intent": "PROTECT",
    "wave": 2,
    "domain_layer": False,
    "domain_because": "Eloquent model + Action class IS the aggregate here",
    "state": "not started",
    "owns_table": ["legal_requests", "policy_pages", "retention_rules"],
    "what": "The legal and privacy engine: DMCA takedown requests, privacy requests, policy pages per tenant, retention rules."
}

data["X-223"] = {
    "intent": "GROW",
    "wave": 2,
    "domain_layer": False,
    "domain_because": "Eloquent model + Action class IS the aggregate here",
    "state": "not started",
    "owns_table": ["warmup_domains", "warmup_seeds", "warmup_days"],
    "what": "The email warm-up engine: a seed audience of platform-owned mailboxes, auto-reply simulation, important-tagging, and cross-platform sync."
}

with open("build-plan.json", "w") as f:
    json.dump(data, f, indent=1)
