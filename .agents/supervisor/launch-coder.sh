#!/usr/bin/env bash
#
# Supervisor's coder dispatcher. Launches ONE coder run on the current
# KICKOFF.md, detached, logging to /home/goaiez/tmp/<coder>-<track>-run<N>.log.
#
#   bash .agents/supervisor/launch-coder.sh                 # Antigravity (default)
#   bash .agents/supervisor/launch-coder.sh --coder claude   # Claude Code fallback
#   bash .agents/supervisor/launch-coder.sh --status         # liveness, read-only
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
MODE=launch

# Owner ruling 2026-09-06 03:5x, ui item 4 ("a shorter --print-timeout / log-based
# liveness in launch-coder.sh: yours, do it").
#
# 8h was the wrong cap. Every healthy wave on this track has closed in 9-40
# minutes; what 8h actually bought was run 63 stalling 76 minutes and run 67
# deadlocking two concurrent `pest` processes against one database with no bound
# at all, both of which cost the track a whole night of ten-minute ticks that
# could only watch. 90m is generous against the observed distribution and turns
# a stall into ONE tick instead of forty-eight.
#
# The outer `timeout` is the belt to that braces: --print-timeout is agy's own
# cooperative deadline, so it cannot fire when agy is blocked in a child that
# never returns — which is precisely the run 67 shape. HARD_TIMEOUT kills the
# process group from outside and needs nothing from the coder.
# Both are overridable for a wave that genuinely needs longer; say so in the
# REVIEWS block that dispatches it.
PRINT_TIMEOUT="${GOAIEZ_PRINT_TIMEOUT:-90m}"
HARD_TIMEOUT="${GOAIEZ_HARD_TIMEOUT:-2h}"

while [ $# -gt 0 ]; do
  case "$1" in
    --status) MODE=status; shift ;;
    --coder)
      [ $# -ge 2 ] || { echo "REFUSED: --coder needs a value (agy|claude)"; exit 1; }
      case "$2" in
        agy|claude) CODER="$2" ;;
        *) echo "REFUSED: --coder takes agy|claude (got '$2')"; exit 1 ;;
      esac
      shift 2 ;;
    *) echo "REFUSED: unknown argument '$1' (only --coder agy|claude, --status)"; exit 1 ;;
  esac
done

cd "$(dirname "$0")/../.." || exit 1

PIDFILE=".agents/supervisor/coder.pid"

