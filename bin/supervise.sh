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
want_tests=0; want_doctor=0; census_only=0
# --census [name] runs ONLY §1a/§1b and exits. `name` is the argv[0] basename the census
# hunts for, default `agy`. It exists so the census has a POSITIVE CONTROL THAT IS SAFE
# WHEN THE DETECTOR IS ABSENT (2026-09-06): proving it by starting a real `agy` in this
# checkout would run an ungoverned coder to demonstrate that something notices — dangerous
# in exactly the case the census exists for. `--census sleep` against a backgrounded
# `sleep` proves the same mechanism (argv[0] basename × cwd) and is a harmless sleep if the
# detector is dead.
prev=""
for a in "$@"; do
  case "$a" in
    --tests) want_tests=1;;
    --full-doctor) want_doctor=1;;
    --census) census_only=1;;
    -*) ;;
    *) [ "$prev" = "--census" ] && CENSUS_NAME="$a";;
  esac
  prev="$a"
done
CENSUS_NAME=${CENSUS_NAME:-agy}
bar() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
fail=0

# SHARED GATE LOG (2026-09-06, shape agreed with the sibling project). One TSV line
# per tool run, appended at exit, so a death correlates against what else was running
# in that minute and the next kill is attributable instead of argued about.
# EIGHT columns, in this order:
#   start_iso  end_iso  gate_pid  tool_pid  rc  project  checkout  tool
# rc is the RAW code and is the whole point: 128+N — 143 SIGTERM, 137 SIGKILL, 124 is
# timeout(1)'s own. Never normalise it to 0/1.
# **tool_pid is the join key, gate_pid only groups a run's rows** (sibling project,
# 2026-09-06, from our own first sample: one gate_pid appeared on both the pint and
# the phpstan row, which is what proved it useless as a key). `coder-bin/kill` records
# the TARGET pid, and an agent killing a suite kills the *tool* — pest is what looks
# stray in `ps`, not the wrapper. Joining kill-log on gate_pid would fail silently in
# exactly the case this log exists to answer. Both pids cost one `$!` and neither is
# recoverable afterwards.
# `project` is the PROJECT, `checkout` is the working copy — this file wrote `grs-antig`
# in both until 2026-09-06 16:4x, which split its own traffic on any group-by. The seven
# lanes had it right from the first message; Track 1 did not. One stable label per
# project, and it is not the directory name.
# ⚠️ HISTORY IS MIXED. Rows before 16:4x come in two widths — the lanes wrote 7 columns
# (no `tool_pid`) because that is the shape Track 1 sent them, and Track 1 wrote 8 after
# the sibling project's correction without re-sending it. Nothing in a row announces its
# own width, so **a consumer must branch on NF before touching a field**: on a 7-column
# row `$4` is `rc`, on an 8-column row `$4` is `tool_pid`. Read `$4` as rc across the mix
# and every Track 1 row becomes a seven-digit failure code.
# No lock: appends under PIPE_BUF to an O_APPEND file are atomic on Linux, and a flock
# here would interact with the pest lock for nothing.
# Overridable so a TEST can never write into the shared log (sibling project, 28c52305,
# 2026-09-06): their pre-push test executes the real hook with instant no-op stubs, and
# once the hook logged, the test began appending rows for tools that never ran — twelve
# of them, well-formed, eight-column, correctly typed and invented. A diagnostic log that
# records its own harness is worse than no log: the fabrications have the shape of the
# evidence. No test here touches this script today (grepped across all eight checkouts);
# this is the preventive, because nothing in the schema would object if one did.
GATE_LOG=${GATE_LOG:-/home/goaiez/tmp/gate-runs.tsv}
log_gate() {                     # log_gate <tool> <start_iso> <rc> [tool_pid]
  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
    "$2" "$(date -Is)" "$$" "${4:--}" "$3" "goaiez-antigravity" "$(basename "$ROOT")" "$1" \
    >> "$GATE_LOG" 2>/dev/null || true
}
# Defined here, at the top, deliberately: a gate killed during sections 0-5 must
# still leave a start row. Defining it at section 6 recorded nothing for a gate
# killed two seconds in — measured, 2026-09-06 16:16.
# A KILLED GATE MUST NOT BE SILENT (sibling project, 2026-09-06). log_gate appends at
# tool exit, so a tool killed mid-run still lands its row with rc 143 — but a kill
# aimed at the WRAPPER leaves no row at all, indistinguishable from a gate that never
# started. Two sentinels fix it for the cost of two lines: a start row with rc `-`,
# and an end row from an EXIT trap. A start with no matching end is a killed gate.
GATE_STARTED=$(date -Is)
log_gate gate "$GATE_STARTED" - "$$"
trap 'log_gate gate "$GATE_STARTED" "${fail:-?}" "$$"' EXIT
# `trap - EXIT` first: without it the EXIT trap fires after the signal trap's `exit`
# and appends a SECOND end row carrying $fail — measured 2026-09-06 16:2x, a killed
# gate wrote rc=143 then rc=0, and a reader taking the last row would call a killed
# gate clean. That is the same defect class this log exists to catch.
trap 'trap - EXIT; log_gate gate "$GATE_STARTED" 143 "$$"; exit 143' TERM
trap 'trap - EXIT; log_gate gate "$GATE_STARTED" 130 "$$"; exit 130' INT


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
# 0b. schema dump (N185, 2026-09-17): a file under app/database/schema/ makes `migrate:fresh` LOAD THE DUMP AND
# SKIP THE MIGRATIONS, so a suite run with it present proves nothing about the migrations — SIXTY-212b's coder ran
# `php artisan schema:dump` before deleting a module, gated green, then removed the dump. Fail closed, like §0.
if [ -d "$APP/database/schema" ] && [ -n "$(ls -A "$APP/database/schema" 2>/dev/null)" ]; then
  echo "  ⛔ app/database/schema/ holds a dump ($(ls -A "$APP/database/schema" | tr '\n' ' ')) — migrate:fresh would skip the migrations. Stop. Nothing below may run."; exit 2
