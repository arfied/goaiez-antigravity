#!/bin/bash
set -e

DIR="$(cd "$(dirname "$0")/.." && pwd)"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/t-one" "$TMP/t-two"
cp "$DIR/fixtures/one-section.html" "$TMP/t-one/index.html"
cp "$DIR/fixtures/one-section.html" "$TMP/t-two/index.html"

cat << 'JSON' > "$TMP/templates.json"
{
  "t-one": { "label": "Template One" },
  "t-two": { "label": "Template Two" }
}
JSON

echo "{}" > "$TMP/map.json"

OUT=$(WS_AUTH_SECRET="secret-value" bash "$DIR/bin/templates-import.sh" --dry-run "$TMP" "$TMP/work" "$TMP/map.json" < /dev/null 2>&1)

# Asserts
if [ ! -f "$TMP/work/t-one/bundle.json" ]; then echo "bundle t-one missing"; exit 1; fi
if [ ! -f "$TMP/work/t-two/bundle.json" ]; then echo "bundle t-two missing"; exit 1; fi

node -e "require('fs').readFileSync('$TMP/work/t-one/bundle.json')" || { echo "bundle t-one invalid json"; exit 1; }
node -e "require('fs').readFileSync('$TMP/work/t-two/bundle.json')" || { echo "bundle t-two invalid json"; exit 1; }

echo "$OUT" | grep -q "## step t-one convert" || { echo "missing step t-one convert"; exit 1; }
echo "$OUT" | grep -q "## step t-two project" || { echo "missing step t-two project"; exit 1; }
echo "$OUT" | grep -q "import --to" || { echo "missing import --to"; exit 1; }
echo "$OUT" | grep -q -e "--skip-assets" || { echo "missing --skip-assets"; exit 1; }
echo "$OUT" | grep -q -e "--title" || { echo "missing --title"; exit 1; }
if echo "$OUT" | grep -q "secret-value"; then echo "leaked secret-value"; exit 1; fi
if echo "$OUT" | grep -q "authToken=[^ ]"; then echo "leaked authToken"; exit 1; fi
DEVNULL_COUNT=$(echo "$OUT" | grep -o "< /dev/null" | wc -l)
if [ "$DEVNULL_COUNT" -lt 4 ]; then echo "less than 4 < /dev/null"; exit 1; fi

# Seed map.json with t-one
cat << 'JSON' > "$TMP/map.json"
{
  "t-one": { "projectId": "123", "label": "Template One", "imported_at": "2026-10-07T00:00:00Z" }
}
JSON

OUT2=$(WS_AUTH_SECRET="secret-value" bash "$DIR/bin/templates-import.sh" --dry-run "$TMP" "$TMP/work" "$TMP/map.json" < /dev/null 2>&1)

echo "$OUT2" | grep -q "## step t-one skipped (already imported)" || { echo "missing skip t-one"; exit 1; }
echo "$OUT2" | grep -q "## step t-two convert" || { echo "missing still step t-two convert"; exit 1; }

echo "templates-import.test.sh PASSED"
