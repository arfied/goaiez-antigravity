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
# Track 4 (pricebook): dev DB goaiez_antig_pricebook, tests goaiez_antig_pricebook_test.
# ⛔ app/phpunit.xml still pins Track 1's goaiez_antig_test and is on the never-list.
#    §7 below EXPORTS goaiez_antig_pricebook_test over that pin. Any pest run made by
#    hand must carry the same prefix, or it writes into Track 1's test database.
want_tests=0; want_doctor=0
for a in "$@"; do case "$a" in --tests) want_tests=1;; --full-doctor) want_doctor=1;; esac; done
bar() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
fail=0

# ── shared gate log (Track 1, OWNER.md 2026-09-06 16:0x, corrected 16:5x/17:1x) ──
# One TSV row per tool run, appended at exit. EIGHT columns, in this order:
#
#   start_iso  end_iso  gate_pid  tool_pid  rc  project  checkout  tool
#
# `rc` is RAW — 128+N, so 143 SIGTERM, 137 SIGKILL, 124 timeout(1)'s own. Never
# normalised to 0/1: the signal is the whole point. `project` is the project
# (goaiez-antigravity), `checkout` the directory basename. `tool_pid` is the join
# key against /home/goaiez/tmp/kill-log.tsv; `gate_pid` only groups a run's rows.
# `tool` is one of exactly five values — gate | pint | phpstan | pest | doctor —
# because a group-by on it is the entire point of the column (17:1x: four
# spellings of the gate already split the shared file four ways). No lock: an
# append under PIPE_BUF to an O_APPEND file is atomic on Linux, and a flock here
# would interact with the pest lock for nothing.
# GATE_LOG is overridable so a test that exercises the gate can never write the
# shared file — the sibling project's harness appended twelve fabricated rows
# with every property the schema demands, and the only tell was a pest row whose
# start and end were the same second. No test here touches the gate today; this
# is prevention.
GATE_LOG=${GATE_LOG:-/home/goaiez/tmp/gate-runs.tsv}
GATE_PID=$$
GATE_START=$(date -Is)
GATE_CHECKOUT=$(basename "$ROOT")
log_gate() {  # start_iso end_iso tool_pid rc tool
  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
    "$1" "$2" "$GATE_PID" "$3" "$4" "goaiez-antigravity" "$GATE_CHECKOUT" "$5" \
    >> "$GATE_LOG" 2>/dev/null || true
}
# The sentinel pair distinguishes a gate killed at the wrapper from one that
# never ran. Defined HERE, at the top — Track 1's sat where the tools run and
# recorded nothing for a gate killed two seconds in — and `trap - EXIT` fires
# INSIDE each signal trap, or the honest rc=143 row is hidden by a later rc=0.
gate_end() { _rc=$?; trap - EXIT; log_gate "$GATE_START" "$(date -Is)" "-" "$_rc" "gate"; }
trap 'gate_end' EXIT
trap '_s=$?; trap - EXIT; log_gate "$GATE_START" "$(date -Is)" "-" "143" "gate"; exit 143' TERM
trap '_s=$?; trap - EXIT; log_gate "$GATE_START" "$(date -Is)" "-" "130" "gate"; exit 130' INT
log_gate "$GATE_START" "-" "-" "-" "gate"