fi

bar "1. working tree"
git status --short | head -40
echo "  $(git status --short | wc -l) uncommitted path(s)"
git log --oneline -5 | sed 's/^/  /'
git rev-list --left-right --count origin/main...HEAD 2>/dev/null \
  | awk '{print "  vs origin/main (local ref): behind " $1 ", ahead " $2 "  — refresh with: git fetch --no-write-fetch-head origin"}'

# A PIDFILE REPORTS AN INTENTION, NOT A STATE (2026-09-06 17:3x, ruled in REVIEWS).
# The tick's case (a) is "if coder.pid is alive, print `coder running` and stop" — and a
# HUNG run satisfies that forever: the pid exists, the lane reports healthy, and it idles
# every ten minutes with its work unpushed. Measured live that afternoon: sixty silent for
# 182 minutes, pricebook for 77, both "running". Liveness therefore has to be measured as
# PROGRESS (has the log grown) and not as existence, and the supervisor must be able to
# measure it with a command its own settings.json allows — `ps` and `kill -0` are not on
# that list, which is how a tick ends up reasoning about a pid instead of reading one.
# §2e is also the one-writer census (2026-09-03 incident): any process with cwd here that
# launch-coder.sh did not start is a BLOCK, and it must print before any dispatch or gate.
bar "1a. coder process  (progress, not existence)"
STALL_MIN=${STALL_MIN:-30}
pidfile="$ROOT/.agents/supervisor/coder.pid"
coder_pid=""
[ -f "$pidfile" ] && coder_pid=$(tr -dc '0-9' < "$pidfile")
if [ -z "$coder_pid" ]; then
  echo "  no coder.pid — no dispatch has been recorded in this checkout"
elif ! kill -0 "$coder_pid" 2>/dev/null; then
  echo "  coder.pid $coder_pid is DEAD — the slot is free"
