<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\ReviewDestination;
use Database\Factories\ReviewDestinationSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One destination's settings for one location.
 *
 * The model is one destination's settings; the table is the set — the same shape
 * SuppressionListEntry has over `suppression_list`. The name differs from the
 * enum's because both cannot be `ReviewDestination`, and the enum is the one
 * every caller says out loud.
 *
 * `destination` and `invite_threshold` are guarded, and that is not tidiness.
 * They are DestinationSettings' to write — the same treatment slice A gave the
 * derived consent columns — because a screen that can mass-assign a threshold is
 * a screen that can mass-assign a Trustpilot threshold, which is the one number
 * that is not ours to set.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $location_id
 * @property ReviewDestination $destination
 * @property bool $enabled
 * @property int $invite_threshold
 * @property ?string $link_url
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
final class ReviewDestinationSetting extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ReviewDestinationSettingFactory> */
    use HasFactory;

    protected $table = 'review_destinations';

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id', 'destination', 'invite_threshold'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'destination' => ReviewDestination::class,
            'enabled' => 'boolean',
            'invite_threshold' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
