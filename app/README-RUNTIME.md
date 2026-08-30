# GOAIEZ — THE RUNTIME LAYER (§265)

Drop-in. Nothing here is optional; each file exists because a silent failure needs it.

| File | Put it | Why |
| :--- | :--- | :--- |
| `Procfile` | repo root | three process types, named: `web` · `worker` · `scheduler` |
| `deploy/supervisor.conf` | `/etc/supervisor/conf.d/` | ⛔ **scheduler `numprocs=1`** · workers carry **no `--tries`** · `stopwaitsecs > max-time` so a restart drains |
| `deploy/crontab` | only if supervisor is unavailable | ⛔ never run `schedule:work` and cron together |
| `app/Console/Commands/DeployCheckCommand.php` | `app/Console/Commands/` | ⛔⛔ **returns 1** on an unconfigured box |
| `tests/Runtime/RuntimeProofTest.php` | `tests/Runtime/` | ⭐ **R225** — the suite may not stay green on a dead runtime |
| `.github/workflows/ci.yml` | `.github/workflows/` | the six doctor stages + the runtime group run separately |
| `.gitattributes` | repo root | `eol=lf` — four packaging failures came from line endings |

## THE ONE COMMAND THAT SETTLES THE STATE TODAY
```
php artisan app:deploy-check
```
Nine checks. Any FAIL means the box is not deployed, whatever else is true.

## WHAT TO WIRE IN THE APP (three lines)
1. **Worker heartbeat** — in a `queue.looping` listener: `cache()->put('goaiez:worker:heartbeat', now(), 300)`.
   ⭐ It must fire on an EMPTY pass, or an idle queue and a dead worker look identical.
2. **Scheduler heartbeat + claim** — in `routes/console.php`:
   `cache()->put('goaiez:scheduler:heartbeat', now(), 300)` and claim
   `goaiez:scheduler:tick:{YmdHi}` with `cache()->add()`. A second scheduler fails to claim.
3. **`R226`** — `numbers:return-parked` is scheduled daily. **A parked number is CANCELLED after 30 days of non-use**, not held.