else
  log=$(ls -1t /home/goaiez/tmp/*"$(basename "$ROOT")"-run*.log 2>/dev/null | head -1)
  now=$(date +%s)
  pstart=$(stat -c %Y "/proc/$coder_pid" 2>/dev/null || echo "$now")
  age=$(( (now - pstart) / 60 ))
  if [ -n "$log" ]; then
    lmt=$(stat -c %Y "$log"); silent=$(( (now - lmt) / 60 )); bytes=$(stat -c %s "$log")
    echo "  coder.pid $coder_pid ALIVE ${age}m · log $(basename "$log") ${bytes}B · silent ${silent}m"
    if [ "$silent" -ge "$STALL_MIN" ]; then
      echo "  ⚠ coder STALLED — no log growth in ${silent}m (threshold ${STALL_MIN}m)."
      echo "    The slot is NOT free: do not dispatch over it. Report pid/log/silence to the owner."
      fail=1
    fi
  else
    echo "  coder.pid $coder_pid ALIVE ${age}m · NO LOG FOUND for $(basename "$ROOT") — cannot measure progress"
    fail=1
  fi
fi

# MATCH `agy`, NOT `claude` — and this section's FIRST output is why the line is here.
# Written as *agy*|*claude*, it flagged five "stray writers" on a checkout that had none:
# the supervisor tick itself, its two snapshot shells and the owner's VS Code session. The
# 2026-09-03 incident was an interactive `agy` started by hand with no pidfile, no brief
# and no review; CLAUDE.md's own census one-liner therefore ends `| grep agy`, and widening
# it turns a BLOCK signal into one that fires on every human who opens the checkout — a
# gate that always fails is worth exactly as much as one that always passes. A `claude`
# started as the CODER is caught by the pidfile in §1a, which is where it belongs.
# (After building anything that measures, its first output is data you do not trust.)
#
# EXCLUDE THE DISPATCHED CODER'S WHOLE TREE, NOT ITS PID — third false positive in this
# section in one hour, and the costliest, because it fires only when a healthy coder is
# running. `coder.pid` holds the `nohup bash -c` WRAPPER's pid; `agy` is its child. Matching
# `pn = coder_pid` therefore never matches the process that is actually named agy, so the
# census reported our own dispatched coder as a stray and set fail=1 — inside a gate the
# CODER itself runs at item 6, which would have read its own existence as a failed gate.
# Walk PPid instead.
is_descendant_of() {              # is_descendant_of <pid> <ancestor>
  local q="$1" hops=0 pp
  while [ -n "$q" ] && [ "$q" != 0 ] && [ $hops -lt 12 ]; do
    [ "$q" = "$2" ] && return 0
    pp=$(awk '/^PPid:/{print $2}' "/proc/$q/status" 2>/dev/null)
    q="$pp"; hops=$((hops+1))
  done
  return 1
}
bar "1b. one-writer census  ($CENSUS_NAME with cwd here that launch-coder.sh did not start)"
strays=0
for p in /proc/[0-9]*; do
  pn=${p#/proc/}
  [ "$pn" = "$$" ] && continue
  [ -n "$coder_pid" ] && is_descendant_of "$pn" "$coder_pid" && continue
  case "$(readlink "$p/cwd" 2>/dev/null)" in
    "$ROOT")
      # ARGV[0], never the whole cmdline — MATCH COMMAND POSITION, NOT MENTION (7686da5c,
      # and this section earned the lesson a second time twenty minutes later). Grepping
      # the joined cmdline for `agy` flagged a plain `bash -c` whose command merely NAMED
      # agy — a previous tick's own census one-liner, `… | grep agy`. A detector that fires
      # on any shell that talks about the thing it hunts will fire on its own documentation.
      argv0=""; IFS= read -r -d '' argv0 < "$p/cmdline" 2>/dev/null
      case "${argv0##*/}" in
        "$CENSUS_NAME")
          cmd=$(tr '\0' ' ' < "$p/cmdline" 2>/dev/null)
          echo "  ✗ stray $CENSUS_NAME pid $pn: ${cmd:0:140}"; strays=$((strays+1));;
      esac;;
  esac
done
[ "$strays" -eq 0 ] && echo "  none" || { echo "  ⛔ $strays $CENSUS_NAME process(es) this checkout did not launch — BLOCK until resolved"; fail=1; }
if [ $census_only -eq 1 ]; then
  echo; echo "  (--census: sections 1a/1b only; exit $fail)"; exit $fail
fi

bar "2. forbidden paths touched  (uncommitted + last commit)"
# Money's ruling 591 (TRACK 1 ACTION 29, applied 2026-09-12): HEAD~1..HEAD saw only the LAST commit, so a
# wave that committed twice, or ended in a chore(state) commit, hid its earlier commits from §2 and §2b.
# The unreviewed range is everything not yet on the pushed branch, because the supervisor pushes only
# gated shas. Falls back to HEAD~1 when HEAD is already pushed or detached.
upstream="origin/$(git rev-parse --abbrev-ref HEAD 2>/dev/null)"
base="HEAD~1"
if [ "$upstream" != "origin/HEAD" ] && git merge-base --is-ancestor "$upstream" HEAD 2>/dev/null \
   && [ "$(git rev-parse "$upstream" 2>/dev/null)" != "$(git rev-parse HEAD)" ]; then base="$upstream"; fi
