#!/usr/bin/env bash
#
# STEP 0 — make `doctor` runnable. One command.
#
# ⛔⛔ WHY THIS FILE EXISTS
#
# 31 real PHP files were written for this platform and NOT ONE HAS EVER RUN.
# Every claim in the master plan about what the code does is a claim about
# DOCUMENTS, not about code. `doctor` is the program that settles the
# difference, and it has never executed.
#
# The estimate for doing this by hand was ~1.5 days. This script is the same
# work, ordered, with each step checked. It does not need a developer — it
# needs a machine with PHP 8.2+, Postgres and Redis reachable.
#
# ⭐ IT IS SAFE TO RE-RUN. Every step is idempotent and nothing is destructive.
#
#   bash install-step-0.sh
#
set -euo pipefail

say()  { printf '\n\033[1m▸ %s\033[0m\n' "$*"; }
ok()   { printf '  ✅ %s\n' "$*"; }
warn() { printf '  ⚠️  %s\n' "$*"; }
die()  { printf '  ⛔ %s\n' "$*" >&2; exit 1; }

# ─────────────────────────────────────────────────────────────────────
say "0/7  Preconditions — checked, never assumed"

command -v php >/dev/null || die "php not found. Install PHP 8.2 or newer."
PHPV=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
php -r 'exit(version_compare(PHP_VERSION,"8.2.0","<") ? 1 : 0);' || die "PHP $PHPV is too old — 8.2+ required."
ok "php $PHPV"

# ⭐ Composer may be global OR a composer.phar sitting in the tree. The first
#   version demanded the global one and DIED on a machine that had the phar
#   right there — a precondition that was true in spirit and false in fact.
COMPOSER=""
if command -v composer >/dev/null 2>&1; then
  COMPOSER="composer"
elif [ -f composer.phar ]; then
  COMPOSER="php composer.phar"
  ok "using ./composer.phar (no global composer)"
elif [ -f /usr/local/bin/composer ]; then
  COMPOSER="php /usr/local/bin/composer"
else
  die "composer not found. Install it, or drop composer.phar in this directory.
     https://getcomposer.org/download/"
fi
ok "composer present"

[ -f artisan ] || die "No artisan file here. Run this from the Laravel project root."
ok "laravel project root"

# ⛔⛔⛔ THE PACKAGE FILES MUST BE IN THE LARAVEL ROOT.
#
# FIVE classes read them from base_path():
#   module:scaffold · capabilities:scaffold · find · why · CitationStage
#
# Without them: the scaffold generates ZERO manifests and reports "0 modules"
# as though the system were empty, CitationStage refuses every build, and `why`
# cannot resolve a single rule. Nothing errors loudly — it all just reads as a
# system with nothing in it.
if [ ! -f GOAIEZ-MASTER-PLAN.md ]; then
  if [ -d "${GOAIEZ_PACKAGE:-../package}" ] && ls "${GOAIEZ_PACKAGE:-../package}"/GOAIEZ-*.md >/dev/null 2>&1; then
    cp "${GOAIEZ_PACKAGE:-../package}"/GOAIEZ-*.md .
    ok "copied $(ls GOAIEZ-*.md | wc -l) package files from ${GOAIEZ_PACKAGE:-../package}"
  else
    die "GOAIEZ-MASTER-PLAN.md is not in this directory.
     Copy the 14 GOAIEZ-*.md package files into the Laravel root, or set
     GOAIEZ_PACKAGE=/path/to/them and re-run.
     ⛔ Without them the scaffold reports '0 modules' instead of failing."
  fi
else
  ok "package files present ($(ls GOAIEZ-*.md 2>/dev/null | wc -l) files)"
fi

# ─────────────────────────────────────────────────────────────────────
say "1/8  Dependencies and registration"
$COMPOSER install --no-interaction --prefer-dist
ok "composer install"

# ⛔⛔⛔ REGISTER THE PROVIDER, OR NOTHING IN THIS BUNDLE EXISTS.
#
# 13 commands, 7 doctor stages, 12 journeys — and without registration Laravel
# knows about none of them. Every step below would succeed and then
# `php artisan doctor` would report "command not found".
#
# Laravel 11+ reads bootstrap/providers.php; Laravel 10 and earlier read
# config/app.php. Handle both, because the live tree's version is not ours to
# assume.
PROVIDER='App\Providers\GoaiezRuntimeServiceProvider'
if [ -f bootstrap/providers.php ]; then
  if ! grep -q "GoaiezRuntimeServiceProvider" bootstrap/providers.php; then
    php -r '
      $f = "bootstrap/providers.php";
      $s = file_get_contents($f);
      $s = preg_replace("/\];\s*$/", "    App\\Providers\\GoaiezRuntimeServiceProvider::class,
];
", $s, 1);
      file_put_contents($f, $s);
    '
    ok "provider registered in bootstrap/providers.php (Laravel 11+)"
  else
    ok "provider already registered"
  fi
