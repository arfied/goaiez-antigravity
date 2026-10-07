#!/bin/bash
set -e
NODE24="/home/goaiez/.local/node-v24.21.0-linux-x64/bin"
export PATH="$NODE24:$PATH"
WS_CACHE="${WS_CACHE:-/home/goaiez/.cache/goaiez-webstudio}"
DIR="$(cd "$(dirname "$0")/.." && pwd)"
VER="$(awk 'NR==1 {print $2}' "$DIR/cli.lock")"

# CLI
CLI_DIR="$WS_CACHE/cli-$VER"
if [ -f "$CLI_DIR/.ready" ] && [ "$(cat "$CLI_DIR/.ready")" = "$VER" ]; then
    echo "cli: ready"
else
    echo "cli: installing"
    rm -rf "$CLI_DIR"
    mkdir -p "$CLI_DIR"
    npm install --prefix "$CLI_DIR" "webstudio@$VER" --no-audit --no-fund < /dev/null
    V="$("$CLI_DIR/node_modules/.bin/webstudio" --version)"
    if [ "$V" != "$VER" ]; then
        exit 1
    fi
    echo "$VER" > "$CLI_DIR/.ready"
fi

# Deps
DEPS_DIR="$WS_CACHE/ssg-$VER"
if [ -f "$DEPS_DIR/.ready" ] && [ "$(cat "$DEPS_DIR/.ready")" = "$VER" ]; then
    echo "deps: ready"
else
    echo "deps: installing"
    rm -rf "$DEPS_DIR"
    mkdir -p "$DEPS_DIR"
    cp "$DIR/locks/ssg-$VER.package.json" "$DEPS_DIR/package.json"
    cp "$DIR/locks/ssg-$VER.package-lock.json" "$DEPS_DIR/package-lock.json"
    (cd "$DEPS_DIR" && npm ci --no-audit --no-fund < /dev/null)
    if [ ! -f "$DEPS_DIR/node_modules/.bin/vite" ] || [ ! -f "$DEPS_DIR/node_modules/.bin/vike" ]; then
        exit 1
    fi
    echo "$VER" > "$DEPS_DIR/.ready"
fi
