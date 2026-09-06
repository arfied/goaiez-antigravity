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
# Track 7 (site): dev DB goaiez_antig_site, tests goaiez_antig_site_test.
# This is the authority for §7, NOT app/phpunit.xml. The 2026-09-05 17:4x
# fast-forward onto main replaced app/phpunit.xml with Track 1's copy
# (goaiez_antig_test), so a bare pest here would point RefreshDatabase at
# ANOTHER TRACK's schema — the 2026-08-31 shape. The export below wins over
# phpunit.xml's <env>, which does not carry force="true".
TRACK_DB="goaiez_antig_site_test"
want_tests=0; want_doctor=0
for a in "$@"; do case "$a" in --tests) want_tests=1;; --full-doctor) want_doctor=1;; esac; done
bar() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
fail=0

# ── Shared gate log (Track 1 relay 2026-09-06 16:0x, CORRECTED 16:5x to EIGHT columns).
#   start_iso  end_iso  gate_pid  tool_pid  rc  project  checkout  tool
# ⛔ Track 1's first relay gave a SEVEN-column shape and every lane built to it, so the file
# is mixed and nothing in a row announces its own width: read $4 as rc and an 8-col row shows
# a seven-digit pid, read $5 as rc and a 7-col row shows the project name. Any consumer
# branches on NF. We write 8.
# `project` is the PROJECT (goaiez-antigravity), `checkout` the directory basename — Track 1's
# own writer put the directory in both and split its traffic on any group-by.
# `rc` is RAW, never normalised: 128+N is the whole signal (143 SIGTERM, 137 SIGKILL, 124 is
# timeout(1)'s own). `tool` matters because pint, phpstan and pest are three populations and a
# kill hits whichever is running.
# No lock: an append under PIPE_BUF to an O_APPEND file is atomic on Linux, and a flock here
# would interact with the pest lock for nothing.
GATE_RUNS=/home/goaiez/tmp/gate-runs.tsv
log_gate() {  # $1 start_iso  $2 rc  $3 tool  $4 tool_pid
  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
    "$1" "$(date -Is)" "$$" "${4:--}" "$2" goaiez-antigravity "$(basename "$ROOT")" "$3" \
    >> "$GATE_RUNS" 2>/dev/null || true
}

# run_tool <tool> <cmd...> — sets tool_out and tool_rc, logs one row with the REAL tool pid.
# ⛔ `out=$(cmd)` gives no pid, so the command is backgrounded and waited on. The `pgrep -P`
# descent is not optional where anything wraps the tool: `timeout 1800 pest` makes `timeout`
# the job and pest the process a killer sees, so logging the wrapper's pid would break the
# join against kill-log.tsv in exactly the case the log exists for — silently.
run_tool() {
  local tool="$1"; shift
  local t0 tmp jobpid child
  t0=$(date -Is)
  tmp=$(mktemp "${TMPDIR:-/tmp}/gate-XXXXXX")
  "$@" > "$tmp" 2>&1 &
  jobpid=$!
  tool_pid=$jobpid
  child=$(pgrep -P "$jobpid" 2>/dev/null | head -1)
  [ -n "$child" ] && tool_pid=$child
  wait "$jobpid"; tool_rc=$?
  tool_out=$(cat "$tmp"); rm -f "$tmp"
  log_gate "$t0" "$tool_rc" "$tool" "$tool_pid"
}

# Gate sentinel: a start row (rc `-`) and an end row from an EXIT trap, so a gate killed at
# the wrapper is distinguishable from one that never ran. ⛔ Defined HERE, at the top — Track 1
# put theirs where the tools run and recorded nothing for a gate killed two seconds in. And
# `trap - EXIT` goes INSIDE the signal traps: theirs wrote rc=143 and then rc=0, so the last
# row read clean and the honest row was hidden by a later one.
GATE_T0=$(date -Is)
log_gate "$GATE_T0" - gate-start
trap 'log_gate "$GATE_T0" "$fail" gate-end' EXIT
trap 'log_gate "$GATE_T0" 143 gate-signal; trap - EXIT; exit 143' TERM
trap 'log_gate "$GATE_T0" 130 gate-signal; trap - EXIT; exit 130' INT

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
echo "  §7 will export DB_DATABASE=$TRACK_DB (this track's authority)"
if [ "$xml_db" != "$TRACK_DB" ]; then
  echo "  ⚠ app/phpunit.xml pins '$xml_db', NOT this track's '$TRACK_DB'."
  echo "    §7's export covers the gate; anything run WITHOUT it (a bare ./vendor/bin/pest,"
  echo "    php artisan test) would hit '$xml_db'. app/ is the coder's column — briefed."
