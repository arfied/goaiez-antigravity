#!/bin/bash
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/.."
LOCK_SHA=$(head -n 1 "$DIR/cloner.lock" | awk '{print $1}')
CACHE_DIR="/home/goaiez/.cache/goaiez-cloner/template-${LOCK_SHA:0:8}"

if [ -f "$CACHE_DIR/.ready" ]; then
    if [ "$(cat "$CACHE_DIR/.ready")" = "$LOCK_SHA" ]; then
        echo "cached $LOCK_SHA"
        exit 0
    fi
fi

rm -rf "$CACHE_DIR"
git clone https://github.com/JCodesMore/ai-website-cloner-template.git "$CACHE_DIR"
cd "$CACHE_DIR"
git branch pinned "$LOCK_SHA"
git checkout pinned

CURRENT_SHA=$(git rev-parse HEAD)
if [ "$CURRENT_SHA" != "$LOCK_SHA" ]; then
    echo "Error: git rev-parse HEAD ($CURRENT_SHA) does not match lock ($LOCK_SHA)"
    exit 1
fi

export PATH="/home/goaiez/.local/node-v24.21.0-linux-x64/bin:$PATH"
npm ci

echo "$LOCK_SHA" > "$CACHE_DIR/.ready"
