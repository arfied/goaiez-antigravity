#!/usr/bin/env bash
#
# The session preamble.  Run this ONCE at the start of a session, then work
# `python3 bin/state.py next` until it says FINISHED or STOP.
#
#   bash bin/loop.sh            # from the package root
#
# It does not build anything.  It establishes the two facts that decide whether
# any result you get afterwards means anything:
#
#   1. is the CHECKER sound?          (doctor:selftest)
#   2. is the seal digest unchanged?  (a sealed file changed, or arrived
#                                      outside the bundle - you cannot tell
#                                      which, and neither can the seal)
#
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1
ROOT="$PWD"
APP="$ROOT/app"

bar() { printf '\n\033[1m%s\033[0m\n' "$*"; }

bar "GOAIEZ — session preamble"

bar "0. are the load-bearing source files present?"
if ! python3 "$ROOT/bin/preflight.py"; then
  echo
  echo "  ⛔ STOP. Do not start building — see above."
  exit 1
fi

if [ ! -f "$APP/artisan" ]; then
  echo "  no Laravel tree at $APP — the loop will return BOOTSTRAP."
  echo
  python3 "$ROOT/bin/state.py" next
  exit 0
fi

cd "$APP" || exit 1

bar "1. is the CHECKER sound?"
if php artisan doctor:selftest; then
  python3 "$ROOT/bin/state.py" selftest sound
else
  python3 "$ROOT/bin/state.py" selftest problems
  echo
  echo "  ⛔ STOP. The problem is in the RUNTIME, not your code."
  echo "     app/Doctor is sealed — do not patch it."
  echo "     Re-run runtime/goaiez-runtime.sh, then try again."
  exit 1
fi

bar "2. the eight stages"
php artisan doctor || true

bar "3. where the build is"
python3 "$ROOT/bin/state.py" status

bar "4. what to do now"
python3 "$ROOT/bin/state.py" next

cat <<'TXT'

  Now work the loop:

      python3 bin/state.py next     # do what it says, then run it again

  It stops for four reasons and no others: FINISHED · RUNTIME · SEAL · STARVED.
  A red stage, a failing gate or a blocker is UNRESOLVED — record it, move on.

TXT
