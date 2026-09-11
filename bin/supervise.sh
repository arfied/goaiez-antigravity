#!/usr/bin/env bash
#
# The supervisor's read-only gate.  Changes nothing.  Safe at any time.
#
#   bash bin/supervise.sh                # guard · tree · state · integrity · pint · phpstan
#   bash bin/supervise.sh --tests        # + pest, against phpunit.xml's database
#   bash bin/supervise.sh --full-doctor  # + all eight doctor stages
#
# Exit 2 = a database points at production. Exit 1 = a gate failed. Exit 0 =
# gates green, which is necessary and not sufficient: now read the diff.
#
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1
ROOT="$PWD"; APP="$ROOT/app"
PROD_DB="goaiez_antig"
# Track 5 (money): dev DB goaiez_antig_money, tests goaiez_antig_money_test (exported above pest).
want_tests=0; want_doctor=0
for a in "$@"; do case "$a" in --tests) want_tests=1;; --full-doctor) want_doctor=1;; esac; done
bar() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
fail=0

# ---------------------------------------------------------------------------
# Shared gate log (OWNER.md 2026-09-06 16:0x, corrected to EIGHT columns 16:5x).
#   start_iso  end_iso  gate_pid  tool_pid  rc  project  checkout  tool
# `rc` is RAW — 128+N, so 143 SIGTERM, 137 SIGKILL, 124 timeout(1)'s own — because
# the whole point is telling a kill from a verdict. `project` is the project, never
# the directory; `checkout` is this worktree's basename. `tool_pid` is the join key
# against coder-bin's kill-log.tsv. No lock: an append under PIPE_BUF to an O_APPEND
# file is atomic on Linux, and a flock here would interact with the pest lock for
# nothing. Consumers branch on NF — 24 seven-column rows predate this shape.
GATE_LOG=/home/goaiez/tmp/gate-runs.tsv
GATE_PROJECT="goaiez-antigravity"
GATE_CHECKOUT="$(basename "$ROOT")"
GATE_PID=$$
log_gate() { # <start_iso> <end_iso> <tool_pid> <rc> <tool>
  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
    "$1" "$2" "$GATE_PID" "$3" "$4" "$GATE_PROJECT" "$GATE_CHECKOUT" "$5" \
    >> "$GATE_LOG" 2>/dev/null || true
}
# Sentinel. Defined HERE, at the top, and not where the tools run: Track 1's first
# copy sat beside the tools and recorded nothing at all for a gate killed two
# seconds in. `trap - EXIT` goes INSIDE each signal trap, or the honest rc=143 row
# is followed by a clean rc=0 row that hides it.
GATE_START=$(date -Iseconds)
log_gate "$GATE_START" "-" "-" "-" "gate"
trap 'grc=$?; trap - EXIT; log_gate "$GATE_START" "$(date -Iseconds)" "-" "$grc" "gate"' EXIT
trap 'trap - EXIT INT TERM; log_gate "$GATE_START" "$(date -Iseconds)" "-" 143 "gate"; exit 143' TERM
trap 'trap - EXIT INT TERM; log_gate "$GATE_START" "$(date -Iseconds)" "-" 130 "gate"; exit 130' INT

# Run one tool, capturing its output in $out and its RAW exit code in $rc, and log
# a row. `out=$(cmd)` gives no pid, so the tool is backgrounded and the job's pid
# recorded — then DESCENDED, because `timeout 1800 pest` makes `timeout` the job
# and `php ./vendor/bin/pest` the process a killer actually sees in ps. Logging the
# wrapper's pid fails the join in exactly the case the log exists for, silently.
run_tool() { # <tool-name> <cmd...>
  local tool="$1"; shift
  local s tmp jobpid c
  s=$(date -Iseconds)
  tmp=$(mktemp "${TMPDIR:-/tmp}/gate-XXXXXX")
  "$@" > "$tmp" 2>&1 &
  jobpid=$!
  TOOL_PID=$jobpid
  sleep 1   # settle: pgrep before the fork returns nothing and pins the wrapper
  # Descend only THROUGH KNOWN WRAPPERS. Descending blindly to the deepest first
  # child would pin whatever the tool itself happened to fork at that instant.
  for _ in 1 2 3; do
    # A short tool (pint) can already be gone — its pid is still the right one.
    [ -r "/proc/$TOOL_PID/cmdline" ] || break
    case "$(tr '\0' ' ' < "/proc/$TOOL_PID/cmdline" 2>/dev/null)" in
      *timeout\ *|*"/env "*|env\ *) ;;
      *) break ;;
    esac
    c=$(pgrep -P "$TOOL_PID" 2>/dev/null | head -1)
    [ -n "$c" ] || break
    TOOL_PID=$c
  done
  wait "$jobpid"; rc=$?
  out=$(cat "$tmp"); rm -f "$tmp"
  log_gate "$s" "$(date -Iseconds)" "$TOOL_PID" "$rc" "$tool"
}
# A tool that died on a signal has returned no verdict. 124 is timeout(1)'s own,
# 137 SIGKILL, 143 SIGTERM (ruling 67). Print it as a kill, count it as a failed
# gate, and never read the output as a style or type result.
tool_killed() { # <tool-name>
  if [ "$rc" -ge 124 ]; then
    echo "  ⛔ $1 was KILLED or timed out · rc=$rc — this is NOT a verdict"
    return 0
  fi
  return 1
}

