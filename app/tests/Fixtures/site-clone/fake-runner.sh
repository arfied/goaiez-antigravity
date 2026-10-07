#!/bin/bash
# A stand-in for webstudio-bridge/bin/run-clone.sh: same step lines, same artefacts, no Claude. Mode from FAKE_RUNNER_MODE.
set -e
URL="$1"; BIZ="$2"; MODE="${FAKE_RUNNER_MODE:-ok}"
SLUG=$(echo "$URL" | awk -F/ '{print $3}' | sed 's/^www\.//' | tr '.' '-' | tr '[:upper:]' '[:lower:]')
WORKDIR="$CLONE_ROOT/$BIZ/$SLUG"; mkdir -p "$WORKDIR/evidence/pages" "$WORKDIR/evidence/shots" "$WORKDIR/app"
echo "## step setup workdir"; echo "## step copy template"; echo "## step mcp.json"
echo "## step run claude"
[ -n "$ANTHROPIC_API_KEY" ] || { echo "no key" >&2; exit 3; }
printf '%s\n' '{"type":"assistant","message":{"content":[{"type":"tool_use","name":"mcp__playwright__browser_navigate","input":{}}]}}' '{"type":"assistant","message":{"content":[{"type":"tool_use","name":"Write","input":{}}]}}' '{"type":"result","total_cost_usd":0.1234,"duration_ms":1000}' > "$WORKDIR/evidence/claude.stream.jsonl"
if [ "$MODE" = "hang" ]; then sleep 600; fi
if [ "$MODE" = "fail" ]; then echo "boom" >&2; exit 1; fi
echo "## step static render"; echo "## step html-to-bundle"; echo '{"pages":[]}' > "$WORKDIR/evidence/bundle.json"
if [ "$MODE" = "ok" ] && [ "$WS_AUTO_PROJECT" = "1" ]; then
    echo '{"projectId":"fake-uuid-456","token":"fake-token-value-abc123"}' > "$WORKDIR/evidence/project.json"
fi
echo "## step import"; echo "import: skipped (no WS_SHARE_LINK)"; echo "## step report.md"
