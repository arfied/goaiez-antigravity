#!/usr/bin/env bash
#
# Supervisor's coder dispatcher. Launches ONE coder run on the current
# KICKOFF.md, detached, logging to /home/goaiez/tmp/<coder>-<track>-run<N>.log.
#
#   bash .agents/supervisor/launch-coder.sh                 # auto-numbers the run, coder=agy
#   bash .agents/supervisor/launch-coder.sh --coder claude  # fallback coder (see below)
#   bash .agents/supervisor/launch-coder.sh --status        # liveness ONLY, never launches
#
# Refuses to start if a coder is already running (never two in one tree).
#
# ⚠️ Only three invocations exist: no argument (dispatch), --coder agy|claude
# (dispatch on a named coder) and --status (liveness). ANY other argument is
# refused without launching. History: on 2026-09-02 a tick probed with an
# invented `--probe-only` and launched run 9 by accident; on 2026-09-03 tick 123
# probed with `--help` and launched run 17 the same way. An unattended tick that
# wants case (a) must use --status.
#
# The `claude` coder exists for ONE reason (owner ruling 2026-09-05 17:1x):
# Antigravity dies on "Individual quota reached" and the account is shared by all
# eight tracks. The first launch after a reset is always agy; --coder claude is
# passed by hand-of-tick only after a SECOND consecutive quota death, and the tick
# records `coder=claude` in the REVIEWS block that quotes the LAUNCHED line.
# `--setting-sources user` keeps the SUPERVISOR's .claude/settings.json (which
# denies app/**) out of the coder's permissions; the coder-bin git guard and the
# seal bind the claude coder exactly as they bind agy.
set -euo pipefail
cd "$(dirname "$0")/../.." || exit 1

PIDFILE=".agents/supervisor/coder.pid"
CODER="agy"

case "${1:-}" in
  "")
    ;;
  --status)
    if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
      echo "ALIVE $(cat "$PIDFILE")"
    else
      echo "DEAD"
    fi
    exit 0
    ;;
  --coder)
    case "${2:-}" in
      agy|claude) CODER="$2" ;;
      *) echo "REFUSED: --coder takes exactly 'agy' or 'claude' (got '${2:-}'). Nothing launched."; exit 1 ;;
    esac
    if [ $# -gt 2 ]; then
      echo "REFUSED: unexpected extra argument '$3' after --coder $CODER. Nothing launched."; exit 1
    fi
    ;;
  *)
    echo "REFUSED: unknown argument '$1' — this script takes no argument (dispatch), --coder agy|claude, or --status (liveness). Nothing launched."
    exit 1
    ;;
esac

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

# One run sequence across BOTH coders: a number is taken if either coder logged it,
# so run 93 is run 93 whichever binary produced it and the ledger stays readable.
n=1
TRACK=$(basename "$PWD")
while [ -e "/home/goaiez/tmp/agy-${TRACK}-run${n}.log" ] || [ -e "/home/goaiez/tmp/claude-${TRACK}-run${n}.log" ]; do n=$((n+1)); done
LOG="/home/goaiez/tmp/${CODER}-${TRACK}-run${n}.log"

if [ "$CODER" = "claude" ]; then
  nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; export GOAIEZ_PUSH_OK='"$PUSH_OK"'; timeout 8h /home/goaiez/.local/bin/claude -p "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --setting-sources user --output-format text < /dev/null > '"$LOG"' 2>&1; echo "CLAUDE_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
else
  nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; export GOAIEZ_PUSH_OK='"$PUSH_OK"'; /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
fi
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "LAUNCHED run $n (pid $(cat "$PIDFILE")) coder=$CODER log=$LOG GOAIEZ_PUSH_OK=$PUSH_OK"
else
  echo "LAUNCH FAILED — check $LOG"; exit 1
fi
