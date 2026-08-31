<?php

declare(strict_types=1);

namespace App\Services\Visibility;

use App\Models\GoogleRatingSnapshot;
use App\Services\Automation\AutomationRunRetention;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

/**
 * Deletes `google_rating_snapshots` rows past the period an operator has
 * stated — `TableHorizons`' rule for a table with no stated period: no seed,
 * and an unset period is a NO-OP rather than a zero (wave 38 lane D, on
 * `App\Services\Automation\AutomationRunRetention`'s exact precedent).
 *
 * ⛔ **THE FLOOR IS CLAMPED TO {@see ReviewLossDetection::PAUSE_FLAT_DAYS_KEY},
 * NEVER TO WHATEVER THE OPERATOR TYPES.** `ReviewLossDetection::flatRunStart()`
 * reads back up to that many days of history (plus its own seven-day buffer)
 * on every evaluation, so a stated retention period shorter than that would
 * silently make pause detection permanently unreachable — this file's own
 * mistake would look identical to the operator setting a short period on
 * purpose. This is a simpler shape than
 * {@see AutomationRunRetention}'s survivor rule: there
 * is no per-group row that must outlive every other, only a uniform recent
 * window nothing here may cut into.
 */
final class GoogleRatingSnapshotRetention
{
    public const string RETENTION_KEY = 'review_loss.snapshot_retention_days';

    /**
     * Rows per batch — `AutomationRunRetention::CHUNK`'s figure, for the same
     * reason: a limited DELETE on an indexed predicate that matches nothing on
     * an ordinary night.
     */
    private const int CHUNK = 500;

    /**
     * Slack past `ReviewLossDetection`'s own lookback window, matching that
     * class's identical buffer against a run that started exactly on the
     * boundary.
     */
    private const int FLOOR_BUFFER_DAYS = 7;

    public function __construct(private readonly DefaultsRegistry $defaults) {}

    /**
     * How many days a snapshot is kept, or null when nobody has said.
     *
     * ⛔ **NULL MEANS "DELETE NOTHING"** — `AutomationRunRetention::retentionDays()`'s
     * rule: the conservative direction on a deletion schedule is to delete
     * less, and reading an unset period as zero would delete every row on this
     * platform's first scheduled sweep after this class ships.
     */
    public function retentionDays(): ?int
    {
        $stated = $this->defaults->intOr(self::RETENTION_KEY, 0);

        if ($stated <= 0) {
            return null;
        }

        $floor = $this->defaults->int(ReviewLossDetection::PAUSE_FLAT_DAYS_KEY) + self::FLOOR_BUFFER_DAYS;

        return max($stated, $floor);
    }

    /**
     * Delete rows past the stated (and floor-clamped) period.
     *
     * ⚠️ **FAIL-CLOSED ON THE TENANT IN CONTEXT**, `AutomationRunRetention::prune()`'s
     * identical reason: `google_rating_snapshots` is `ENABLE`+`FORCE` row-level
     * secured on `app.business_id`, so a raw delete with no tenant established
     * would in any case match zero rows and report success — the silent
     * failure this codebase keeps finding, and why {@see PruneReviewLossSnapshots}
     * walks owners rather than issuing one platform-wide delete.
     */
    public function prune(?CarbonImmutable $now = null): int
    {
        Tenancy::idOrFail();

        $days = $this->retentionDays();

        if ($days === null) {
            return 0;
        }

        $cutoff = ($now ?? CarbonImmutable::now())->subDays($days);

        $deleted = 0;

        do {
            $batch = GoogleRatingSnapshot::query()
                ->where('captured_at', '<', $cutoff)
                ->limit(self::CHUNK)
                ->delete();

            $deleted += $batch;
        } while ($batch === self::CHUNK);

        return $deleted;
    }
}
