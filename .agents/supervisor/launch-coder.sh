#!/usr/bin/env bash
#
# Supervisor's coder dispatcher. Launches ONE Antigravity run on the current
# KICKOFF.md, detached, logging to /home/goaiez/tmp/agy-run<N>.log.
#
#   bash .agents/supervisor/launch-coder.sh                 # Antigravity (default)
#   bash .agents/supervisor/launch-coder.sh --coder claude  # REFUSED (owner 2026-09-11 14:0x: no
#                                                           # fallback coder; enabled 10:2x–14:0x
#                                                           # the same day, refused 09-10 before that)
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
ALLOW_RESTORE=0
while [ $# -gt 0 ]; do
  case "$1" in
    --coder) CODER="${2:-}"; shift 2;;  # 2026-09-23 03:0x — the OWNER RE-ENABLED `--coder claude` as the fallback coder ("is claude enabled as backup coder? if not, please enable it"), after all four agy runs died on the 429 quota at 02:56. Use it only when agy reports quota reached; record `coder=claude` from the LAUNCHED line in REVIEWS. History: disabled 2026-09-10, enabled 10:2x and disabled 14:0x 2026-09-11.
    --allow-merge) ALLOW_MERGE=1; shift;;   # opens the shared coder guard's merge gate (GOAIEZ_MERGE_OK=1) for THIS run only; the guard added it 2026-09-05 13:27
    --allow-harness) ALLOW_HARNESS=1; shift;;  # opens GOAIEZ_HARNESS_OK=1 for THIS run only (guard, 2026-09-06 17:2x). It opens the ABILITY TO COMMIT app/tests/Journeys/JourneyHarness.php, not permission to weaken it: quote the diff in REVIEWS, and a change that makes a journey easier to pass is a BLOCK.
    --allow-restore) ALLOW_RESTORE=1; shift;;  # opens GOAIEZ_RESTORE_OK=1 for THIS run only (owner ruling 2026-09-07, reserved-questions item 3B; guard clause added the same day). It permits `git checkout|restore -- <existing file paths>` and NOTHING else: no directory, no option, and supervisor-owned paths (.agents/supervisor, .agents/rules, .claude, CLAUDE.md, bin/supervise.sh, bin/state.py, any .env) stay refused inside it, because restoring one of those discards the supervisor's uncommitted notes — that is run 27. Restoring a SEALED file is the safe direction: it can only discard a local modification, never weaken a committed check.
    *) echo "REFUSED: unknown argument $1 (takes only --coder agy|claude, --allow-merge, --allow-harness, --allow-restore)"; exit 1;;
  esac
done
case "$CODER" in agy|claude) ;; *) echo "REFUSED: --coder must be agy or claude"; exit 1;; esac

# GATE/BRIEF AGREEMENT (2026-09-07, tick 125). Run 124 was dispatched on a KICKOFF that
# opened the merge and harness gates in prose while the flags were absent, so the run got
# GOAIEZ_MERGE_OK=0 / GOAIEZ_HARNESS_OK=0 and could not do the one thing it was briefed to
# do. `kill` is denied to this seat, so the mistake was unrecallable — which is exactly the
# class of error a launcher should refuse rather than a tick should remember.
#
# The needles are the supervisor's OWN deliberate phrasing in KICKOFF.md, not a generic word
# like "merge": a kickoff that says "closed" cannot match, and a kickoff that says neither is
# silent. Both matched KICKOFF.md when this was written (the positive control — a needle that
# has never matched anything is not an instrument, wave 122). Fails OPEN by construction: if a
# needle ever stops matching, the dispatch proceeds exactly as it did before this block.
if grep -q 'Merge gate \*\*OPEN' .agents/supervisor/KICKOFF.md && [ "$ALLOW_MERGE" = 0 ]; then
  echo "REFUSED: KICKOFF.md declares 'Merge gate **OPEN' but --allow-merge was not passed."
  echo "         The run would export GOAIEZ_MERGE_OK=0 and the shared guard would refuse the merge."
  exit 1
fi
if grep -q 'Harness gate \*\*OPEN' .agents/supervisor/KICKOFF.md && [ "$ALLOW_HARNESS" = 0 ]; then
  echo "REFUSED: KICKOFF.md declares 'Harness gate **OPEN' but --allow-harness was not passed."
  echo "         The run would export GOAIEZ_HARNESS_OK=0 and could not commit the merged harness."
  exit 1
