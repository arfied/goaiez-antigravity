<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\ClickDeviceClass;
use App\Enums\ClickDiscardReason;
use Database\Factories\ShortLinkClickFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One fetch of a short link — counted or not.
 *
 * ⛔ **`counted = false` IS NOT NOISE AND MUST NOT BE FILTERED OUT AT WRITE
 * TIME.** Carriers, link checkers and messaging apps fetch every link we send;
 * see the creating migration for why storing only the counted ones would leave
 * nobody able to explain a campaign's numbers, and would hide the day the filter
 * starts discarding real people.
 *
 * ⚠️ **NO IP, NO USER-AGENT STRING, NO HEADER DUMP.** A bucketed device class
 * and two flags. `CLAUDE.md` permits device signals for bucketing and bot
 * scoring only, never concatenated into something stable, and the defence here
 * is that there is nothing to concatenate.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $short_link_id
 * @property ?int $customer_id
 * @property Carbon $clicked_at
 * @property bool $counted
 * @property ?ClickDiscardReason $discard_reason
 * @property ClickDeviceClass $device_class
 */
final class ShortLinkClick extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ShortLinkClickFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'short_link_id',
        'customer_id',
        'clicked_at',
        'counted',
        'discard_reason',
        'device_class',
    ];

    /**
     * @return BelongsTo<ShortLink, $this>
     */
    public function shortLink(): BelongsTo
    {
        return $this->belongsTo(ShortLink::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
            'counted' => 'boolean',
            'discard_reason' => ClickDiscardReason::class,
            'device_class' => ClickDeviceClass::class,
        ];
    }
}
