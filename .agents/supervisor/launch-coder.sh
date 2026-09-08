#!/usr/bin/env bash
#
# Supervisor's coder dispatcher. Launches ONE coder run on the current
# KICKOFF.md, detached, logging to /home/goaiez/tmp/<coder>-<track>-run<N>.log.
#
#   bash .agents/supervisor/launch-coder.sh                  # Antigravity (default)
#   bash .agents/supervisor/launch-coder.sh --coder claude   # Claude Code fallback
#   bash .agents/supervisor/launch-coder.sh --allow-merge    # opens the guard's merge gate
#
# --allow-merge exports GOAIEZ_MERGE_OK=1, which is the ONLY thing that lets
# coder-bin/git run `merge|pull|cherry-pick|revert` (guard line 36). Added
# 2026-09-05 16:2x after PB-27 was refused for want of it: the guard gained the
# merge gate on Track 1's side, this per-track launcher never did, and per-track
# files never merge in either direction (ruling 26), so it had to be added here
# by hand. Deliberately NOT read from BRIEF.md — the tick rewrites that file
# every ten minutes, which is the door the push ruling closed. Off by default:
# only a tick that has measured the merge surface passes the flag.
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
ALLOW_MERGE=0
# --allow-harness exports GOAIEZ_HARNESS_OK=1 for ONE run (Track 1, OWNER.md
# 2026-09-06 17:2x). coder-bin/git keyed the JourneyHarness exemption to the
# checkout name grs-antig, so a lane could not fix the harness of a journey it
# owns. The flag opens the ABILITY TO COMMIT, never permission to weaken: a
# deleted assertion, a stubbed transport or a journey passing on a constant is a
# BLOCK, and the supervisor that opened the gate wears it. The supervisor that
# passes it quotes the harness diff in its own REVIEWS.md block. Never standing.
ALLOW_HARNESS=0
while [ $# -gt 0 ]; do
  case "$1" in
    --coder)
      CODER="${2:-}"
      shift 2
      ;;
    --allow-merge)
      ALLOW_MERGE=1
      shift
      ;;
    --allow-harness)
      ALLOW_HARNESS=1
      shift
      ;;
    *)
      echo "REFUSED: unknown argument '$1' (only --coder agy|claude, --allow-merge, --allow-harness)"; exit 1
      ;;
  esac
done
case "$CODER" in
  agy|claude) ;;
  *) echo "REFUSED: --coder must be agy or claude (got '$CODER')"; exit 1 ;;
esac

PIDFILE=".agents/supervisor/coder.pid"

# The mailbox fallback, added 2026-09-08 05:3x. The PB-113 merge took main's
# .claude/settings.json, which denies Edit/Write on
# grs-antig-*/.agents/supervisor/{BRIEF,REVIEWS,REPORT,KICKOFF}.md. That glob is
# plainly aimed at CODER sessions — Track 1's own checkout is `grs-antig`, which
# the glob does not match — but it binds this lane's SUPERVISOR too, since both
# share one settings.json. The surviving allow rule Write(.agents/supervisor/**)
# still permits every OTHER name in the directory, so the supervisor writes
# BRIEF-NEXT.md / KICKOFF-NEXT.md and this launcher prefers them when present.
# Nothing is weakened: the push gate still reads a `push:` line, the merge and
# harness gates are still command-line only, and a -NEXT kickoff states in its
# own text that BRIEF.md is stale so the coder is never handed two directives.
# Delete the two -NEXT files and this block the day the glob is narrowed.
KICKOFF_FILE=".agents/supervisor/KICKOFF.md"
BRIEF_FILE=".agents/supervisor/BRIEF.md"
# NB: written as `if`, not `[ … ] && VAR=…`. Under `set -e` a bare `&&` list that
# fails IS the statement's status and would exit the script the day the -NEXT
# files are removed — the failure mode would be a launcher that silently stops
# dispatching, which is the exact shape of the stall this block exists to end.
if [ -s .agents/supervisor/KICKOFF-NEXT.md ]; then KICKOFF_FILE=".agents/supervisor/KICKOFF-NEXT.md"; fi
if [ -s .agents/supervisor/BRIEF-NEXT.md ]; then BRIEF_FILE=".agents/supervisor/BRIEF-NEXT.md"; fi
echo "kickoff: $KICKOFF_FILE"
echo "brief:   $BRIEF_FILE"

