#!/bin/bash
set -e

if [ "$1" = "--dry-run" ]; then
    DRY_RUN=1
    shift
fi

URL="$1"
BUSINESS_ID="$2"

if [ -z "$URL" ] || [ -z "$BUSINESS_ID" ]; then
    echo "Usage: run-clone.sh [--dry-run] <url> <business_id>"
    exit 1
fi

if [ -z "$_WRAPPED" ] && [ "$DRY_RUN" != "1" ]; then
    export _WRAPPED=1
    exec timeout -k 60 1500 "$0" "$@"
fi

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/.."
LOCK_SHA=$(head -n 1 "$DIR/cloner.lock" | awk '{print $1}')
CACHE_DIR="/home/goaiez/.cache/goaiez-cloner/template-${LOCK_SHA:0:8}"
NODE24="/home/goaiez/.local/node-v24.21.0-linux-x64/bin"
SLUG=$(echo "$URL" | awk -F/ '{print $3}' | sed 's/^www\.//' | tr '.' '-' | tr '[:upper:]' '[:lower:]')
if [ -z "$SLUG" ]; then
    SLUG=$(echo "$URL" | tr '.' '-' | tr '[:upper:]' '[:lower:]')
fi

if [ "$DRY_RUN" = "1" ]; then
    WORKDIR="$DIR/.dry/clones/$BUSINESS_ID/$SLUG"
else
    WORKDIR="/home/goaiez/public_html/clones/$BUSINESS_ID/$SLUG"
fi

echo "## step setup workdir"
if [ "$DRY_RUN" = "1" ]; then
    mkdir -p "$DIR/.dry/clones/$BUSINESS_ID"
    echo "mkdir -p $WORKDIR/evidence/pages $WORKDIR/evidence/shots $WORKDIR/app"
else
    if [ ! -f "/home/goaiez/public_html/clones/.htaccess" ]; then
        echo "Require all denied" > "/home/goaiez/public_html/clones/.htaccess"
    else
        if [ "$(cat "/home/goaiez/public_html/clones/.htaccess")" != "Require all denied" ]; then
            echo "Error: /home/goaiez/public_html/clones/.htaccess differs"
            cat "/home/goaiez/public_html/clones/.htaccess"
            exit 1
        fi
    fi
    mkdir -p "$WORKDIR/evidence/pages"
    mkdir -p "$WORKDIR/evidence/shots"
    mkdir -p "$WORKDIR/app"
fi

echo "## step copy template"
if [ "$DRY_RUN" = "1" ]; then
    echo "cp -a $CACHE_DIR/. $WORKDIR/app/"
else
    cp -a "$CACHE_DIR/." "$WORKDIR/app/"
fi

echo "## step mcp.json"
MCP_JSON='{"mcpServers":{"playwright":{"command":"npx","args":["-y","@playwright/mcp@0.0.83","--headless","--browser","chromium"]}}}'
if [ "$DRY_RUN" = "1" ]; then
    echo "echo '$MCP_JSON' > $WORKDIR/mcp.json"
else
    echo "$MCP_JSON" > "$WORKDIR/mcp.json"
fi

echo "## step run claude"
CMD="claude -p \"/clone-website $URL\" --model claude-opus-5-5 --output-format stream-json --verbose --permission-mode dontAsk --allowedTools \"Bash,Read,Edit,Write,Glob,Grep,WebFetch,mcp__playwright__*\" --mcp-config $WORKDIR/mcp.json --strict-mcp-config"
if [ -f "$DIR/../.agents/supervisor/.spike.env" ]; then
    set -a; . "$DIR/../.agents/supervisor/.spike.env"; set +a
fi
if [ -n "$CLONE_BUDGET_USD" ]; then
    CMD="$CMD --max-budget-usd \"$CLONE_BUDGET_USD\""
fi
if [ "$DRY_RUN" = "1" ]; then
    echo "cd $WORKDIR/app && $CMD > ../evidence/claude.stream.jsonl"
else
    (cd "$WORKDIR/app" && eval "$CMD" > "../evidence/claude.stream.jsonl")
fi

echo "## step static render"
if [ "$DRY_RUN" = "1" ]; then
    echo "cd $WORKDIR/app"
    echo "PATH=$NODE24:\$PATH npm run build"
    echo "PATH=$NODE24:\$PATH npm run start -- -p <free_port> &"
    echo "node script to run Playwright, fetch routes, inline CSS, drop scripts, prepend inception mark -> evidence/pages/<route>.html"
    echo "Playwright: screenshots 1280 & 390 -> evidence/shots/"
else
    echo "Not fully implemented since coder never runs run-clone.sh for real"
fi

echo "## step html-to-bundle"
if [ "$DRY_RUN" = "1" ]; then
    echo "cd /home/goaiez/public_html/webstudio && pnpm exec tsx --conditions=webstudio $DIR/bin/html-to-bundle.ts $WORKDIR/evidence/pages $WORKDIR/evidence/bundle.json"
else
    cd /home/goaiez/public_html/webstudio && pnpm exec tsx --conditions=webstudio "$DIR/bin/html-to-bundle.ts" "$WORKDIR/evidence/pages" "$WORKDIR/evidence/bundle.json"
fi

echo "## step import"
if [ -n "$WS_SHARE_LINK" ]; then
    if [ "$DRY_RUN" = "1" ]; then
        echo "cd /home/goaiez/public_html/webstudio && node packages/cli/local.js link --link \"$WS_SHARE_LINK\""
        echo "cd /home/goaiez/public_html/webstudio && node packages/cli/local.js import"
    else
        cd /home/goaiez/public_html/webstudio && node packages/cli/local.js link --link "$WS_SHARE_LINK"
        cd /home/goaiez/public_html/webstudio && node packages/cli/local.js import
    fi
else
    echo "import: skipped (no WS_SHARE_LINK)"
fi

echo "## step report.md"
if [ "$DRY_RUN" = "1" ]; then
    echo "write evidence/report.md"
else
    echo "Report" > "$WORKDIR/evidence/report.md"
fi
