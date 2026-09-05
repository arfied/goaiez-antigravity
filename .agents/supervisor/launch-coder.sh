#!/usr/bin/env bash
#
# Supervisor's coder dispatcher. Launches ONE Antigravity run on the current
# KICKOFF.md, detached, logging to /home/goaiez/tmp/agy-run<N>.log.
#
#   bash .agents/supervisor/launch-coder.sh          # auto-numbers the run
#
# Refuses to start if a coder is already running (never two in one tree).
set -euo pipefail

# ⛔ RESOLVE PHYSICALLY, AND THE `-P` IS THE WHOLE POINT. `app/.agents` is a
# SYMLINK to `../.agents`, so a launch from inside `app/` made
# `dirname "$0"/../..` collapse logically back to `app/` — bash resolves `..`
# textually. On 2026-09-03 that launched a coder with its working directory in
# `app/` and wrote its log to `agy-app-run1.log`, a name every track on this box
# would collide on. `cd -P` on the script's own directory resolves the symlink
# first, so `../..` lands on the real checkout root wherever it was invoked from.
SELF="$(cd -P "$(dirname "$0")" && pwd)"
cd "$SELF/../.." || exit 1
[ -f bin/supervise.sh ] && [ -d app ] || {
  echo "REFUSED: $PWD is not this checkout's root (no bin/supervise.sh + app/)"; exit 1; }

PIDFILE=".agents/supervisor/coder.pid"

# `--status` answers "is the coder alive?" and does nothing else. An unattended
# supervisor tick has no other way to ask: `ps` and `/proc` are both outside its
# permitted set, and probing by calling this script bare would *launch* a coder
# on whatever KICKOFF.md happened to be on disk. Case (a) of the tick needs the
# answer before it writes anything, so the answer has to be free of side effects.
if [ "${1:-}" = "--status" ]; then
  if [ ! -f "$PIDFILE" ]; then echo "NONE: no pidfile"; exit 0; fi
  if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
    echo "ALIVE $(cat "$PIDFILE")"
  else
    echo "DEAD $(cat "$PIDFILE")"
  fi
  exit 0
fi

if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "REFUSED: this track's coder is already active (pid $(cat "$PIDFILE"))"
  exit 1
fi
[ -s .agents/supervisor/KICKOFF.md ] || { echo "REFUSED: KICKOFF.md missing or empty"; exit 1; }

# Snapshot the supervisor's uncommitted files before every dispatch (a coder
# reset/checkout/stash wiped them once on Track 1, 2026-09-02 15:31). Parity
# with grs-antig's launcher — the mailbox is the only record of every verdict
# this track has issued and none of it is committed.
SNAP="/home/goaiez/tmp/sup-snap-$(basename "$PWD")-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$SNAP/.agents/supervisor" "$SNAP/.claude" "$SNAP/bin"
cp .agents/supervisor/*.md "$SNAP/.agents/supervisor/" 2>/dev/null
cp .claude/settings.json "$SNAP/.claude/" 2>/dev/null; cp CLAUDE.md "$SNAP/"; cp bin/supervise.sh "$SNAP/bin/"
echo "snapshot: $SNAP"

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