fi
# N164 (2026-09-10, tick 303): the two arms above read KICKOFF.md ONLY, and the CODER READS
# BRIEF.md. Tick 303 found the standing wave-269 brief declaring "Merge gate **OPEN** for this
# run" at item 2 while KICKOFF.md said "Merge gate closed for this run" — a bare dispatch would
# have passed both arms above, exported GOAIEZ_MERGE_OK=0, and handed the coder a brief telling
# it the gate was open. That is N103 with the two sources of truth moved one file across, and
# the guard written for N103 could not see it: it was watching the file the supervisor declares
# in, not the file the coder obeys. Same needles, same fail-open construction (a brief that says
# neither is silent; a brief that says "closed" cannot match). Positive control at the time of
# writing: the needle read 0 in BRIEF.md and KICKOFF.md after tick 303's correction, and 16 in
# REVIEWS.md — it matches text of this shape, it just does not match a correct mailbox.
if grep -q 'Merge gate \*\*OPEN' .agents/supervisor/BRIEF.md && [ "$ALLOW_MERGE" = 0 ]; then
  echo "REFUSED: BRIEF.md declares 'Merge gate **OPEN' but --allow-merge was not passed."
  echo "         The coder reads BRIEF.md; the run would export GOAIEZ_MERGE_OK=0 and the shared guard would refuse the merge."
  exit 1
fi
if grep -q 'Harness gate \*\*OPEN' .agents/supervisor/BRIEF.md && [ "$ALLOW_HARNESS" = 0 ]; then
  echo "REFUSED: BRIEF.md declares 'Harness gate **OPEN' but --allow-harness was not passed."
  echo "         The coder reads BRIEF.md; the run would export GOAIEZ_HARNESS_OK=0 and could not commit the merged harness."
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
# N104 (2026-09-07, tick 125): the glob above is `*.md`, so the one supervisor file most
# likely to be edited AT dispatch time — this script — was the one the dispatch-time
# snapshot did not preserve. A protection whose scope was stated once and never read back.
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
  nohup bash -c 'export GOAIEZ_MERGE_OK='"$ALLOW_MERGE"'; export GOAIEZ_HARNESS_OK='"$ALLOW_HARNESS"'; export GOAIEZ_RESTORE_OK='"$ALLOW_RESTORE"'; export PATH=/home/goaiez/agents/coder-bin:$PATH; export BASH_ENV=/home/goaiez/agents/coder-bin/shell-init.sh; timeout -k 60 3h /home/goaiez/.local/bin/claude -p "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --setting-sources user --output-format text < /dev/null > '"$LOG"' 2>&1; echo "CLAUDE_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
else
  # BOUND (2026-09-07, backlog item 1 of tick ~01:4x, taken deliberately rather than
  # inherited from the sixty lane's copy by a merge). `--print-timeout 8h` is agy's OWN
  # timer and is exactly the thing a hung agy stops honouring, so the enforcer is the
  # outer `timeout`: 3h, then SIGKILL 60s later. Track 1 builds nothing — its longest
  # honest wave is one gate plus a 40-minute pest-lock wait — so 3h bounds a hang and
  # never a run. The inner 8h is left where it is precisely so there is ONE effective
  # number and it is the outer one; `bound=3h` is printed in the LAUNCHED line so the
  # value is read back rather than asserted (the drift shape, CLAUDE.md).
  nohup bash -c 'export GOAIEZ_MERGE_OK='"$ALLOW_MERGE"'; export GOAIEZ_HARNESS_OK='"$ALLOW_HARNESS"'; export GOAIEZ_RESTORE_OK='"$ALLOW_RESTORE"'; export PATH=/home/goaiez/agents/coder-bin:$PATH; export BASH_ENV=/home/goaiez/agents/coder-bin/shell-init.sh; timeout -k 60 3h /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
fi
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  # Both gates are printed. Until 2026-09-06 18:4x only merge-gate was, while a REVIEWS
  # block claimed "harness-gate in the LAUNCHED line" — the drift shape from CLAUDE.md:
  # two sources of truth in one file, only one of them read back.
  echo "LAUNCHED run $n (pid $(cat "$PIDFILE")) coder=$CODER bound=3h merge-gate=$([ "$ALLOW_MERGE" = 1 ] && echo OPEN || echo closed) harness-gate=$([ "$ALLOW_HARNESS" = 1 ] && echo OPEN || echo closed) log=$LOG"
else
  echo "LAUNCH FAILED — check $LOG"; exit 1
fi
