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
ALLOW_HARNESS=0
STATUS_ONLY=0
while [ $# -gt 0 ]; do
  case "$1" in
    --coder) CODER="${2:?--coder takes agy or claude}"; shift 2;;
    --allow-merge) ALLOW_MERGE=1; shift;;   # opens the shared coder guard's merge gate (GOAIEZ_MERGE_OK=1) for THIS run only; the guard added it 2026-09-05 13:27
    # opens the shared coder guard's JourneyHarness.php gate (GOAIEZ_HARNESS_OK=1) for THIS
    # run only. Added 2026-09-06 17:2x on Track 1's ruling: coder-bin/git:53 keyed the harness
    # exemption to the checkout NAME `grs-antig`, so the lane that owns a journey could not fix
    # its own harness — this lane spent two dispatches on J11 and correctly refused a third.
    # ⛔ It opens the ability to COMMIT, not permission to WEAKEN. Provisioning real state so a
    # real code path runs is a fix; deleting an assertion or stubbing a transport is a BLOCK,
    # and the supervisor that opens the gate quotes the harness diff in REVIEWS.md.
    # Open it for the run that needs it, never as a standing flag.
    --allow-harness) ALLOW_HARNESS=1; shift;;
    --status) STATUS_ONLY=1; shift;;
    *) echo "REFUSED: unknown argument $1 (takes only --coder agy|claude, --allow-merge, --allow-harness, --status)"; exit 1;;
  esac
done
# Owner ruling 2026-09-11 14:0x (supersedes the 10:2x re-enable, restores 2026-09-10): the claude
# fallback is DISABLED on every track. On "quota reached" the tick records it in REVIEWS.md and
# stops; the next tick after the reset dispatches agy. Refused here, before any liveness check,
# snapshot or launch, so no path below can reach the claude branch (tick 344, after money's 39647c21).
case "$CODER" in
  agy) ;;
  claude) echo "REFUSED: --coder claude is DISABLED (owner ruling 2026-09-11 14:0x). Record the agy quota exit in REVIEWS.md and stop."; exit 1;;
  *) echo "REFUSED: --coder must be agy"; exit 1;;
esac

# Liveness probe. Restored 2026-09-05 17:5x: main's copy dropped it in the ff and
# every supervisor tick's "is the coder alive?" check became `REFUSED: unknown
# argument --status`, exit 1. It exits BEFORE any dispatch path — the bare script
# always launches, and an argument that merely fell through to the launcher once
# turned three probes into accidental dispatches (runs 7, 8, 9).
#
# Liveness (tick 360, money's ruling 597 measured here, adapted). A bare `kill -0` on the
# pidfile was wrong both ways:
#  - nothing clears the pidfile when a run ends and this box wraps its pid space within a
#    day, so the old pid comes back as some other process. MEASURED at tick 360: with
#    sixty's live agy pid 63463 (cwd …/grs-antig-sixty) written into this pidfile, --status
#    printed `ALIVE: pid 63463`, and the dispatch path below used the identical expression,
#    so every tick would have refused to launch for as long as that stranger lived.
#  - the pidfile holds the `nohup bash` wrapper; `timeout` and agy are separate processes,
#    so a killed wrapper leaves a live coder that read DEAD — a second writer in this tree,
#    the one-writer BLOCK. READ, not run: no orphan was constructed.
# So a live pidfile pid counts only if its cwd is this checkout. An UNREADABLE cwd counts
# as ours — the direction that cannot produce a second writer — and names the pid, so a
# tick resolves it with tick 176's second `pgrep`. Then any agy process whose cwd is this
# checkout counts even when the pidfile does not name it. agy only: a supervisor tick is a
# `claude` process with this cwd and must never read as a running coder.
HERE=$(pwd -P)
CODER_PID=""
CODER_NOTE=""
coder_running() {
  local pid cwd p
  pid=""
  if [ -f "$PIDFILE" ]; then pid=$(cat "$PIDFILE" 2>/dev/null || true); fi
  if [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null; then
    cwd=$(readlink "/proc/$pid/cwd" 2>/dev/null || true)
    if [ -z "$cwd" ] || [ "$cwd" = "$HERE" ]; then
      CODER_PID="$pid"
      if [ -z "$cwd" ]; then CODER_NOTE="pidfile pid $pid is alive and its cwd is unreadable: counted as ours (the safe direction); re-run pgrep agy — a pid that persists unreadable is another uid's (tick 176)"; fi
      return 0
    fi
    CODER_NOTE="pidfile pid $pid is alive but runs in $cwd, not this checkout: a reused pid, ignored"
  fi
  for p in $(pgrep -f '/[.]local/bin/agy ' 2>/dev/null || true); do
    cwd=$(readlink "/proc/$p/cwd" 2>/dev/null || true)
    if [ "$cwd" = "$HERE" ]; then
      CODER_PID="$p"
      CODER_NOTE="the pidfile names no live coder here, but agy process $p runs in this checkout: an orphaned coder"
      return 0
    fi
  done
  return 1
}

if [ "$STATUS_ONLY" -eq 1 ]; then
  if coder_running; then
    echo "ALIVE: pid $CODER_PID"
  else
    echo "DEAD: no coder for this track (pidfile: $( [ -f "$PIDFILE" ] && cat "$PIDFILE" || echo none ))"
  fi
  if [ -n "$CODER_NOTE" ]; then echo "  note: $CODER_NOTE"; fi
  echo "(probe only — no dispatch. Run with no arguments to launch.)"
  exit 0
fi

if coder_running; then
  echo "REFUSED: this track's coder is already active (pid $CODER_PID)"
  if [ -n "$CODER_NOTE" ]; then echo "  note: $CODER_NOTE"; fi
  exit 1
fi
if [ -n "$CODER_NOTE" ]; then echo "note: $CODER_NOTE"; fi
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
  nohup bash -c 'export GOAIEZ_MERGE_OK='"$ALLOW_MERGE"'; export GOAIEZ_HARNESS_OK='"$ALLOW_HARNESS"'; export PATH=/home/goaiez/agents/coder-bin:$PATH; export BASH_ENV=/home/goaiez/agents/coder-bin/shell-init.sh;timeout 8h /home/goaiez/.local/bin/claude -p "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --setting-sources user --output-format text < /dev/null > '"$LOG"' 2>&1; echo "CLAUDE_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
else
  nohup bash -c 'export GOAIEZ_MERGE_OK='"$ALLOW_MERGE"'; export GOAIEZ_HARNESS_OK='"$ALLOW_HARNESS"'; export PATH=/home/goaiez/agents/coder-bin:$PATH; export BASH_ENV=/home/goaiez/agents/coder-bin/shell-init.sh;timeout -k 60 3h /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
fi
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "LAUNCHED run $n (pid $(cat "$PIDFILE")) coder=$CODER merge-gate=$([ "$ALLOW_MERGE" = 1 ] && echo OPEN || echo closed) harness-gate=$([ "$ALLOW_HARNESS" = 1 ] && echo OPEN || echo closed) GOAIEZ_PUSH_OK=$PUSH_OK log=$LOG"
else
  echo "LAUNCH FAILED — check $LOG"; exit 1
fi
