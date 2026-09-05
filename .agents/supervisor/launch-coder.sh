#!/usr/bin/env bash
#
# Supervisor's coder dispatcher. Launches ONE Antigravity run on the current
# KICKOFF.md, detached, logging to /home/goaiez/tmp/agy-run<N>.log.
#
#   bash .agents/supervisor/launch-coder.sh          # auto-numbers the run
#
# Refuses to start if a coder is already running (never two in one tree).
set -euo pipefail
cd "$(dirname "$(readlink -f "$0")")/../.." || exit 1

PIDFILE=".agents/supervisor/coder.pid"

# --status: liveness only, never launches. The unattended supervisor tick needs
# step (a) of its contract and cannot run `kill -0` under its own allow list.
if [ "${1:-}" = "--status" ]; then
  if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
    echo "CODER ALIVE pid=$(cat "$PIDFILE")"
  else
    echo "CODER DEAD"
  fi
  exit 0
fi

if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "REFUSED: this track's coder is already active (pid $(cat "$PIDFILE"))"
  exit 1
fi
[ -s .agents/supervisor/KICKOFF.md ] || { echo "REFUSED: KICKOFF.md missing or empty"; exit 1; }


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
while [ -e "/home/goaiez/tmp/agy-${TRACK}-run${n}.log" ]; do n=$((n+1)); done
LOG="/home/goaiez/tmp/agy-${TRACK}-run${n}.log"

# Push gate — WIRED SHUT. OWNER RULING 2026-09-05 14:0x: the coder never
# pushes; the supervisor runs every push for this lane by explicit ref, on a sha
# it has gated and recorded in REVIEWS.md. coder-bin/git (2026-09-04 09:13)
# refuses `git push` unless GOAIEZ_PUSH_OK=1, and only the launcher may set it,
# so holding it at 0 here is what makes the ruling structural rather than a
# sentence in a brief. Do NOT restore the BRIEF.md `push:` derivation: reading
# the gate out of a file the supervisor rewrites every tick is exactly the door
# the ruling closes.
PUSH_OK=0
echo "push gate: closed (owner ruling 2026-09-05 14:0x — the coder never pushes)"

nohup bash -c 'export GOAIEZ_PUSH_OK='"$PUSH_OK"'; export PATH=/home/goaiez/agents/coder-bin:$PATH; /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "LAUNCHED run $n (pid $(cat "$PIDFILE")) log=$LOG"
else
  echo "LAUNCH FAILED — check $LOG"; exit 1
fi
