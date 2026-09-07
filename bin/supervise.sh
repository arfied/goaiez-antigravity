#!/usr/bin/env bash
#
# The supervisor's read-only gate.  Changes nothing.  Safe at any time.
#
#   bash bin/supervise.sh                # guard · tree · state · integrity · pint · phpstan
#   bash bin/supervise.sh --tests        # + pest, against phpunit.xml's database
#   bash bin/supervise.sh --full-doctor  # + all eight doctor stages
#   bash bin/supervise.sh --tests --filter X-199   # narrow the suite (see 7b)
#
# Exit 2 = a database points at production. Exit 1 = a gate failed. Exit 0 =
# gates green, which is necessary and not sufficient: now read the diff.
#
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1
ROOT="$PWD"; APP="$ROOT/app"
PROD_DB="goaiez_antig"
# This lane's two databases, and the only two names §0 accepts. A blacklist of one
# could not catch goaiez_antig_test (Track 1's) when a merge wrote it into
# app/phpunit.xml on 2026-09-06; an allowlist catches every wrong name there is.
LANE_DB="goaiez_antig_ui"
LANE_TEST_DB="goaiez_antig_ui_test"
want_tests=0; want_doctor=0; pest_filter=""
while [ $# -gt 0 ]; do
  case "$1" in
    --tests) want_tests=1;;
    --full-doctor) want_doctor=1;;
    --filter) pest_filter="${2:-}"; shift;;
    --filter=*) pest_filter="${1#--filter=}";;
  esac
  shift
done
bar() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
fail=0

# ── The shared gate log (Track 1, OWNER.md 2026-09-06 16:0x, item 3).
#
# One TSV line per tool run, across every checkout and both projects on this box.
# ⛔ The rc is written RAW and is never normalised to 0/1 — the raw value IS the
# signal: 143 SIGTERM, 137 SIGKILL, 124 `timeout(1)`'s own, anything else the
# tool's own verdict. `tool` matters because pint, phpstan and pest are three
# populations and a kill lands on whichever happens to be running.
# ⚠️ NO LOCK, deliberately. An O_APPEND write under PIPE_BUF is atomic on Linux,
# and a flock here would interact with the pest lock below for nothing.
#
# ⚠️⚠️ EIGHT COLUMNS, NOT SEVEN (Track 1 correction, OWNER.md 2026-09-06 16:5x).
# The shape Track 1 first sent was seven-wide and had since gained a column they
# did not re-send. Nothing in a row announces its own width, so a field-index
# parser is silently wrong in the column that carries the whole point:
#   7-column row: $4 is rc (0,1,2)   ·   8-column row: $4 is tool_pid (1226723)
# Read $4 as rc and every 8-column row looks like a failure with a seven-digit
# code; read $5 as rc and every 7-column row reads the project name. History
# stays mixed and is NOT migrated — the log is append-only. Any consumer
# branches on NF.
#
#   start_iso  end_iso  gate_pid  tool_pid  rc  project  checkout  tool
#
# `project` is goaiez-antigravity — the project, not the directory; `checkout` is
# the directory basename. Track 1 wrote grs-antig in both and split their own
# traffic on any group-by; we had this right and keep it.
# `tool_pid` is the join key against /home/goaiez/tmp/kill-log.tsv. `gate_pid`
# only groups one gate run's rows together.
#
# ⚠️ `tool` IS A CLOSED VOCABULARY OF FIVE, NOT FREE TEXT (Track 1 correction,
# OWNER.md 2026-09-06 17:1x). Track 1 first said it was free and the shared file
# already holds four spellings of the same thing, which splits a group-by on the
# one column that exists to be grouped. Exactly these and nothing else:
#
#   gate | pint | phpstan | pest | doctor
#
# `gate` covers BOTH sentinels — a consumer tells start from end by `rc` (`-` on
# the start row), never by inventing `gate-start`/`gate-end`. `supervise.sh` is
# not a tool, it IS the gate, so it logs as `gate`. Anything narrower collapses
# to its family: `pest-not-run` is now `pest`, and the lock-timeout row is still
# unmistakable because it carries `tool_pid -` — no process was ever started, so
# the join key is the tell rather than a private tool name.
#
# ⚠️ `GATE_LOG` IS OVERRIDABLE, AND THAT IS A SAFETY PROPERTY (same correction).
# The sibling project's pre-push test executes the real hook against no-op stubs
# on a rigged PATH; once the hook logged, the TEST began appending rows for tools
# that never ran — twelve rows, eight columns, correct types, distinct tool_pid,
# plausible rc. Every property designed into the schema was satisfied and the
# only tell was a `pest` row whose start and end were the same second. No test in
# this project touches the gate today, so this is prevention: any test that ever
# exercises the gate points GATE_LOG at a throwaway path. A diagnostic log that
# records its own harness is worse than no log, because the fabrications have
# exactly the shape of the evidence.
#
# ⚠️ WHEN READING THE SHARED FILE, filter twelve fabricated rows from
# goaiez-review-system/wt10 under gate pids
# `1411718 1411764 1425556 1425618 1468140 1468184`, 16:49–17:00 on 2026-09-06.
# They are not being deleted — the file is append-only and a rewrite would race
# our own writers.
GATE_LOG=${GATE_LOG:-/home/goaiez/tmp/gate-runs.tsv}
log_gate() {  # <start_iso> <end_iso> <gate_pid> <tool_pid> <rc> <tool>
  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
    "$1" "$2" "$3" "$4" "$5" "goaiez-antigravity" "$(basename "$ROOT")" "$6" \
    >> "$GATE_LOG" 2>/dev/null || true
}

