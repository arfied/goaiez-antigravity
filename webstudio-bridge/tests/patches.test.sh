#!/usr/bin/env bash
set -e

BRIDGE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

tmpdir=$(mktemp -d)
trap 'rm -rf "$tmpdir"' EXIT

cd "$tmpdir"
git init -q

mkdir -p apps/builder/app/builder/shared
mkdir -p apps/builder/app/builder/features/menu

cp "$BRIDGE_DIR/fixtures/patches/topbar.tsx" apps/builder/app/builder/shared/topbar.tsx
cp "$BRIDGE_DIR/fixtures/patches/menu.tsx" apps/builder/app/builder/features/menu/menu.tsx

git add -A
git -c user.email=t@t -c user.name=t commit -qm base

out=$(WS_CHECKOUT="$tmpdir" bash "$BRIDGE_DIR/bin/patches-check.sh")
if [[ "$out" != *"applies cleanly"* ]]; then
    echo "Check script failed to print applies cleanly: $out" >&2
    exit 1
fi

WS_CHECKOUT="$tmpdir" bash "$BRIDGE_DIR/bin/patches-apply.sh" >/dev/null

c=$(grep -c 'authToken === undefined' apps/builder/app/builder/shared/topbar.tsx || true)
if [ "$c" -ne 1 ]; then
    echo "Assertion failed: grep -c 'authToken === undefined' .../topbar.tsx = $c" >&2
    exit 1
fi

c=$(grep -c 'authToken === undefined' apps/builder/app/builder/features/menu/menu.tsx || true)
if [ "$c" -ne 2 ]; then
    echo "Assertion failed: grep -c 'authToken === undefined' .../menu.tsx = $c" >&2
    exit 1
fi

c=$(grep -c '<CloneButton />' apps/builder/app/builder/shared/topbar.tsx || true)
if [ "$c" -ne 1 ]; then
    echo "Assertion failed: grep -c '<CloneButton />' .../topbar.tsx = $c" >&2
    exit 1
fi

c=$(grep -c 'import { $authToken, $editingPageId, $editingTemplateId }' apps/builder/app/builder/shared/topbar.tsx || true)
if [ "$c" -ne 1 ]; then
    echo "Assertion failed: grep -c 'import { $authToken, $editingPageId, $editingTemplateId }' .../topbar.tsx = $c" >&2
    exit 1
fi

out=$(WS_CHECKOUT="$tmpdir" bash "$BRIDGE_DIR/bin/patches-check.sh")
if [[ "$out" != *"already applied"* ]]; then
    echo "Check script failed to print already applied: $out" >&2
    exit 1
fi

patch_file="$BRIDGE_DIR/patches/0001-hide-owner-chrome-for-token-sessions.patch"
if ! git apply --check --reverse "$patch_file"; then
    echo "Assertion failed: git apply --check --reverse <patch>" >&2
    exit 1
fi
echo "patches.test.sh PASSED"
