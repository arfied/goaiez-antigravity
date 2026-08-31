<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\ReviewRequestCampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A review-request campaign (DATA-MODEL §5.7). Sends ride the consent lanes
 * — the campaign never overrides suppression or the frequency cap.
 */
final class ReviewRequestCampaign extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ReviewRequestCampaignFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'schedule' => 'array',
            'audience_filter' => 'array',
            'frequency_cap_days' => 'integer',
            'requests_sent' => 'integer',
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
