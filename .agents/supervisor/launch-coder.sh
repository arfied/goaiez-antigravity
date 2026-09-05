#!/usr/bin/env bash
#
# Supervisor's coder dispatcher. Launches ONE coder run on the current
# KICKOFF.md, detached, logging to /home/goaiez/tmp/<coder>-<track>-run<N>.log.
#
#   bash .agents/supervisor/launch-coder.sh                 # Antigravity (default)
#   bash .agents/supervisor/launch-coder.sh --coder claude   # Claude Code fallback
#
# Refuses to start if a coder is already running (never two in one tree).
set -euo pipefail

# Owner item 17 (2026-09-05 08:3x). The launcher auto-numbers its run, so any
# argument that is not the one flag below is a caller that thinks it is passing a
# brief, a run number or something else that would be silently discarded.
#
# Owner ruling 2026-09-05 17:1x, item 2. Antigravity dies on "Individual quota
# reached" whenever all eight tracks are busy on the one account. After a SECOND
# quota death the supervisor may launch the same KICKOFF.md with Claude Code on
# the DEFAULT account. Never automatic: the tick passes --coder claude by hand
# and records `coder=claude` in the REVIEWS block that quotes the LAUNCHED line.
# Antigravity stays the default and the first launch after a reset is agy.
CODER=agy
while [ $# -gt 0 ]; do
  case "$1" in
    --coder)
      [ $# -ge 2 ] || { echo "REFUSED: --coder needs a value (agy|claude)"; exit 1; }
      case "$2" in
        agy|claude) CODER="$2" ;;
        *) echo "REFUSED: --coder takes agy|claude (got '$2')"; exit 1 ;;
      esac
      shift 2 ;;
    *) echo "REFUSED: unknown argument '$1' (only --coder agy|claude)"; exit 1 ;;
  esac
done

cd "$(dirname "$0")/../.." || exit 1

PIDFILE=".agents/supervisor/coder.pid"
if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "REFUSED: this track's coder is already active (pid $(cat "$PIDFILE"))"
  exit 1
fi
[ -s .agents/supervisor/KICKOFF.md ] || { echo "REFUSED: KICKOFF.md missing or empty"; exit 1; }

# Owner item 17, second half. A KICKOFF.md older than the newest REVIEWS.md block
# is a stale directive: the supervisor has since judged something and has not
# rewritten the kickoff, so the coder would re-execute the previous wave. Run 55
# was exactly this and nothing refused it. REVIEWS.md is append-only, so its mtime
# IS the timestamp of its newest block; a tick that dispatches always writes the
# verdict, then BRIEF.md, then KICKOFF.md, so a correct dispatch is never refused.
if [ .agents/supervisor/REVIEWS.md -nt .agents/supervisor/KICKOFF.md ]; then
  echo "REFUSED: KICKOFF.md is older than the newest REVIEWS.md block — stale directive."
  echo "         Write the verdict block, then BRIEF.md, then KICKOFF.md, then dispatch."
  ls -la --time-style=full-iso .agents/supervisor/REVIEWS.md .agents/supervisor/KICKOFF.md
  exit 1
fi


# Snapshot the supervisor's uncommitted files before every dispatch (a coder
# reset/checkout/stash wiped them once, 2026-09-02 15:31).
SNAP="/home/goaiez/tmp/sup-snap-$(basename "$PWD")-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$SNAP/.agents/supervisor" "$SNAP/.claude" "$SNAP/bin"
cp .agents/supervisor/*.md "$SNAP/.agents/supervisor/" 2>/dev/null
cp .claude/settings.json "$SNAP/.claude/" 2>/dev/null; cp CLAUDE.md "$SNAP/"; cp bin/supervise.sh "$SNAP/bin/"
echo "snapshot: $SNAP"

# ── The push gate is the BRIEF's `push:` line (CLAUDE.md), so derive the flag
# from the document rather than from the launcher's own opinion.
# `coder-bin/git:30` refuses `git push` unless GOAIEZ_PUSH_OK=1, and until
# 2026-09-04 NOTHING ever set it — its own error message says "launcher sets
# GOAIEZ_PUSH_OK=1" and the launcher did not. So `push: OPEN` was unenforceable
# and run 42 stopped on item 0 with a correct UNRESOLVED. Fixed here, not by
# loosening the guard.
PUSHLINE=$(grep -m1 -i '^push:' .agents/supervisor/BRIEF.md 2>/dev/null || true)
case "$PUSHLINE" in
  *CLOSED*|*closed*)          PUSH_OK=0 ;;
  *OPEN*|*open*|*FREE*|*free*) PUSH_OK=1 ;;
  *)                          PUSH_OK=0 ;;   # no line, or unrecognised → closed
esac
export GOAIEZ_PUSH_OK="$PUSH_OK"
echo "push gate: GOAIEZ_PUSH_OK=$PUSH_OK  <-  ${PUSHLINE:-<no push: line in BRIEF.md>}"

# One run-number sequence across BOTH coders, so a claude run can never reuse an
# agy run's number and the REVIEWS ledger stays readable as one history.
n=1
TRACK=$(basename "$PWD")
while [ -e "/home/goaiez/tmp/agy-${TRACK}-run${n}.log" ] \
   || [ -e "/home/goaiez/tmp/claude-${TRACK}-run${n}.log" ]; do n=$((n+1)); done
LOG="/home/goaiez/tmp/${CODER}-${TRACK}-run${n}.log"

# Both branches put coder-bin FIRST on PATH: the git guard and the seal bind the
# fallback coder exactly as they bind agy (owner 17:1x, item 2). For claude,
# --setting-sources user keeps the SUPERVISOR's .claude/settings.json — which
# denies app/** — out of the coder's permissions.
if [ "$CODER" = claude ]; then
  nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; timeout 8h /home/goaiez/.local/bin/claude -p "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --setting-sources user --output-format text < /dev/null > '"$LOG"' 2>&1; echo "CLAUDE_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
else
  nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
fi
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "LAUNCHED run $n coder=$CODER (pid $(cat "$PIDFILE")) log=$LOG"
else
  echo "LAUNCH FAILED (coder=$CODER) — check $LOG"; exit 1
fi