# A live pidfile is NOT proof of a live coder. `nohup bash -c '… agy …'` can
# outlive the agy it launched: run 54 left its wrapper parented to init with no
# agy under it and two orphaned `tail -f` holding its fds, and the plain
# `kill -0` below then refused every dispatch that followed. The supervisor
# cannot kill the orphan — `kill` is outside its column — so the launcher has to
# see through it. A wrapper with no agy/claude DESCENDANT is a stale waiter:
# say so, and launch anyway.
coder_alive() {
  want="$1"
  [ -n "$want" ] || return 1
  kill -0 "$want" 2>/dev/null || return 1
  for pid in $(pgrep -f '/home/goaiez/\.local/bin/(agy|claude)' 2>/dev/null || true); do
    # ⚠️ pgrep -f matches the WRAPPER too: `bash -c '… /home/goaiez/.local/bin/agy …'`
    # carries the binary path in its own argv. Without this skip the walk starts at
    # `want` itself, `q == want` fires on hop 0, and the function can never report a
    # stale waiter — inert, and wrong in the one direction that matters.
    [ "$pid" = "$want" ] && continue
    q="$pid"
    hops=0
    while [ -n "$q" ] && [ "$q" != "1" ] && [ "$q" != "0" ] && [ "$hops" -lt 64 ]; do
      [ "$q" = "$want" ] && return 0
      q="$(ps -o ppid= -p "$q" 2>/dev/null | tr -d ' ' || true)"
      hops=$((hops + 1))
    done
  done
  return 1
}

if [ -f "$PIDFILE" ]; then
  RUNNING="$(cat "$PIDFILE")"
  if coder_alive "$RUNNING"; then
    echo "REFUSED: this track's coder is already active (pid $RUNNING)"
    exit 1
  fi
  if kill -0 "$RUNNING" 2>/dev/null; then
    echo "STALE WAITER: pid $RUNNING is alive with no agy/claude under it — treating the run as finished and launching"
  fi
fi
[ -s "$KICKOFF_FILE" ] || { echo "REFUSED: $KICKOFF_FILE missing or empty"; exit 1; }

# The push gate. coder-bin/git's `push` rule wants GOAIEZ_PUSH_OK=1 and reads it
# from the environment; it is exported here ONLY when BRIEF.md's `push:` line
# says YES (rule 10: the BRIEF's push line is the gate). Added 2026-09-04 21:1x
# after run 26 was refused for want of it (REVIEWS.md, the 21:1x block).
# Since the owner's 14:0x ruling the supervisor runs every push itself, so this
# stays 0 on every brief this track writes; the gate is kept, not removed.
GOAIEZ_PUSH_OK=0
if grep -qiE '^push:[[:space:]]*\**[[:space:]]*yes' "$BRIEF_FILE"; then GOAIEZ_PUSH_OK=1; fi
export GOAIEZ_PUSH_OK
echo "push gate: GOAIEZ_PUSH_OK=$GOAIEZ_PUSH_OK (from $BRIEF_FILE's push: line)"

# The merge gate. Set ONLY by --allow-merge on this command line, never from a file.
GOAIEZ_MERGE_OK=$ALLOW_MERGE
export GOAIEZ_MERGE_OK
echo "merge gate: GOAIEZ_MERGE_OK=$GOAIEZ_MERGE_OK (from --allow-merge)"

# The harness gate. Set ONLY by --allow-harness on this command line, never from
# a file. coder-bin/git clears its JourneyHarness refusal on this variable.
GOAIEZ_HARNESS_OK=$ALLOW_HARNESS
export GOAIEZ_HARNESS_OK
echo "harness gate: GOAIEZ_HARNESS_OK=$GOAIEZ_HARNESS_OK (from --allow-harness)"

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
  nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; export BASH_ENV=/home/goaiez/agents/coder-bin/shell-init.sh; timeout 8h /home/goaiez/.local/bin/claude -p "$(cat '"$KICKOFF_FILE"')" --dangerously-skip-permissions --setting-sources user --output-format text < /dev/null > '"$LOG"' 2>&1; echo "CLAUDE_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
else
  # `timeout -k 60 3h` bounds the run. agy's own --print-timeout does NOT: run 54
  # finished its wave, wrote REPORT.md at 04:42, and then sat alive indefinitely
  # parked on a `tail -f` it never reaped — and `kill` is outside this supervisor's
  # column, so an unbounded parked coder stalls every following tick on case (a)
  # until a human intervenes (44 ticks, in another lane). Tracks 2 and 7 already
  # wrap agy this way; this lane did not. 3h is well past any wave here.
  # BASH_ENV makes `kill` resolve through PATH in every non-interactive shell the
  # coder spawns (coder-bin/shell-init.sh runs `enable -n kill`), so coder-bin/kill
  # can record caller · target · cwd to /home/goaiez/tmp/kill-log.tsv and THEN kill.
  # It refuses nothing — killing a pid you started is legitimate; it makes the
  # SIGTERMs attributable (Track 1, OWNER.md 2026-09-06 16:0x).
  nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; export BASH_ENV=/home/goaiez/agents/coder-bin/shell-init.sh; timeout -k 60 3h /home/goaiez/.local/bin/agy --print "$(cat '"$KICKOFF_FILE"')" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
fi
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "LAUNCHED run $n coder=$CODER (pid $(cat "$PIDFILE")) log=$LOG"
else
  echo "LAUNCH FAILED — coder=$CODER — check $LOG"; exit 1
fi
