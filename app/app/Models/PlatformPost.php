<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\PlatformPostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A post published to GBP or social (DATA-MODEL §5.11). AI writes posts —
 * never reviews.
 */
final class PlatformPost extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<PlatformPostFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

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
            'hold_until' => 'datetime',
            'scheduled_for' => 'datetime',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
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
     * @return BelongsTo<Review, $this>
     */
    public function sourceReview(): BelongsTo
    {
        return $this->belongsTo(Review::class, 'source_review_id');
    }
}
