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
#
# Refuses to start if a coder is already running (never two in one tree).
set -euo pipefail
cd "$(dirname "$(readlink -f "$0")")/../.." || exit 1

CODER=agy
ALLOW_MERGE=0
while [ $# -gt 0 ]; do
  case "$1" in
    --coder) CODER="${2:-}"; shift 2;;
    --allow-merge) ALLOW_MERGE=1; shift;;   # opens the shared coder guard's merge gate (GOAIEZ_MERGE_OK=1) for THIS run only; the guard added it 2026-09-05 13:27
    *) echo "REFUSED: unknown argument $1 (takes only --coder agy|claude, --allow-merge)"; exit 1;;
  esac
done
case "$CODER" in agy|claude) ;; *) echo "REFUSED: --coder must be agy or claude"; exit 1;; esac

# GATE/BRIEF AGREEMENT (adopted at tick 197, 2026-09-08, from origin/main's launcher;
# upstream provenance Track 1 tick 125, incident N103/run 124). That run was dispatched on
# a KICKOFF which opened the merge gate in prose while the flag was absent, so it got
# GOAIEZ_MERGE_OK=0 and could not do the one thing it existed to do. `kill` is outside this
# seat's column, so the mistake was unrecallable — exactly the class of error a launcher
# should refuse rather than a tick should remember. It matters here specifically because the
# ONE wave this lane is queued for (the take, RULING CP/CR) is a merge wave.
#
# The needle is this seat's OWN deliberate phrasing in KICKOFF.md, never a generic word like
# "merge": today's KICKOFF.md says `Merge gate **CLOSED**`, which cannot match, and a kickoff
# that says neither is silent.
#
# ⚠️ POSITIVE CONTROL, recorded honestly — this differs from upstream. Upstream's comment
# says both needles matched its KICKOFF.md when written, because "a needle that has never
# matched anything is not an instrument" (wave 122). In THIS lane `Merge gate **OPEN` has
# never matched anything: grep over REVIEWS.md returns 0, because this seat has never opened
# the gate. So the needle was verified mechanically against a synthetic string at tick 197
# instead, and CLAUDE.md now BINDS the convention that a kickoff opening the merge gate says
# exactly `Merge gate **OPEN**`. Fails OPEN by construction: if the convention is ever
# broken the dispatch proceeds exactly as it did before this block, so it can never refuse a
# run it should have allowed.
if grep -q 'Merge gate \*\*OPEN' .agents/supervisor/KICKOFF.md && [ "$ALLOW_MERGE" = 0 ]; then
  echo "REFUSED: KICKOFF.md declares 'Merge gate **OPEN' but --allow-merge was not passed."
  echo "         The run would export GOAIEZ_MERGE_OK=0 and the shared guard would refuse the merge."
  exit 1
fi
# The harness refusal is UNCONDITIONAL here, not flag-gated as upstream: this lane has no
# --allow-harness (RULING CO), so GOAIEZ_HARNESS_OK is never exported and a kickoff declaring
# the harness gate open is unsatisfiable by construction — the RULING CL shape, which burns
# item 0 of a run. Deliberately NOT resolved by adding the flag (RULING CY/CZ).
if grep -q 'Harness gate \*\*OPEN' .agents/supervisor/KICKOFF.md; then
  echo "REFUSED: KICKOFF.md declares 'Harness gate **OPEN' but this lane's launcher has no --allow-harness."
  echo "         The run would export no GOAIEZ_HARNESS_OK and could not commit a merged harness."
  exit 1
fi

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
# N104 (upstream Track 1, 2026-09-07 tick 125; adopted here tick 197): the glob above is
# `*.md`, so the one supervisor file most likely to be edited AT dispatch time — this script
# — was the one the dispatch-time snapshot did not preserve. A protection whose scope was
# stated once and never read back.
cp .agents/supervisor/*.sh "$SNAP/.agents/supervisor/" 2>/dev/null || true
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
  nohup bash -c 'export GOAIEZ_MERGE_OK='"$ALLOW_MERGE"'; export PATH=/home/goaiez/agents/coder-bin:$PATH; export BASH_ENV=/home/goaiez/agents/coder-bin/shell-init.sh; timeout 8h /home/goaiez/.local/bin/claude -p "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --setting-sources user --output-format text < /dev/null > '"$LOG"' 2>&1; echo "CLAUDE_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
else
  nohup bash -c 'export GOAIEZ_MERGE_OK='"$ALLOW_MERGE"'; export PATH=/home/goaiez/agents/coder-bin:$PATH; export BASH_ENV=/home/goaiez/agents/coder-bin/shell-init.sh; /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
fi
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "LAUNCHED run $n (pid $(cat "$PIDFILE")) coder=$CODER merge-gate=$([ "$ALLOW_MERGE" = 1 ] && echo OPEN || echo closed) log=$LOG"
else
  echo "LAUNCH FAILED — check $LOG"; exit 1
fi