echo "  range: $base..HEAD  ($(git rev-list --count "$base"..HEAD 2>/dev/null) commit(s))"
touched=$( { git diff --name-only "$base" HEAD 2>/dev/null; } | sort -u)
sup_edits=$(git diff --name-only HEAD -- .agents/supervisor CLAUDE.md bin/supervise.sh 2>/dev/null)
[ -n "$sup_edits" ] && printf '%s\n' "$sup_edits" | sed 's/^/  ℹ supervisor working notes (uncommitted — leave them alone): /'
touched=$(printf '%s\n%s' "$touched" "$(git diff --name-only HEAD | grep -vE '^(\.agents/supervisor/|CLAUDE\.md$|bin/supervise\.sh$)')" | sort -u | grep -v '^$')
pat='^app/app/Doctor/|seals\.json$|tests/Journeys/JourneyHarness\.php$|^app/app/Modules/[^/]+/(manifest|capabilities)\.php$|(^|/)\.env(\.|$)|^app/phpunit\.xml$|^source/|^runtime/|^bin/state\.py$|^\.agents/supervisor/(BRIEF|REVIEWS)\.md$'
hits=$(printf '%s\n' "$touched" | grep -E "$pat" | grep -v '\.env\.example$' || true)
if [ -n "$hits" ]; then
  printf '%s\n' "$hits" | sed 's/^/  ⛔ /'
  echo "  (manifest/capabilities are legal only via regeneration; supervisor files are legal only from the supervisor)"
  fail=1
else
  echo "  none"
fi

bar "2a. rewrite ledger (amends/rebases are recorded by the post-rewrite hook)"
if [ ! -x "$ROOT/.git/hooks/post-rewrite" ]; then
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

bar "2d. shared coder guard parses  (/home/goaiez/agents/coder-bin/git — all seven lanes' git)"
GUARD=/home/goaiez/agents/coder-bin/git
# The guard is a supervisor-maintained file (owner grant in .claude/settings.json) and it is
# on every coder's PATH in every checkout. A syntax error in it does not fail closed — it
# breaks `git` itself for every lane at once. Check it on every gate; it costs nothing.
if [ ! -f "$GUARD" ]; then
  echo "  ⛔ MISSING — coder runs would get the real git with no guard at all"; fail=1
elif bash -n "$GUARD" 2>/tmp/guard-parse.$$; then
  echo "  parses · $(wc -l <"$GUARD") lines · md5 $(md5sum "$GUARD" | cut -c1-12)"
  rm -f /tmp/guard-parse.$$
else
  sed 's/^/  ⛔ /' /tmp/guard-parse.$$; rm -f /tmp/guard-parse.$$; fail=1
fi

bar "2e. a merge that REVERTED a lane's check  (harness vs the incoming side)"
# §2 above measures the last commit against OUR HEAD, so it is structurally blind to the
# one thing a merge can do wrong: silently DROP the incoming side's change to a forbidden
# path. Run 115 restored `app/tests/Journeys/JourneyHarness.php` to HEAD during the site
# merge — reverting site's gated three-line J11 EdgeZone fix — and §2 printed `none`,
# correctly, because against HEAD the merge changed nothing there. The baseline was wrong,
# not the check. For a merge, the harness's baseline is the SECOND PARENT.
# The shared guard already encodes the intent (coder-bin/git:66-75: a gated merge may carry
# the harness ONLY when the staged blob is byte-identical to MERGE_HEAD's — "take the
# incoming side whole"), so this section only reports what that clause is there to enforce.
# ⚠️ The clause has a PRECONDITION and the first version of this check omitted it (run 117,
# 2026-09-06, its first firing on a real merge): "take the incoming side whole" only has
# meaning when the incoming side CHANGED the harness. On 8bccc2c6 track/ui never touched it
# while main was 64/-16 ahead of the merge base (a9e6a25f, site's J11 fix), so HEAD^2 alone
# read main's own legitimate ahead-ness as a dropped incoming change — the row that is
# legitimate by construction, again. The baseline for "did the incoming side change it" is
# the MERGE BASE; the baseline for "did the merge take it" is the second parent.
# Arms: FIRES on c1849a75 (site changed it +3 vs base 3c60289d, and the merge result still
# differs from the incoming side by that +3); SILENT on 8bccc2c6 (incoming vs base is empty);
# SILENT on any non-merge HEAD.
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
  php artisan doctor 2>&1 | tail -30 | sed 's/^/  /'
