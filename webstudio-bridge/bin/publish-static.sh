#!/bin/bash
set -e

DRY_RUN=0
if [ "$1" = "--dry-run" ]; then
    DRY_RUN=1
    shift
fi

PUB="$1"

if [ -z "$_WRAPPED" ] && [ "$DRY_RUN" != "1" ]; then
    export _WRAPPED=1
    exec timeout -k 60 900 "$0" "$@"
fi

if [ -z "$PUBLISH_BASE" ] || ! [[ "$PUBLISH_BASE" =~ ^/([A-Za-z0-9._-]+/)*$ ]]; then
    echo "invalid base path"
    exit 2
fi

if [ "$DRY_RUN" = "0" ] && [ -z "$WS_SHARE_LINK" ]; then
    echo "missing WS_SHARE_LINK"
    exit 2
fi

NODE24="/home/goaiez/.local/node-v24.21.0-linux-x64/bin"
export PATH="$NODE24:$PATH"
WS_CACHE="${WS_CACHE:-/home/goaiez/.cache/goaiez-webstudio}"
DIR="$(cd "$(dirname "$0")/.." && pwd)"
VER="$(awk 'NR==1 {print $2}' "$DIR/cli.lock")"

CLI="$WS_CACHE/cli-$VER/node_modules/.bin/webstudio"
TPL="$WS_CACHE/cli-$VER/node_modules/webstudio/templates/ssg"
DEPS="$WS_CACHE/ssg-$VER/node_modules"
TPLDIR="${PUB%/}.template"

echo "## step cache"
if [ "$DRY_RUN" = "1" ]; then
    echo "[ -f \"$WS_CACHE/cli-$VER/.ready\" ] && [ -f \"$WS_CACHE/ssg-$VER/.ready\" ]"
else
    if [ ! -f "$WS_CACHE/cli-$VER/.ready" ] || [ "$(cat "$WS_CACHE/cli-$VER/.ready")" != "$VER" ] || [ ! -f "$WS_CACHE/ssg-$VER/.ready" ] || [ "$(cat "$WS_CACHE/ssg-$VER/.ready")" != "$VER" ]; then
        echo "cache not ready — run bin/webstudio-cache.sh"
        exit 3
    fi
fi

echo "## step workdir"
if [ -d "$PUB/dist" ]; then
    echo "dist already exists"
    exit 2
fi
if [ -d "$TPLDIR" ]; then
    echo "template dir already exists"
    exit 2
fi
mkdir -p "$PUB"

echo "## step link"
# a background process group reading its terminal is stopped; @clack/prompts reads stdin when it is a TTY
if [ "$DRY_RUN" = "1" ]; then
    echo "(cd \"$PUB\" && $CLI link --link <redacted> < /dev/null)"
else
    (cd "$PUB" && NODE_TLS_REJECT_UNAUTHORIZED="${WS_INSECURE_TLS:+0}" "$CLI" link --link "$WS_SHARE_LINK" < /dev/null)
fi

echo "## step sync"
if [ "$DRY_RUN" = "1" ]; then
    echo "(cd \"$PUB\" && $CLI sync < /dev/null)"
else
    (cd "$PUB" && NODE_TLS_REJECT_UNAUTHORIZED="${WS_INSECURE_TLS:+0}" "$CLI" sync < /dev/null)
fi

echo "## step template"
if [ "$DRY_RUN" = "1" ]; then
    echo "bash \"$DIR/bin/publish-template.sh\" \"$TPL\" \"$TPLDIR\" \"$PUBLISH_BASE\""
else
    bash "$DIR/bin/publish-template.sh" "$TPL" "$TPLDIR" "$PUBLISH_BASE"
fi

echo "## step scaffold"
# options.template.includes("ssg"); vike scans every +*.ts(x)
if [ "$DRY_RUN" = "1" ]; then
    echo "(cd \"$PUB\" && $CLI build --template ssg --template \"$TPLDIR\" --assets < /dev/null)"
    echo "rm -rf \"$TPLDIR\""
else
    (cd "$PUB" && NODE_TLS_REJECT_UNAUTHORIZED="${WS_INSECURE_TLS:+0}" "$CLI" build --template ssg --template "$TPLDIR" --assets < /dev/null)
    rm -rf "$TPLDIR"
fi

echo "## step deps"
if [ "$DRY_RUN" = "1" ]; then
    echo "diff <(jq -S '.dependencies + .devDependencies' \"$PUB/package.json\") <(jq -S '.dependencies + .devDependencies' \"$WS_CACHE/ssg-$VER/package.json\")"
    echo "cp -al \"$DEPS\" \"$PUB/node_modules\""
else
    if ! diff <(jq -S '.dependencies + .devDependencies' "$PUB/package.json") <(jq -S '.dependencies + .devDependencies' "$WS_CACHE/ssg-$VER/package.json"); then
        echo "template dependencies changed — rebuild the cache"
        exit 4
    fi
    cp -al "$DEPS" "$PUB/node_modules"
fi

echo "## step build"
if [ "$DRY_RUN" = "1" ]; then
    echo "(cd \"$PUB\" && ./node_modules/.bin/vite build < /dev/null && ./node_modules/.bin/vike prerender < /dev/null)"
else
    (cd "$PUB" && ./node_modules/.bin/vite build < /dev/null && ./node_modules/.bin/vike prerender < /dev/null)
fi

echo "## step flatten"
if [ "$DRY_RUN" = "1" ]; then
    echo "bash \"$DIR/bin/publish-flatten.sh\" \"$PUB/dist/client\" \"$PUBLISH_BASE\""
else
    bash "$DIR/bin/publish-flatten.sh" "$PUB/dist/client" "$PUBLISH_BASE"
fi

echo "## step done"
if [ "$DRY_RUN" = "1" ]; then
    :
else
    PAGES=$(find "$PUB/dist/client" -name "index.html" | wc -l)
    BYTES=$(du -sb "$PUB/dist/client" | cut -f1)
    echo "pages=$PAGES bytes=$BYTES dist=$PUB/dist/client"
    mkdir -p "$PUB/evidence"
    DATE=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
    echo "{\"pages\":$PAGES,\"bytes\":$BYTES,\"base\":\"$PUBLISH_BASE\",\"cli\":\"$VER\",\"finished_at\":\"$DATE\"}" > "$PUB/evidence/publish.json"
fi
