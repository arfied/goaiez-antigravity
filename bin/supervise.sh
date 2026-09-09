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
# Track 2 (UI): dev DB goaiez_antig_stages, tests goaiez_antig_stages_test (exported above pest).
want_tests=0; want_doctor=0
for a in "$@"; do case "$a" in --tests) want_tests=1;; --full-doctor) want_doctor=1;; esac; done
bar() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
fail=0

# Shared gate log (Track 1 relay 2026-09-06 16:0x, CORRECTED by Track 1 16:5x —
# the seven-column shape they first sent gained a column and is now final at EIGHT):
#   start_iso <TAB> end_iso <TAB> gate_pid <TAB> tool_pid <TAB> rc <TAB> project <TAB> checkout <TAB> tool
# Nothing in a row announces its own width, so a field-index parser is silently
# wrong across the mix: in a 7-column row $4 is rc, in an 8-column row $4 is the
# tool_pid. Any consumer branches on NF. History stays mixed; no row is migrated.
# The rc is RAW and never normalised — 128+N is the whole signal (143 SIGTERM,
# 137 SIGKILL, 124 is timeout(1)'s own). `tool` matters because pint, phpstan and
# pest are three populations and a kill hits whichever is running. `project` is
# the project, never the directory; `checkout` is the directory basename.
# No lock: an append under PIPE_BUF to an O_APPEND file is atomic on Linux, and a
# flock here would interact with the pest lock for nothing.
# `tool` is a CLOSED vocabulary — exactly `gate | pint | phpstan | pest | doctor`
# (Track 1, 2026-09-06 17:1x, correcting its own "free text"). The column exists to
# be grouped on, and four spellings of the gate across the checkouts split that
# group-by four ways. Anything narrower than the five collapses to its family:
# a per-stage doctor run logs `doctor`, never `doctor-<stage>`. The two sentinel
# rows are both `gate` and a consumer tells them apart by `rc`, not by a name.
# GATE_LOG is overridable so a test that exercises this gate writes a throwaway
# file: the sibling project's pre-push test appended twelve perfectly-shaped rows
# for tools that never ran, and the only tell was a `pest` row whose start and end
# were the same second. A log that records its own harness is worse than no log.
GATE_LOG=${GATE_LOG:-/home/goaiez/tmp/gate-runs.tsv}
log_gate() {  # $1 start_iso  $2 tool_pid  $3 rc  $4 tool
  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
    "$1" "$(date -Is)" "$$" "$2" "$3" goaiez-antigravity "$(basename "$ROOT")" "$4" \
    >> "$GATE_LOG" 2>/dev/null || true
}

# Runs one gate tool and captures BOTH its output and the pid a killer would see.
# `out=$(cmd)` yields no pid at all, so the tool is backgrounded into a temp file
# and its pid taken from $!. Through a wrapper — `timeout 1800 pest` — the job is
# `timeout` and the process in ps is pest, so descend one level with `pgrep -P`;
# logging the wrapper's pid makes the join against kill-log.tsv fail in exactly
# the case the log exists for, silently. The descent is attempted ONLY for a
# wrapped command: phpstan forks workers, and a worker pid is not the tool.
# `test -d /proc/N` and not `kill -0` — the BASH_ENV shim routes `kill` through
# coder-bin, and a liveness probe must not enter the kill log.
# Sets TOOL_OUT and TOOL_RC.
run_tool() {  # $1 tool-name  $2.. the command
  local tool="$1"; shift
  local t0 tmp jobpid tpid child descend i
  t0=$(date -Is)
  tmp=$(mktemp "${TMPDIR:-/tmp}/gate-XXXXXX") || tmp="$ROOT/.agents/supervisor/.gate-tool.$$"
  "$@" > "$tmp" 2>&1 &
  jobpid=$!; tpid=$jobpid
  descend=0; case " $* " in *" timeout "*) descend=1 ;; esac
  if [ $descend -eq 1 ]; then
    i=0
    while [ $i -lt 10 ]; do
      child=$(pgrep -P "$jobpid" 2>/dev/null | head -1)
      [ -n "$child" ] && { tpid="$child"; break; }
      test -d /proc/"$jobpid" || break
      sleep 0.2; i=$((i + 1))
    done
  fi
  wait "$jobpid"; TOOL_RC=$?
  log_gate "$t0" "$tpid" "$TOOL_RC" "$tool"
  TOOL_OUT=$(cat "$tmp"); rm -f "$tmp"
}