fi

bar "6. style + static analysis"
# A KILLED TOOL IS NOT A FAILED TOOL (2026-09-06, from the sibling project's 13900).
# Piping straight into `tail` threw away the exit code and the shell's own report of
# the signal: their gate logged "Pint failed — push aborted" when Pint had been
# SIGTERMed, because a killed process prints a bare `Terminated` that no signal-9
# pattern matches. §7 below already reads rc for pest; §6 did not, and had the same
# hole. rc >= 124 is timeout (124) or a signal (128+n: 137 = KILL, 143 = TERM).
run_tool() {                     # run_tool <label> <cmd...>
  local label="$1"; shift
  local out rc started tmp tpid child jobpid
  started=$(date -Is)
  # Run in the background solely to capture the TOOL's pid. An agent killing a
  # suite kills the tool — that is the pid it sees in `ps` — so the tool pid is the
  # join key against kill-log.tsv; the gate pid only groups a run's rows. Neither is
  # recoverable after the fact (sibling project, 2026-09-06).
  tmp=$(mktemp "${TMPDIR:-/tmp}/gate-XXXXXX")
  "$@" > "$tmp" 2>&1 &
  jobpid=$!; tpid=$jobpid
  child=$(pgrep -P "$jobpid" 2>/dev/null | head -1)  # through a `timeout` wrapper
  [ -n "$child" ] && tpid=$child
  wait "$jobpid"; rc=$?
  out=$(cat "$tmp"); rm -f "$tmp"
  log_gate "$label" "$started" "$rc" "$tpid"
  printf '%s\n' "$out" | tail -4 | sed 's/^/  /'
  if [ $rc -ne 0 ]; then
    if [ $rc -ge 124 ] || printf '%s' "$out" | grep -qiE 'terminated|killed|signaled|signal "?[0-9]+"?'; then
      echo "  ⛔ $label was KILLED or timed out (rc=$rc) — this is NOT a $label verdict."
      echo "     No number from this run. Re-run it; if it repeats, find what is signalling."
    fi
    fail=1
  fi
}
run_tool pint    ./vendor/bin/pint --test
run_tool phpstan ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress

if [ $want_tests -eq 1 ]; then
  bar "7. test suite  (phpunit.xml → $xml_db)"
  # Refuse while another pest runs on THIS database from any checkout whose
  # phpunit.xml pins it (2026-09-05 07:1x: the sixty checkout wiped the schema
  # under a Track 1 gate — 32 spurious "relation does not exist" errors).
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
    echo '{"tool":"pest","result":"refused-shared-db"}' > /home/goaiez/tmp/last-pest.json
    fail=1; want_tests=0
  fi
