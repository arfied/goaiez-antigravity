#!/usr/bin/env bash
#
# Supervisor's coder dispatcher. Launches ONE Antigravity run on the current
# KICKOFF.md, detached, logging to /home/goaiez/tmp/agy-run<N>.log.
#
#   bash .agents/supervisor/launch-coder.sh                 # auto-numbers the run
#   bash .agents/supervisor/launch-coder.sh --check         # liveness only, no launch
#   bash .agents/supervisor/launch-coder.sh --coder claude   # quota fallback
#   bash .agents/supervisor/launch-coder.sh --allow-merge    # opens GOAIEZ_MERGE_OK
#
# Refuses to start if a coder is already running (never two in one tree).
#
# --coder (owner ruling 30, OWNER.md 17:1x). Antigravity is the default and the
# first launch after a quota reset is always agy. `--coder claude` is passed BY
# HAND OF TICK, only after a redispatch has died a second time on "Individual
# quota reached", and the tick writes `coder=claude` into the REVIEWS block that
# records the LAUNCHED line. It is never automatic.
set -euo pipefail
cd "$(dirname "$0")/../.." || exit 1

PIDFILE=".agents/supervisor/coder.pid"
CODER="agy"
# Merge gate. `coder-bin/git:51` refuses merge|pull|cherry-pick|revert unless
# GOAIEZ_MERGE_OK=1, and says in as many words that only launch-coder.sh may open
# it. This launcher never exported it, so six raisings of "OWNER ACTION 9(b)" were
# spent on a door that has been unlocked since 2026-09-05 13:27 (Track 1, OWNER.md
# 12:2x). Default CLOSED, per dispatch, by hand of tick — never read from BRIEF.md,
# which is rewritten every tick (the door ruling 26 closed on the push gate).
ALLOW_MERGE=0

# --check: report liveness and exit without launching anything. Used by the
# unattended supervisor tick, whose allowlist has no ps/pgrep/kill.
if [ "${1:-}" = "--check" ]; then
  if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
    echo "CODER ALIVE pid=$(cat "$PIDFILE")"
  else
    echo "CODER DEAD"
  fi
  exit 0
fi

# --coder agy|claude. Anything else is refused rather than defaulted: a typo that
# silently launched the wrong coder would be indistinguishable from a deliberate
# fallback in the log, and ruling 30 requires the choice to be recorded.
while [ -n "${1:-}" ]; do
  case "$1" in
    --coder)
      case "${2:-}" in
        agy|claude) CODER="$2" ;;
        *) echo "REFUSED: --coder takes 'agy' or 'claude', got '${2:-}'"; exit 1 ;;
      esac
      shift 2 ;;
    --allow-merge) ALLOW_MERGE=1; shift ;;
    *) echo "REFUSED: unknown argument '$1' (expected --check, --coder agy|claude, --allow-merge)"; exit 1 ;;
  esac
done

if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "REFUSED: this track's coder is already active (pid $(cat "$PIDFILE"))"
  exit 1
fi
[ -s .agents/supervisor/KICKOFF.md ] || { echo "REFUSED: KICKOFF.md missing or empty"; exit 1; }

# Push gate. Added 2026-09-04 14:2x (MONEY-17c) to open the coder's push on a
# briefed `push: YES`; CLOSED PERMANENTLY 2026-09-05 14:0x (owner ruling 26) —
# the supervisor runs all git for this lane, the coder never pushes. The gate is
# no longer derived from BRIEF.md, so a stale `push: YES` cannot reopen it.
PUSH_OK=0
echo "push gate: CLOSED — ruling 26, the supervisor pushes the gated tip -> GOAIEZ_PUSH_OK=$PUSH_OK"
if grep -qE '^push: *\**YES' .agents/supervisor/BRIEF.md 2>/dev/null; then
  echo "  note: BRIEF.md still carries a 'push: YES' line. Stale, ignored."
fi

if [ "$ALLOW_MERGE" = 1 ]; then
  echo "merge gate: OPEN — --allow-merge passed by hand of tick -> GOAIEZ_MERGE_OK=$ALLOW_MERGE"
else
  echo "merge gate: closed -> GOAIEZ_MERGE_OK=$ALLOW_MERGE"
fi


# Snapshot the supervisor's uncommitted files before every dispatch (a coder
# reset/checkout/stash wiped them once, 2026-09-02 15:31).
SNAP="/home/goaiez/tmp/sup-snap-$(basename "$PWD")-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$SNAP/.agents/supervisor" "$SNAP/.claude" "$SNAP/bin"
cp .agents/supervisor/*.md "$SNAP/.agents/supervisor/" 2>/dev/null
cp .claude/settings.json "$SNAP/.claude/" 2>/dev/null; cp CLAUDE.md "$SNAP/"; cp bin/supervise.sh "$SNAP/bin/"
echo "snapshot: $SNAP"

# Run log. It lives INSIDE the checkout from 2026-09-05 13:4x: an unattended tick's
# tool sandbox is confined to the worktree, so a log under /home/goaiez/tmp cannot be
# read when it is most needed — diagnosing a run that died without writing REPORT.md
# (MONEY-36 did exactly that). `.gitignore` ignores `.agents/supervisor/*`, so nothing
# here is ever committed. Numbering continues across both locations.
TRACK=$(basename "$PWD")
LOGDIR=".agents/supervisor/logs"
mkdir -p "$LOGDIR"
# One run counter for both coders, and it steps over a name either coder may have
# taken, in either location — a claude run must never reuse an agy run's number.
n=1
while [ -e "/home/goaiez/tmp/agy-${TRACK}-run${n}.log" ] \
   || [ -e "/home/goaiez/tmp/claude-${TRACK}-run${n}.log" ] \
   || [ -e "$LOGDIR/agy-run${n}.log" ] \
   || [ -e "$LOGDIR/claude-run${n}.log" ]; do n=$((n+1)); done
# Ruling 30(b) names the log /home/goaiez/tmp/claude-<track>-runN.log. It lives in
# the checkout here for the same reason the agy log does (MONEY-36, 0b925de6): an
# unattended tick's sandbox cannot read /home/goaiez/tmp, and a fallback run that
# dies without a REPORT is exactly when the log must be readable. The coder is
# still named in the filename, which is the property the ruling is after.
LOG="$LOGDIR/${CODER}-run${n}.log"

if [ "$CODER" = "claude" ]; then
  nohup bash -c 'export GOAIEZ_PUSH_OK='"$PUSH_OK"'; export GOAIEZ_MERGE_OK='"$ALLOW_MERGE"'; export PATH=/home/goaiez/agents/coder-bin:$PATH; timeout 8h /home/goaiez/.local/bin/claude -p "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --setting-sources user --output-format text < /dev/null > '"$LOG"' 2>&1; echo "CLAUDE_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
else
  nohup bash -c 'export GOAIEZ_PUSH_OK='"$PUSH_OK"'; export GOAIEZ_MERGE_OK='"$ALLOW_MERGE"'; export PATH=/home/goaiez/agents/coder-bin:$PATH; /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
fi
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  MG=closed; [ "$ALLOW_MERGE" = 1 ] && MG=OPEN
  echo "LAUNCHED run $n coder=$CODER merge-gate=$MG (pid $(cat "$PIDFILE")) log=$LOG"
else
  echo "LAUNCH FAILED — check $LOG"; exit 1
fi