fi

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
hits=$(printf '%s\n' "$touched" | grep -E "$pat" | grep -v '\.env\.example$' || true)
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
  SEEN="/home/goaiez/tmp/rewrites-seen-$(basename "$ROOT")"
  cur=$(md5sum "$ROOT/.agents/supervisor/REWRITES.log" | cut -d' ' -f1)
  if [ -f "$SEEN" ] && [ "$(cat "$SEEN")" = "$cur" ]; then
    echo "  ledger unchanged since last review ($(grep -c '^==' "$ROOT/.agents/supervisor/REWRITES.log") historical entries, already quoted)"
  else
    tail -6 "$ROOT/.agents/supervisor/REWRITES.log" | sed 's/^/  ⛔ NEW: /'; fail=1
    echo "$cur" > "$SEEN"
  fi
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
# ⛔ These two lines used to pipe straight into `tail | sed`, so `|| fail=1` was reading SED's
# status, which is always 0 — §6 could never fail the gate, for a style red OR for a kill.
# (Track 1 hit the same hole: a KILLED pint prints a bare `Terminated` and was about to be
# filed as "pint failed".) Capture rc BEFORE any pipe. rc >= 124 is a statement about the box,
# never a verdict about the code — say so and do not call it a style red.
run_tool pint ./vendor/bin/pint --test
printf '%s\n' "$tool_out" | tail -3 | sed 's/^/  /'
if [ "$tool_rc" -ge 124 ]; then
  echo "  ⛔ pint was KILLED or timed out (rc $tool_rc) — this is NOT a verdict"; fail=1
elif [ "$tool_rc" -ne 0 ]; then fail=1; fi

run_tool phpstan ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress
printf '%s\n' "$tool_out" | tail -4 | sed 's/^/  /'
if [ "$tool_rc" -ge 124 ]; then
  echo "  ⛔ phpstan was KILLED or timed out (rc $tool_rc) — this is NOT a verdict"; fail=1
elif [ "$tool_rc" -ne 0 ]; then fail=1; fi

