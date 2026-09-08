#!/usr/bin/env bash
# deploy.sh — everything after `git pull`. Run as `composer deploy` from the app root in PRODUCTION.
#
#   git pull && composer deploy
#
# ORDER, AND WHY IT IS THIS ORDER:
#   0  refuse unless this is a production checkout with a clean tree     (cheap, before anything)
#   1  record the sha we are leaving — that plus the backup IS the rollback
#   2  maintenance mode ON, with a trap that turns it OFF on every exit path
#   3  pg_dump the database                                              ← BEFORE migrate: the one irreversible step
#   4  composer install --no-dev                                          new code needs its deps first
#   5  migrate --force
#   6  php artisan app:deploy-check                                       ← AFTER migrate: it reads platform_credentials
#                                                                          and phone_numbers, which may not exist before
#   7  seal check (doctor --stage=integrity)                              the checker on disk is the one that was shipped
#   8  caches: config / route / view / event
#   9  queue:restart                                                      cron's queue:work exits; cron relaunches it
#  10  maintenance mode OFF (the trap does this), print the summary
#
# WHAT IT REFUSES, and each refusal is a real incident this project has had:
#   - a dirty working tree      a hand edit in prod that `git pull` silently merged over
#   - no .env                   this is not a configured box
#   - DB_CONNECTION != pgsql    the backup below is pg_dump; refusing beats a backup that did not happen
#   - a failed backup           an unbacked migrate against goaiez_antig, whose schema has already been dropped once
#   - deploy-check rc != 0      its own header: "IT RETURNS 1. IT IS NOT A WARNING"
#   - integrity red             a checker that does not match its seal
#
# ROLLBACK is printed at the end and again on failure. It is two commands: git checkout <sha-before>
# and a psql restore of the dump. Nothing here tries to automate the rollback — a script that
# undoes a half-applied migrate is more dangerous than a human reading the summary.

set -euo pipefail
cd "$(dirname "$0")"

say()  { printf '\n\033[1m== %s\033[0m\n' "$*"; }
die()  { printf '\n\033[31m⛔ %s\033[0m\n' "$*" >&2; exit 1; }

# ── 0. preconditions ──────────────────────────────────────────────────────────────────
say "0. preconditions"
[ -f .env ]        || die "no .env here — this is not a configured checkout"
[ -d .git ] || git rev-parse --git-dir >/dev/null 2>&1 || die "not a git checkout — 'after pull' needs one (see the one-time setup)"
APP_ENV=$(grep -E '^APP_ENV=' .env | cut -d= -f2- | tr -d '"'"'" ) ; echo "  APP_ENV=$APP_ENV"
[ "$APP_ENV" = production ] || die "APP_ENV is '$APP_ENV', not production — refusing to run a production deploy here"
dirty=$(git status --porcelain --untracked-files=no | grep -v '^ M storage/' || true)
[ -z "$dirty" ] || { printf '%s\n' "$dirty"; die "working tree is dirty — a hand edit in prod. Commit or discard it first; a pull may already have merged over it"; }
DB_CONN=$(grep -E '^DB_CONNECTION=' .env | cut -d= -f2- | tr -d '"'"'")
[ "$DB_CONN" = pgsql ] || die "DB_CONNECTION is '$DB_CONN'; this script backs up with pg_dump and refuses to migrate without a backup"
echo "  ok"

# ── 1. the sha we are leaving ─────────────────────────────────────────────────────────
say "1. rollback point"
TO=$(git rev-parse --short HEAD)
# `git pull` sets ORIG_HEAD to the HEAD it moved away from — that is the sha to roll back to.
# HEAD@{1} is the reflog fallback if the caller fetched+merged some other way.
FROM=$(git rev-parse --short ORIG_HEAD 2>/dev/null || git rev-parse --short 'HEAD@{1}' 2>/dev/null || echo "$TO")
echo "  deploying $TO   rollback point: $FROM"
[ "$FROM" != "$TO" ] || echo "  (no pull detected — FROM == TO; rollback would be a no-op for code, the backup still covers the database)"
git log -1 --format='  %h  %ad  %s' --date=format:'%Y-%m-%d %H:%M' | cut -c1-110

