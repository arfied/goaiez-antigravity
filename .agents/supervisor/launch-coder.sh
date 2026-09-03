#!/usr/bin/env bash
#
# Supervisor's coder dispatcher. Launches ONE Antigravity run on the current
# KICKOFF.md, detached, logging to /home/goaiez/tmp/agy-run<N>.log.
#
#   bash .agents/supervisor/launch-coder.sh          # auto-numbers the run
#   bash .agents/supervisor/launch-coder.sh --status # liveness ONLY, never launches
#
# Refuses to start if a coder is already running (never two in one tree).
#
# ⚠️ Every flag other than --status is IGNORED and still dispatches a real run.
# An unattended tick that wants case (a) must use --status: on 2026-09-02 a tick
# probed with an invented `--probe-only` and launched run 9 by accident.
set -euo pipefail
cd "$(dirname "$0")/../.." || exit 1

PIDFILE=".agents/supervisor/coder.pid"

if [ "${1:-}" = "--status" ]; then
  if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
    echo "ALIVE $(cat "$PIDFILE")"
  else
    echo "DEAD"
  fi
  exit 0
fi

if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "REFUSED: this track's coder is already active (pid $(cat "$PIDFILE"))"
  exit 1
fi
[ -s .agents/supervisor/KICKOFF.md ] || { echo "REFUSED: KICKOFF.md missing or empty"; exit 1; }

n=1
TRACK=$(basename "$PWD")
while [ -e "/home/goaiez/tmp/agy-${TRACK}-run${n}.log" ]; do n=$((n+1)); done
LOG="/home/goaiez/tmp/agy-${TRACK}-run${n}.log"

nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "LAUNCHED run $n (pid $(cat "$PIDFILE")) log=$LOG"
else
  echo "LAUNCH FAILED — check $LOG"; exit 1
fi
