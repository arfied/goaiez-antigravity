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

rm -rf "$TEST_DIR"
exit $FAIL
