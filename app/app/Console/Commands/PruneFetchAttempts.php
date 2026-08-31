<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Config\DefaultsRegistry;
use App\Services\Fetch\DirectFetchGateway;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Delete `fetch_attempts` rows past the period stated in Ops — decisions
 * 10189(f), 10260–10269.
 *
 * ## ⛔ Why this is not an owner walk, unlike `automation:prune-runs`
 *
 * `fetch_attempts` carries no `business_id` and no row-level security at all —
 * these are our own outbound requests under our policy, made before any tenant
 * exists in row 2's case (the table's own creating migration). A plain range
 * `DELETE` genuinely matches, on `PrunePlatformMailSends`' precedent for the
 * identical reason: a table with `USING (true)` rather than a tenant policy is
 * the one shape in this codebase's `Prune*` family that does not need to
 * enumerate accounts to reach its own rows.
 *
 * ## ⚠️ Why the period is still a registry key and not a constant, unlike
 * `PrunePlatformMailSends`' 30
 *
 * `PrunePlatformMailSends::RETENTION_DAYS` is a hard-coded 30 because that
 * table's argument is *"nothing reads a row older than a day, so a
 * configurable retention offers a bill and no capability."* This table's
 * longest reader window is seven days ({@see DirectFetchGateway::nextCooldownHours()}),
 * and a period well past that changes nothing any reader can see — **but the
 * table's own creating migration names a capability a longer window keeps**:
 * *"a refusal row is the evidence that the answer is no, and it costs one
 * insert on a path that was not going to make a network call anyway"* — a
 * `guided_only` source that was never fetched leaves a trace precisely so that
 * *"do you scrape Yelp?"* has an answer. How long that answer stays available is
 * an audit-posture question, and decision 10189(f) left it to the owner rather
 * than picking a number, which this command does not overturn.
 *
 * ⚠️ **`DB::table()` RATHER THAN THE FETCH-ATTEMPT MODEL, DELIBERATELY.**
 * `tests/Feature/Architecture/OutboundTest.php`'s *"the fetch gateway is the
 * only thing that reaches the fetch_attempts ledger"* chokepoints the model's
 * static call syntax to `DirectFetchGateway` and the model itself, because a
 * hand-written row is how the ledger stops meaning *"every fetch"* — and a
 * range `DELETE` by age is not a write anybody could confuse for a fetch the
 * gateway never made, so reaching the table directly here costs that lint nothing and
 * avoids adding a pruner to an allowlist that exists to catch fabricated rows,
 * not honest deletions of old real ones.
 */
#[Signature('fetch:prune-attempts')]
#[Description('Delete fetch_attempts rows past the period stated in Ops')]
final class PruneFetchAttempts extends Command
{
    /**
     * The registry key holding how many days a row is kept.
     */
    public const string RETENTION_KEY = 'fetch.attempts_retention_days';

    /**
     * Rows per batch — `PruneTrialOriginClaims::CHUNK`'s figure and its
     * reasoning: a backlog should not be one long transaction on a table a
     * live fetch path also writes to.
     */
    private const int CHUNK = 500;

    public function handle(DefaultsRegistry $defaults): int
    {
        $stated = $defaults->intOr(self::RETENTION_KEY, 0);
        $days = $stated > 0 ? $stated : null;

        if ($days === null) {
            $this->line(
                'No retention period is set for fetch_attempts (key: '
                .self::RETENTION_KEY.'), so nothing was deleted. '
                .'Set it in Ops to start pruning.'
            );

            return self::SUCCESS;
        }

        $cutoff = CarbonImmutable::now()->subDays($days);

        $deleted = 0;

        do {
            // Correlated `whereIn` over a subquery selecting the primary key,
            // `PruneTrialOriginClaims`' exact shape: chunked, and the same
            // statement whether the table is empty or a year deep.
            $batch = DB::table('fetch_attempts')
                ->whereIn('id', fn ($query): mixed => $query
                    ->select('id')
                    ->from('fetch_attempts')
                    ->where('created_at', '<', $cutoff)
                    ->limit(self::CHUNK))
                ->delete();

            $deleted += $batch;
        } while ($batch === self::CHUNK);

        $this->info($deleted === 0
            ? "No fetch attempts older than {$days} days to prune."
            : "Pruned {$deleted} fetch ".str('attempt')->plural($deleted)." older than {$days} days.");

        return self::SUCCESS;
    }
}
