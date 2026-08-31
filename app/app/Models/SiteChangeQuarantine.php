<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\SiteChangeActor;
use App\Services\Actuation\SiteChangeQuarantines;
use Database\Factories\SiteChangeQuarantineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One kind of change, resting on one site, because measuring it said it made
 * that site worse.
 *
 * ⛔ **ONLY {@see SiteChangeQuarantines} MAY TOUCH THIS MODEL**, enforced by an
 * `Architecture\ActuationTest` lint on `VoiceUsageEvent`'s and
 * `GbpGrantRevocationAttempt`'s precedent (4904, 5071). What a second writer
 * would skip is not tidiness: the release is three columns that must move
 * together and a partial unique index that makes *live* a state rather than a
 * pile of rows, and a caller writing `released_at` on its own would put an
 * automation back onto a customer's website with nobody's name against it.
 *
 * ⚠️ **A ROW HERE IS NOT A PROHIBITION ON THE OWNER.** It stops *this platform*
 * repeating a fix that measured badly; `29` §2 rule 44's advisory rung still
 * hands the same copy to the tenant to apply themselves, which is 5745's
 * reasoning one slice on — the site-writing path is gated absolutely and the
 * paste-it-yourself path is not.
 *
 * ⚠️ **NOTHING RELEASES THIS AUTOMATICALLY AND THAT IS DELIBERATE** (3796's
 * lesson, from the other end). A quarantine that lifts on a timer is not a
 * quarantine; a quarantine with no exit is a tenant stuck for ever. The exit is
 * `actuation:release-quarantine`, with a typed reason and a named actor.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $location_id
 * @property string $change_type
 * @property ?int $site_change_id
 * @property Carbon $quarantined_at
 * @property SiteChangeActor $quarantined_by
 * @property string $quarantined_reason
 * @property ?Carbon $released_at
 * @property ?string $released_by
 * @property ?string $released_reason
 * @property-read Location $location
 */
final class SiteChangeQuarantine extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<SiteChangeQuarantineFactory> */
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
     * Whether this fix is still resting.
     */
    public function isLive(): bool
    {
        return $this->released_at === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quarantined_at' => 'datetime',
            'quarantined_by' => SiteChangeActor::class,
            'released_at' => 'datetime',
        ];
    }
}
