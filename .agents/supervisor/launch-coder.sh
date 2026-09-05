#!/usr/bin/env bash
#
# Supervisor's coder dispatcher. Launches ONE coder run on the current
# KICKOFF.md, detached, logging to /home/goaiez/tmp/<coder>-<track>-run<N>.log.
#
#   bash .agents/supervisor/launch-coder.sh                  # Antigravity (default)
#   bash .agents/supervisor/launch-coder.sh --coder claude   # Claude Code fallback
#
# Antigravity is the default coder and stays so. The claude branch exists for
# ONE case, set by the owner 2026-09-05 17:1x (OWNER.md): an agy launch that
# died on "Individual quota reached", was waited out, and died on quota AGAIN.
# It is never automatic — the tick passes --coder claude by hand and writes
# `coder=claude` in the REVIEWS block that announces the LAUNCHED line.
#
# Both branches run with /home/goaiez/agents/coder-bin first on PATH, so the
# git guard (hard rule 4) and the seal bind claude exactly as they bind agy.
#
# Refuses to start if a coder is already running (never two in one tree).
set -euo pipefail
cd "$(dirname "$0")/../.." || exit 1

CODER="agy"
while [ $# -gt 0 ]; do
  case "$1" in
    --coder)
      CODER="${2:-}"
      shift 2
      ;;
    *)
      echo "REFUSED: unknown argument '$1' (only --coder agy|claude)"; exit 1
      ;;
  esac
done
case "$CODER" in
  agy|claude) ;;
  *) echo "REFUSED: --coder must be agy or claude (got '$CODER')"; exit 1 ;;
esac

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
# Since the owner's 14:0x ruling the supervisor runs every push itself, so this
# stays 0 on every brief this track writes; the gate is kept, not removed.
GOAIEZ_PUSH_OK=0
if grep -qiE '^push:[[:space:]]*\**[[:space:]]*yes' .agents/supervisor/BRIEF.md; then GOAIEZ_PUSH_OK=1; fi
export GOAIEZ_PUSH_OK
echo "push gate: GOAIEZ_PUSH_OK=$GOAIEZ_PUSH_OK (from BRIEF.md's push: line)"

# One run number per track, free under BOTH prefixes, so "run N" in REVIEWS.md
# names one run whichever coder served it.
n=1
TRACK=$(basename "$PWD")
while [ -e "/home/goaiez/tmp/agy-${TRACK}-run${n}.log" ] || [ -e "/home/goaiez/tmp/claude-${TRACK}-run${n}.log" ]; do
  n=$((n+1))
done
LOG="/home/goaiez/tmp/${CODER}-${TRACK}-run${n}.log"

if [ "$CODER" = "claude" ]; then
  # --setting-sources user keeps THIS checkout's supervisor .claude/settings.json
  # (which denies app/**) out of the coder's permissions.
  nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; timeout 8h /home/goaiez/.local/bin/claude -p "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --setting-sources user --output-format text < /dev/null > '"$LOG"' 2>&1; echo "CLAUDE_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
else
  nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
fi
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "LAUNCHED run $n coder=$CODER (pid $(cat "$PIDFILE")) log=$LOG"
else
  echo "LAUNCH FAILED — coder=$CODER — check $LOG"; exit 1
fi