bar "0. database guard  (production is $PROD_DB — see NEXT-SESSION.md, 2026-08-31)"
env_db=$(grep -E '^DB_DATABASE=' "$APP/.env" 2>/dev/null | head -1 | cut -d= -f2- | tr -d "\"' ")
xml_db=$(grep -oE 'name="DB_DATABASE" value="[^"]*"' "$APP/phpunit.xml" 2>/dev/null | sed -E 's/.*value="([^"]*)"/\1/')
echo "  app/.env         DB_DATABASE=${env_db:-<unset>}"
echo "  app/phpunit.xml  DB_DATABASE=${xml_db:-<unset>}"
for db in "$env_db" "$xml_db"; do
  if [ "$db" = "$PROD_DB" ]; then
    echo "  ⛔ points at PRODUCTION. Stop. Nothing below may run."; exit 2
  fi
done
[ -z "$env_db" ] && echo "  ⚠ .env has no DB_DATABASE — anything reading config would use the framework default"

bar "1. working tree"
git status --short | head -40
echo "  $(git status --short | wc -l) uncommitted path(s)"
git log --oneline -5 | sed 's/^/  /'
git rev-list --left-right --count origin/main...HEAD 2>/dev/null \
  | awk '{print "  vs origin/main (local ref): behind " $1 ", ahead " $2 "  — refresh with: git fetch --no-write-fetch-head origin"}'

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
hook=$(git -C "$ROOT" rev-parse --git-path hooks/post-rewrite 2>/dev/null); [ "${hook#/}" = "$hook" ] && hook="$ROOT/$hook"
if [ ! -x "$hook" ]; then
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

bar "2c. debug debris in app code (dump/dd/var_dump)"
dbg=$(grep -rnE '\b(dump|dd|var_dump)\(' "$APP/app" --include='*.php' 2>/dev/null | grep -vE ':[0-9]+:\s*(\*|//)' | grep -v '@allow-dump' | head -5)
if [ -n "$dbg" ]; then printf '%s\n' "$dbg" | sed 's/^/  ⛔ /'; fail=1; else echo "  none"; fi

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
  php artisan doctor 2>&1 | tail -30 | sed 's/^/  /'
fi

bar "6. style + static analysis"
# Both were piped straight into `tail`, which throws the exit code away: a KILLED
# pint prints a bare `Terminated` and was about to be recorded as a style red
# (OWNER.md 16:0x). rc is read first now, and a signal death says so.
run_tool pint ./vendor/bin/pint --test
if tool_killed pint; then fail=1; else
  printf '%s\n' "$out" | tail -3 | sed 's/^/  /'
  [ "$rc" -ne 0 ] && fail=1
fi
run_tool phpstan ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress
if tool_killed phpstan; then fail=1; else
  printf '%s\n' "$out" | tail -4 | sed 's/^/  /'
  [ "$rc" -ne 0 ] && fail=1
fi

