<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\ActuationTier;
use App\Enums\SpeedFix;
use App\Enums\SpeedFixStatus;
use App\Services\Actuation\SpeedFixes;
use Database\Factories\SpeedChangeSetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One of `28` §4.1's seven speed fixes, applied once to one site, with the
 * evidence §4.3 judged it on.
 *
 * ⛔ **ONLY {@see SpeedFixes} MAY TOUCH THIS MODEL**, on `SiteChange`'s and
 * `SiteChangeQuarantine`'s precedent (5521, 5812) and enforced by an
 * `Architecture\ActuationTest` lint. What a second writer would skip is not
 * tidiness: this row is what makes §4.3's *"one at a time per site with ≥48h
 * between"* true, and a caller inserting one without going through the service
 * puts a second fix on a site whose first fix is still being measured — which
 * makes the next regression attributable to the wrong change, on somebody else's
 * website.
 *
 * ⚠️ **IT NEVER CARRIES THE CHANGE ITSELF.** Both sides of the edit live on the
 * `site_changes` row this points at, which is where rule 32's snapshot is and
 * where the undo path reads from. What is here is the *speed* half: which fix,
 * what the site looked like before and after in vitals, and what was decided.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $location_id
 * @property SpeedFix $fix_key
 * @property ActuationTier $tier
 * @property int $change_set_id
 * @property ?array<string, mixed> $baseline
 * @property ?array<string, mixed> $result
 * @property SpeedFixStatus $status
 * @property Carbon $decided_at
 * @property ?Carbon $applied_at
 * @property int $revert_attempts
 * @property ?Carbon $revert_attempt_after
 * @property ?Carbon $revert_attempts_exhausted_at
 * @property-read Location $location
 */
final class SpeedChangeSet extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<SpeedChangeSetFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Is this fix on the site and still inside its window?
     */
    public function isInFlight(): bool
    {
        return $this->applied_at !== null && $this->result === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fix_key' => SpeedFix::class,
            'tier' => ActuationTier::class,
            'status' => SpeedFixStatus::class,
            'baseline' => 'array',
            'result' => 'array',
            'decided_at' => 'datetime',
            'applied_at' => 'datetime',

            // ⚠️ **6265's THREE, ON THE `RevertFailed` ARM OF
            // `SpeedFixes::dueForJudgement()`.** 5861 records that slice H's own
            // retry cannot see these rows, so the bound has to exist twice.
            'revert_attempt_after' => 'datetime',
            'revert_attempts_exhausted_at' => 'datetime',
        ];
    }
}
