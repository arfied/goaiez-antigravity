#!/usr/bin/env python3
"""Drive the anti-pipe hook over every arm. Run from the repo root.

Kept out of the Bash command line on purpose: the arms contain the very strings
the hook matches, so typing them into a shell command is itself refused.
"""
import json
import subprocess
import sys

HOOK = ".claude/hooks/no-piped-gate-tool.py"

DENY = [
    "./vendor/bin/pest | tail -5",
    "bash bin/supervise.sh --tests | tail -3",
    "php artisan test --compact | head",
    "./vendor/bin/pint --test | tail -3",
    "DB_DATABASE=x ./vendor/bin/pest | tail -1",
    "timeout 1800 ./vendor/bin/pest | tail -1",
    "git status && ./vendor/bin/phpstan analyse | tail -2",
]
ALLOW = [
    './vendor/bin/pest --filter="a|b"',
    "./vendor/bin/pest 2>&1",
    "./vendor/bin/pest || fail=1",
    "bash bin/supervise.sh --tests > out.log 2>&1",
    "composer stan | tail -2",
    "git log --oneline | head -5",
    "sed -n '1,5p' bin/supervise.sh | head -2",     # reading, not running
    "grep -n pest bin/supervise.sh | wc -l",         # the false positive that started this
    "ls -l vendor/bin/pest | awk '{print $5}'",
]


def decision(cmd: str) -> str:
    out = subprocess.run(
        [sys.executable, HOOK],
        input=json.dumps({"tool_name": "Bash", "tool_input": {"command": cmd}}),
        capture_output=True, text=True,
    ).stdout.strip()
    if not out:
        return "allowed"
    return json.loads(out)["hookSpecificOutput"]["permissionDecision"]


bad = 0
for cmd, want in [(c, "deny") for c in DENY] + [(c, "allowed") for c in ALLOW]:
    got = decision(cmd)
    ok = got == want
    bad += not ok
    print(f"{'ok  ' if ok else 'FAIL'} want={want:<7} got={got:<7} {cmd}")

print(f"\n{bad} arm(s) wrong" if bad else "\nall arms correct")
sys.exit(1 if bad else 0)
