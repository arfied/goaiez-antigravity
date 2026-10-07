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