# Run one tool, capture its RAW rc and its own pid, log a row, leave the output
# in $TOOL_OUT. `out=$(cmd)` gives no pid, so the job is backgrounded; and the
# `pgrep -P` descent is not optional, because `timeout 1800 pest` makes timeout
# the job and php the process a killer sees in ps — logging the wrapper's pid
# fails the join in exactly the case the log exists for, silently.
run_tool() {  # name cmd...
  _tname="$1"; shift
  _ts=$(date -Is)
  _tmp=$(mktemp "${TMPDIR:-/tmp}/gate-XXXXXX")
  "$@" > "$_tmp" 2>&1 &
  _job=$!; _tpid=$_job
  sleep 0.3   # the wrapper has to exec and fork before its child exists
  _child=$(pgrep -P "$_job" 2>/dev/null | head -1)
  [ -n "$_child" ] && _tpid=$_child
  wait "$_job"; _rc=$?
  TOOL_OUT=$(cat "$_tmp"); rm -f "$_tmp"
  log_gate "$_ts" "$(date -Is)" "$_tpid" "$_rc" "$_tname"
  return $_rc
}
# A tool that was killed did not return a verdict. 124 is timeout(1); 128+N is a
# signal. Ours piped pint and phpstan into `tail`, which throws the exit code
# away — a killed Pint prints a bare `Terminated` and was one step from being
# recorded as "pint failed".
killed_note() {  # name rc
  if [ "$2" -eq 124 ] || [ "$2" -ge 128 ]; then
    echo "  ⛔ $1 was KILLED or timed out (rc $2) — this is NOT a verdict"
  fi
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

# 2026-09-06 07:0x. A merge from main takes main's app/phpunit.xml whole and SILENTLY —
# our side had not touched the file since the merge base and main had, so git resolves it
# with no conflict marker and nothing to see in `git show <merge> -- app/phpunit.xml`.
# The same merge takes main's bin/supervise.sh, which has no TEST_DB export at all, so
# both belts fail together and §7 runs the suite against whatever main pinned. On
# 2026-09-06 that was goaiez_antig_test — Track 1's — and only the coder stopping of its
# own accord kept 1860 tests and a migrate:fresh off another lane's schema.
# Refuse instead of warning: a gate measured against the wrong database is not a number.
if [ -n "$xml_db" ] && [ "$xml_db" != "goaiez_antig_pricebook_test" ]; then
  echo "  ⛔ app/phpunit.xml pins '$xml_db', not this lane's goaiez_antig_pricebook_test."
  echo "     A merge from main flips this silently. Restore it before anything runs:"
  echo "       git show <our-last-pre-merge-sha>:app/phpunit.xml > app/phpunit.xml"
  echo "     (the coder writes it; the supervisor commits that one path — ruling 27)"
  exit 2
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
# ⚠️ These two used to read `cmd | tail | sed || fail=1`. A pipeline's status is
# its LAST command's, so `fail=1` could never fire and a pint or phpstan red was
# printed and then forgotten by the verdict. Read the rc first, print second.
run_tool pint ./vendor/bin/pint --test; pint_rc=$?
printf '%s\n' "$TOOL_OUT" | tail -3 | sed 's/^/  /'
[ $pint_rc -ne 0 ] && fail=1
killed_note pint $pint_rc
run_tool phpstan ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress; stan_rc=$?
printf '%s\n' "$TOOL_OUT" | tail -4 | sed 's/^/  /'
[ $stan_rc -ne 0 ] && fail=1
killed_note phpstan $stan_rc

TEST_DB=goaiez_antig_pricebook_test
if [ $want_tests -eq 1 ]; then
  bar "7. test suite  (phpunit.xml pins $xml_db — §7 EXPORTS $TEST_DB over it)"
  # (owner 2026-09-05 08:0x, after Track 1 9b65e1e5) refuse while another checkout whose
  # phpunit.xml pins OUR test database has pest live — two runs share one database and
  # the second one's migrate:fresh drops the first one's schema mid-run.
  busy=0
  for pid in $(pgrep -f 'vendor/bin/pest' 2>/dev/null); do
    # pgrep -f matches any process whose command line merely MENTIONS the path.
    # The coder is one of them: launch-coder.sh:91 passes the whole of KICKOFF.md
    # as a single argv element, so a brief that names ./vendor/bin/pest anywhere
    # makes this guard refuse the very gate it was briefed to run, from the repo
    # root, whose app/phpunit.xml pins our own TEST_DB. PB-34 (2026-09-05 18:0x)
    # measured nothing for exactly that reason. A real pest run is the php binary
    # (vendor/bin/pest is #!/usr/bin/env php), so require that and no shell or
    # agent process can trip it.
    # An unreadable exe (a pid that died, or another account's) keeps the OLD
    # conservative behaviour and still gets the cwd check — this narrows the
    # guard, it does not open it.
    exe=$(readlink -f "/proc/$pid/exe" 2>/dev/null)
    if [ -n "$exe" ]; then
      case "${exe##*/}" in php|php[0-9]*) ;; *) continue ;; esac
    fi
    cw=$(readlink -f "/proc/$pid/cwd" 2>/dev/null) || continue
    [ -n "$cw" ] || continue
    for px in "$cw/phpunit.xml" "$cw/app/phpunit.xml"; do
      [ -f "$px" ] || continue
      if grep -q "value=\"$TEST_DB\"" "$px" 2>/dev/null; then
        echo "  ⛔ pest is already live in $cw (pid $pid), pinned to $TEST_DB — refusing to run"
        busy=1
      fi
    done
  done
  if [ $busy -eq 1 ]; then
    echo "  rerun when idle; this is not a code finding"
    fail=1
  else
  PEST_LOCK=/home/goaiez/tmp/pest.lock
  lock_held=1
  : >> "$PEST_LOCK" 2>/dev/null || PEST_LOCK=
  if [ -n "$PEST_LOCK" ] && command -v flock >/dev/null 2>&1; then
    exec 9>>"$PEST_LOCK"
    if ! flock -n 9; then
      echo "  … another suite holds $PEST_LOCK — waiting up to 40 min (never killing it)"
      flock -w 2400 9 || lock_held=0
    fi
  fi
  if [ $lock_held -eq 0 ]; then
    echo "  ✗ pest NOT RUN — $PEST_LOCK held for 40 minutes. Not a red suite: no test ran."
    echo '{"tool":"pest","result":"lock-timeout"}' > /home/goaiez/tmp/last-pest-grs-antig-pricebook.json
    fail=1
  else
  run_tool pest env DB_DATABASE=$TEST_DB timeout 1800 ./vendor/bin/pest; rc=$?
  out=$TOOL_OUT
  killed_note pest $rc
  flock -u 9 2>/dev/null
  printf '%s' "$out" | tail -1 > /home/goaiez/tmp/last-pest-$(basename "$(git rev-parse --show-toplevel)").json
  [ $rc -ne 0 ] && fail=1
  [ $rc -eq 124 ] && echo "  ⛔ TIMEOUT — pest passed 1800s and was killed (rc 124). The number below, if any, is partial."
  if [ -z "$out" ]; then
    echo "  ⛔ ZERO BYTES — pest printed nothing (rc $rc). Narrow it with --filter before debugging any code:"
    echo "     memory (phpunit.xml.dist pins 512M), a missing Vite manifest (npm run build), or a dead database."
    fail=1
  elif printf '%s' "$out" | tail -1 | grep -q '^{"tool":"pest"'; then
    printf '%s' "$out" | tail -1 | python3 -c '
import json,sys
d=json.loads(sys.stdin.read())
print("  tests %s · passed %s · FAILED %s · errors %s · result %s · rc '"$rc"'" % (d.get("tests"),d.get("passed"),d.get("failed",0),d.get("errors"),d.get("result")))
for f in (d.get("failures") or [])[:5]:
    print("   ✗ FAILURE %s" % f.get("test","?").split("::")[-1])
for e in (d.get("error_details") or [])[:5]:
    print("   ✗ %s\n      %s" % (e.get("test","?").split("::")[-1], (e.get("message") or "")[:160]))
n=len(d.get("error_details") or [])
if n>5: print("   … %d more" % (n-5))'
  else
    echo "  (pest printed no JSON summary line — rc $rc; raw tail:)"
    printf '%s\n' "$out" | tail -12 | sed 's/^/  /'
  fi
  fi
  fi
fi

bar "verdict"
if [ $fail -eq 0 ]; then
  echo "  gates green. Necessary, not sufficient — now read the diff and REPORT.md."
else
  echo "  ⛔ a gate failed above."
fi
exit $fail