# ── 2. maintenance mode, and the trap that always lifts it ────────────────────────────
say "2. maintenance mode"
php artisan down --retry=30 >/dev/null && echo "  down"
UP_DONE=0
finish() {
  rc=$?
  if [ "$UP_DONE" = 0 ]; then php artisan up >/dev/null 2>&1 && echo "  (maintenance mode lifted)"; fi
  if [ $rc -ne 0 ]; then
    printf '\n\033[31m⛔ DEPLOY FAILED (rc=%s). The site is back up on whatever state it reached.\033[0m\n' "$rc"
    [ -n "${BACKUP:-}" ] && printf 'ROLLBACK:\n  git checkout %s\n  psql "$DATABASE_URL" < %s\n  composer install --no-dev && php artisan config:cache && php artisan queue:restart\n' "$FROM" "$BACKUP"
  fi
}
trap finish EXIT

# ── 3. backup — BEFORE the irreversible step ──────────────────────────────────────────
say "3. database backup (pg_dump, before migrate)"
DB_HOST=$(grep -E '^DB_HOST=' .env | cut -d= -f2- | tr -d '"'"'"); DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=$(grep -E '^DB_PORT=' .env | cut -d= -f2- | tr -d '"'"'"); DB_PORT=${DB_PORT:-5432}
DB_NAME=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"'"'")
DB_USER=$(grep -E '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"'"'")
DB_PASS=$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"'"'")
[ -n "$DB_NAME" ] || die "DB_DATABASE is empty in .env"
mkdir -p storage/backups
BACKUP="storage/backups/${DB_NAME}-$(date +%Y%m%d-%H%M%S)-pre-${TO}.sql"
PGPASSWORD="$DB_PASS" pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" --no-owner --no-privileges "$DB_NAME" > "$BACKUP" \
  || die "pg_dump failed — NOT migrating without a backup"
sz=$(stat -c %s "$BACKUP"); [ "$sz" -gt 10000 ] || die "backup is only ${sz} bytes — that is not a database; NOT migrating"
echo "  $BACKUP  ($((sz/1024)) KB)"
export DATABASE_URL="postgresql://${DB_USER}:${DB_PASS}@${DB_HOST}:${DB_PORT}/${DB_NAME}"

# ── 4. dependencies ───────────────────────────────────────────────────────────────────
say "4. composer install --no-dev"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist 2>&1 | tail -3 | sed 's/^/  /'

# ── 5. migrate ────────────────────────────────────────────────────────────────────────
say "5. migrate"
pending=$(php artisan migrate:status --pending 2>/dev/null | grep -c 'Pending' || true)
echo "  pending migrations: $pending"
php artisan migrate --force --no-interaction 2>&1 | tail -15 | sed 's/^/  /'

# ── 6. the gate — AFTER migrate, because it reads tables the migrations create ────────
say "6. app:deploy-check"
if php artisan app:deploy-check; then echo "  ok"; else die "app:deploy-check returned non-zero — it is not a warning. Read its output above."; fi

# ── 7. the seal ───────────────────────────────────────────────────────────────────────
say "7. checker seal"
php artisan doctor --stage=integrity 2>&1 | grep -E '^ (ok|FAIL) ' | sed 's/^/  /' || true
php artisan doctor --stage=integrity >/dev/null 2>&1 || die "integrity is red — the checker on disk does not match seals.json"

# ── 8. caches ─────────────────────────────────────────────────────────────────────────
say "8. caches"
php artisan config:cache  >/dev/null && echo "  config"
php artisan route:cache   >/dev/null && echo "  route"
php artisan view:cache    >/dev/null && echo "  view"
php artisan event:cache   >/dev/null && echo "  event"
php artisan storage:link  >/dev/null 2>&1 || true

# ── 9. workers pick up the new code ───────────────────────────────────────────────────
say "9. queue:restart"
php artisan queue:restart >/dev/null && echo "  signalled — cron's queue:work exits and relaunches within a minute"

# ── 10. up ────────────────────────────────────────────────────────────────────────────
say "10. up"
php artisan up >/dev/null && UP_DONE=1 && echo "  site is live"

say "DEPLOYED $TO"
echo "  backup   : $BACKUP"
echo "  seal     : $(php artisan doctor:selftest 2>/dev/null | grep -oE 'seal digest [0-9a-f]{16}' || echo '?')"
echo "  rollback : git checkout $FROM && psql \"\$DATABASE_URL\" < $BACKUP && composer install --no-dev && php artisan config:cache && php artisan queue:restart"