# Gate sentinel (Track 1 16:5x). A start row with rc `-` and an end row from an
# EXIT trap, so a gate killed at the wrapper is distinguishable from one that
# never ran at all. Two defects Track 1 already paid for and this copy avoids:
# the sentinel is defined HERE, at the top, not where the tools run (a gate
# killed two seconds in recorded nothing); and `trap - EXIT` fires INSIDE each
# signal trap (otherwise the honest rc=143 row is followed by a clean rc=0 row
# that hides it). The gate's own rows carry `-` in the tool_pid column.
GATE_T0=$(date -Is)
log_gate "$GATE_T0" - - gate
_gate_exit() { log_gate "$GATE_T0" - "$1" gate; }
trap '_gate_rc=$?; _gate_exit "$_gate_rc"' EXIT
trap 'trap - EXIT; _gate_exit 143; exit 143' TERM
trap 'trap - EXIT; _gate_exit 130; exit 130' INT

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
pat='^app/app/Doctor/|seals\.json$|tests/Journeys/JourneyHarness\.php$|^app/app/Modules/[^/]+/(manifest|capabilities)\.php$|(^|/)\.env(\.|$)|^app/phpunit\.xml$|^source/|^runtime/|^bin/state\.py$|^\.agents/supervisor/(BRIEF|REVIEWS)\.md$'
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

bar "2e. a merge that REVERTED a lane's check  (harness vs the incoming side)"
# Adopted from main's copy at d2a81ee0, tick 239, unmodified. RULING FS's ADOPTABLE class:
# it reads only this checkout, crosses no boundary, and changes what the instrument reports
# and never what the tree contains. §2 above measures the last commit against OUR HEAD, so
# it is structurally blind to the one thing a merge can do wrong: silently DROP the incoming
# side's change to a forbidden path. That is RULING DL's loss class and RULING FM's — the
# baseline is wrong, not the check. For a merge, the harness's baseline is the SECOND PARENT.
# The shared guard already encodes the intent (a gated merge may carry the harness ONLY when
# the staged blob is byte-identical to MERGE_HEAD's — "take the incoming side whole"), so
# this section only reports what that clause is there to enforce.
# ⚠️ The clause has a PRECONDITION, and main's first version omitted it: "take the incoming
# side whole" only has meaning when the incoming side CHANGED the harness. The baseline for
# "did the incoming side change it" is the MERGE BASE; the baseline for "did the merge take
# it" is the second parent.
# Arms proven at tick 239: the ✓ arm and its precondition FIRE on this lane's own take
# 6b7c315b (incoming 7a75f289 changed the harness vs base 6b3e7d63, and the result is
# identical to the incoming side) — a live positive control on this lane's own history, not
# a borrowed one. The ⛔ arm is proven on main's c1849a75 and is UNPROVEN here; the
# not-a-merge arm is what this seat's HEAD exercises today.
p2=$(git rev-parse -q --verify 'HEAD^2' 2>/dev/null || true)
if [ -z "$p2" ]; then
  echo "  HEAD is not a merge — nothing to compare"
else
  mb=$(git merge-base HEAD^1 "$p2" 2>/dev/null || true)
  inc=$(git diff --name-only "$mb" "$p2" -- app/tests/Journeys/JourneyHarness.php 2>/dev/null)
  if [ -z "$inc" ]; then
    echo "  incoming side never touched the harness (vs merge base $(git rev-parse --short "$mb")) — nothing to take ✓"
  else
    drop=$(git diff --name-only HEAD "$p2" -- app/tests/Journeys/JourneyHarness.php 2>/dev/null)
    if [ -n "$drop" ]; then
      echo "  ⛔ the merge did NOT take the incoming harness — $(git diff --shortstat HEAD "$p2" -- app/tests/Journeys/JourneyHarness.php)"
      echo "     inspect: git diff HEAD $p2 -- app/tests/Journeys/JourneyHarness.php"
      fail=1
    else
      echo "  harness identical to the incoming side ✓"
    fi
  fi
fi

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
  _doc="$ROOT/.agents/supervisor/.doctor-full.out"
  php artisan doctor > "$_doc" 2>&1
  # per-stage counts first: the tail below drops them, and a total with no stage
  # attribution cannot tell you which stage a wave was supposed to move
  grep -aE '(integrity|boundary|contract|citation|schema|capability|anchor|journey) [0-9]+ms ' "$_doc" | sed 's/^/  /'
  echo "  ---"
  tail -30 "$_doc" | sed 's/^/  /'
fi

bar "6. style + static analysis"
# Piping a tool into `tail` throws its exit code away — `|| fail=1` was reading sed's,
# which is always 0. Capture rc BEFORE the pipe (Track 1, 2026-09-06 16:0x): a KILLED
# pint prints a bare `Terminated` and would otherwise be filed as a style red. rc >= 124
# is a signal about the box, never a verdict about the code.
run_tool pint ./vendor/bin/pint --test
pint_out=$TOOL_OUT; pint_rc=$TOOL_RC
printf '%s\n' "$pint_out" | tail -3 | sed 's/^/  /'
if [ "$pint_rc" -ge 124 ]; then
  echo "  ⛔ pint was KILLED or timed out (rc $pint_rc) — this is NOT a verdict"; fail=1
elif [ "$pint_rc" -ne 0 ]; then fail=1; fi

run_tool phpstan ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress
stan_out=$TOOL_OUT; stan_rc=$TOOL_RC
printf '%s\n' "$stan_out" | tail -4 | sed 's/^/  /'
if [ "$stan_rc" -ge 124 ]; then
  echo "  ⛔ phpstan was KILLED or timed out (rc $stan_rc) — this is NOT a verdict"; fail=1
