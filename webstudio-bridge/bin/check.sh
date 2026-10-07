#!/bin/bash
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/.."

echo "Checking owner checkout..."
if [ -n "$(git -C /home/goaiez/public_html/webstudio status --short)" ]; then
    echo "Error: Webstudio checkout is dirty before check"
    exit 1
fi

echo "Syntax checks..."
find "$DIR" -name "*.sh" -exec bash -n {} +

echo "Running html-to-bundle.test.sh..."
bash "$DIR/tests/html-to-bundle.test.sh"

echo "Running render-pages.test.sh..."
bash "$DIR/tests/render-pages.test.sh"

echo "Running run-clone.sh dry run..."
WS_SHARE_LINK=dummy bash "$DIR/bin/run-clone.sh" --dry-run https://example.com 0 > "$DIR/.dry/run-clone.out"

if ! grep -q 'ws/.webstudio/data.json' "$DIR/.dry/run-clone.out"; then
    echo "Error: Output does not contain ws/.webstudio/data.json"
    exit 1
fi

CLONES_MKDIR_LINE=$(grep -n 'mkdir -p /home/goaiez/public_html/clones' "$DIR/.dry/run-clone.out" | cut -d: -f1)
EVIDENCE_MKDIR_LINE=$(grep -n 'mkdir -p .*evidence/pages' "$DIR/.dry/run-clone.out" | head -n 1 | cut -d: -f1)
if [ -z "$CLONES_MKDIR_LINE" ] || [ -z "$EVIDENCE_MKDIR_LINE" ] || [ "$CLONES_MKDIR_LINE" -ge "$EVIDENCE_MKDIR_LINE" ]; then
    echo "Error: mkdir -p clones must precede mkdir -p evidence/pages"
    exit 1
fi

if grep -q 'cd /home/goaiez/public_html/webstudio && node packages/cli' "$DIR/.dry/run-clone.out"; then
    echo "Error: Output contains cd /home/goaiez/public_html/webstudio && node packages/cli"
    exit 1
fi

echo "Checking owner checkout again..."
if [ -n "$(git -C /home/goaiez/public_html/webstudio status --short)" ]; then
    echo "Error: Webstudio checkout is dirty after check"
    exit 1
fi

echo "OK"

echo "Running run-clone.sh dry run (auto project)..."
WS_AUTO_PROJECT=1 WS_AUTH_SECRET=dummy bash "$DIR/bin/run-clone.sh" --dry-run https://example.com 0 > "$DIR/.dry/run-clone-auto.out"
if ! grep -q 'project.mjs' "$DIR/.dry/run-clone-auto.out"; then
    echo "Error: Output does not contain project.mjs"
    exit 1
fi

echo "Running run-clone.sh dry run (link)..."
WS_SHARE_LINK='https://example.invalid/?authToken=x' bash "$DIR/bin/run-clone.sh" --dry-run https://example.com 0 > "$DIR/.dry/run-clone-link.out"

if ! grep -q 'import --to' "$DIR/.dry/run-clone-link.out" || ! grep -q -e '--skip-assets' "$DIR/.dry/run-clone-link.out" || grep -q ' link --link' "$DIR/.dry/run-clone-link.out"; then
    echo "Error: Output must contain import --to and --skip-assets and NOT contain link --link"
    exit 1
fi
