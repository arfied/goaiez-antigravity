#!/bin/bash
set -e

DIST="$1"
BASE="$2"

if [ ! -d "$DIST" ]; then
    echo "dist is not a directory"
    exit 2
fi

if ! [[ "$BASE" =~ ^/([A-Za-z0-9._-]+/)*$ ]]; then
    echo "invalid base path"
    exit 2
fi

if [[ "$BASE" == /assets/* ]]; then
    echo "base may not start with /assets/"
    exit 2
fi

if [ "$BASE" = "/" ]; then
    echo "flatten: host-root build, nothing to do"
else
    SRC="$DIST${BASE}assets"
    if [ -d "$SRC" ]; then
        mkdir -p "$DIST/assets"
        COUNT=$(find "$SRC" -type f | wc -l)
        cp -a "$SRC/." "$DIST/assets/"
        FIRST_SEG=$(echo "$BASE" | awk -F/ '{print $2}')
        rm -rf "$DIST/$FIRST_SEG"
        echo "flatten: moved $COUNT files"
    else
        echo "flatten: nothing to flatten"
    fi
fi

if [ -d "$DIST/../server" ]; then
    rm -rf "$DIST/../server"
    echo "flatten: removed dist/server"
fi
