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
# ⚠️ Only two invocations exist: no argument (dispatch) and --status (liveness).
# ANY other argument is refused without launching. History: on 2026-09-02 a tick
# probed with an invented `--probe-only` and launched run 9 by accident; on
# 2026-09-03 tick 123 probed with `--help` and launched run 17 the same way.
# An unattended tick that wants case (a) must use --status.
set -euo pipefail
cd "$(dirname "$0")/../.." || exit 1

PIDFILE=".agents/supervisor/coder.pid"

if [ $# -gt 0 ] && [ "${1:-}" != "--status" ]; then
  echo "REFUSED: unknown argument '$1' — this script takes no argument (dispatch) or --status (liveness). Nothing launched."
  exit 1
fi

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

# The coder-bin git wrapper refuses `git push` unless GOAIEZ_PUSH_OK=1, and its own
# message says the launcher sets it from the BRIEF. That line was never wired here,
# so every push brief on this track was refused at the wrapper (tick 248, reviews
# run 14). The gate lives in one place: BRIEF.md's `push:` line must contain YES.
# `set -e` would abort the whole launcher on a bare `grep … && PUSH_OK=1` miss, so
# this is an if, not an AND-list: a brief that does not say YES must still dispatch.
PUSH_OK=0
if grep -qE '^push:.*\bYES\b' .agents/supervisor/BRIEF.md 2>/dev/null; then
  PUSH_OK=1
fi

n=1
TRACK=$(basename "$PWD")
while [ -e "/home/goaiez/tmp/agy-${TRACK}-run${n}.log" ]; do n=$((n+1)); done
LOG="/home/goaiez/tmp/agy-${TRACK}-run${n}.log"

nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; export GOAIEZ_PUSH_OK='"$PUSH_OK"'; /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "LAUNCHED run $n (pid $(cat "$PIDFILE")) log=$LOG GOAIEZ_PUSH_OK=$PUSH_OK"
else
  echo "LAUNCH FAILED — check $LOG"; exit 1
fi