# ── The gate sentinel: one row when this script starts, one when it exits.
#
# Without it, a gate killed at the wrapper is indistinguishable from one that
# never ran at all. Two defects Track 1 already paid for and we inherit fixed:
#   1. Define it at the TOP of the script. Theirs sat down where the tools run
#      and recorded nothing for a gate killed two seconds in.
#   2. `trap - EXIT` goes INSIDE the signal traps. Theirs wrote rc=143 and then
#      the EXIT trap fired and wrote rc=0, so the last row read clean — the
#      honest row existed and a later, cleaner row hid it.
GATE_START_ISO=$(date -Iseconds)
log_gate "$GATE_START_ISO" "-" "$$" "-" "-" "gate"
_gate_exit() { log_gate "$GATE_START_ISO" "$(date -Iseconds)" "$$" "-" "$1" "gate"; }
trap '_gate_exit $?' EXIT
trap 'trap - EXIT; _gate_exit 143; exit 143' TERM
trap 'trap - EXIT; _gate_exit 130; exit 130' INT

# ── Run one gate tool and read ITS OWN exit code.
#
# ⛔⛔ NEVER `tool 2>&1 | tail -3 | sed … || fail=1`. That was lines 127–128 until
# 2026-09-06 16:1x and it was DECORATIVE: `||` binds to the pipeline, `sed` is
# last, `sed` always exits 0, so `fail=1` never fired once in the life of this
# gate — a genuinely red pint or phpstan has never failed §6. (`set -o pipefail`
# does not save it: pipefail sets the pipeline's status from the *rightmost
# failing* command, and there was none.) Track 1 found the same hole on their
# side from the other end: a KILLED Pint prints a bare `Terminated`, which reads
# exactly like a style red and was about to be recorded as one.
#
# ⚠️ `out=$(cmd)` gives a correct rc but NO PID, and tool_pid is the join key
# against kill-log.tsv. So the tool is backgrounded into a tempfile instead.
# The `pgrep -P` descent is not optional for anything wrapped: `timeout 1800
# pest` makes `timeout` the job and `php ./vendor/bin/pest` the process a killer
# actually sees in `ps` — log the wrapper's pid and the join fails silently in
# exactly the case the log exists for.
gate_tool() {  # <tool-name> <cmd...>
  local label="$1"; shift
  local start end out rc tmp jobpid tpid child
  start=$(date -Iseconds)
  tmp=$(mktemp "${TMPDIR:-/tmp}/gate-XXXXXX")
  "$@" > "$tmp" 2>&1 &
  jobpid=$!; tpid=$jobpid
  child=$(pgrep -P "$jobpid" 2>/dev/null | head -1)
  [ -n "$child" ] && tpid=$child
  wait "$jobpid"; rc=$?
  out=$(cat "$tmp"); rm -f "$tmp"
  end=$(date -Iseconds)
  printf '%s\n' "$out" | tail -4 | sed 's/^/  /'
  log_gate "$start" "$end" "$$" "$tpid" "$rc" "$label"
  if [ "$rc" -eq 124 ] || [ "$rc" -gt 128 ]; then
    echo "  ⛔ $label was KILLED or timed out (rc=$rc) — this is NOT a verdict."
    echo "     Do not report it as a style or analysis failure. Re-run it."
    fail=1
  elif [ "$rc" -ne 0 ]; then
    echo "  ⛔ $label FAILED (rc=$rc) — a real verdict."
    fail=1
  fi
}