elif [ -f config/app.php ]; then
  grep -q "GoaiezRuntimeServiceProvider" config/app.php     && ok "provider already registered"     || warn "Laravel 10 detected — add App\Providers\GoaiezRuntimeServiceProvider::class to config/app.php providers[]"
else
  die "Neither bootstrap/providers.php nor config/app.php found. Not a Laravel root?"
fi

# ⭐ New classes need the autoloader refreshed, or they are invisible files.
$COMPOSER dump-autoload -o
ok "autoloader refreshed"

# ⛔ Prove the registration WORKED before doing anything that depends on it.
#    Trusting a grep is how "claimed but never applied" happens.
php artisan list 2>/dev/null | grep -qE '^\s+doctor' \
  && ok "artisan can see the runtime commands" \
  || die "artisan does NOT see the commands. Registration failed — stop here."

# ─────────────────────────────────────────────────────────────────────
say "2/8  Environment"
if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate --ansi
  ok ".env created and key generated"
else
  ok ".env already present — left alone"
fi

# ⛔⛔ THE SINGLE MOST IMPORTANT LINE IN THIS FILE.
#
# Laravel's default queue driver is `sync`: queued jobs run inline, instantly,
# in the same process. Every test passes. No worker is needed. And the whole
# platform's asynchronous behaviour is a fiction.
#
# That is exactly how "nothing runs the queue" stayed invisible for months
# while the suite was green. A journey on `sync` proves nothing at all.
if grep -qE '^QUEUE_CONNECTION=sync' .env 2>/dev/null; then
  sed -i.bak 's/^QUEUE_CONNECTION=sync/QUEUE_CONNECTION=database/' .env
  warn "QUEUE_CONNECTION was 'sync' — changed to 'database'."
  warn "On 'sync' the journeys would pass with NO WORKER RUNNING, which proves nothing."
else
  ok "queue driver is not sync"
fi

# ─────────────────────────────────────────────────────────────────────
say "3/8  Database"
php artisan migrate --force
ok "migrations applied"

# ⭐ RLS FORCE is the whole tenant boundary. Postgres applies row-level
#   security to normal roles but NOT to the table owner or a superuser —
#   so a policy can look perfect and still be bypassed by the very
#   connection the app uses. FORCE closes that, and the schema stage
#   asserts it.
# ⛔⛔ THE TENANT BOUNDARY, AND WHY IT IS NOT OPTIONAL.
#
# Postgres applies row-level security to ordinary roles but NOT to the table
# OWNER and NOT to a superuser — and Laravel usually connects as the owner. So a
# policy can be written perfectly, reviewed, hand-tested, and bypassed entirely
# by the exact connection the application uses.
#
# FORCE closes that. Without this step SchemaStage checks a database where RLS
# was never enabled, finds nothing to complain about, and reports clean.
php artisan db:bootstrap --force && ok "roles + RLS FORCE + the R233 grant constraint" \
  || die "db:bootstrap FAILED. Do not continue — the tenant boundary is not enforced."

php artisan queue:table --quiet 2>/dev/null || true
php artisan migrate --force
ok "queue tables"

# ─────────────────────────────────────────────────────────────────────
say "4/8  Generate the module contracts"

# ⛔ 119 headers live in a 3 MB markdown plan. `doctor` reads
#   app/Modules/<id>/manifest.php. Without this step there are ZERO of those
#   files, every check passes vacuously, and `brief` has nothing to assemble.
php artisan module:scaffold --dry-run
printf '\n  Proceed and WRITE these files? [y/N] '
read -r reply
case "$reply" in
  [yY]*) php artisan module:scaffold && ok "manifests written" ;;
  *)     warn "skipped — doctor will find no modules and pass vacuously" ;;
esac

php artisan capabilities:scaffold --dry-run
printf '\n  Write capabilities.php for every module? [y/N] '
read -r reply2
case "$reply2" in
  [yY]*) php artisan capabilities:scaffold && ok "capabilities written" ;;
  *)     warn "skipped — LAW 128's floor will refuse every brief" ;;
