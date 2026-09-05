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

# ⛔ This track's OWN test database. `app/phpunit.xml` is a per-track file and
# never merges in either direction (owner ruling, 2026-09-04). A merge of
# origin/main put `goaiez_antig_test` — TRACK 1's — back on this checkout on
# 2026-09-05, and a suite run from here wiped Track 1's schema under their gate
# at 07:1x (32 spurious "relation phone_numbers does not exist" errors). The
# same check stands alone in .agents/supervisor/pin-check.sh, which is gitignored
# and therefore survives a merge that overwrites this file.
OWN_TEST_DB="goaiez_antig_sixty_test"
if [ "$xml_db" != "$OWN_TEST_DB" ]; then
  echo "  ⛔ app/phpunit.xml pins '${xml_db:-<unset>}', not this track's '$OWN_TEST_DB'"
  echo "     restore: git show cd30f19c:app/phpunit.xml > app/phpunit.xml"
  if [ $want_tests -eq 1 ]; then
    echo "     REFUSING --tests: a run on another track's database is false here and destructive there."
    want_tests=0
  fi
  fail=1
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

# ⛔ A PATH THAT *ARRIVED* IN A MERGE IS NOT A PATH THAT WAS *TOUCHED*.
# `git diff HEAD~1 HEAD` on a merge commit diffs against the FIRST parent (ours),
# so every file main brought reads as changed — JourneyHarness.php included, on
# every single merge. That false positive cost this track waves 60 and 61 (it is
# the same defect as the coder guard's, escalated as OWNER 59) before the merge
# was verified by hand and committed at tick 148. The honest test is against the
# MERGE parent: if the file is byte-identical to HEAD^2 it came from there
# untouched, and no assertion, refusal or seal moved.
merged_in=""
if [ -n "$(git rev-parse -q --verify HEAD^2 2>/dev/null)" ] && [ -n "$hits" ]; then
  kept=""
  for h in $hits; do
    if git diff --quiet HEAD^2 HEAD -- "$h" 2>/dev/null; then
      merged_in="$merged_in$h
"
    else
      kept="$kept$h
"
    fi
  done
  hits=$(printf '%s' "$kept" | grep -v '^$' || true)
fi
[ -n "$merged_in" ] && printf '%s' "$merged_in" | grep -v '^$' \
  | sed 's|^|  ✓ arrived unchanged from the merge parent (identical to HEAD^2, not touched): |'

if [ -n "$hits" ]; then
  printf '%s\n' "$hits" | sed 's/^/  ⛔ /'
  echo "  (manifest/capabilities are legal only via regeneration; supervisor files are legal only from the supervisor)"
  fail=1
elif [ -n "$merged_in" ]; then
  echo "  none touched"
else
  echo "  none"
fi

bar "2a. rewrite ledger (amends/rebases are recorded by the post-rewrite hook)"
# This checkout is a linked worktree, so $ROOT/.git is a FILE and $ROOT/.git/hooks
# can never exist. Git runs hooks from core.hooksPath, else the COMMON git dir —
# never the per-worktree one. Resolve it the way git does. (Fixed 2026-09-05: the
# literal path made this gate report MISSING for several waves while the hook the
# supervisor wrote on 2026-09-02 was present and live all along.)
hooksdir=$(git -C "$ROOT" config --get core.hooksPath 2>/dev/null)
[ -n "$hooksdir" ] || hooksdir="$(git -C "$ROOT" rev-parse --git-common-dir)/hooks"
case "$hooksdir" in /*) ;; *) hooksdir="$ROOT/$hooksdir" ;; esac
if [ ! -x "$hooksdir/post-rewrite" ]; then
  echo "  ⛔ post-rewrite hook is MISSING at $hooksdir — its absence is a finding"; fail=1
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
  php artisan doctor 2>&1 | tail -30 | sed 's/^/  /'
fi

bar "6. style + static analysis"
./vendor/bin/pint --test 2>&1 | tail -3 | sed 's/^/  /' || fail=1
./vendor/bin/phpstan analyse --memory-limit=1G --no-progress 2>&1 | tail -4 | sed 's/^/  /' || fail=1

if [ $want_tests -eq 1 ]; then
  bar "7. test suite  (phpunit.xml → $xml_db)"
  # Refuse while another pest runs on THIS database from any checkout whose
  # phpunit.xml pins it (adopted from Track 1's 9b65e1e5; the incident is the
  # one recorded in §0 above).
  shared=""
  for co in /home/goaiez/agents/grs-antig*; do
    grep -q "DB_DATABASE\" value=\"$xml_db\"" "$co/app/phpunit.xml" 2>/dev/null && shared="$shared $co"
  done
  clash=0
  for p in $(pgrep -x php); do
    if tr '\0' ' ' < /proc/$p/cmdline 2>/dev/null | grep -q "bin/pes""t"; then
      c=$(readlink /proc/$p/cwd 2>/dev/null)
      for co in $shared; do case "$c" in "$co"/*) clash=$((clash+1)); echo "  ✗ pest pid $p running on $xml_db from $c";; esac; done
    fi
  done
  if [ $clash -gt 0 ]; then
    echo "  ✗ REFUSED: $clash other pest process(es) on $xml_db (checkouts pinning it:$shared) — a gate now would be false"
    fail=1; want_tests=0
  fi
fi
if [ $want_tests -eq 1 ]; then
  # timeout: a hung suite is a red line, never a 26-minute wait (owner ruling
  # relayed 2026-09-05 08:0x).
  out=$(timeout 1800 ./vendor/bin/pest 2>&1); rc=$?
  if [ $rc -eq 124 ]; then
    echo "  ✗ pest TIMEOUT after 1800s — the suite hung (a lock wait or a prompt); treat as red"
    out="$out"$'\n''{"tool":"pest","result":"timeout"}'
  elif [ -z "$out" ]; then
    # Zero bytes is never a result: rc 137/143 = killed from outside; 255 = PHP
    # died before the formatter existed (memory, or a missing Vite manifest —
    # narrow with --filter); 0 with no output = the formatter never ran.
    echo "  ✗ pest printed ZERO BYTES (rc=$rc) — no test ran to completion; not a number, a silence. Re-run; if it repeats, --filter one file to surface the exception"
    out='{"tool":"pest","result":"silent","rc":'"$rc"'}'
    fail=1
  fi
  [ $rc -ne 0 ] && fail=1
  if printf '%s' "$out" | tail -1 | grep -q '^{"tool":"pest"'; then
    printf '%s' "$out" | tail -1 | python3 -c '
import json,sys
d=json.loads(sys.stdin.read())
print("  tests %s · passed %s · errors %s · result %s" % (d.get("tests"),d.get("passed"),d.get("errors"),d.get("result")))
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
