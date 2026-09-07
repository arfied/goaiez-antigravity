#!/usr/bin/env bash
#
# Supervisor's coder dispatcher. Launches ONE Antigravity run on the current
# KICKOFF.md, detached, logging to /home/goaiez/tmp/agy-run<N>.log.
#
#   bash .agents/supervisor/launch-coder.sh                 # Antigravity (default)
#   bash .agents/supervisor/launch-coder.sh --allow-harness # opens JourneyHarness.php
#                                                           # for THIS run only
#   bash .agents/supervisor/launch-coder.sh --coder claude  # Claude Code as the coder
#                                                           # (owner 2026-09-05: the
#                                                           # default account; only
#                                                           # when agy reports
#                                                           # "quota reached")
#
# Refuses to start if a coder is already running (never two in one tree).
set -euo pipefail
cd "$(dirname "$(readlink -f "$0")")/../.." || exit 1

CODER=agy
ALLOW_MERGE=0
ALLOW_HARNESS=0
while [ $# -gt 0 ]; do
  case "$1" in
    --coder) CODER="${2:-}"; shift 2;;
    --allow-merge) ALLOW_MERGE=1; shift;;   # opens the shared coder guard's merge gate (GOAIEZ_MERGE_OK=1) for THIS run only; the guard added it 2026-09-05 13:27
    # ⛔ --allow-harness OPENS THE ABILITY TO COMMIT tests/Journeys/JourneyHarness.php,
    # NOT PERMISSION TO WEAKEN IT (Track 1, 2026-09-06 17:2x). Provisioning real state
    # so a real code path runs is a fix; deleting an assertion, stubbing a transport or
    # making a journey pass on a constant is a BLOCK, and the supervisor that opened the
    # gate wears it. The supervisor that passes this flag QUOTES THE HARNESS DIFF in its
    # own REVIEWS.md block — an unreviewable harness change is the exact shape of the
    # fake green this repo keeps finding. One run only; never a standing flag.
    --allow-harness) ALLOW_HARNESS=1; shift;;
    *) echo "REFUSED: unknown argument $1 (takes only --coder agy|claude, --allow-merge, --allow-harness)"; exit 1;;
  esac
done
case "$CODER" in agy|claude) ;; *) echo "REFUSED: --coder must be agy or claude"; exit 1;; esac

PIDFILE=".agents/supervisor/coder.pid"
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
while [ -e "/home/goaiez/tmp/agy-${TRACK}-run${n}.log" ] || [ -e "/home/goaiez/tmp/claude-${TRACK}-run${n}.log" ]; do n=$((n+1)); done
LOG="/home/goaiez/tmp/${CODER}-${TRACK}-run${n}.log"

if [ "$CODER" = claude ]; then
  # Claude Code as the coder, on the DEFAULT account (the owner's ruling). The
  # same KICKOFF, the same coder-bin git guard, the same gate. `--setting-sources
  # user` keeps this checkout's .claude/settings.json (the SUPERVISOR's column,
  # which denies app/**) out of the coder's permissions; the guard and the seal
  # are what bind it, not that file. Bounded by `timeout 8h` like agy's
  # --print-timeout.
  nohup bash -c 'export GOAIEZ_MERGE_OK='"$ALLOW_MERGE"'; export GOAIEZ_HARNESS_OK='"$ALLOW_HARNESS"'; export BASH_ENV=/home/goaiez/agents/coder-bin/shell-init.sh; export PATH=/home/goaiez/agents/coder-bin:$PATH; timeout -k 60 3h /home/goaiez/.local/bin/claude -p "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --setting-sources user --output-format text < /dev/null > '"$LOG"' 2>&1; echo "CLAUDE_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
else
  nohup bash -c 'export GOAIEZ_MERGE_OK='"$ALLOW_MERGE"'; export GOAIEZ_HARNESS_OK='"$ALLOW_HARNESS"'; export BASH_ENV=/home/goaiez/agents/coder-bin/shell-init.sh; export PATH=/home/goaiez/agents/coder-bin:$PATH; timeout -k 60 3h /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
fi
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "LAUNCHED run $n (pid $(cat "$PIDFILE")) coder=$CODER merge-gate=$([ "$ALLOW_MERGE" = 1 ] && echo OPEN || echo closed) harness-gate=$([ "$ALLOW_HARNESS" = 1 ] && echo OPEN || echo closed) bound=3h log=$LOG"
else
  echo "LAUNCH FAILED — check $LOG"; exit 1
fi
