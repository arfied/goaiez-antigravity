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

# ⛔ THIS RUN'S OWN CLOCK (REV-152).
#
# Two §3 checks resolve their subject as "the newest r*-… artefact". Both were
# built and both fired on the WRONG WAVE's file within two ticks of each other:
#
#   - the report-order check (REV-142 §2) compares REPORT.md against the newest
#     r*-gate.log. Inside a gate, the newest gate log is THIS SCRIPT'S OWN OUTPUT,
#     being written as the comparison runs — so "the report is older" is trivially
#     true of every correctly-ordered wave, and its ⛔ arm fired on run 147, which
#     had ordered itself perfectly.
#   - the schema annotation (REV-138 §2) reads the newest r*-doctor.txt. REV-119 §D
#     orders the gate BEFORE the stage dump, so inside a gate the current wave's
#     dump does not exist yet, by construction — it can only ever find the previous
#     wave's, and it set fail=1 on run 147's gate for run 146's artefact.
#
# ⭐ The general form, and it is the one worth keeping: A CHECK THAT RESOLVES ITS
#   SUBJECT AS "THE NEWEST ARTEFACT" CARRIES AN IMPLICIT CLOCK, and running it
#   inside the wave it is measuring points that clock either at its own output or
#   at the wave before. REV-138 §1 ruled the neighbouring case — a check that
#   reports by quoting can match itself — and this is the same defect with a
#   timestamp instead of a string.
#
# `want_tests` is the discriminator and it is exact: with --tests this script IS
# the gate, so both subjects are known to belong to another wave and the finding is
# LABELLED rather than asserted. Bare (tick time) the newest of each really is the
# last completed wave's, the reader is the supervisor, and both arms stand.
SV_T0=$(date +%s)
if [ "$want_tests" = 1 ]; then wave_self=1; else wave_self=0; fi

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

# ⛔⛔ PER-LANE TEST DATABASE (REV-119, 2026-09-09). Re-ported after run 114's
#     fast-forward took Track 1's copy of this file whole and dropped this lane's
#     version of §7. `main`'s app/phpunit.xml pins goaiez_antig_test for EVERY
#     lane, and app/phpunit.xml is a never-merge path — so after the fast-forward
#     nothing at all was left holding the separation. RefreshesTenantDatabase runs
#     migrate:fresh, which DROPS every table first, so one bare `--tests` from any
#     of the seven checkouts destroys the test database of the other six.
#
# ⭐ §7's clash guard below does not substitute for this. It SERIALISES suites that
#   share a database; it never SEPARATES them. Serialising seven lanes onto one
#   database makes the destruction orderly, not absent.
#
# ⭐⭐ Derived, and an already-exported DB_DATABASE WINS — a derived default is the
#    one value a brief must be able to override without editing a tracked file.
#    NOT exported globally on purpose: §4's doctor and its schema stage read the
#    LIVE database, and exporting here would silently repoint them off .env's
#    goaiez_antig_reviews. The value is applied at the pest call in §7 and nowhere
#    else, which is the only place whose blast radius is the suite.
lane_db=${DB_DATABASE:-goaiez_antig_$(basename "$ROOT" | sed 's/^grs-antig-*//')_test}
[ "$lane_db" = "goaiez_antig__test" ] && lane_db=goaiez_antig_test
echo "  effective (§7)   DB_DATABASE=$lane_db"
if [ "$lane_db" = "$PROD_DB" ]; then
  echo "  ⛔ the effective test database is PRODUCTION. Stop. Nothing below may run."; exit 2
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

bar "2f. per-track paths a merge would take from THEIRS silently  (merge=ours cannot fire)"
# ⛔⛔ REV-126 (2026-09-09). REV-119 §A ruled that "a fast-forward is a write to every
# per-track path", and blamed the fast-forward. THAT DIAGNOSIS WAS TOO NARROW and the
# narrowness is why run 122 was about to repeat run 114 through an ordinary merge.
#
# `merge=ours` is a CONFLICT-RESOLUTION driver. Git consults it only when it performs a
# three-way content merge for the path — i.e. only when BOTH sides moved the path since the
# merge base. When our side has NOT moved it and theirs has, there is no conflict to
# resolve: git takes theirs as a trivial file-level fast-forward and the driver is never
# called. A `.gitattributes` full of `merge=ours` is silent in exactly that case.
#
# ⭐ And the round trip makes that case the NORMAL one, not the exotic one: once Track 1
#   merges this lane into main, the merge base advances to a lane commit that contains the
#   lane's own copy of every per-track path. From then on the lane looks unchanged on all of
#   them and only main moves — so the lane silently adopts main's. `merge=ours` protects
#   whichever side is "ours" at merge time; it cannot protect a path across a
#   lane→main→lane round trip. Measured 2026-09-09: base e3aea7ff (this lane's own commit),
#   four of eight paths in the bypass state, including app/phpunit.xml (six lanes' test
#   databases) and .agents/rules/10-supervisor.md (the anti-push rule, REV-121 §1).
#
# ⭐⭐ The list is READ FROM .gitattributes, never restated here. REV-119 §A's own defect was
#    a stated list generalised by a sentence; REV-121 §A repeated it. The authoritative
#    statement of "per-track" is the `merge=ours` lines, so this check derives from them and
#    a path added there is covered the same run, with no edit to this file.
#
# No fetch: `origin/main` is read as the LOCAL remote-tracking ref. The gate never fetches
# (root-owned FETCH_HEAD, CLAUDE.md) — refreshing it is the caller's step.
if ! git rev-parse -q --verify origin/main >/dev/null 2>&1; then
  echo "  no local origin/main ref — nothing to compare (refresh with: git fetch --no-write-fetch-head origin)"
elif [ ! -f "$ROOT/.gitattributes" ]; then
  echo "  ⚠ no .gitattributes — the per-track list has no authoritative statement"
else
  ptbase=$(git merge-base HEAD origin/main 2>/dev/null || true)
  echo "  base $(git rev-parse --short "$ptbase")  ours HEAD  theirs origin/main ($(git rev-parse --short origin/main))"
  echo "  merge.ours.driver=$(git config --get merge.ours.driver || echo '⛔ UNSET — the driver never runs at all')"
  pt_n=0; pt_bypass=0
  while read -r pt_path pt_attr; do
    case "$pt_attr" in merge=ours) ;; *) continue;; esac
    [ -n "$pt_path" ] || continue
    pt_n=$((pt_n+1))
    pt_ours=$(git diff --name-only "$ptbase" HEAD -- "$pt_path" 2>/dev/null)
    pt_theirs=$(git diff --name-only "$ptbase" origin/main -- "$pt_path" 2>/dev/null)
    if [ -z "$pt_theirs" ]; then
      echo "     ✓ $pt_path — theirs unchanged since base; nothing incoming to take"
    elif [ -n "$pt_ours" ]; then
      echo "     ✓ $pt_path — both sides moved; the merge=ours driver FIRES and keeps ours"
    else
      pt_bypass=$((pt_bypass+1))
      echo "     ⛔ $pt_path — OURS UNCHANGED, THEIRS MOVED: a merge takes THEIRS with no conflict and no driver"
      echo "        $(git diff --shortstat "$ptbase" origin/main -- "$pt_path")"
      echo "        inspect: git diff HEAD origin/main -- $pt_path"
    fi
  done < "$ROOT/.gitattributes"
  if [ "$pt_bypass" -gt 0 ]; then
    echo "  ⛔ $pt_bypass of $pt_n per-track path(s) would be silently overwritten by a merge from origin/main."
    echo "     A merge wave must restore each one AFTER the merge, or move it on our side BEFORE the merge."
    echo "     ⚠ --allow-restore REFUSES CLAUDE.md, bin/supervise.sh, .agents/rules/** and .claude/** —"
    echo "       a bypass on one of those is the SUPERVISOR's to repair, and the coder cannot do it."
    fail=1
  else
    echo "  $pt_n per-track path(s) checked · none in the bypass state ✓"
  fi
