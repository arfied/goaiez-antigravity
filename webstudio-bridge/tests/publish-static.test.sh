#!/bin/bash
set -e

DIR="$(cd "$(dirname "$0")/.." && pwd)"
T=$(mktemp -d)
trap 'rm -rf "$T"' EXIT

echo "--- publish-static.test.sh ---"

# Set up dist tree
mkdir -p "$T/dist/client/sites/0/deploy_test/assets"
mkdir -p "$T/dist/client/assets/entries"
mkdir -p "$T/dist/server"
touch "$T/dist/client/index.html"
echo "pic bytes" > "$T/dist/client/sites/0/deploy_test/assets/pic.png"
touch "$T/dist/client/assets/entries/e.js"
touch "$T/dist/server/entry.mjs"

# Tests

# 1. publish-template.sh success
mkdir -p "$T/orig"
cp "$DIR/fixtures/publish/template/vite.config.ts" "$DIR/fixtures/publish/template/app/constants.mjs" "$T/orig/"
if ! bash "$DIR/bin/publish-template.sh" "$DIR/fixtures/publish/template" "$T/tpl" /sites/0/deploy_test/; then
    echo "publish-template.sh failed"
    exit 1
fi
if [ "$(grep -c 'base: "/sites/0/deploy_test/",' "$T/tpl/vite.config.ts")" -ne 1 ]; then
    echo "publish-template.sh: base not inserted"
    exit 1
fi
if [ "$(grep -c 'export const assetBaseUrl = "/sites/0/deploy_test/assets/";' "$T/tpl/app/constants.mjs")" -ne 1 ]; then
    echo "publish-template.sh: assetBaseUrl not replaced"
    exit 1
fi
if ! cmp "$DIR/fixtures/publish/template/vite.config.ts" "$T/orig/vite.config.ts" || ! cmp "$DIR/fixtures/publish/template/app/constants.mjs" "$T/orig/constants.mjs"; then
    echo "publish-template.sh: original modified"
    exit 1
fi

# 2. publish-template.sh bad base
if bash "$DIR/bin/publish-template.sh" "$DIR/fixtures/publish/template" "$T/tpl2" "bad-base" 2>/dev/null; then
    echo "publish-template.sh bad-base should exit 2"
    exit 1
fi

# 3. publish-template.sh missing needle
mkdir -p "$T/bad-tpl/app"
cp "$DIR/fixtures/publish/template/vite.config.ts" "$T/bad-tpl/"
echo "export const assetBaseUrl = '/wrong/';" > "$T/bad-tpl/app/constants.mjs"
if bash "$DIR/bin/publish-template.sh" "$T/bad-tpl" "$T/tpl3" /sites/0/deploy_test/ 2>/dev/null; then
    echo "publish-template.sh missing needle should exit 1"
    exit 1
fi

# 4. publish-flatten.sh
if ! bash "$DIR/bin/publish-flatten.sh" "$T/dist/client" /sites/0/deploy_test/; then
    echo "publish-flatten.sh failed"
    exit 1
fi
if [ ! -f "$T/dist/client/assets/pic.png" ]; then
    echo "publish-flatten.sh: pic.png not moved"
    exit 1
fi
if [ -d "$T/dist/client/sites" ]; then
    echo "publish-flatten.sh: sites/ not removed"
    exit 1
fi
if [ ! -f "$T/dist/client/assets/entries/e.js" ]; then
    echo "publish-flatten.sh: e.js missing"
    exit 1
fi
if [ -d "$T/dist/server" ]; then
    echo "publish-flatten.sh: server/ not removed"
    exit 1
fi

# 5. publish-flatten.sh second run
OUT=$(bash "$DIR/bin/publish-flatten.sh" "$T/dist/client" /sites/0/deploy_test/)
if [[ "$OUT" != *"nothing to flatten"* ]]; then
    echo "publish-flatten.sh second run didn't print nothing to flatten"
    exit 1
fi

# 6. publish-flatten.sh root base
if ! bash "$DIR/bin/publish-flatten.sh" "$T/dist/client" "/"; then
    echo "publish-flatten.sh root base failed"
    exit 1
fi

# 6.5 publish-flatten.sh /assets/ base
if bash "$DIR/bin/publish-flatten.sh" "$T/dist/client" "/assets/" 2>/dev/null; then
    echo "publish-flatten.sh /assets/ base should fail"
    exit 1
fi
if [ ! -f "$T/dist/client/assets/entries/e.js" ]; then
    echo "publish-flatten.sh /assets/ removed e.js"
    exit 1
fi

# 7. publish-static.sh --dry-run
OUT2=$(PUBLISH_BASE=/sites/0/deploy_test/ WS_SHARE_LINK=secret-value bash "$DIR/bin/publish-static.sh" --dry-run "$T/pub")
if [[ "$OUT2" != *"## step link"* ]] || [[ "$OUT2" != *"## step build"* ]] || [[ "$OUT2" != *"vike prerender"* ]] || [[ "$OUT2" != *"build --template"* ]]; then
    echo "publish-static.sh output missing expected strings"
    exit 1
fi
if [[ "$OUT2" == *"secret-value"* ]]; then
    echo "publish-static.sh leaked secret-value"
    exit 1
fi
if [[ "$OUT2" == *"npm "* ]]; then
    echo "publish-static.sh invoked npm"
    exit 1
fi
if [[ "$OUT2" == *"/home/goaiez/public_html/webstudio"* ]]; then
    echo "publish-static.sh touched webstudio source"
    exit 1
fi
if [[ "$OUT2" != *"--template ssg --template"* ]] || [[ "$OUT2" != *"pub.template"* ]]; then
    echo "publish-static.sh missing template strings"
    echo "$OUT2"
    exit 1
fi
if [[ "$OUT2" == *"pub/template"* ]]; then
    echo "publish-static.sh output contains pub/template"
    echo "$OUT2"
    exit 1
fi
if [ "$(echo "$OUT2" | grep -c '< /dev/null')" -lt 4 ]; then
    echo "publish-static.sh missing < /dev/null redirects, count: $(echo "$OUT2" | grep -c '< /dev/null')"
    exit 1
fi

echo "publish-static.test.sh PASSED"
