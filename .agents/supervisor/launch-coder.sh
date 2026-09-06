#!/usr/bin/env bash
#
# Supervisor's coder dispatcher. Launches ONE Antigravity run on the current
# KICKOFF.md, detached, logging to /home/goaiez/tmp/agy-run<N>.log.
#
#   bash .agents/supervisor/launch-coder.sh                 # Antigravity (default)
#   bash .agents/supervisor/launch-coder.sh --coder claude  # Claude Code as the coder
#                                                           # (owner 2026-09-05: the
#                                                           # default account; only
#                                                           # when agy reports
#                                                           # "quota reached")
#   bash .agents/supervisor/launch-coder.sh --status        # probe only, never dispatches
#
# Refuses to start if a coder is already running (never two in one tree).
set -euo pipefail
cd "$(dirname "$(readlink -f "$0")")/../.." || exit 1

PIDFILE=".agents/supervisor/coder.pid"

CODER=agy
ALLOW_MERGE=0
STATUS_ONLY=0
while [ $# -gt 0 ]; do
  case "$1" in
    --coder) CODER="${2:?--coder takes agy or claude}"; shift 2;;
    --allow-merge) ALLOW_MERGE=1; shift;;   # opens the shared coder guard's merge gate (GOAIEZ_MERGE_OK=1) for THIS run only; the guard added it 2026-09-05 13:27
    --status) STATUS_ONLY=1; shift;;
    *) echo "REFUSED: unknown argument $1 (takes only --coder agy|claude, --allow-merge, --status)"; exit 1;;
  esac
done
case "$CODER" in agy|claude) ;; *) echo "REFUSED: --coder must be agy or claude"; exit 1;; esac

# Liveness probe. Restored 2026-09-05 17:5x: main's copy dropped it in the ff and
# every supervisor tick's "is the coder alive?" check became `REFUSED: unknown
# argument --status`, exit 1. It exits BEFORE any dispatch path — the bare script
# always launches, and an argument that merely fell through to the launcher once
# turned three probes into accidental dispatches (runs 7, 8, 9).
if [ "$STATUS_ONLY" -eq 1 ]; then
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

# Push gate. Restored 2026-09-05 17:5x alongside --status; main's copy never sets
# it, so the variable reached the coder guard UNSET. On this lane the supervisor
# owns every push and unset is the safe direction — but it was unset by accident,
# and an unset gate means a `push: YES` line in BRIEF.md is silently inert, which
# is exactly what ate run 13's push. The token is the bare word YES: "OPEN" or
# "open for <range>" does NOT arm it.
# Written as an `if`, never `grep … && VAR=1`: under `set -euo pipefail` a failing
# AND-list is the last command of the script and aborts every non-push dispatch.
PUSH_OK=0
if grep -qE '^push:.*\bYES\b' .agents/supervisor/BRIEF.md 2>/dev/null; then
  PUSH_OK=1
fi
export GOAIEZ_PUSH_OK="$PUSH_OK"

# Snapshot the supervisor's uncommitted files before every dispatch (a coder
# reset/checkout/stash wiped them once, 2026-09-02 15:31).
SNAP="/home/goaiez/tmp/sup-snap-$(basename "$PWD")-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$SNAP/.agents/supervisor" "$SNAP/.claude" "$SNAP/bin"
cp .agents/supervisor/*.md "$SNAP/.agents/supervisor/" 2>/dev/null
cp .claude/settings.json "$SNAP/.claude/"
mkdir -p "$SNAP/.agents/state" && cp .agents/state/BUILD-STATE.json .agents/state/JOURNAL.md "$SNAP/.agents/state/" 2>/dev/null || true 2>/dev/null; cp CLAUDE.md "$SNAP/"; cp bin/supervise.sh "$SNAP/bin/"
echo "snapshot: $SNAP"

n=1
TRACK=$(basename "$PWD")
while [ -e "/home/goaiez/tmp/agy-${TRACK}-run${n}.log" ] || [ -e "/home/goaiez/tmp/claude-${TRACK}-run${n}.log" ]; do n=$((n+1)); done
LOG="/home/goaiez/tmp/${CODER}-${TRACK}-run${n}.log"

if [ "$CODER" = claude ]; then
  # Claude Code as the coder, on the DEFAULT account (the owner's ruling). The
  # same KICKOFF, the same coder-bin git guard, the same gate. `--setting-sources
  # user` keeps this checkout's .claude/settings.json (the SUPERVISOR's column,
  # which denies app/**) out of the coder's permissions; the guard and the seal
  # are what bind it, not that file. Bounded by `timeout 8h` like agy's
  # --print-timeout.
  nohup bash -c 'export GOAIEZ_MERGE_OK='"$ALLOW_MERGE"'; export PATH=/home/goaiez/agents/coder-bin:$PATH; timeout 8h /home/goaiez/.local/bin/claude -p "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --setting-sources user --output-format text < /dev/null > '"$LOG"' 2>&1; echo "CLAUDE_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
else
  nohup bash -c 'export GOAIEZ_MERGE_OK='"$ALLOW_MERGE"'; export PATH=/home/goaiez/agents/coder-bin:$PATH; /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
fi
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "LAUNCHED run $n (pid $(cat "$PIDFILE")) coder=$CODER merge-gate=$([ "$ALLOW_MERGE" = 1 ] && echo OPEN || echo closed) GOAIEZ_PUSH_OK=$PUSH_OK log=$LOG"
else
  echo "LAUNCH FAILED — check $LOG"; exit 1
fi