fi

bar "2g. a class the COMMITTED tree references whose file git does not have"
# Run 138 committed `throw new \App\Modules\CReviews\Domain\UnauthenticatedConfirmationException`
# and left the class file UNTRACKED. `git commit -m … -- <paths>` does not add an untracked
# path and reports no error, so the sha references a class that does not exist in it. Every
# instrument in the wave was green: §2b's `php -l` parses a missing class fine, phpstan read
# `errors 0`, and the re-derivation grep passed — because ALL of them read the WORKING TREE,
# where the file is present. REV-140 §4 fixed the TIMING of that grep ("after git commit
# returns") and left the tree/commit distinction untouched; this is the instrument half.
# The baseline is therefore HEAD, never the working tree: `git grep … HEAD`.
# Arms: ⛔ when a tracked file at HEAD names an untracked file's class; ⚠ when an untracked
# app PHP file is unreferenced (still invisible to a named-path commit); ✓ when there are none.
bar_untracked=$(git -C "$ROOT" ls-files --others --exclude-standard -- 'app/*.php' 2>/dev/null)
if [ -z "$bar_untracked" ]; then
  echo "  no untracked PHP under app/ ✓"
else
  ut_ref=0; ut_n=0
  for u in $bar_untracked; do
    ut_n=$((ut_n+1))
    cls=$(basename "$u" .php)
    refs=$(git -C "$ROOT" grep -l -F "$cls" HEAD -- 'app/*.php' 2>/dev/null | sed 's/^HEAD://')
    if [ -n "$refs" ]; then
      ut_ref=$((ut_ref+1))
      echo "  ⛔ $u is UNTRACKED, and HEAD references $cls:"
      printf '%s\n' "$refs" | sed 's/^/       /'
      echo "     the committed sha cannot run — git commit -- <path> silently skips an untracked file"
    else
      echo "  ⚠ $u is untracked and unreferenced at HEAD — a named-path commit will not pick it up"
    fi
  done
  echo "  untracked app PHP: $ut_n · referenced by HEAD: $ut_ref"
  [ "$ut_ref" -gt 0 ] && fail=1
fi

bar "2h. a module class the AUTOLOADER cannot resolve  (tracked, parseable, unloadable)"
# ⛔ REV-146, 2026-09-10. Run 141's filtered suite read
#     Class "App\Modules\CReviews\Domain\UnauthenticatedConfirmationException" not found
# on a class that IS at HEAD, in the right namespace, at the right path, with the right
# body — and that §2g reports clean, because §2g asks whether git HAS the file.
# `app/composer.json` reaches the modules with `"classmap": ["app/Modules/"]` and NO psr-4
# prefix can (the directory is `C-Reviews`, the namespace segment is `CReviews`), so a
# class under app/app/Modules is loadable only if `composer dump-autoload` has run SINCE
# it was written. The classmap was generated 04:44:12 and the class landed at 08:28:59.
# ⭐ So the suite measured a failure that NO SHA OWNS — a stale build artefact, not a
# regression, and indistinguishable from one in the output. Same family as REV-119 §B,
# where `schema` reads a live database rather than the tree.
# §2g fixed tracked-vs-untracked and left resolvable-vs-unresolvable one axis over, which
# is this project's standing shape: REV-138 §4 fixed a search's scope and left its
# vocabulary, REV-140 §4 fixed a grep's timing and left the grep.
# Arms: ⛔ declared under app/app/Modules, absent from the classmap AND with no psr-4 file;
# ⚠ no classmap at all (composer install has not run here); ✓ every declared class resolves.
cm_file="$APP/vendor/composer/autoload_classmap.php"
if [ ! -f "$cm_file" ]; then
  echo "  ⚠ $cm_file is absent — composer install has not run in this checkout; not measured"
else
  cm_keys=$(mktemp "${TMPDIR:-/tmp}/cmkeys-XXXXXX")
  sed -n "s/^[[:space:]]*'\([^']*\)'[[:space:]]*=>.*/\1/p" "$cm_file" | sed 's/\\\\/\\/g' | sort -u > "$cm_keys"
  # ⛔ The namespace is the LAST declaration before the class, not the first. Its first
  # draft used `sed … | head -1` and reported App\Modules\X170\Events\PackSeeded as
  # unresolvable; the file declares `namespace App\Modules\X170\Events;` on one line and
  # `namespace App\Modules\X180\Events;` on the next, and PHP binds the class to the
  # second. A one-line-per-property grep cannot read a language with scope — this walks
  # the file the way the parser does and stops at the first top-level declaration.
  cm_pairs=$(mktemp "${TMPDIR:-/tmp}/cmpairs-XXXXXX")
  git -C "$ROOT" ls-files -z -- 'app/app/Modules/*.php' 2>/dev/null | xargs -0 -r awk '
    FNR==1 { ns=""; done=0 }
    done { next }
    /^namespace[ \t]+/ { ns=$0; sub(/^namespace[ \t]+/,"",ns); sub(/;.*/,"",ns); gsub(/[ \t\r]/,"",ns); next }
    /^((final|abstract|readonly)[ \t]+)*(class|interface|trait|enum)[ \t]+[A-Za-z_]/ {
      cn=$0
      sub(/^((final|abstract|readonly)[ \t]+)*/,"",cn)
      sub(/^(class|interface|trait|enum)[ \t]+/,"",cn)
      sub(/[^A-Za-z0-9_].*/,"",cn)
      if (ns != "" && cn != "") printf "%s\\%s\t%s\n", ns, cn, FILENAME
      done=1
    }' > "$cm_pairs"
  cm_seen=$(wc -l < "$cm_pairs" | tr -d ' ')
  cm_miss=0
  cm_gone=$(mktemp "${TMPDIR:-/tmp}/cmgone-XXXXXX")
  cut -f1 "$cm_pairs" | sort -u | comm -23 - "$cm_keys" > "$cm_gone"
  while IFS= read -r m_fq; do
    [ -n "$m_fq" ] || continue
    # psr-4 fallback: `App\` => `app/`, so a module whose directory DOES match its
    # namespace segment is resolvable without the classmap. Not a violation.
    m_rel=$(printf '%s' "$m_fq" | sed 's/^App\\//; s/\\/\//g')
    [ -f "$APP/app/$m_rel.php" ] && continue
    cm_miss=$((cm_miss+1))
    echo "  ⛔ $m_fq is declared at"
    grep -F "$m_fq	" "$cm_pairs" | cut -f2 | sed 's/^/       /'
    echo "     and is in NEITHER the composer classmap NOR a psr-4 path — every run that"
    echo "     loads it dies with 'Class … not found', and that failure belongs to this"
    echo "     checkout's vendor/, not to any commit.  Fix: (cd app && composer dump-autoload)"
  done < "$cm_gone"
  rm -f "$cm_pairs" "$cm_gone"
  echo "  module classes declared: $cm_seen · unresolvable: $cm_miss · classmap keys: $(wc -l < "$cm_keys" | tr -d ' ')"
  echo "  classmap generated: $(date -r "$cm_file" '+%Y-%m-%d %H:%M:%S')"
  rm -f "$cm_keys"
  [ "$cm_miss" -gt 0 ] && fail=1
fi

