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

CLONE_ROOT="${CLONE_ROOT:-/home/goaiez/public_html/clones}"

if [ "$DRY_RUN" = "1" ]; then
    WORKDIR="$DIR/.dry/clones/$BUSINESS_ID/$SLUG"
else
    WORKDIR="$CLONE_ROOT/$BUSINESS_ID/$SLUG"
fi

echo "## step setup workdir"
if [ "$DRY_RUN" = "1" ]; then
    mkdir -p "$DIR/.dry/clones/$BUSINESS_ID"
    echo "mkdir -p $CLONE_ROOT"
    echo "mkdir -p $WORKDIR/evidence/pages $WORKDIR/evidence/shots $WORKDIR/app"
else
    mkdir -p "$CLONE_ROOT"
    if [ ! -f "$CLONE_ROOT/.htaccess" ]; then
        echo "Require all denied" > "$CLONE_ROOT/.htaccess"
    else
        if [ "$(cat "$CLONE_ROOT/.htaccess")" != "Require all denied" ]; then
            echo "Error: $CLONE_ROOT/.htaccess differs"
            cat "$CLONE_ROOT/.htaccess"
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
CMD=(claude -p "/clone-website $URL" --model claude-opus-5-5 --output-format stream-json --verbose --permission-mode dontAsk --allowedTools "Bash,Read,Edit,Write,Glob,Grep,WebFetch,mcp__playwright__*" --mcp-config "$WORKDIR/mcp.json" --strict-mcp-config)
if [ -f "$DIR/../.agents/supervisor/.spike.env" ]; then
    set -a; . "$DIR/../.agents/supervisor/.spike.env"; set +a
fi
if [ -n "$CLONE_BUDGET_USD" ]; then
    CMD+=(--max-budget-usd "$CLONE_BUDGET_USD")
fi
if [ "$DRY_RUN" = "1" ]; then
    echo "cd $WORKDIR/app && ${CMD[*]} > ../evidence/claude.stream.jsonl"
else
    (cd "$WORKDIR/app" && "${CMD[@]}" > "../evidence/claude.stream.jsonl")
fi

echo "## step static render"
if [ "$DRY_RUN" = "1" ]; then
    echo "cd $WORKDIR/app"
    echo "PATH=$NODE24:\$PATH npm run build"
    echo "PORT=\$(node -e 'const s=require(\"net\").createServer().listen(0,()=>{console.log(s.address().port);s.close()})')"
    echo "PATH=$NODE24:\$PATH npm run start -- -p \$PORT &"
    echo "find src/app -name page.tsx | awk -F/ '{ route = \"/\"; skip = 0; for (i=3; i<NF; i++) { if (\$i ~ /\\(.*\\)/ || \$i ~ /\\[.*\\]/) { skip = 1; break; } if (route == \"/\") route = route \$i; else route = route \"/\" \$i; } if (!skip) print route; }' > ../evidence/routes.txt"
    echo "node \"$DIR/bin/render-pages.mjs\" --base \"http://127.0.0.1:\$PORT\" --routes \"../evidence/routes.txt\" --pages \"../evidence/pages\" --shots \"../evidence/shots\" --source \"$URL\""
else
    cd "$WORKDIR/app"
    PATH=$NODE24:$PATH npm run build
    PORT=$(node -e 'const s=require("net").createServer().listen(0,()=>{console.log(s.address().port);s.close()})')
    PATH=$NODE24:$PATH npm run start -- -p $PORT &
    SERVER_PID=$!

    function write_report {
        kill $SERVER_PID || true
        cd "$WORKDIR"
        echo "# Clone Report" > "evidence/report.md"
        echo "Wall time: $SECONDS seconds" >> "evidence/report.md"
        if [ -f "evidence/claude.stream.jsonl" ]; then
            grep '"type":"result"' "evidence/claude.stream.jsonl" | tail -n 1 >> "evidence/report.md" || true
        fi
        echo "## Converter Output" >> "evidence/report.md"
        if [ -f "evidence/convert.out" ]; then cat "evidence/convert.out" >> "evidence/report.md"; fi
        echo "## Screenshots" >> "evidence/report.md"
        ls -1 "evidence/shots/"*.png 2>/dev/null >> "evidence/report.md" || true
        echo "## Skipped" >> "evidence/report.md"
        if [ -z "$WS_SHARE_LINK" ]; then echo "import: skipped (no WS_SHARE_LINK)" >> "evidence/report.md"; fi
    }
    trap write_report EXIT

    timeout 60 bash -c "until curl -s -o /dev/null http://127.0.0.1:$PORT/; do sleep 1; done" || { echo "Server failed to start"; exit 1; }

    find src/app -name page.tsx | awk -F/ '{
        route = "/";
        skip = 0;
        for (i=3; i<NF; i++) {
            if ($i ~ /\(.*\)/ || $i ~ /\[.*\]/) { skip = 1; break; }
            if (route == "/") route = route $i;
            else route = route "/" $i;
        }
        if (!skip) print route;
    }' > ../evidence/routes.txt

    node "$DIR/bin/render-pages.mjs" --base "http://127.0.0.1:$PORT" --routes "../evidence/routes.txt" --pages "../evidence/pages" --shots "../evidence/shots" --source "$URL"
    cd "$DIR/.."
