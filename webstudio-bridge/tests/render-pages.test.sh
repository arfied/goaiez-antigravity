#!/bin/bash
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/.."
TEST_DIR="$DIR/.dry/test-render-$$"
mkdir -p "$TEST_DIR/pages" "$TEST_DIR/shots"

# find a free port
PORT=$(node -e 'const s=require("net").createServer().listen(0,()=>{console.log(s.address().port);s.close()})')

python3 -m http.server "$PORT" --bind 127.0.0.1 --directory "$DIR/fixtures/render" &
SERVER_PID=$!

trap 'kill $SERVER_PID || true; rm -rf "$TEST_DIR"' EXIT

# wait for server
for i in {1..30}; do
  if curl -s -o /dev/null "http://127.0.0.1:$PORT/"; then break; fi
  sleep 0.1
done

echo "/" > "$TEST_DIR/routes.txt"

node "$DIR/bin/render-pages.mjs" --base "http://127.0.0.1:$PORT" --routes "$TEST_DIR/routes.txt" --pages "$TEST_DIR/pages" --shots "$TEST_DIR/shots"

FAIL=0
OUT_FILE="$TEST_DIR/pages/index.html"

if ! grep -q "@webstudio/inception/1" "$OUT_FILE"; then echo "missing inception comment"; FAIL=1; fi
if ! grep -q "color:red" "$OUT_FILE"; then echo "missing css content"; FAIL=1; fi
if grep -q "<script" "$OUT_FILE"; then echo "script tag still present"; FAIL=1; fi

if [ ! -f "$TEST_DIR/shots/clone-index-1280.png" ]; then echo "missing 1280 shot"; FAIL=1; fi
if [ ! -f "$TEST_DIR/shots/clone-index-390.png" ]; then echo "missing 390 shot"; FAIL=1; fi

MANIFEST="$TEST_DIR/assets/manifest.json"
if [ ! -f "$MANIFEST" ]; then echo "missing manifest.json"; FAIL=1; fi

ASSET_NAME=$(node -e "const m=require('$MANIFEST'); console.log(m[0].name)")
if [[ ! "$ASSET_NAME" == *-pic.png ]]; then echo "wrong asset name: $ASSET_NAME"; FAIL=1; fi

if ! node -e "const m=require('$MANIFEST'); if(m.length!==1 || m[0].width!==2 || m[0].height!==2) process.exit(1)" ; then
  echo "manifest contents wrong"; FAIL=1;
fi

if ! grep -q "<img src=\"$ASSET_NAME\"" "$OUT_FILE"; then echo "HTML img src not rewritten to $ASSET_NAME"; FAIL=1; fi

if [ ! -f "$TEST_DIR/assets/$ASSET_NAME" ]; then echo "saved image file missing"; FAIL=1; fi

ORIG_SIZE=$(stat -c%s "$DIR/fixtures/render/pic.png")
SAVED_SIZE=$(stat -c%s "$TEST_DIR/assets/$ASSET_NAME")
if [ "$ORIG_SIZE" -ne "$SAVED_SIZE" ]; then echo "size mismatch: $ORIG_SIZE vs $SAVED_SIZE"; FAIL=1; fi

if [ "$FAIL" -ne 0 ]; then
  echo "Test failed!"
  exit 1
fi

echo "Render test passed."