bar "2i. uncommitted app/ work — a tree state no sha owns  (REV-156 §1)"
# ⛔ RUN 151 HAND-REVERTED A MUTATION AND LEFT ONE BLANK LINE BEHIND, AND THAT
#   BLANK LINE TURNED THE GATE'S PINT RED ON A SHA WHOSE OWN COPY OF THE FILE IS
#   CLEAN. The report then cited `r151-pint.txt` — a genuine, correctly-echoed
#   `pint --test` taken 15 minutes BEFORE the mutation — and never mentioned that
#   its own gate, 15 minutes after, read `result: fail` on the file the wave had
#   dirtied. Both statements were true of the moment they were taken and the
#   wave's conclusion was false.
#
# ⭐ The property is not "the coder was untidy". Every working-tree instrument in
#   this script — §2b's `php -l`, §2c's debris grep, §2h's autoloader walk, §6's
#   pint and phpstan — reads the CHECKOUT, while the reviewer, the push and
#   `git log` read `HEAD`. An uncommitted `app/` path is the one state where
#   those two disagree, so it either hides a defect from the gate (REV-128 §2,
#   where the working tree was RIGHT and HEAD was wrong) or invents one that no
#   sha owns (here, inverted). CLAUDE.md has said "do not review a dirty tree"
#   since this lane began; it was a sentence, and a sentence cannot be read by
#   the gate. REV-138 §2's ladder, an eleventh time: a CHECK beats a redirect.
#
# ⭐ `app/phpunit.xml` is EXCLUDED and that exclusion is derived, not stated: §2
#   above already prints it as a forbidden path every single run, so re-reporting
#   it here would be a second copy of one finding. It is the lane's standing
#   REV-128 divergence — committable by neither column — and it is the reason
#   this check cannot simply demand a clean tree.
#
# ⚠ The neighbouring property this does NOT cover (REV-146 §1's standard): it
#   sees a path git knows is modified or untracked. A file changed and changed
#   back reads clean, and so does an edit inside a path `.gitignore` hides —
#   which is exactly how a stray local `phpunit.xml` goes unnoticed.
_dirty=$(git -C "$ROOT" status --porcelain --untracked-files=all -- app 2>/dev/null \
         | sed 's/^...//' | grep -v '^app/phpunit\.xml$' || true)
if [ -n "$_dirty" ]; then
  printf '%s\n' "$_dirty" | sed 's/^/  ⛔ /'
  echo "     uncommitted under app/ — invisible to review and to the push, while pint,"
  echo "     phpstan, php -l and the autoloader walk above all measured it. A number"
  echo "     from this gate is a statement about the CHECKOUT, not about HEAD."
  echo "     Repair by content, never by hand: git show <sha>:<path> > <path>,"
  echo "     verified by an empty  git diff <sha> HEAD -- <path>  (REV-127)."
  fail=1
else
  echo "  none (app/phpunit.xml excluded — §2 reports it, REV-128)"
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
# ⛔ A MAILBOX FILE THAT LANDED SOMEWHERE ELSE (REV-134 §3, REV-135 §4).
#
# Run 129 wrote REPORT.md to the repo ROOT. The block above reads only the
# mailbox copy, so it advertised run 128's report as current and would have gone
# on doing so indefinitely — the tick's own session-start step 2 ("is REPORT.md
# newer than the last REVIEWS block?") resolves against that same stale path.
# The damage is silent and it compounds every tick.
#
# ⭐ What was missing is the DISPLAY, not the file: a stray copy at the root is
#   now reported beside the real one, with both timestamps, so no reader can
#   mistake which is which. Run 130 left one behind — the positive control.
for f in BRIEF REPORT REVIEWS; do
  s="$ROOT/$f.md"
  if [ -f "$s" ]; then
    fail=1
    printf '    ⛔ %s at the REPO ROOT %s  %4s lines — the mailbox is .agents/supervisor/%s\n' \
      "$f.md" "$(date -r "$s" '+%F %T')" "$(wc -l <"$s")" "$f.md"
    echo "       a mailbox file outside the mailbox is read by nobody and goes stale silently"
  fi
done

# ⛔ AND THE SAME DEFECT FOR EVERY *OTHER* ARTEFACT — WHICH IS THE HALF THAT BIT
#   (REV-151 §2).
#
# The loop above covers three FILENAMES. Run 146 wrote all four of its wave
# artefacts — r146-gate.log, r146-doctor.txt, r146-pgstat.txt, r146-deadlock.log
# — to the repo root, and none of them is called BRIEF/REPORT/REVIEWS, so nothing
# above said a word. The cost was not provenance. It was that BOTH of this lane's
# newest checks resolve their input by globbing `.agents/supervisor/r*-…` and
# therefore silently fell back to the PREVIOUS run's file and printed a green
# line about it:
#
#   ✓ REPORT.md 13:50:21 is newer than r145-gate.log 13:13:35    ← r146's was 13:53:13
#   schema, annotated … — from r145-doctor.txt: FAIL schema 14   ← r146's could not connect
#
# Both readings were false and both looked like passes. ⭐ A check that resolves
# its own input by glob inherits every way that glob can miss, so the miss has to
# be reported where it happens rather than at each consumer.
#
# ⭐ Fifth instance of this lane's axis sub-species (REV-146 §1): REV-135 §4 fixed
#   REPORT.md's LOCATION and left every other artefact's location one axis over.
#   Per REV-146 §1, the neighbouring property this does NOT cover: it finds a
#   wave artefact at the repo root, and says nothing about one written to a third
#   directory that is neither.
_stray=$(find "$ROOT" -maxdepth 1 -type f \( -name 'r[0-9]*-*.txt' -o -name 'r[0-9]*-*.log' \) 2>/dev/null | sort)
if [ -n "$_stray" ]; then
  fail=1
  printf '    ⛔ %s wave artefact(s) at the REPO ROOT — the mailbox is .agents/supervisor/\n' \
    "$(printf '%s\n' "$_stray" | wc -l | tr -d ' ')"
  printf '%s\n' "$_stray" | while read -r s; do
    [ -n "$s" ] || continue
    printf '       %-28s %s  %6s bytes\n' "${s##*/}" "$(date -r "$s" '+%F %T')" "$(wc -c <"$s" | tr -d ' ')"
  done
  echo "       ⚠ every r*-gate.log / r*-doctor.txt consumer below globs the mailbox only,"
  echo "         so it has just resolved to an OLDER run's file and reported on that"
fi

# ⛔ A REPORT WRITTEN BEFORE ITS OWN GATE FINISHED (REV-131 §1, REV-142 §2).
#
# REV-131 §1 ruled `REPORT.md` the LAST artefact of a wave: written after the gate
# log exists, quoting a line from it. Run 126 broke it by six seconds. Run 137
# broke it by TWENTY-NINE MINUTES — REPORT.md 05:26:40, r137-gate.log 05:55:43 —
# and used the gap to assert `pint passed`, `phpstan errors 0` and a TIMEOUT that
# had not happened yet. The gate's own §7 finished `tests None · result timeout`,
# so the wave has NO measured suite and the report described one anyway.
#
# ⭐ The rule was stated, restated and violated twice, which is this lane's signal
#   that a restatement is not the remedy (REV-138 §2's ladder: a paste-ready
#   string beats a citation, a redirect beats a paste-ready string, and a CHECK
#   beats a redirect). A timestamp comparison cannot be paraphrased by a report.
#
# ⚠ Compared against the NEWEST r*-gate.log, not a named one, so the check needs
#   no run number and cannot be pointed at a stale log. A wave with no gate log at
#   all is silent here — §7 is the check for that.
_gl=$(find "$ROOT/.agents/supervisor" -maxdepth 1 -name 'r*-gate.log' -type f -mmin -1440 2>/dev/null \
      | xargs -r ls -t 2>/dev/null | head -1)
