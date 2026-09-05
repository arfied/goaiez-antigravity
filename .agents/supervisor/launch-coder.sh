#!/usr/bin/env bash
#
# Supervisor's coder dispatcher. Launches ONE Antigravity run on the current
# KICKOFF.md, detached, logging to /home/goaiez/tmp/agy-run<N>.log.
#
#   bash .agents/supervisor/launch-coder.sh          # auto-numbers the run
#
# Refuses to start if a coder is already running (never two in one tree).
set -euo pipefail
cd "$(dirname "$0")/../.." || exit 1

PIDFILE=".agents/supervisor/coder.pid"
if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "REFUSED: this track's coder is already active (pid $(cat "$PIDFILE"))"
  exit 1
fi
[ -s .agents/supervisor/KICKOFF.md ] || { echo "REFUSED: KICKOFF.md missing or empty"; exit 1; }

# The push gate. coder-bin/git's `push` rule wants GOAIEZ_PUSH_OK=1 and reads it
# from the environment; it is exported here ONLY when BRIEF.md's `push:` line
# says YES (rule 10: the BRIEF's push line is the gate). Added 2026-09-04 21:1x
# after run 26 was refused for want of it (REVIEWS.md, the 21:1x block).
GOAIEZ_PUSH_OK=0
if grep -qiE '^push:[[:space:]]*\**[[:space:]]*yes' .agents/supervisor/BRIEF.md; then GOAIEZ_PUSH_OK=1; fi
export GOAIEZ_PUSH_OK
echo "push gate: GOAIEZ_PUSH_OK=$GOAIEZ_PUSH_OK (from BRIEF.md's push: line)"

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