fi
if [ $want_tests -eq 1 ]; then
  # SHARED SUITE LOCK (2026-09-06). This box runs eight checkouts of this project
  # plus a sibling project's agents on the same account. Two suites at once is not
  # only slow — it is what gives an agent a reason to reap a "stray" pest, and on
  # 2026-09-06 that cost this repo a gate to `killall -9` (rc 137) and two more to
  # SIGTERM (rc 143), while the sibling project lost a suite and a Pint run the same
  # day and blamed a neighbour. Nobody could prove who killed what.
  #
  # The lock removes the reason. It is ADVISORY and cross-project by design: any
  # script on this box that wraps its suite in the same flock serialises with this
  # one. It is not a DB guard — §7 above still refuses a clash on the pinned
  # database, which is a correctness problem, not a scheduling one.
  #
  # Never kill a suite you did not start. Wait for the lock, or report and stop.
  PEST_LOCK=/home/goaiez/tmp/pest.lock
  lock_held=0
  if command -v flock >/dev/null 2>&1; then
    exec 9>>"$PEST_LOCK" 2>/dev/null && {
      if ! flock -n 9; then
        # NAME THE HOLDER (sixty TRACK 1 ACTION 2, adopted by Track 1 2026-09-08). Until now
        # this line printed the path and the timeout and never said WHO held it. A lane that
        # cannot see what it is waiting on has a standing incentive to route around the wait,
        # and on sixty's tick 251 that cost the wave its entire mutation set — the ask was
        # filed as hygiene and was re-filed once it had cost a wave.
        #
        # Scanned from /proc, not from `fuser`/`lsof`: that is §1b's idiom, which is known to
        # work on this box, and it needs no tool whose presence this seat cannot even test
        # (`command -v` is denied here). All seven lanes run as one account, so the fds of the
        # process we are actually waiting on are readable.
        #
        # ⚠ OUR OWN fd 9 IS ON THIS FILE — the row that is legitimate by construction, which
        # every detector in this repo has been bitten by at least once. $$ is excluded by name,
        # exactly as §1b excludes it. Fails open in every arm: an unreadable /proc, a vanished
        # pid, or a holder on another account all fall back to the old message; nothing here
        # can break the gate or shorten the wait.
        echo "  … another suite holds $PEST_LOCK — waiting up to 40 min (never killing it)"
        held_by=0
        for lp in /proc/[0-9]*; do
          lpn=${lp#/proc/}
          [ "$lpn" = "$$" ] && continue
          for lfd in "$lp"/fd/*; do
            [ "$(readlink "$lfd" 2>/dev/null)" = "$PEST_LOCK" ] || continue
            lcwd=$(readlink "$lp/cwd" 2>/dev/null)
            lcmd=$(tr '\0' ' ' < "$lp/cmdline" 2>/dev/null)
            echo "      holder pid $lpn  cwd ${lcwd:-?}  ${lcmd:0:100}"
            held_by=$((held_by+1))
            break
          done
        done
        if [ "$held_by" -eq 0 ]; then
          echo "      (holder not identifiable from /proc — it may belong to another account)"
        fi
      fi
      flock -w 2400 9 && lock_held=1
    }
    if [ $lock_held -eq 0 ]; then
      echo "  ✗ pest NOT RUN — $PEST_LOCK held for 40 minutes. Not a red suite: no test ran."
      echo '{"tool":"pest","result":"lock-timeout"}' > /home/goaiez/tmp/last-pest.json
      fail=1; want_tests=0
    fi
  fi
fi
if [ $want_tests -eq 1 ]; then
  # timeout: a hung suite is a red line, never a 26-minute wait (ruling 2026-09-05 07:0x)
  #
  # N205b (2026-09-18): `stdbuf -oL` was added here and then REMOVED, because the
  # hypothesis behind it was measured and is false. The reasoning was that PHP
  # block-buffers a redirected stdout, so a hung suite would have flushed nothing and
  # N205's preservation could never fire — money's live pest file was indeed 0 bytes
  # fifteen minutes into a hang (found via /proc/<pid>/fd/1). The control: two php
  # processes printing a line, sleeping 6s, printing another, both redirected to a
  # file — WITH and WITHOUT stdbuf. Both showed 7 bytes while still running. PHP CLI
  # does not block-buffer stdout, so stdbuf changes nothing here and the empty file
  # has a different cause: pest had genuinely printed nothing yet.
  # Do not re-add it without re-running that control.
  pest_started=$(date -Is)
  ptmp=$(mktemp "${TMPDIR:-/tmp}/pest-XXXXXX"); timeout 1800 ./vendor/bin/pest > "$ptmp" 2>&1 & pjob=$!; pest_pid=$(pgrep -P "$pjob" 2>/dev/null | head -1); pest_pid=${pest_pid:-$pjob}; wait "$pjob"; rc=$?; out=$(cat "$ptmp"); rm -f "$ptmp"
  log_gate pest "$pest_started" "$rc" "${pest_pid:--}"
  [ "${lock_held:-0}" -eq 1 ] && flock -u 9 2>/dev/null
  if [ $rc -eq 124 ]; then
    echo "  ✗ pest TIMEOUT after 1800s — the suite hung (a lock wait or a prompt); treat as red"
    # N205 (2026-09-18): the captured output used to die here with the temp file.
    # money timed out twice at 1800s and produced NO evidence either time — the one
    # artefact that names the last test to START was discarded on the single path
    # where re-running costs half an hour and tells you nothing new. The parser
    # below finds no result line and prints `tests None`, which reads like a
    # measurement and is a silence. Same family as N137: an instrument that can
    # only under-report is safe as a trigger and unsafe as a finding.
    pto="$ROOT/.agents/supervisor/.pest-timeout-$(date +%Y%m%d-%H%M%S).txt"
    if [ -z "${out//[$' \t\n']/}" ]; then
      # N205c (2026-09-18, first firing): do NOT announce "raw output preserved" over an
      # empty file. `laravel/pao` (composer files-autoload, vendor/laravel/pao/src/Autoload.php)
      # takes over tool output for an agent: it unsets COLLISION_PRINTER, sets
      # PEST_PARALLEL_NO_OUTPUT=1, and emits its one {"tool":"pest",…} line from a
      # register_shutdown_function. SIGTERM from `timeout` does not run shutdown functions,
      # so a hung suite emits NOTHING and there is nothing to preserve. Measured: a real
      # captured run is wc -l = 1, and this branch's first firing wrote 1 byte.
      echo "    ⚠ pest produced NO output to preserve — this is expected under laravel/pao,"
      echo "      which emits one JSON line from a shutdown function that SIGTERM never runs."
      echo "      To name the hanging test, re-run with PAO_DISABLE=true and a short timeout;"
      echo "      do NOT change this script's invocation — §7 parses pao's JSON as tail -1."
    elif printf '%s\n' "$out" > "$pto" 2>/dev/null; then
      echo "    raw output preserved: $pto ($(wc -l < "$pto" 2>/dev/null) lines)"
      echo "    last 15 lines — the suite stopped after the last test named here:"
      tail -15 "$pto" 2>/dev/null | sed 's/^/      /'
    else
      echo "    ⚠ could not preserve the raw output (unwritable: $pto) — it is lost, as it was before N205"
    fi
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
  printf '%s' "$out" | tail -1 > /home/goaiez/tmp/last-pest.json
  [ $rc -ne 0 ] && fail=1
  if printf '%s' "$out" | tail -1 | grep -q '^{"tool":"pest"'; then
    printf '%s' "$out" | tail -1 | python3 -c '
import json,sys
d=json.loads(sys.stdin.read())
print("  tests %s · passed %s · FAILED %s · errors %s · result %s" % (d.get("tests"),d.get("passed"),d.get("failed",0),d.get("errors"),d.get("result")))
# FAILURES ARE NEVER TRUNCATED SILENTLY (N137, 2026-09-08, wave 155). This loop
# read [:5] while the errors loop below it printed a "… N more" line, so a suite
# with FAILED 10 named five and said nothing about the other five — and the brief
# that run was graded on had "a FAILED name not in the expected list" as its STOP
# condition, a question this printer could not answer. An instrument that can only
# under-report is safe as a trigger and unsafe as a finding. Both lists now carry
# their own overflow line, and a FAILURE prints its message like an error does.
FCAP=40
fails=d.get("failures") or []
errs=d.get("error_details") or []
# 2026-09-20: .split("::")[-1] threw away the CLASS, and the class is the identifying
# half. A gate reporting two failures BOTH named test_screen_renders_for_tenant — a
# method that exists in 30+ files — cannot be acted on: this seat could not tell which
# screens were red without running pest, which it is denied. Same family as N137, one
# level down: the LIST was complete and each ROW was not. Print class::method, and
# widen the message, which was cutting off before the assertion it was reporting.
def _tid(t):
    t=t or "?"
    if "::" in t:
        c,m=t.rsplit("::",1); return "%s::%s" % (c.split("\\")[-1], m)
    return t
for f in fails[:FCAP]:
    print("   ✗ FAILURE %s" % _tid(f.get("test")))
    fm=" ".join((f.get("message") or "").split())
    if fm: print("      %s" % fm[:400])
if len(fails)>FCAP: print("   … %d more FAILURE(s) not listed" % (len(fails)-FCAP))
for e in errs[:FCAP]:
    print("   ✗ %s\n      %s" % (_tid(e.get("test")), " ".join((e.get("message") or "").split())[:400]))
if len(errs)>FCAP: print("   … %d more ERROR(s) not listed" % (len(errs)-FCAP))'
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