_rp="$ROOT/.agents/supervisor/REPORT.md"
if [ -n "$_gl" ] && [ -f "$_rp" ]; then
  # ⛔ REV-152: inside a gate, "$_gl" is THIS RUN'S OWN OUTPUT — the log this
  #   script is being redirected into, touched moments ago. Comparing the report
  #   against it asks whether the previous wave's report predates a file that did
  #   not exist until now, which is true of every correctly-ordered wave and was
  #   printed as a ⛔ against run 147. Labelled here, asserted only at tick time.
  if [ "$wave_self" = 1 ] && [ "$(date -r "$_gl" '+%s')" -ge "$SV_T0" ]; then
    printf '    ⚠ %s is THIS run’s own output (touched %s, after this script started %s).\n' \
      "${_gl##*/}" "$(date -r "$_gl" '+%T')" "$(date -d "@$SV_T0" '+%T')"
    printf '      REPORT.md %s is the PREVIOUS wave’s — REV-119 §D puts this wave’s report\n' \
      "$(date -r "$_rp" '+%F %T')"
    echo "      after this gate, so the order is checked at tick time, not from inside the gate."
  elif [ "$_rp" -ot "$_gl" ]; then
    fail=1
    printf '    ⛔ REPORT.md %s is OLDER than %s %s\n' \
      "$(date -r "$_rp" '+%F %T')" "${_gl##*/}" "$(date -r "$_gl" '+%F %T')"
    echo "       the report was written before its own gate finished — every test/pint/phpstan"
    echo "       claim in it is a prediction, not a measurement (REV-131 §1)"
  else
    printf '    ✓ REPORT.md %s is newer than %s %s\n' \
      "$(date -r "$_rp" '+%F %T')" "${_gl##*/}" "$(date -r "$_gl" '+%F %T')"
  fi
fi

# ⛔ REPORT.md CARRIES RULE 10's HEADER, OR THE WAVE IS INVISIBLE TO EVERY READER
#   THAT IS NOT THIS TICK (REV-155 §2, 2026-09-10).
#
# Run 150's report was fifteen lines: one line per brief item, in order, and NOT
# ONE header field. No STATUS, no COMMITS, no TESTS, no DOCTOR, no RAW. Every
# claim in it was true and every artefact it cited was quoted — and the reviewer
# still had to re-derive the whole wave from `git log`, the gate log and the
# doctor dump, which is precisely the work the report exists to save.
#
# ⭐ It was the SUPERVISOR's defect: run 150's brief item 9 specified "one line
#   per brief item, in order, 1 through 9" and a 150-line ceiling, and said
#   nothing about the header — so the brief read as a complete specification of
#   the report and the coder complied with it exactly. Same shape as REV-134 §2
#   (the one artefact whose path was left implicit is the one that landed in the
#   wrong directory) and REV-142 §1 (a brief that names a parameter and never
#   asks where it comes from). A brief that specifies part of an artefact reads
#   as specifying all of it.
#
# ⭐ THE FIELD LIST IS DERIVED FROM `.agents/rules/10-supervisor.md`, NEVER
#   RESTATED HERE. REV-119 §A's defect is a stated list where a derived one
#   belonged, and REV-126's §2f fix set the standard: read the list from the file
#   that owns it, so the check cannot drift from the contract it enforces. If
#   rule 10's shape block changes, this check follows it with no edit.
#
# ⚠ The neighbouring property this does NOT cover (REV-146 §1's standard): it
#   asserts each field is PRESENT, never that its value is measured. `TESTS: none`
#   on a wave that ran a suite passes here — REV-146 §5 is the ruling for that,
#   and it is a judgement a grep cannot make.
#
# ⛔ REV-156 §3: THIS CHECK CARRIED REV-152 §1's IMPLICIT CLOCK AND FIRED ON THE
#   WRONG WAVE'S REPORT ON ITS FIRST RUN. REV-119 §D puts the report AFTER the
#   gate, so inside a `--tests` run `REPORT.md` is necessarily the PREVIOUS
#   wave's — and run 151's gate printed `⛔ missing 10 of 10` about run 150's
#   report, three minutes before run 151's own report was written carrying all
#   ten. REV-152 §1 ruled this for the two checks above and this one was written
#   one tick later without the discriminator: the ruling was not the shape of the
#   check, so it did not carry. Labelled inside a gate, asserted at tick time.
_r10="$ROOT/.agents/rules/10-supervisor.md"
if [ -f "$_rp" ] && [ -f "$_r10" ]; then
  _fields=$(grep -oE '^[A-Z]{3,10} *:' "$_r10" | tr -d ' :' | sort -u)
  if [ -n "$_fields" ]; then
    _miss=""; _have=0; _n=0
    for _f in $_fields; do
      _n=$((_n + 1))
      if grep -qE "^ *$_f *:" "$_rp"; then _have=$((_have + 1)); else _miss="$_miss $_f"; fi
    done
    if [ -n "$_miss" ] && [ "$wave_self" = 1 ]; then
      printf '    ⚠ REPORT.md %s is the PREVIOUS wave’s (this wave’s comes after this gate,\n' \
        "$(date -r "$_rp" '+%F %T')"
      printf '      REV-119 §D) — it is missing %d of %d rule-10 header field(s):%s\n' \
        "$((_n - _have))" "$_n" "$_miss"
      echo "      Not asserted from inside a gate; the header is checked at tick time (REV-156 §3)."
    elif [ -n "$_miss" ]; then
      fail=1
      printf '    ⛔ REPORT.md is missing %d of %d rule-10 header field(s):%s\n' \
        "$((_n - _have))" "$_n" "$_miss"
      echo "       a report with no header is one wave's item list — the next reader has no"
      echo "       STATUS, no COMMITS, no TESTS and no DOCTOR stamp, and must re-derive the"
      echo "       wave from the tree, which is the work the report exists to save (REV-155 §2)"
    else
      printf '    ✓ REPORT.md carries all %d rule-10 header fields\n' "$_n"
    fi
  fi
fi

# ⛔ A WAVE ARTEFACT THAT RECORDS A SHELL ERROR INSTEAD OF A MEASUREMENT (REV-137 §1).
#
# Run 132's `.agents/supervisor/r132-doctor.txt` was 40 bytes and read
# `bash: line 1: goaiez: command not found`. The stage dump never ran — and
# REPORT.md, written 41 seconds later, said "All stages clean. No stage moved."
# and cited that file. A redirect is only an instrument if the command that
# fills it actually runs; an empty-of-measurement artefact is worse than a
# missing one, because its existence reads as evidence.
#
# ⭐ The same read catches the other half: run 132's r132-seam.txt carried
#   `grep: app/routes/routes.generated.php: No such file or directory` — the
#   brief had named a path that does not exist — and the report converted that
#   error into "the file does not exist", a statement about the tree. A grep
#   error in an artefact is a REFUSED-shaped event, and the reviewer must see it
#   without opening every file.
#
# Scoped to the last 24h so yesterday's artefacts age out on their own, and it
# prints the count it scanned so a clean reading is legible — a check that only
# ever prints nothing is indistinguishable from a check that is not running.
#
# ⛔ AND IT FLAGGED ITS OWN OUTPUT ON ITS SECOND RUN (REV-138 §1). A gate log is
#   an artefact matching `r*-*`, and this block PRINTS the offending line
#   verbatim, so run 133's `r133-gate.log:102` carried
#   `bash: line 1: goaiez: command not found` — quoted from r132-doctor.txt by
#   this very loop. The count went 2 → 3 for a benign reason, which is the
#   failure mode of a permanent ⛔: it trains the reader to skip the section that
#   would show a real one.
#
#   The fix is NOT to skip `*-gate.log` — a gate log can carry a real error from
#   a command inside the gate, and excluding the file would blind the check to
#   exactly that. Instead the detector's OWN output block is blanked before the
#   scan: `s/.*//` over the range from its `carries a shell/grep error` line to
#   its `wave artefacts` footer, which preserves line numbering so the number it
#   reports stays truthful. Nothing but this loop's output lives in that range.
_art_n=0; _art_bad=0
for p in $(find "$ROOT/.agents/supervisor" -maxdepth 1 -name 'r*-*' -type f -mmin -1440 2>/dev/null | sort); do
  _art_n=$((_art_n + 1))
  # ⛔ REV-146 §6 — the SAME self-match through a third route. Run 141 wrote a
  #   `bash -x` gate trace (r141-gate-debug.log, 752 KB) and this loop flagged it at
  #   line 29960, which is `++ grep -m1 -nE 'command not found|No such file or …'` —
  #   the trace echoing THIS detector's own pattern. REV-138 §1 blanked the
  #   detector's OUTPUT range; a `set -x` trace prints the PATTERN, a thousand lines
  #   earlier, and splitting the literal in source would not help because bash traces
  #   the expanded argument. A trace line is a command echo, never an error, so
  #   `^+` lines are blanked too — a real error inside a traced script is written by
  #   the failing command to stderr and does not carry the `+` prefix, so nothing is lost.
  hit=$(sed -e '/carries a shell\/grep error at line/,/wave artefacts (24h):/s/.*//' -e '/^++*[[:space:]]/s/.*//' "$p" 2>/dev/null \
        | grep -m1 -nE 'command not found|No such file or directory|Permission denied|: syntax error|Could not open input file' || true)
  [ -n "$hit" ] || continue
  _art_bad=$((_art_bad + 1)); fail=1
  printf '    ⛔ %s carries a shell/grep error at line %s\n' "${p##*/}" "${hit%%:*}"
  printf '       %s\n' "$(printf '%s' "$hit" | cut -d: -f2- | cut -c1-100)"