bar "0. database guard  (allowlist: .env=$LANE_DB · phpunit.xml=$LANE_TEST_DB)"
env_db=$(grep -E '^DB_DATABASE=' "$APP/.env" 2>/dev/null | head -1 | cut -d= -f2- | tr -d "\"' ")
xml_db=$(grep -oE 'name="DB_DATABASE" value="[^"]*"' "$APP/phpunit.xml" 2>/dev/null | sed -E 's/.*value="([^"]*)"/\1/')
echo "  app/.env         DB_DATABASE=${env_db:-<unset>}"
echo "  app/phpunit.xml  DB_DATABASE=${xml_db:-<unset>}"
# A wrong name is exit 2 whether or not it is production: goaiez_antig is PRODUCTION
# (2026-08-31, NEXT-SESSION.md), goaiez_antig_test and goaiez_antig_dev are Track 1's,
# and a suite run inside another track's database is the same drop-the-schema shape.
guard_db() {  # <label> <value> <expected>
  case "$2" in
    "$3") return 0;;
    "") echo "  ⚠ $1 has no DB_DATABASE — expected $3. Nothing below is trustworthy."; fail=1; return 0;;
    "$PROD_DB") echo "  ⛔ $1 points at PRODUCTION ($PROD_DB). Stop. Nothing below may run."; exit 2;;
    *) echo "  ⛔ $1 is $2, not this lane's $3. Stop — that is another track's database."; exit 2;;
  esac
}
guard_db "app/.env        " "$env_db" "$LANE_DB"
guard_db "app/phpunit.xml " "$xml_db" "$LANE_TEST_DB"
[ $fail -eq 0 ] && echo "  both on this lane"

bar "1. working tree"
git status --short | head -40
echo "  $(git status --short | wc -l) uncommitted path(s)"
git log --oneline -5 | sed 's/^/  /'
git rev-list --left-right --count origin/main...HEAD 2>/dev/null \
  | awk '{print "  vs origin/main (local ref): behind " $1 ", ahead " $2 "  — refresh with: git fetch --no-write-fetch-head origin"}'

bar "1a. uncommitted PHP that boots the framework  (walks around the §0 database pin)"
# A script that require()s bootstrap/app.php reads app/.env, never app/phpunit.xml, so §0's pin
# does not apply to it and nothing rolls its writes back. pint only sees files under app/; above
# app/ there was no tell at all. This is that tell, and it is not width-limited.
boot_hits=""
for p in $(git status --porcelain | awk '{print $NF}' | grep -E '\.php$' || true); do
  [ -f "$ROOT/$p" ] || continue
  if grep -qE "bootstrap/app\.php|Contracts\\\\Console\\\\Kernel|Foundation\\\\Application" "$ROOT/$p"; then
    boot_hits="$boot_hits$p
"
  fi