esac

# ─────────────────────────────────────────────────────────────────────
say "5/8  Prove the runtime is real"

# ⭐ These are expected to FAIL on a fresh box, and that is the point:
#   a green suite that proves nothing is worse than a red one that proves
#   something. A test that supplies its own queue is not a test.
php artisan test --group=runtime || warn "runtime proofs FAILED — read them; each names what is missing."

# ⛔⛔ The journeys will ERROR, not fail, until the harness is implemented — and
#    that is correct. Every harness method throws a named RuntimeException
#    saying what it must do against the REAL transport.
#
# ⭐ Do NOT stub them to get green. A stub makes all twelve journeys pass while
#   touching no carrier, no gateway, no queue — and journeys are the ONLY checks
#   that cross a module seam.
php artisan test --group=journeys 2>&1 | head -30 \
  || warn "journeys not runnable yet — implement tests/Journeys/JourneyHarness.php against real transports."

# ─────────────────────────────────────────────────────────────────────
say "6/8  The four patches"
php artisan test --group=patches || warn "patch tests FAILED — expected. They DEFINE the four patches; make them pass."

# ─────────────────────────────────────────────────────────────────────
say "7/8  doctor — for the first time"
php artisan doctor || true

# ─────────────────────────────────────────────────────────────────────
say "8/8  Smoke-test the read commands"

# ⭐⭐ These have never executed. A command that loads but crashes on its first
#    real input is indistinguishable from one that works, until an agent needs
#    it at 3am. So: run each once, against real data, now.
#
# ⛔ `impact` is expected to REFUSE if the graph has holes — that is N-263-02
#    working, not a failure. Read what it says.
php artisan map            | head -20 || warn "map failed — read the error."
php artisan map --events   | head -12 || warn "map --events failed."
php artisan map --tables   | head -12 || warn "map --tables failed."
php artisan find pixel     | head -12 || warn "find failed."
php artisan context X-110  | head -20 || warn "context failed."
php artisan why P-163      | head -8  || warn "why failed."
php artisan impact X-110   | head -20 || warn "impact refused or failed — read it."

# ⭐ N-263-01, executable: asking for a partial id must REFUSE, never
#   helpfully resolve. A fuzzy matcher answers `X-18` with `X-188`.
echo
echo "  N-263-01 check — a partial id must be REFUSED:"
if php artisan context X-18 >/dev/null 2>&1; then
  die "context resolved a PARTIAL id. That is how X-180 once returned X-186."
else
  ok "context refused a partial id, as N-263-01 requires"
fi

cat <<'DONE'

────────────────────────────────────────────────────────────────
  ⭐ STEP 0 COMPLETE.

  What just changed: `doctor` has now RUN. Until this moment every
  claim about what the code does was a claim about DOCUMENTS.

  ⛔ Expect failures. They are the point — each violation carries the
     FIX that resolves it. Work them in this order:

     1. doctor stages 0-3   fail the COMMIT   — fix before merging
     2. stages 4-5          fail the MERGE
     3. stage 6 journeys    fails the WAVE    — the only checks that
                                                cross a module seam

  ⭐ THE LOOP — this is the part that TERMINATES:

       php artisan doctor                    # a number. your progress bar.
       php artisan doctor --stage=boundary   # ONE stage at a time
       ... fix, using each violation's own `fix` text ...
       php artisan doctor --stage=boundary   # ⭐ CONFIRM THE COUNT FELL

  ⛔⛔ IF THE COUNT DID NOT FALL, STOP. Your fix did not land.
     An edit that reports success and changes nothing happened FIVE TIMES
     while this package was written. It is the most common failure here.

  ⛔⛔⛔ THE THREE WAYS TO CHEAT — each worse than the violation:
       · delete the assertion      -> the requirement is GONE (P-210)
       · stub the journey harness  -> 12 journeys pass touching NOTHING
       · widen an @ingress list    -> the check is silenced, the bug stays

     The test for any fix: DID THE SYSTEM CHANGE, OR DID THE CHECK CHANGE?

  ⭐ DONE is four numbers:
       doctor                    -> All stages clean.
       doctor:module-done x122   -> 122 DONE
       test --group=journeys     -> 12 green, each with an EXTERNAL id
       brief x122                -> 0 refusals

  Full runbook: GOAIEZ-THE-DOCTOR-LOOP.md
────────────────────────────────────────────────────────────────
DONE
