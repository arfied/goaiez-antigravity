#!/bin/bash
set -e

if [ "$_TIMEOUT_WRAPPED" != "1" ]; then
    export _TIMEOUT_WRAPPED=1
    exec timeout -k 60 3600 "$0" "$@"
fi

DRY_RUN=0
if [ "$1" = "--dry-run" ]; then
    DRY_RUN=1
    shift
fi

TEMPLATES_DIR="$1"
WORK_DIR="$2"
MAP_JSON="$3"

if [ -z "$TEMPLATES_DIR" ] || [ -z "$WORK_DIR" ] || [ -z "$MAP_JSON" ]; then
    echo "Usage: templates-import.sh [--dry-run] <templates-dir> <work-dir> <map.json>"
    exit 1
fi

DIR="$(cd "$(dirname "$0")/.." && pwd)"
NODE24="/home/goaiez/.local/node-v24.21.0-linux-x64/bin"
export PATH="$NODE24:$PATH"

if [ "$DRY_RUN" = "0" ] && { [ -z "$WS_BUILDER_ORIGIN" ] || [ -z "$WS_AUTH_SECRET" ]; }; then
    echo "Missing WS_BUILDER_ORIGIN or WS_AUTH_SECRET"
    exit 1
fi

if [ ! -f "$MAP_JSON" ]; then
    echo "{}" > "$MAP_JSON"
fi

for id in $(jq -r 'keys[]' "$TEMPLATES_DIR/templates.json"); do
    if jq -e --arg id "$id" 'has($id)' "$MAP_JSON" > /dev/null; then
        echo "## step $id skipped (already imported)"
        continue
    fi

    label=$(jq -r --arg id "$id" '.[$id].label' "$TEMPLATES_DIR/templates.json")

    echo "## step $id convert"
    mkdir -p "$WORK_DIR/$id"
    echo "cd /home/goaiez/public_html/webstudio && pnpm exec tsx --conditions=webstudio \"$DIR/bin/html-to-bundle.ts\" \"$TEMPLATES_DIR/$id\" \"$WORK_DIR/$id/bundle.json\" --title \"$label\" < /dev/null"
    (cd /home/goaiez/public_html/webstudio && pnpm exec tsx --conditions=webstudio "$DIR/bin/html-to-bundle.ts" "$TEMPLATES_DIR/$id" "$WORK_DIR/$id/bundle.json" --title "$label" < /dev/null)

    echo "## step $id project"
    if [ "$DRY_RUN" = "1" ]; then
        echo "WS_PROJECT_TITLE=\"Template — $label\" node \"$DIR/bin/project.mjs\" > \"$WORK_DIR/$id/project.json\" < /dev/null"
        echo "## step $id import"
        echo "mkdir -p \"$WORK_DIR/$id/ws/.webstudio\" && cp \"$WORK_DIR/$id/bundle.json\" \"$WORK_DIR/$id/ws/.webstudio/data.json\""
        echo "(cd \"$WORK_DIR/$id/ws\" && NODE_TLS_REJECT_UNAUTHORIZED=\"\${WS_INSECURE_TLS:+0}\" node /home/goaiez/public_html/webstudio/packages/cli/local.js link --link \"\$SHARE\" < /dev/null)"
        echo "(cd \"$WORK_DIR/$id/ws\" && NODE_TLS_REJECT_UNAUTHORIZED=\"\${WS_INSECURE_TLS:+0}\" node /home/goaiez/public_html/webstudio/packages/cli/local.js import --to \"\$SHARE\" --skip-assets < /dev/null)"
        
        jq --arg id "$id" --arg lbl "$label" \
           '.[$id] = {"projectId": "<uuid>", "label": $lbl, "imported_at": "<ISO>"}' "$MAP_JSON" > "$MAP_JSON.tmp"
        mv "$MAP_JSON.tmp" "$MAP_JSON"
    else
        WS_PROJECT_TITLE="Template — $label" node "$DIR/bin/project.mjs" > "$WORK_DIR/$id/project.json" < /dev/null
        
        echo "## step $id import"
        SHARE=$(jq -r .shareLink "$WORK_DIR/$id/project.json")
        PROJECT_ID=$(jq -r .projectId "$WORK_DIR/$id/project.json")
        
        mkdir -p "$WORK_DIR/$id/ws/.webstudio"
        cp "$WORK_DIR/$id/bundle.json" "$WORK_DIR/$id/ws/.webstudio/data.json"
        
        (cd "$WORK_DIR/$id/ws" && NODE_TLS_REJECT_UNAUTHORIZED="${WS_INSECURE_TLS:+0}" node /home/goaiez/public_html/webstudio/packages/cli/local.js link --link "$SHARE" < /dev/null)
        (cd "$WORK_DIR/$id/ws" && NODE_TLS_REJECT_UNAUTHORIZED="${WS_INSECURE_TLS:+0}" node /home/goaiez/public_html/webstudio/packages/cli/local.js import --to "$SHARE" --skip-assets < /dev/null)
        
        NOW=$(date -Iseconds)
        jq --arg id "$id" --arg pid "$PROJECT_ID" --arg lbl "$label" --arg now "$NOW" \
           '.[$id] = {"projectId": $pid, "label": $lbl, "imported_at": $now}' "$MAP_JSON" > "$MAP_JSON.tmp"
        mv "$MAP_JSON.tmp" "$MAP_JSON"
    fi
done
