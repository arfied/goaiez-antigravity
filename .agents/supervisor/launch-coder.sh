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

# Flags, in any order:
#   --allow-merge      opens the merge gate for THIS launch only (see the gate below).
#                      Pass it when, and only when, the brief's item is a merge from
#                      origin/main.
#   --coder agy|claude which coder runs the KICKOFF. Default agy (Antigravity).
#                      OWNER RULING 2026-09-05 17:1x: `claude` is the fallback after a
#                      SECOND consecutive "Individual quota reached" death, passed by
#                      hand-of-tick and recorded as `coder=claude` in the REVIEWS block
#                      that carries the LAUNCHED line. Never automatic.
ALLOW_MERGE=0
CODER=agy
while [ $# -gt 0 ]; do
  case "$1" in
    --allow-merge) ALLOW_MERGE=1; shift;;
    --coder) CODER="${2:-}"; shift 2 || { echo "REFUSED: --coder needs a value (agy|claude)"; exit 1; };;
    --coder=*) CODER="${1#--coder=}"; shift;;
    *) echo "REFUSED: unknown argument '$1' (expected --allow-merge, --coder agy|claude, --status)"; exit 1;;
  esac
done
case "$CODER" in
  agy|claude) ;;
  *) echo "REFUSED: --coder must be agy or claude, not '$CODER'"; exit 1;;
esac

if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "REFUSED: this track's coder is already active (pid $(cat "$PIDFILE"))"
  exit 1
fi
[ -s .agents/supervisor/KICKOFF.md ] || { echo "REFUSED: KICKOFF.md missing or empty"; exit 1; }


# Snapshot the supervisor's uncommitted files before every dispatch (a coder
# reset/checkout/stash wiped them once, 2026-09-02 15:31).
SNAP="/home/goaiez/tmp/sup-snap-$(basename "$PWD")-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$SNAP/.agents/supervisor" "$SNAP/.claude" "$SNAP/bin"
# `*` not `*.md` (2026-09-06, REV-58): the 05:28 truncation zeroed 426 mailbox
# files and the restore recovered only the .md ones, because that is all this
# line ever copied. The `tick*-gate.txt` logs every verdict block cites were
# unrecoverable. Copy everything; the directory is small and text.
cp .agents/supervisor/* "$SNAP/.agents/supervisor/" 2>/dev/null
cp .claude/settings.json "$SNAP/.claude/"
mkdir -p "$SNAP/.agents/state" && cp .agents/state/BUILD-STATE.json .agents/state/JOURNAL.md "$SNAP/.agents/state/" 2>/dev/null || true 2>/dev/null; cp CLAUDE.md "$SNAP/"; cp bin/supervise.sh "$SNAP/bin/"
echo "snapshot: $SNAP"

n=1
TRACK=$(basename "$PWD")
# Probe BOTH coders' log names so a run number is never reused across a fallback.
while [ -e "/home/goaiez/tmp/agy-${TRACK}-run${n}.log" ] \
   || [ -e "/home/goaiez/tmp/claude-${TRACK}-run${n}.log" ]; do n=$((n+1)); done
LOG="/home/goaiez/tmp/${CODER}-${TRACK}-run${n}.log"

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

# Merge gate — owner-approved 2026-09-05 13:2x. A merge writes never-list files
# (app/phpunit.xml, seals.json, app/app/Doctor/**, CLAUDE.md, the mailbox) with
# no `git commit`, so coder-bin/git's commit guard never sees it; the run-39
# merge is the near miss (git reported no conflict at all). The wrapper refuses
# merge/pull/cherry-pick/revert unless GOAIEZ_MERGE_OK=1 and only this launcher
# may set it. Closed unless the supervisor launches with --allow-merge, which is
# an explicit act at dispatch: it is deliberately NOT derived from BRIEF.md, for
# the same reason the push gate is not (that file is rewritten every tick).
MERGE_OK=0
if [ "${ALLOW_MERGE:-0}" = 1 ]; then MERGE_OK=1; fi
if [ "$MERGE_OK" = 1 ]; then echo "merge gate: OPEN (--allow-merge)"; else echo "merge gate: closed"; fi

# Both branches export the same two gates and the same PATH: coder-bin/git binds
# `claude` exactly as it binds `agy`, and so does the seal. The only differences are
# the binary, its flags and the log name.
#
# `--setting-sources user` on the claude branch is load-bearing: without it the coder
# would inherit THIS checkout's supervisor `.claude/settings.json`, which denies
# `app/**` — the coder's own column — and the run would refuse its whole brief.
if [ "$CODER" = claude ]; then
  nohup bash -c 'export GOAIEZ_PUSH_OK='"$PUSH_OK"'; export GOAIEZ_MERGE_OK='"$MERGE_OK"'; export PATH=/home/goaiez/agents/coder-bin:$PATH; timeout 8h /home/goaiez/.local/bin/claude -p "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --setting-sources user --output-format text < /dev/null > '"$LOG"' 2>&1; echo "CLAUDE_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
else
  nohup bash -c 'export GOAIEZ_PUSH_OK='"$PUSH_OK"'; export GOAIEZ_MERGE_OK='"$MERGE_OK"'; export PATH=/home/goaiez/agents/coder-bin:$PATH; /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout 8h < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
fi
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "LAUNCHED run $n (pid $(cat "$PIDFILE")) coder=$CODER log=$LOG"
else
  echo "LAUNCH FAILED — check $LOG"; exit 1
fi
