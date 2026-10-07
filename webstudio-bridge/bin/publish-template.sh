#!/bin/bash
set -e

SRC="$1"
DST="$2"
BASE="$3"

if ! [[ "$BASE" =~ ^/([A-Za-z0-9._-]+/)*$ ]]; then
    echo "invalid base path"
    exit 2
fi

cp -a "$SRC/." "$DST/"

VITE_CONFIG="$DST/vite.config.ts"
if [ "$(grep -c 'export default defineConfig({' "$VITE_CONFIG")" -ne 1 ]; then
    exit 1
fi

python3 -c '
import sys
path = sys.argv[1]
base = sys.argv[2]
with open(path, "r") as f:
    lines = f.readlines()
with open(path, "w") as f:
    for line in lines:
        f.write(line)
        if line.startswith("export default defineConfig({"):
            f.write(f"  base: \"{base}\",\n")
' "$VITE_CONFIG" "$BASE"

if [ "$(grep -c "base: \"$BASE\"," "$VITE_CONFIG")" -ne 1 ]; then
    exit 1
fi

CONSTANTS="$DST/app/constants.mjs"
if [ "$(grep -c 'export const assetBaseUrl = "/assets/";' "$CONSTANTS")" -ne 1 ]; then
    exit 1
fi

python3 -c '
import sys
path = sys.argv[1]
base = sys.argv[2]
with open(path, "r") as f:
    content = f.read()
content = content.replace("export const assetBaseUrl = \"/assets/\";", f"export const assetBaseUrl = \"{base}assets/\";")
with open(path, "w") as f:
    f.write(content)
' "$CONSTANTS" "$BASE"

if [ "$(grep -c "export const assetBaseUrl = \"${BASE}assets/\";" "$CONSTANTS")" -ne 1 ]; then
    exit 1
fi
