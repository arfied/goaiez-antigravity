#!/usr/bin/env bash
#
# Supervisor's coder dispatcher. Launches ONE coder run on the current
# KICKOFF.md, detached, logging to /home/goaiez/tmp/<coder>-<track>-run<N>.log.
#
#   bash .agents/supervisor/launch-coder.sh                 # auto-numbers, coder=agy
#   bash .agents/supervisor/launch-coder.sh --coder claude  # quota fallback
#   bash .agents/supervisor/launch-coder.sh --status        # probe only
#
# Refuses to start if a coder is already running (never two in one tree).
set -euo pipefail
cd "$(dirname "$0")/../.." || exit 1

PIDFILE=".agents/supervisor/coder.pid"

# Which coder. Antigravity is the default and stays the default; `--coder claude`
# is the owner's 2026-09-05 17:1x fallback for a SECOND consecutive death on
# "Individual quota reached", passed by hand-of-tick and recorded in REVIEWS.md.
# Anything other than the two names is refused rather than silently defaulted.
CODER=agy
if [ "${1:-}" = "--coder" ]; then
  case "${2:-}" in
    agy|claude) CODER="$2"; shift 2 ;;
    *) echo "REFUSED: --coder takes agy or claude (got '${2:-}')"; exit 1 ;;
  esac
fi

# Liveness probe. The bare script ALWAYS launches; it used to ignore every
# argument, which turned three supervisor "is the coder alive?" probes into
# accidental dispatches (runs 7, 8, 9). Any REMAINING argument reports and exits.
if [ "$#" -gt 0 ]; then
  if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
    echo "ALIVE: pid $(cat "$PIDFILE")"
  else
    echo "DEAD: no coder for this track (pidfile: $( [ -f "$PIDFILE" ] && cat "$PIDFILE" || echo none ))"
  fi
  echo "(probe only — no dispatch. Run with no arguments to launch.)"
  exit 0
fi

if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "REFUSED: this track's coder is already active (pid $(cat "$PIDFILE"))"
  exit 1
fi
[ -s .agents/supervisor/KICKOFF.md ] || { echo "REFUSED: KICKOFF.md missing or empty"; exit 1; }

# Push gate. The coder-bin `git` wrapper refuses `git push` unless GOAIEZ_PUSH_OK=1
# is in its environment (run 13's REFUSED line). A `push:` line in BRIEF.md is not
# read by anything unless it is exported here. The token is the bare word YES —
# "OPEN"/"open for <range>" does NOT arm it, which is what silently ate run 13's push.
# Written as an `if`, never `grep … && VAR=1`: under `set -euo pipefail` a failing
# AND-list is the last command of the script and aborts every non-push dispatch.
PUSH_OK=0
if grep -qE '^push:.*\bYES\b' .agents/supervisor/BRIEF.md 2>/dev/null; then
  PUSH_OK=1
fi
export GOAIEZ_PUSH_OK="$PUSH_OK"

# Run numbering is shared across BOTH coders, so a run number never names two
# runs: n advances past an agy log OR a claude log at that number.
n=1
TRACK=$(basename "$PWD")
while [ -e "/home/goaiez/tmp/agy-${TRACK}-run${n}.log" ] \
   || [ -e "/home/goaiez/tmp/claude-${TRACK}-run${n}.log" ]; do n=$((n+1)); done
LOG="/home/goaiez/tmp/${CODER}-${TRACK}-run${n}.log"

# `--setting-sources user` on the claude branch is load-bearing: it keeps THIS
# checkout's supervisor .claude/settings.json (which denies Edit/Write on app/**)
# out of the coder's permissions. The coder-bin git guard and the seal bind the
# claude coder exactly as they bind agy.
if [ "$CODER" = "claude" ]; then
  nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; timeout 8h /home/goaiez/.local/bin/claude -p "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --setting-sources user --output-format text < /dev/null > '"$LOG"' 2>&1; echo "CLAUDE_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
else
  nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
fi
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "LAUNCHED run $n coder=$CODER (pid $(cat "$PIDFILE")) log=$LOG GOAIEZ_PUSH_OK=$PUSH_OK"
else
  echo "LAUNCH FAILED — check $LOG"; exit 1
fi
