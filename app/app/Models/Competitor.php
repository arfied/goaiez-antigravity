<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A nearby peer proposed from Places for one location (`28` §5.5).
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $location_id
 * @property string $place_id
 * @property string $name
 * @property string $source
 * @property ?string $website_url
 * @property bool $confirmed
 */
class Competitor extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return HasMany<CompetitorSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(CompetitorSnapshot::class);
    }

    /**
     * @return HasOne<CompetitorSnapshot, $this>
     */
    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(CompetitorSnapshot::class)->latestOfMany('captured_at');
    }

    /**
     * @return HasOne<CompetitorSiteNote, $this>
     */
    public function siteNote(): HasOne
    {
        return $this->hasOne(CompetitorSiteNote::class);
    }
}
