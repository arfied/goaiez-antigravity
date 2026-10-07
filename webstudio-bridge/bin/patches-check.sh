#!/usr/bin/env bash
set -e

WS="${WS_CHECKOUT:-/home/goaiez/public_html/webstudio}"
BRIDGE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [ -z "$WS_CHECKOUT" ]; then
    EXPECTED_SHA=$(awk '{print $1; exit}' "$BRIDGE_DIR/webstudio.lock")
    ACTUAL_SHA=$(git -C "$WS" rev-parse --short HEAD)
    if [ "$EXPECTED_SHA" != "$ACTUAL_SHA" ]; then
        echo "Error: checkout SHA ($ACTUAL_SHA) does not match webstudio.lock ($EXPECTED_SHA)" >&2
        exit 2
    fi
fi

PATCHES_DIR="$BRIDGE_DIR/patches"
if [ ! -d "$PATCHES_DIR" ]; then
    exit 0
fi

# Sort in name order implicitly by shell glob
for p in "$PATCHES_DIR"/*.patch; do
    [ -e "$p" ] || continue
    name=$(basename "$p")
    if git -C "$WS" apply --check "$p" 2>/dev/null; then
        echo "patch $name: applies cleanly"
    elif git -C "$WS" apply --check --reverse "$p" 2>/dev/null; then
        echo "patch $name: already applied"
    else
        echo "patch $name: DOES NOT APPLY"
        exit 1
    fi
done