elif [ "$stan_rc" -ne 0 ]; then fail=1; fi

if [ $want_tests -eq 1 ]; then
  bar "7. test suite  (DB_DATABASE=goaiez_antig_stages_test, exported over phpunit.xml's $xml_db)"
  gate_db=goaiez_antig_stages_test
  # (a) OWNER 2026-09-05 08:0x — refuse while another checkout has a pest live on OUR database.
  #     Concurrent runs share one schema; the number would be noise (OWNER ACTION 56).
  busy=""
  for pid in $(pgrep -f 'vendor/bin/pest' 2>/dev/null); do
    cwd=$(readlink /proc/"$pid"/cwd 2>/dev/null) || continue
    [ -n "$cwd" ] || continue
    case "$cwd" in "$APP"*) continue ;; esac
    other_db=$(tr '\0' '\n' < /proc/"$pid"/environ 2>/dev/null | sed -n 's/^DB_DATABASE=//p' | head -1)
    [ -n "$other_db" ] || other_db=$(grep -oE 'name="DB_DATABASE" value="[^"]*"' "$cwd/phpunit.xml" 2>/dev/null | sed -E 's/.*value="([^"]*)"/\1/')
    [ "$other_db" = "$gate_db" ] && busy="$busy $cwd(pid=$pid)"
  done
  if [ -n "$busy" ]; then
    echo "  ⛔ REFUSED — a pest run is already live on $gate_db in:$busy"
    echo "  (rerun when idle; a shared-database run is a measurement failure, not a code failure)"
    fail=1
    out=""; rc=0; skip_pest=1
  else
    skip_pest=0
    # (a2) Track 1, 2026-09-06 14:1x — serialise every suite on this box behind one advisory
    #      lock. Two concurrent suites are what gives an agent a reason to reap a "stray" pest
    #      (rc 137/143). Orthogonal to (a): that is a correctness guard, this is scheduling.
    #      A lock-timeout is NOT a red suite — it means no test ran.
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
        echo '{"tool":"pest","result":"lock-timeout"}' > /home/goaiez/tmp/last-pest-grs-antig-stages.json
        fail=1; skip_pest=1
      fi
    fi
    if [ $skip_pest -eq 0 ]; then
      run_tool pest env DB_DATABASE="$gate_db" timeout 1800 ./vendor/bin/pest
      out=$TOOL_OUT; rc=$TOOL_RC
      [ $lock_held -eq 1 ] && flock -u 9
    else
      out=""; rc=0
    fi
  fi
  # (b) rc 124 is the 1800s timeout; (c) a zero-byte run is named, never printed as a blank.
  if [ "$skip_pest" -eq 1 ]; then
    :
  elif [ $rc -eq 124 ]; then
    echo "  ⛔ TIMEOUT — pest exceeded 1800s and was killed (rc 124). No number from this run."
    fail=1
  elif [ -z "$out" ]; then
    echo "  ⛔ ZERO BYTES — pest printed nothing, rc $rc. Narrow with --filter before debugging code"
    echo "  (memory, a missing Vite manifest, or a died-before-the-formatter run all read like this)"
    fail=1
  else
  printf '%s' "$out" | tail -1 > /home/goaiez/tmp/last-pest-$(basename "$(git rev-parse --show-toplevel)").json
  [ $rc -ne 0 ] && fail=1
  if printf '%s' "$out" | tail -1 | grep -q '^{"tool":"pest"'; then
    printf '%s' "$out" | tail -1 | python3 -c '
import json,sys
d=json.loads(sys.stdin.read())
print("  tests %s · passed %s · FAILED %s · errors %s · result %s" % (d.get("tests"),d.get("passed"),d.get("failed",0),d.get("errors"),d.get("result")))
# FAILURES ARE NEVER TRUNCATED SILENTLY (N137 upstream; RULING FO here, tick 235).
# This loop read [:5] while the errors loop below it printed a "… N more" line, so
# a suite with FAILED 6 named five and said nothing about the sixth — tick 235 had
# to record that sixth as NOT MEASURED. An instrument that can only under-report is
# safe as a trigger and unsafe as a finding. Both lists now carry their own
# overflow line, and a FAILURE prints its message the way an error does.
FCAP=40
fails=d.get("failures") or []
errs=d.get("error_details") or []
for f in fails[:FCAP]:
    print("   ✗ FAILURE %s" % f.get("test","?").split("::")[-1])
    fm=" ".join((f.get("message") or "").split())
    if fm: print("      %s" % fm[:200])
if len(fails)>FCAP: print("   … %d more FAILURE(s) not listed" % (len(fails)-FCAP))
for e in errs[:FCAP]:
    print("   ✗ %s\n      %s" % (e.get("test","?").split("::")[-1], (e.get("message") or "")[:160]))
if len(errs)>FCAP: print("   … %d more ERROR(s) not listed" % (len(errs)-FCAP))'
  else
    printf '%s\n' "$out" | tail -12 | sed 's/^/  /'
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