if [ $want_tests -eq 1 ]; then
  bar "7. test suite  (phpunit.xml → $xml_db)"
  # Another checkout whose app/phpunit.xml pins OUR database will migrate:fresh it out
  # from under this run. Refuse rather than produce a number nobody can trust
  # (OWNER.md 2026-09-05 08:0x; Track 1 commit 9b65e1e5, adapted — money's DB name).
  busy=""
  for x in /home/goaiez/agents/*/app/phpunit.xml /home/goaiez/public_html/*/app/phpunit.xml; do
    [ -f "$x" ] || continue
    case "$x" in "$ROOT"/app/phpunit.xml) continue ;; esac
    grep -q 'goaiez_antig_money_test' "$x" || continue
    other=$(dirname "$(dirname "$x")")
    for p in /proc/[0-9]*; do
      [ "$(readlink "$p/cwd" 2>/dev/null)" = "$other/app" ] || continue
      tr '\0' ' ' < "$p/cmdline" 2>/dev/null | grep -q 'pest\|phpunit' && busy="$busy $other"
    done
  done
  if [ -n "$busy" ]; then
    echo "  ⛔ REFUSED: a pest run is live in a checkout pinning goaiez_antig_money_test:$busy"
    echo "     (two suites on one database is the 'permission denied to terminate process' shape — rerun when idle)"
    fail=1
    out=''; rc=0
  else
  # Shared advisory lock (OWNER.md 14:1x). Two suites at once on this box is what
  # gives an agent a reason to reap a "stray" pest, and it is cross-project by
  # design: anything wrapping its suite in the same flock serialises with us. It is
  # ORTHOGONAL to the shared-database refusal above — that one is correctness, this
  # one is scheduling, and both stay. A lock-timeout is NOT a red suite: no test ran.
  PEST_LOCK=/home/goaiez/tmp/pest.lock
  lock_held=0
  if command -v flock >/dev/null 2>&1; then
    exec 9>>"$PEST_LOCK" 2>/dev/null && {
      if ! flock -n 9; then
        echo "  … another suite holds $PEST_LOCK — waiting up to 40 min (never killing it)"
      fi
      flock -w 2400 9 && lock_held=1
    }
    if [ $lock_held -eq 0 ]; then
      echo "  ✗ pest NOT RUN — $PEST_LOCK held for 40 minutes. Not a red suite: no test ran."
      echo '{"tool":"pest","result":"lock-timeout"}' > /home/goaiez/tmp/last-pest-money.json
      fail=1; want_tests=0
    fi
  fi
  if [ $want_tests -eq 1 ]; then
  # pest under a wall clock: a hung suite must say so, not hang the tick (OWNER.md 08:0x).
  # ruling 563: pao counts risky/incomplete but never names them; PHPUnit's own event
  # log does, and it lands in this checkout where the supervisor can read it.
  : > "$ROOT/.agents/supervisor/pest-events.txt"
  run_tool pest env DB_DATABASE=goaiez_antig_money_test timeout 1800 ./vendor/bin/pest --log-events-text "$ROOT/.agents/supervisor/pest-events.txt"
  flock -u 9 2>/dev/null
  printf '%s' "$out" | tail -1 > /home/goaiez/tmp/last-pest-$(basename "$(git rev-parse --show-toplevel)").json
  [ $rc -ne 0 ] && fail=1
  [ $rc -eq 124 ] && echo "  ⛔ TIMEOUT: pest exceeded 1800s and was killed — the number below, if any, is partial"
  # ruling 67: 137 is 128+9 (SIGKILL), 143 is 128+15 (SIGTERM). A killed suite did
  # not fail — the run is VOID and is never compared against a baseline.
  [ $rc -ge 128 ] && echo "  ⛔ KILLED: pest died on signal $((rc-128)) · rc=$rc — VOID, not a verdict (ruling 67). Do not re-run in the same tick."
  if [ -z "$out" ]; then
    echo "  ⛔ ZERO BYTES: pest produced no output · rc=$rc"
    echo "     (memory, or a missing Vite manifest — narrow with --filter, do not debug the code)"
  elif printf '%s' "$out" | tail -1 | grep -q '^{"tool":"pest"'; then
    printf '%s' "$out" | tail -1 | python3 -c '
import json,sys
d=json.loads(sys.stdin.read())
print("  tests %s · passed %s · FAILED %s · errors %s · result %s" % (d.get("tests"),d.get("passed"),d.get("failed",0),d.get("errors"),d.get("result")))
# ruling 560: laravel/pao computes passed = tests - failed - errors - skipped, so an
# INCOMPLETE or RISKY test is counted inside "passed", and pao emits these keys only
# when non-zero. Print them, or the gate cannot show an absence.
inside=[(k,d.get(k)) for k in ("incomplete","risky") if d.get(k)]
if inside: print("   ⚠️ counted INSIDE passed, not passes: %s" % " · ".join("%s %s" % kv for kv in inside))
other=[(k,d.get(k)) for k in ("skipped","warnings","deprecations","notices","php_errors") if d.get(k)]
if other: print("   ⚠️ also reported by pest: %s" % " · ".join("%s %s" % kv for kv in other))
for f in (d.get("failures") or [])[:5]:
    print("   ✗ FAILURE %s" % f.get("test","?").split("::")[-1])
for e in (d.get("error_details") or [])[:5]:
    print("   ✗ %s\n      %s" % (e.get("test","?").split("::")[-1], (e.get("message") or "")[:160]))
n=len(d.get("error_details") or [])
if n>5: print("   … %d more" % (n-5))'
  else
    echo "  (pest's last line is not the JSON summary · rc=$rc)"
    printf '%s\n' "$out" | tail -12 | sed 's/^/  /'
  fi
  if [ -s "$ROOT/.agents/supervisor/pest-events.txt" ]; then
    grep -A 1 -e 'Test Considered Risky (' -e 'Test Marked Incomplete (' "$ROOT/.agents/supervisor/pest-events.txt" | grep -v -e '^--$' | head -24 | sed 's/^/   ⚠️ /'
  fi
  fi   # want_tests, re-checked after the lock
  fi   # busy
fi

bar "verdict"
if [ $fail -eq 0 ]; then
  echo "  gates green. Necessary, not sufficient — now read the diff and REPORT.md."
else
  echo "  ⛔ a gate failed above."
fi
exit $fail
