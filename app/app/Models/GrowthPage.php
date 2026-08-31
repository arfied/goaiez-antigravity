<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\GrowthPageStatus;
use App\Enums\GrowthPageType;
use App\Services\Content\ContentQuality;
use App\Services\Content\GrowthPages;
use Database\Factories\GrowthPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A page this platform proposes to put on a tenant's own website
 * (`DATA-MODEL.md` §5.11).
 *
 * ⛔ **ONLY `App\Services\Content\GrowthPages` MAY TOUCH THIS MODEL**, enforced
 * by a `Architecture\ContentTest` lint on `SiteChange`'s precedent (5071, 5521).
 * `status` and `hold_until` together are what decide whether a page publishes,
 * and {@see GrowthPages::holdUntil()} is the only
 * thing that refuses to put a release time on a page the gate turned down. A
 * second writer is a second way that refusal is skipped.
 *
 * ⚠️ **A `draft` PAGE IS NOT THE SAME AS AN UNGATED ONE, AND THIS MODEL CANNOT
 * TELL YOU WHICH IT IS.** The verdict lives on a `content_quality_checks` row,
 * because that row carries the *reasons*; ask
 * {@see ContentQuality::clearedTheGate()}. Copying
 * the verdict onto this table would give the application two answers to "may
 * this publish?" that a job ordering could put out of step — `reviews.status`
 * versus `flagged_at` is the same trap, and 345 is where it was refused before.
 *
 * ⚠️ **`quality_score` IS A SUMMARY AND NOT A THRESHOLD** (5568). The gate
 * requires every check to pass, so nothing anywhere compares this number
 * against anything.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $location_id
 * @property GrowthPageType $type
 * @property string $slug
 * @property string $title
 * @property ?string $meta_description
 * @property string $content
 * @property ?string $target_keyword
 * @property ?int $quality_score
 * @property GrowthPageStatus $status
 * @property ?Carbon $hold_until
 * @property ?Carbon $published_at
 * @property-read Location $location
 */
final class GrowthPage extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<GrowthPageFactory> */
    use HasFactory;

    /**
     * ⚠️ **AN ALLOWLIST OF NOTHING, WHICH IS `Reply`'s ARGUMENT TAKEN ONE STEP
     * FURTHER** (1749). `$guarded = ['id', 'business_id']` would leave `status`
     * and `hold_until` mass-assignable — the two columns that decide whether
     * text this platform wrote appears on somebody else's website. `GrowthPages`
     * assigns every column as a property, so there is nothing to allow.
     * Factories are unaffected: `Factory::make()` runs inside `Model::unguarded()`.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Whether somebody has to look at this before it can go anywhere.
     */
    public function isHeld(): bool
    {
        return $this->status === GrowthPageStatus::Held;
    }

    /**
     * Whether this hold lapses on its own.
     *
     * ⛔ **THE DISTINCTION SLICE D DEPENDS ON.** A held page with no release
     * time waits for a person and must never be swept into publication by a
     * lapsed-hold sweep; one with a release time is AUTO-WITH-HOLD, which `29`
     * requires proceed on silence.
     */
    public function releasesOnSilence(): bool
    {
        return $this->isHeld() && $this->hold_until !== null;
    }

    /**
     * Whether this page's own hold window has closed.
     *
     * ⛔ **A HELD PAGE WITH NO RELEASE TIME IS NEVER DUE, WHATEVER THE CLOCK
     * SAYS** (5565). Two holds share the `held` case: one waits for a person and
     * one proceeds on silence, and this is the question the lapsed-hold sweep
     * asks. Reading `hold_until <= now` on its own would answer *yes* for the
     * gate's own hold, whose null is a fact rather than a missing value.
     */
    public function holdHasLapsed(): bool
    {
        return $this->releasesOnSilence() && $this->hold_until?->isPast() === true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => GrowthPageType::class,
            'quality_score' => 'integer',
            'status' => GrowthPageStatus::class,
            'hold_until' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