if [ $want_tests -eq 1 ]; then
  bar "7. test suite  (exported DB_DATABASE → $TRACK_DB;  app/phpunit.xml pins $xml_db)"
  # Refuse while another pest runs on THIS database from any checkout whose
  # phpunit.xml pins it (2026-09-05 07:1x: the sixty checkout wiped the schema
  # under a Track 1 gate — 32 spurious "relation does not exist" errors).
  # Keyed on $TRACK_DB, not $xml_db: after the ff this checkout's phpunit.xml
  # names a database the gate does not actually use, and scanning for THAT
  # would both miss a real clash on ours and refuse on a harmless Track 1 run.
  shared="$ROOT"
  for co in /home/goaiez/agents/grs-antig*; do
    [ "$co" = "$ROOT" ] && continue
    grep -q "DB_DATABASE\" value=\"$TRACK_DB\"" "$co/app/phpunit.xml" 2>/dev/null && shared="$shared $co"
  done
  clash=0
  for p in $(pgrep -x php); do
    if tr '\0' ' ' < /proc/$p/cmdline 2>/dev/null | grep -q "bin/pes""t"; then
      c=$(readlink /proc/$p/cwd 2>/dev/null)
      for co in $shared; do case "$c" in "$co"/*) clash=$((clash+1)); echo "  ✗ pest pid $p running on $TRACK_DB from $c";; esac; done
    fi
  done
  if [ $clash -gt 0 ]; then
    echo "  ✗ REFUSED: $clash other pest process(es) on $TRACK_DB (checkouts pinning it:$shared) — a gate now would be false"
    echo '{"tool":"pest","result":"refused-shared-db"}' > "/home/goaiez/tmp/last-pest-$(basename "$ROOT").json"
    fail=1; want_tests=0
  fi
fi
if [ $want_tests -eq 1 ]; then
  # Track 1 ruling 2026-09-06 14:1x, adopted from track/stages 40b67efe and track/ui 91c48be7.
  # Serialise every suite on this box behind ONE advisory lock. Two concurrent suites are what
  # gives an agent a reason to reap a "stray" pest (rc 137/143 — this lane saw exactly that at
  # tick 208). Orthogonal to the clash refusal above: that is a correctness guard on OUR
  # database, this is scheduling across all seven checkouts.
  # ⛔ A lock-timeout is NOT a red suite — it means no test ran, and it says so.
  PEST_LOCK=/home/goaiez/tmp/pest.lock
  lock_held=0
  if command -v flock >/dev/null 2>&1; then
    if exec 9>>"$PEST_LOCK" 2>/dev/null; then
      flock -n 9 || echo "  … another suite holds $PEST_LOCK — waiting up to 40 min (never killing it)"
      flock -w 2400 9 && lock_held=1
    fi
    if [ $lock_held -eq 0 ]; then
      echo "  ✗ pest NOT RUN — $PEST_LOCK held for 40 minutes. Not a red suite: no test ran."
      echo '{"tool":"pest","result":"lock-timeout"}' > "/home/goaiez/tmp/last-pest-$(basename "$ROOT").json"
      fail=1; want_tests=0
    fi
  fi
fi
if [ $want_tests -eq 1 ]; then
  # timeout: a hung suite is a red line, never a 26-minute wait (ruling 2026-09-05 07:0x)
  # run_tool logs the pid of pest ITSELF, not of the `timeout` wrapper — `env` execs pest in
  # the same process, so timeout's only child IS the pest process. Logging the wrapper would
  # break the join against kill-log.tsv in exactly the case this lane saw at tick 208
  # (rc=143, SIGTERM from outside, attributable to nothing).
  run_tool pest timeout 1800 env DB_DATABASE="$TRACK_DB" ./vendor/bin/pest
  out="$tool_out"; rc=$tool_rc
  [ "${lock_held:-0}" -eq 1 ] && flock -u 9
  if [ $rc -eq 124 ]; then
    echo "  ✗ pest TIMEOUT after 1800s — the suite hung (a lock wait or a prompt); treat as red"
    out="$out"$'\n''{"tool":"pest","result":"timeout"}'
  elif [ -z "$out" ]; then
    # 2026-09-05 07:2x: a gate printed a blank §7 and an empty last-pest.json.
    # Zero bytes is never a result: rc 137/143 = killed from outside (a
    # `pkill -f pest` in another session); 255 = PHP died before the formatter
    # (memory, Vite manifest — narrow with --filter); 0 with no output = the
    # formatter never ran.
    echo "  ✗ pest printed ZERO BYTES (rc=$rc) — no test ran to completion; not a number, a silence. Re-run; if it repeats, --filter one file to surface the exception"
    out='{"tool":"pest","result":"silent","rc":'"$rc"'}'
    fail=1
  fi
  # OWNER ACTION 8 (answered 2026-09-04): per-track path. $ROOT, not $PWD — we cd'd into $APP above.
  printf '%s' "$out" | tail -1 > "/home/goaiez/tmp/last-pest-$(basename "$ROOT").json"
  [ $rc -ne 0 ] && fail=1
  if printf '%s' "$out" | tail -1 | grep -q '^{"tool":"pest"'; then
    printf '%s' "$out" | tail -1 | python3 -c '
import json,sys
d=json.loads(sys.stdin.read())
print("  tests %s · passed %s · FAILED %s · errors %s · result %s" % (d.get("tests"),d.get("passed"),d.get("failed",0),d.get("errors"),d.get("result")))
for f in (d.get("failures") or [])[:5]:
    print("   ✗ FAILURE %s" % f.get("test","?").split("::")[-1])
for e in (d.get("error_details") or [])[:5]:
    print("   ✗ %s\n      %s" % (e.get("test","?").split("::")[-1], (e.get("message") or "")[:160]))
n=len(d.get("error_details") or [])
if n>5: print("   … %d more" % (n-5))'
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