done
if [ "$_art_n" -gt 0 ]; then
  printf '  wave artefacts (24h): %s scanned · %s carrying an error\n' "$_art_n" "$_art_bad"
  [ "$_art_bad" -gt 0 ] && echo "       a redirect whose command did not run is not a measurement — the report must not cite it"
fi

# ⛔ AN ARTEFACT IS THE REDIRECT OF A NAMED COMMAND, OR IT IS A MEMORY (REV-153 §2).
#
# r147-pint.txt and r148-pint.txt are both 33 bytes reading
# `{"tool":"pint","result":"passed"}` — that is `run_tool`'s shape from §6 of THIS
# script, not the output of `(cd app && ./vendor/bin/pint --test)`, which both
# briefs named and which prints a progress table and a summary. Both claims were
# TRUE and nothing was concealed; what is missing is the property REV-132's erratum
# ruled — a number with no command beside it is a memory. Restating the instruction
# a second time did not fix it (REV-138 §2's ladder: a paste-ready string beats a
# citation, a redirect beats a paste-ready string, and a CHECK beats a redirect),
# and a redirect is exactly the rung that cannot say WHICH command filled it.
#
# So the artefact format carries its own provenance: the first line is `$ <command>`.
# The subject is the NEWEST RUN NUMBER's artefacts, not the last 24h — the convention
# starts at run 149 and an older artefact must not be retro-flagged forever.
#
# ⚠ The neighbouring property this does NOT cover (REV-146 §1): it checks that a
#   command is NAMED, never that the named command is the one that ran. A wrong echo
#   over a right artefact reads clean. That gap is deliberate — the alternative is a
#   per-tool output-shape table, which is REV-119 §A's stated list waiting to drift.
_pv_run=$(find "$ROOT/.agents/supervisor" -maxdepth 1 -name 'r[0-9]*-*' -type f -printf '%f\n' 2>/dev/null \
          | sed -n 's/^r\([0-9][0-9]*\)-.*/\1/p' | sort -n | tail -1)
if [ -n "$_pv_run" ]; then
  _pv_n=0; _pv_bad=0
  for p in $(find "$ROOT/.agents/supervisor" -maxdepth 1 -name "r${_pv_run}-*" -type f 2>/dev/null | sort); do
    case "${p##*/}" in *-gate.log) continue ;; esac
    _pv_n=$((_pv_n + 1))
    _pv_first=$(grep -m1 -v '^[[:space:]]*$' "$p" 2>/dev/null || true)
    case "$_pv_first" in
      '$ '*) ;;
      *)
        _pv_bad=$((_pv_bad + 1)); fail=1
        printf '    ⛔ %s opens with no `$ ` command echo: %s\n' \
          "${p##*/}" "$(printf '%s' "$_pv_first" | cut -c1-60)"
        ;;
    esac
  done
  if [ "$_pv_n" -gt 0 ]; then
    printf '  run %s artefacts: %s checked · %s carry a command echo · %s do not\n' \
      "$_pv_run" "$_pv_n" "$((_pv_n - _pv_bad))" "$_pv_bad"
    if [ "$_pv_bad" -gt 0 ]; then
      echo "       a number with no command beside it is a memory (REV-132 erratum)."
      echo "       Write artefacts as: { echo '\$ <cmd>'; <cmd>; } > .agents/supervisor/rN-x.txt 2>&1"
      echo "       ⚠ this is a record defect, not a tree defect — it fails the gate and does"
      echo "         NOT revoke the sha by itself (REV-134 §1: the criterion is WHAT failed)."
    fi
  fi
fi

# ⛔ EVERY FAILING AND ERRORING TEST NAME IN EVERY PEST ARTEFACT, PRINTED HERE SO A
#   REPORT CANNOT OMIT ONE (REV-147 §1).
#
# Run 142's report read `r142-ts-modules.txt: tests 2030 · passed 2013 · failed 6`.
# Every number in it is true and the line is still wrong: the artefact also carries
# `"errors":11`, eleven X-102 ChatDoorTest entries reading
# `SQLSTATE[42501]: Insufficient privilege: 7 ERROR: must be owner of table
# account_mappings`, none of them in the admitted set. The brief had said in terms
# that a name outside that set "is the wave's headline". ⭐ The line contains the
# evidence of its own omission — 2013 + 6 ≠ 2030 — and the same report correctly
# wrote `errors 2` for the Journeys suite one line below, so the field was known.
#
# ⭐ THIS BLOCK DELIBERATELY DOES NOT CLASSIFY. The admitted set (REV-134 §1's eight
#   baseline names, plus `NOT BUILT:`, plus `UNRESOLVED — ` from tests/Journeys/**)
#   is a POLICY and belongs in one place, not copied into a script that would then
#   drift from it — that is REV-119 §A's stated-list defect. What the omission
#   needed was not a better classifier but that no name can go unprinted, so this
#   derives the PRODUCT (which tests failed) and leaves the judgement to the reader.
#
# Arms: ⚠ any artefact with failed+errors > 0 lists every name (advisory, no fail —
# an admitted failure is the normal state of this lane). ⛔ + fail=1 when the
# arithmetic does not close, i.e. tests ≠ passed + failed + errors, because that is
# a breakdown with a name hiding in the remainder.
_pest_seen=0
# ⚠ `.md` is excluded, and it is a CATEGORY rather than a filename (REV-138 §1's
#   constraint): a pest redirect target in this mailbox is always `.txt` or `.log`,
#   while `.md` is prose — a brief, a note, a REVIEWS draft — which can QUOTE a pest
#   JSON line and then fail to parse as one. `rev142-block.md` did exactly that and
#   produced a permanent ⚠, which is the failure mode of a warning that never clears.
for p in $(find "$ROOT/.agents/supervisor" -maxdepth 1 -name 'r*-*' -type f ! -name '*.md' -mmin -1440 2>/dev/null | sort); do
  grep -q '^{"tool":"pest"' "$p" 2>/dev/null || continue
  _pest_seen=$((_pest_seen + 1))
  grep -h '^{"tool":"pest"' "$p" | tail -1 | PEST_ART="${p##*/}" python3 -c '
import json, os, sys
name = os.environ["PEST_ART"]
try:
    d = json.loads(sys.stdin.read())
except Exception:
    print("    ⚠ %s: not parseable as a pest JSON line" % name); sys.exit(0)
t  = d.get("tests"); pa = d.get("passed")
f  = d.get("failed", 0) or 0; er = d.get("errors", 0) or 0
if t is None or pa is None:
    print("    ⚠ %s: result %s — no tests/passed to reconcile" % (name, d.get("result"))); sys.exit(0)
print("    %s: tests %s · passed %s · failed %s · errors %s" % (name, t, pa, f, er))
names = [e.get("test", "?").split("::")[-1] for e in (d.get("failures") or []) + (d.get("error_details") or [])]
for n in sorted(set(names)):
    print("       ✗ %s" % n)