done
if [ -n "$boot_hits" ]; then
  printf '%s' "$boot_hits" | sed 's/^/  ⛔ boots the framework outside PHPUnit — reads app\/.env, NOT phpunit.xml: /'
  echo "  To see a screen, write a throwaway test and --filter it: that loads the pin and rolls back."
  fail=1
else
  echo "  none"
fi

bar "2. forbidden paths touched  (uncommitted + last commit)"
touched=$( { git diff --name-only HEAD~1 HEAD 2>/dev/null; } | sort -u)
sup_edits=$(git diff --name-only HEAD -- .agents/supervisor CLAUDE.md bin/supervise.sh 2>/dev/null)
[ -n "$sup_edits" ] && printf '%s\n' "$sup_edits" | sed 's/^/  ℹ supervisor working notes (uncommitted — leave them alone): /'
touched=$(printf '%s\n%s' "$touched" "$(git diff --name-only HEAD | grep -vE '^(\.agents/supervisor/|CLAUDE\.md$|bin/supervise\.sh$)')" | sort -u | grep -v '^$')
pat='^app/app/Doctor/|seals\.json$|tests/Journeys/JourneyHarness\.php$|^app/Modules/[^/]+/(manifest|capabilities)\.php$|(^|/)\.env(\.|$)|^app/phpunit\.xml$|^source/|^runtime/|^bin/state\.py$|^\.agents/supervisor/(BRIEF|REVIEWS)\.md$'
hits=$(printf '%s\n' "$touched" | grep -E "$pat" || true)
if [ -n "$hits" ]; then
  printf '%s\n' "$hits" | sed 's/^/  ⛔ /'
  echo "  (manifest/capabilities are legal only via regeneration; supervisor files are legal only from the supervisor)"
  fail=1
else
  echo "  none"
fi

bar "2a. rewrite ledger (amends/rebases are recorded by the post-rewrite hook)"
if [ ! -x "$(git -C "$ROOT" rev-parse --git-path hooks)/post-rewrite" ]; then
  echo "  ⛔ post-rewrite hook is MISSING — its absence is a finding"; fail=1
elif [ -s "$ROOT/.agents/supervisor/REWRITES.log" ]; then
  tail -6 "$ROOT/.agents/supervisor/REWRITES.log" | sed 's/^/  ⛔ /'; fail=1
else
  echo "  empty — no history rewrites since the ledger began"
fi

bar "2b. php -l on every PHP file in that set"
bad=0
for f in $(printf '%s\n' "$touched" | grep -E '\.php$'); do
  [ -f "$ROOT/$f" ] || continue
  if ! php -l "$ROOT/$f" >/dev/null 2>&1; then echo "  ⛔ parse error: $f"; bad=1; fi
done
[ $bad -eq 0 ] && echo "  all parse" || fail=1

bar "3. build state"
python3 "$ROOT/bin/state.py" status 2>&1 | head -30 | sed 's/^/  /'
python3 "$ROOT/bin/state.py" next 2>&1 | head -20 | sed 's/^/  /'
echo "  journal tail:"; tail -6 "$ROOT/.agents/state/JOURNAL.md" | sed 's/^/    /'
echo "  mailbox:"
for f in BRIEF REPORT REVIEWS; do
  p="$ROOT/.agents/supervisor/$f.md"
  [ -f "$p" ] && printf '    %-10s %s  %4s lines\n' "$f.md" "$(date -r "$p" '+%F %T')" "$(wc -l <"$p")"
done

if [ ! -f "$APP/artisan" ]; then echo; echo "no app/artisan — nothing more to check"; exit $fail; fi
cd "$APP" || exit 1
[ -d /home/goaiez/tmp ] && export TMPDIR=/home/goaiez/tmp

