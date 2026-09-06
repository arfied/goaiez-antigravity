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
./vendor/bin/pint --test 2>&1 | tail -3 | sed 's/^/  /' || fail=1
./vendor/bin/phpstan analyse --memory-limit=1G --no-progress 2>&1 | tail -4 | sed 's/^/  /' || fail=1

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
  out=$(DB_DATABASE=$TEST_DB timeout 1800 ./vendor/bin/pest 2>&1); rc=$?
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

bar "verdict"
if [ $fail -eq 0 ]; then
  echo "  gates green. Necessary, not sufficient — now read the diff and REPORT.md."
else
  echo "  ⛔ a gate failed above."
fi
exit $fail