gap = t - pa - f - er
if gap:
    print("    ⛔ %s: %s + %s + %s does not reach %s — %s test(s) unaccounted for" % (name, pa, f, er, t, gap))
    sys.exit(3)
' 2>/dev/null || fail=1
done
[ "$_pest_seen" -gt 0 ] && printf '  pest artefacts (24h): %s reconciled — a ✗ name outside the admitted set is the wave headline\n' "$_pest_seen"

# ⭐ `schema` IS REPORTED WITH THE DATABASE IT WAS READ FROM, OR IT IS NOT REPORTED
#   (REV-119 §B) — AND THAT INSTRUCTION IS NOW EMITTED RATHER THAN RESTATED.
#
# SchemaStage reads .env's live database, not the tree, so its count is not a
# property of a sha and every lane measures a different number on the identical
# commit. The rule has been handed to the coder as a citation (REV-132 §3, missed),
# as a firmer citation (REV-134 §5, missed), as a paste-ready literal string
# (REV-135, emitted correctly — the rung that worked) and as that same literal
# again (REV-138 §2, missed, because "STAGES: none moved" needs no line at all).
#
# ⭐ The ladder this lane has now measured four times: a paste-ready string beats a
#   citation, a redirect beats a paste-ready string, and a CHECK beats a redirect —
#   because a check cannot be omitted by a report that summarises. The annotated
#   line is produced here, from the coder's own doctor dump, so the report quotes a
#   line that already carries the database and cannot paraphrase the annotation
#   away. A brief fix protects one run; a check protects every run (REV-135 §4).
_doc=$(find "$ROOT/.agents/supervisor" -maxdepth 1 -name 'r*-doctor.txt' -type f -mmin -1440 2>/dev/null | sort | tail -1)
if [ -n "$_doc" ]; then
  _sch=$(grep -m1 -E '(ok|FAIL) schema ' "$_doc" 2>/dev/null | sed 's/^[[:space:]]*//' || true)
  if [ -n "$_sch" ]; then
    printf '  schema, annotated for the report (REV-119 §B) — from %s:\n' "${_doc##*/}"
    printf '    %s   (read from %s, per app/.env)\n' "$_sch" "${env_db:-<unset>}"
    # ⛔ A STAGE THAT COULD NOT CONNECT REPORTS A *LOWER* NUMBER, AND A LOWER
    #   NUMBER READS AS AN IMPROVEMENT (REV-151 §3).
    #
    # Run 146's dump: `FAIL schema 78ms 3 violation(s)`, down from 14 — and two of
    # the three rows are the stage saying it could not run:
    #
    #   · database: schema checks could not run: SQLSTATE[08006] [7] FATAL:
    #     remaining connection slots are reserved for roles with the SUPERUSER attribute
    #   · pg_roles: cannot read role attributes — BYPASSRLS is unverified
    #
    # Twelve `tenant-owned table has no RLS` rows did not get fixed; they got
    # skipped, because the stage never reached the tables. ⭐ This is REV-119 §B's
    # family at its sharpest — a stage whose input is a live database — and the
    # count-did-not-fall rule inverted: the danger is a count that FELL for a
    # reason that is not a fix (REV-129 §2). A summary line cannot show it, so the
    # rows have to be read.
    # ⛔ REV-152: with --tests this script IS the gate, and REV-119 §D puts the
    #   stage dump AFTER it — so "$_doc" is the PREVIOUS wave's dump, always, and
    #   a fail=1 here reddens this gate for an artefact this wave has not written
    #   yet and is about to supersede. That is what happened to run 147: its gate
    #   annotated run 146's unmeasured `3` while its own honest `14` landed 23
    #   seconds later. Labelled inside a gate, asserted at tick time.
    if grep -qE 'could not run|SQLSTATE\[08006\]|cannot read role attributes|connection slots' "$_doc" 2>/dev/null; then
      if [ "$wave_self" = 1 ]; then
        echo '    ⚠ that schema number is NOT MEASURED — and this dump is the PREVIOUS wave’s:'
      else
        fail=1
        echo '    ⛔ that schema number is NOT MEASURED — the stage could not reach the database:'
      fi
      grep -m3 -E 'could not run|SQLSTATE\[08006\]|cannot read role attributes|connection slots' "$_doc" 2>/dev/null \
        | sed 's/^[[:space:]]*/       /' | cut -c1-160
      echo '       a stage that could not connect reports FEWER violations, which reads as an'
      echo '       improvement. Do NOT state.py stage it, and do not compare it to the ledger.'
      if [ "$wave_self" = 1 ]; then
        echo '       REV-119 §D puts THIS wave’s dump after this gate, so it does not exist yet;'
        echo '       run your own doctor and annotate from that. Not a gate failure.'
      fi
    fi
  else
    printf '  ⚠ %s carries no schema line — the stage dump did not reach it\n' "${_doc##*/}"
  fi
fi

# ⭐ A `file:line` OFFERED AS EVIDENCE IS THE LINE THAT CONTAINS THE THING, AND
#   A LINE OF PROSE IS NOT THAT LINE (REV-136 §3, REV-141 §3).
#
# This lane keeps shipping citations that RESOLVE and still point at nothing.
# Measured, three runs:
#
#   REV-136 §3  create_review_destinations_table.php:31  → `Schema::create(...)`
#               ReviewDestinationSetting.php:40          → `final class …`
#               ReviewRouter.php:333                     → prose inside a docblock
#   REV-141 §3  CReviewsTest.php:512                     → `* ⛔ REFUSED: surveyed …`
#                                                          — a discharge citing the
#                                                          refusal it discharges
#
# Every one is the first grep hit in its file, not the line that settles the claim,
# and every one survived review only because the reviewer re-derived it by hand.
# `php artisan why` cannot help: it resolves module ids, not file offsets, and the
# citation stage counts a citation as good the moment the FILE exists.
#
# ⭐ The rung above a restatement is a check (REV-138 §2's ladder). What is
#   mechanically decidable is not "does this line prove the claim" but the weaker
#   property that catches all four above: a line that is BLANK or is pure comment
#   (`*`, `//`, `#`) carries no code, so it cannot be the line that contains the
#   thing. That is an advisory ⚠ and does not set fail — a comment CAN be the right
#   target when the claim is about a declaration in a docblock (the property
#   annotations on ReviewRemovalRequest are exactly that, and are cited correctly).
#
# ⛔ A citation whose line does not EXIST is different in kind and does set fail:
#   this repo already carries 64 unresolvable citations and CLAUDE.md's standing
#   rule is that the 65th is a BLOCK.
#
# Scope is derived, never stated (REV-119 §A): the added lines of the last ten
# commits plus the tail of the ledger, which is where discharges and (R245) lines
# are written. Self-reference is handled the way REV-138 §1 ruled — this block's
# own output range is blanked, not its filename excluded.
_cit_n=0; _cit_prose=0; _cit_dead=0
_cit_raw=$( { git -C "$ROOT" log -10 -p --format='' 2>/dev/null | grep '^+' || true
              tail -60 "$ROOT/.agents/state/JOURNAL.md" 2>/dev/null || true
            } | grep -oE '(app|bin|tests|database)/[A-Za-z0-9._/-]+\.(php|md):[0-9]+' | sort -u | head -60 )