bar "4. checker soundness + seal"
php artisan doctor:selftest 2>&1 | tail -4 | sed 's/^/  /' || { fail=1; echo "  ⛔ RUNTIME — the checker, not the code"; }
php artisan doctor --stage=integrity 2>&1 | tail -4 | sed 's/^/  /' || { fail=1; echo "  ⛔ SEAL/integrity red"; }
echo "  runtime_build in BUILD-STATE: $(python3 -c "import json;print(json.load(open('$ROOT/.agents/state/BUILD-STATE.json'))['runtime_build'])" 2>/dev/null) — compare with the doctor build stamp above"

if [ $want_doctor -eq 1 ]; then
  bar "5. all eight stages  (non-zero exit on any red stage is by design)"
  # `tail -30` alone drops the eight per-stage lines off the top — measured
  # 2026-09-06 04:4x, when the only stage numbers left in the output were
  # `integrity` and `journey`. The stage summary IS the thing being gated, so
  # pull those lines out by name first, then the tail for the detail.
  doc=$(php artisan doctor 2>&1)
  printf '%s\n' "$doc" | grep -E '^\s*(ok|FAIL|WARN)\s+\w+' | sed 's/^/  /'
  printf '%s\n' "$doc" | grep -E '[0-9]+ violation\(s\)\.' | sed 's/^/  /'
  # 5a. The `journey` stage is NOT a property of the sha.
  #
  # MEASURED 2026-09-07 09:3x: the total sat at 745 across 26 gate logs and fell
  # to 744 on an unchanged tree, because the suite that ran at 09:23 wrote
  # app/storage/app/evidence/journeys/cancel.json — a gitignored directory the
  # pest run itself populates. `journey` counts the twelve journeys MINUS those
  # with evidence on disk, so it reports the outcome of the LAST SUITE THAT RAN
  # IN THIS CHECKOUT, not the state of the committed code.
  #
  # And inside one `--tests` run §5 executes BEFORE §7, so the doctor block a
  # report pastes is always evidence about the suite BEFORE this one. The two
  # halves of a single gate come from different worlds by construction.
  #
  # This line does not fix that — it makes it visible. Compare the timestamp
  # with §7's run before reading the journey number as a fact about the tree.
  echo "  journey evidence on disk (gitignored; written by the suite, not the sha):"
  if [ -d "$ROOT/app/storage/app/evidence/journeys" ]; then
    ls -l --time-style=long-iso "$ROOT/app/storage/app/evidence/journeys" \
      | tail -n +2 | sed 's/^/    /'
  else
    echo "    (no evidence/journeys directory — every journey reads as 'not run')"
  fi
  echo "  --- detail (last 30 lines) ---"
  printf '%s\n' "$doc" | tail -30 | sed 's/^/  /'
fi

bar "6. style + static analysis"
gate_tool pint ./vendor/bin/pint --test
gate_tool phpstan ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress

