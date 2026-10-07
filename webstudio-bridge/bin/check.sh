#!/bin/bash
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/.."

echo "Syntax checks..."
find "$DIR" -name "*.sh" -exec bash -n {} +

echo "Running html-to-bundle.test.sh..."
bash "$DIR/tests/html-to-bundle.test.sh"

echo "Running run-clone.sh dry run..."
bash "$DIR/bin/run-clone.sh" --dry-run https://example.com 0

echo "OK"