for c in $_cit_raw; do
  _cf=${c%:*}; _cl=${c##*:}
  [ -f "$ROOT/$_cf" ] || { _cf="app/$_cf"; }
  _cit_n=$((_cit_n + 1))
  if [ ! -f "$ROOT/$_cf" ]; then
    _cit_dead=$((_cit_dead + 1)); fail=1
    printf '    ⛔ %s — no such file; the citation cannot resolve\n' "$c"; continue
  fi
  _tot=$(wc -l < "$ROOT/$_cf")
  if [ "$_cl" -lt 1 ] || [ "$_cl" -gt "$_tot" ]; then
    _cit_dead=$((_cit_dead + 1)); fail=1
    printf '    ⛔ %s — file has %s lines; the citation points past the end\n' "$c" "$_tot"; continue
  fi
  _txt=$(sed -n "${_cl}p" "$ROOT/$_cf")
  case $(printf '%s' "$_txt" | sed 's/^[[:space:]]*//') in
    ''|'*'*|'/*'*|'//'*|'#'*)
      _cit_prose=$((_cit_prose + 1))
      printf '    ⚠ %s lands on a comment or a blank line\n' "$c"
      printf '       %s\n' "$(printf '%s' "$_txt" | sed 's/^[[:space:]]*//' | cut -c1-90)" ;;
  esac
done
if [ "$_cit_n" -gt 0 ]; then
  printf '  citations in the last 10 commits + ledger tail: %s checked · %s on prose · %s unresolvable\n' \
         "$_cit_n" "$_cit_prose" "$_cit_dead"
  [ "$_cit_prose" -gt 0 ] && echo "       a citation offered as evidence names the line that CONTAINS the thing (REV-136 §3)"
fi

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
  bar "7. test suite  (effective DB_DATABASE → $lane_db; phpunit.xml pins $xml_db)"
  # REV-119: the clash guard must ask about the database pest will REALLY use, not
  # the one phpunit.xml pins — otherwise it scans siblings for a name nobody runs on.
  xml_db="$lane_db"
  # Refuse while another pest runs on THIS database from any checkout whose
  # phpunit.xml pins it (2026-09-05 07:1x: the sixty checkout wiped the schema
  # under a Track 1 gate — 32 spurious "relation does not exist" errors).
  shared=""
  for co in /home/goaiez/agents/grs-antig*; do
    grep -q "DB_DATABASE\" value=\"$xml_db\"" "$co/app/phpunit.xml" 2>/dev/null && shared="$shared $co"
  done
  clash=0; mine=0; minepids=""
  for p in $(pgrep -x php); do
    if tr '\0' ' ' < /proc/$p/cmdline 2>/dev/null | grep -q "bin/pes""t"; then
      c=$(readlink /proc/$p/cwd 2>/dev/null)
      for co in $shared; do case "$c" in "$co"/*) clash=$((clash+1)); echo "  ✗ pest pid $p running on $xml_db from $c";
        case "$c" in "$PWD"/*|"$PWD") mine=$((mine+1)); minepids="$minepids $p";; esac;;
      esac; done
    fi
  done
  if [ $clash -gt 0 ]; then
    echo "  ✗ REFUSED: $clash other pest process(es) on $xml_db (checkouts pinning it:$shared) — a gate now would be false"
    # REV-144 §1. The word "other" reads as ANOTHER LANE, and after REV-119 §E it usually
    # cannot be: $shared is every checkout whose phpunit.xml PINS $xml_db, and this lane's
    # database is pinned by this checkout alone. So the refusal names our own stray pest
    # and is fixable HERE — a distinct condition from the shared /home/goaiez/tmp/pest.lock
    # wait below, which really is every lane and ends in {"result":"lock-timeout"}.
    # Runs 137-139 read one as the other and lost three gates to it.
    # ⛔ REV-146 §4. This arm's FIRST live firing was a false alarm, and its wording was the
    # dangerous half. Run 141 started a second --tests two minutes into the real gate; the
    # refusal correctly identified pid 2625841 as this checkout's, then told the reader it
    # was "a leftover from an earlier wave" and to end it by pid. It was the running gate's
    # own pest. "From this checkout" is MEASURED; "left over" is a guess about what the
    # process is for, and acting on it kills the run you are waiting for. So: print what is
    # known — the pid and when it started — and let the reader decide.
    if [ $mine -gt 0 ]; then
      echo "  ⛔ $mine of those is THIS checkout's own pest (pid$minepids) — not another lane."
      for mp in $minepids; do
        echo "     pid $mp started $(ps -o lstart= -p "$mp" 2>/dev/null | sed 's/^ *//' || echo 'unknown')"
      done
      echo "     ⚠ If you started a gate, THIS IS IT — wait for it. Only a pest older than your own"
      echo "     wave is a stray, and a stray is ended BY PID (never pkill -f pest)."
    else
      echo "  ⚠ none of those is this checkout — another checkout pins $xml_db, which REV-119 §E says"
      echo "     should not happen. Report the checkout name; do not work around it."
    fi
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
  pest_started=$(date -Is); pest_t0=$(date +%s)
  # ⛔⛔ REV-149: §7 RUNS THE FOUR TESTSUITES AS FOUR PROCESSES, NOT ONE.
  #
  # The single bare `pest` above produced NO number for eight consecutive waves
  # (runs 137-144: TIMEOUT, REFUSED, REFUSED, TIMEOUT, TIMEOUT, then measured by
  # hand). Run 144 item 3 settled what the difference is, by a fork whose arms
  # were stated in advance:
  #
  #   four SEPARATE processes, one per testsuite   157 s, all four finish
  #     (12:22:16 -> 12:24:53, r144-uptime-start/end-item2.txt)
  #   ONE process, --testsuite=Unit,Feature,Modules,Journeys
  #     killed at its 400 s budget (12:25:30 -> 12:32:10, Arm B)
  #
  # Box load was comparable at both ends (1.42-2.68), so this is not REV-148 §5's
  # shared-box effect. The single process is the difference, and what it is doing
  # in there is STILL UNMEASURED — run 145 asks pg_stat_activity whether it is
  # working or blocked. This change routes the GATE around the defect; it does not
  # fix it and does not claim to (REV-145 §3: a wave that both diagnoses and treats
  # can no longer tell which of the two worked — so the diagnosis stays a separate
  # command in a separate column).
  #
  # ⭐ THE COVERAGE ARGUMENT IS MEASURED, NOT CONSTRUCTED. REV-148 §4 admitted the
  #   four-suite union on the reasoning that app/phpunit.xml declares no fifth
  #   suite. That is an argument from a file; here is the count from the tool:
  #
  #     $ grep -c '^ - ' .agents/supervisor/r142-list.txt      -> 2463   (--list-tests)
  #     $ Unit 1 + Feature 420 + Modules 2030 + Journeys 12    -> 2463
  #
  #   Equal. Every test a bare `pest` discovers is in exactly one of the four.
  #
  # ⛔ THE LIMIT, NAMED (REV-146 §1 — this lane's corrections keep landing one axis
  #   from the defect, so the axis gets said out loud): four processes CANNOT SHOW
  #   CROSS-SUITE INTERFERENCE. A test that fails only when another suite ran first
  #   in the same process is invisible to this gate — and that is precisely the
  #   property the single-process hang is about. This number is a suite result; it
  #   is not evidence that one process would be green.
  ptmp=$(mktemp "${TMPDIR:-/tmp}/pest-XXXXXX"); psdir=$(mktemp -d "${TMPDIR:-/tmp}/pestsuites-XXXXXX")
  ( for _ts in Unit Feature Modules Journeys; do
      DB_DATABASE="$lane_db" timeout 600 ./vendor/bin/pest --testsuite="$_ts" > "$psdir/$_ts.txt" 2>&1
      printf '%s %s\n' "$_ts" "$?" >> "$psdir/rc.txt"
    done ) & pjob=$!; pest_pid=$(pgrep -P "$pjob" 2>/dev/null | head -1); pest_pid=${pest_pid:-$pjob}; wait "$pjob"
  # rc keeps the old meaning for every branch below: 124 = a budget was hit,
  # non-zero = a suite was red, 0 = all four green. 124 outranks the rest so the
  # TIMEOUT arm still fires when any one suite is killed.
  rc=0
  while read -r _tsn _tsrc; do
    [ "$_tsrc" = "124" ] && rc=124
    [ "$_tsrc" != "0" ] && [ "$rc" = "0" ] && rc="$_tsrc"
  done < "$psdir/rc.txt" 2>/dev/null
  PEST_SUITE_DIR="$psdir" python3 - "$psdir/summary.txt" > "$ptmp" <<'PYMERGE'