if [ $want_tests -eq 1 ]; then
  bar "7. test suite  (phpunit.xml → $xml_db)"

  # 7a. Refuse a SECOND suite against this one database.
  #
  # Run 67 (2026-09-06 02:5x) started a second `supervise.sh --tests` while the
  # first was still going; the two deadlocked on $xml_db and wrote nothing for
  # 76 minutes, while the coder's log said it was waiting on the shot rig. A
  # brief saying "one test process at a time" is not enough — the tool has to
  # refuse it.
  #
  # ⚠️ Scoped by /proc cwd, NEVER by name. Sibling tracks (grs-antig-sixty,
  # grs-antig-stages) run their own pest on this box and match every name
  # pattern there is; comparing the process's cwd to *this* $APP cannot reach
  # them. This only ever reports — killing anything is the owner's.
  # ⛔ NOT `pgrep -f 'vendor/bin/pest'`. Measured 2026-09-06 04:4x: that matched the
  # coder's own agy process, because the coder's command line IS the whole KICKOFF
  # and BRIEF, and this brief names `./vendor/bin/pest` in its own text. The gate
  # would then have refused every suite the coder ever tried to run. Three
  # conditions together, all cheap and none of them a name match:
  #   comm is php*  — excludes agy, node, timeout, bash, and any prompt text
  #   cwd is $APP   — excludes every sibling track
  #   cmdline names pest — excludes `php artisan` in this same checkout
  live=""
  for d in /proc/[0-9]*; do
    p=${d#/proc/}
    [ "$p" = "$$" ] && continue
    # ⚠️ `read … < "$d/comm" 2>/dev/null` does NOT silence this: bash reports a
    # failed *redirect* itself, before the command's stderr exists. A pid that
    # exits mid-scan then prints "No such file or directory" into the gate
    # output (seen 2026-09-06 08:1x). Test the file first.
    [ -r "$d/comm" ] || continue
    read -r comm < "$d/comm" || continue
    case "$comm" in php*) ;; *) continue ;; esac
    [ "$(readlink "$d/cwd" 2>/dev/null)" = "$APP" ] || continue
    grep -qa 'vendor/bin/pest' "$d/cmdline" 2>/dev/null && live="$live $p"
  done
  if [ -n "$live" ]; then
    echo "  ⛔ REFUSED — a pest is already running in this checkout against $xml_db:$live"
    echo "     Two suites on one database deadlock and write nothing (run 67, 2026-09-06)."
    echo "     Wait for it. NEVER kill a suite you did not start — it is another checkout's gate,"
    echo "     and a killed gate reports as a failed one (a killed Pint prints a bare 'Terminated')."
    echo "     If it is genuinely stranded, report it and stop; the owner decides."
    echo "     ⛔ Never pkill -f pest: sibling tracks match the same pattern. Their cwd is what"
    echo "     identifies them — and matching is not a licence."
    exit 1
  fi

  # 7b. A hard deadline. agy's own --print-timeout is COOPERATIVE and cannot
  # fire while it is blocked in a child that never returns — the run 67 shape.
  # Override for a wave that genuinely needs longer, declared in its brief.
  pest_timeout=${GOAIEZ_PEST_TIMEOUT:-30m}

  # ⛔ NEVER `out=$(timeout … pest)`. A command substitution only yields its
  # value when the child exits normally; when `timeout` kills pest, the shell
  # discards everything pest had already written and `$out` comes back EMPTY.
  # That is the whole of the "ZERO BYTES" epidemic — runs 67, 68 and 69 plus
  # three supervisor gates all read `rc=124/137/143 · 0 bytes` and were read as
  # a silent suite, when in fact the suite had been printing for thirty minutes
  # and the capture threw it away. Measured 2026-09-06 08:4x on 87027f75 with
  # nothing else on the box. Write to a file, then read the file back: the
  # bytes survive the kill, and the last line before the deadline names the
  # test that is actually slow.
  # ⚠️ Inside the checkout, NOT $TMPDIR. `/home/goaiez/tmp` is unreadable to the
  # supervisor session (ls/find/tail are all blocked there), so a log written
  # into it is a log nobody who needs it can open. Gitignored via .tick-*.
  pest_log="$ROOT/.agents/supervisor/.tick-pest.log"
  : > "$pest_log"

  # 7b2. The BOX-WIDE suite lock (Track 1, OWNER.md 2026-09-06 14:1x).
  #
  # 7a above is a CORRECTNESS guard — it refuses a second suite against *our*
  # database. This is a SCHEDULING one, and it is orthogonal: it serialises
  # against every other track and every sibling project on this box that takes
  # the same advisory lock. Two suites at once is not merely slow; it is what
  # gives some other agent a reason to reap a "stray" pest. Track 1 lost three
  # gates to that on 2026-09-06 (one rc=137, two rc=143), and this track's own
  # run-77 gate died `rc=137 · 0 bytes · 23s` — far below any cap.
  #
  # ⛔ Never kill the holder to get the lock. Wait, or report the timeout.
  PEST_LOCK=/home/goaiez/tmp/pest.lock
  lock_held=0
  if command -v flock >/dev/null 2>&1; then
    exec 9>>"$PEST_LOCK" 2>/dev/null && {
      if ! flock -n 9; then
        echo "  … another suite holds $PEST_LOCK — waiting up to 40 min (never killing it)"
      fi
      flock -w 2400 9 && lock_held=1
    }
  else
    # No flock on this box: behave exactly as before rather than refusing.
    lock_held=1
  fi

  # ⏱ Time the run. Without this the rc alone cannot tell a real deadline from a
  # kill that arrived in seconds, and 7c below guessed wrong for a whole wave.
  pest_t0=$(date +%s)
  pest_start_iso=$(date -Iseconds)
  pest_pid="-"   # stays `-` on the lock-timeout path: no process was ever started
  if [ $lock_held -eq 0 ]; then
    # ⚠️ A lock-timeout is NOT a red suite — NO TEST RAN. Never take a number
    # from a run that did not happen. The JSON below carries result
    # `lock-timeout` and no counts, so 7d prints `tests None … result
    # lock-timeout` rather than anything that could be mistaken for a gate.
    echo "  ✗ pest NOT RUN — $PEST_LOCK held for 40 minutes. Not a red suite: no test ran."
    echo '{"tool":"pest","result":"lock-timeout"}' > "$pest_log"
    rc=0
  elif [ -n "$pest_filter" ]; then
    echo "  narrowed: --filter $pest_filter"
    timeout -k 30 "$pest_timeout" ./vendor/bin/pest --filter "$pest_filter" > "$pest_log" 2>&1 &
    pest_job=$!; pest_pid=$pest_job
    pest_child=$(pgrep -P "$pest_job" 2>/dev/null | head -1)
    [ -n "$pest_child" ] && pest_pid=$pest_child
    wait "$pest_job"; rc=$?
    flock -u 9 2>/dev/null
  else
    timeout -k 30 "$pest_timeout" ./vendor/bin/pest > "$pest_log" 2>&1 &
    pest_job=$!; pest_pid=$pest_job
    # ⚠️ The descent matters most here: `timeout` is the job, `php
    # ./vendor/bin/pest` is what a killer sees. Logging $! would join to nothing.
    pest_child=$(pgrep -P "$pest_job" 2>/dev/null | head -1)
    [ -n "$pest_child" ] && pest_pid=$pest_child
    wait "$pest_job"; rc=$?
    flock -u 9 2>/dev/null
  fi
  pest_elapsed=$(( $(date +%s) - pest_t0 ))
  # The shared gate log. Raw rc — a `lock-timeout` writes rc 0 above, and the row
  # can never be counted as a suite that ran because `pest_pid` stays `-`: no
  # process was started, so the kill-log join key is empty. That is the tell now,
  # not a private `pest-not-run` tool name — the vocabulary is the five and
  # anything narrower collapses to its family (Track 1, OWNER.md 17:1x).
  log_gate "$pest_start_iso" "$(date -Iseconds)" "$$" "$pest_pid" "$rc" "pest"
  out=$(cat "$pest_log")
  [ $rc -ne 0 ] && fail=1
  [ $lock_held -eq 0 ] && fail=1

  # The cap in seconds, so "did the deadline actually arrive?" is arithmetic
  # rather than an assumption. Accepts the `30m` / `600s` / `600` forms.
  case "$pest_timeout" in
    *h) pest_cap=$(( ${pest_timeout%h} * 3600 )) ;;
    *m) pest_cap=$(( ${pest_timeout%m} * 60 )) ;;
    *s) pest_cap=${pest_timeout%s} ;;
    *)  pest_cap=$pest_timeout ;;
  esac

  # 7c. Say the rc and the elapsed time out loud, and name zero bytes for what it
  # is rather than letting a silent run read as a pass.
  #
  # ⛔ rc ∈ {124,137,143} IS NOT A TIMEOUT ON ITS OWN. Until 2026-09-06 11:5x this
  # block printed "TIMED OUT after 30m" for any of the three, and run 72 believed
  # it twice inside a run that was twelve minutes old — two thirty-minute
  # deadlines that could not both have fitted, on a suite that had in fact
  # finished in 101s and written a complete result to $pest_log. The report came
  # back `stopped: RUNTIME` on a wave that was green. Only `timeout` can produce
  # 124; 137/143 far below the cap is something else killing pest — the OOM
  # killer, the launcher's own cap, or a hand. Decide by the clock, and when the
  # log still ends in a complete pest JSON, say so: the measurement survived and
  # the wave can be gated on it.
  bytes=$(wc -c < "$pest_log" | tr -d ' ')
  echo "  pest rc=$rc · ${bytes} bytes of output · ${pest_elapsed}s elapsed (cap ${pest_timeout})"
  if [ "$rc" -eq 124 ] || [ "$rc" -eq 137 ] || [ "$rc" -eq 143 ]; then
    if [ "$rc" -eq 124 ] || [ "$pest_elapsed" -ge $(( pest_cap * 9 / 10 )) ]; then
      echo "  ⛔ TIMED OUT after ${pest_elapsed}s of a ${pest_timeout} cap (rc=$rc) — killed, not failed."
      echo "     Partial output survives in $pest_log — its last lines name the slow test."
      echo "     Narrow it: bash bin/supervise.sh --tests --filter <expr>"
    else
      echo "  ⚠️ KILLED FROM OUTSIDE after ${pest_elapsed}s — this is NOT the ${pest_timeout} timeout."
      echo "     rc=$rc arriving this far below the cap is the OOM killer, the launcher's"
      echo "     own cap, or a hand — not \`timeout\`. Do not report it as a timeout."
      if printf '%s' "$out" | tail -1 | grep -q '^{"tool":"pest"'; then
        echo "     ✅ $pest_log still ends in a COMPLETE pest result — the suite finished"
        echo "        and the numbers below are a real measurement. Gate on them."
      fi
    fi
  fi
  if [ "$bytes" -eq 0 ]; then
    echo "  ⛔ ZERO BYTES. Do not debug the code — narrow it with --filter and the real"
    echo "     exception appears immediately. Usual causes: memory_limit, a missing Vite"
    echo "     manifest (npm run build), or the run was killed from outside."
  fi

  # ⚠️ `failed` was ADDED to this line 2026-09-06 20:3x and the omission was four
  # months old. Until then the summary read `tests · passed · errors · result`, so a
  # supervisor reading only this line could not see whether FAILURES rose — half of
  # every floor a brief states. Run 90's report pasted it verbatim and correctly, and
  # its `failed 3` reached review only because I opened the JSON by hand. An assertion
  # that did not hold is a failure, not an error; the two counts are independent and
  # both belong here.
  if printf '%s' "$out" | tail -1 | grep -q '^{"tool":"pest"'; then
    printf '%s' "$out" | tail -1 | python3 -c '
