#!/bin/bash
# A stand-in for webstudio-bridge/bin/publish-static.sh: same step lines, same artefact, no builder. Mode from FAKE_PUBLISHER_MODE.
set -e
PUB="$1"; MODE="${FAKE_PUBLISHER_MODE:-ok}"
[ -n "$WS_SHARE_LINK" ] || { echo "no share link" >&2; exit 3; }
[ -n "$PUBLISH_BASE" ] || { echo "no base" >&2; exit 4; }
for s in cache workdir link sync template scaffold deps build; do echo "## step $s"; done
if [ "$MODE" = "fail" ]; then echo "boom" >&2; exit 1; fi
mkdir -p "$PUB/dist/client/assets/static" "$PUB/evidence"
printf '<h1>Published 7733</h1>' > "$PUB/dist/client/index.html"
printf 'body{}' > "$PUB/dist/client/assets/static/s.css"
echo "## step flatten"; echo "## step done pages=1 bytes=25 dist=$PUB/dist/client"
