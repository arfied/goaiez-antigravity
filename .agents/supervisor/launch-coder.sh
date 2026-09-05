#!/usr/bin/env bash
#
# Supervisor's coder dispatcher. Launches ONE coder run on the current
# KICKOFF.md, detached, logging to /home/goaiez/tmp/<coder>-<track>-run<N>.log.
#
#   bash .agents/supervisor/launch-coder.sh                 # Antigravity (default)
#   bash .agents/supervisor/launch-coder.sh --status        # is the coder alive?
#   bash .agents/supervisor/launch-coder.sh --coder claude  # Claude Code, default account
#
# `--coder claude` exists for ONE case (owner ruling 2026-09-05 17:1x): agy died on
# "Individual quota reached" and the redispatch after the reset died on it AGAIN. It is
# never automatic — a tick passes it by hand and writes `coder=claude` in the REVIEWS
# block that quotes the LAUNCHED line. Antigravity stays the default and the first
# launch after a quota reset is always agy.
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

# Which coder. Parsed AFTER `--status` so the side-effect-free probe above keeps
# working with no argument grammar to satisfy. Anything that is not agy or claude is
# refused rather than defaulted — a typo here launches the wrong binary on the wrong
# account, and the LAUNCHED line is the only place the choice is recorded.
CODER=agy
while [ $# -gt 0 ]; do
  case "$1" in
    # The value is required explicitly: a bare `--coder` would leave `shift 2` with
    # one argument, and `shift` refuses to shift more than it has — which spins this
    # loop forever rather than failing.
    --coder)
      [ $# -ge 2 ] || { echo "REFUSED: --coder needs a value (agy|claude)"; exit 1; }
      CODER="$2"; shift 2;;
    *) echo "REFUSED: unknown argument '$1' (takes only --status or --coder agy|claude)"; exit 1;;
  esac
done
case "$CODER" in
  agy|claude) ;;
  *) echo "REFUSED: --coder must be agy or claude (got '$CODER')"; exit 1;;
esac

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

# One run-number sequence across BOTH coders. If the scan only looked at its own
# prefix, an agy run and a claude run would both be "run 41" and every REVIEWS block
# naming a run number would be ambiguous.
n=1
TRACK=$(basename "$PWD")
while [ -e "/home/goaiez/tmp/agy-${TRACK}-run${n}.log" ] || [ -e "/home/goaiez/tmp/claude-${TRACK}-run${n}.log" ]; do
  n=$((n+1))
done
LOG="/home/goaiez/tmp/${CODER}-${TRACK}-run${n}.log"

if [ "$CODER" = claude ]; then
  # Claude Code on the DEFAULT account (no CLAUDE_CONFIG_DIR). Same KICKOFF, same
  # coder-bin git guard, same seal — those bind it exactly as they bind agy.
  # `--setting-sources user` deliberately keeps THIS checkout's .claude/settings.json
  # out of the coder's permissions: that file is the supervisor's column and denies
  # app/**, which is the coder's whole job. `timeout 8h` matches agy's --print-timeout.
  nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; timeout 8h /home/goaiez/.local/bin/claude -p "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --setting-sources user --output-format text < /dev/null > '"$LOG"' 2>&1; echo "CLAUDE_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
else
  nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
fi
echo $! > "$PIDFILE"

# ⚠️ This 2-second check proves the process STARTED, not that it read its kickoff.
# Run 40 passed it and was dead on "Individual quota reached" two minutes later. The
# tick confirms a launch with `--status` 60–120s afterwards and by READING the log.
sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "LAUNCHED run $n (pid $(cat "$PIDFILE")) coder=$CODER log=$LOG"
else
  echo "LAUNCH FAILED — check $LOG"; exit 1
fi
