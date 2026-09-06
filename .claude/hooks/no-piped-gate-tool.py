#!/usr/bin/env python3
"""Refuse a Bash call that pipes a gate tool into anything.

WHY THIS IS A HOOK AND NOT A LINE IN CLAUDE.md. It already is a line in
CLAUDE.md, and in every subagent dispatch prompt, and it still has to be
repeated every time — a rule that depends on being remembered is a rule that
gets forgotten by the one agent whose prompt nobody updated. This fails the
call instead, in every session and every subagent, at the moment it is broken.

WHAT PIPING ACTUALLY COSTS HERE. `--compact` output is a single JSON line, so
there is nothing to page through. Piping it makes the run hang, and it replaces
the test command's exit code with the exit code of whatever it was piped into —
so a red suite reports success. That second half is the dangerous one: it turns
a quality gate into a gate that always passes.

WHAT IS DELIBERATELY STILL ALLOWED:

  php artisan test --compact                     the normal form
  php artisan test --compact 2>&1                a redirect is not a pipe
  php artisan test --compact || fail "Tests"     `||` is control flow
  php artisan test --filter="a|b"                a pipe inside a quoted argument
  composer stan | tail -2                        a different command entirely

Reads a PreToolUse payload on stdin, prints a deny decision when it matches,
and exits 0 either way — a hook that crashes must not block unrelated work.
"""

import json
import re
import sys

# `artisan test`, allowing `php artisan`, `./artisan`, `@php artisan` and any
# amount of whitespace between the two words.
INVOCATION = re.compile(
    r"artisan\s+test\b"                # Laravel's own runner
    r"|vendor/bin/pest\b"             # THIS repo's suite — the sibling's needle missed it
    r"|vendor/bin/phpstan\b"          # section 6 tools: their rc is the verdict
    r"|vendor/bin/pint\b"
    r"|bin/supervise\.sh\b"           # the gate itself
)

# Where one shell statement ends and the next begins. `||` and `&&` are listed
# before a bare `|` can be looked for, so control flow never reads as a pipe.
SEPARATOR = re.compile(r";|&&|\|\||\n")

# Single- or double-quoted runs, removed before looking for a pipe so that a
# --filter regex carrying alternation is not mistaken for one.
QUOTED = re.compile(r"\"[^\"]*\"|'[^']*'")


def pipes_a_test_run(command: str) -> bool:
    """True when a `artisan test` invocation is piped into something."""
    for match in INVOCATION.finditer(command):
        statement = SEPARATOR.split(command[match.end():])[0]

        if "|" in QUOTED.sub("", statement):
            return True

    return False


def main() -> None:
    try:
        payload = json.load(sys.stdin)
    except (json.JSONDecodeError, ValueError):
        return

    tool_input = payload.get("tool_input", payload)
    command = tool_input.get("command", "") if isinstance(tool_input, dict) else ""

    if not isinstance(command, str) or not pipes_a_test_run(command):
        return

    print(json.dumps({
        "hookSpecificOutput": {
            "hookEventName": "PreToolUse",
            "permissionDecision": "deny",
            "permissionDecisionReason": (
                "Do not pipe a gate tool (pest, pint, phpstan, supervise.sh, "
                "artisan test). A pipe replaces the tool's exit code with the "
                "pipe target's, so a red suite — or a KILLED tool, which is worse "
                "— reports success, and a quality gate becomes a gate that always "
                "passes. Run it bare and read its rc. A redirect (`2>&1`), a file "
                "redirect and control flow (`||`) are fine; a pipe is not."
            ),
        }
    }))


if __name__ == "__main__":
    main()
