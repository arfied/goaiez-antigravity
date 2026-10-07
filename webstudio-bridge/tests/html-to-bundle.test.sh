#!/bin/bash
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/.."
TEST_DIR="$DIR/.dry/test-$$"
mkdir -p "$TEST_DIR/pages"
cp "$DIR/fixtures/one-section.html" "$TEST_DIR/pages/"

cd /home/goaiez/public_html/webstudio
OUTPUT=$(pnpm exec tsx --conditions=webstudio "$DIR/bin/html-to-bundle.ts" "$TEST_DIR/pages" "$TEST_DIR/bundle.json")

echo "$OUTPUT"

INSTANCES=$(echo "$OUTPUT" | grep "instances:" | sed 's/.*instances: \([0-9]*\).*/\1/')
STYLES=$(echo "$OUTPUT" | grep "styles:" | sed 's/.*styles: \([0-9]*\).*/\1/')
BREAKPOINTS=$(echo "$OUTPUT" | grep "breakpoints:" | sed 's/.*breakpoints: \([0-9]*\).*/\1/')
SKIPPED=$(echo "$OUTPUT" | grep "skippedSelectors:" | sed 's/.*skippedSelectors: \([0-9]*\).*/\1/')

FAIL=0
if [ "$INSTANCES" -lt 3 ]; then echo "instances < 3"; FAIL=1; fi
if [ "$STYLES" -lt 2 ]; then echo "styles < 2"; FAIL=1; fi
if [ "$BREAKPOINTS" -lt 2 ]; then echo "breakpoints < 2"; FAIL=1; fi
if [ "$SKIPPED" -ne 0 ]; then echo "skipped != 0"; FAIL=1; fi

if ! echo "$OUTPUT" | grep -q "bundle: ok (schema)"; then echo "missing bundle: ok (schema)"; FAIL=1; fi

node -e "
const b = require('$TEST_DIR/bundle.json');
if (b.build.styles.length !== 20) {
  console.error('styles length is ' + b.build.styles.length + ' not 20');
  process.exit(1);
}
if (b.build.styles.some(s => s[0] === null)) {
  console.error('found null key in styles');
  process.exit(1);
}
" || FAIL=1

# New test for images
mkdir -p "$TEST_DIR/assets"
echo '<img src="abcdef12-pic.png" alt="pic">' > "$TEST_DIR/pages/pic.html"
echo '[{"url":"http://example.com/pic.png","name":"abcdef12-pic.png","mime":"image/png","size":100,"width":2,"height":2}]' > "$TEST_DIR/assets/manifest.json"

OUTPUT2=$(pnpm exec tsx --conditions=webstudio "$DIR/bin/html-to-bundle.ts" "$TEST_DIR/pages" "$TEST_DIR/bundle2.json" --assets-manifest "$TEST_DIR/assets/manifest.json" --project-id 00000000-0000-4000-8000-000000000000)

echo "$OUTPUT2"

if ! echo "$OUTPUT2" | grep -q "assets: 1 declared · 1 image props rebound · 0 left as strings"; then echo "missing assets print"; FAIL=1; fi
if ! echo "$OUTPUT2" | grep -q "bundle: ok (schema)"; then echo "missing bundle: ok (schema)"; FAIL=1; fi

node -e "
const b = require('$TEST_DIR/bundle2.json');
if (b.assets.length !== 1) {
  console.error('assets length ' + b.assets.length); process.exit(1);
}
const assetId = b.assets[0].id;
let foundProp = false;
for (const [id, prop] of b.build.props) {
  if (prop.name === 'src' && prop.type === 'asset' && prop.value === assetId) {
    foundProp = true;
  }
}
if (!foundProp) {
  console.error('rebound prop not found'); process.exit(1);
}
" || FAIL=1

rm -rf "$TEST_DIR"
exit $FAIL