import json, os, sys
d = os.environ['PEST_SUITE_DIR']
tot = {'tool': 'pest', 'result': 'passed', 'tests': 0, 'passed': 0,
       'failed': 0, 'errors': 0, 'assertions': 0, 'duration_ms': 0}
fails, errs, lines, missing = [], [], [], []
for n in ('Unit', 'Feature', 'Modules', 'Journeys'):
    try:
        raw = open(os.path.join(d, n + '.txt')).read().splitlines()
    except OSError:
        raw = []
    obj = None
    for ln in reversed(raw):
        if ln.startswith('{"tool":"pest"'):
            try:
                obj = json.loads(ln)
            except ValueError:
                obj = None
            break
    if obj is None:
        missing.append(n)
        lines.append('%-9s NO pest JSON LINE — %d byte(s) of output kept' % (n, sum(len(x) + 1 for x in raw)))
        continue
    for k in ('tests', 'passed', 'failed', 'errors', 'assertions', 'duration_ms'):
        tot[k] += int(obj.get(k) or 0)
    fails.extend(obj.get('failures') or [])
    errs.extend(obj.get('error_details') or [])
    lines.append('%-9s tests %-5s passed %-5s failed %-3s errors %-3s %sms' % (
        n, obj.get('tests'), obj.get('passed'), obj.get('failed', 0),
        obj.get('errors'), obj.get('duration_ms')))
tot['failures'] = fails
tot['error_details'] = errs
tot['suites'] = 4 - len(missing)
if missing:
    tot['result'] = 'incomplete'
    tot['missing'] = missing
elif tot['failed'] or tot['errors']:
    tot['result'] = 'failed'
open(sys.argv[1], 'w').write('\n'.join(lines) + '\n')
# ⛔ THE SEPARATORS ARE LOAD-BEARING, NOT COSMETIC (REV-150 §2, 2026-09-10). Three
#   consumers grep this line with the literal `^{"tool":"pest"` — :642's §3
#   reconciler, :1096's summary-and-every-failing-name printer, and whatever reads
#   /home/goaiez/tmp/last-pest.json. A bare json.dumps() emits `{"tool": "pest"`
#   WITH A SPACE, which matches none of them: run 145's gate printed 175 KB of raw
#   JSON and not one ✗ name, because :1096's branch — the one N137 wrote so a
#   failure could never be truncated silently — never ran. The merged line is a
#   drop-in for the per-suite line pest itself writes, so it is shaped like it.
sys.stdout.write(json.dumps(tot, separators=(',', ':')) + '\n')
PYMERGE
  out=$(cat "$ptmp")
  pest_elapsed=$(( $(date +%s) - pest_t0 ))
  echo "  four testsuites, four processes (REV-149) — union is every test --list-tests finds:"
  [ -s "$psdir/summary.txt" ] && sed 's/^/    /' "$psdir/summary.txt"
  # ⛔ THE RAW PER-SUITE OUTPUT IS KEPT BEFORE THE DIRECTORY GOES. REV-145 §1 is
  #   this lane's record of a check that deleted the one artefact that could have
  #   diagnosed it, and it cost two thirty-minute waves. The merged JSON above is
  #   a summary; these four files are the evidence, and `missing` names which of
  #   them to read first.
  pest_suites=${TMPDIR:-/tmp}/last-pest-suites
  rm -rf "$pest_suites" 2>/dev/null; cp -r "$psdir" "$pest_suites" 2>/dev/null || true
  echo "    per-suite raw output KEPT at $pest_suites/"
  rm -rf "$psdir" 2>/dev/null || true
  # ⛔⛔ REV-147: THE KEPT PARTIAL CANNOT DIAGNOSE ANYTHING, BECAUSE THIS SUITE'S
  #   PRINTER EMITS EXACTLY ONE LINE AND EMITS IT AT THE END.
  #
  # REV-145 kept the partial on the reasoning that "its LINE COUNT separates the
  # only two candidate causes outright — a suite still printing near the end is
  # too SLOW for the budget, a suite stopped a few hundred lines in is HUNG".
  # That premise is false here and run 142 measured it four ways:
  #
  #   r142-ts-unit.txt        1 line     86 B      4 ms
  #   r142-ts-feature.txt     1 line     97 B     27 949 ms
  #   r142-ts-modules.txt     1 line    177 199 B 121 946 ms
  #   r142-filter-screens.txt 1 line     92 B      1 774 ms
  #
  # One line each, over four orders of magnitude of runtime and three of size.
  # So the line count of an INCOMPLETE run is always 0 and of a complete run is
  # always 1 — a scale with no room on it for the distinction REV-145 wanted.
  # Run 142's item 3 confirmed it directly: `timeout 20` against the Modules
  # suite (a 122-second run) kept 0 bytes.
  #
  # ⭐ The measurement that DOES separate the two is ELAPSED TIME, and it costs
  #   two `date` calls. A budget that is too small shows a full 1800s; a suite
  #   that dies early shows seconds. That is what this should have printed all
  #   along, and REV-145's defect was reaching for a richer signal (the last
  #   line names the file!) that this printer does not produce.
  #
  # The partial is still kept: it costs one `cp`, the zero-bytes arm below reads
  # it, and a future printer change would make it informative again. What is
  # retired is the CLAIM that its line count means something.
  pest_partial=${TMPDIR:-/tmp}/last-pest-partial.txt
  cp "$ptmp" "$pest_partial" 2>/dev/null || true
  rm -f "$ptmp"
  log_gate pest "$pest_started" "$rc" "${pest_pid:--}"
  [ "${lock_held:-0}" -eq 1 ] && flock -u 9 2>/dev/null
  if [ $rc -eq 124 ]; then
    # ⛔ REV-149: THE MERGED JSON IS NOT OVERWRITTEN BY THE TIMEOUT NOTICE. Before
    #   the four-suite split a 124 meant the ONE run died and there was no number
    #   to keep, so this arm appended a `result: timeout` object and that object
    #   became tail -1. Now a 124 means ONE OF FOUR was killed and the other three
    #   have real numbers in the merged line — `missing` names which. Appending
    #   here would discard three measured suites to report the fourth, which is
    #   REV-145 §1's shape (a branch that destroys the evidence it exists to
    #   report) one level up.
    echo "  ✗ a testsuite hit its 600s budget — treat as red. Elapsed ${pest_elapsed}s for all four"
    echo "     (the lock WAIT is not counted here — it is above). The suite named in the merged"
    echo "     line's \"missing\" key is the one that was killed; its raw output is at $pest_suites/."
    echo "     ⚠ REV-147: this printer emits ONE line, at the END, so a killed suite keeps 0 bytes"
    echo "       whatever it did. The byte count is NOT evidence of where it stopped. Run 144 measured"
    echo "       Unit 4ms · Feature 27.9s · Modules 121.9s · Journeys 14.8s = 157s for all 2463 tests,"
    echo "       so a 600s budget on any one of them is 5x the slowest and a hit is a real finding."
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
  # REV-149: a suite that produced no JSON line at all is a SILENCE, and the merged
  # line still carries the other three — so the zero-bytes arm above can no longer
  # see it (out is never empty now). This is that arm, moved up one level.
  if printf '%s' "$out" | tail -1 | grep -q '"result":"incomplete"'; then
    echo "  ✗ a testsuite produced NO pest JSON line — see the per-suite summary above and"
    echo "     $pest_suites/. That is CLAUDE.md's zero-bytes trap: memory, or a missing Vite"
    echo "     manifest (confirm npm run build). Narrow it with --filter before debugging code."
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

bar "verdict"
if [ $fail -eq 0 ]; then
  echo "  gates green. Necessary, not sufficient — now read the diff and REPORT.md."
else
  echo "  ⛔ a gate failed above."
fi
exit $fail