import json,sys
d=json.loads(sys.stdin.read())
print("  tests %s · passed %s · failed %s · errors %s · result %s" % (d.get("tests"),d.get("passed"),d.get("failed"),d.get("errors"),d.get("result")))
for e in (d.get("error_details") or [])[:5]:
    print("   ✗ %s\n      %s" % (e.get("test","?").split("::")[-1], (e.get("message") or "")[:160]))
n=len(d.get("error_details") or [])
if n>5: print("   … %d more" % (n-5))'
    # A pest run can end `result failed` with `errors: None` — an ASSERTION that
    # did not hold is a failure, not an error, and carries no error_details. The
    # 08:0x version printed only the one-line summary there and said nothing
    # about which assertion, which is how run 69 reached review unmeasured.
    if printf '%s' "$out" | grep -q '^ *FAILED\|Failed asserting'; then
      echo "  --- failures ---"
      printf '%s\n' "$out" | grep -A4 'FAILED\|Failed asserting' | head -24 | sed 's/^/  /'
    fi
  else
    printf '%s\n' "$out" | tail -12 | sed 's/^/  /'
  fi
fi

bar "verdict"
if [ $fail -eq 0 ]; then
  echo "  gates green. Necessary, not sufficient — now read the diff and REPORT.md."
else
  echo "  ⛔ a gate failed above."
fi
exit $fail