# ── --status: the log-based liveness check (owner 2026-09-06 03:5x, ui item 4).
#
# The tick cannot answer "is this run working or hung?" from its own tool calls.
# `ps` is denied to the supervisor; so are `ls`, `find` and `tail` under
# /home/goaiez/tmp, where the coder log lives — measured 2026-09-06 04:1x, all
# three blocked as outside the session's working directory. So every fact below
# has to be gathered by a script the supervisor is allowed to RUN, which is this
# one. It writes nothing and signals nothing; it is safe against a live coder.
#
# Two clocks, because they fail differently. The LOG clock catches a coder that
# has stopped thinking. The TREE clock catches a coder that is blocked inside a
# child — run 67's two deadlocked `pest` processes wrote nothing anywhere for 72
# minutes while both the pid and the log looked exactly like a long wave.
if [ "$MODE" = status ]; then
  # This block is read-only, and every pipeline in it ends in `head -1`, which
  # SIGPIPEs its producer — under the file's `set -euo pipefail` that is exit
  # 141 and no report. Relax both here and nowhere else.
  set +e +o pipefail
  TRACK=$(basename "$PWD")
  now=$(date +%s)

  pid=""; [ -f "$PIDFILE" ] && pid=$(cat "$PIDFILE" 2>/dev/null)
  if [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null; then alive=yes; else alive=no; fi
  echo "coder.pid: ${pid:-<none>} alive=$alive"

  # ⚠️ The pidfile holds the `nohup bash` parent, and it can be dead while the
  # real coder is alive (run 67) or hold a stale pid for a whole tick (runs
  # 65/66). The process table is the authority; `pgrep -f` on the checkout name
  # matches the coder's own prompt, which always names it.
  # ⚠️ Truncate hard. The coder's command line IS the whole KICKOFF.md — 8 KB on
  # one line — so an untruncated listing buries every other fact in this report.
  echo "-- agy/claude processes naming $TRACK --"
  { pgrep -af "$TRACK" || true; } | grep -E '\.local/bin/(agy|claude)' \
    | cut -c1-120 | sed 's/$/ …/;s/^/  /' || echo "  none"

  LOG=$( { ls -1t /home/goaiez/tmp/agy-"$TRACK"-run*.log \
                  /home/goaiez/tmp/claude-"$TRACK"-run*.log 2>/dev/null || true; } | head -1)
  if [ -n "$LOG" ]; then
    lage=$(( (now - $(stat -c %Y "$LOG")) / 60 ))
    echo "log: $LOG  ${lage}m since last write  $(stat -c %s "$LOG") bytes"
    echo "  tail: $(tail -n 2 "$LOG" 2>/dev/null | tr '\n' ' ' | cut -c1-200)"
  else
    echo "log: <none for $TRACK>"
  fi

  # Newest write anywhere the coder actually works. Excludes .git, the module
  # dirs and .agents/supervisor — the last because the SUPERVISOR writes there
  # every tick, so including it would make a dead run look alive forever.
  newest=$(find . -path ./.git -prune -o -path ./node_modules -prune \
                -o -path ./app/vendor -prune -o -path ./app/node_modules -prune \
                -o -path ./.agents/supervisor -prune \
                -o -type f -printf '%T@ %p\n' 2>/dev/null | sort -rn | head -1)
  if [ -n "$newest" ]; then
    tage=$(( (now - ${newest%%.*}) / 60 ))
    echo "tree: ${tage}m since last write  ${newest#* }"
  else
    tage=999; echo "tree: <no files>"
  fi

  # Suites still holding THIS checkout's test database, identified by /proc cwd
  # and never by name — a sibling track's pest matches every name pattern there
  # is, and its cwd can never equal ours. This has to be separate from
  # Descendants: when the coder dies its suites are reparented to init, so they
  # are descendants of nothing and the tree walk above cannot see them. A tick
  # that runs its own gate with one of these alive gets the run 67 deadlock.
  # ⛔ NOT `pgrep -f 'vendor/bin/pest'` and NOT `pgrep -p` (no such option).
  # Measured 2026-09-06 04:4x against the live run 68: `-f` matched the coder's own
  # agy process, because the coder's command line IS the whole KICKOFF and BRIEF and
  # those name `vendor/bin/pest` in their text. Three conditions instead, none a name
  # match: comm is php* (excludes agy, node, timeout, bash and any prompt text), cwd
  # is under this checkout (excludes every sibling track), cmdline names pest
  # (excludes `php artisan` here).
  echo "-- pest with cwd in this checkout (holds the test database) --"
  orph=""
  for d in /proc/[0-9]*; do
    p=${d#/proc/}
    # 2>/dev/null on `read` does NOT silence a failed *redirect* — bash reports
    # "No such file or directory" itself, and a pid that exits mid-scan is the
    # normal case, not a finding. Seen 2026-09-06 06:5x on pid 2000446. Test the
    # file first; the race can still lose, hence the `|| continue` as well.
    [ -r "$d/comm" ] || continue
    { read -r comm < "$d/comm"; } 2>/dev/null || continue
    case "$comm" in php*) ;; *) continue ;; esac
    c=$(readlink "$d/cwd" 2>/dev/null) || continue
    case "$c" in "$PWD"|"$PWD"/*) ;; *) continue ;; esac
    grep -qa 'vendor/bin/pest' "$d/cmdline" 2>/dev/null || continue
    orph="$orph $p"
    echo "  $p  $(tr '\0' ' ' < "$d/cmdline" 2>/dev/null | cut -c1-110)"
  done
  [ -n "$orph" ] && echo "  ⚠️ still holding the database:$orph — the gate will refuse to start a second"
  [ -z "$orph" ] && echo "  none"

  # STALLED is a claim about both clocks at once, so a coder that is merely
  # quiet (thinking, or in a long single test) does not trip it.
  if ! { pgrep -af "$TRACK" || true; } | grep -qE '\.local/bin/(agy|claude)'; then
    echo "VERDICT: DEAD — review the REPORT as case (b)."
  elif [ "${tage:-0}" -ge 30 ]; then
    echo "VERDICT: STALLED — no write in the checkout for ${tage}m. Descendants:"
    # Only descendants of the coder itself, so another track's suite can never
    # appear in this list and be mistaken for ours (the `pkill -f pest` trap).
    #
    # ⚠️ Walk the WHOLE subtree, printing each pid with its parent. The 04:1x
    # version printed grandchildren only and never their parents, so the pids
    # it listed could not be reached by `pkill -P <coder>` at all — the kill
    # would have kept the intermediate shells' children orphaned and still
    # holding the database. Print parentage and the kill is unambiguous.
    coder=$( { pgrep -f "$TRACK" || true; } | tail -1)
    walk() {  # walk <pid> <depth>
      [ "${2:-0}" -gt 6 ] && return
      for c in $( { pgrep -P "$1" || true; } ); do
        printf '    %*sppid %-8s %s\n' $(( ${2:-0} * 2 )) '' "$1" \
          "$( { pgrep -a -P "$1" || true; } | grep -E "^$c " | cut -c1-110)"
        walk "$c" $(( ${2:-0} + 1 ))
      done
    }
    walk "$coder" 0
    echo "  ⚠️ Killing them is an OWNER ACTION: kill/pkill are both refused to the supervisor"
    echo "     (measured 2026-09-06 04:1x). Record the pids, do not route around the refusal."
  else
    echo "VERDICT: WORKING — wrote ${tage}m ago."
  fi
  exit 0
fi

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


# ── Run 68, 2026-09-06 04:4x. The supervisor committed cc6bda58, then kept
# editing bin/supervise.sh and this file, then dispatched over the dirty tree.
# The coder's next commit swept both in: d625bae5 "chore(X-199): fix customer_id
# table in seeder, with person" carries 37 lines of supervisor gate logic, and
# because supervise.sh §2 only ever looked at HEAD~1..HEAD it reported "none".
# The coder is not at fault for a file it never opened — the dispatch is. A
# supervisor-owned path that is dirty at dispatch WILL end up in a coder commit,
# so refuse here rather than discover it two commits later.
dirty_sup=$(git status --porcelain -- .agents/supervisor/launch-coder.sh bin CLAUDE.md .claude 2>/dev/null)
if [ -n "$dirty_sup" ]; then
  echo "REFUSED: supervisor-owned files are uncommitted — commit them as chore(supervisor) first."
  printf '%s\n' "$dirty_sup" | sed 's/^/         /'
  echo "         (BRIEF.md, REVIEWS.md, KICKOFF.md and .tick-* are mailbox files and are not checked here.)"
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
#
# `timeout -k` on both branches: TERM first, then KILL 60s later if the coder
# ignores it. Without -k a coder blocked in an unkillable child survives its own
# timeout, which is the run 67 failure with one extra step.
echo "timeouts: print=$PRINT_TIMEOUT hard=$HARD_TIMEOUT"
if [ "$CODER" = claude ]; then
  nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; timeout -k 60 '"$HARD_TIMEOUT"' /home/goaiez/.local/bin/claude -p "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --setting-sources user --output-format text < /dev/null > '"$LOG"' 2>&1; echo "CLAUDE_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
else
  nohup bash -c 'export PATH=/home/goaiez/agents/coder-bin:$PATH; timeout -k 60 '"$HARD_TIMEOUT"' /home/goaiez/.local/bin/agy --print "$(cat .agents/supervisor/KICKOFF.md)" --dangerously-skip-permissions --effort high --print-timeout '"$PRINT_TIMEOUT"' < /dev/null > '"$LOG"' 2>&1; echo "AGY_EXIT=$?" >> '"$LOG"'' > /dev/null 2>&1 &
fi
echo $! > "$PIDFILE"

sleep 2
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "LAUNCHED run $n coder=$CODER (pid $(cat "$PIDFILE")) log=$LOG"
else
  echo "LAUNCH FAILED (coder=$CODER) — check $LOG"; exit 1
fi