fi

echo "## step html-to-bundle"
MANIFEST_ARG=""
if [ -s "$WORKDIR/evidence/assets/manifest.json" ]; then
    MANIFEST_ARG="--assets-manifest $WORKDIR/evidence/assets/manifest.json --project-id 00000000-0000-4000-8000-000000000000"
fi

if [ "$DRY_RUN" = "1" ]; then
    echo "cd /home/goaiez/public_html/webstudio && pnpm exec tsx --conditions=webstudio $DIR/bin/html-to-bundle.ts $WORKDIR/evidence/pages $WORKDIR/evidence/bundle.json $MANIFEST_ARG > $WORKDIR/evidence/convert.out"
else
    cd /home/goaiez/public_html/webstudio && pnpm exec tsx --conditions=webstudio "$DIR/bin/html-to-bundle.ts" "$WORKDIR/evidence/pages" "$WORKDIR/evidence/bundle.json" $MANIFEST_ARG > "$WORKDIR/evidence/convert.out"
fi

if [ -z "$WS_SHARE_LINK" ] && [ "$WS_AUTO_PROJECT" = "1" ] && [ -n "$WS_AUTH_SECRET" ]; then
    echo "## step project creation"
    if [ "$DRY_RUN" = "1" ]; then
        echo "WS_PROJECT_TITLE=\"$SLUG\" node \"$DIR/bin/project.mjs\" > \"$WORKDIR/evidence/project.json\""
    else
        WS_PROJECT_TITLE="$SLUG" node "$DIR/bin/project.mjs" > "$WORKDIR/evidence/project.json"
        export WS_SHARE_LINK=$(node -e "console.log(require('$WORKDIR/evidence/project.json').shareLink)")
    fi
fi

echo "## step import"
if [ -n "$WS_SHARE_LINK" ]; then
    WS="$WORKDIR/ws"
    if [ "$DRY_RUN" = "1" ]; then
        echo "WS=\"$WORKDIR/ws\""
        echo "mkdir -p \"$WORKDIR/ws/.webstudio\""
        echo "cp \"$WORKDIR/evidence/bundle.json\" \"$WORKDIR/ws/.webstudio/data.json\""
        if [ -s "$WORKDIR/evidence/assets/manifest.json" ]; then
            echo "mkdir -p \"$WS/.webstudio/assets\" && cp \"$WORKDIR/evidence/assets/\"*.* \"$WS/.webstudio/assets/\""
            echo "(cd \"$WS\" && NODE_TLS_REJECT_UNAUTHORIZED=\"\${WS_INSECURE_TLS:+0}\" node /home/goaiez/public_html/webstudio/packages/cli/local.js import --to \"$WS_SHARE_LINK\")"
        else
            echo "(cd \"$WS\" && NODE_TLS_REJECT_UNAUTHORIZED=\"\${WS_INSECURE_TLS:+0}\" node /home/goaiez/public_html/webstudio/packages/cli/local.js import --to \"$WS_SHARE_LINK\" --skip-assets)"
        fi
    else
        mkdir -p "$WORKDIR/ws/.webstudio"
        cp "$WORKDIR/evidence/bundle.json" "$WORKDIR/ws/.webstudio/data.json"
        if [ -s "$WORKDIR/evidence/assets/manifest.json" ]; then
            mkdir -p "$WS/.webstudio/assets" && cp "$WORKDIR/evidence/assets/"*.* "$WS/.webstudio/assets/" || true
            (cd "$WS" && NODE_TLS_REJECT_UNAUTHORIZED="${WS_INSECURE_TLS:+0}" node /home/goaiez/public_html/webstudio/packages/cli/local.js import --to "$WS_SHARE_LINK")
        else
            (cd "$WS" && NODE_TLS_REJECT_UNAUTHORIZED="${WS_INSECURE_TLS:+0}" node /home/goaiez/public_html/webstudio/packages/cli/local.js import --to "$WS_SHARE_LINK" --skip-assets)
        fi
    fi
else
    echo "import: skipped (no WS_SHARE_LINK)"
fi

echo "## step report.md"
if [ "$DRY_RUN" = "1" ]; then
    echo "write evidence/report.md"
else
    echo "Report is written via EXIT trap"
fi
